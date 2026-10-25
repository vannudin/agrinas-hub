<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\BarangHargaGrosir;
use App\Models\Koperasi;
use App\Models\Pasokan;
use App\Models\Pesanan;
use App\Models\PesananItem;
use App\Models\PesananTindakan;
use App\Models\Saldo;
use App\Models\User;
use App\Support\Halaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Toko barang: katalog bergaya toko daring, keranjang, checkout, dan pesanan.
 *
 * Dua kanal pembayaran:
 *   AGRN — Saldo Agrinas (modal belanja Rp 400 juta, TIDAK bisa ditarik):
 *          dipotong saat checkout, invoice terbit, tidak menyentuh kas koperasi.
 *   KAS  — kas koperasi: dicatat admin sebagai pengeluaran nyata saat ditandai
 *          dibayar (kanal='KAS', ref=nomor invoice) supaya tidak tercampur
 *          dengan biaya operasional biasa.
 *
 * Stok gudang Agrinas dipotong saat pesanan dibuat (pesanan = pemesanan stok)
 * dan dipulihkan bila pesanan dibatalkan — supaya tidak ada dua pembeli
 * membayar barang yang sama.
 */
class TokoController extends Controller
{
    private const KERANJANG = 'toko_keranjang';

    /** Produk per muat di etalase. Kisi kartu, jadi lebih banyak dari tabel kerja. */
    private const PER_KATALOG = 24;

    // ------------------------------------------------------------------ katalog

    public function katalog(Request $request)
    {
        $u = $request->user();
        // Katalog hanya untuk manajer koperasi. Admin & PIC tidak berbelanja:
        // mereka melihat pesanan koperasi binaannya di /toko/pesanan.
        if (! $u->manajerKoperasi()) {
            return redirect()->route('toko.pesanan');
        }
        $kop = $this->koperasiBelanja($request);

        return view('toko.katalog', $this->bagianKatalog($request) + [
            'aktif' => 'toko',
            'keranjang' => $this->rincianKeranjang($request),
            'saldos' => $this->saldoKoperasi($u), 'koperasiPilihan' => $this->koperasiPilihan($u),
        ]);
    }

    /**
     * Bagian katalog yang dipakai DUA jalur: halaman penuh & potongan gulir.
     *
     * SEJAK 2026-10-21 etalase adalah SATU daftar, bukan dua bagian terpisah.
     * Dulu barang gudang dan produk lokal tampil di dua kisi dengan judul
     * masing-masing; akibatnya halaman punya DUA penghitung ("0-0 dari 0
     * produk"), dua penanda "sudah habis", dan pada ponsel dua blok besar yang
     * membuat halaman terasa berantakan — padahal bagi pembeli keduanya sama
     * saja: barang yang bisa dibeli.
     *
     * Penyaringnya pun berubah: bukan lagi "asal" (gudang/lokal), melainkan
     * JENIS yang dikenal pembeli — Subsidi, Lokal & UMKM, Non Subsidi
     * (Barang::JENIS_TOKO). Kategori gudang (Pangan/Benih/Pupuk) tetap ada
     * untuk halaman Kelola Katalog, tapi TIDAK dipakai sebagai penyaring
     * etalase: pembeli tidak mencari "Pupuk", ia mencari "yang disubsidi".
     */
    private function bagianKatalog(Request $request): array
    {
        $cari = trim((string) $request->query('q', ''));
        $jenis = (string) $request->query('jenis', '');
        // Nilai asing dianggap "semua": URL yang salah ketik tidak boleh
        // menghasilkan halaman kosong yang membingungkan.
        if ($jenis !== '' && ! array_key_exists($jenis, Barang::JENIS_TOKO)) {
            $jenis = '';
        }

        $halaman = Halaman::buat($this->hitungEtalase($request, $cari, $jenis),
            $request->query('halaman', 1), self::PER_KATALOG);

        return [
            'barang' => $this->barisEtalase($request, $cari, $jenis, $halaman),
            'halaman' => $halaman,
            'kop' => $this->koperasiBelanja($request),
            // `manajer` WAJIB ikut: kartu mengirim koperasi_id tersembunyi
            // hanya untuk pengelola (manajer terkunci ke koperasinya). Dulu
            // nilai ini hanya disiapkan di katalog.blade.php, sehingga potongan
            // gulir AJAX (yang merender partial langsung dari controller)
            // meledak "Undefined variable $manajer" — halaman penuh tampak
            // baik-baik saja, gulirnya yang mati.
            'manajer' => (bool) $request->user()->manajerKoperasi(),
            'jenis' => Barang::JENIS_TOKO, 'jenisAktif' => $jenis,
            'cariAktif' => $cari,
        ];
    }

    /**
     * Cabang GUDANG dari daftar gabungan.
     *
     * `selectRaw` memakai alias yang SAMA dengan cabang lokal supaya UNION-nya
     * sejajar kolom demi kolom. Urutan kolom itulah kontraknya — menambah kolom
     * di satu cabang tanpa cabang lain langsung merusak hasil.
     */
    private function kueriEtalaseGudang(string $cari, string $jenis)
    {
        return DB::connection('hub')->table('barang')
            ->selectRaw("'gudang' as asal, id, nama, satuan, harga, stok as sisa, "
                ."kategori, subsidi, gambar, stok_min, '' as koperasi, '' as jenis_lokal, 0 as koperasi_id")
            ->where('aktif', 1)
            ->when($jenis === 'SUBSIDI', fn ($x) => $x->where('subsidi', 1))
            ->when($jenis === 'NONSUBSIDI', fn ($x) => $x->where('subsidi', 0))
            ->when($cari !== '', function ($x) use ($cari) {
                $pola = $this->polaCari($cari);
                $x->where(fn ($w) => $w->where('nama', 'like', $pola)->orWhere('kode', 'like', $pola));
            });
    }

    /**
     * Cabang PRODUK LOKAL dari daftar gabungan.
     *
     * Tiga penjaga sekaligus — `status = DISETUJUI` (belum lolos keputusan tidak
     * dijual), `tayang = 1` (opsi manajer), dan `koperasi_id <> pembeli`
     * (koperasi tidak membeli produknya sendiri).
     */
    private function kueriEtalaseLokal(Request $request, string $cari)
    {
        $kop = $this->koperasiBelanja($request);

        return DB::connection('hub')->table('pasokan as p')
            ->leftJoin('koperasi as k', 'k.id', '=', 'p.koperasi_id')
            ->selectRaw("'lokal' as asal, p.id, p.komoditas as nama, p.satuan, "
                ."CASE WHEN p.harga_jual > 0 THEN p.harga_jual ELSE p.harga_satuan END as harga, "
                ."p.jumlah as sisa, '' as kategori, 0 as subsidi, '' as gambar, 0 as stok_min, "
                ."COALESCE(k.nama, p.umkm_nama, '') as koperasi, p.jenis as jenis_lokal, "
                ."p.koperasi_id as koperasi_id")
            ->where('p.status', 'DISETUJUI')->where('p.tayang', 1)
            ->when($kop, fn ($x) => $x->where('p.koperasi_id', '!=', $kop->id))
            ->when($cari !== '', function ($x) use ($cari) {
                $pola = $this->polaCari($cari);
                $x->where(fn ($w) => $w->where('p.komoditas', 'like', $pola)
                    ->orWhere('p.umkm_nama', 'like', $pola)
                    ->orWhere('k.nama', 'like', $pola));
            });
    }

    /** Jumlah baris kedua cabang — dihitung per cabang, BUKAN dari hasil UNION. */
    private function hitungEtalase(Request $request, string $cari, string $jenis): int
    {
        $n = 0;
        if ($jenis !== 'LOKAL') {
            $n += $this->kueriEtalaseGudang($cari, $jenis)->count();
        }
        if ($jenis === '' || $jenis === 'LOKAL') {
            $n += $this->kueriEtalaseLokal($request, $cari)->count();
        }

        return $n;
    }

    /**
     * Ambil SATU halaman dari daftar gabungan, lalu lengkapi datanya.
     *
     * Pelengkapan dilakukan BERKELOMPOK (satu kueri untuk semua jenjang grosir,
     * satu kueri untuk semua sisa pasokan), bukan per kartu: dengan 24 kartu,
     * cara per kartu berarti 48 kueri tambahan setiap kali halaman dibuka.
     */
    private function barisEtalase(Request $request, string $cari, string $jenis, Halaman $halaman): array
    {
        $g = $jenis === 'LOKAL' ? null : $this->kueriEtalaseGudang($cari, $jenis);
        $l = ($jenis === '' || $jenis === 'LOKAL') ? $this->kueriEtalaseLokal($request, $cari) : null;

        $q = $g && $l ? $g->unionAll($l) : ($g ?: $l);
        $rows = $q->orderBy('nama')->limit($halaman->perHalaman)->offset($halaman->offset())->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // ---- data tambahan, satu kueri per jenis ----
        $idGudang = $rows->where('asal', 'gudang')->pluck('id')->all();
        $idLokal = $rows->where('asal', 'lokal')->pluck('id')->all();

        $grosir = $idGudang === [] ? [] : BarangHargaGrosir::query()
            ->whereIn('barang_id', $idGudang)->orderBy('min_jumlah')->get()
            ->groupBy('barang_id');

        // Sisa pasokan = jumlah ditawarkan - yang sudah dipesan (pesanan yang
        // tidak dibatalkan). Satu agregat untuk semua baris lokal di halaman ini.
        $dipesan = $idLokal === [] ? [] : PesananItem::query()
            ->join('pesanan', 'pesanan.id', '=', 'pesanan_item.pesanan_id')
            ->whereIn('pesanan_item.pasokan_id', $idLokal)
            ->where('pesanan.status', '!=', 'DIBATALKAN')
            ->groupBy('pesanan_item.pasokan_id')
            ->pluck(DB::raw('SUM(pesanan_item.jumlah)'), 'pesanan_item.pasokan_id')->all();

        $kop = $this->koperasiBelanja($request);
        $labelJenis = Pasokan::JENIS;

        $keluar = [];
        foreach ($rows as $r) {
            $lokal = $r->asal === 'lokal';
            $sisa = (int) $r->sisa;
            if ($lokal) {
                $sisa = max(0, $sisa - (int) ($dipesan[$r->id] ?? 0));
            }
            $milikSendiri = $lokal && $kop && (int) $r->koperasi_id === (int) $kop->id;

            $keluar[] = (object) [
                'asal' => $r->asal,
                'id' => (int) $r->id,
                'nama' => (string) $r->nama,
                'satuan' => (string) $r->satuan,
                'harga' => (int) $r->harga,
                'sisa' => $sisa,
                'stok_min' => (int) $r->stok_min,
                // Lencana di pojok gambar: status stok menang atas label jenis.
                'lencana' => $sisa <= 0
                    ? 'Stok habis'
                    : ($lokal
                        ? ($labelJenis[$r->jenis_lokal] ?? (string) $r->jenis_lokal)
                        : (Barang::JENIS_TOKO[$r->subsidi ? 'SUBSIDI' : 'NONSUBSIDI'] ?? '')),
                'gambar' => $lokal ? '' : $this->urlGambarBarang((string) $r->gambar),
                'koperasi' => (string) $r->koperasi,
                'grosir' => $lokal ? [] : ($grosir[$r->id] ?? collect())
                    ->map(fn ($j) => ['min' => (int) $j->min_jumlah, 'harga' => (int) $j->harga])->all(),
                'milikSendiri' => (bool) $milikSendiri,
                'asalNomor' => $lokal ? 'p'.$r->id : (string) $r->id,
                'jenisKode' => $lokal ? 'LOKAL' : ($r->subsidi ? 'SUBSIDI' : 'NONSUBSIDI'),
            ];
        }

        return $keluar;
    }

    /**
     * URL gambar barang — dipindahkan dari model ke sini karena baris etalase
     * kini array biasa (hasil UNION), bukan model Barang.
     */
    private function urlGambarBarang(string $g): string
    {
        $g = trim($g);
        if ($g === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $g)) {
            return preg_match('~^https?://(www\.)?contoh\.id/~i', $g) ? '' : $g;
        }
        if (! preg_match('~^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$~i', $g)) {
            return '';
        }

        return is_file(public_path('img/katalog/'.$g)) ? asset('img/katalog/'.$g) : '';
    }

    /** Pola LIKE yang aman: `%` dan `_` dilolosi supaya `_` tidak cocokkan semua. */
    private function polaCari(string $cari): string
    {
        return '%'.str_replace(['%', '_'], ['\\\\%', '\\\\_'], $cari).'%';
    }

    /**
     * Potongan katalog untuk gulir-tak-berujung.
     *
     * Mengembalikan MARKUP (bukan JSON) dari partial yang sama dengan muat
     * penuh. Tanpa JS, pengguna tetap bisa memakai paging lewat tautan
     * "Muat lagi" yang ada di potongan itu.
     */
    public function katalog_muat(Request $request)
    {
        $u = $request->user();
        if (! $u->manajerKoperasi()) {
            abort(403, 'Katalog hanya untuk manajer koperasi.');
        }

        // Etalase kini SATU daftar gabungan (gudang + produk lokal), jadi
        // penanda gulir selalu meminta potongan yang sama. Tidak ada lagi
        // kemungkinan "kisi gudang kemasukan produk lokal".
        return response()->view('toko._katalog-isi', $this->bagianKatalog($request));
    }

    // ---------------------------------------------------------------- keranjang

    public function keranjang_tambah(Request $request)
    {
        $d = $request->validate([
            // `asal=lokal` = produk lokal & UMKM dari koperasi lain. Dua id
            // berbeda: barang gudang punya `barang_id`, produk lokal punya
            // `pasokan_id`. `penjual_id` dikirim kartu sebagai penunjuk pemilik
            // (boleh salah ketik — harga tetap dibaca dari pasokannya, bukan
            // dari nilai ini).
            'asal' => ['nullable', 'in:gudang,lokal'],
            'barang_id' => ['required_if:asal,gudang', 'nullable', 'integer'],
            'pasokan_id' => ['required_if:asal,lokal', 'nullable', 'integer'],
            'penjual_id' => ['nullable', 'integer'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:1000000'],
            'koperasi_id' => ['nullable', 'integer'],
        ], [], ['barang_id' => 'barang', 'pasokan_id' => 'produk', 'jumlah' => 'jumlah']);

        $kop = $this->koperasiBelanja($request);
        if (! $kop) {
            return back()->with('galat', 'Akun ini belum ditautkan ke koperasi, jadi belum bisa berbelanja.');
        }

        $lokal = ($d['asal'] ?? 'gudang') === 'lokal';
        if ($lokal) {
            $p = Pasokan::find($d['pasokan_id'] ?? 0);
            // Penjaga yang SAMA dengan etalase, diulang di sini karena keranjang
            // bisa diisi tanpa lewat kartu (POST langsung). Barang yang tidak
            // tayang / belum disetujui / milik sendiri tidak boleh masuk.
            if (! $p || (int) $p->tayang !== 1 || $p->status !== 'DISETUJUI'
                || (int) $p->koperasi_id === (int) $kop->id) {
                return back()->with('galat', 'Produk lokal itu tidak tersedia untuk koperasi Anda.');
            }
            $kunci = 'p'.$p->id;
        } else {
            if (! Barang::whereKey($d['barang_id'])->exists()) {
                return back()->with('galat', 'Barang tidak ditemukan.');
            }
            $kunci = (string) (int) $d['barang_id'];
        }

        $k = $this->isiKeranjang($request);
        // Keranjang dikunci ke satu koperasi: berpindah koperasi mengosongkannya,
        // supaya tidak ada pesanan tercampur dua koperasi.
        if (($k['koperasi_id'] ?? null) !== $kop->id) {
            $k = ['koperasi_id' => $kop->id, 'item' => []];
        }
        $k['item'][$kunci] = ($k['item'][$kunci] ?? 0) + (int) $d['jumlah'];
        $this->simpanKeranjang($request, $k);

        return back()->with('pesan', 'Ditambahkan ke keranjang.');
    }

    public function keranjang_ubah(Request $request)
    {
        $d = $request->validate([
            'barang_id' => ['nullable', 'integer'],
            'pasokan_id' => ['nullable', 'integer'],
            'jumlah' => ['required', 'integer', 'min:0', 'max:1000000'],
        ], [], ['barang_id' => 'barang', 'pasokan_id' => 'produk', 'jumlah' => 'jumlah']);

        $k = $this->isiKeranjang($request);
        // Kunci baris mengikuti bentuk yang sama dengan keranjang_tambah:
        // produk lokal diawali 'p'. Nomor baris lama (sebelum ada produk lokal)
        // tetap angka polos, jadi keranjang yang sedang terisi tidak hilang.
        $kunci = $request->filled('pasokan_id') ? 'p'.(int) $d['pasokan_id'] : (string) (int) $d['barang_id'];
        if ((int) $d['jumlah'] === 0) {
            unset($k['item'][$kunci]);
        } else {
            $k['item'][$kunci] = (int) $d['jumlah'];
        }
        $this->simpanKeranjang($request, $k);

        return back()->with('pesan', 'Keranjang diperbarui.');
    }

    public function keranjang(Request $request)
    {
        $u = $request->user();
        // Admin & PIC tidak punya keranjang belanja — dilempar ke pesanan.
        if (! $u->manajerKoperasi()) {
            return redirect()->route('toko.pesanan');
        }

        return view('toko.keranjang', [
            'aktif' => 'toko',
            'keranjang' => $this->rincianKeranjang($request),
            'kop' => $this->koperasiBelanja($request),
            'saldos' => $this->saldoKoperasi($u),
        ]);
    }

    // ----------------------------------------------------------------- checkout

    public function checkout(Request $request)
    {
        $u = $request->user();
        $kop = $this->koperasiBelanja($request);
        $k = $this->isiKeranjang($request);
        $kanal = $request->input('kanal') === 'KAS' ? 'KAS' : 'AGRN';
        // Pesanan antar-koperasi WAJIB lewat kas koperasi. Saldo Agrinas adalah
        // modal belanja ke gudang Agrinas — memakainya untuk membayar koperasi
        // lain berarti uang modal itu hilang tanpa penerima. Dipaksa di sini
        // (bukan hanya di tampilan) karena keranjang bisa dikirim tanpa lewat UI.
        $adaLokal = false;
        foreach (array_keys($k['item']) as $kunci) {
            if (str_starts_with((string) $kunci, 'p')) {
                $adaLokal = true;
                break;
            }
        }
        if ($adaLokal) {
            $kanal = 'KAS';
        }
        // Cara penyerahan dipilih saat checkout:  dikirim ke koperasi, atau
        // diambil sendiri di gudang Agrinas.
        $antar = $request->input('antar') === 'AMBIL' ? 'AMBIL' : 'KIRIM';

        if (! $kop || $k['item'] === []) {
            return redirect()->route('toko')->with('galat', 'Keranjang masih kosong.');
        }

        $rincian = $this->rincianKeranjang($request);
        if ($rincian['kurang'] !== []) {
            return redirect()->route('toko.keranjang')
                ->with('galat', 'Stok gudang tidak cukup untuk: '.implode(', ', $rincian['kurang']).'.');
        }

        $total = (int) $rincian['total'];
        if ($total <= 0) {
            return redirect()->route('toko.keranjang')->with('galat', 'Total pesanan belum ada harganya.');
        }

        $saldo = Saldo::untuk($kop->id);
        if ($kanal === 'AGRN' && $saldo->sisa() < $total) {
            return redirect()->route('toko.keranjang')->with('galat',
                'Saldo Agrinas tidak cukup. Sisa Rp '.number_format($saldo->sisa(), 0, ',', '.').
                ' — pilih pembayaran lewat kas koperasi.');
        }

        try {
            $pesanan = DB::connection('hub')->transaction(function () use ($u, $kop, $rincian, $kanal, $total, $antar, $request) {
                // Stok diperiksa ULANG di dalam transaksi, baru dipotong: antara
                // halaman keranjang dan tombol checkout bisa ada pembeli lain.
                // ponytail: SQLite tak punya baris-kunci, aman karena satu penulis.
                // Pindah ke MySQL -> tambahkan ->lockForUpdate() di kueri ini.
                $penjual = [];
                foreach ($rincian['baris'] as $b) {
                    if ($b['lokal']) {
                        // Produk lokal: yang diperiksa bukan stok gudang, tetapi
                        // sisa tayangan pasokan milik koperasi penjual (jumlah
                        // dikurangi pesanan koperasi lain yang belum dibatalkan).
                        // Ini juga yang mencegah KAS dicatat sebagai pengeluaran
                        // koperasi sendiri: `pasokan_id` tidak pernah terisi untuk
                        // baris gudang, dan koperasi tidak bisa membeli produknya.
                        $p = Pasokan::find($b['id']);
                        if (! $p || (int) $p->tayang !== 1 || $p->status !== 'DISETUJUI'
                            || (int) $p->koperasi_id === (int) $kop->id) {
                            throw new \RuntimeException('Produk lokal '.$b['nama'].' sudah tidak tersedia.');
                        }
                        $sisa = $p->sisaTayang();
                        if ($sisa < (int) $b['jumlah']) {
                            throw new \RuntimeException('Produk lokal '.$b['nama'].' tinggal '
                                .number_format($sisa, 0, ',', '.').'.');
                        }
                        // Checkout sekali = satu penjual. Dua penjual berbeda
                        // tidak bisa ditagih sebagai satu pesanan — pemisahan itu
                        // urusan tampilan nanti, bukan alur uang.
                        if ($penjual && $penjual[0] !== (int) $p->koperasi_id) {
                            throw new \RuntimeException('Produk lokal dari dua koperasi '
                                .'berbeda tidak bisa digabung dalam satu pesanan.');
                        }
                        $penjual = [(int) $p->koperasi_id];
                        continue;
                    }
                    $stok = (int) Barang::whereKey($b['id'])->value('stok');
                    if ($stok < $b['jumlah']) {
                        throw new \RuntimeException('Stok '.$b['nama'].' tinggal '.number_format($stok, 0, ',', '.').'.');
                    }
                    Barang::whereKey($b['id'])->update(['stok' => $stok - $b['jumlah'], 'diperbarui' => kini()]);
                }

                $pesanan = Pesanan::create([
                    'kode' => Pesanan::kodeBaru(), 'invoice' => Pesanan::invoiceBaru(),
                    'koperasi_id' => $kop->id, 'pemesan' => $u->id, 'total' => $total,
                    'kanal' => $kanal, 'antar' => $antar,
                    // `penjual_id` kosong = pesanan dari gudang Agrinas (perilaku
                    // lama apa adanya); terisi = order dari koperasi lain.
                    'penjual_id' => $penjual[0] ?? null,
                    'status' => $kanal === 'AGRN' ? 'DIBAYAR' : 'DIPESAN',
                    'catatan' => (string) $request->input('catatan', ''),
                    'tanggal' => kini(), 'dibayar' => $kanal === 'AGRN' ? kini() : '',
                    'dibuat' => kini(), 'diperbarui' => kini(),
                ]);

                foreach ($rincian['baris'] as $b) {
                    PesananItem::create([
                        'pesanan_id' => $pesanan->id,
                        'barang_id' => $b['lokal'] ? null : $b['id'],
                        'pasokan_id' => $b['lokal'] ? $b['id'] : null,
                        'nama' => $b['nama'],
                        'satuan' => $b['satuan'], 'jumlah' => $b['jumlah'], 'harga' => $b['harga'],
                    ]);
                }

                return $pesanan;
            });
        } catch (\Throwable $e) {
            return redirect()->route('toko.keranjang')->with('galat', $e->getMessage());
        }

        $this->simpanKeranjang($request, ['koperasi_id' => null, 'item' => []]);
        jurnal($u->id, 'TOKO_PESAN', $pesanan->invoice, 'kanal='.$kanal.' total='.$total, $request->ip() ?? '');

        return redirect()->route('toko.invoice', $pesanan->id)
            ->with('pesan', 'Pesanan '.$pesanan->kode.' dibuat. Invoice '.$pesanan->invoice.' terbit.');
    }

    // ------------------------------------------------------------------ pesanan

    public function pesanan(Request $request)
    {
        $u = $request->user();
        $lingkup = $u->koperasiQuery()->select('koperasi.id');

        $daftar = Pesanan::query()
            ->leftJoin('koperasi as k', 'k.id', '=', 'pesanan.koperasi_id')
            ->leftJoin('users as pem', 'pem.id', '=', 'pesanan.pemesan')
            ->select('pesanan.*', 'k.nama as nama_koperasi', 'k.kode as kode_koperasi',
                'pem.nama as nama_pemesan')
            // Dua sisi dalam satu daftar: pesanan yang SAYA beli (koperasi_id)
            // dan pesanan yang SAYA jual ke koperasi lain (penjual_id). Tanpa
            // sisi kedua, koperasi penjual tidak pernah tahu ada order masuk.
            ->where(fn ($w) => $w->whereIn('pesanan.koperasi_id', $lingkup)
                ->orWhereIn('pesanan.penjual_id', $lingkup))
            ->orderByDesc('pesanan.id')->limit(200)->get();

        return view('toko.pesanan', [
            'aktif' => 'toko', 'daftar' => $daftar,
            'kanal' => Saldo::LABEL_KANAL, 'saldos' => $this->saldoKoperasi($u),
            'kop' => Koperasi::where('manajer_id', $u->id)->first(),
            // Koperasi milik pengguna ini — dipakai view untuk membedakan baris
            // "saya pembeli" dari "saya penjual" tanpa kueri per baris.
            'kopSendiri' => (int) (Koperasi::where('manajer_id', $u->id)->value('id') ?? 0),
            // Keputusan wewenang dihitung SEKALI di sini, bukan diulang di Blade:
            // satu tempat = tampilan dan penjagaan server tidak mungkin berbeda.
            'pengelola' => ! $u->manajerKoperasi(),
            'agrinas' => $u->lingkupPenuh(),
            // Mencatat pelunasan = mengeluarkan uang: pusat / petugas keuangan
            // (PIC memantau saja, HR mengurus kepegawaian). Satu penanda, dipakai
            // tombol di view DAN penjagaan di pesanan_bayar().
            'bolehBayar' => $u->bolehBayar(),
            // Jejak tindak lanjut (catatan/minta ubah) untuk SEMUA pesanan di
            // daftar ini — satu kueri, bukan satu kueri per baris.
            'jejak' => \App\Models\PesananTindakan::whereIn(
                'pesanan_id', $daftar->pluck('id'))->orderBy('id')->get()->groupBy('pesanan_id'),
        ]);
    }

    /** Invoice satu pesanan — halaman cetak, bisa disimpan jadi PDF dari peramban. */
    public function invoice(Request $request, int $id)
    {
        $u = $request->user();
        // Penjual juga boleh membuka invoice pesanan yang masuk ke produknya.
        $pesanan = Pesanan::where(fn ($w) => $w
                ->whereIn('koperasi_id', $u->koperasiQuery()->select('koperasi.id'))
                ->orWhereIn('penjual_id', $u->koperasiQuery()->select('koperasi.id')))
            ->whereKey($id)->firstOrFail();

        return view('toko.invoice', [
            'aktif' => 'toko', 'pesanan' => $pesanan,
            'item' => PesananItem::where('pesanan_id', $pesanan->id)->get(),
            'koperasi' => Koperasi::find($pesanan->koperasi_id),
            'pemesan' => User::find($pesanan->pemesan),
        ]);
    }

    /**
     * Surat jalan: dokumen serah-terima untuk ditandatangani manajer.
     *
     * Hanya boleh dilihat pihak yang berkepentingan — koperasi pemesan atau
     * pengelola pusat — bukan sembarang pengguna yang menebak id-nya.
     */
    public function surat_jalan(Request $request, int $id)
    {
        $u = $request->user();
        $q = Pesanan::query()->whereKey($id);
        // Pihak berkepentingan: pembeli, penjual, atau pengelola pusat.
        // Tanpa `penjual_id`, koperasi penjual tidak bisa mencetak surat jalan
        // barang yang ia lepas sendiri.
        $lingkup = $u->koperasiQuery()->select('koperasi.id');
        if ($u->manajerKoperasi()) {
            $q->where(fn ($w) => $w->whereIn('koperasi_id', $lingkup)
                ->orWhereIn('penjual_id', $lingkup));
        } elseif ($u->pic()) {
            $q->where(fn ($w) => $w->whereIn('koperasi_id', $lingkup)
                ->orWhereIn('penjual_id', $lingkup));
        }
        $pesanan = $q->firstOrFail();

        return view('toko.surat-jalan', [
            'aktif' => 'toko', 'pesanan' => $pesanan,
            'item' => PesananItem::where('pesanan_id', $pesanan->id)->get(),
            'koperasi' => Koperasi::find($pesanan->koperasi_id),
            'pemesan' => User::find($pesanan->pemesan),
        ]);
    }

    /** Tandai dibayar (kanal kas): sekaligus mencatat pengeluaran kas koperasi. */
    public function pesanan_bayar(Request $request, int $id)
    {
        // Mencatat pembayaran menulis ke buku kas koperasi — wewenang pusat.
        // `wajibPengelola()` tidak cukup di sini: ia juga meloloskan PIC, dan
        // PIC hanya memantau. Penjaganya harus sama dengan lepas & batal.
        //
        // PENGECUALIAN order antar-koperasi: pembeli adalah koperasi lain, dan
        // pengeluarannya masuk buku kas koperasi PEMBELI itu — jadi manajer
        // pembeli yang menandainya, bukan Agrinas. Uang tidak lewat Agrinas
        // sama sekali, karena itu pusat tidak boleh ikut menuliskannya.
        $u = $request->user();
        $pesanan = Pesanan::whereKey($id)->firstOrFail();
        $lokal = $pesanan->penjual_id !== null;
        if ($lokal) {
            if (! $u->manajerKoperasi() || (int) $pesanan->koperasi_id !== (int) $this->kopSendiri($u)) {
                abort(403, 'Pembayaran order antar-koperasi dicatat koperasi pembelinya.');
            }
        } elseif (! $u->bolehBayar()) {
            // Uang keluar = wewenang pusat & petugas keuangan; PIC memantau.
            return $this->hasilBlokirPic($request);
        }
        if ($pesanan->kanal !== 'KAS' || $pesanan->status !== 'DIPESAN') {
            return back()->with('galat', 'Pesanan ini tidak menunggu pembayaran kas.');
        }

        $pesanan->forceFill(['status' => 'DIBAYAR', 'dibayar' => kini(), 'diperbarui' => kini()])->save();

        // Pembelian = pengeluaran nyata koperasi. Buku keuangan hanya punya SATU
        // baris per hari (unique koperasi_id + tanggal), jadi pembelian TIDAK
        // membuat baris baru: ia menambah pengeluaran pada baris hari itu dan
        // menumpuk nomor invoice di `ref` supaya tiap rupiah tetap terlacak.
        $hari = now()->toDateString();
        $baris = \App\Models\Keuangan::query()
            ->where('koperasi_id', $pesanan->koperasi_id)->where('tanggal', $hari)->first();

        if ($baris) {
            $baris->forceFill([
                'pengeluaran' => (int) $baris->pengeluaran + (int) $pesanan->total,
                'rincian_pengeluaran' => mb_substr(trim(($baris->rincian_pengeluaran !== '' ? $baris->rincian_pengeluaran.'; ' : '')
                    .'Pembelian barang '.$pesanan->invoice), 0, 200),
                'ref' => mb_substr(trim(($baris->ref !== '' ? $baris->ref.', ' : '').$pesanan->invoice), 0, 200),
                'kanal' => $baris->kanal !== '' ? $baris->kanal : 'KAS',
                'dicatat_oleh' => $u->id, 'diperbarui' => kini(),
            ])->save();
        } else {
            \App\Models\Keuangan::create([
                'koperasi_id' => $pesanan->koperasi_id, 'tanggal' => $hari,
                'pemasukan' => 0, 'pengeluaran' => (int) $pesanan->total,
                'rincian_pengeluaran' => 'Pembelian barang '.$pesanan->invoice,
                'rincian_pemasukan' => '', 'catatan' => 'Checkout toko (kas koperasi)',
                'kanal' => 'KAS', 'ref' => $pesanan->invoice,
                'dicatat_oleh' => $u->id, 'dibuat' => kini(), 'diperbarui' => kini(),
            ]);
        }

        jurnal($u->id, 'TOKO_BAYAR', $pesanan->invoice, 'kas', $request->ip() ?? '');

        return back()->with('pesan', 'Pesanan '.$pesanan->kode.' ditandai dibayar.');
    }

    public function pesanan_batal(Request $request, int $id)
    {
        $u = $request->user();
        $pesanan = Pesanan::whereKey($id)->firstOrFail();
        $lokal = $pesanan->penjual_id !== null;

        if ($lokal) {
            // Order antar-koperasi dibatalkan penjualnya (dia yang menahan
            // barangnya); pengelola pusat tidak ikut menuliskannya.
            if (! $u->manajerKoperasi()
                || (int) $pesanan->penjual_id !== (int) $this->kopSendiri($u)) {
                abort(403, 'Pembatalan order antar-koperasi dikerjakan koperasi penjualnya.');
            }
        } elseif (! $u->lingkupPenuh()) {
            return $this->hasilBlokirPic($request);
        }
        if (! in_array($pesanan->status, ['DIPESAN', 'DIBAYAR'], true)) {
            return back()->with('galat', 'Pesanan yang sudah dikirim tidak bisa dibatalkan.');
        }

        $dibayarKas = $pesanan->status === 'DIBAYAR' && $pesanan->kanal === 'KAS';

        DB::connection('hub')->transaction(function () use ($pesanan, $dibayarKas) {
            foreach (PesananItem::where('pesanan_id', $pesanan->id)->get() as $it) {
                if ($it->barang_id) {
                    Barang::whereKey($it->barang_id)->increment('stok', (int) $it->jumlah);
                }
            }

            // Uang yang sudah keluar ikut ditarik kembali. Tanpa ini, pesanan
            // yang dibatalkan tetap tercatat sebagai pengeluaran di buku kas
            // koperasi (baris `keuangan` dibuat saat pelunasan), sehingga kas
            // koperasi tampak lebih kecil daripada uang yang benar-benar keluar.
            // Saldo Agrinas tidak perlu disentuh: sisanya DIHITUNG dari pesanan
            // berstatus DIBAYAR/DIKIRIM/DITERIMA, jadi DIBATALKAN memulihkannya.
            if ($dibayarKas) {
                $kas = \App\Models\Keuangan::query()
                    ->where('koperasi_id', $pesanan->koperasi_id)
                    ->where('ref', 'like', '%'.$pesanan->invoice.'%')->first();
                if ($kas) {
                    $sisaKeluar = max(0, (int) $kas->pengeluaran - (int) $pesanan->total);
                    $ref = array_filter(array_map('trim', explode(',', (string) $kas->ref)),
                        fn ($v) => $v !== '' && $v !== $pesanan->invoice);
                    $rincian = array_filter(array_map('trim', explode(';', (string) $kas->rincian_pengeluaran)),
                        fn ($v) => $v !== '' && $v !== 'Pembelian barang '.$pesanan->invoice);
                    if ($sisaKeluar === 0 && (int) $kas->pemasukan === 0 && $ref === []) {
                        // Baris ini lahir dari pembelian yang kini batal — ia tak
                        // lagi mewakili apa pun.
                        $kas->delete();
                    } else {
                        $kas->forceFill([
                            'pengeluaran' => $sisaKeluar,
                            'ref' => mb_substr(implode(', ', $ref), 0, 200),
                            'rincian_pengeluaran' => mb_substr(implode('; ', $rincian), 0, 200),
                            'diperbarui' => kini(),
                        ])->save();
                    }
                }
            }

            $pesanan->forceFill(['status' => 'DIBATALKAN', 'diperbarui' => kini()])->save();
        });
        jurnal($u->id, 'TOKO_BATAL', $pesanan->invoice, $dibayarKas ? 'kas ditarik kembali' : '', $request->ip() ?? '');

        return back()->with('pesan', 'Pesanan '.$pesanan->kode.' dibatalkan; '
            .($lokal ? 'sisa tayangan produk kembali tersedia.' : 'stok dikembalikan.'));
    }

    // -------------------------------------------------------------------- ubah

    /** Pengelola pusat: sunting harga & gambar katalog, kelola semua pesanan. */
    public function kelola(Request $request)
    {
        $u = $request->user();
        if ($u->manajerKoperasi()) {
            abort(403, 'Hanya pengelola pusat atau PIC wilayah.');
        }

        return view('toko.kelola', [
            'aktif' => 'toko',
            // Penyaring yang SAMA dipakai jalur AJAX dan jalur tanpa JS
            // (/admin/toko?q=…&aktif=turun) — satu tempat, hasil tidak mungkin
            // berbeda antara dua jalur itu.
            ...$this->halamanBarang($request),
            'pesanan' => $this->pesananMasuk(),
            'vendor' => \App\Models\Vendor::orderBy('nama')->get(),
            'kategori' => Barang::KATEGORI, 'satuan' => Barang::SATUAN,
            'sumber' => Barang::SUMBER, 'dipakai' => $this->barangDipakai(),
            'kanal' => Saldo::LABEL_KANAL,
        ]);
    }

    /**
     * Katalog + paginasi dalam satu tempat, supaya daftar penuh dan potongan
     * AJAX tidak mungkin menghitung halaman secara berbeda.
     */
    private function halamanBarang(Request $request): array
    {
        $q = $this->cariBarang($request);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return [
            'barang' => $halaman->batasi($q)->get(),
            'halaman' => $halaman,
            'jumlah' => $jumlah,
        ];
    }

    public function barang_ubah(Request $request, int $id)
    {
        if ($request->user()->manajerKoperasi()) {
            abort(403, 'Hanya pengelola pusat atau PIC wilayah.');
        }
        $d = $request->validate([
            'harga' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'gambar' => ['nullable', 'string', 'max:400'],
            'kategori' => ['nullable', 'in:'.implode(',', array_keys(Barang::KATEGORI))],
            'grosir' => ['nullable', 'string', 'max:2000'],
        ], [], ['harga' => 'harga', 'gambar' => 'gambar']);

        // URL gambar: hanya http(s) yang diterima. Tanpa saringan ini nilai
        // seperti javascript: ikut tersimpan dan dimuat di halaman publik.
        $gambar = trim((string) ($d['gambar'] ?? ''));
        if ($gambar !== '' && ! preg_match('~^https?://~i', $gambar)) {
            return back()->with('galat', 'Alamat gambar harus dimulai http:// atau https://');
        }

        $ubah = ['harga' => (int) $d['harga'], 'kategori' => $d['kategori'] ?? null, 'diperbarui' => kini()];
        // Hanya sentuh gambar bila field-nya memang dikirim: tanpa penjagaan ini
        // formulir yang cuma menyunting harga menghapus gambar yang sudah ada.
        if ($request->has('gambar')) {
            $ubah['gambar'] = $gambar;
        }
        Barang::whereKey($id)->update(array_filter($ubah, fn ($v) => $v !== null));
        // Jenjang grosir ikut di sini: menaikkan harga satuan bisa membuat jenjang
        // lama jadi tidak masuk akal (lebih mahal dari harga normal), jadi
        // pengelola perlu bisa membetulkannya dari layar yang sama.
        if ($request->has('grosir')) {
            $this->simpanJenjang($id, $this->jenjangGrosir($d['grosir'] ?? '', (int) $d['harga']));
        }

        jurnal($request->user()->id, 'TOKO_HARGA', (string) $id, 'harga='.$d['harga'], $request->ip() ?? '');

        return back()->with('pesan', 'Katalog diperbarui.');
    }

    // ------------------------------------------------- katalog: CRUD tanpa muat ulang

    /**
     * Daftar katalog untuk sisi kiri halaman Kelola Katalog.
     *
     * Sumbernya SATU partial yang sama dengan tabel HTML, jadi JS tidak
     * menyusun markup sendiri — tidak ada dua versi tampilan yang bisa
     * berbeda. Penyaring pencarian/kategori/sumber ikut di sini supaya AJAX
     * dan muat-penuh menghasilkan halaman yang identik.
     */
    public function barang_daftar(Request $request)
    {
        $this->wajibPengelola($request);
        $d = $this->halamanBarang($request);

        if ($this->inginJson($request)) {
            return response()->view('toko._katalog-baris',
                ['barang' => $d['barang'], 'kategori' => Barang::KATEGORI, 'dipakai' => $this->barangDipakai()])
                ->header('X-Jumlah', (string) $d['jumlah']);
        }

        // Dibuka langsung (bukan lewat fetch): alihkan ke halaman kelola supaya
        // tidak ada dua halaman berbeda yang menampilkan hal yang sama.
        return redirect()->route('toko.kelola', $request->query());
    }

    /**
     * URL gambar harus http(s). Tanpa saringan ini nilai seperti `javascript:`
     * ikut tersimpan dan dipasang di halaman publik — proteksi yang sudah ada di
     * jalur lama WAJIB ikut ke jalur CRUD baru, bukan hanya di satu rute.
     */
    /**
     * Gambar sah = kosong, URL http(s), atau nama berkas lokal hasil unggahan
     * (`Barang::gambarUrl()` yang memutuskan bisa dimuat atau tidak).
     */
    private function gambarSah(string $gambar): bool
    {
        return $gambar === ''
            || (bool) preg_match('~^https?://~i', $gambar)
            || (bool) preg_match('~^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$~i', $gambar);
    }

    /** Simpan berkas gambar unggahan ke public/img/katalog; kembalikan namanya. */
    private function simpanGambar(Request $request, string $lama): string
    {
        $f = $request->file('gambar_berkas');
        if (! $f) {
            return trim($lama);
        }
        $nama = \Illuminate\Support\Str::slug((string) $request->input('nama', 'barang'))
            .'-'.now()->format('YmdHis').'.'.strtolower($f->getClientOriginalExtension() ?: 'jpg');
        $f->move(public_path('img/katalog'), $nama);

        return $nama;
    }

    /** Tambah barang ke katalog Agrinas. */
    public function barang_simpan(Request $request)
    {
        $this->wajibPengelola($request);
        $d = $request->validate($this->aturanBarang(true), [], $this->namaKolomBarang());
        $gambar = $this->simpanGambar($request, (string) ($d['gambar'] ?? ''));
        if (! $this->gambarSah($gambar)) {
            return $this->hasil($request, 'galat', 'Gambar harus jpg/png/webp yang sah.', 422);
        }

        $kode = mb_strtoupper(trim((string) ($d['kode'] ?? '')));
        if ($kode === '') {
            $kode = 'BRG-'.str_pad((string) (Barang::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        }
        if (Barang::query()->where('kode', $kode)->exists()) {
            return $this->hasil($request, 'galat', 'Kode barang '.$kode.' sudah dipakai.', 422);
        }

        $b = Barang::create([
            'kode' => $kode, 'nama' => $d['nama'], 'satuan' => $d['satuan'],
            'kategori' => $d['kategori'], 'stok' => $d['stok'], 'stok_min' => $d['stok_min'] ?? 0,
            'harga' => $d['harga'], 'gambar' => $gambar, 'catatan' => $d['catatan'] ?? '',
            'sumber' => $d['sumber'] ?? 'GUDANG', 'aktif' => 1,
            'subsidi' => (int) ($d['subsidi'] ?? 0),
            'vendor_id' => $d['vendor_id'] ?? null, 'dibuat' => kini(), 'diperbarui' => kini(),
        ]);
        $this->simpanJenjang($b->id, $this->jenjangGrosir($d['grosir'] ?? '', (int) $d['harga']));
        jurnal($request->user()->id, 'KATALOG_TAMBAH', $b->kode, 'stok='.$b->stok, $request->ip() ?? '');

        return $this->hasil($request, 'pesan', 'Barang '.$b->nama.' ditambahkan.', 200,
            ['id' => $b->id, 'kode' => $b->kode, 'nama' => $b->nama]);
    }

    /** Sunting satu barang: katalog, asal, maupun harga & gambar. */
    public function barang_perbarui(Request $request, int $id)
    {
        $this->wajibPengelola($request);
        $b = Barang::findOrFail($id);
        $d = $request->validate($this->aturanBarang(false), [], $this->namaKolomBarang());
        // Berkas baru menang; tanpa berkas, nama gambar lama dipertahankan.
        $gambar = $this->simpanGambar($request, (string) ($d['gambar'] ?? $b->gambar));
        if (! $this->gambarSah($gambar)) {
            return $this->hasil($request, 'galat', 'Gambar harus jpg/png/webp yang sah.', 422);
        }

        $lama = (int) $b->stok;
        $ubah = $d;
        // `catatan` sengaja tidak ditulis: formulir katalog tak memuatnya, jadi
        // menyentuhnya hanya akan menghapus catatan yang sudah ada.
        unset($ubah['catatan']);
        // `grosir` bukan kolom tabel — disimpan lewat relasinya di bawah.
        unset($ubah['grosir']);
        // `gambar` NOT NULL: kolom nullable yang dikirim kosong divalidasi jadi
        // NULL, dan itu langsung melanggar NOT NULL. Kosong berarti "" di sini.
        $ubah['gambar'] = $gambar;
        $ubah['stok_min'] = (int) ($d['stok_min'] ?? 0);
        $ubah['vendor_id'] = ($d['vendor_id'] ?? null) ?: null;
        $ubah['diperbarui'] = kini();
        // Sama seperti `grosir`: hanya disentuh bila formulirnya memang
        // mengirim. Tanpa penjagaan ini, menyunting stok dari formulir lain
        // akan menghapus tanda subsidi yang sudah diatur.
        if ($request->has('subsidi')) {
            $ubah['subsidi'] = (int) $d['subsidi'];
        }
        $b->update($ubah);
        // Hanya sentuh jenjang bila field-nya memang dikirim, aturan yang sama
        // dengan `gambar`: formulir yang cuma menyunting stok tidak boleh
        // menghapus harga grosir yang sudah diatur.
        if ($request->has('grosir')) {
            $this->simpanJenjang($b->id, $this->jenjangGrosir($d['grosir'] ?? '', (int) $b->harga));
        }
        jurnal($request->user()->id, 'KATALOG_UBAH', $b->kode,
            $lama === (int) $b->stok ? 'data' : "stok $lama -> {$b->stok}", $request->ip() ?? '');

        return $this->hasil($request, 'pesan', 'Barang '.$b->nama.' diperbarui.', 200,
            ['id' => $b->id, 'nama' => $b->nama, 'harga' => (int) $b->harga]);
    }

    /**
     * Turunkan barang dari katalog.
     *
     * Tidak menghapus baris: `barang_id` sudah dipakai pesanan & distribusi,
     * jadi DELETE akan memutus riwayat pengiriman. Kalau barang BELUM pernah
     * dipakai sama sekali, barisnya memang boleh dibuang.
     */
    public function barang_hapus(Request $request, int $id)
    {
        $this->wajibPengelola($request);
        $b = Barang::findOrFail($id);
        $dipakai = PesananItem::where('barang_id', $id)->exists()
            || DB::connection('hub')->table('distribusi_item')->where('barang_id', $id)->exists();

        if ($dipakai) {
            $b->forceFill(['aktif' => 0, 'diperbarui' => kini()])->save();
            $pesan = 'Barang '.$b->nama.' diturunkan dari katalog (riwayat pesanan tetap utuh).';
        } else {
            $b->delete();
            $pesan = 'Barang '.$b->nama.' dihapus dari katalog.';
        }
        jurnal($request->user()->id, 'KATALOG_HAPUS', $b->kode, $dipakai ? 'nonaktif' : 'hapus', $request->ip() ?? '');

        return $this->hasil($request, 'pesan', $pesan, 200, ['id' => $id, 'hapus' => ! $dipakai]);
    }

    /** Naikkan lagi barang yang pernah diturunkan. */
    public function barang_aktifkan(Request $request, int $id)
    {
        $this->wajibPengelola($request);
        $b = Barang::findOrFail($id);
        $b->forceFill(['aktif' => 1, 'diperbarui' => kini()])->save();

        return $this->hasil($request, 'pesan', 'Barang '.$b->nama.' tayang lagi.', 200, ['id' => $id]);
    }

    // ---------------------------------------------- pantauan: kirim, ambil, surat jalan

    /**
     * Lepas barang dari gudang Agrinas.
     *
     * Sesuai opsi penyerahan pesanan:
     *   KIRIM -> status DIKIRIM
     *   AMBIL -> status DIAMBIL (koperasi datang sendiri ke gudang)
     * Keduanya menerbitkan nomor SURAT JALAN otomatis. Nomor itulah yang
     * ditandatangani manajer saat barang diterima — jadi bukti serah-terima
     * lahir dari sistem, bukan dari buku catatan terpisah.
     */
    public function pesanan_lepas(Request $request, int $id)
    {
        $u = $request->user();
        $pesanan = Pesanan::whereKey($id)->firstOrFail();
        $lokal = $pesanan->penjual_id !== null;

        if ($lokal) {
            // Barang dilepas dari gudang KOPERASI PENJUAL, bukan gudang Agrinas —
            // jadi yang berwenang manajer penjual itu, bukan pengelola pusat.
            if (! $u->manajerKoperasi()
                || (int) $pesanan->penjual_id !== (int) $this->kopSendiri($u)) {
                abort(403, 'Barang order antar-koperasi dilepas koperasi penjualnya.');
            }
        } elseif (! $u->lingkupPenuh()) {
            return $this->hasilBlokirPic($request);
        }

        if (! in_array($pesanan->status, ['DIBAYAR'], true)) {
            return $this->hasil($request, 'galat', 'Hanya pesanan yang sudah dibayar bisa dilepas.', 422);
        }

        $d = $request->validate(['antar' => ['nullable', 'in:KIRIM,AMBIL']]);
        $antar = $d['antar'] ?? ($pesanan->antar ?: 'KIRIM');
        $kapan = kini();
        // Opsi penyerahan menentukan status: barang diantar, atau koperasi
        // mengambil sendiri di gudang (statusnya beda supaya terlihat di meja).
        $statusBaru = $antar === 'AMBIL' ? 'DIAMBIL' : 'DIKIRIM';

        $pesanan->forceFill([
            'antar' => $antar, 'status' => $statusBaru, 'dikirim' => $kapan,
            'surat_jalan' => $pesanan->surat_jalan !== '' ? $pesanan->surat_jalan : Pesanan::suratJalanBaru(),
            'diperbarui' => $kapan,
        ])->save();

        $this->catatTindakan($pesanan->id, $u->id, 'siap',
            'Surat jalan '.$pesanan->surat_jalan.' — '.mb_strtolower($pesanan->label_antar()).'.');
        jurnal($u->id, 'TOKO_LEPAS', $pesanan->invoice, $statusBaru.' sj='.$pesanan->surat_jalan, $request->ip() ?? '');

        return $this->hasil($request, 'pesan',
            'Surat jalan '.$pesanan->surat_jalan.' terbit — '
            .($antar === 'AMBIL' ? 'pembeli mengambil barang sendiri.'
                : 'barang dalam perjalanan ke koperasi pembeli.'), 200,
            ['status' => $pesanan->status, 'surat_jalan' => $pesanan->surat_jalan]);
    }

    /** Kirim/tolak pakai form: jaga kompatibilitas tautan lama. */
    public function pesanan_kirim(Request $request, int $id)
    {
        return $this->pesanan_lepas($request, $id);
    }

    /**
     * Sekali pengelola menandai "Siap Diambil". Riwayat pengambilan dicatat
     * terpisah supaya jam keluarnya barang terbaca.
     */
    public function pesanan_terima(Request $request, int $id)
    {
        $u = $request->user();
        $pesanan = $this->pesananKelola($u, $id)->firstOrFail();
        // Penjual tidak boleh mengonfirmasi penerimaan barangnya sendiri: bukti
        // serah-terima harus datang dari pihak yang menerima.
        if ($pesanan->penjual_id !== null && (int) $pesanan->penjual_id === (int) $this->kopSendiri($u)) {
            // 403, bukan redirect: penjaga wewenang harus terbaca sebagai
            // penolakan, bukan "berhasil lalu kembali ke halaman semula".
            abort(403, 'Penerimaan barang dikonfirmasi koperasi pembeli.');
        }

        // Manajer boleh menandai sendiri barangnya diterima (bukti serah-terima),
        // pengelola pusat juga boleh (mencatatkan atas nama koperasi).
        if (! in_array($pesanan->status, ['DIKIRIM', 'DIAMBIL'], true)) {
            return $this->hasil($request, 'galat', 'Barang belum dilepas dari gudang.', 422);
        }

        $kapan = kini();
        $pesanan->forceFill(['status' => 'DITERIMA', 'diterima' => $kapan, 'diambil' => $kapan,
            'diperbarui' => $kapan])->save();
        $this->catatTindakan($pesanan->id, $u->id, 'terima',
            'Diterima sesuai surat jalan '.($pesanan->surat_jalan ?: '—').'.');
        jurnal($u->id, 'TOKO_TERIMA', $pesanan->invoice, 'sj='.$pesanan->surat_jalan, $request->ip() ?? '');

        return $this->hasil($request, 'pesan',
            'Barang diterima. Surat jalan '.($pesanan->surat_jalan ?: '—')
            .' siap ditandatangani manajer (buka lalu cetak).', 200,
            ['status' => $pesanan->status, 'surat_jalan' => $pesanan->surat_jalan,
             'url_surat_jalan' => route('toko.surat-jalan', $pesanan->id)]);
    }

    /**
     * Koperasi mengonfirmasi barangnya sudah diambil di gudang (opsi AMBIL).
     * Nomor surat jalan tetap sama — yang bertambah hanya bukti pengambilan.
     */
    public function pesanan_diambil(Request $request, int $id)
    {
        $u = $request->user();
        $pesanan = $this->pesananKelola($u, $id)->firstOrFail();
        if ($pesanan->penjual_id !== null && (int) $pesanan->penjual_id === (int) $this->kopSendiri($u)) {
            abort(403, 'Pengambilan barang dikonfirmasi koperasi pembeli.');
        }
        if ($pesanan->status !== 'DIAMBIL') {
            return $this->hasil($request, 'galat', 'Pesanan ini bukan pesanan ambil-di-gudang.', 422);
        }

        $kapan = kini();
        $pesanan->forceFill(['diambil' => $kapan, 'diterima' => $pesanan->diterima ?: $kapan,
            'status' => 'DITERIMA', 'diperbarui' => $kapan])->save();
        $this->catatTindakan($pesanan->id, $u->id, 'terima', 'Diambil di gudang Agrinas sesuai surat jalan '.($pesanan->surat_jalan ?: '—').'.');
        jurnal($u->id, 'TOKO_AMBIL', $pesanan->invoice, 'sj='.$pesanan->surat_jalan, $request->ip() ?? '');

        return $this->hasil($request, 'pesan', 'Pengambilan barang tercatat. Surat jalan siap ditandatangani.', 200,
            ['status' => $pesanan->status]);
    }

    /**
     * Pantauan order: catatan perjalanan, minta ubah, atau minta hapus.
     *
     * Sengaja BUKAN beberapa tabel baru: semua permintaan pengelola dicatat di
     * satu jejak (`pesanan_tindakan`) dan yang benar-benar mengubah data
     * dikerjakan method aksi pesanan lainnya. Nomor surat jalan tetap utuh
     * sebagai bukti kalau diubah.
     */
    public function pesanan_pantau(Request $request, int $id)
    {
        $u = $request->user();
        // Tindak lanjut = pekerjaan pengelola (pusat & PIC wilayah). Manajer
        // cukup MEMBACA jejaknya di halaman pesanan.
        $this->wajibPengelola($request);
        // "Minta ubah"/"Minta hapus" membatalkan pesanan (status DIBATALKAN) —
        // itu wewenang pusat; PIC hanya mencatat.
        if (in_array($request->input('tindakan'), ['minta', 'hapus'], true) && ! $u->lingkupPenuh()) {
            return $this->hasilBlokirPic($request);
        }
        $pesanan = $this->pesananKelola($u, $id)->firstOrFail();
        $d = $request->validate([
            'tindakan' => ['required', 'in:catatan,minta,hapus'],
            'pesan' => ['required', 'string', 'max:400'],
            'lampiran' => ['nullable', 'string', 'max:300'],
            'lampiran_berkas' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], [], ['tindakan' => 'tindakan', 'pesan' => 'catatan']);

        // Lampiran: berkas UNGGAHAN (disimpan di disk privat, disajikan lewat
        // rute ber-sesi) menang atas tautan lama. Tautan http(s) tetap diterima
        // supaya pemanggil lama tidak putus; selain itu ditolak — nilai seperti
        // javascript: akan ikut tersimpan dan terpasang di halaman.
        $lampiran = trim((string) ($d['lampiran'] ?? ''));
        if ($f = $request->file('lampiran_berkas')) {
            $lampiran = $f->store('toko/lampiran', 'local');
        } elseif ($lampiran !== '' && ! preg_match('~^https?://~i', $lampiran)) {
            return $this->hasil($request, 'galat', 'Lampiran harus berkas unggahan atau tautan http(s).', 422);
        }

        $this->catatTindakan($pesanan->id, $u->id, $d['tindakan'], $d['pesan'], $lampiran);
        jurnal($u->id, 'TOKO_PANTAU', $pesanan->invoice, $d['tindakan'].':'.mb_substr($d['pesan'], 0, 80), $request->ip() ?? '');

        $label = PesananTindakan::TINDAKAN[$d['tindakan']] ?? $d['tindakan'];
        $sah = in_array($d['tindakan'], ['hapus', 'minta'], true)
            ? $pesanan->forceFill(['status' => 'DIBATALKAN', 'diperbarui' => kini()])->save()
            : false;

        if ($sah) {
            $pesanan->forceFill(['catatan' => mb_substr(($pesanan->catatan !== '' ? $pesanan->catatan.' | ' : '').$label.': '.$d['pesan'], 0, 200)])->save();
        }

        return $this->hasil($request, 'pesan', $label.' tercatat di pesanan '.$pesanan->invoice.'.', 200);
    }

    // ------------------------------------------------------------------ bantu

    /** Koperasi yang berbelanja: manajer -> koperasinya; pusat/PIC -> pilihannya. */
    private function koperasiBelanja(Request $request): ?Koperasi
    {
        $u = $request->user();
        if ($u->manajerKoperasi()) {
            return Koperasi::where('manajer_id', $u->id)->first();
        }

        $pilih = (int) ($request->input('koperasi_id') ?: $request->query('koperasi_id') ?: 0);
        $q = $u->koperasiQuery();

        return $pilih ? $q->whereKey($pilih)->first() : $q->orderBy('nama')->first();
    }

    private function isiKeranjang(Request $request): array
    {
        $k = (array) $request->session()->get(self::KERANJANG, []);

        return ['koperasi_id' => $k['koperasi_id'] ?? null, 'item' => (array) ($k['item'] ?? [])];
    }

    private function simpanKeranjang(Request $request, array $k): void
    {
        $request->session()->put(self::KERANJANG, $k);
    }

    /**
     * Keranjang + rincian harga & sisa; satu query untuk semua barangnya.
     *
     * Keranjang menampung DUA jenis baris dan kuncinya punya bentuk sendiri:
     *   - barang gudang  -> kunci "123"   (id `barang`)
     *   - produk lokal   -> kunci "p45"   (id `pasokan`)
     * Bentuk angka polos untuk barang gudang dipertahankan apa adanya: keranjang
     * adalah data SESI, jadi mengubah bentuk kuncinya akan mengosongkan
     * keranjang siapa pun yang sedang berbelanja saat perubahan ini dipasang.
     */
    private function rincianKeranjang(Request $request): array
    {
        $k = $this->isiKeranjang($request);

        $idBarang = $idPasokan = [];
        foreach (array_keys($k['item']) as $kunci) {
            $kunci = (string) $kunci;
            if (str_starts_with($kunci, 'p')) {
                $idPasokan[] = (int) substr($kunci, 1);
            } else {
                $idBarang[] = (int) $kunci;
            }
        }

        // `with('hargaGrosir')` WAJIB: tanpa ini tiap baris menembak satu kueri
        // jenjang, dan keranjang berisi 10 barang = 10 kueri tambahan per muat.
        $barang = Barang::with('hargaGrosir')->whereIn('id', $idBarang)->get()->keyBy('id');
        $pasokan = Pasokan::query()
            ->leftJoin('koperasi as k', 'k.id', '=', 'pasokan.koperasi_id')
            ->select('pasokan.*', 'k.nama as nama_koperasi')
            ->whereIn('pasokan.id', $idPasokan)->get()->keyBy('id');

        $baris = [];
        $total = 0;
        $kurang = [];
        foreach ($k['item'] as $kunci => $jumlah) {
            $kunci = (string) $kunci;
            $jumlah = (int) $jumlah;
            $lokal = str_starts_with($kunci, 'p');

            if ($lokal) {
                $b = $pasokan[(int) substr($kunci, 1)] ?? null;
            } else {
                $b = $barang[(int) $kunci] ?? null;
            }
            if (! $b) {
                continue;
            }

            if ($lokal) {
                // Sisa yang bisa dipesan = jumlah pasokan dikurangi pesanan
                // koperasi lain yang belum dibatalkan. Bukan `stok` gudang —
                // produk ini tidak pernah masuk gudang Agrinas.
                $sisa = $b->sisaTayang();
                $harga = $b->hargaTawaran();
                $sub = $jumlah * $harga;
                if ($jumlah > $sisa) {
                    $kurang[] = $b->komoditas;
                }
                $total += $sub;
                $baris[] = ['id' => (int) $b->id, 'lokal' => true, 'kunci' => $kunci,
                    'kode' => $b->kode, 'nama' => $b->komoditas,
                    'penjual' => $b->nama_koperasi ?: ('koperasi #'.$b->koperasi_id),
                    'satuan' => $b->satuan, 'harga' => $harga, 'harga_dasar' => $harga,
                    'grosir' => false, 'grosir_mulai' => 0, 'stok' => $sisa,
                    'gambar' => '', 'jumlah' => $jumlah, 'subtotal' => $sub];
                continue;
            }

            if ($jumlah > (int) $b->stok) {
                $kurang[] = $b->nama;
            }
            // SATU tempat menghitung harga: keranjang, checkout, dan invoice
            // semuanya memakai fungsi ini, jadi harga grosir tidak mungkin
            // diterapkan di satu layar tetapi tidak di layar lain.
            $harga = $b->hargaUntuk($jumlah);
            $sub = $jumlah * $harga;
            $total += $sub;
            $baris[] = ['id' => (int) $b->id, 'lokal' => false, 'kunci' => $kunci,
                'kode' => $b->kode, 'nama' => $b->nama, 'penjual' => '',
                'satuan' => $b->satuan, 'harga' => $harga, 'harga_dasar' => (int) $b->harga,
                'grosir' => $harga < (int) $b->harga, 'grosir_mulai' => $b->grosirMulai(),
                'stok' => (int) $b->stok, 'gambar' => $b->gambarUrl(),
                'jumlah' => $jumlah, 'subtotal' => $sub];
        }

        return ['baris' => $baris, 'total' => $total, 'kurang' => $kurang,
            'koperasi_id' => $k['koperasi_id'], 'jumlah' => count($baris)];
    }

    /**
     * Sisa saldo tiap koperasi dalam lingkup pengguna — TIGA query, bukan dua
     * per koperasi (pusat punya 16 koperasi, jadi ini bedanya 3 vs 48 query).
     */
    private function saldoKoperasi(User $u): array
    {
        $koperasi = $u->koperasiQuery()->orderBy('nama')->get();
        if ($koperasi->isEmpty()) {
            return [];
        }
        $ids = $koperasi->pluck('id');

        $saldo = Saldo::whereIn('koperasi_id', $ids)->pluck('saldo', 'koperasi_id');
        // Koperasi yang belum punya baris saldo: dibuat sekarang (jatah awal).
        $belum = $ids->diff($saldo->keys());
        if ($belum->isNotEmpty()) {
            $baris = $belum->map(fn ($id) => ['koperasi_id' => (int) $id, 'saldo' => Saldo::AWAL,
                'catatan' => 'Modal belanja awal — tidak dapat ditarik atau diuangkan.',
                'dibuat' => kini(), 'diperbarui' => kini()])->all();
            Saldo::insert($baris);
            $saldo = Saldo::whereIn('koperasi_id', $ids)->pluck('saldo', 'koperasi_id');
        }

        $terpakai = Pesanan::query()->whereIn('koperasi_id', $ids)->where('kanal', 'AGRN')
            ->whereIn('status', ['DIBAYAR', 'DIKIRIM', 'DITERIMA'])
            ->groupBy('koperasi_id')->pluck(DB::raw('SUM(total)'), 'koperasi_id');

        $keluar = [];
        foreach ($koperasi as $k) {
            $awal = (int) ($saldo[$k->id] ?? Saldo::AWAL);
            $pakai = (int) ($terpakai[$k->id] ?? 0);
            $keluar[$k->id] = ['kode' => $k->kode, 'nama' => $k->nama, 'awal' => $awal,
                'terpakai' => $pakai, 'sisa' => max(0, $awal - $pakai)];
        }

        return $keluar;
    }

    /** Koperasi yang boleh dipilih pengguna ini (untuk pemilih "belanja untuk"). */
    private function koperasiPilihan(User $u)
    {
        return $u->koperasiQuery()->orderBy('nama')->get();
    }

    // ------------------------------------------------- katalog: penjaga & bentuk data

    /** Hanya pengelola pusat / PIC wilayah yang boleh menyunting katalog. */
    private function wajibPengelola(Request $request): void
    {
        if ($request->user()->manajerKoperasi()) {
            abort(403, 'Hanya pengelola pusat atau PIC wilayah.');
        }
    }

    /**
     * Jawaban seragam untuk aksi yang bukan wewenang PIC wilayah: 403 + alasan
     * yang terbaca. Dipakai lepas/bayar/batal di toko.
     */
    private function hasilBlokirPic(Request $request)
    {
        return $this->hasil($request, 'galat',
            'PIC wilayah hanya memantau dan menindaklanjuti pesanan; '
            .'melepas barang, pembayaran, dan pembatalan dikerjakan Agrinas pusat.', 403);
    }

    /**
     * AJAX atau muat-penuh?
     *
     * Halaman Kelola Katalog memakai satu alur untuk dua-duanya: permintaan
     * biasa mengembalikan HALAMAN, permintaan fetch mengembalikan POTONGAN
     * (partial yang sama). Jadi tidak ada markup kedua yang bisa berbeda.
     */
    private function inginJson(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson();
    }

    /**
     * Jawaban seragam: balas JSON untuk fetch, atau alihkan kembali ke halaman
     * sebelumnya dengan pesan. Satu bentuk untuk semua aksi katalog/pesanan,
     * jadi JS tak perlu menerjemahkan dua bentuk jawaban berbeda.
     */
    private function hasil(Request $request, string $kunci, string $pesan, int $kode = 200, array $data = [])
    {
        if ($this->inginJson($request)) {
            return response()->json(['ok' => $kunci === 'pesan', $kunci => $pesan] + $data, $kode);
        }

        return back()->with($kunci, $pesan);
    }

    /** Pencarian katalog; satu tempat supaya AJAX & muat-penuh menyaring sama. */
    private function cariBarang(Request $request)
    {
        return Barang::query()
            // Catatan: selalu lewat Barang::query() — bukan `new Barang`, yang
            // menghasilkan model tanpa koneksi terpasang lalu memecahkan kueri.
            ->when($request->query('aktif') === 'turun', fn ($q) => $q->where('aktif', 0))
            ->when($request->query('aktif') === 'semua', fn ($q) => $q)
            ->when(! in_array($request->query('aktif'), ['turun', 'semua'], true), fn ($q) => $q->where('aktif', 1))
            ->when($request->query('kategori'), fn ($q, $k) => $q->where('kategori', $k))
            ->when($request->query('sumber'), fn ($q, $s) => $q->where('sumber', $s))
            ->when($request->query('q'), function ($q, $c) {
                // `%` dan `_` dilolosi: keduanya pola LIKE, dan tanpa ini mencari
                // `_` mencocokkan hampir semua baris.
                $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $c).'%';
                $q->where(fn ($w) => $w->where('nama', 'like', $pola)->orWhere('kode', 'like', $pola));
            })
            // Terbaru dulu supaya barang yang baru ditambah langsung terlihat di
            // halaman 1 (dulu urut nama: barang baru bisa mendarat di halaman
            // belakang dan pengguna mengira penambahannya gagal). `id` jadi
            // pemutus seri agar urutannya pasti.
            ->orderByDesc('diperbarui')->orderByDesc('id');
    }

    /**
     * Id barang yang sudah dipakai di pesanan/distribusi.
     *
     * Dipakai untuk memberi tombol yang JUJUR: barang yang belum pernah dipakai
     * benar-benar dihapus, yang sudah dipakai hanya diturunkan. Tanpa daftar ini
     * tombolnya berbunyi "Turunkan" lalu diam-diam menghapus.
     */
    private function barangDipakai(): array
    {
        return PesananItem::query()->whereNotNull('barang_id')->distinct()->pluck('barang_id')
            ->merge(DB::connection('hub')->table('distribusi_item')->whereNotNull('barang_id')
                ->distinct()->pluck('barang_id'))
            ->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /** Aturan kolom katalog. Kode hanya diminta saat tambah (boleh dikosongkan). */
    private function aturanBarang(bool $denganKode): array
    {
        return array_filter([
            'kode' => $denganKode ? ['nullable', 'string', 'max:24', 'alpha_dash'] : null,
            'nama' => ['required', 'string', 'max:120'],
            'satuan' => ['required', 'in:'.implode(',', Barang::SATUAN)],
            'kategori' => ['required', 'in:'.implode(',', array_keys(Barang::KATEGORI))],
            'stok' => ['required', 'integer', 'min:0', 'max:100000000'],
            'stok_min' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'harga' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'sumber' => ['required', 'in:'.implode(',', array_keys(Barang::SUMBER))],
            'vendor_id' => ['nullable', 'integer', 'exists:vendor,id'],
            'gambar' => ['nullable', 'string', 'max:400'],
            // Gambar kini DIUNGGAH sebagai berkas, bukan tautan.
            'gambar_berkas' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'catatan' => ['nullable', 'string', 'max:200'],
            'grosir' => ['nullable', 'string', 'max:2000'],
            // Checkbox mengirim "0" (tersembunyi) + "1" (bila dicentang) —
            // nilai TERAKHIR yang menang, jadi cukup 'in:0,1'.
            'subsidi' => ['nullable', 'in:0,1'],
        ]);
    }

    /**
     * Baca jenjang harga grosir dari isian formulir.
     *
     * Bentuk kiriman: `grosir` = "min:harga" dipisah koma (mis. "5:12000,20:11000").
     * Dipilih dari pada JSON supaya modal yang sama tetap bekerja tanpa JS, sama
     * seperti seluruh formulir lain di Hub.
     *
     * Baris tidak sah DIBUANG (bukan ditolak): satu salah ketik tidak boleh
     * menggagalkan penyimpanan seluruh barang. Harga >= harga normal juga dibuang
     * — jenjang yang lebih mahal dari harga satuan bukan grosir.
     */
    private function jenjangGrosir(?string $isian, int $hargaNormal): array
    {
        $jenjang = [];
        foreach (explode(',', (string) $isian) as $potong) {
            $potong = trim($potong);
            if ($potong === '') {
                continue;
            }
            // Terima "5:12000" dan "5 = 12000" (operator boleh pakai titik dua).
            $bagian = preg_split('/\s*[:=]\s*/', $potong) ?: [];
            if (count($bagian) !== 2) {
                continue;
            }
            [$min, $harga] = array_map(fn ($v) => (int) preg_replace('/\D+/', '', $v), $bagian);
            if ($min < 2 || $harga < 1 || $harga >= $hargaNormal) {
                continue;
            }
            $jenjang[$min] = $harga;   // min yang sama = yang terakhir menang
        }
        ksort($jenjang);

        return $jenjang;
    }

    /** Ganti seluruh jenjang grosir sebuah barang. */
    private function simpanJenjang(int $barangId, array $jenjang): void
    {
        BarangHargaGrosir::where('barang_id', $barangId)->delete();
        foreach ($jenjang as $min => $harga) {
            BarangHargaGrosir::create(['barang_id' => $barangId, 'min_jumlah' => (int) $min,
                'harga' => (int) $harga, 'dibuat' => kini()]);
        }
    }

    private function namaKolomBarang(): array
    {
        return ['kode' => 'kode', 'nama' => 'nama barang', 'satuan' => 'satuan',
            'kategori' => 'kategori', 'stok' => 'stok', 'stok_min' => 'stok minimum',
            'harga' => 'harga', 'sumber' => 'sumber barang', 'vendor_id' => 'vendor',
            'gambar' => 'alamat gambar', 'catatan' => 'catatan'];
    }

    /**
     * Order dari manajer untuk meja pengelola.
     *
     * Hanya pesanan yang LAHIR dari manajer koperasi yang tampil di sini —
     * Agrinas tidak berbelanja seperti manajer, jadi mesin penjualan tidak
     * ditampilkan sebagai pesanan. Satu query untuk semua baris termasuk nama
     * koperasi & pemesan (bukan satu query per baris).
     */
    private function pesananMasuk()
    {
        return Pesanan::query()
            ->leftJoin('koperasi as k', 'k.id', '=', 'pesanan.koperasi_id')
            ->leftJoin('users as pem', 'pem.id', '=', 'pesanan.pemesan')
            ->select('pesanan.*', 'k.nama as nama_koperasi', 'k.kode as kode_koperasi', 'pem.nama as nama_pemesan')
            ->orderByDesc('pesanan.id')->limit(200)->get();
    }

    /** Satu baris jejak pantauan; dipakai semua aksi pesanan supaya riwayat lengkap. */
    private function catatTindakan(int $pesananId, int $oleh, string $tindakan, string $pesan, string $lampiran = ''): void
    {
        PesananTindakan::create([
            'pesanan_id' => $pesananId, 'oleh' => $oleh, 'tindakan' => $tindakan,
            'pesan' => mb_substr($pesan, 0, 400), 'lampiran' => mb_substr($lampiran, 0, 300),
            'dibuat' => kini(),
        ]);
    }

    /**
     * Koperasi milik manajer yang sedang masuk, atau 0 bila bukan manajer.
     *
     * Dipakai penjagaan order antar-koperasi: penjual/pembeli hanya boleh
     * bertindak atas koperasinya sendiri. BUKAN `koperasiBelanja()`, karena
     * method itu memilih koperasi pertama untuk pengelola pusat — di sini
     * pengelola memang tidak boleh cocok dengan koperasi mana pun.
     */
    private function kopSendiri(User $u): int
    {
        return (int) (Koperasi::where('manajer_id', $u->id)->value('id') ?? 0);
    }

    /** Pesanan yang boleh diubah pengguna ini: lingkup koperasinya. */
    /** Sajikan lampiran tindakan pesanan dari disk privat (wajib ber-sesi). */
    public function lampiran(Request $request, int $id)
    {
        $u = $request->user();
        $t = PesananTindakan::findOrFail($id);
        // Wewenang sama dengan halaman pesanan: pengelola ATAU koperasi yang
        // terlibat di pesanan itu.
        $boleh = $u->lingkupPenuh() || $this->pesananKelola($u, (int) $t->pesanan_id)->exists();
        if (! $boleh) {
            abort(403);
        }
        if (! Storage::disk('r2')->exists((string) $t->lampiran)) {
            abort(404, 'Lampiran tidak ditemukan di penyimpanan.');
        }

        return Storage::disk('r2')->download((string) $t->lampiran);
    }

    private function pesananKelola(User $u, int $id)
    {
        // Penjual ikut berwenang atas pesanan yang MASUK ke produknya — tanpa
        // itu koperasi penjual tidak bisa menandai dibayar atau melepas barang.
        return Pesanan::where(fn ($w) => $w
                ->whereIn('koperasi_id', $u->koperasiQuery()->select('koperasi.id'))
                ->orWhereIn('penjual_id', $u->koperasiQuery()->select('koperasi.id')))
            ->whereKey($id);
    }
}

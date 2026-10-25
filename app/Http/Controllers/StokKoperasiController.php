<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\DistribusiItem;
use App\Models\Koperasi;
use App\Models\KoperasiBarang;
use App\Models\KoperasiBarangStok;
use App\Models\User;
use App\Support\Halaman;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stok barang KOPERASI (`/distribusi/stok-koperasi`).
 *
 * Pembagian kewenangan yang diminta Bos:
 *  - MANAJER : CRUD penuh atas daftar barang koperasinya + update stok harian.
 *  - ADMIN   : hanya MELIHAT stok koperasi (barangnya sendiri dikelola di gudang
 *              Agrinas, jadi tidak ikut menulis daftar koperasi).
 *  - PIC     : hanya MELIHAT, dan hanya koperasi dalam wilayah binaannya.
 *
 * Barangnya BUKAN tabel `barang`: `barang` itu katalog gudang pusat yang dipakai
 * bersama seluruh koperasi, sehingga stok koperasi tidak boleh ditumpangkan di sana.
 * Lihat migrasi `koperasi_barang`.
 */
class StokKoperasiController extends Controller
{
    private const PER_HALAMAN = 20;

    /**
     * Kolom CSV impor/ekspor — SATU sumber, supaya template yang diunduh
     * pengguna tidak pernah menyimpang dari yang dibaca `impor()`.
     */
    private const KOLOM = ['kode', 'barcode', 'nama', 'satuan', 'kategori', 'stok', 'stok_min', 'harga', 'catatan'];

    /** Kewenangan menulis: hanya manajer koperasi. */
    private function bolehTulis(User $u): bool
    {
        return $u->manajerKoperasi();
    }

    /**
     * Koperasi yang sedang dilihat.
     *
     * Manajer: koperasinya sendiri (dipaksa, bukan hasil pilih). Admin & PIC:
     * dari `?koperasi=ID`, dan WAJIB berada dalam lingkupnya — kalau tidak,
     * dikembalikan null supaya PIC tidak bisa mengintip wilayah lain lewat ID
     * yang ditebak (penyaring lingkup diambil dari User::koperasiQuery()).
     */
    private function koperasiTampil(Request $request): ?Koperasi
    {
        $u = $request->user();

        if ($u->manajerKoperasi()) {
            return Koperasi::where('manajer_id', $u->id)->first();
        }

        $id = (int) $request->query('koperasi', 0);
        if ($id <= 0) {
            // Kulit pertama: belum memilih. Halaman meminta memilih lebih dulu.
            return null;
        }

        return $u->koperasiQuery()->whereKey($id)->first();
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $kop = $this->koperasiTampil($request);
        $tulis = $this->bolehTulis($u);

        // Halaman boleh dibuka admin/PIC SEBELUM memilih koperasi: yang tampil
        // ketika itu adalah pemilihnya (bukan halaman kosong yang membingungkan).
        $cari = trim((string) $request->query('q', ''));
        $q = $kop
            ? KoperasiBarang::where('koperasi_id', $kop->id)
                ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                    ->where('nama', 'like', '%'.$cari.'%')
                    ->orWhere('kode', 'like', '%'.$cari.'%')
                    ->orWhere('barcode', 'like', '%'.$cari.'%')))
                ->orderBy('nama')
            : null;

        $h = $q ? Halaman::buat((clone $q)->count(), $request->query('halaman', 1), self::PER_HALAMAN) : null;

        // Riwayat penyesuaian terakhir — supaya "update stok" bisa ditelusuri,
        // bukan cuma angka yang berubah diam-diam.
        $riwayat = $kop
            ? KoperasiBarangStok::where('koperasi_id', $kop->id)
                ->orderByDesc('id')->limit(10)->get()
            : collect();

        // Ringkasan yang berguna, bukan hiasan: berapa jenis barang, berapa yang
        // menipis, dan total nilai stok bila harganya diisi.
        $ringkas = $kop ? [
            'jenis' => KoperasiBarang::where('koperasi_id', $kop->id)->count(),
            'menipis' => KoperasiBarang::where('koperasi_id', $kop->id)
                ->where('stok_min', '>', 0)->whereColumn('stok', '<=', 'stok_min')->count(),
            'nilai' => (int) KoperasiBarang::where('koperasi_id', $kop->id)
                ->selectRaw('COALESCE(SUM(stok * harga), 0) n')->value('n'),
        ] : null;

        // "Barang diterima" dari Agrinas tetap ditampilkan: itu sumber stok yang
        // paling sering, dan manajer perlu melihatnya untuk membandingkan dengan
        // daftar yang ia kelola sendiri.
        $diterima = $kop ? DistribusiItem::query()
            ->join('distribusi as d', 'd.id', '=', 'distribusi_item.distribusi_id')
            ->join('barang as b', 'b.id', '=', 'distribusi_item.barang_id')
            ->where('d.koperasi_id', $kop->id)->where('d.status', 'DITERIMA')
            ->selectRaw('b.kode, b.nama, b.satuan, SUM(distribusi_item.jumlah_diterima) as jumlah,
                         MAX(d.dibuat) as terakhir')
            ->groupBy('b.kode', 'b.nama', 'b.satuan')
            ->orderBy('b.nama')->get() : collect();

        return view('distribusi.stok-koperasi', [
            'aktif' => 'distribusi.stok-koperasi',
            'koperasi' => $kop,
            'barang' => $h ? $h->batasi($q)->get() : collect(),
            'halaman' => $h,
            'cari' => $cari,
            'riwayat' => $riwayat,
            'ringkas' => $ringkas,
            'diterima' => $diterima,
            'tulis' => $tulis,
            'kategoriOpsi' => KoperasiBarang::KATEGORI,
            'satuanOpsi' => KoperasiBarang::satuan(),
            'sebabOpsi' => KoperasiBarang::SEBAB,
            'promoOpsi' => KoperasiBarang::PROMO,
            // Daftar barang untuk memilih BARANG BONUS di promo (satu koperasi
            // biasanya < 100 barang; dipakai satu <select> di modal).
            'bonusOpsi' => $kop ? KoperasiBarang::where('koperasi_id', $kop->id)
                ->orderBy('nama')->limit(500)->get(['id', 'nama']) : collect(),
        ]);
    }

    /** Potongan daftar untuk pemilih koperasi (admin & PIC) — cari/ketik nama. */
    public function pilih(Request $request)
    {
        $u = $request->user();
        if ($u->manajerKoperasi()) {
            abort(403);
        }

        $q = trim((string) $request->query('cari_kop', ''));

        return response()->view('distribusi._pemilih-koperasi', [
            'q' => $q,
            'hasil' => $u->koperasiQuery()
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                    ->where('nama', 'like', '%'.$q.'%')->orWhere('kode', 'like', '%'.$q.'%')))
                ->orderBy('nama')->limit(30)->get(['id', 'kode', 'nama']),
            'pilihanId' => (int) $request->query('koperasi', 0),
        ]);
    }

    /**
     * Cari barang untuk fitur barcode (opsional).
     *
     * Mengembalikan JSON: hasil scan barcode langsung dicocokkan, dan bila tidak
     * ada yang cocok pemanggil menawarkan "tambah barang baru dengan barcode ini".
     * Hanya koperasi dalam lingkup pemanggil — sama seperti daftarnya.
     */
    public function cari(Request $request)
    {
        $u = $request->user();
        $kop = $this->koperasiTampil($request);

        if (! $kop) {
            return response()->json(['ok' => false, 'pesan' => 'Pilih koperasi dulu.'], 400);
        }

        $kunci = trim((string) $request->query('kunci', ''));
        if ($kunci === '') {
            return response()->json(['ok' => true, 'hasil' => []]);
        }

        $hasil = KoperasiBarang::where('koperasi_id', $kop->id)
            ->where(fn ($x) => $x->where('barcode', $kunci)->orWhere('kode', $kunci)
                ->orWhere('nama', 'like', '%'.$kunci.'%'))
            ->orderBy('nama')->limit(20)->get();

        return response()->json([
            'ok' => true,
            'koperasi' => ['id' => $kop->id, 'nama' => $kop->nama, 'kode' => $kop->kode],
            'kunci' => $kunci,
            // `tepat` = cocok persis barcode/kode: UI memakainya untuk langsung
            // menyorot satu barang, bukan menampilkan daftar.
            'tepat' => $hasil->firstWhere('barcode', $kunci) ?? $hasil->firstWhere('kode', $kunci),
            'hasil' => $hasil->map(fn ($b) => [
                'id' => $b->id, 'kode' => $b->kode, 'barcode' => $b->barcode, 'nama' => $b->nama,
                'satuan' => $b->satuan, 'stok' => (int) $b->stok, 'stok_min' => (int) $b->stok_min,
                'harga' => (int) $b->harga, 'catatan' => $b->catatan,
                'kategori' => $b->kategori,
                'kategori_label' => KoperasiBarang::KATEGORI[$b->kategori] ?? $b->kategori,
                'menipis' => $b->menipis(),
                'diperbarui' => $b->diperbarui,
            ]),
        ]);
    }

    /** Tambah atau ubah barang koperasi — satu jalur, `id` yang menentukan. */
    public function simpan(Request $request)
    {
        $u = $request->user();
        if (! $this->bolehTulis($u)) {
            abort(403, 'Daftar barang koperasi diisi manajer koperasi.');
        }
        $kop = $this->koperasiTampil($request);
        if (! $kop) {
            return back()->with('galat', 'Akun Anda belum ditautkan ke koperasi.');
        }

        $d = $this->validasi($request, $kop->id);
        $id = (int) ($d['id'] ?? 0);
        unset($d['id']);

        // Kolom teks di tabel ini NOT NULL, sedangkan Laravel mengubah string
        // kosong dari formulir menjadi NULL (ConvertEmptyStringsToNull). Tanpa
        // normalisasi ini, mengosongkan "catatan" saja sudah cukup untuk membuat
        // penyimpanan gagal dengan "NOT NULL constraint failed".
        // Barcode kosong juga disimpan sebagai '' supaya aturan unik per koperasi
        // tidak melarang dua barang yang sama-sama tanpa barcode.
        $d['nama'] = trim((string) $d['nama']);
        foreach (['kode', 'barcode', 'catatan'] as $k) {
            $d[$k] = trim((string) ($d[$k] ?? ''));
        }
        $d['catatan'] = mb_substr($d['catatan'], 0, 200);

        // PROMO dinormalkan di sini supaya yang tersimpan selalu berarti: jenis
        // dikenal, diskon ≤ 100%, dan barang bonus benar-benar milik koperasi
        // ini (id dari formulir tidak dipercaya). Bonus tanpa barang yang sah
        // DITOLAK, bukan disimpan setengah jalan — promo "gratis" yang tidak
        // memberi apa pun cuma mengecewakan pembeli di kasir.
        $d['promo_jenis'] = in_array($d['promo_jenis'] ?? '', ['DISKON', 'GRATIS'], true) ? $d['promo_jenis'] : '';
        $d['promo_nilai'] = max(0, (int) ($d['promo_nilai'] ?? 0));
        $d['promo_min'] = max(0, (int) ($d['promo_min'] ?? 0));
        $d['promo_bonus_id'] = max(0, (int) ($d['promo_bonus_id'] ?? 0));
        if ($d['promo_jenis'] === 'DISKON') {
            $d['promo_nilai'] = min(100, $d['promo_nilai']);
            $d['promo_bonus_id'] = 0;
        }
        if ($d['promo_jenis'] === 'GRATIS') {
            $sah = $d['promo_nilai'] > 0 && $d['promo_bonus_id'] > 0 && $d['promo_bonus_id'] !== $id
                && KoperasiBarang::where('koperasi_id', $kop->id)->whereKey($d['promo_bonus_id'])->exists();
            if (! $sah) {
                return back()->with('galat', 'Promo bonus butuh barang bonus yang sudah ada di koperasi ini '
                    .'(bukan barang itu sendiri) dan jumlah bonus minimal 1.');
            }
        }
        if ($d['promo_jenis'] !== '' && $d['promo_nilai'] <= 0) {
            $d['promo_jenis'] = '';   // promo tanpa nilai = tanpa promo
        }

        if ($id > 0) {
            $b = KoperasiBarang::where('koperasi_id', $kop->id)->whereKey($id)->first();
            if (! $b) {
                return back()->with('galat', 'Barang yang ingin diubah tidak ditemukan di koperasi Anda.');
            }
            // Perubahan stok lewat jalur ubah ikut dicatat — angka stok yang
            // berubah tanpa riwayat adalah hal yang bikin laporan tak bisa dipercaya.
            if ((int) $b->stok !== (int) $d['stok']) {
                $this->catat($b, (int) $b->stok, (int) $d['stok'], 'HITUNG',
                    'Diubah dari formulir barang', $u->id, $kop->id);
            }
            $b->update($d + ['oleh_id' => $u->id, 'diperbarui' => kini()]);

            return back()->with('pesan', 'Barang "'.$d['nama'].'" diperbarui.');
        }

        $b = KoperasiBarang::create($d + [
            'koperasi_id' => $kop->id, 'oleh_id' => $u->id,
            'dibuat' => kini(), 'diperbarui' => kini(),
        ]);
        if ((int) $b->stok > 0) {
            $this->catat($b, 0, (int) $b->stok, 'MASUK',
                'Stok awal saat barang didaftarkan', $u->id, $kop->id);
        }

        return back()->with('pesan', 'Barang "'.$d['nama'].'" ditambahkan ke daftar koperasi.');
    }

    /**
     * Update stok harian.
     *
     * Menerima angka BARU (hasil hitung ulang) atau SELISIH (barang masuk/keluar).
     * Dua-duanya berakhir di satu tempat: baris `koperasi_barang_stok` + stok baru.
     */
    public function stok(Request $request, int $id)
    {
        $u = $request->user();
        if (! $this->bolehTulis($u)) {
            abort(403, 'Stok koperasi hanya bisa diperbarui manajer koperasi.');
        }
        $kop = $this->koperasiTampil($request);
        $b = KoperasiBarang::where('koperasi_id', $kop?->id ?? 0)->whereKey($id)->firstOrFail();

        $d = $request->validate([
            'stok' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'tambah' => ['nullable', 'integer', 'min:-1000000', 'max:1000000'],
            'sebab' => ['required', Rule::in(array_keys(KoperasiBarang::SEBAB))],
            'catatan' => ['nullable', 'string', 'max:200'],
        ], [], ['stok' => 'jumlah stok', 'sebab' => 'sebab perubahan']);

        // `?? null` WAJIB: field yang tidak dikirim tidak ada kuncinya sama sekali
        // (bukan bernilai null), dan membacanya langsung melempar "Undefined array
        // key" — pernah terjadi saat form hanya mengirim selisih.
        $stokBaru = $d['stok'] ?? null;
        $selisih = $d['tambah'] ?? null;
        if ($stokBaru === null && $selisih === null) {
            return back()->with('galat', 'Isi jumlah stok baru atau selisihnya.');
        }

        $baru = $stokBaru !== null
            ? (int) $stokBaru
            : max(0, (int) $b->stok + (int) $selisih);

        if ($baru === (int) $b->stok) {
            return back()->with('pesan', 'Stok "'.$b->nama.'" tidak berubah (tetap '.$b->stok.').');
        }

        $this->catat($b, (int) $b->stok, $baru, $d['sebab'], (string) ($d['catatan'] ?? ''),
            $u->id, $b->koperasi_id);
        $b->update(['stok' => $baru, 'oleh_id' => $u->id, 'diperbarui' => kini()]);

        return back()->with('pesan', 'Stok "'.$b->nama.'" kini '.number_format($baru, 0, ',', '.')
            .' '.$b->satuan.'.');
    }

    /** Hapus barang koperasi. */
    public function hapus(Request $request, int $id)
    {
        $u = $request->user();
        if (! $this->bolehTulis($u)) {
            abort(403, 'Daftar barang koperasi diisi manajer koperasi.');
        }
        $kop = $this->koperasiTampil($request);
        $b = KoperasiBarang::where('koperasi_id', $kop?->id ?? 0)->whereKey($id)->firstOrFail();
        $nama = $b->nama;
        // Riwayat stok ikut dihapus: `koperasi_barang_stok` menunjuk ke barang lewat
        // id, jadi begitu barangnya hilang riwayatnya jadi YATIM — barisnya masih
        // menghitung (menghambat) tapi sebab/tanggalnya tak bisa dibaca lagi dari
        // layar mana pun. Terukur: 3 baris yatim nyata (barang 177 terhapus, riwayat
        // 533-535 tertinggal) dan gate `uji_stok_koperasi.py` menuntut nol.
        KoperasiBarangStok::where('koperasi_barang_id', $id)->delete();
        $b->delete();

        return back()->with('pesan', 'Barang "'.$nama.'" dihapus dari daftar koperasi.');
    }

    /**
     * Ekspor daftar barang koperasi ke CSV (titik koma — Excel berlokal Indonesia
     * memakai pemisah itu, sama seperti ekspor payroll & keuangan).
     *
     * Berisi SELURUH barang koperasi, bukan satu halaman tabel: berkas ini dipakai
     * untuk memeriksa/mengisi stok di luar aplikasi, bukan untuk membaca layar.
     */
    public function ekspor(Request $request)
    {
        $kop = $this->koperasiTampil($request);
        if (! $kop) {
            return back()->with('galat', 'Pilih koperasi dulu, lalu ulangi ekspornya.');
        }

        $baris = KoperasiBarang::where('koperasi_id', $kop->id)->orderBy('nama')->get();

        $keluar = fopen('php://temp', 'r+');
        fputcsv($keluar, self::KOLOM, ';');
        foreach ($baris as $b) {
            fputcsv($keluar, [$b->kode, $b->barcode, $b->nama, $b->satuan, $b->kategori,
                $b->stok, $b->stok_min, $b->harga, $b->catatan], ';');
        }
        rewind($keluar);

        jurnal($request->user()->id, 'STOK_KOP_EKSPOR', $kop->kode, $baris->count().' baris', $request->ip() ?? '');

        // BOM: tanpa itu Excel di Windows membaca huruf beraksen sebagai mojibake.
        // `impor()` membuangnya kembali, jadi berkas ini bisa langsung diimpor ulang.
        return response("\xEF\xBB\xBF".stream_get_contents($keluar), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="stok-'.$kop->kode.'-'
                .now('Asia/Jakarta')->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * Template CSV impor: kepala kolom (persis `KOLOM`) + satu baris contoh.
     *
     * Dikirim sebagai berkas, bukan dijelaskan panjang di layar — pengguna tinggal
     * mengisinya, dan formatnya mustahil salah tulis.
     */
    public function template(Request $request)
    {
        $keluar = fopen('php://temp', 'r+');
        fputcsv($keluar, self::KOLOM, ';');
        fputcsv($keluar, ['BRG-001', '8991234567890', 'Beras Premium 5 kg', 'sak',
            'SEMBAKO', '25', '5', '68000', 'Rak A'], ';');
        rewind($keluar);

        return response("\xEF\xBB\xBF".stream_get_contents($keluar), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template-stok-koperasi.csv"',
        ]);
    }

    /**
     * Impor daftar barang dari CSV.
     *
     * Baris dicocokkan dengan barcode ATAU kode yang sudah ada di koperasi ini:
     * cocok = diperbarui, tidak cocok = barang baru. Jadi satu berkas yang sama
     * boleh diunggah berulang (mis. setelah stok opname) tanpa menggandakan barang.
     *
     * Satu baris gagal tidak menggagalkan seluruh berkas: barisnya dilewati dan
     * dilaporkan ke pengguna, supaya berkas 200 baris tidak batal hanya karena satu
     * satuan salah tulis.
     */
    public function impor(Request $request)
    {
        $u = $request->user();
        if (! $this->bolehTulis($u)) {
            abort(403, 'Impor daftar barang koperasi dilakukan manajer koperasi.');
        }
        $kop = $this->koperasiTampil($request);
        if (! $kop) {
            return back()->with('galat', 'Akun Anda belum ditautkan ke koperasi.');
        }

        $request->validate([
            'berkas' => ['required', 'file', 'max:2048'],
        ], ['berkas.required' => 'Pilih berkas CSV yang ingin diimpor.']);

        $isi = (string) file_get_contents($request->file('berkas')->getRealPath());

        $masuk = fopen('php://temp', 'r+');
        fwrite($masuk, $isi);
        rewind($masuk);

        // Pemisah: Excel berlokal Indonesia memakai ';', berkas dari sistem lain
        // sering memakai ','. Dipilih dari baris pertama supaya tidak ada
        // pengaturan yang harus dijelaskan ke pengguna.
        $pertama = (string) fgets($masuk);
        $pisah = substr_count($pertama, ';') >= substr_count($pertama, ',') ? ';' : ',';

        $tambah = 0; $ubah = 0; $galat = []; $barisData = 0; $nomor = 0; $kepala = true;

        while (($r = fgetcsv($masuk, 0, $pisah)) !== false) {
            $nomor++;

            // BOM di sel pertama (hasil ekspor Excel) harus dibuang, kalau tidak
            // kolom "kode" tidak akan pernah cocok dengan data lama.
            if ($nomor === 1) {
                $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($r[0] ?? ''));
            }

            $sel = fn (int $i) => trim((string) ($r[$i] ?? ''));
            // Angka dari sel CSV: pemisah ribuan dibuang, minus jadi 0, dan hasilnya
            // dipagari batas kolomnya (stok 1 juta, harga 1 miliar — sama dengan
            // batas di validasi formulir).
            $angka = function (int $i, int $maks) use ($sel) {
                $t = preg_replace('/\D/', '', $sel($i));

                return $t === '' ? 0 : min($maks, (int) $t);
            };

            // Baris kosong (termasuk baris terakhir berkas) dilewati diam-diam.
            if (implode('', array_map($sel, array_keys(self::KOLOM))) === '') {
                continue;
            }

            // Baris kepala dilewati HANYA kalau kolom pertamanya memang nama kolom
            // kita — berkas tanpa baris kepala tetap terbaca.
            if ($kepala && in_array(mb_strtolower($sel(0)), ['kode', 'barcode', 'nama'], true)) {
                $kepala = false;
                continue;
            }
            $kepala = false;
            $barisData++;

            $nama = mb_substr($sel(2), 0, 160);
            $satuan = mb_strtolower($sel(3));
            $kategori = $this->kategori($sel(4));

            if ($nama === '') {
                $galat[] = "baris $nomor: nama barang kosong.";
                continue;
            }
            if (! in_array($satuan, KoperasiBarang::satuan(), true)) {
                $galat[] = "baris $nomor: satuan \"{$sel(3)}\" tidak dikenal.";
                continue;
            }
            if ($kategori === '') {
                $galat[] = "baris $nomor: kategori \"{$sel(4)}\" tidak dikenal.";
                continue;
            }

            $kode = mb_substr($sel(0), 0, 32);
            $barcode = mb_substr($sel(1), 0, 64);
            $data = [
                'kode' => $kode, 'barcode' => $barcode, 'nama' => $nama,
                'satuan' => $satuan, 'kategori' => $kategori,
                // "1.500" dan "1,500" sama-sama 1500. Nilai bertanda minus dibaca 0,
                // bukan dipaksa jadi positif: kolom ini tidak mengenal angka negatif,
                // dan menebak tandanya menghasilkan stok yang tidak pernah ada.
                'stok' => $angka(5, 1000000),
                'stok_min' => $angka(6, 1000000),
                'harga' => $angka(7, 1000000000),
                'catatan' => mb_substr($sel(8), 0, 200),
            ];

            // Pencocokan: barcode ATAU kode. Sengaja bukan keduanya sekaligus —
            // berkas hasil ekspor kadang punya kode tanpa barcode, dan sebaliknya.
            $lama = ($barcode === '' && $kode === '') ? null
                : KoperasiBarang::where('koperasi_id', $kop->id)
                    ->where(function ($x) use ($barcode, $kode) {
                        if ($barcode !== '') {
                            $x->orWhere('barcode', $barcode);
                        }
                        if ($kode !== '') {
                            $x->orWhere('kode', $kode);
                        }
                    })->first();

            try {
                if ($lama) {
                    if ((int) $lama->stok !== $data['stok']) {
                        $this->catat($lama, (int) $lama->stok, $data['stok'], 'HITUNG',
                            "Impor CSV baris $nomor", $u->id, $kop->id);
                    }
                    $lama->update($data + ['oleh_id' => $u->id, 'diperbarui' => kini()]);
                    $ubah++;
                } else {
                    $b = KoperasiBarang::create($data + [
                        'koperasi_id' => $kop->id, 'oleh_id' => $u->id,
                        'dibuat' => kini(), 'diperbarui' => kini(),
                    ]);
                    if ((int) $b->stok > 0) {
                        $this->catat($b, 0, (int) $b->stok, 'MASUK',
                            "Stok awal dari impor CSV baris $nomor", $u->id, $kop->id);
                    }
                    $tambah++;
                }
            } catch (\Throwable $e) {
                // Umumnya barcode yang sudah dipakai barang LAIN di koperasi ini.
                $galat[] = "baris $nomor: gagal disimpan (barcode/kode bentrok dengan barang lain?).";
            }
        }

        fclose($masuk);

        if ($barisData === 0) {
            return back()->with('galat', 'Tidak ada baris data yang terbaca di berkas itu. '
                .'Unduh template CSV-nya untuk melihat susunan kolom yang benar.');
        }

        jurnal($u->id, 'STOK_KOP_IMPOR', $kop->kode,
            "tambah=$tambah ubah=$ubah galat=".count($galat), $request->ip() ?? '');

        return back()
            ->with($tambah || $ubah ? 'pesan' : 'galat',
                "Impor selesai: {$tambah} barang baru, {$ubah} diperbarui"
                .($galat ? ', '.count($galat).' baris dilewati.' : '.'))
            // Hanya sepuluh baris pertama yang ditampilkan: daftar galat sepanjang
            // halaman tidak lagi menolong siapa pun, yang perlu diperbaiki cukup jelas.
            ->with('impor_galat', array_slice($galat, 0, 10));
    }

    /** Kategori dari CSV: terima kunci ("SEMBAKO") maupun labelnya ("Sembako"). */
    private function kategori(string $nilai): string
    {
        $nilai = trim($nilai);
        if (isset(KoperasiBarang::KATEGORI[mb_strtoupper($nilai)])) {
            return mb_strtoupper($nilai);
        }

        $ketemu = array_search(mb_strtolower($nilai),
            array_map('mb_strtolower', KoperasiBarang::KATEGORI), true);

        return $ketemu === false ? '' : (string) $ketemu;
    }

    /**
     * Aturan validasi bersama Tambah/Ubah.
     *
     * Barcode unik PER KOPERASI. Aturan unik tidak boleh membandingkan barcode
     * kosong: `unique` dengan nilai '' akan menolak barang kedua yang juga tanpa
     * barcode, padahal itu wajar.
     */
    private function validasi(Request $request, int $kopId): array
    {
        return $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'kode' => ['nullable', 'string', 'max:32'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'nama' => ['required', 'string', 'max:160'],
            'satuan' => ['required', Rule::in(KoperasiBarang::satuan())],
            'kategori' => ['required', Rule::in(array_keys(KoperasiBarang::KATEGORI))],
            'stok' => ['required', 'integer', 'min:0', 'max:1000000'],
            'stok_min' => ['required', 'integer', 'min:0', 'max:1000000'],
            'harga' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'catatan' => ['nullable', 'string', 'max:200'],
            'promo_jenis' => ['nullable', 'string', 'max:10'],
            'promo_nilai' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'promo_min' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'promo_bonus_id' => ['nullable', 'integer', 'min:0'],
        ], [
            'nama.required' => 'Nama barang wajib diisi.',
            'satuan.in' => 'Satuan tidak dikenal.',
            'kategori.in' => 'Kategori tidak dikenal.',
        ]);
    }

    /**
     * Catat satu penyesuaian stok (satu-satunya penulis riwayat).
     *
     * `$sebelum` sengaja jadi PARAMETER, bukan dibaca dari `$b->stok`: nilainya
     * berbeda tergantung kapan ia dipanggil terhadap create()/update(), dan
     * ketergantungan urutan seperti itu menghasilkan riwayat palsu (pernah
     * tercatat "25 -> 25" untuk stok awal yang sebenarnya datang dari 0).
     */
    private function catat(KoperasiBarang $b, int $sebelum, int $baru, string $sebab,
        string $catatan, ?int $userId, int $kopId): void
    {
        KoperasiBarangStok::create([
            'koperasi_barang_id' => $b->id, 'koperasi_id' => $kopId, 'user_id' => $userId,
            'sebelum' => $sebelum, 'sesudah' => $baru, 'selisih' => $baru - $sebelum,
            'sebab' => $sebab, 'catatan' => mb_substr($catatan, 0, 200),
            'tanggal' => now('Asia/Jakarta')->toDateString(), 'dibuat' => kini(),
        ]);
    }
}

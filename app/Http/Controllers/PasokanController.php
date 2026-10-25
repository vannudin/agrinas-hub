<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Koperasi;
use App\Models\Pasokan;
use App\Models\PasokanBayar;
use App\Models\User;
use App\Support\Halaman;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Penyediaan barang dari UMKM lokal.
 *
 * Manajer koperasi mencatat pasokan yang dibeli dari UMKM di sekitarnya
 * (nama UMKM, komoditas, jumlah, harga) dan mengajukannya; admin Agrinas
 * melihat seluruh pasokan dari semua koperasi dan menyetujui/menolaknya.
 * Tiga status saja: Diajukan -> Disetujui / Ditolak, dengan jalan koreksi
 * (kembalikan ke antrean, atau pulihkan penolakan yang keliru). Penerimaan
 * barang fisik dicatat di modul Distribusi, bukan di sini.
 */
class PasokanController extends Controller
{
    /** Disk berkas: sama dengan berkas regulasi Agrinas (storage/app/private). */
    private const DISK = 'r2';

    /** Jenis berkas yang diterima untuk invoice & bukti bayar. */
    private const BERKAS = 'mimes:pdf,png,jpg,jpeg,webp,heic|max:10240';

    private function koperasi(Request $request): ?Koperasi
    {
        $u = $request->user();

        return Koperasi::where('manajer_id', $u->id)->first();
    }

    public function daftar(Request $request)
    {
        $u = $request->user();
        // Pengelola pasokan = pusat & PIC (PIC menyetujui/menolak untuk wilayahnya);
        // manajer hanya mencatat miliknya sendiri. Petugas KEUANGAN juga melihat
        // semuanya — ia yang membayar tagihannya — tetapi bukan "agrinas" untuk
        // urusan barang: $bolehPutus & $bolehBayar yang membedakannya.
        $agrinas = ! $u->manajerKoperasi();
        $lingkup = $u->koperasiQuery()->select('koperasi.id');
        $kop = $u->manajerKoperasi() ? $this->koperasi($request) : null;
        $status = mb_strtoupper((string) $request->query('status', ''));

        $semua = $this->saringPasokan($request, $kop, $lingkup);
        $jumlah = (clone $semua)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        $daftar = $halaman->batasi($semua)->get();

        return view('pasokan.daftar', [
            ...$this->pengajuanPerBaris($daftar),
            'bankOpsi' => PasokanBayar::BANK, 'statusBayar' => PasokanBayar::LABEL,
            'aktif' => 'pasokan', 'agrinas' => $agrinas, 'koperasi' => $kop,
            // Dua wewenang yang BERBEDA, dihitung sekali di sini (dipakai view
            // DAN penjagaan rute — jangan sampai berbeda):
            //   bolehPutus = memutus STATUS BARANGNYA  -> pagar 'wilayah'
            //   bolehBayar = memutus & membayar UANGNYA -> pagar 'bayar'
            'bolehPutus' => $u->agrinas() || $u->hrd() || $u->pic(),
            'bolehBayar' => $u->bolehBayar(),
            'daftar' => $daftar, 'halaman' => $halaman,
            'jumlah' => $jumlah,
            // Kartu status = saringan `status`, jadi parameter itu yang dulu
            // bernama '$status'. `status_kop` terpisah: dulu satu nama dipakai
            // untuk dua hal (saringan kartu & status baris), dan tabrakan itu
            // membuat saringan kartu ikut mengubah data pasokan.
            'status' => $status,
            'cari' => trim((string) $request->query('q', '')),
            'saringStatus' => $this->statusDiminta($request),
            'jenis' => $this->jenisDiminta($request),
            'statJenis' => $this->statistikJenis($lingkup, $kop),
            // `stat` = hitungan SELURUH pasokan dalam lingkup (tanpa saringan) —
            // dipakai untuk angka kartu & daftar pilihan status yang memang ada
            // datanya. `statKop` = versi untuk koperasi (hanya miliknya).
            'stat' => $agrinas ? Pasokan::query()->whereIn('koperasi_id', $lingkup)
                ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all() : [],
            'statKop' => $kop ? Pasokan::query()->where('koperasi_id', $kop->id)
                ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all() : [],
        ]);
    }

    /**
     * Saringan daftar pasokan (cari + filter status baris), SATU tempat untuk
     * kedua jalur: halaman penuh dan potongan AJAX. Kalau disusun terpisah,
     * cepat atau lambat salah satunya tertinggal saat saringan ditambah.
     */
    private function saringPasokan(Request $request, ?Koperasi $kop, $lingkup)
    {
        $cari = trim((string) $request->query('q', ''));
        $st = $this->statusDiminta($request);
        $jenis = $this->jenisDiminta($request);

        return Pasokan::query()
            ->leftJoin('koperasi as k', 'k.id', '=', 'pasokan.koperasi_id')
            ->select('pasokan.*', 'k.nama as nama_koperasi', 'k.kode as kode_koperasi')
            ->when($kop, fn ($w) => $w->where('pasokan.koperasi_id', $kop->id))
            ->when(! $kop, fn ($w) => $w->whereIn('pasokan.koperasi_id', $lingkup))
            ->when($st !== '', fn ($w) => $w->where('pasokan.status', $st))
            ->when($jenis !== '', fn ($w) => $w->where('pasokan.jenis', $jenis))
            ->when($cari !== '', function ($w) use ($cari) {
                // `%` dan `_` dilolosi: keduanya pola LIKE, dan tanpa ini mencari
                // `_` mencocokkan hampir semua baris.
                $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cari).'%';
                $w->where(fn ($x) => $x->where('pasokan.kode', 'like', $pola)
                    ->orWhere('pasokan.umkm_nama', 'like', $pola)
                    ->orWhere('pasokan.komoditas', 'like', $pola)
                    ->orWhere('k.nama', 'like', $pola));
            })
            // Urutan baris: yang masih perlu diputuskan di atas, lalu disetujui,
            // dibayar, terakhir ditolak. Daftar urutannya diambil dari
            // `Pasokan::URUT` supaya query, kartu status, dan peta perpindahan
            // tidak mungkin menyimpang satu sama lain.
            ->orderByRaw(Pasokan::urutSql())
            ->orderByDesc('pasokan.id');
    }

    /** Status baris dari URL; nilai asing atau "SEMUA" berarti tanpa saringan. */
    private function statusDiminta(Request $request): string
    {
        $st = mb_strtoupper((string) $request->query('status_kop', ''));

        return in_array($st, Pasokan::STATUS, true) ? $st : '';
    }

    /** Saringan jenis produk; nilai asing berarti tanpa saringan. */
    private function jenisDiminta(Request $request): string
    {
        $j = mb_strtoupper((string) $request->query('jenis', ''));

        return in_array($j, array_keys(Pasokan::JENIS), true) ? $j : '';
    }

    /** Hitungan pasokan per jenis — dipakai kartu & daftar pilihan saringan. */
    private function statistikJenis($lingkup, ?Koperasi $kop): array
    {
        return Pasokan::query()
            ->when($kop, fn ($w) => $w->where('koperasi_id', $kop->id))
            ->when(! $kop, fn ($w) => $w->whereIn('koperasi_id', $lingkup))
            ->selectRaw('jenis, COUNT(*) as n')->groupBy('jenis')->pluck('n', 'jenis')->all();
    }

    /** Potongan baris tabel pasokan untuk AJAX (tanpa layout halaman). */
    public function baris(Request $request)
    {
        $u = $request->user();
        $agrinas = ! $u->manajerKoperasi();
        $lingkup = $u->koperasiQuery()->select('koperasi.id');
        $kop = $u->manajerKoperasi() ? $this->koperasi($request) : null;

        $q = $this->saringPasokan($request, $kop, $lingkup);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        // Dibuka langsung (bukan lewat fetch): alihkan ke halaman dengan saringan
        // yang sama, supaya pengunjung tidak menerima potongan baris telanjang.
        if (! $this->inginPanel($request)) {
            return redirect()->route('pasokan', $request->query());
        }

        $daftar = $halaman->batasi($q)->get();

        return response()->view('pasokan._baris', [
            ...$this->pengajuanPerBaris($daftar),
            'bolehPutus' => $u->agrinas() || $u->hrd() || $u->pic(),
            'bolehBayar' => $u->bolehBayar(),
            'koperasi' => $kop,
            'daftar' => $daftar,
            'agrinas' => $agrinas,
            'kosong' => ($this->statusDiminta($request) !== '' || trim((string) $request->query('q', '')) !== ''),
        ])->header('X-Jumlah', (string) $jumlah);
    }

    public function simpan(Request $request)
    {
        $u = $request->user();
        if (! $u->manajerKoperasi()) {
            return back()->with('galat', 'Catatan pasokan UMKM diisi manajer koperasi.');
        }
        $kop = $this->koperasi($request);
        if (! $kop) {
            return back()->with('galat', 'Akun ini belum terhubung ke koperasi mana pun.');
        }

        if ($this->inginPanel($request)) {
            $request->headers->set('Accept', 'application/json');
        }
        $d = $request->validate([
            // Jenis produk: Pertanian / Peternakan / UMKM. Bawaan UMKM bila tidak
            // dikirim, supaya formulir lama & gate lama tetap sah.
            'jenis' => ['nullable', 'in:'.implode(',', array_keys(Pasokan::JENIS))],
            'umkm_nama' => ['required', 'string', 'max:120'],
            'umkm_kontak' => ['nullable', 'string', 'max:60'],
            'umkm_alamat' => ['nullable', 'string', 'max:160'],
            'komoditas' => ['required', 'string', 'max:120'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:1000000'],
            'satuan' => ['required', 'in:'.implode(',', Barang::SATUAN)],
            'harga_satuan' => ['required', 'integer', 'min:0', 'max:999999999'],
            // Harga jual ke koperasi lain + opsi tayang di /toko. Keduanya
            // opsional: pasokan yang tidak dijual keluar cukup dicatat & dibayar
            // seperti sebelumnya.
            'harga_jual' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'tayang' => ['nullable', 'in:0,1'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'catatan' => ['nullable', 'string', 'max:300'],
            // Pengajuan pembayaran: rekening tujuan WAJIB (tanpa itu bendahara
            // tidak punya tujuan transfer). Nomor & berkas invoice TIDAK diminta
            // lagi di sini — nomornya DITERBITKAN SISTEM (INV/<kode pasokan>) dan
            // berkasnya opsional, sebab satu pasokan tidak boleh tertahan hanya
            // karena UMKM belum mengirim gambar invoice. Yang mengesahkan
            // pembayaran adalah keputusan pengelola + bukti transfer, bukan
            // gambar tagihan dari UMKM.
            'bank' => ['required', 'string', 'max:60'],
            'no_rekening' => ['required', 'string', 'max:40'],
            'atas_nama' => ['required', 'string', 'max:120'],
            'invoice' => ['nullable', 'file', self::BERKAS],
        ], [
            'no_rekening.required' => 'Nomor rekening tujuan wajib diisi — tanpa itu pembayaran tidak bisa dieksekusi.',
            'atas_nama.required' => 'Nama pemilik rekening wajib diisi.',
            'invoice.mimes' => 'Lampiran invoice harus PDF atau gambar (jpg/png/webp/heic).',
            'invoice.max' => 'Berkas lampiran terlalu besar (maksimal 10 MB).',
        ]);

        $f = $d['invoice'] ?? null;
        $simpan = $f ? $f->store('pasokan/invoice', self::DISK) : '';

        // Field pengajuan (rekening/invoice) TIDAK ikut ke tabel `pasokan`:
        // tempatnya di `pasokan_bayar`. array_diff_key, bukan menyaring manual —
        // daftar field berkas bisa bertambah tanpa mengubah baris ini.
        $isiPasokan = array_diff_key($d, array_flip(['bank', 'no_rekening', 'atas_nama', 'invoice']));
        $p = Pasokan::create($isiPasokan + [
            'kode' => Pasokan::kodeBaru(), 'koperasi_id' => $kop->id,
            'jenis' => $d['jenis'] ?? 'UMKM',
            'harga_jual' => (int) ($d['harga_jual'] ?? 0),
            // Opsi tayang disimpan apa adanya, tetapi etalase /toko hanya
            // menampilkan pasokan yang sudah DISETUJUI pengelola — jadi centang
            // di sini berarti "tayang begitu disetujui", bukan tayang seketika.
            'tayang' => (int) ($d['tayang'] ?? 0),
            'status' => 'DIAJUKAN', 'pemohon' => $u->id, 'dibuat' => kini(), 'diperbarui' => kini(),
        ]);

        PasokanBayar::create([
            'pasokan_id' => $p->id, 'koperasi_id' => $kop->id,
            'bank' => $d['bank'], 'no_rekening' => $d['no_rekening'], 'atas_nama' => $d['atas_nama'],
            // Nomor TERBITAN SISTEM, bukan masukan pengaju.
            'invoice_nomor' => $p->nomorInvoice(), 'invoice_tanggal' => $d['tanggal'],
            'invoice_nama' => $f ? $f->getClientOriginalName() : '', 'invoice_simpan' => $simpan,
            'invoice_ukuran' => $f ? (int) $f->getSize() : 0,
            'invoice_mime' => $f ? (string) $f->getClientMimeType() : '',
            'status' => 'DIAJUKAN', 'diajukan_oleh' => $u->id,
            'diajukan_pada' => kini(), 'dibuat' => kini(),
        ]);

        jurnal($u->id, 'PASOKAN_AJU', $p->kode,
            'umkm='.$p->umkm_nama.' nilai='.$p->nilai().' invoice='.$p->nomorInvoice(), $request->ip() ?? '');

        return back()->with('pesan', 'Pasokan '.$p->kode.' dari '.$p->umkm_nama
            .' diajukan — invoice '.$p->nomorInvoice().' terbit otomatis, menunggu persetujuan & pembayaran Agrinas.');
    }

    /**
     * Invoice RESMI pasokan (PDF A4 satu halaman) — dibuat OTOMATIS sistem.
     *
     * Inilah yang dipakai admin Agrinas sebagai dasar membayar ke UMKM, jadi
     * tidak bergantung pada berkas kiriman UMKM: nomornya terbit dari kode
     * pasokan (`INV/<kode pasokan>`) dan isinya dibaca dari data yang sama
     * dengan tabel. Penerbitnya `App\Support\Pdf` yang sudah dipakai surat jalan
     * — dokumennya teks, garis, dan satu tabel di satu halaman, persis batas
     * kemampuan penulis itu, jadi tidak perlu pustaka PDF baru.
     *
     * Boleh diunduh siapa pun yang berhak melihat pasokan ini (manajer pemilik
     * koperasi, PIC wilayahnya, admin) — dokumen ini MEMANG untuk diserahkan ke
     * UMKM, beda dengan berkas invoice/bukti di `pasokan.bayar.berkas` yang
     * memuat nomor rekening & bukti transfer.
     */
    public function bayar_invoice_pdf(Request $request, int $id)
    {
        $u = $request->user();
        $p = Pasokan::findOrFail($id);
        if (! $u->bolehKoperasi((int) $p->koperasi_id)) {
            abort(403);
        }

        $pdf = Pdf::dariBlade('pasokan.invoice-pdf', [
            // Kunci data model sengaja `pas`, BUKAN `p`: `Pdf::dariBlade`
            // menyuntik penulis dokumennya sebagai `$p`, jadi memakai `p` di
            // sini akan menimpanya (dan view-nya meledak di pemanggilan pertama).
            'pas' => $p,
            'koperasi' => Koperasi::find($p->koperasi_id),
            'pemohon' => $p->pemohon ? User::find($p->pemohon) : null,
            'bayar' => PasokanBayar::where('pasokan_id', $p->id)->orderByDesc('id')->first(),
        ], 'Invoice '.$p->nomorInvoice());

        return response($pdf->keluaran(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$p->kode.'.pdf"',
        ]);
    }

    /** Riwayat pengajuan pembayaran, terbaru di atas. */
    public static function riwayat(int $pasokanId)
    {
        return PasokanBayar::where('pasokan_id', $pasokanId)->orderByDesc('id')->get();
    }

    /**
     * Pengajuan pembayaran untuk sekumpulan pasokan, dikunci per pasokan_id.
     *
     * SATU tempat dipakai halaman penuh DAN potongan AJAX; kalau disusun terpisah,
     * hasil saring/paging cepat atau lambat menampilkan langkah pembayaran yang
     * berbeda dari muat penuh. Yang diambil = pengajuan TERBARU (termasuk yang
     * ditolak, supaya riwayatnya terlihat), plus jumlah pengajuan sebagai penanda
     * pernah ditolak berapa kali.
     */
    private function pengajuanPerBaris($daftar): array
    {
        $id = collect($daftar)->pluck('id')->all();
        if ($id === []) {
            return [];
        }

        $semua = PasokanBayar::whereIn('pasokan_id', $id)->orderByDesc('id')->get();

        return [
            'pengajuan' => $semua->groupBy('pasokan_id')->map(fn ($g) => $g->first())->all(),
            'pengajuanJumlah' => $semua->groupBy('pasokan_id')->map->count()->all(),
        ];
    }

    /**
     * Satu pintu ubah status pasokan: Diajukan -> Disetujui / Ditolak (dan jalan
     * koreksinya). Modal hanya punya SATU pemilih status + SATU keterangan,
     * sehingga kolom Aksi cukup satu tombol dan endpoint-nya pun satu.
     *
     * Tahap "Diterima" dihapus: penerimaan barang adalah urusan distribusi
     * Agrinas, sedangkan pasokan UMKM berhenti pada keputusan pengelola —
     * Disetujui (hijau) berarti pasokan itu diterima.
     */
    public function putus(Request $request, int $id)
    {
        $u = $request->user();
        $p = Pasokan::findOrFail($id);
        if (! $u->bolehKoperasi((int) $p->koperasi_id)) {
            abort(403);
        }

        // Modal meminta JSON: tanpa ini galat validasi dijawab 302 dan modal
        // menganggapnya sukses (lihat DistribusiController::gagalValidasi).
        if ($this->inginPanel($request)) {
            $request->headers->set('Accept', 'application/json');
        }

        $d = $request->validate([
            // Daftar dari MODEL, bukan tulis-tangan: apa pun yang ditawarkan
            // peta perpindahan pasti diterima di sini.
            'status' => ['required', 'in:'.implode(',', Pasokan::SEMUA)],
            'catatan_agrinas' => ['nullable', 'string', 'max:300'],
        ], [], ['status' => 'status', 'catatan_agrinas' => 'keterangan']);

        $ke = $d['status'];
        $ket = trim((string) ($d['catatan_agrinas'] ?? ''));

        if ($ke === $p->status) {
            return $this->balas($request, $p, 'pesan', 'Status '.Pasokan::label($ke).' tidak berubah.');
        }

        // Perpindahan yang sah datang dari PETA DI MODEL (termasuk jalan koreksi
        // untuk data yang sudah Disetujui/Ditolak). Satu sumber dengan view,
        // jadi pilihan yang ditampilkan mustahil ditolak server.
        $boleh = $p->boleh($ke);
        if (! $boleh) {
            return $this->balas($request, $p, 'galat', 'Pasokan '.$p->kode.' berstatus '
                .Pasokan::label($p->status).' — tidak bisa diubah menjadi '.Pasokan::label($ke).'.');
        }

        // Penolakan wajib beralasan: tanpa keterangan, koperasi tak tahu apa
        // yang harus diperbaiki.
        if ($ke === 'DITOLAK' && $ket === '') {
            return $this->balas($request, $p, 'galat', 'Keterangan wajib diisi saat menolak pasokan.');
        }

        $p->update(['status' => $ke, 'catatan_agrinas' => $ket, 'diperbarui' => kini()]);
        jurnal($u->id, 'PASOKAN_'.$ke, $p->kode, $ket, $request->ip() ?? '');

        return $this->balas($request, $p, 'pesan',
            'Pasokan '.$p->kode.' '.mb_strtolower(Pasokan::label($ke)).'.');
    }

    // ---------------------------------------------- Produk Lokal & UMKM: tayang

    /**
     * Opsi manajer: tayangkan / turunkan pasokan sendiri dari etalase /toko
     * supaya bisa dipesan koperasi lain.
     *
     * Hanya manajer PEMILIK pasokan (bukan pengelola pusat — mereka punya
     * /admin/toko untuk katalog gudang, bukan produk lokal).
     *
     * Menayangkan mensyaratkan pasokan sudah DISETUJUI: barang yang belum lolos
     * keputusan pengelola tidak boleh dijual ke koperasi lain. Karena itu
     * menayangkan pasokan yang masih Diajukan DITOLAK dengan alasan yang
     * terbaca, bukan diam-diam gagal.
     */
    public function tayang(Request $request, int $id)
    {
        $u = $request->user();
        $p = Pasokan::findOrFail($id);
        if (! $u->manajerKoperasi() || ! $u->bolehKoperasi((int) $p->koperasi_id)) {
            abort(403, 'Tayangan produk lokal diatur manajer koperasi pemiliknya.');
        }

        $mau = (string) $request->input('tayang') === '1';
        if ($mau && $p->status !== 'DISETUJUI') {
            return back()->with('galat', 'Pasokan '.$p->kode.' belum disetujui pengelola '
                .'(status '.Pasokan::label($p->status).'), jadi belum bisa ditayangkan ke Toko.');
        }

        // Harga jual boleh diisi sekalian dari tombol yang sama; dikosongkan
        // berarti "pakai harga beli dari UMKM" (lihat Pasokan::hargaTawaran).
        $jual = $request->input('harga_jual');
        $ubah = ['tayang' => $mau ? 1 : 0, 'diperbarui' => kini()];
        if ($jual !== null && $jual !== '') {
            $ubah['harga_jual'] = max(0, (int) preg_replace('/\D+/', '', (string) $jual));
        }

        $p->forceFill($ubah)->save();
        jurnal($u->id, 'PASOKAN_TAYANG', $p->kode, $mau ? 'tayang' : 'turun', $request->ip() ?? '');

        return back()->with('pesan', $mau
            ? 'Pasokan '.$p->kode.' ('.$p->komoditas.') tayang di Toko — harga '
                .\App\Models\Keuangan::rupiah($p->hargaTawaran()).' / '.$p->satuan.', bisa dipesan koperasi lain.'
            : 'Pasokan '.$p->kode.' ditarik dari Toko.');
    }

    // ----------------------------------------------- Pengajuan pembayaran pasokan

    /**
     * Pengajuan pembayaran yang sedang berjalan untuk satu pasokan.
     *
     * Dipakai bersama oleh halaman DAN potongan AJAX supaya keduanya mustahil
     * menampilkan langkah yang berbeda. Pasokan lama (sebelum alur ini ada) tidak
     * punya baris pengajuan — itu ditandai apa adanya, bukan dipaksa tampil
     * seolah sudah mengajukan.
     */
    private function pengajuanAktif(int $pasokanId): ?PasokanBayar
    {
        return PasokanBayar::where('pasokan_id', $pasokanId)->orderByDesc('id')
            ->get()->first(fn ($b) => $b->status !== 'DITOLAK');
    }

    /** Pengaju: HANYA manajer koperasi pemilik pasokan itu. */
    public function bayar_ajukan(Request $request, int $id)
    {
        $u = $request->user();
        $p = Pasokan::findOrFail($id);

        if (! $u->manajerKoperasi() || ! $u->bolehKoperasi((int) $p->koperasi_id)) {
            abort(403, 'Pengajuan pembayaran diisi manajer koperasi pemilik pasokan.');
        }
        // Sudah ada pengajuan berjalan: jangan buat tumpukan yang membingungkan
        // bendahara (dua tagihan untuk satu pasokan).
        if ($this->pengajuanAktif($id)) {
            return back()->with('galat', 'Pasokan '.$p->kode.' sudah punya pengajuan pembayaran yang berjalan.');
        }

        $d = $request->validate([
            'bank' => ['required', 'string', 'max:60'],
            'no_rekening' => ['required', 'string', 'max:40'],
            'atas_nama' => ['required', 'string', 'max:120'],
            // Berkas invoice OPSIONAL & nomornya tidak diminta: nomor invoice
            // diterbitkan sistem dari kode pasokan (lihat Pasokan::nomorInvoice).
            'invoice' => ['nullable', 'file', self::BERKAS],
        ], [
            'no_rekening.required' => 'Nomor rekening tujuan wajib diisi.',
            'atas_nama.required' => 'Nama pemilik rekening wajib diisi.',
            'invoice.mimes' => 'Lampiran invoice harus PDF atau gambar (jpg/png/webp/heic).',
            'invoice.max' => 'Berkas lampiran terlalu besar (maksimal 10 MB).',
        ]);

        $f = $d['invoice'] ?? null;
        PasokanBayar::create([
            'pasokan_id' => $p->id, 'koperasi_id' => $p->koperasi_id,
            'bank' => $d['bank'], 'no_rekening' => $d['no_rekening'], 'atas_nama' => $d['atas_nama'],
            'invoice_nomor' => $p->nomorInvoice(), 'invoice_tanggal' => (string) $p->tanggal,
            'invoice_nama' => $f ? $f->getClientOriginalName() : '',
            'invoice_simpan' => $f ? $f->store('pasokan/invoice', self::DISK) : '',
            'invoice_ukuran' => $f ? (int) $f->getSize() : 0,
            'invoice_mime' => $f ? (string) $f->getClientMimeType() : '',
            'status' => 'DIAJUKAN', 'diajukan_oleh' => $u->id, 'diajukan_pada' => kini(), 'dibuat' => kini(),
        ]);

        jurnal($u->id, 'PASOKAN_BAYAR_AJU', $p->kode, 'invoice='.$p->nomorInvoice(), $request->ip() ?? '');

        return back()->with('pesan', 'Pengajuan pembayaran pasokan '.$p->kode
            .' terkirim ke Agrinas — invoice '.$p->nomorInvoice().' menunggu persetujuan.');
    }

    /**
     * Putuskan pengajuan: DISETUJUI / DITOLAK.
     *
     * Berwenang: admin Agrinas (semua koperasi) atau PIC (koperasi wilayahnya).
     * MANAJER TIDAK BOLEH — ia pengaju, dan orang yang mengajukan tidak boleh
     * menyetujui pembayarannya sendiri.
     */
    public function bayar_putus(Request $request, int $id)
    {
        $u = $request->user();
        $b = PasokanBayar::findOrFail($id);
        if (! $u->bolehKoperasi((int) $b->koperasi_id) || $u->manajerKoperasi()) {
            abort(403, 'Persetujuan pembayaran pasokan hanya untuk admin Agrinas atau PIC wilayahnya.');
        }

        $d = $request->validate([
            'status' => ['required', 'in:DISETUJUI,DITOLAK'],
            'catatan' => ['nullable', 'string', 'max:300'],
        ], [], ['status' => 'keputusan', 'catatan' => 'keterangan']);

        if (! $b->boleh($d['status'])) {
            return back()->with('galat', 'Pengajuan ini berstatus '.PasokanBayar::label($b->status)
                .' — tidak bisa diputuskan lagi.');
        }
        // Menolak wajib beralasan: tanpa itu pengaju tidak tahu apa yang salah.
        $ket = trim((string) ($d['catatan'] ?? ''));
        if ($d['status'] === 'DITOLAK' && $ket === '') {
            return back()->with('galat', 'Keterangan wajib diisi saat menolak pengajuan pembayaran.');
        }

        $b->update(['status' => $d['status'], 'catatan' => $ket,
            'diputus_oleh' => $u->id, 'diputus_pada' => kini()]);

        // `pasokan.status` ikut diselaraskan: daftar utama memakai status itu, dan
        // dua sumber status yang berbeda artinya halaman berbohong di salah satunya.
        Pasokan::whereKey($b->pasokan_id)->update(['status' => $d['status'], 'diperbarui' => kini()]);

        jurnal($u->id, 'PASOKAN_'.$d['status'], (string) $b->pasokan_id, $ket, $request->ip() ?? '');

        return back()->with('pesan', 'Pengajuan pembayaran pengajuan#'.$b->id.' '
            .mb_strtolower(PasokanBayar::label($d['status'])).'.');
    }

    /**
     * Bayar: unggah bukti transfer. Berwenang admin Agrinas atau PIC wilayahnya —
     * sama dengan pemutus, dan BUKAN pengaju.
     *
     * Bukti pembayaran WAJIB: menandai "Dibayar" tanpa bukti membuat laporan
     * keuangan tidak bisa diaudit.
     */
    public function bayar_bukti(Request $request, int $id)
    {
        $u = $request->user();
        $b = PasokanBayar::findOrFail($id);
        if (! $u->bolehKoperasi((int) $b->koperasi_id) || $u->manajerKoperasi()) {
            abort(403, 'Pembayaran pasokan hanya untuk admin Agrinas atau PIC wilayahnya.');
        }
        if (! $b->boleh('DIBAYAR')) {
            return back()->with('galat', 'Pengajuan ini berstatus '.PasokanBayar::label($b->status)
                .' — bukti pembayaran hanya bisa diunggah setelah pengajuan DISETUJUI.');
        }

        $d = $request->validate([
            'bukti' => ['required', 'file', self::BERKAS],
            'bukti_catatan' => ['nullable', 'string', 'max:300'],
        ], [
            'bukti.required' => 'Bukti pembayaran wajib diunggah (foto/screenshot transfer).',
            'bukti.mimes' => 'Bukti pembayaran harus PDF atau gambar (jpg/png/webp/heic).',
            'bukti.max' => 'Berkas bukti terlalu besar (maksimal 10 MB).',
        ], ['bukti' => 'bukti pembayaran']);

        $f = $d['bukti'];
        $b->update([
            'bukti_nama' => $f->getClientOriginalName(),
            'bukti_simpan' => $f->store('pasokan/bukti', self::DISK),
            'bukti_ukuran' => (int) $f->getSize(), 'bukti_mime' => (string) $f->getClientMimeType(),
            'bukti_catatan' => trim((string) ($d['bukti_catatan'] ?? '')),
            'status' => 'DIBAYAR', 'dibayar_oleh' => $u->id, 'dibayar_pada' => kini(),
        ]);
        Pasokan::whereKey($b->pasokan_id)->update(['status' => 'DIBAYAR', 'diperbarui' => kini()]);

        jurnal($u->id, 'PASOKAN_DIBAYAR', (string) $b->pasokan_id,
            'bukti='.$f->getClientOriginalName(), $request->ip() ?? '');

        return back()->with('pesan', 'Pembayaran pasokan pengajuan#'.$b->id.' tercatat — bukti tersimpan.');
    }

    /**
     * Unduh invoice / bukti bayar.
     *
     * WAJIB lewat rute ber-`auth` ini, bukan tautan langsung ke storage: berkasnya
     * memuat nomor rekening dan bukti transfer — tidak boleh terbuka bagi siapa pun
     * yang menebak nama berkasnya.
     */
    public function bayar_berkas(Request $request, int $id, string $jenis)
    {
        $u = $request->user();
        $b = PasokanBayar::findOrFail($id);
        if (! $u->bolehKoperasi((int) $b->koperasi_id)) {
            abort(403);
        }

        $path = $jenis === 'bukti' ? (string) $b->bukti_simpan : (string) $b->invoice_simpan;
        $nama = $jenis === 'bukti' ? (string) $b->bukti_nama : (string) $b->invoice_nama;
        if ($path === '' || ! Storage::disk(self::DISK)->exists($path)) {
            abort(404, 'Berkas tidak ditemukan di penyimpanan.');
        }

        return Storage::disk(self::DISK)->download($path, $nama !== '' ? $nama : basename($path));
    }

    /** Apakah pemanggilnya modal (fetch), bukan formulir biasa? */
    private function inginPanel(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson();
    }

    /**
     * Jawaban seragam: JSON untuk modal, alihkan-dengan-pesan untuk form biasa.
     *
     * Rute lama tetap dilayani bentuk yang sama seperti dulu, jadi tautan atau
     * formulir yang masih beredar tidak berubah perilakunya.
     */
    private function balas(Request $request, Pasokan $p, string $kunci, string $pesan)
    {
        if ($this->inginPanel($request)) {
            return response()->json(['ok' => $kunci === 'pesan', $kunci => $pesan,
                'status_label' => Pasokan::label($p->status)], $kunci === 'pesan' ? 200 : 422);
        }

        return back()->with($kunci, $pesan);
    }
}

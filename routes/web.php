<?php

use App\Http\Controllers\AgriAbsenHubController;
use App\Http\Controllers\AgriController;
use App\Http\Controllers\AgriHrisController;
use App\Http\Controllers\AsistenController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\StokKoperasiController;
use App\Http\Controllers\DasborController;
use App\Http\Controllers\DistribusiController;
use App\Http\Controllers\HrdController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\KontrakController;
use App\Http\Controllers\KoperasiController;
use App\Http\Controllers\KerjaController;
use App\Http\Controllers\KomunikasiController;
use App\Http\Controllers\KomunikasiDuaarahController;
use App\Http\Controllers\MasukController;
use App\Http\Controllers\MateriController;
use App\Http\Controllers\MitraController;
use App\Http\Controllers\PasokanController;
use App\Http\Controllers\PelatihanController;
use App\Http\Controllers\PemetaanController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RapatController;
use App\Http\Controllers\RapatSfuController;
use App\Http\Controllers\SimkopdesController;
use App\Http\Controllers\TokoController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VoteController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------- publik
Route::get('/healthz', [BerandaController::class, 'healthz'])->name('healthz');
Route::get('/', [BerandaController::class, 'beranda'])->name('beranda');
Route::get('/artikel', [BerandaController::class, 'artikel'])->name('artikel');

// Sitemap & llms.txt: halaman publik untuk mesin pencari (dan mesin pencari AI).
// Diletakkan di luar grup auth — kalau tidak, perayap akan ditendang ke /masuk.
Route::get('/sitemap.xml', [BerandaController::class, 'sitemap'])->name('sitemap');
Route::get('/llms.txt', [BerandaController::class, 'llms'])->name('llms');
Route::get('/k/{kode}', [BerandaController::class, 'koperasi'])->name('koperasi.publik');

// ---------------------------------------------------------------- masuk
Route::get('/masuk', [MasukController::class, 'formulir'])->name('masuk');

// Gerbang untuk nginx `auth_request` di /video/ (editor OpenCut). Dijawab
// 204 bila sesi Hub hidup, 401 bila tidak — tanpa redirect, karena nginx
// memperlakukan 3xx sebagai "tolak" dan halaman login tidak akan muncul.
Route::get('/__video_cek', fn () => auth()->check()
    ? response('', 204)
    : response('', 401))->name('video.cek');
Route::post('/masuk', [MasukController::class, 'aksi']);
Route::get('/aktivasi', [MasukController::class, 'aktivasi'])->name('aktivasi');
Route::post('/aktivasi', [MasukController::class, 'aktivasiAksi']);
Route::post('/masuk/simkopdes', [MasukController::class, 'lewatToken'])->name('masuk.simkopdes');
// Helper di PC admin mengirim token SIMKOPDES untuk satu akun. Tanpa sesi &
// tanpa CSRF (pemanggilnya mesin); keamanannya kunci bersama di header.
Route::post('/helper/token', [App\Http\Controllers\HelperTokenController::class, 'simpan'])
    ->withoutMiddleware([Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->middleware('throttle:30,1')
    ->name('helper.token');
Route::get('/keluar', [MasukController::class, 'keluar'])->name('keluar');

/*
| Berkas & callback ONLYOFFICE: dipanggil server dokumen tanpa sesi pengguna,
| jadi TIDAK boleh ada di grup `auth`. Penjaganya tanda tangan URL (middleware
| `signed`) yang dibuat dengan masa berlaku 1 jam.
*/
Route::middleware(['signed'])->group(function () {
    Route::get('/workspace/dokumen/{id}/berkas', [WorkspaceController::class, 'dokumen_berkas'])
        ->whereNumber('id')->name('workspace.dokumen.berkas');
    Route::post('/workspace/dokumen/{id}/simpan', [WorkspaceController::class, 'dokumen_simpan'])
        ->whereNumber('id')->name('workspace.dokumen.simpan');
});

// ---------------------------------------------------------------- area masuk
// `pantau` = pagar tulis peran pengawasan (TNI & Dinas Koperasi). Dipasang di
// grup TERLUAR, bukan per modul: halaman baru otomatis ikut terlindungi dan
// tidak ada lagi jalur tulis yang lolos karena lupa ditempeli penjaga.
Route::middleware(['auth', 'pantau'])->group(function () {
    // Profil: identitas (cerminan SIMKOPDES) + ganti sandi. Menu "Keamanan"
    // dilebur ke sini; /ubah-sandi lama dialihkan agar tautan lama tetap hidup.
    Route::get('/profil', [ProfilController::class, 'index'])->name('profil');
    Route::post('/profil', [ProfilController::class, 'simpan'])->name('profil.simpan');
    Route::post('/profil/tarik', [ProfilController::class, 'tarik'])->name('profil.tarik');
    Route::post('/profil/sandi', [ProfilController::class, 'sandi'])->name('profil.sandi');
    Route::get('/ubah-sandi', fn () => redirect('/profil#sandi'));

    // ---- Toko: katalog, keranjang, checkout, pesanan + invoice.
    // Peran Agrinas & PIC memakai prefix /admin/toko (kontrol pusat: harga,
    // kirim barang); manajer memakai /toko tanpa prefix. Rute bernama SAMA
    // supaya satu view melayani dua prefix.
    Route::get('/toko', [TokoController::class, 'katalog'])->name('toko');
    // Potongan katalog untuk gulir-tak-berujung (AJAX). Mengembalikan MARKUP,
    // bukan JSON: satu partial yang sama dipakai muat penuh & gulir, jadi kartu
    // tidak mungkin berbeda antara dua jalur itu.
    Route::get('/toko/muat', [TokoController::class, 'katalog_muat'])->name('toko.muat');
    Route::get('/toko/keranjang', [TokoController::class, 'keranjang'])->name('toko.keranjang');
    Route::post('/toko/keranjang', [TokoController::class, 'keranjang_tambah'])->name('toko.keranjang.tambah');
    Route::post('/toko/keranjang/ubah', [TokoController::class, 'keranjang_ubah'])->name('toko.keranjang.ubah');
    Route::post('/toko/checkout', [TokoController::class, 'checkout'])->name('toko.checkout');
    Route::get('/toko/pesanan', [TokoController::class, 'pesanan'])->name('toko.pesanan');
    Route::get('/toko/pesanan/{id}/invoice', [TokoController::class, 'invoice'])->whereNumber('id')->name('toko.invoice');
    Route::post('/toko/pesanan/{id}/bayar', [TokoController::class, 'pesanan_bayar'])->whereNumber('id')->name('toko.pesanan.bayar');
    Route::post('/toko/pesanan/{id}/kirim', [TokoController::class, 'pesanan_kirim'])->whereNumber('id')->name('toko.pesanan.kirim');
    // Lepas barang dari gudang (pilih kirim / ambil-di-gudang) -> terbit SURAT JALAN.
    Route::post('/toko/pesanan/{id}/lepas', [TokoController::class, 'pesanan_lepas'])->whereNumber('id')->name('toko.pesanan.lepas');
    Route::post('/toko/pesanan/{id}/diambil', [TokoController::class, 'pesanan_diambil'])->whereNumber('id')->name('toko.pesanan.diambil');
    Route::post('/toko/pesanan/{id}/pantau', [TokoController::class, 'pesanan_pantau'])->whereNumber('id')->name('toko.pesanan.pantau');
    Route::get('/toko/pesanan/lampiran/{id}', [TokoController::class, 'lampiran'])->whereNumber('id')->name('toko.lampiran');
    Route::get('/toko/pesanan/{id}/surat-jalan', [TokoController::class, 'surat_jalan'])->whereNumber('id')->name('toko.surat-jalan');
    Route::post('/toko/pesanan/{id}/terima', [TokoController::class, 'pesanan_terima'])->whereNumber('id')->name('toko.pesanan.terima');
    Route::post('/toko/pesanan/{id}/batal', [TokoController::class, 'pesanan_batal'])->whereNumber('id')->name('toko.pesanan.batal');

    // ---- Halaman kerja VENDOR penyalur (akun mitra: BULOG, Pupuk Indonesia, …).
    // Vendor hanya membuka dua halaman ini + /profil. Pagar `vendor` menuntut
    // peran VENDOR yang SUDAH tertaut ke satu baris `vendor` (users.vendor_id);
    // tanpa tautan, halamannya akan menampilkan barang vendor lain.
    Route::middleware(['vendor'])->group(function () {
        Route::get('/mitra', [MitraController::class, 'index'])->name('mitra.index');
        Route::get('/mitra/tagihan', [MitraController::class, 'tagihan'])->name('mitra.tagihan');
        // Barang berangkat ke koperasi (DIBAYAR -> DIKIRIM + surat jalan).
        Route::post('/mitra/pesanan/{id}/kirim', [MitraController::class, 'kirim'])
            ->whereNumber('id')->name('mitra.kirim');
        // Terbitkan invoice yang ditagihkan ke Agrinas (jumlah dari pesanan).
        Route::post('/mitra/pesanan/{id}/tagih', [MitraController::class, 'tagih'])
            ->whereNumber('id')->name('mitra.tagih');
    });

    // ---- Modul manajer
    Route::get('/kerja', [KerjaController::class, 'dasbor'])->name('kerja');

    // Pemungutan "Manager Memilih Sistem": hanya manajer koperasi. Halaman &
    // simpannya di satu gerbang, jadi akun lain tidak bisa menyuarakan apa pun.
    Route::middleware(['manajer'])->group(function () {
        Route::get('/vote', [VoteController::class, 'halaman'])->name('vote');
        Route::post('/vote', [VoteController::class, 'simpan'])->name('vote.simpan');

        // Data koperasi milik manajer sendiri: TAMBAH (pilih desa dari pemetaan
        // lahan), UBAH, HAPUS. Jalannya selama SIMKOPDES tidak bisa
        // disinkronkan: akun manajer masuk tanpa koperasi, sehingga
        // kas/produk/stok kosong. Tanpa tautan koperasi semua fitur itu mati.
        Route::post('/kop', [KoperasiController::class, 'simpan'])->name('kop.simpan');
        Route::post('/kop/{id}', [KoperasiController::class, 'ubah'])->whereNumber('id')->name('kop.ubah');
        Route::post('/kop/{id}/taut', [KoperasiController::class, 'taut'])->whereNumber('id')->name('kop.taut');
        Route::post('/kop/{id}/lepas', [KoperasiController::class, 'lepas'])->whereNumber('id')->name('kop.lepas');
        Route::delete('/kop/{id}', [KoperasiController::class, 'hapus'])->whereNumber('id')->name('kop.hapus');
        // Pencarian desa untuk Select2 (AJAX; 38.861 baris tak bisa jadi <option>).
        Route::get('/kop/lahan', [KoperasiController::class, 'cariLahan'])->name('kop.lahan');
    });
    // Dihapus: /kerja/tugas/{id} menunjuk view `tugas` yang TIDAK ADA di repo ini
    // (500 bila dibuka) dan tak satu pun tautan mengarah ke sana. Detail tugas
    // hidup di /workspace/tugas.

    // Materi/juknis: kedua peran boleh mengunduh, hanya Agrinas yang mengunggah.
    Route::get('/materi', [MateriController::class, 'daftar'])->name('materi');
    Route::get('/materi/{id}/unduh', [MateriController::class, 'unduh'])->name('materi.unduh');
    // Baca langsung di peramban (PDF inline) + sampul kartu materi.
    Route::get('/materi/{id}/baca', [MateriController::class, 'lihat'])->name('materi.baca');
    Route::get('/materi/{id}/sampul', [MateriController::class, 'thumb'])->name('materi.sampul');

    // ---- Kontrak pendamping KDKMP: kontrak + periode + penempatan dalam satu
    // halaman. Salinan SIMKOPDES (diisi saat pendamping login), jadi admin pusat
    // & PIC bisa memeriksanya tanpa akun SIMKOPDES siapa pun. Lingkup disaring
    // di controller: pusat semua, PIC wilayahnya, manajer dirinya.
    Route::get('/kontrak', [KontrakController::class, 'daftar'])->name('kontrak');
    // Potongan baris tabel untuk AJAX (cari & saring tanpa muat ulang).
    // Didaftarkan SEBELUM /{id} supaya tidak pernah tertangkap sebagai id.
    Route::get('/kontrak/baris', [KontrakController::class, 'baris'])->name('kontrak.baris');
    Route::post('/kontrak/tarik', [KontrakController::class, 'tarik'])->name('kontrak.tarik');
    // Berkas PDF diambil SEGAR dari SIMKOPDES (presigned GCS mati ~1 jam), jadi
    // hanya pemilik akunnya yang bisa mengunduh — dijaga di controller.
    Route::get('/kontrak/{id}/berkas', [KontrakController::class, 'berkas'])
        ->whereNumber('id')->name('kontrak.berkas');

    // Pelatihan: materi dari LMS APN + progres belajar per pengguna.
    // Manajer hanya membaca & menandai selesai; pengelolaan ada di grup admin.
    Route::get('/pelatihan', [PelatihanController::class, 'sesi'])->name('pelatihan');
    Route::get('/pelatihan/{lmsId}/baca', [PelatihanController::class, 'baca'])
        ->whereNumber('lmsId')->name('pelatihan.baca');
    Route::post('/pelatihan/{lmsId}/selesai', [PelatihanController::class, 'selesai'])
        ->whereNumber('lmsId')->name('pelatihan.selesai');

    // ------------------------------------------------------------ Konferensi
    // Konferensi video: semua peran boleh menyimak ruang rapat yang sedang/akan
    // jalan. Butir menu manajer kembali ke halaman ini setelah integrasi Lark
    // dibatalkan (2026-10-14).
    Route::get('/rapat', [RapatController::class, 'daftar'])->name('rapat');
    Route::get('/rapat/{id}', [RapatController::class, 'ruang'])->name('rapat.ruang');

    // Rapat Cloudflare Realtime: meeting (dua arah) & webinar (pembicara -> penonton).
    Route::post('/rapat/{id}/sfu/peran', [RapatSfuController::class, 'peran'])->whereNumber('id')->name('rapat.sfu.peran');
    Route::post('/rapat/{id}/sfu/ikut', [RapatSfuController::class, 'ikut'])->whereNumber('id')->name('rapat.sfu.ikut');
    Route::post('/rapat/{id}/sfu/siarkan', [RapatSfuController::class, 'siarkan'])->whereNumber('id')->name('rapat.sfu.siarkan');
    Route::post('/rapat/{id}/sfu/tonton', [RapatSfuController::class, 'tonton'])->whereNumber('id')->name('rapat.sfu.tonton');
    Route::post('/rapat/{id}/sfu/jawab', [RapatSfuController::class, 'jawab'])->whereNumber('id')->name('rapat.sfu.jawab');
    Route::post('/rapat/{id}/sfu/aksi', [RapatSfuController::class, 'aksi'])->whereNumber('id')->name('rapat.sfu.aksi');
    Route::post('/rapat/{id}/sfu/keluar', [RapatSfuController::class, 'berhenti'])->whereNumber('id')->name('rapat.sfu.keluar');

    // ---- Keuangan koperasi: catatan harian -> rekap mingguan/bulanan/tahunan.
    // Manajer mencatat koperasinya; Agrinas melihat semua (pilih lewat ?koperasi=).
    Route::get('/keuangan', [KeuanganController::class, 'index'])->name('keuangan');
    Route::post('/keuangan', [KeuanganController::class, 'simpan'])->name('keuangan.simpan');
    Route::post('/keuangan/{id}/hapus', [KeuanganController::class, 'hapus'])->whereNumber('id')->name('keuangan.hapus');
    Route::get('/keuangan/koperasi', [KeuanganController::class, 'pilih'])->name('keuangan.koperasi');
    Route::get('/keuangan.csv', [KeuanganController::class, 'ekspor'])->name('keuangan.ekspor');

    // ---- Pasokan dari UMKM lokal (di luar distribusi Agrinas).
    Route::get('/pasokan', [PasokanController::class, 'daftar'])->name('pasokan');
    Route::post('/pasokan', [PasokanController::class, 'simpan'])->name('pasokan.simpan');
    // Opsi manajer: tayangkan produk lokal ke etalase /toko agar bisa dipesan
    // koperasi lain (order antar-koperasi). Wewenang rinci dijaga di controller.
    Route::post('/pasokan/{id}/tayang', [PasokanController::class, 'tayang'])
        ->whereNumber('id')->name('pasokan.tayang');
    // Potongan baris tabel pasokan untuk AJAX (cari & filter tanpa muat ulang).
    Route::get('/pasokan/baris', [PasokanController::class, 'baris'])->name('pasokan.baris');
    // ---- Pengajuan pembayaran pasokan UMKM. Grup middleware dipisah menurut
    // PELAKUNYA, bukan sekadar dikelompokkan — `wilayah` (Agrinas pusat/HR/PIC)
    // memblokir manajer koperasi, sedangkan dua rute di bawah justru milik
    // manajer. Sebelumnya `bayar.ajukan` ikut di dalam grup itu, sehingga
    // manajer TIDAK PERNAH bisa mengajukan pembayaran lewat jalurnya sendiri
    // (hanya lewat formulir pasokan baru); cacat itu tak terlihat karena
    // uji-ujinya memakai POST /pasokan.
    //
    //   ajukan + unduh berkas = manajer/pemilik pasokan -> cukup `auth`
    //   putus / bayar.putus / bayar.bukti = Agrinas pusat & PIC -> `wilayah`
    //
    // Wewenang rinci tetap dijaga di controller masing-masing.
    Route::post('/pasokan/{id}/bayar/ajukan', [PasokanController::class, 'bayar_ajukan'])
        ->whereNumber('id')->name('pasokan.bayar.ajukan');
    Route::get('/pasokan/bayar/{id}/berkas/{jenis}', [PasokanController::class, 'bayar_berkas'])
        ->whereNumber('id')->whereIn('jenis', ['invoice', 'bukti'])->name('pasokan.bayar.berkas');

    // Invoice RESMI pasokan (PDF, terbit otomatis dari kode pasokan). Juga di
    // luar grup: dokumen ini untuk DISERAHKAN ke UMKM lewat manajer koperasi.
    Route::get('/pasokan/{id}/invoice.pdf', [PasokanController::class, 'bayar_invoice_pdf'])
        ->whereNumber('id')->name('pasokan.invoice.pdf');

    // Dua tahap yang berbeda, dua pagar yang berbeda:
    //
    //   putus pasokan  = keputusan atas BARANGNYA (belum ada uang bergerak)
    //                    -> 'wilayah' (pusat, HR, PIC)
    //   bayar.*        = keputusan & bukti atas UANGNYA
    //                    -> 'bayar' (pusat, PIC, petugas keuangan)
    //
    // Dipisah karena peran KEUANGAN mengurus uang, bukan stok; sebaliknya PIC
    // boleh memutus barang tanpa mengeluarkan uang.
    Route::middleware(['wilayah'])->group(function () {
        Route::post('/pasokan/{id}/putus', [PasokanController::class, 'putus'])->whereNumber('id')->name('pasokan.putus');
    });

    Route::middleware(['bayar'])->group(function () {
        Route::post('/pasokan/bayar/{id}/putus', [PasokanController::class, 'bayar_putus'])
            ->whereNumber('id')->name('pasokan.bayar.putus');
        Route::post('/pasokan/bayar/{id}/bukti', [PasokanController::class, 'bayar_bukti'])
            ->whereNumber('id')->name('pasokan.bayar.bukti');
    });

    // ---- Asisten AI (9Router lokal).
    Route::get('/asisten', [AsistenController::class, 'index'])->name('asisten');
    Route::post('/asisten', [AsistenController::class, 'tanya'])->name('asisten.tanya');
    Route::post('/asisten/bersih', [AsistenController::class, 'bersih'])->name('asisten.bersih');

    // Ruang kerja pribadi.
    Route::get('/workspace', [WorkspaceController::class, 'index'])->name('workspace');
    // PhotoCraft: editor gambar; dirutekan DI SINI (bukan di luar grup auth)
    // supaya setiap pengguna yang sudah masuk bisa membukanya, peran apa pun.
    Route::get('/workspace/gambar', [WorkspaceController::class, 'gambar'])->name('workspace.gambar');
    // Berkas editor PhotoCraft disalurkan HUB, bukan dibuka dari subdomainnya
    // sendiri. Dua sebab:
    //  1. subdomain photocraft.advi.dev dulu bisa dimuat siapa saja yang tahu
    //     alamatnya; lewat rute ini (di dalam grup `auth`) hanya pengguna yang
    //     sudah masuk yang bisa memuatnya;
    //  2. halaman dan editor jadi SATU asal, jadi cookie sesi Hub tidak perlu
    //     dilebarkan ke subdomain mana pun (SESSION_DOMAIN tetap kosong).
    // Tanpa token di URL — cookie sesi sudah cukup, dan lebih aman.
    Route::get('/workspace/gambar/editor/{berkas?}', [WorkspaceController::class, 'gambar_editor'])
        ->where('berkas', '.*')->name('workspace.gambar.berkas');

    // OpenCut (pengganti FilmCraft): halaman pembungkus editor video. BERKAS
    // editornya TIDAK lewat Laravel — nginx menyajikannya di /video/ dengan
    // gerbang `auth_request` ke /__video_cek (lihat /etc/nginx/agrinas.conf),
    // karena OpenCut aplikasi penuh: aset /assets/… dan navigasi /editor.
    Route::get('/workspace/video', [WorkspaceController::class, 'video'])->name('workspace.video');
    Route::get('/fitur', [WorkspaceController::class, 'fitur'])->name('fitur');

    // ---- Koperasi: SATU halaman kerja koperasi (permintaan Bos 2026-10-18).
    // Berisi data koperasi + status operasional, sarana prasarana, dan POS
    // penjualan. Rutenya `auth` saja (bukan `admin`) karena MANAJER pun
    // memakainya: ia yang menjalankan kasir. Lingkupnya dijaga di controller
    // lewat User::koperasiQuery() — pusat & HR semua, PIC wilayahnya, manajer
    // miliknya sendiri; koperasi di luar lingkup menjawab 404.
    Route::get('/kop', [KoperasiController::class, 'index'])->name('kop');
    Route::get('/kop/sarana', [KoperasiController::class, 'sarana'])->name('kop.sarana');
    Route::get('/kop/pos', [KoperasiController::class, 'pos'])->name('kop.pos');
    Route::post('/kop/pos', [KoperasiController::class, 'jual'])->name('kop.pos.jual');
    Route::post('/kop/pos/{id}/batal', [KoperasiController::class, 'jual_batal'])
        ->whereNumber('id')->name('kop.pos.batal');
    Route::get('/kop/nota/{id}', [KoperasiController::class, 'nota'])
        ->whereNumber('id')->name('kop.nota');
    Route::post('/kop/gerai', [KoperasiController::class, 'geraiSimpan'])->name('kop.gerai.simpan');
    Route::post('/kop/gerai/{id}', [KoperasiController::class, 'geraiUbah'])
        ->whereNumber('id')->name('kop.gerai.ubah');
    Route::delete('/kop/gerai/{id}', [KoperasiController::class, 'geraiHapus'])
        ->whereNumber('id')->name('kop.gerai.hapus');
    // Karyawan koperasi: diisi manajer sendiri (permintaan Bos 2026-10-25).
    Route::get('/kop/karyawan', [KoperasiController::class, 'karyawan'])->name('kop.karyawan');
    Route::post('/kop/karyawan', [KoperasiController::class, 'karyawanSimpan'])->name('kop.karyawan.simpan');
    Route::post('/kop/karyawan/{id}', [KoperasiController::class, 'karyawanUbah'])
        ->whereNumber('id')->name('kop.karyawan.ubah');
    Route::delete('/kop/karyawan/{id}', [KoperasiController::class, 'karyawanHapus'])
        ->whereNumber('id')->name('kop.karyawan.hapus');
    // Status operasional diisi MANAJER (bukan hasil hitungan penarik): SIMKOPDES
    // tidak punya kolomnya, dan tarikan sudah diputus 2026-10-25.
    Route::post('/kop/operasional', [KoperasiController::class, 'operasional'])->name('kop.operasional');
    // Katalog sarana prasarana program: centang apa yang sudah tersedia.
    Route::post('/kop/sarana', [KoperasiController::class, 'centang'])->name('kop.sarana.centang');
    Route::post('/workspace/catatan', [WorkspaceController::class, 'simpan_catatan'])->name('workspace.catatan');
    Route::post('/workspace/tugas-cepat', [WorkspaceController::class, 'tugas_cepat'])->name('workspace.tugas-cepat');
    // Potongan kotak hasil pencarian koperasi untuk pemilih "Tugas Cepat".
    Route::get('/workspace/koperasi', [WorkspaceController::class, 'kop_cari'])->name('workspace.koperasi');

    // ---- Enam modul Ruang Kerja: Messenger, Docs & Sheets, Catatan Harian,
    // Kalender, Tasks, Wiki & Approval. Lingkupnya disaring di controller, sama
    // seperti modul lain (manajer: koperasinya; PIC: wilayahnya; pusat: semua).
    Route::get('/workspace/pesan', [WorkspaceController::class, 'pesan'])->name('workspace.pesan');
    Route::post('/workspace/pesan', [WorkspaceController::class, 'pesan_ruang_baru'])->name('workspace.pesan.baru');
    // Mulai percakapan pribadi. Lewat body (bukan {id} di URL) supaya cukup SATU
    // formulir dengan daftar pilihan, bukan satu formulir per pengguna.
    Route::post('/workspace/pesan/mulai', [WorkspaceController::class, 'pesan_orang'])->name('workspace.pesan.mulai');
    // Pilihan cepat untuk kotak tulis (rekan/dokumen/tugas) — dipakai "Bagikan".
    Route::get('/workspace/pesan/pilihan', [WorkspaceController::class, 'pesan_pilihan'])->name('workspace.pesan.pilihan');
    Route::get('/workspace/pesan/{id}', [WorkspaceController::class, 'pesan_ruang'])->whereNumber('id')->name('workspace.pesan.ruang');
    Route::post('/workspace/pesan/{id}', [WorkspaceController::class, 'pesan_kirim'])->whereNumber('id')->name('workspace.pesan.kirim');
    // Lampiran & pesan suara terpisah: multipart tak bisa lewat badan JSON.
    Route::post('/workspace/pesan/{id}/lampiran', [WorkspaceController::class, 'pesan_lampiran'])->whereNumber('id')->name('workspace.pesan.lampiran');
    Route::post('/workspace/pesan/{id}/suara', [WorkspaceController::class, 'pesan_suara'])->whereNumber('id')->name('workspace.pesan.suara');
    Route::get('/workspace/pesan/lampiran/{id}', [WorkspaceController::class, 'pesan_lampiran_unduh'])->whereNumber('id')->name('workspace.pesan.lampiran.unduh');
    // Satu rute untuk semua aksi pada pesan: reaksi, sematkan, teruskan, sunting, tarik.
    Route::post('/workspace/pesan/{id}/aksi', [WorkspaceController::class, 'pesan_aksi'])->whereNumber('id')->name('workspace.pesan.aksi');
    Route::post('/workspace/pesan/{id}/baca', [WorkspaceController::class, 'pesan_baca'])->whereNumber('id')->name('workspace.pesan.baca');
    Route::post('/workspace/pesan/{id}/semat', [WorkspaceController::class, 'pesan_semat'])->whereNumber('id')->name('workspace.pesan.semat');
    Route::post('/workspace/pesan/{id}/terjemah', [WorkspaceController::class, 'pesan_terjemah'])->whereNumber('id')->name('workspace.pesan.terjemah');

    Route::get('/workspace/dokumen', [WorkspaceController::class, 'dokumen'])->name('workspace.dokumen');
    Route::post('/workspace/dokumen', [WorkspaceController::class, 'dokumen_baru'])->name('workspace.dokumen.baru');
    Route::get('/workspace/dokumen/{id}/sunting', [WorkspaceController::class, 'dokumen_editor'])->whereNumber('id')->name('workspace.dokumen.editor');
    // Editor PENUH di tab tersendiri: tanpa bilah sisi/kepala/nav, satu layar
    // untuk satu dokumen. Rute terpisah (bukan ?penuh=1) supaya tautannya bisa
    // dibuka dari mana pun tanpa membawa keadaan halaman lain.
    Route::get('/workspace/dokumen/{id}/penuh', [WorkspaceController::class, 'dokumen_penuh'])->whereNumber('id')->name('workspace.dokumen.penuh');
    Route::post('/workspace/dokumen/{id}/minta-akses', [WorkspaceController::class, 'dokumen_minta'])
        ->whereNumber('id')->name('workspace.dokumen.minta');
    Route::post('/workspace/dokumen/{id}/putus-akses', [WorkspaceController::class, 'dokumen_putus'])
        ->whereNumber('id')->name('workspace.dokumen.putus');
    Route::post('/workspace/dokumen/{id}/unggah', [WorkspaceController::class, 'dokumen_unggah'])
        ->whereNumber('id')->name('workspace.dokumen.unggah');

    Route::get('/workspace/catatan-harian', [WorkspaceController::class, 'catatan_harian'])->name('workspace.catatan-harian');
    Route::post('/workspace/catatan-harian', [WorkspaceController::class, 'catatan_harian_simpan'])->name('workspace.catatan-harian.simpan');
    Route::delete('/workspace/catatan-harian/{id}', [WorkspaceController::class, 'catatan_harian_hapus'])
        ->whereNumber('id')->name('workspace.catatan-harian.hapus');

    Route::get('/workspace/kalender', [WorkspaceController::class, 'kalender'])->name('workspace.kalender');
    Route::post('/workspace/kalender', [WorkspaceController::class, 'kalender_rapat'])->name('workspace.kalender.rapat');

    Route::get('/workspace/tugas', [WorkspaceController::class, 'tugas'])->name('workspace.tugas');
    Route::post('/workspace/tugas', [WorkspaceController::class, 'tugas_baru'])->name('workspace.tugas.baru');
    Route::post('/workspace/tugas/{id}/langkah', [WorkspaceController::class, 'tugas_langkah'])->whereNumber('id')->name('workspace.tugas.langkah');
    Route::post('/workspace/tugas/{id}/status', [WorkspaceController::class, 'tugas_status'])->whereNumber('id')->name('workspace.tugas.status');
    // Keterangan pengelola pada tugas (alasan revisi/penolakan, instruksi).
    Route::post('/workspace/tugas/{id}/keterangan', [WorkspaceController::class, 'tugas_keterangan'])->whereNumber('id')->name('workspace.tugas.keterangan');
    // Progres pelaksana (catatan + lampiran) dan unduhan lampirannya.
    Route::post('/workspace/tugas/{id}/progres', [WorkspaceController::class, 'tugas_progres'])
        ->whereNumber('id')->name('workspace.tugas.progres');
    Route::get('/workspace/tugas/{id}/progres/{kiriman}', [WorkspaceController::class, 'tugas_progres_berkas'])
        ->whereNumber('id')->whereNumber('kiriman')->name('workspace.tugas.progres.berkas');
    // Rujukan tugas tetap: Job Description Manajer KDKMP (Lampiran 1 PKWT).
    Route::get('/workspace/jobdesc', [WorkspaceController::class, 'jobdesc'])->name('workspace.jobdesc');

    Route::get('/workspace/wiki', [WorkspaceController::class, 'wiki'])->name('workspace.wiki');
    Route::post('/workspace/wiki', [WorkspaceController::class, 'wiki_baru'])->name('workspace.wiki.baru');
    Route::get('/workspace/wiki/{id}', [WorkspaceController::class, 'wiki_lihat'])->whereNumber('id')->name('workspace.wiki.lihat');
    Route::post('/workspace/wiki/{id}', [WorkspaceController::class, 'wiki_simpan'])->whereNumber('id')->name('workspace.wiki.simpan');
    Route::post('/workspace/wiki/{id}/gambar', [WorkspaceController::class, 'wiki_gambar'])->whereNumber('id')->name('workspace.wiki.gambar');
    // Approval (biaya operasional gerai) — halaman SENDIRI, bukan bagian wiki.
    Route::get('/workspace/approval', [WorkspaceController::class, 'approval'])->name('workspace.approval');
    Route::post('/workspace/setuju', [WorkspaceController::class, 'setuju_ajukan'])->name('workspace.setuju.ajukan');
    Route::post('/workspace/setuju/{id}', [WorkspaceController::class, 'setuju_putus'])->whereNumber('id')->name('workspace.setuju.putus');

    // ---- Distribusi barang Agrinas -> koperasi.
    // Daftar & rincian dipakai ketiga peran; aksi dibatasi middleware + guard peran.
    Route::get('/distribusi', [DistribusiController::class, 'daftar'])->name('distribusi.daftar');
    // Potongan baris tabel distribusi untuk AJAX (cari & filter tanpa muat ulang).
    Route::get('/distribusi/baris', [DistribusiController::class, 'baris'])->name('distribusi.baris');
    Route::get('/distribusi/pengajuan', [DistribusiController::class, 'pengajuan'])->name('distribusi.pengajuan');
    // Stok barang KOPERASI (inventaris koperasi, bukan gudang Agrinas).
    // Manajer: CRUD + update stok harian. Admin & PIC: hanya MELIHAT — dan PIC
    // hanya koperasi di wilayahnya (penyaring lingkup di controller).
    Route::get('/distribusi/stok-koperasi', [StokKoperasiController::class, 'index'])->name('distribusi.stok-koperasi');
    // Impor/ekspor CSV: manajer mengisi & memeriksa daftarnya dari luar aplikasi.
    // Rute template SENGAJA tanpa syarat peran — siapa pun yang boleh membuka
    // halamannya boleh melihat susunan kolom yang benar.
    Route::get('/distribusi/stok-koperasi/template', [StokKoperasiController::class, 'template'])->name('distribusi.stok-koperasi.template');
    Route::get('/distribusi/stok-koperasi/ekspor', [StokKoperasiController::class, 'ekspor'])->name('distribusi.stok-koperasi.ekspor');
    Route::post('/distribusi/stok-koperasi/impor', [StokKoperasiController::class, 'impor'])->name('distribusi.stok-koperasi.impor');
    Route::get('/distribusi/stok-koperasi/pilih', [StokKoperasiController::class, 'pilih'])->name('distribusi.stok-koperasi.pilih');
    Route::get('/distribusi/stok-koperasi/cari', [StokKoperasiController::class, 'cari'])->name('distribusi.stok-koperasi.cari');
    Route::post('/distribusi/stok-koperasi', [StokKoperasiController::class, 'simpan'])->name('distribusi.stok-koperasi.simpan');
    Route::post('/distribusi/stok-koperasi/{id}/stok', [StokKoperasiController::class, 'stok'])
        ->whereNumber('id')->name('distribusi.stok-koperasi.stok');
    Route::delete('/distribusi/stok-koperasi/{id}', [StokKoperasiController::class, 'hapus'])
        ->whereNumber('id')->name('distribusi.stok-koperasi.hapus');
    Route::get('/distribusi/{id}', [DistribusiController::class, 'rincian'])->whereNumber('id')->name('distribusi.rincian');
    // Potongan panel untuk modal (tanpa layout halaman).
    Route::get('/distribusi/{id}/panel', [DistribusiController::class, 'panel'])->whereNumber('id')->name('distribusi.panel');
    // Satu pintu ubah status dari modal: satu select + satu keterangan.
    Route::post('/distribusi/{id}/status', [DistribusiController::class, 'simpanStatus'])->whereNumber('id')->name('distribusi.status');
    Route::get('/distribusi/{id}/surat-jalan', [DistribusiController::class, 'suratJalan'])->whereNumber('id')->name('distribusi.surat-jalan');
    Route::get('/distribusi/{id}/surat-jalan.pdf', [DistribusiController::class, 'suratJalanPdf'])->whereNumber('id')->name('distribusi.pdf');
    Route::post('/distribusi/ajukan', [DistribusiController::class, 'ajukan'])->name('distribusi.ajukan');
    // Draf pesanan: menyimpan/membuang pilihan TANPA membuat permintaan.
    Route::post('/distribusi/draf', [DistribusiController::class, 'draf'])->name('distribusi.draf');
    Route::post('/distribusi/draf/hapus', [DistribusiController::class, 'draf_hapus'])->name('distribusi.draf.hapus');
    Route::post('/distribusi/{id}/terima', [DistribusiController::class, 'terima'])->whereNumber('id')->name('distribusi.terima');
    // Stok gudang: pusat & PIC melihat; kirim/tolak permintaan wilayahnya = PIC juga.
    Route::middleware(['wilayah'])->group(function () {
        Route::get('/admin/barang', [DistribusiController::class, 'stok'])->name('stok');
        // Toko sisi pengelola: harga & gambar katalog, plus kirim pesanan.
        // Nama rutenya SAMA dengan rute manajer supaya view-nya satu.
        Route::get('/admin/toko', [TokoController::class, 'kelola'])->name('toko.kelola');
        // CRUD katalog tanpa muat ulang: endpoint JSON, satu partial markup.
        Route::get('/admin/toko/daftar', [TokoController::class, 'barang_daftar'])->name('toko.barang.daftar');
        Route::post('/admin/toko/simpan', [TokoController::class, 'barang_simpan'])->name('toko.barang.simpan');
        Route::post('/admin/toko/{id}/perbarui', [TokoController::class, 'barang_perbarui'])->whereNumber('id')->name('toko.barang.perbarui');
        Route::post('/admin/toko/{id}/aktifkan', [TokoController::class, 'barang_aktifkan'])->whereNumber('id')->name('toko.barang.aktifkan');
        Route::post('/admin/toko/{id}/hapus', [TokoController::class, 'barang_hapus'])->whereNumber('id')->name('toko.barang.hapus');
        // Tombol lama (form penuh) tetap hidup.
        Route::post('/admin/toko/{id}', [TokoController::class, 'barang_ubah'])->whereNumber('id')->name('toko.barang.ubah');
        Route::post('/distribusi/{id}/proses', [DistribusiController::class, 'proses'])->whereNumber('id')->name('distribusi.proses');
        Route::post('/distribusi/{id}/kirim', [DistribusiController::class, 'kirim'])->whereNumber('id')->name('distribusi.kirim');
        Route::post('/distribusi/{id}/tolak', [DistribusiController::class, 'tolak'])->whereNumber('id')->name('distribusi.tolak');
    });
    // Tambah/ubah barang adalah kewenangan pusat.
    Route::middleware(['agrinas'])->group(function () {
        Route::post('/admin/barang', [DistribusiController::class, 'barang_simpan'])->name('barang.simpan');
        Route::post('/admin/barang/{id}', [DistribusiController::class, 'barang_ubah'])->whereNumber('id')->name('barang.ubah');
        // Potongan baris tabel stok untuk AJAX (saring & paging tanpa muat ulang).
        Route::get('/admin/barang/daftar', [DistribusiController::class, 'barang_daftar'])->name('barang.daftar');
    });

    // ---- Modul Agrinas. Modul yang belum dipindahkan sengaja TIDAK didaftarkan
    // supaya tidak ada tautan buntu — menu menyaring dengan Route::has().
    // ---- Dasbor & koperasi: pusat + PIC wilayah (isinya disaring di controller).
    Route::middleware(['wilayah'])->group(function () {
        Route::get('/admin', [DasborController::class, 'dasbor'])->name('admin');
        Route::get('/admin/koperasi', [DasborController::class, 'koperasi'])->name('admin.koperasi');
        // ---- Pemetaan lahan KDKMP: data publik portalkdkmp (desa, luas, potensi,
        // status pembangunan, Babinsa + nomor HP). Pusat + PIC, sama seperti
        // Koperasi: ini gambaran program lintas wilayah, tidak disaring per wilayah.
        Route::get('/admin/pemetaan', [PemetaanController::class, 'index'])->name('admin.pemetaan');
        Route::post('/admin/pemetaan/tarik', [PemetaanController::class, 'tarik'])->name('admin.pemetaan.tarik');
        Route::get('/admin/audit', [DasborController::class, 'audit'])->name('admin.audit');
    });

    // ---- Pemetaan lahan untuk MANAJER: view yang SAMA dengan /admin/pemetaan,
    // hanya-baca (tanpa tombol Sinkronkan, tanpa pemilih "sasaran"). Manajer
    // memeriksa proses pembangunan KDKMP lewat pencarian desa/kecamatan.
    Route::get('/pemetaan', [PemetaanController::class, 'index'])->name('pemetaan');

    // ---- Tautkan akun manajer ke koperasi (kewenangan pusat).
    // Selama SIMKOPDES tidak bisa dihubungi, penautan otomatis lewat profil
    // berhenti: akun manajer baru masuk tanpa koperasi, dan fitur yang butuh
    // koperasi (kas, produk, stok) tampil kosong. Halaman ini jalan manualnya.
    Route::middleware(['agrinas'])->group(function () {
        Route::get('/admin/tautkan-manajer', [DasborController::class, 'tautkan'])->name('admin.tautkan');
        Route::post('/admin/tautkan-manajer', [DasborController::class, 'tautkan_simpan'])->name('admin.tautkan.simpan');
        // Lepas tautan manajer dari koperasi: tanpa ini akun yang sudah tertaut
        // tidak bisa dipindah ke koperasi lain (tidak muncul di daftar pilihan).
        Route::post('/admin/tautkan-manajer/{id}/lepas', [DasborController::class, 'tautkan_lepas'])
            ->whereNumber('id')->name('admin.tautkan.lepas');
    });

    // ---- Meja SDM & Kepegawaian: admin pusat ATAU petugas HR (peran 'HR').
    // Halaman ini memuat identitas & kontrak SELURUH pegawai lintas wilayah, jadi
    // penjaganya 'hrd' — bukan 'wilayah' (PIC memegang satu wilayah saja) dan
    // bukan 'agrinas' (peran HR tidak boleh ikut membuka kendali pusat).
    Route::middleware(['hrd'])->group(function () {
        Route::get('/admin/hr', [HrdController::class, 'index'])->name('hr');
        // Aksi meja SDM. Tiap aksi satu rute sempit ({id} = pegawai/pelamar),
        // jadi tidak ada formulir "serba bisa" yang bisa menulis ke sembarang orang.
        Route::post('/admin/hr/{id}/sdm', [HrdController::class, 'sdm_simpan'])
            ->whereNumber('id')->name('hr.sdm');
        Route::post('/admin/hr/{id}/nilai', [HrdController::class, 'nilai_simpan'])
            ->whereNumber('id')->name('hr.nilai');
        Route::post('/admin/hr/{id}/slip', [HrdController::class, 'slip_simpan'])
            ->whereNumber('id')->name('hr.slip');
        Route::post('/admin/hr/rekrut', [HrdController::class, 'rekrut_simpan'])->name('hr.rekrut');
        // Ekspor kesiapan payroll (rekening + BPJS) & tarik-ulang dari profil
        // SIMKOPDES yang SUDAH tersimpan — tidak ada panggilan API ke luar.
        Route::get('/admin/hr/payroll.csv', [HrdController::class, 'payroll_csv'])->name('hr.payroll.csv');
        Route::post('/admin/hr/payroll/tarik', [HrdController::class, 'payroll_tarik'])->name('hr.payroll.tarik');
        // Potongan baris pelamar (?pelamar=) untuk penyaringan tanpa muat ulang.
        Route::get('/admin/hr/pelamar', [HrdController::class, 'pelamar_semua'])->name('hr.pelamar');
        Route::post('/admin/hr/pelamar/{id}/putus', [HrdController::class, 'keputusan'])
            ->whereNumber('id')->name('hr.keputusan');
    });

    Route::middleware(['agrinas'])->group(function () {
        // Tambah/angkat akun dengan peran pilihan (PIC/HR/Keuangan): kewenangan
        // pusat. Satu jalur untuk semua peran — lihat akun_simpan().
        Route::post('/admin/akun', [KomunikasiController::class, 'akun_simpan'])->name('admin.akun.simpan');
        Route::post('/admin/akun/{id}/aktif', [KomunikasiController::class, 'akun_aktif'])
            ->whereNumber('id')->name('admin.akun.aktif');

        // Integrasi SIMKOPDES: tarik koperasi binaan dari api.simkopdes.go.id.
        Route::get('/admin/simkopdes', [SimkopdesController::class, 'halaman'])->name('admin.simkopdes');
        Route::post('/admin/simkopdes/tarik', [SimkopdesController::class, 'tarik'])->name('admin.simkopdes.tarik');
        // Tarik data lahan/pembangunan KDKMP (verval aset) dari SIMKOPDES.
        // Menulis ke tabel `koperasi_lahan`; idempoten, jadi aman ditekan ulang.
        Route::post('/admin/koperasi/lahan/tarik', [DasborController::class, 'lahan_tarik'])->name('admin.lahan.tarik');
        Route::get('/admin/akun', [KomunikasiController::class, 'akun'])->name('admin.akun');

        Route::get('/admin/vendor', [VendorController::class, 'daftar'])->name('vendor');
        Route::post('/admin/vendor', [VendorController::class, 'simpan'])->name('vendor.simpan');
        // Potongan baris tabel vendor untuk AJAX (cari/filter/paging tanpa muat ulang).
        // Didaftarkan SEBELUM rute /{id} supaya "baris" tidak tertangkap sebagai id.
        Route::get('/admin/vendor/baris', [VendorController::class, 'baris'])->name('vendor.baris');
        Route::post('/admin/vendor/{id}', [VendorController::class, 'ubah'])->whereNumber('id')->name('vendor.ubah');

        Route::get('/admin/broadcast', [KomunikasiController::class, 'siaran'])->name('admin.broadcast');
        Route::post('/admin/broadcast', [KomunikasiController::class, 'siaran_aksi'])->name('admin.broadcast.kirim');
        Route::get('/admin/broadcast/{id}/tujuan.csv', [KomunikasiController::class, 'siaran_tujuan'])
            ->whereNumber('id')->name('admin.broadcast.tujuan');
        // QR gerbang WhatsApp diproksi lewat Hub: kontainer Baileys hanya dengar
        // loopback, jadi tidak bisa dibuka langsung dari peramban.
        Route::get('/admin/broadcast/whatsapp/qr.png', [KomunikasiController::class, 'wa_qr'])
            ->name('admin.broadcast.wa_qr');
        Route::get('/admin/broadcast/whatsapp/status', [KomunikasiController::class, 'wa_status'])
            ->name('admin.broadcast.wa_status');
        Route::post('/admin/broadcast/whatsapp/putus', [KomunikasiController::class, 'wa_putus'])
            ->name('admin.broadcast.wa_putus');

        Route::get('/admin/pelatihan', [PelatihanController::class, 'kelola'])->name('admin.pelatihan');
        Route::post('/admin/pelatihan/tarik', [PelatihanController::class, 'tarik'])->name('admin.pelatihan.tarik');
        Route::post('/admin/pelatihan/{id}/simpan', [PelatihanController::class, 'simpan'])
            ->whereNumber('id')->name('admin.pelatihan.simpan');
        Route::post('/admin/pelatihan/{id}/hapus', [PelatihanController::class, 'hapus'])
            ->whereNumber('id')->name('admin.pelatihan.hapus');

        // Materi: daftar & unggah milik pusat + pengawasan (grup `materi`
        // di bawah); HAPUS tetap kewenangan pusat.
        Route::post('/admin/materi/{id}/hapus', [MateriController::class, 'hapus'])->name('admin.materi.hapus');

        Route::get('/admin/rapat', [RapatController::class, 'kelola'])->name('admin.rapat');
        Route::post('/admin/rapat', [RapatController::class, 'jadwalkan'])->name('admin.rapat.simpan');
        Route::post('/admin/rapat/{id}/hapus', [RapatController::class, 'hapus'])->name('admin.rapat.hapus');
        // Undang pembicara: daftar nama yang boleh bicara di webinar sejak masuk.
        Route::post('/admin/rapat/{id}/undang', [RapatController::class, 'undang'])
            ->whereNumber('id')->name('admin.rapat.undang');
    });

    // ---- Tagihan VENDOR ke Agrinas: pusat, PIC, dan keuangan boleh memutus &
    // melunasi (pagar `bayar`). Didaftarkan DI LUAR grup `agrinas` supaya
    // petugas keuangan bisa membayar tanpa membuka kendali pusat; vendor sendiri
    // tetap ditolak — yang menagih tidak boleh melunasi tagihannya.
    Route::middleware(['bayar'])->group(function () {
        Route::get('/admin/vendor/tagihan', [VendorController::class, 'tagihan'])->name('vendor.tagihan');
        Route::post('/admin/vendor/tagihan/{id}/putus', [VendorController::class, 'tagihan_putus'])
            ->whereNumber('id')->name('vendor.tagihan.putus');
    });

    // ---- Materi pembelajaran: pusat, TNI, dan Dinas Koperasi boleh menambah.
    // Dikeluarkan dari grup `agrinas` supaya dua peran pengawasan bisa
    // menyumbang materi tanpa ikut membuka kendali pusat (akun, siaran, vendor).
    Route::middleware(['materi'])->group(function () {
        Route::get('/admin/materi', [MateriController::class, 'kelola'])->name('admin.materi');
        Route::post('/admin/materi', [MateriController::class, 'unggah'])->name('admin.materi.unggah');
        // Potongan baris tabel materi untuk AJAX (cari/saring tanpa muat ulang).
        // Didaftarkan SEBELUM rute /{id}/hapus supaya "baris" tidak tertangkap id.
        Route::get('/admin/materi/baris', [MateriController::class, 'baris'])->name('admin.materi.baris');
    });
});

    // (Rute /pengumuman & /aspirasi didaftarkan DI LUAR grup `agri` — lihat
    // blok "Komunikasi dua arah" setelah grup ini ditutup.)

    // ---- Komunikasi dua arah dengan manajer (DI LUAR grup `agri`).
    //
    // Ditaruh di sini, bukan di dalam grup `agri`, supaya URL-nya tetap pendek
    // dan sesuai yang dipakai sehari-hari: /pengumuman dan /aspirasi. Di dalam
    // grup itu keduanya otomatis jadi /agri/pengumuman & /agri/aspirasi — dan
    // nama rute `pengumuman` bertabrakan dengan rute lama di atas.
    //
    // Pengumuman (turun): pengelola menerbitkan, manajer membaca. Datanya tetap
    // tabel `broadcast` — tabel baru hanya untuk PENANDA BACA, jadi pengumuman
    // tidak ditulis dua kali dan siaran email/WhatsApp tetap dari /admin/broadcast.
    // Penjaganya 'wilayah' = pengelola yang Bos minta: Agrinas, HR, dan PIC.
    //
    // Aspirasi (naik): SEMUA peran login boleh melihat halamannya; yang mengirim
    // (manajer) dan yang menanggapi (pengelola) diperiksa di dalam controller —
    // satu penjaga per-alur, bukan tiga rute berbeda yang isinya sama.
    // Pengumuman dibaca SEMUA peran (manajer = pembaca utama). Formulir terbit
    // hanya muncul untuk pengelola, dan rutenya tetap diperiksa di controller
    // (`pengumuman_terbit` menolak 403) — jadi tidak butuh penjaga rute kedua.
    // `auth` WAJIB di sini: tanpa itu, permintaan tanpa login lolos sampai ke
    // controller dengan `$request->user()` = null, lalu 500 (bukan dialihkan ke
    // /masuk). Terbukti: `PengumumanBaca::untuk(): Argument #1 ($u) must be of
    // type User, null given`. Penjaga peran tetap di controller (terbit & tanggapi
    // menolak 403), tetapi identitas pemanggil harus ada lebih dulu.
    Route::middleware(['auth'])->group(function () {
        Route::get('/pengumuman', [KomunikasiDuaarahController::class, 'pengumuman'])->name('pengumuman');
        // Detail pengumuman: halaman TERPISAH, dan membukanya otomatis menandai
        // sudah dibaca. `whereNumber` menahan agar '/terbit' tak tertangkap id.
        Route::get('/pengumuman/{id}', [KomunikasiDuaarahController::class, 'pengumuman_detail'])
            ->whereNumber('id')->name('pengumuman.detail');
        Route::post('/pengumuman/terbit', [KomunikasiDuaarahController::class, 'pengumuman_terbit'])->name('pengumuman.terbit');
        Route::post('/pengumuman/baca', [KomunikasiDuaarahController::class, 'pengumuman_baca'])->name('pengumuman.baca');

        // Aspirasi: manajer mengirim, pengelola menanggapi. Penjaganya di controller.
        Route::get('/aspirasi', [KomunikasiDuaarahController::class, 'aspirasi'])->name('aspirasi');
        Route::post('/aspirasi', [KomunikasiDuaarahController::class, 'aspirasi_simpan'])->name('aspirasi.simpan');
    });

    Route::middleware(['wilayah'])->group(function () {
        Route::get('/admin/aspirasi', [KomunikasiDuaarahController::class, 'aspirasi_admin'])->name('admin.aspirasi');
        Route::post('/admin/aspirasi/{id}', [KomunikasiDuaarahController::class, 'aspirasi_tanggapi'])
            ->whereNumber('id')->name('admin.aspirasi.tanggapi');
    });


    /*
    | ---------------------------------------------------------------- AgriConnect
    | Layanan mandiri pegawai, diadopsi dari aplikasi AgriConnect / Agrinas HRIS.
    | Semua peran boleh memakainya — isinya hampir seluruhnya milik pengguna sendiri
    | (presensi, tugas pribadi, poin, pengajuan, umpan balik). Yang lintas pengguna
    | dijaga di controller: persetujuan pengajuan mengikuti wilayah/koperasi, dan
    | katalog hadiah / regulasi / materi hanya boleh ditulis admin Agrinas.
    |
    | Didaftarkan di luar grup admin supaya manajer koperasi pun bisa membukanya —
    | inilah bedanya dengan modul kewenangan pusat di atas.
    */
    Route::middleware(['auth'])->prefix('agri')->name('agri.')->group(function () {
    Route::get('/', [AgriController::class, 'ringkasan'])->name('beranda');

    // Presensi: satu baris per hari; tombol yang sama dua kali tidak menambah baris.
    Route::get('/presensi', [AgriController::class, 'presensi'])->name('presensi');
    Route::post('/presensi', [AgriController::class, 'presensi_simpan'])->name('presensi.simpan');
    // Izin tulis berdurasi pendek: peramban menaruh foto LANGSUNG di R2, dan
    // yang kembali ke formulir hanya NAMANYA. Tidak ada byte foto lewat server.
    Route::post('/presensi/url-unggah', [AgriController::class, 'presensi_url_unggah'])
        ->name('presensi.url');

    // Absen Agrinashub — absen KETIGA, milik sistem ini sendiri. Bentuknya
    // kejadian (masuk/istirahat/keluar), bukan dua kolom jam, jadi istirahat dan
    // jam kerja bersih ikut tercatat. Tidak bergantung pada SIMKOPDES maupun
    // AgriConnect: kalau keduanya lambat atau mati, kehadiran tetap tercatat.
    Route::get('/absen-hub', [AgriAbsenHubController::class, 'index'])->name('absen-hub');
    Route::post('/absen-hub', [AgriAbsenHubController::class, 'simpan'])->name('absen-hub.simpan');
    Route::post('/absen-hub/url-unggah', [AgriAbsenHubController::class, 'urlUnggah'])
        ->name('absen-hub.url');

    // Riwayat untuk AJAX: dipanggil saat mengganti bulan/tahun atau halaman.
    // Mengembalikan HTML yang sudah dirender server (lihat AgriAbsenHubController::riwayat).
    Route::get('/absen-hub/riwayat', [AgriAbsenHubController::class, 'riwayat'])
        ->name('absen-hub.riwayat');
    Route::get('/absen-hub/{id}/foto', [AgriAbsenHubController::class, 'foto'])
        ->whereNumber('id')->name('absen-hub.foto');
    Route::get('/presensi/{id}/foto', [AgriController::class, 'presensi_foto'])
        ->whereNumber('id')->name('presensi.foto');

    // Absensi KDKMP: daftar hadir SIMKOPDES ditarik ke Hub, dan Check In Hub
    // diteruskan ke SIMKOPDES. Peran lain tetap boleh membuka daftarnya (ia
    // kosong), tetapi hanya manajer ber-akun SIMKOPDES yang bisa menghubungkan
    // — lihat penjagaan di AgriController.
    Route::get('/absen', [AgriController::class, 'absen'])->name('absen');
    Route::get('/absen/{id}/foto', [AgriController::class, 'absen_foto'])
        ->whereNumber('id')->name('absen.foto');
    Route::post('/absen/hubungkan', [AgriController::class, 'kdkmp_hubungkan'])->name('absen.hubungkan');
    Route::post('/absen/tarik', [AgriController::class, 'kdkmp_tarik'])->name('absen.tarik');
    Route::post('/absen/kirim', [AgriController::class, 'kdkmp_kirim'])->name('absen.kirim');
    Route::post('/absen/lepas', [AgriController::class, 'kdkmp_lepas'])->name('absen.lepas');

    // Tugas pribadi. Rute `{id}` didaftarkan setelah rute statis.
    Route::get('/tugas', [AgriController::class, 'tugas'])->name('tugas');
    Route::post('/tugas', [AgriController::class, 'tugas_simpan'])->name('tugas.simpan');
    Route::post('/tugas/{id}/status', [AgriController::class, 'tugas_status'])
        ->whereNumber('id')->name('tugas.status');
    Route::delete('/tugas/{id}', [AgriController::class, 'tugas_hapus'])
        ->whereNumber('id')->name('tugas.hapus');

    // Perjalanan dinas / klaim imbalan / pengajuan umum — satu tabel, tiga jenis.
    Route::get('/perjalanan', [AgriController::class, 'perjalanan'])->name('perjalanan');
    Route::post('/perjalanan', [AgriController::class, 'ajuan_simpan'])->name('perjalanan.simpan');
    Route::post('/perjalanan/{id}/putus', [AgriController::class, 'ajuan_putus'])
        ->whereNumber('id')->name('perjalanan.putus');

    // StarPoint: saldo, mutasi, dan katalog hadiah.
    Route::get('/imbalan', [AgriController::class, 'imbalan'])->name('imbalan');
    Route::post('/imbalan', [AgriController::class, 'imbalan_simpan'])->name('imbalan.simpan');
    Route::post('/imbalan/{id}/tukar', [AgriController::class, 'imbalan_tukar'])
        ->whereNumber('id')->name('imbalan.tukar');
    // Tambah & ubah lewat SATU rute (`imbalan.simpan`): `id` di body menentukan.
    // Hapus terpisah karena butuh DELETE.
    Route::delete('/imbalan/{id}', [AgriController::class, 'imbalan_hapus'])
        ->whereNumber('id')->name('imbalan.hapus');

    Route::get('/umpan', [AgriController::class, 'umpan'])->name('umpan');
    Route::post('/umpan', [AgriController::class, 'umpan_simpan'])->name('umpan.simpan');

    // Regulasi: berkas nyata hidup di disk `local`; unduhan lewat rute ber-`auth`
    // ini, bukan tautan langsung ke storage.
    Route::get('/berkas', [AgriController::class, 'berkas'])->name('berkas');
    Route::post('/berkas', [AgriController::class, 'berkas_unggah'])->name('berkas.unggah');
    Route::get('/berkas/{id}/unduh', [AgriController::class, 'berkas_unduh'])
        ->whereNumber('id')->name('berkas.unduh');
    Route::delete('/berkas/{id}', [AgriController::class, 'berkas_hapus'])
        ->whereNumber('id')->name('berkas.hapus');

    // E-Learning: pelajaran berbasis teks + progres per akun.
    Route::get('/pelatihan', [AgriController::class, 'pelatihan'])->name('pelatihan');
    Route::post('/pelatihan', [AgriController::class, 'pelatihan_simpan'])->name('pelatihan.simpan');
    Route::get('/pelatihan/{id}/baca', [AgriController::class, 'pelatihan_baca'])
        ->whereNumber('id')->name('pelatihan.baca');
    Route::post('/pelatihan/{id}/selesai', [AgriController::class, 'pelatihan_selesai'])
        ->whereNumber('id')->name('pelatihan.selesai');

    // Ulang tahun rekan kerja (Employee Birthday).
    Route::get('/ulangtahun', [AgriController::class, 'ulangtahun'])->name('ulangtahun');

    // Pengumuman pindah ke /pengumuman (halaman bersama: pengelola menerbitkan,
    // manajer membaca, plus penanda baca). Nama rute `agri.pengumuman` DIPERTAHANKAN
    // sebagai pengalih supaya tautan lama di kisi modul AgriConnect tidak buntu.
    Route::get('/pengumuman', fn () => redirect()->route('pengumuman'))->name('pengumuman');


    // Konferensi & Webinar. Ditangani RapatController, BUKAN AgriController: satu
    // tempat menyimpan jadwal rapat (tabel `konferensi`), dua tampilan — halaman
    // pegawai di sini dan halaman mandiri /rapat. Tombol "Masuk Ruang" ada di
    // keduanya; dulu halaman ini hanya menyuruh pindah ke Kalender Ruang Kerja,
    // sehingga pengguna melihat jadwalnya tetapi tidak bisa langsung masuk.
    Route::get('/konferensi', [RapatController::class, 'pegawai'])->name('konferensi');

    // Kinerja: rekap dari data yang sudah ada (presensi, tugas, poin) — tanpa tabel baru.
    Route::get('/kinerja', [AgriController::class, 'kinerja'])->name('kinerja');

    // E-Learn terarah: daftar langkah per pelajaran + centang per akun.
    Route::post('/pelatihan/{id}/langkah', [AgriController::class, 'fokus_simpan'])
        ->whereNumber('id')->name('pelatihan.langkah');
    Route::post('/pelatihan/{id}/langkah/{langkah}/centang', [AgriController::class, 'fokus_centang'])
        ->whereNumber('id')->whereNumber('langkah')->name('pelatihan.centang');

    Route::get('/ruang', [AgriController::class, 'ruang'])->name('ruang');
    Route::get('/struktur', [AgriController::class, 'struktur'])->name('struktur');

    // ---- AgriConnect HRIS langsung (bukan data Hub).
    //
    // Halaman-halaman ini membaca API AgriConnect memakai sesi MILIK pengguna
    // yang sedang masuk (`users.agri_sesi`), jadi tidak ada data pegawai yang
    // disalin ke Hub. Akun yang belum tersambung diarahkan ke halaman Akun.
    Route::get('/hris/akun', [AgriHrisController::class, 'akun'])->name('hris.akun');
    Route::post('/hris/akun', [AgriHrisController::class, 'akun_masuk'])->name('hris.masuk');
    Route::post('/hris/akun/keluar', [AgriHrisController::class, 'akun_keluar'])->name('hris.keluar');
    Route::get('/hris/profil', [AgriHrisController::class, 'profil'])->name('hris.profil');
    // Menulis ke AgriConnect (POST /api/user/my-profile/sosmed) — bukan pengajuan.
    Route::post('/hris/profil/sosmed', [AgriHrisController::class, 'profil_sosmed'])->name('hris.profil.sosmed');
    // Biodata (identitas, NPWP, BPJS, rekening) berbentuk PENGAJUAN ke AgriConnect:
    // POST /api/user/self-service/biodata. Dipakai dua formulir — Profil Pegawai
    // dan BPJS & NPWP — jadi rutenya satu.
    Route::post('/hris/profil/biodata', [AgriHrisController::class, 'biodata_simpan'])->name('hris.profil.biodata');
    Route::get('/hris/keluarga', [AgriHrisController::class, 'keluarga'])->name('hris.keluarga');
    // Tambah (tanpa `id`) & ubah (dengan `id`) lewat satu rute: POST /api/user/self-service/family.
    Route::post('/hris/keluarga', [AgriHrisController::class, 'keluarga_simpan'])->name('hris.keluarga.simpan');
    Route::get('/hris/bpjs', [AgriHrisController::class, 'bpjs'])->name('hris.bpjs');
    Route::get('/hris/cuti', [AgriHrisController::class, 'cuti'])->name('hris.cuti');
    // Seluruh pengajuan mandiri (12 jenis) dalam satu halaman baca.
    Route::get('/hris/pengajuan', [AgriHrisController::class, 'pengajuan'])->name('hris.pengajuan');
    Route::get('/hris/gaji', [AgriHrisController::class, 'gaji'])->name('hris.gaji');
    Route::get('/hris/info', [AgriHrisController::class, 'info'])->name('hris.info');
    Route::get('/hris/absensi', [AgriHrisController::class, 'absensi'])->name('hris.absensi');
    // Absen Masuk / Pulang AgriConnect langsung dari Hub (foto + keterangan).
    Route::post('/hris/absensi', [AgriHrisController::class, 'absensi_simpan'])->name('hris.absensi.simpan');
    // Report CheckInOut: laporan absen susulan untuk hari yang tidak bisa diabsen
    // dari aplikasi. Jalur server sama dengan menu "Report CheckInOut" di APK.
    Route::post('/hris/absensi/lapor', [AgriHrisController::class, 'absensi_lapor'])->name('hris.absensi.lapor');
    Route::get('/hris/performa', [AgriHrisController::class, 'performa'])->name('hris.performa');
    });

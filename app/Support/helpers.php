<?php

use App\Models\Audit;
use App\Models\Riwayat;
use Illuminate\Support\Carbon;

if (! function_exists('kini')) {
    /** Stempel waktu warisan: ISO-8601 zona lokal, tanpa mikrodetik. */
    function kini(): string
    {
        return now()->isoFormat('YYYY-MM-DDTHH:mm:ssZ');
    }
}

if (! function_exists('jurnal')) {
    /** Catat aksi ke jurnal audit. `aktor` = id pengguna, atau teks bebas (mis. "cli"). */
    function jurnal(?int $uid, string $aksi, string $target = '', string $detail = '', string $ip = ''): void
    {
        Audit::create([
            'aktor'  => $uid,
            'aksi'   => $aksi,
            'target' => $target,
            'ip'     => $ip,
            'detail' => mb_substr($detail, 0, 500),
            'dibuat' => kini(),
        ]);
    }
}

if (! function_exists('jejak')) {
    /** Catat perubahan status sebuah tugas. */
    function jejak(int $tugasId, string $aktor, string $aksi, string $dari = '', string $ke = '', string $catatan = ''): void
    {
        Riwayat::create([
            'tugas_id' => $tugasId,
            'aktor'    => $aktor,
            'aksi'     => $aksi,
            'dari'     => $dari,
            'ke'       => $ke,
            'catatan'  => mb_substr($catatan, 0, 1000),
            'dibuat'   => kini(),
        ]);
    }
}

if (! function_exists('ikon')) {
    /** Ikon SVG sebaris; path-nya diambil dari Domain::IKON. */
    function ikon(string $nama, string $kelas = 'ik'): string
    {
        // Kunci menu boleh bertitik (mis. 'distribusi.stok-koperasi'). Peta ikon
        // memakai nama pendek, jadi bila kunci utuh tak ada, pakai segmen
        // terakhirnya — satu aturan ini menutup semua kunci bertitik sekaligus.
        $kunci = [trim($nama, '-'), trim(substr((string) strrchr('.'.$nama, '.'), 1), '-')];
        // Kunci modul Ruang Kerja memakai tanda hubung ('catatan-harian') sedangkan
        // peta ikon memakai garis bawah ('catatan_harian'); tanpa terjemahan ini
        // ikon modul itu keluar sebagai <svg> tanpa path (kotak kosong).
        foreach ($kunci as $k) {
            if (App\Models\Domain::IKON[$k] ?? null) {
                return '<svg class="'.e($kelas).'" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
                    .'<path d="'.App\Models\Domain::IKON[$k].'"/></svg>';
            }
        }
        $path = App\Models\Domain::IKON[str_replace('-', '_', $kunci[0])] ?? '';

        return '<svg class="'.e($kelas).'" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            .'<path d="'.$path.'"/></svg>';
    }
}

if (! function_exists('tautan_peta')) {
    /**
     * Tautan Google Maps ke satu titik koordinat.
     *
     * Google Maps LEBIH DULU daripada peta lain di sini karena inilah yang
     * dipakai orang untuk berbagi & menavigasi titik lokasi koperasi.
     *
     * `query` (bukan `q=`) supaya titiknya langsung dibuka sebagai penanda pada
     * peta; parameternya dipisah `&` supaya koma desimal di dalam angka tidak
     * ditebak sebagai pemisah parameter.
     */
    function tautan_peta(float $lintang, float $bujur): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='
            .rawurlencode(koordinat_mentah($lintang, $bujur));
    }
}

if (! function_exists('koordinat_teks')) {
    /**
     * Koordinat untuk DIBACA MANUSIA: koma desimal (kebiasaan Indonesia).
     *
     * Jangan pakai bentuk ini untuk tautan peta: Google Maps menolak koma
     * desimal, jadi orang yang menyalin angkanya dari layar akan gagal.
     */
    function koordinat_teks(float $lintang, float $bujur, int $desimal = 6): string
    {
        $f = fn (float $v) => number_format($v, $desimal, ',', '.');

        return $f($lintang).', '.$f($bujur);
    }
}

if (! function_exists('koordinat_mentah')) {
    /** Koordinat untuk DISALIN ke peta/berkas: titik desimal. */
    function koordinat_mentah(float $lintang, float $bujur, int $desimal = 6): string
    {
        return number_format($lintang, $desimal, '.', '').','.number_format($bujur, $desimal, '.', '');
    }
}

if (! function_exists('tautan_berkas_dokumen')) {
    /**
     * URL bertanda tangan untuk mengunduh berkas dokumen.
     *
     * Rute `workspace.dokumen.berkas` dijaga middleware `signed` (tanpa cek sesi,
     * karena ONLYOFFICE mengambilnya sebagai klien anonim). `route()` saja TIDAK
     * menghasilkan tanda tangan — dulu itu penyebab halaman unduh menjawab
     * "Invalid signature. Forbidden" (403). Semua tautan unduh wajib lewat sini.
     */
    function tautan_berkas_dokumen(\App\Models\Dokumen $dok, int $menit = 60): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'workspace.dokumen.berkas', now()->addMinutes($menit), ['id' => $dok->id]);
    }
}

if (! function_exists('tanggal_id')) {
    /** Tanggal ISO dari DB -> format Indonesia yang enak dibaca.
     *
     * Stempel di basis data ini UTC (config app.timezone = UTC), sedangkan orang
     * membacanya sebagai jam dinding WIB. Tanpa dipindah ke Asia/Jakarta lebih
     * dulu, jam pesan tampil 7 jam lebih awal — pesan pukul 11:27 WIB terbaca
     * "04:27". Zona WAJIB dipasang sebelum format, bukan sesudah. */
    function tanggal_id(?string $iso, string $format = 'd M Y'): string
    {
        if (! $iso) {
            return '—';
        }
        try {
            return Carbon::parse($iso)->timezone('Asia/Jakarta')->locale('id')->translatedFormat($format);
        } catch (Throwable) {
            return $iso;
        }
    }
}

if (! function_exists('waktu_id')) {
    /** Tanggal + jam, untuk kolom audit. */
    function waktu_id(?string $iso): string
    {
        return tanggal_id($iso, 'd M Y H:i');
    }
}

if (! function_exists('ukuran_id')) {
    /** Ukuran berkas dalam satuan yang enak dibaca. */
    function ukuran_id(int $bita): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $satuan) {
            if ($bita < 1024 || $i === 3) {
                return number_format($bita, $i === 0 ? 0 : 1, ',', '.').' '.$satuan;
            }
            $bita /= 1024;
        }

        return $bita.' B';
    }
}

if (! function_exists('boleh_rute')) {
    /**
     * Boleh pengguna ini membuka rute bernama $nama — dibaca dari PENJAGA RUTE
     * itu sendiri (middleware), bukan dari daftar kedua yang ditulis tangan.
     *
     * Ini penutup satu kelas cacat: menu adalah daftar tulis tangan, penjaga
     * rute daftar lain. Setiap kali keduanya menyimpang, pengguna melihat menu
     * yang membalas 403 ("Halaman ini khusus pengguna Agrinas") — persis yang
     * dilaporkan Bos pada akun HR: 22 menu tampil, 6 di antaranya 403.
     * Dengan membaca middleware, menu dan pagar tidak mungkin berbeda lagi.
     */
    function boleh_rute(?\App\Models\User $u, string $nama): bool
    {
        if (! $u) {
            return false;
        }
        $rute = \Illuminate\Support\Facades\Route::getRoutes()->getByName($nama);
        if (! $rute) {
            return false;
        }

        foreach ($rute->gatherMiddleware() as $m) {
            if (! is_string($m)) {
                continue; // middleware objek/kelas langsung: tak bisa dinilai di sini
            }
            $alias = explode(':', $m)[0];
            $boleh = match ($alias) {
                // Tamu sudah ditolak di atas; `auth` selalu lolos di sini.
                'auth' => true,
                'agrinas' => $u->agrinas(),
                'hrd' => $u->hrd(),
                // 'wilayah' = Agrinas pusat ATAU PIC wilayah (kendali khusus pusat
                // diperiksa lagi di controller). Peran pengawasan (TNI/Dinas)
                // ikut karena halaman yang dijaga pagar ini memang halaman
                // pemantauan; pagar tulisnya middleware `pantau`.
                'wilayah' => $u->agrinas() || $u->pic() || $u->hrd() || $u->pantau(),
                // 'bayar' = pagar UANG pada pasokan: pusat, PIC wilayah, keuangan.
                'bayar' => $u->agrinas() || $u->pic() || $u->keuangan(),
                'manajer' => $u->manajerKoperasi(),
                // Halaman kerja mitra penyalur: hanya akun vendor tertaut.
                'vendor' => $u->vendorAkun(),
                // Materi pembelajaran: pusat + dua peran pengawasan.
                'materi' => in_array($u->peran, \App\Models\Domain::PERAN_MATERI, true),
                // 'pantau' bukan pagar peran melainkan pagar TULIS (semua peran
                // lolos untuk membaca), jadi sengaja jatuh ke `default => true`.
                default => true, // web, signed, dsb — bukan pagar peran
            };
            if (! $boleh) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('rute_dasbor')) {
    /** Halaman beranda tiap peran — satu tempat, dipakai menu, redirect, dan kepala. */
    function rute_dasbor(?\App\Models\User $u): string
    {
        return match (true) {
            ! $u => route('beranda'),
            $u->manajerKoperasi() => route('kerja'),
            // Keuangan tidak memegang dasbor pusat (/admin dijaga 'wilayah'),
            // jadi berandanya buku kas — halaman yang memang jadi pekerjaannya.
            $u->keuangan() => route('keuangan'),
            // Vendor mitra juga tidak memegang /admin (dijaga 'wilayah');
            // berandanya halaman mitra. Tanpa cabang ini akun vendor mendarat
            // di halaman yang menolaknya dengan 403.
            $u->vendorAkun() => route('mitra.index'),
            default => route('admin'),
        };
    }
}

if (! function_exists('daftar_menu')) {
    /**
     * Daftar menu tiap peran: [kunci, nama rute, label].
     *
     * DUA penyaring di akhir, keduanya wajib:
     *   1. `Route::has()` — tidak ada tautan buntu ke rute yang tak terdaftar;
     *   2. `boleh_rute()` — hanya halaman yang PENJAGANYA membolehkan peran ini.
     *
     * Penyaring kedua itulah yang membuat menu dan pagar mustahil menyimpang.
     * Sebelumnya keduanya dua daftar tulis tangan: akun HR melihat 22 menu
     * padahal 6 halamannya dijaga `agrinas` dan membalas 403 ("Halaman ini
     * khusus pengguna Agrinas") — persis keluhan Bos 2026-10-19.
     *
     * Urutan penting: tiga butir teratas mengisi bilah bawah ponsel, jadi taruh
     * yang paling sering dipakai di depan.
     */
    function daftar_menu(?\App\Models\User $u): \Illuminate\Support\Collection
    {
        if (! $u) {
            return collect();
        }

        $daftar = match (true) {
            // PIC wilayah: pekerjaan lapangan (distribusi, stok, pasokan, audit)
            // tanpa kendali khusus pusat — Tambah/Ubah koperasi, akun, broadcast,
            // SIMKOPDES, vendor, kelola materi/pelatihan/rapat. Kendali itu memang
            // tidak ada di daftar ini, dan pagar rutenya menjaga kalau berubah.
            $u->pic() => [
                ['dasbor', 'admin', 'Dashboard'], ['keuangan', 'keuangan', 'Keuangan'],
                ['asisten', 'asisten', 'Asisten AI'], ['pesan', 'workspace.pesan', 'Message'],
                ['distribusi', 'distribusi.daftar', 'Distribusi'],
                // PIC tidak berbelanja (katalog khusus manajer) — butirnya menuju
                // daftar PESANAN wilayahnya, bukan katalog yang mengalihkan halaman.
                ['toko', 'toko.pesanan', 'Pesanan Toko'],
                ['stok', 'stok', 'Stok Gudang'], ['pasokan', 'pasokan', 'Produk Lokal & UMKM'],
                ['workspace', 'workspace', 'Ruang Kerja'],
                // Koperasi: SATU butir menu untuk SEMUA peran pengelola, menuju
                // /kop (halaman kerja koperasi). Daftar & lahan (/admin/koperasi)
                // dibuka dari dalam /kop, bukan dari menu tersendiri — dua butir
                // menu untuk satu modul membuat pengguna mengira dua halaman
                // berbeda (keluhan Bos 2026-10-20).
                ['kop', 'kop', 'Koperasi'],
                ['pemetaan', 'admin.pemetaan', 'Pemetaan Lahan'],
                // Kontrak TIDAK didaftarkan: halamannya sudah pindah ke
                // AgriConnect (/agri, seksi Data Pegawai) — dua pintu membingungkan.
                ['pelatihan', 'pelatihan', 'Pelatihan'],
                ['rapat', 'rapat', 'Konferensi'], ['audit', 'admin.audit', 'Audit'],
                // PENGUMUMAN tidak didaftarkan: AgriConnect punya tile-nya dan
                // lonceng di kepala jadi jalan pintasnya.
                ['aspirasi', 'admin.aspirasi', 'Aspirasi Manajer'],
                ['agri', 'agri.beranda', 'AgriConnect'], ['profil', 'profil', 'Profil'],
            ],

            // Admin pusat & petugas HR berbagi meja pusat (SDM, koperasi, audit).
            // Yang membedakan hanya pagar rutenya: /admin/akun, /admin/broadcast,
            // /admin/materi, /admin/pelatihan, /admin/vendor, /admin/rapat dijaga
            // `agrinas`, jadi keenamnya gugur untuk HR lewat `boleh_rute()`.
            $u->hrd() => [
                ['dasbor', 'admin', 'Dashboard'], ['keuangan', 'keuangan', 'Keuangan'],
                ['asisten', 'asisten', 'Asisten AI'], ['pesan', 'workspace.pesan', 'Message'],
                ['distribusi', 'distribusi.daftar', 'Distribusi'],
                ['stok', 'stok', 'Stok Gudang'], ['pasokan', 'pasokan', 'Produk Lokal & UMKM'],
                ['workspace', 'workspace', 'Ruang Kerja'],
                ['vendor', 'vendor', 'Vendor'],
                // Tagihan mitra penyalur: barang sudah dikirim vendor, Agrinas
                // melunasi. Pagar rutenya `bayar`, jadi HR melihatnya hanya
                // kalau memang berwenang membayar.
                ['tagihan', 'vendor.tagihan', 'Tagihan Vendor'],
                // Katalog: pengelola menyediakan barang, memantau order manajer,
                // dan menerbitkan surat jalan.
                ['toko', 'toko.kelola', 'Kelola Katalog'],
                // SIMKOPDES sengaja tidak di menu: halamannya tidak punya
                // pekerjaan yang bisa diselesaikan pengguna (hanya tarik data
                // otomatis). Masih hidup di /admin/simkopdes bila perlu diperiksa.
                // Satu butir "Koperasi" → /kop (sama dengan PIC & manajer).
                ['kop', 'kop', 'Koperasi'], ['akun', 'admin.akun', 'Akun'],
                ['pemetaan', 'admin.pemetaan', 'Pemetaan Lahan'],
                ['aspirasi', 'admin.aspirasi', 'Aspirasi Manajer'],
                ['hr', 'hr', 'SDM & Kepegawaian'],
                ['siaran', 'admin.broadcast', 'Broadcast'], ['materi', 'admin.materi', 'Materi'],
                ['pelatihan', 'admin.pelatihan', 'Pelatihan'],
                // Konferensi tampil untuk SEMUA peran (permintaan Bos 2026-10-19).
                // Admin pusat tetap diarahkan ke meja penjadwalan (/admin/rapat);
                // peran lain ke halaman bersama /rapat — halaman yang sama isinya,
                // tanpa kolom kendali yang bukan miliknya.
                ['rapat', $u->agrinas() ? 'admin.rapat' : 'rapat', 'Konferensi'],
                ['audit', 'admin.audit', 'Audit'],
                ['agri', 'agri.beranda', 'AgriConnect'], ['profil', 'profil', 'Profil'],
            ],

            // Peran PENGAWASAN (TNI & Dinas Koperasi): hanya-baca. Yang dibuka
            // adalah halaman bukti & kondisi lapangan — kas, distribusi, stok,
            // pasokan, koperasi, pemetaan lahan, presensi manajer, audit,
            // aspirasi — plus Kelola Materi (mereka boleh mengunggah materi;
            // hapus tetap milik pusat). Tidak ada menu kendali pusat.
            $u->pantau() => [
                ['dasbor', 'admin', 'Dashboard'],
                ['keuangan', 'keuangan', 'Keuangan'],
                ['distribusi', 'distribusi.daftar', 'Distribusi'],
                ['stok', 'stok', 'Stok Gudang'],
                ['pasokan', 'pasokan', 'Produk Lokal & UMKM'],
                ['kop', 'kop', 'Koperasi'],
                // Pemetaan lahan: TNI disaring menurut wilayahnya sendiri
                // (pusat / provinsi / kabupaten) di PemetaanController.
                ['pemetaan', 'admin.pemetaan', 'Pemetaan Lahan'],
                ['presensi', 'agri.presensi', 'Presensi Manajer'],
                ['audit', 'admin.audit', 'Audit'],
                ['aspirasi', 'admin.aspirasi', 'Aspirasi Manajer'],
                ['materi', 'admin.materi', 'Kelola Materi'],
                ['rapat', 'rapat', 'Konferensi'],
                ['profil', 'profil', 'Profil'],
            ],

            // Petugas keuangan: uang masuk-keluar (kas koperasi, pembayaran
            // pasokan UMKM, pelunasan order toko). Tidak mengurus stok, katalog,
            // koperasi, akun, atau kepegawaian — jadi tidak ada di daftar ini.
            $u->keuangan() => [
                ['keuangan', 'keuangan', 'Keuangan'],
                ['asisten', 'asisten', 'Asisten AI'], ['pesan', 'workspace.pesan', 'Message'],
                ['pasokan', 'pasokan', 'Produk Lokal & UMKM'],
                // Tagihan vendor: pekerjaan uang keluar, tepat di meja keuangan.
                ['tagihan', 'vendor.tagihan', 'Tagihan Vendor'],
                // Toko: mencatat pelunasan order, bukan berbelanja/menyunting
                // katalog — jadi butirnya menuju daftar PESANAN.
                ['toko', 'toko.pesanan', 'Pesanan Toko'],
                ['distribusi', 'distribusi.daftar', 'Distribusi'],
                ['workspace', 'workspace', 'Ruang Kerja'],
                // Keuangan mengurus kas koperasi, jadi ia juga membuka /kop —
                // butir menu yang SAMA dengan peran pengelola lain (keluhan Bos
                // 2026-10-20: satu modul, satu halaman, satu butir menu).
                ['kop', 'kop', 'Koperasi'],
                ['rapat', 'rapat', 'Konferensi'],
                ['agri', 'agri.beranda', 'AgriConnect'], ['profil', 'profil', 'Profil'],
            ],

            // Akun VENDOR penyalur: hanya halaman kerjanya sendiri. Bukan
            // default (manajer) — vendor tidak berbelanja, tidak punya koperasi,
            // dan tidak boleh melihat kas/audit. Tiga butir, karena bilah bawah
            // ponsel mengambil tiga teratas.
            $u->vendorAkun() => [
                ['distribusi', 'mitra.index', 'Pesanan & Distribusi'],
                ['keuangan', 'mitra.tagihan', 'Tagihan ke Agrinas'],
                ['profil', 'profil', 'Profil'],
            ],

            // Manajer koperasi. Asisten AI sengaja ikut 3 teratas: alat kerja
            // harian, bukan menu sesekali.
            default => [
                ['kerja', 'kerja', 'Dashboard'], ['keuangan', 'keuangan', 'Keuangan'],
                ['asisten', 'asisten', 'Asisten AI'], ['pesan', 'workspace.pesan', 'Message'],
                ['distribusi', 'distribusi.daftar', 'Distribusi'], ['toko', 'toko', 'Produk'],
                // "Koperasi" menuju halaman kerja koperasi (/kop): data + status
                // operasional, sarana, POS, dan stok. Kuncinya 'kop' (= nama rute),
                // BUKAN 'koperasi' — menu pusat memakai kunci itu untuk Koperasi
                // Wilayah, dan kunci kembar menyalakan dua butir sekaligus.
                ['kop', 'kop', 'Koperasi'],
                ['pasokan', 'pasokan', 'Produk Lokal & UMKM'],
                // Pemetaan lahan: manajer memantau proses pembangunan KDKMP di
                // desanya (hanya-baca, view sama dengan /admin/pemetaan).
                ['pemetaan', 'pemetaan', 'Pemetaan Lahan'],
                ['workspace', 'workspace', 'Ruang Kerja'], ['materi', 'materi', 'Materi'],
                ['pelatihan', 'pelatihan', 'Pelatihan'], ['rapat', 'rapat', 'Konferensi'],
                // Aspirasi: manajer MENGIRIM masukan ke pengelola.
                ['aspirasi', 'aspirasi', 'Aspirasi'],
                // Pemungutan sistem: butir sementara sampai hasilnya diputuskan.
                ['vote', 'vote', 'Vote Sistem'],
                ['agri', 'agri.beranda', 'AgriConnect'], ['profil', 'profil', 'Profil'],
            ],
        };

        return collect($daftar)
            ->filter(fn ($m) => \Illuminate\Support\Facades\Route::has($m[1]))
            ->filter(fn ($m) => boleh_rute($u, $m[1]))
            ->values();
    }
}

/**
 * Ganti hanya parameter `halaman` milik daftar ini pada URL sekarang — saringan
 * q/status dan `halaman` daftar LAIN ikut terbawa, dan `halaman` selalu berada
 * di urutan terakhir.
 *
 * `$kunci` wajib begitu satu halaman punya dua daftar berhalaman (mis. dasbor
 * manajer: tabel tugas + tabel barang). Keduanya memakai partial pager yang
 * sama, jadi tanpa awalan, menekan "halaman 2" di tabel barang akan menggeser
 * tabel tugas juga — pengguna melihat tabel yang tak ia sentuh ikut berubah.
 */
if (! function_exists('tautan_halaman')) {
    function tautan_halaman(int $n, string $kunci = 'halaman'): string
    {
        $kueri = request()->query();
        // Buang SEMUA kunci halaman milik daftar mana pun ('halaman',
        // 'halaman_barang', …) kecuali kunci yang sedang dipakai. Syarat
        // `$k !== $kunci` yang lama meloloskan 'halaman' tetap ada saat
        // memangku daftar berkunci lain, sehingga URL memuat dua nomor halaman
        // sekaligus dan pengguna bisa dipindah ke halaman yang salah.
        foreach (array_keys($kueri) as $k) {
            if ($k !== $kunci && preg_match('/^halaman(_|$)/', (string) $k)) {
                unset($kueri[$k]);
            }
        }
        $kueri[$kunci] = $n;

        return request()->url().'?'.http_build_query($kueri);
    }
}

/**
 * Buang bagian HTML yang bisa MENJALANKAN skrip, tanpa menyentuh tag lain.
 *
 * Konten pelatihan ditarik dari LMS eksternal lalu dicetak mentah ({!! !!}).
 * Satu modul yang disusupi = skrip berjalan di origin aplikasi ini, lengkap
 * dengan cookie sesi pembacanya. Yang dibuang hanya <script>, atribut `on*`,
 * dan URL `javascript:`; tag, kelas, dan gaya dari editor tetap utuh sehingga
 * tampilan modul tidak berubah.
 *
 * ponytail: penyaring regex, bukan parser DOM. Cukup untuk jalur skrip yang
 * lazim; kalau kelak konten boleh ditulis pengguna biasa, pakai HTMLPurifier.
 */
if (! function_exists('html_aman')) {
    function html_aman(?string $html): string
    {
        $html = (string) $html;
        $html = preg_replace('#<script\b[^>]*>.*?</script\s*>#is', '', $html) ?? '';
        $html = preg_replace('#\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';

        return preg_replace('#(href|src)\s*=\s*(["\']?)\s*(?:javascript|vbscript):[^"\'\s>]*#i', '$1="#"', $html) ?? '';
    }
}

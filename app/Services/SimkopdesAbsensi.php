<?php

namespace App\Services;

use App\Models\Koperasi;
use App\Models\Profil as ProfilModel;
use App\Models\SimkopdesAbsensi as BarisAbsen;
use App\Models\User;
use App\Support\JedaLogin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Absensi DUA ARAH antara Hub dan SIMKOPDES (KDKMP).
 *
 *   turun (SIMKOPDES → Hub) : daftar absensi milik satu manajer ditarik ke
 *                             tabel `simkopdes_absensi`, supaya daftar hadir di
 *                             Hub adalah cermin daftar hadir SIMKOPDES.
 *   naik  (Hub → SIMKOPDES) : Check In di Hub diteruskan ke SIMKOPDES memakai
 *                             foto & titik lokasi YANG SAMA.
 *
 * ## Kenapa memakai kredensial MANAJER, bukan akun di .env
 *
 * Sudah diuji: token SIMKOPDES memang bisa membaca — bahkan menulis — absensi
 * facilitator LAIN (POST atas nama fac lain dijawab 422 sama seperti milik
 * sendiri; tidak ada pemeriksaan kepemilikan). Jadi menulis absensi seluruh
 * manajer dari akun .env itu MUNGKIN, dan justru karena itu ditolak di sini:
 * yang absen atas nama seseorang harus kredensialnya sendiri. Satu akun global
 * juga tidak bisa dipakai — login SIMKOPDES mematikan token sebelumnya (satu
 * sesi per akun), jadi akun bersama akan saling menendang bila dipakai
 * bergantian. Kredensial manajer disimpan terenkripsi di kolom
 * `users.simkopdes_sandi` (APP_KEY), hanya dipakai untuk baris miliknya, dan
 * dihapus begitu ia lepas penanda centang sinkronisasi.
 *
 * ## Kenapa absensi TIDAK PERNAH diubah/dihapus
 *
 * API SIMKOPDES tidak punya endpoint ubah/hapus absensi (GET/PUT/PATCH/DELETE
 * per-ID semuanya 404). Sekali kirim, barisnya tetap di sana dan tidak bisa
 * ditarik kembali. Karena itu:
 *   - sinkronisasi bersifat IDEMPOTEN: tanggal yang sudah tercatat tidak
 *     dikirim dua kali;
 *   - absensi LEWAT JAM tidak dikirim otomatis (lihat CUTOFF_JAM) — menulis
 *     "check_in 19:00" untuk kedatangan pagi adalah bukti palsu yang permanen;
 *   - `tandaiTerkirim()` dipanggil hanya setelah server menjawab 2xx.
 *
 * ponytail: tanpa antrean/pekerja latar. Satu permintaan sinkron = satu panggilan
 * HTTP (timeout 30 dtk, seperti Simkopdes::kirim). Kalau nanti satu manajer
 * punya ratusan baris, pindahkan ke queue — bukan sekarang.
 */
class SimkopdesAbsensi
{
    /** Kunci/IV protokol payload SIMKOPDES (AES-256-CBC, dari bundle frontend). */
    private const PROTO_KEY = 'KUNCI_DIHAPUS';
    private const PROTO_IV = 'IV_DIHAPUS';

    private const URL = 'https://api.simkopdes.go.id/api';
    private const DISK = 'r2';

    /**
     * Batas jam (WIB) untuk pengiriman OTOMATIS saat Check In. Absensi
     * SIMKOPDES permanen: mengirim "check_in 18:41" untuk orang yang masuk
     * pagi bukan sinkronisasi, itu catatan keliru yang tidak bisa dibatalkan.
     * Setelah jam ini absen tetap tercatat di Hub, dan barisnya bisa dikirim
     * sengaja lewat halaman Absen KDKMP (atau perintah `absen:simkopdes`).
     */
    public const CUTOFF_JAM = '16:00';

    /** Ukuran foto terbesar yang mau diunggah; lebih dari ini baris dibiarkan tertunda. */
    public const MAKS_FOTO = 4 * 1024 * 1024;

    /** Batas ukuran `start_photo` yang diterima server (untuk pesan galat yang jelas). */
    public const BATAS_SERVER = 10 * 1024 * 1024;

    // ------------------------------------------------------------------ konteks

    public function manajer(string $email): ?User
    {
        return User::where('email', mb_strtolower(trim($email)))->first();
    }

    /** Terkonfigurasi = punya sandi, facilitator id, periode, dan koperasi binaan. */
    public function terkonfigurasi(User $u): bool
    {
        if (! $u->manajerKoperasi()) {
            return false;
        }
        if ($this->facilitatorId($u) <= 0) {
            return false;
        }
        if ((string) $u->simkopdes_sandi === '' && $this->tokenBrowser($u) === '') {
            // Tanpa sandi/token akun ini tidak bisa mengirim apa pun — tidak ada
            // jalan lain (SIMKOPDES tanpa OAuth/SSO).
            return false;
        }

        // SENGAJA TANPA panggilan jaringan: profil (periode & koperasi) selalu
        // tersimpan bersama sandinya — di `titipSandiLogin()` saat login, atau di
        // halaman Absen KDKMP saat menghubungkan. Fungsi ini cuma MEMUTUSKAN
        // apakah akun siap, jadi ia boleh dipanggil saat menggambar halaman
        // (lencana di /agri/presensi) tanpa risiko menggantung.
        return $this->periodeId($u) > 0 && $this->koperasiId($u) > 0;
    }

    /**
     * Facilitator id pendamping — kunci penyambung baris absensi SIMKOPDES ke
     * pemiliknya di Hub.
     *
     * Sumber pertama `users.simkopdes_facilitator_id` (diisi saat login & backfill),
     * BUKAN `users.simkopdes_id` — isinya tidak konsisten antar jalur login.
     * Blob profil jadi cadangan: ia baru ada setelah profil pernah ditarik, dan
     * akun yang menghubungkan pertama kali justru belum punya.
     */
    public function facilitatorId(User $u): int
    {
        $kolom = (int) ($u->simkopdes_facilitator_id ?? 0);
        if ($kolom > 0) {
            return $kolom;
        }

        return (int) ($this->blobProfil($u)['regional_facilitator']['regional_facilitator_id'] ?? 0);
    }

    /**
     * Periode pendampingan yang dipakai, dibaca dari blob profil
     * (`regional_facilitator_periods`, ikut payload login).
     *
     * SENGAJA tanpa panggilan HTTP cadangan. Dulu ada cabang ke
     * `by-facilitator-id/{fac}` di sini, dan akibatnya fungsi PEMUTUS ini bisa
     * menghubungi jaringan — padahal ia dipanggil saat menggambar halaman
     * (lencana /agri/presensi), sehingga halaman bisa menggantung karena
     * SIMKOPDES lambat. Jalur itu juga sudah dijawab 403 pada 8 Okt 2026. Kalau
     * profilnya belum memuat periode, akun dianggap belum siap dan diambil pada
     * tindakan yang memang menghubungi jaringan (`ambilProfil` lewat tombol
     * hubungkan / tarik).
     */
    public function periodeId(User $u): int
    {
        $periode = (array) ($this->blobProfil($u)['regional_facilitator_periods'] ?? []);
        $ids = array_filter(array_map(
            fn ($p) => (int) ($p['regional_facilitator_period_id'] ?? ($p['regional_facilitator_assistance_period_id'] ?? 0)), $periode));
        return $ids === [] ? 0 : max($ids);
    }

    /** Id koperasi binaan di SIMKOPDES (profil dulu, lalu koperasi Hub). */
    public function koperasiId(User $u): int
    {
        $id = (int) ($u->koperasi_simkopdes_id ?? 0);
        if ($id > 0) {
            return $id;
        }

        $p = ProfilModel::where('user_id', $u->id)->value('koperasi_simkopdes_id');
        if ((int) $p > 0) {
            return (int) $p;
        }

        return (int) (Koperasi::where('manajer_id', $u->id)->value('koperasi_simkopdes_id') ?? 0);
    }

    /** Nama koperasi binaan — dipakai sebagai keterangan titik lokasi. */
    public function namaKoperasi(User $u): string
    {
        $c = (array) $this->blobProfil($u);
        foreach ((array) ($c['cooperatives'] ?? []) as $baris) {
            $nama = trim((string) ($baris['name'] ?? ''));
            if ($nama !== '') {
                return $nama;
            }
        }

        return (string) (Koperasi::where('manajer_id', $u->id)->value('nama') ?? '');
    }

    /** Blob profil SIMKOPDES milik manajer ini (sumber id/period/koperasi). */
    private function blobProfil(User $u): array
    {
        return (array) (ProfilModel::where('user_id', $u->id)->value('profil') ?? []);
    }

    // ------------------------------------------------------------- kredensial/token

    public function simpanSandi(User $u, string $sandi): void
    {
        $u->forceFill(['simkopdes_sandi' => Crypt::encryptString(trim($sandi))])->save();
    }

    public function lupakanSandi(User $u): void
    {
        $u->forceFill(['simkopdes_sandi' => '', 'simkopdes_token' => ''])->save();
        Cache::forget("absen.tok.{$u->id}");
    }

    /**
     * Baca profil pemilik token mentah — dipakai jalur "masuk lewat token"
     * sebelum akunnya diketahui. Melempar RuntimeException kalau token ditolak.
     *
     * @return array<string, mixed>
     */
    public function profilToken(string $token): array
    {
        $token = trim($token);
        if (strlen($token) < 20 || preg_match('/\s/', $token) === 1) {
            throw new \RuntimeException('Token SIMKOPDES tidak sah.');
        }

        $r = $this->mentah('GET', '/profile', $token);
        $profil = $this->buka(['data' => $r['user'] ?? null]);

        return is_array($profil) ? $profil : [];
    }

    /**
     * Simpan token yang diperoleh DARI BROWSER pengguna sendiri.
     *
     * Sejak 23-10-2026 `POST /api/login` menjawab 403 untuk semua akun (halaman
     * login SIMKOPDES memakai reCAPTCHA), jadi login otomatis dari server tidak
     * mungkin lagi. Pengguna login sendiri di simkopdes.go.id — IP dan captcha
     * miliknya — lalu menyerahkan token sesinya ke Hub. Token diuji dulu ke
     * `/profile`: yang tidak sah tidak menyimpan apa pun.
     *
     * @return array<string, mixed> profil hasil `/profile` (bentuknya sama dengan payload login)
     */
    public function simpanTokenBrowser(User $u, string $token): array
    {
        $token = trim($token);
        $profil = $this->profilToken($token);

        $u->forceFill(['simkopdes_token' => Crypt::encryptString($token)])->save();
        $this->simpanSesi($u, $token, $profil);

        return $profil;
    }

    /** Token yang diserahkan browser (`simkopdes_token`), '' bila belum ada. */
    public function tokenBrowser(User $u): string
    {
        $isi = (string) ($u->simkopdes_token ?? '');
        if ($isi === '') {
            return '';
        }

        try {
            return (string) Crypt::decryptString($isi);
        } catch (\Throwable) {
            // APP_KEY berubah atau nilainya rusak — jangan bikin halaman 500.
            return '';
        }
    }

    /**
     * Token milik MANAJER ini, di-cache 20 jam (umur token ~1 hari).
     *
     * PENTING: jangan pakai `Simkopdes::token()` di sini. Token itu milik akun
     * di .env, dan SIMKOPDES hanya mengizinkan SATU sesi per akun — memakainya
     * untuk membaca absensi manajer lain akan menendang sesi akun tersebut.
     */
    public function token(User $u): string
    {
        // Token yang diserahkan browser pengguna dipakai apa adanya: login
        // otomatis dari server sudah ditolak SIMKOPDES sejak 23-10-2026.
        $browser = $this->tokenBrowser($u);
        if ($browser !== '') {
            Cache::put("absen.tok.{$u->id}", $browser, now()->addHours(20));
            Cache::put('absen.tokowner.'.sha1($browser), (int) $u->id, now()->addHours(20));

            return $browser;
        }

        $sandi = $this->sandi($u);
        if ($sandi === '') {
            throw new \RuntimeException('Sandi SIMKOPDES belum diisi untuk akun ini.');
        }

        return Cache::remember("absen.tok.{$u->id}", now()->addHours(20), function () use ($u, $sandi) {
            $r = $this->mentah('POST', '/login', null,
                ['email' => $u->email, 'password' => $sandi]);

            $tok = $r['token'] ?? null;
            if (! is_string($tok) || $tok === '') {
                throw new \RuntimeException('SIMKOPDES menolak login akun '.$u->email.'.');
            }

            // Pemilik token dicatat TERPISAH (kunci = sidik token), supaya saat
            // token dijawab 401 kita tahu harus login ulang sebagai siapa.
            Cache::put('absen.tokowner.'.sha1($tok), (int) $u->id, now()->addHours(20));

            return $tok;
        });
    }

    public function segarkanToken(User $u): string
    {
        Cache::forget("absen.tok.{$u->id}");

        return $this->token($u);
    }

    /**
     * Titipkan sandi yang BARU SAJA dipakai login (jalur 1 akun Hub).
     *
     * SIMKOPDES hanya punya satu jenis sandi: sandi akun. Kalau sandi yang lolos
     * login Hub juga diterima SIMKOPDES, maka sandi akun ini memang sama — dan
     * absensi boleh ikut tersinkron tanpa meminta sandi lagi. Kalau tidak, tidak
     * ada yang rusak; halaman Absen KDKMP tetap menyediakan tombol hubungkan.
     *
     * Dipanggil HANYA untuk `sandi_sumber='SIMKOPDES'` (sandi warisan SIMKOPDES),
     * TIDAK untuk sandi Hub. Kalau orang mengganti sandi Hub-nya, sandi itu kita
     * ketahui BUKAN sandi SIMKOPDES-nya — menitipkannya ke sana cuma menambah
     * percobaan login gagal (SIMKOPDES membatasi 120 permintaan).
     *
     * @return bool true bila sandi ini sah di SIMKOPDES (absensi bisa sinkron)
     */
    public function titipSandiLogin(User $u, string $sandi): bool
    {
        if (! $u->manajerKoperasi() || (string) $u->sandi_sumber !== 'SIMKOPDES') {
            return false;
        }
        // Sudah punya sandi tersimpan DAN profilnya lengkap: tidak perlu login
        // ulang — sinkronisasi langsung berjalan dengan sandi itu.
        if ((string) $u->simkopdes_sandi !== '' && $this->blobProfil($u) !== []) {
            return true;
        }

        // `masukSebagai()` melakukan login yang sama SEKALIGUS menyimpan profil
        // (periode & koperasi binaan hanya ada di payload itu). Login ganda tidak
        // mungkin dipakai di sini: SIMKOPDES hanya mengizinkan satu sesi per akun,
        // jadi login kedua justru mematikan sesi yang barusan dibuat.
        $profil = $this->masukSebagai($u, $sandi);

        if (($profil['regional_facilitator'] ?? null) === null) {
            return false;
        }

        $this->simpanSandi($u, $sandi);

        return true;
    }

    /**
     * Tarik daftar hadir SEKARANG, di dalam permintaan login, tetapi hanya
     * selama ringkasannya masih cepat.
     *
     * Yang dijaga bukan profilnya, melainkan presensinya: ini berjalan di jalur
     * login, jadi ia TIDAK boleh menggantung sampai peramban menyerah. Habis
     * waktu → berhenti, sisa halaman ditarik lewat tombol "Tarik daftar hadir"
     * di halaman Absen KDKMP. Itu sebabnya `utuh` dikembalikan: supaya pemanggil
     * tahu tarikannya belum lengkap, bukan diam-diam mengira sudah semua.
     *
     * @return array{baris: int, halaman: int, utuh: bool}
     */
    public function tarikRingkas(User $u, float $detik = 3.0, int $maksHalaman = 6): array
    {
        // Sisa waktu WAJIB disisakan untuk menyelesaikan permintaan HTTP yang
        // sedang jalan (± 30 dtk batasnya), jadi anggaran tarikan ditekan ke
        // `$detik` dengan margin 5 dtk.
        $r = $this->tarik($u, null, $maksHalaman, microtime(true) + max(1.0, $detik - 5.0));

        return $r + ['utuh' => $r['halaman'] < $maksHalaman];
    }

    /**
     * Profil SIMKOPDES pemilik akun ini, dibaca dari payload login-nya sendiri.
     *
     * Dipakai saat menghubungkan akun: dari sini datang facilitator id, periode,
     * dan koperasi binaan — tanpa perlu endpoint `detail/{id}` yang menuntut
     * kita sudah tahu id-nya lebih dulu. Login ini sekaligus menyegarkan token
     * yang tersimpan, karena SIMKOPDES hanya mengizinkan satu sesi per akun.
     *
     * @return array<string, mixed>
     */
    public function ambilProfil(User $u): array
    {
        // Token dari browser: profilnya dibaca lewat `GET /profile`, bentuk
        // payloadnya sama dengan login (`{message,user}`), jadi jalur simpan
        // profil di bawah tetap satu.
        $browser = $this->tokenBrowser($u);
        if ($browser !== '') {
            $r = $this->mentah('GET', '/profile', $browser);
            $profil = $this->buka(['data' => $r['user'] ?? null]);

            return is_array($profil) ? $profil : [];
        }

        $sandi = $this->sandi($u);
        if ($sandi === '') {
            throw new \RuntimeException('Sandi SIMKOPDES belum diisi untuk akun ini.');
        }

        return $this->masukSebagai($u, $sandi);
    }

    /**
     * Login dengan sandi yang DIBERIKAN, lalu simpan token + profilnya.
     *
     * Sandi lewat sebagai argumen (bukan dibaca dari kolom) karena ada dua
     * pemanggil dengan sumber sandi berbeda: `ambilProfil()` memakai sandi yang
     * tersimpan, sedangkan `titipSandiLogin()` justru sedang menguji sandi yang
     * baru dipakai login — saat itu kolomnya masih kosong.
     *
     * Disimpan SEKALI di sini, di tempat profil ini lahir. Periode & koperasi
     * binaan HANYA ada di payload login, dan semua keputusan lain
     * (`terkonfigurasi`, `periodeId`, `tarik`) membacanya dari tabel `profil`.
     * Dulu penyimpanannya menumpang di dalam `terkonfigurasi()`, sehingga fungsi
     * PEMUTUS itu ikut menghubungi jaringan — halaman /agri/presensi bisa
     * menggantung karenanya.
     *
     * @return array<string, mixed>
     */
    private function masukSebagai(User $u, string $sandi): array
    {
        $r = $this->mentah('POST', '/login', null, ['email' => $u->email, 'password' => $sandi]);

        $tok = $r['token'] ?? null;
        if (! is_string($tok) || $tok === '') {
            throw new \RuntimeException('SIMKOPDES menolak login akun '.$u->email.'.');
        }

        $profil = $this->buka(['data' => $r['user'] ?? null]);
        $profil = is_array($profil) ? $profil : [];
        $this->simpanSesi($u, $tok, $profil);

        return $profil;
    }

    /** Catat token + profil sesi. Satu tempat untuk dua sumber: login dan `/profile`. */
    private function simpanSesi(User $u, string $tok, array $profil): void
    {
        Cache::put("absen.tok.{$u->id}", $tok, now()->addHours(20));
        Cache::put('absen.tokowner.'.sha1($tok), (int) $u->id, now()->addHours(20));

        if (($profil['regional_facilitator'] ?? null) !== null) {
            try {
                app(\App\Services\ProfilPenarik::class)->dariLogin($profil, null);
            } catch (\Throwable $e) {
                // Gagal menyimpan bukan alasan membatalkan: pemanggilnya masih
                // memakai array yang dikembalikan ini.
                report($e);
            }
        }
    }

    /** Apakah blob profil SIMKOPDES akun ini sudah tersimpan di Hub. */
    public function adaProfil(User $u): bool
    {
        return $this->blobProfil($u) !== [];
    }

    private function sandi(User $u): string
    {
        $isi = (string) ($u->simkopdes_sandi ?? '');
        if ($isi === '') {
            return '';
        }

        try {
            return (string) Crypt::decryptString($isi);
        } catch (\Throwable) {
            // APP_KEY berubah atau nilainya rusak — jangan bikin halaman 500.
            return '';
        }
    }

    // ------------------------------------------------------------------ sisi baca

    /**
     * Tarik absensi manajer ini dari SIMKOPDES.
     *
     * Urutan HALAMAN dari SIMKOPDES: halaman 1 memuat baris TERBARU (terukur
     * 8 Okt 2026 untuk fac 50551: 08, 07, 06, 05, 03, 02, 01 Okt). Jadi berhenti
     * lebih awal itu aman — yang dilewatkan hanya baris lama, yang bisa ditarik
     * lagi lewat tombolnya.
     *
     * `$batasDetik` = stempel `microtime(true)`; saat lewat, sapuan berhenti dan
     * `berhenti` bernilai FALSE, artinya "belum tuntas" — pemanggil tidak boleh
     * mengira ini tarikan penuh.
     *
     * @return array{baris: int, halaman: int, berhenti: bool}
     */
    public function tarik(User $u, ?int $pid = null, int $maksHalaman = 40, ?float $batasDetik = null): array
    {
        $pid = $pid ?? $this->periodeId($u);
        if ($pid <= 0) {
            return ['baris' => 0, 'halaman' => 0, 'berhenti' => true];
        }

        $fac = $this->facilitatorId($u);
        if ($fac <= 0) {
            return ['baris' => 0, 'halaman' => 0, 'berhenti' => true];
        }

        $masuk = 0;
        $halaman = 0;
        $berhenti = false;

        while ($halaman < $maksHalaman) {
            // Anggaran waktu habis (dipakai tarikan di jalur login): tinggalkan
            // halaman berikutnya. `berhenti` tetap false supaya pemanggil tahu
            // sapuan ini belum tuntas.
            if ($batasDetik !== null && microtime(true) >= $batasDetik) {
                break;
            }

            $halaman++;
            $env = $this->mentah('GET',
                "/regional-facilitator-attendances/by-facilitator-id/{$fac}"
                ."?page_size=100&page={$halaman}", $this->token($u));

            $rows = $this->buka($env);
            $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
            if ($rows === []) {
                $berhenti = true;
                break;
            }

            foreach ($rows as $x) {
                // Simpan() mengembalikan 'baru' / 'tetap' / 'asing'. 'asing' =
                // baris milik facilitator atau periode lain; dilewati, TIDAK
                // menghentikan sapuan — dulu baris seperti itu mengakhiri seluruh
                // tarikan, sehingga absensi berikutnya di halaman yang sama ikut
                // hilang tanpa jejak.
                if ($this->simpan($x, $u) === 'baru') {
                    $masuk++;
                }
            }

            $totalHalaman = (int) ($env['pagination']['last_page'] ?? 1);
            if ($halaman >= $totalHalaman) {
                $berhenti = true;
                break;
            }

            // Jeda kecil: sapuan cepat pernah dijawab 404 transien oleh server.
            usleep(60000);
        }

        // Kolom teks di hub ini NOT NULL DEFAULT '', jadi "belum terisi"
        // ditanyakan lewat = '' — whereNull() tidak pernah cocok di sini.
        BarisAbsen::where('user_id', $u->id)->where('periode_id', $pid)
            ->where('ditarik_pada', '')->update(['ditarik_pada' => kini()]);

        return ['baris' => $masuk, 'halaman' => $halaman, 'berhenti' => $berhenti];
    }

    /**
     * Simpan satu baris absensi; kembalikan 'baru', 'tetap', atau 'asing'
     * (baris milik facilitator/periode lain — penanda untuk berhenti menyapu).
     *
     * `$pemilik` WAJIB pengguna yang sedang menarik, bukan hasil pencarian
     * ulang lewat `simkopdes_facilitator_id`. Kolom itu tidak unik — dua akun
     * Hub bisa memegang facilitator id yang sama (mis. akun uji yang tercemar
     * gate) — dan pencarian `User::where(...)->first()` menghasilkan pemilik
     * sembarang: absensi satu pendamping muncul di daftar hadir orang lain.
     * Yang menjamin baris ini milik siapa adalah facilitator id AKUN YANG
     * MEMANGGIL: `tarik()` sudah menyaringnya lewat endpoint by-facilitator-id.
     */
    private function simpan(array $x, ?User $pemilik = null): string
    {
        $id = (int) ($x['regional_facilitator_attendance_id'] ?? 0);
        if ($id <= 0) {
            return 'asing';
        }

        $fac = (int) ($x['regional_facilitator']['regional_facilitator_id'] ?? 0);
        $u = $pemilik ?? ($fac > 0 ? User::where('simkopdes_facilitator_id', $fac)->first() : null);
        if ($u === null || ($fac > 0 && $fac !== $this->facilitatorId($u))) {
            return 'asing';
        }

        // `check_in`/`check_out` ditampilkan APA ADANYA (jam yang sama dengan
        // yang dilihat di SIMKOPDES). Di sana medannya tidak seragam — baris
        // Bos memuat jam WIB, baris pendamping lain memuat jam UTC — jadi
        // mengonversinya berarti menebak, dan menebak salah berarti jam absen
        // pendamping salah di daftar hadir. `created_at` (ISO UTC) hanya dipakai
        // untuk menentukan TANGGAL bila check_in tidak ada.
        $masukMentah = $this->teksJam($x['check_in'] ?? '') ?: $this->tanggalDari($x['created_at'] ?? '');
        $keluarMentah = $this->teksJam($x['check_out'] ?? '');

        $baris = BarisAbsen::firstOrNew(['simkopdes_absensi_id' => $id]);
        $lama = $baris->exists;

        $baris->fill([
            'user_id' => $u->id,
            'periode_id' => (int) ($x['regional_facilitator_period_id'] ?? 0),
            'tanggal' => mb_substr($masukMentah, 0, 10),
            'koperasi_simkopdes_id' => (int) ($x['cooperative_id'] ?? 0) ?: null,
            'aktivitas' => mb_substr((string) ($x['activity'] ?? ''), 0, 200),
            'catatan' => mb_substr((string) ($x['note'] ?? ''), 0, 500),
            'masuk' => $this->jamSaja($masukMentah),
            'keluar' => $this->jamSaja($keluarMentah),
            'masuk_pada' => $masukMentah,
            'keluar_pada' => $keluarMentah,
            'lat' => $this->koordinat($x['latitude'] ?? null),
            'lng' => $this->koordinat($x['longitude'] ?? null),
            'foto_masuk_url' => mb_substr((string) ($x['start_photo'] ?? ''), 0, 2000),
            'foto_keluar_url' => mb_substr((string) ($x['end_photo'] ?? ''), 0, 2000),
            'ditarik_pada' => kini(),
        ])->save();

        // Cuplikan foto hanya untuk baris yang BARU: tautannya bertanda tangan
        // dan kedaluwarsa ~2,5 jam, jadi sekali unduh cukup — tarikan berikutnya
        // tidak mengunduh ulang baris yang sama.
        if (! $lama) {
            $this->cuplikFoto($baris);
        }

        return $lama ? 'tetap' : 'baru';
    }

    /** Teks `Y-m-d H:i:s` dari SIMKOPDES, apa adanya; '' bila bentuknya lain. */
    private function teksJam(mixed $v): string
    {
        $t = trim((string) $v);

        return preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/', $t) ? $t : '';
    }

    /** Jam `Y-m-d` dari titik waktu ISO (`created_at`). */
    private function tanggalDari(mixed $iso): string
    {
        $t = trim((string) $iso);
        if ($t === '') {
            return now('Asia/Jakarta')->toDateString();
        }

        try {
            return \Carbon\CarbonImmutable::parse($t)->setTimezone('Asia/Jakarta')->toDateString();
        } catch (\Throwable) {
            return now('Asia/Jakarta')->toDateString();
        }
    }

    private function jamSaja(string $teks): string
    {
        return strlen($teks) >= 16 ? substr($teks, 11, 5) : '';
    }

    /** Koordinat sebagai teks 6 desimal; di luar jangkauan bumi dianggap kosong. */
    private function koordinat(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $f = (float) $v;
        if (abs($f) < 0.000001) {
            return null;
        }

        return number_format($f, 6, '.', '');
    }

    // ------------------------------------------------------------------ sisi tulis

    /**
     * Kirim SATU baris presensi Hub ke SIMKOPDES.
     *
     * `$paksa` = true dipakai tombol "Kirim ke SIMKOPDES" (manusia sudah melihat
     * foto & titik lokasinya); false adalah jalur otomatis saat Check In, yang
     * tetap tunduk pada CUTOFF_JAM.
     *
     * @return array{ok: bool, pesan: string}
     */
    public function kirim(User $u, BarisAbsen $baris, bool $paksa = false): array
    {
        if ((int) $baris->user_id !== (int) $u->id) {
            return ['ok' => false, 'pesan' => 'Baris absensi ini bukan milik Anda.'];
        }
        if (! $this->bolehHantar($u)) {
            return ['ok' => false, 'pesan' => 'Absensi SIMKOPDES hanya bisa dikirim oleh manajer yang sudah menghubungkan akun SIMKOPDES-nya.'];
        }
        if (trim((string) $baris->kirim_pada) !== '') {
            return ['ok' => false, 'pesan' => 'Absensi '.(string) $baris->tanggal.' sudah pernah dikirim — tidak dikirim dua kali.'];
        }
        if (! $paksa && ! $this->dalamJamKirim()) {
            return ['ok' => false, 'pesan' => 'Sudah lewat jam '.self::CUTOFF_JAM.' WIB, jadi absensi ini tidak dikirim otomatis. '
                .'Absensi SIMKOPDES permanen dan tidak bisa dihapus, jadi mengirimnya sekarang akan menulis jam masuk yang keliru. '
                .'Kirim sengaja lewat tombolnya bila memang itu yang diinginkan.'];
        }

        $foto = trim((string) $baris->masuk_foto);
        if ($foto === '' || ! Storage::disk(self::DISK)->exists($foto)) {
            return ['ok' => false, 'pesan' => 'Foto presensi tidak ada di penyimpanan Hub, jadi tidak ada bukti yang bisa dikirim.'];
        }
        $ukuran = (int) Storage::disk(self::DISK)->size($foto);
        if ($ukuran <= 0 || $ukuran > self::MAKS_FOTO) {
            return ['ok' => false, 'pesan' => 'Foto '.number_format($ukuran / 1048576, 1).' MB — di atas batas '
                .number_format(self::MAKS_FOTO / 1048576, 0).' MB untuk absensi SIMKOPDES. Ambil ulang foto presensi.'];
        }

        $lat = trim((string) $baris->lat);
        $lng = trim((string) $baris->lng);
        if ($lat === '' || $lng === '') {
            return ['ok' => false, 'pesan' => 'Titik lokasi presensi kosong — tidak ada yang bisa dikirim sebagai bukti.'];
        }

        $judul = trim((string) $baris->aktivitas);
        if ($judul === '') {
            $judul = 'Presensi '.tanggal_id((string) $baris->tanggal, 'd M Y');
        }
        $judul = mb_substr($judul, 0, 180);

        $isi = Storage::disk(self::DISK)->get($foto);
        if ($isi === null) {
            return ['ok' => false, 'pesan' => 'Foto presensi tidak terbaca dari penyimpanan Hub.'];
        }

        try {
            $token = $this->token($u);
        } catch (\Throwable $e) {
            return ['ok' => false, 'pesan' => 'Tidak bisa masuk ke SIMKOPDES dengan akun ini: '.$e->getMessage()];
        }

        $bidang = [
            'regional_facilitator_id' => $this->facilitatorId($u),
            'regional_facilitator_period_id' => $this->periodeId($u),
            'latitude' => $lat,
            'longitude' => $lng,
            'activity' => $judul,
            // `note` WAJIB diisi; tanpa itu server menjawab 422.
            'note' => $judul,
            'cooperative_id' => $this->koperasiId($u),
        ];

        $respons = $this->unggah($token, $bidang, $foto, $isi);
        if ($respons->status() === 401) {
            try {
                $respons = $this->unggah($this->segarkanToken($u), $bidang, $foto, $isi);
            } catch (\Throwable $e) {
                return ['ok' => false, 'pesan' => 'SIMKOPDES tidak bisa dihubungi: '.$e->getMessage()];
            }
        }

        if (! $respons->successful()) {
            $pesan = (string) ($respons->json('message') ?? $respons->json('error') ?? $respons->body());
            if ($respons->status() === 413 || str_contains($pesan, 'too large')) {
                $pesan = 'SIMKOPDES menolak fotonya karena ukuran (batas server '
                    .number_format(self::BATAS_SERVER / 1048576, 0).' MB).';
            }

            return ['ok' => false, 'pesan' => 'SIMKOPDES menjawab HTTP '.$respons->status().': '.mb_substr($pesan, 0, 200)];
        }

        $idBaru = (int) ($respons->json('data.regional_facilitator_attendance_id')
            ?? $respons->json('regional_facilitator_attendance_id') ?? 0);

        // Baris yang sudah terkirim TIDAK diubah lagi: id dari server dicatat di
        // kolom terpisah, dan `kirim_pada` menutup jalur pengiriman berikutnya.
        $baris->forceFill([
            'simkopdes_absensi_id' => $idBaru ?: null,
            'ditarik_pada' => kini(),
            'kirim_pada' => kini(),
        ])->save();

        // Hapus baris HUB yang tanggalnya kembar: absensi SIMKOPDES kini menjadi
        // baris kanonik untuk tanggal itu, dan dua baris untuk satu hari di
        // daftar hadir membuat pengiriman berikutnya ambigu. HANYA baris HUB —
        // baris tarikan (sumber=SIMP) adalah catatan resmi yang barusan dikirim;
        // menghapusnya membuang cerminan absensi yang sah dari daftar hadir.
        BarisAbsen::where('user_id', $u->id)->where('tanggal', (string) $baris->tanggal)
            ->where('id', '!=', $baris->id)->where('sumber', 'HUB')->where('kirim_pada', '')->delete();

        return ['ok' => true, 'pesan' => 'Absensi '.tanggal_id((string) $baris->tanggal, 'd M Y')
            .' terkirim ke SIMKOPDES'.($idBaru ? ' (id '.$idBaru.')' : '').'.'];
    }

    private function unggah(string $token, array $bidang, string $foto, string $isi)
    {
        [$dasar, $kunci] = $this->dasar();
        $http = Http::acceptJson()->timeout(60)->connectTimeout(20)
            ->withToken($token)
            ->attach('start_photo', $isi, basename($foto), ['Content-Type' => 'image/jpeg']);

        if ($kunci !== '') {
            $http = $http->withHeaders(['X-Relay-Key' => $kunci]);
        }

        return $http->post($dasar.'/regional-facilitator-attendances', $bidang);
    }

    /**
     * Kirim CHECK OUT ke SIMKOPDES pada baris absensi yang SUDAH ada.
     *
     * Di SIMKOPDES, check-out bukan kiriman baru melainkan PEMBARUAN baris hari
     * itu: medannya `end_photo` (server menjawab 200 "Data updated successfully."
     * dan `total` TIDAK bertambah), bukan `start_photo` yang membuat baris baru.
     * Karena itu metode ini menolak jalan bila barisnya belum punya
     * `simkopdes_absensi_id` — mengirim Check Out sendirian akan melahirkan baris
     * tanpa jam masuk, dan itu lebih buruk daripada tidak mengirim sama sekali.
     *
     * Tidak ada penjagaan jam kirim di sini (beda dengan `kirim()`): yang dikirim
     * adalah kejadian yang BARU SAJA terjadi, jadi jam yang dicatat server memang
     * jam yang benar. Tanpa ini, Check Out pendamping hanya hidup di Hub dan
     * absensi resmi di SIMKOPDES berhenti di jam masuk — itulah keluhan bahwa
     * kehadiran tidak sinkron.
     */
    public function kirimKeluar(User $u, BarisAbsen $baris, \App\Models\AgriPresensi $p): array
    {
        if ((int) $baris->user_id !== (int) $u->id) {
            return ['ok' => false, 'pesan' => 'Baris absensi ini bukan milik Anda.'];
        }
        if (! $this->bolehHantar($u)) {
            return ['ok' => false, 'pesan' => 'Check Out hanya diteruskan oleh manajer yang sudah menghubungkan akun SIMKOPDES-nya.'];
        }

        $id = (int) $baris->simkopdes_absensi_id;
        if ($id <= 0) {
            return ['ok' => false, 'pesan' => 'Absensi hari ini belum ada di SIMKOPDES, jadi tidak ada baris yang bisa diisi jam keluarnya. Check In dulu di SIMKOPDES.'];
        }

        $foto = trim((string) $p->keluar_foto);
        if ($foto === '' || ! Storage::disk(self::DISK)->exists($foto)) {
            return ['ok' => false, 'pesan' => 'Foto Check Out tidak ada di penyimpanan Hub, jadi tidak ada bukti yang bisa dikirim.'];
        }
        $ukuran = (int) Storage::disk(self::DISK)->size($foto);
        if ($ukuran <= 0 || $ukuran > self::MAKS_FOTO) {
            return ['ok' => false, 'pesan' => 'Foto '.number_format($ukuran / 1048576, 1).' MB — di atas batas '
                .number_format(self::MAKS_FOTO / 1048576, 0).' MB untuk absensi SIMKOPDES.'];
        }

        // Titik Check Out; kalau perangkat tidak memberi titik, pakai titik Check
        // In baris itu supaya SIMKOPDES tetap menerima lokasi yang masuk akal.
        $lat = trim((string) $p->keluar_lat);
        $lng = trim((string) $p->keluar_lng);
        if ($lat === '' || $lng === '') {
            $lat = trim((string) $baris->lat);
            $lng = trim((string) $baris->lng);
        }
        if ($lat === '' || $lng === '') {
            return ['ok' => false, 'pesan' => 'Titik lokasi Check Out kosong — ambil ulang lokasinya.'];
        }

        $judul = trim((string) $baris->aktivitas) !== '' ? trim((string) $baris->aktivitas)
            : trim((string) $p->catatan);
        $judul = $judul !== '' ? mb_substr($judul, 0, 180) : 'Presensi '.tanggal_id((string) $baris->tanggal, 'd M Y');

        $isi = Storage::disk(self::DISK)->get($foto);
        if ($isi === null) {
            return ['ok' => false, 'pesan' => 'Foto Check Out tidak terbaca dari penyimpanan Hub.'];
        }

        try {
            $token = $this->token($u);
        } catch (\Throwable $e) {
            return ['ok' => false, 'pesan' => 'Tidak bisa masuk ke SIMKOPDES dengan akun ini: '.$e->getMessage()];
        }

        // Bentuk medan sama seperti Check In — itu yang diterima server — dengan
        // `end_photo` sebagai ganti `start_photo`.
        $bidang = [
            'regional_facilitator_id' => $this->facilitatorId($u),
            'regional_facilitator_period_id' => $this->periodeId($u),
            'latitude' => $lat,
            'longitude' => $lng,
            'activity' => $judul,
            'note' => $judul,
            'cooperative_id' => $this->koperasiId($u),
        ];

        $respons = $this->unggahKeluar($token, $bidang, $foto, $isi);
        if ($respons->status() === 401) {
            try {
                $respons = $this->unggahKeluar($this->segarkanToken($u), $bidang, $foto, $isi);
            } catch (\Throwable $e) {
                return ['ok' => false, 'pesan' => 'SIMKOPDES tidak bisa dihubungi: '.$e->getMessage()];
            }
        }

        if (! $respons->successful()) {
            $pesan = (string) ($respons->json('message') ?? $respons->json('error') ?? $respons->body());

            return ['ok' => false, 'pesan' => 'SIMKOPDES menjawab HTTP '.$respons->status().': '.mb_substr($pesan, 0, 200)];
        }

        // Cermin di Hub ikut diperbarui supaya daftar hadir langsung memperlihatkan
        // jam keluar yang resmi — tanpa menunggu tarikan berikutnya. `keluar_foto`
        // (path Hub) menandai sisi ini sudah punya bukti sendiri.
        $baris->forceFill([
            'keluar' => (string) $p->keluar,
            'keluar_pada' => (string) $p->keluar_pada,
            'keluar_foto' => $foto,
            'ditarik_pada' => kini(),
        ])->save();

        return ['ok' => true, 'pesan' => 'Check Out '.tanggal_id((string) $baris->tanggal, 'd M Y')
            .' terkirim ke SIMKOPDES (jam keluar '.(string) $p->keluar.').'];
    }

    private function unggahKeluar(string $token, array $bidang, string $foto, string $isi)
    {
        [$dasar, $kunci] = $this->dasar();
        $http = Http::acceptJson()->timeout(60)->connectTimeout(20)
            ->withToken($token)
            ->attach('end_photo', $isi, basename($foto), ['Content-Type' => 'image/jpeg']);

        if ($kunci !== '') {
            $http = $http->withHeaders(['X-Relay-Key' => $kunci]);
        }

        return $http->post($dasar.'/regional-facilitator-attendances', $bidang);
    }

    /**
     * Padanan Hub untuk satu baris presensi — dibuat tepat setelah Check In.
     *
     * Hanya untuk manajer ber-akun SIMKOPDES: bagi yang lain baris ini tidak
     * dibuat sama sekali, supaya daftar hadir KDKMP tidak terisi orang yang
     * memang tidak ada di sana.
     *
     * TIDAK mengirim apa pun ke SIMKOPDES — itu tugas `kirim()`, dan baru
     * dijalankan kalau presensinya masih dalam jam kirim.
     */
    public function pastikan(User $u, \App\Models\AgriPresensi $p): ?BarisAbsen
    {
        if (! $this->bolehHantar($u)) {
            return null;
        }

        $tanggal = (string) $p->tanggal;
        // HANYA baris milik Hub yang boleh diselaraskan. Baris hasil TARIKAN
        // (sumber=SIMP) adalah cerminan catatan resmi SIMKOPDES: menimpanya
        // dengan presensi Hub berarti jam masuk orang diganti jam check-in Hub,
        // dan baris itu lalu tampak "tertunda" sehingga bisa terkirim ulang
        // sebagai absensi kedua — di server yang permanen, tanpa endpoint hapus.
        $ada = BarisAbsen::where('user_id', $u->id)->where('tanggal', $tanggal)
            ->where('sumber', 'HUB')->first();
        if ($ada !== null) {
            return $ada;
        }

        // Sudah ada catatan resmi SIMKOPDES untuk tanggal ini: tidak ada yang
        // perlu dibuat, dan tidak ada yang boleh dikirim (akan jadi catatan
        // kembar di sana).
        if (BarisAbsen::where('user_id', $u->id)->where('tanggal', $tanggal)
            ->where('sumber', 'SIMP')->exists()) {
            return null;
        }

        return BarisAbsen::create([
            'user_id' => $u->id,
            'periode_id' => $this->periodeId($u),
            'tanggal' => $tanggal,
            'koperasi_simkopdes_id' => $this->koperasiId($u) ?: null,
            'aktivitas' => mb_substr(trim((string) $p->catatan), 0, 200),
            'masuk' => (string) $p->masuk,
            'masuk_pada' => (string) $p->masuk_pada,
            'lat' => trim((string) $p->masuk_lat) ?: null,
            'lng' => trim((string) $p->masuk_lng) ?: null,
            'masuk_foto' => (string) $p->masuk_foto,
            'sumber' => 'HUB',
            'ditarik_pada' => kini(),
        ]);
    }

    /**
     * Selaraskan baris absen KDKMP dengan satu presensi Hub (tanggal sama).
     *
     * Dipakai untuk SETIAP Check In, termasuk Check In ulang hari yang sama:
     * baris yang belum terkirim ikut diperbarui jam/foto/titiknya, sebab ia
     * masih mencerminkan data lama. Baris yang SUDAH terkirim tidak disentuh —
     * absensi SIMKOPDES permanen, jadi mengubah cerminannya hanya membuat dua
     * catatan berbeda tanpa bisa membetulkan yang di sana.
     */
    public function samakan(User $u, \App\Models\AgriPresensi $p): ?BarisAbsen
    {
        $baris = $this->pastikan($u, $p);
        if ($baris === null) {
            // `pastikan()` memulangkan null HANYA bila tanggal itu sudah punya
            // catatan resmi SIMKOPDES (atau akunnya memang tak boleh menghantar).
            return null;
        }
        if (trim((string) $baris->kirim_pada) !== '') {
            return $baris;
        }

        $baris->forceFill([
            'periode_id' => $this->periodeId($u),
            'koperasi_simkopdes_id' => $this->koperasiId($u) ?: null,
            'aktivitas' => mb_substr(trim((string) $p->catatan), 0, 200),
            'masuk' => (string) $p->masuk,
            'masuk_pada' => (string) $p->masuk_pada,
            'lat' => trim((string) $p->masuk_lat) ?: null,
            'lng' => trim((string) $p->masuk_lng) ?: null,
            'masuk_foto' => (string) $p->masuk_foto,
        ])->save();

        return $baris;
    }

    /** Manajer + terhubung + (opsional) belum lewat cutoff jam untuk pengiriman otomatis. */
    public function bolehHantar(User $u): bool
    {
        return $u->manajerKoperasi() && $this->terkonfigurasi($u);
    }

    /** Jam WIB sekarang, untuk memutuskan pengiriman otomatis boleh jalan. */
    public function dalamJamKirim(): bool
    {
        return now('Asia/Jakarta')->format('H:i') < self::CUTOFF_JAM;
    }

    // ------------------------------------------------------------------- HTTP

    /**
     * Satu panggilan HTTP mentah. Retry 401 SEKALI dengan token baru, karena
     * SIMKOPDES memakai satu sesi per akun: login dari tempat lain (aplikasi
     * ponsel manajer, skrip lain) mematikan token yang tersimpan di sini.
     */
    private function mentah(string $metode, string $path, ?string $token, array $isi = []): array
    {
        // Ritme: jangan menembak /login beruntun — lihat App\Support\JedaLogin.
        if ($path === '/login' && ! JedaLogin::tunggu()) {
            throw new \RuntimeException('SIMKOPDES membatasi login otomatis (ritme/jatah) — coba lagi nanti.');
        }

        $respons = $this->kirimHttp($metode, $path, $token, $isi);

        if ($token !== null && $respons->status() === 401) {
            $u = $this->pemilikToken($token);
            if ($u !== null) {
                $baru = $this->segarkanToken($u);
                // Token dari browser tidak bisa "disegarkan" tanpa login: kalau
                // nilainya sama, 401 berarti sesi itu memang sudah mati.
                if ($baru === $token) {
                    Cache::forget("absen.tok.{$u->id}");

                    throw new \RuntimeException('Token SIMKOPDES sudah tidak berlaku — hubungkan ulang akun dari browser di halaman Absen KDKMP.');
                }
                $respons = $this->kirimHttp($metode, $path, $baru, $isi);
            }
        }

        if (! $respons->successful()) {
            throw new \RuntimeException("SIMKOPDES {$path} menjawab HTTP {$respons->status()}.");
        }

        return (array) $respons->json();
    }

    private function pemilikToken(string $token): ?User
    {
        $id = (int) Cache::get('absen.tokowner.'.sha1($token), 0);

        return $id > 0 ? User::find($id) : null;
    }

    private function kirimHttp(string $metode, string $path, ?string $token, array $isi = [])
    {
        $http = Http::acceptJson()->withUserAgent(JedaLogin::UA)->timeout(30)->connectTimeout(20);
        if ($token !== null) {
            $http = $http->withToken($token);
        }

        [$dasar, $kunci] = $this->dasar();
        if ($kunci !== '') {
            $http = $http->withHeaders(['X-Relay-Key' => $kunci]);
        }

        if ($metode === 'POST') {
            return $http->post($dasar.$path, $isi);
        }

        // Query yang menempel di `$path` WAJIB dipindahkan ke argumen kedua.
        // Terukur: `Http::get($url, [])` MEMBUANG query yang ada di dalam `$url`
        // (jadi `?page_size=100&page=2` hilang), sementara `get($url, ['x'=>1])`
        // justru menggantinya. Akibatnya setiap halaman tarikan selama ini
        // meminta halaman 1 lagi — diam-diam, karena urutan halaman tidak
        // menimbulkan error. Pendamping dengan lebih dari 100 absensi hanya
        // tercermin sebagian, dan sisanya tak akan pernah masuk.
        $tanya = [];
        if (($pos = strpos($path, '?')) !== false) {
            parse_str(substr($path, $pos + 1), $tanya);
            $path = substr($path, 0, $pos);
        }

        return $http->get($dasar.$path, $isi + $tanya);
    }

    /**
     * Dasar URL + kunci relay untuk SEMUA panggilan SIMKOPDES dari kelas ini.
     *
     * Relay Cloudflare jalur UTAMA, bukan cadangan: IP server ini tidak boleh
     * tercatat di log SIMKOPDES (profil, absensi, unggah foto — bukan hanya
     * login). ponytail: relay mati = panggilan gagal (sengaja, jangan bocor ke
     * IP sendiri); pasang relay kedua kalau ketersediaan jadi masalah nyata.
     *
     * @return array{0: string, 1: string} [dasar, kunci relay]
     */
    private function dasar(): array
    {
        $relay = (string) config('services.simkopdes.relay');

        return $relay !== ''
            ? [$relay, (string) config('services.simkopdes.relay_key')]
            : [self::URL, ''];
    }

    /** Dekripsi `data` (AES-256-CBC) → array; teruskan bila bukan teks terenkripsi. */
    private function buka(array $amplop): mixed
    {
        $data = $amplop['data'] ?? null;
        if (! is_string($data) || $data === '') {
            return $data;
        }

        $teks = openssl_decrypt(base64_decode($data), 'aes-256-cbc',
            hash('sha256', self::PROTO_KEY, true), OPENSSL_RAW_DATA, self::PROTO_IV);
        if ($teks === false) {
            throw new \RuntimeException('Payload SIMKOPDES gagal didekripsi.');
        }

        return json_decode($teks, true, 512, JSON_THROW_ON_ERROR);
    }

    /** Rakit ulang peta token → pemilik (dipakai saat cache baru saja dingin). */
    public function daftarkanToken(User $u): void
    {
        try {
            $this->token($u);
        } catch (\Throwable) {
            // belum terkonfigurasi — tidak apa-apa
        }
    }

    /**
     * Cuplik foto absensi ke disk Hub.
     *
     * Tautan foto SIMKOPDES adalah signed URL Google Storage yang kedaluwarsa
     * dalam ~2,5 jam (terukur: Expires - sekarang = 9.211 dtk). Menyimpan
     * tautannya saja berarti besok daftar hadir berisi gambar rusak. Jadi
     * cuplikannya disalin saat tarikan — sekali unduh per baris, dan dilewati
     * kalau berkasnya sudah ada.
     *
     * @return array{masuk: string, keluar: string}
     */
    public function cuplikFoto(BarisAbsen $baris, bool $paksa = false): array
    {
        $hasil = ['masuk' => '', 'keluar' => ''];

        foreach (['masuk' => 'foto_masuk_url', 'keluar' => 'foto_keluar_url'] as $sisi => $kolUrl) {
            $kolBerkas = $sisi.'_foto';
            $lama = trim((string) $baris->{$kolBerkas});

            if ($lama !== '' && ! $paksa) {
                $hasil[$sisi] = Storage::disk(self::DISK)->exists($lama) ? $lama : '';
                if ($hasil[$sisi] !== '') {
                    continue;
                }
            }

            $url = trim((string) $baris->{$kolUrl});
            if ($url === '') {
                continue;
            }

            try {
                $r = Http::timeout(30)->connectTimeout(15)->get($url);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            // Signed URL yang sudah kedaluwarsa dijawab 403/400 oleh Google.
            if (! $r->successful()) {
                continue;
            }

            $biner = $r->body();
            if ($biner === '' || strlen($biner) > 12 * 1024 * 1024) {
                continue;
            }
            $ukuran = @getimagesizefromstring($biner);
            if ($ukuran === false) {
                continue;
            }

            // Jenis berkas ditentukan dari ISI-nya, bukan dari nama: SIMKOPDES
            // menyimpan `start_photo` apa adanya, dan sebagian pendamping
            // mengunggah PNG (wajarnya disajikan sebagai .png).
            $mime = strtolower((string) ($ukuran['mime'] ?? ''));
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }
            $ext = match ($mime) {
                'image/png' => '.png',
                'image/webp' => '.webp',
                default => '.jpg',
            };

            // Perbandingan 3:4 diseragamkan lewat TAMPILAN (CSS `.foto-presensi`
            // memakai object-fit: cover di kotak 3:4), bukan dengan menyunting
            // header JPEG: menyunting header hanya mengubah angka dimensi tanpa
            // memotong satu piksel pun, sehingga citranya justru teregang — dan
            // berkasnya tetap membawa dimensi yang berbeda dari isinya.
            $nama = 'agri/absensi/'.(string) $baris->tanggal.'-'.$sisi.'-'
                .substr(sha1((string) $baris->id.$sisi), 0, 12).$ext;
            if (Storage::disk(self::DISK)->put($nama, $biner)) {
                $hasil[$sisi] = $nama;
            }
        }

        $isi = array_filter([
            'masuk_foto' => $hasil['masuk'],
            'keluar_foto' => $hasil['keluar'],
        ], fn ($v) => $v !== '');

        if ($isi !== []) {
            $baris->forceFill($isi)->save();
        }

        return $hasil;
    }

    /** Ringkasan untuk lencana di halaman Presensi. */
    public function ringkas(User $u): array
    {
        $q = BarisAbsen::where('user_id', $u->id);

        return [
            'total' => (clone $q)->count(),
            'terkirim' => (clone $q)->where('sumber', 'HUB')->where('kirim_pada', '!=', '')->count(),
            // `kirim_pada` NOT NULL DEFAULT '' di SQLite, jadi `whereNull()` di
            // sini tak pernah cocok. Dan sumbernya WAJIB dibatasi 'HUB': baris
            // yang DITARIK dari SIMKOPDES juga ber-`kirim_pada` kosong (kita
            // tidak pernah "mengirimnya"), sehingga tanpa batas ini lencana
            // berbunyi "7 menunggu dikirim" hanya karena ada 7 baris tarikan —
            // padahal tidak ada satu pun yang perlu dikirim.
            'tertunda' => (clone $q)->where('sumber', 'HUB')->where('kirim_pada', '')->count(),
            'tertarik' => (clone $q)->where('sumber', 'SIMP')->count(),
            'terakhir' => (clone $q)->orderByDesc('tanggal')->value('tanggal'),
            'terhubung' => $this->terkonfigurasi($u),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Klien AgriConnect HRIS (Starconnect) — layanan mandiri pegawai.
 *
 * Semua alur di bawah dinyatakan dari BUNDEL APK 1.0.2 + uji nyata dengan akun
 * Manajer KDKMP (2026-10-14), bukan dari dokumentasi:
 *
 *   1. POST {MASTER}/api/auth          {"username"}                  -> {isValid, isNeedOtp}
 *   2. POST {MASTER}/api/auth/login    {"username","password",
 *                                       "grantType":"password"}      -> access_token (JWT)
 *   3. GET  {instance_api}/api/user/profile                         -> authoritiesToken
 *
 * `instance_api` dibaca dari klaim JWT — itu base data sebenarnya. Base data
 * BUKAN alamat loginnya: di master jawaban semua endpoint data adalah 403/404.
 *
 * Rezim `GIT` (huruf besar) di `keycloakLoginUrl` APK sudah MATI (404); yang
 * hidup hanya `git` kecil. Karena itu login di sini lewat `/api/auth/login`,
 * bukan Keycloak — persis yang dilakukan aplikasinya.
 *
 * SATU SESI PER AKUN. Token AgriConnect terikat satu sesi: login dari tempat
 * lain (aplikasi Android pegawai itu sendiri, atau skrip) mematikan token yang
 * kita cache. Karena itu SEMUA panggilan lewat `minta()`, yang mengulang SEKALI
 * saat dijawab 401 — tanpa itu halaman gagal acak dan terbaca "integrasi rusak".
 *
 * Kredensial ada di .env (`AC_*`); token tersimpan di cache (bukan tabel) supaya
 * tidak ada sandi/token yang ikut ter-backup bersama database.
 */
class Agriconnect
{
    /** Batas umur cache token; token aslinya ~30 hari, kita perpendek. */
    private const UMUR_TOKEN = 8; // jam

    /** Memo baris CICO per user id, hanya selama satu permintaan. */
    private array $cicoCache = [];

    // ------------------------------------------------------------------ setup

    public function terkonfigurasi(): bool
    {
        return (string) config('services.agriconnect.username') !== ''
            && (string) config('services.agriconnect.password') !== '';
    }

    private function master(): string
    {
        return rtrim((string) config('services.agriconnect.master'), '/');
    }

    /**
     * Base data satu akun Hub. Diambil dari sesi tersimpan (hasil login akun itu
     * sendiri); tanpa itu integrasi tidak bisa dilanjutkan — dan itu memang
     * benar: base data hanya diketahui setelah pemiliknya login.
     */
    public function sesi(User $u): ?array
    {
        $mentah = $u->agri_sesi ?? null;
        if (! is_string($mentah) || $mentah === '') {
            return null;
        }

        try {
            $s = json_decode(Crypt::decryptString($mentah), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($s) && ! empty($s['access_token']) ? $s : null;
    }

    public function tersambung(User $u): bool
    {
        return $this->sesi($u) !== null;
    }

    /**
     * Login atas nama satu pengguna Hub dan simpan sesinya di baris user itu.
     *
     * `sandi` TIDAK disimpan — hanya token. Halaman Hub menanyakan sandi
     * AgriConnect sekali saat pengguna menekan "Hubungkan", lalu tokennya
     * dipakai sampai kedaluwarsa.
     *
     * @return array{pesan:string, profil:array<string,mixed>}
     */
    public function hubungkan(User $u, string $username, string $sandi, ?string $otp = null): array
    {
        $cek = $this->mentah('POST', $this->master().'/api/auth', null, ['username' => $username]);
        if (($cek['isValid'] ?? false) !== true) {
            throw new \RuntimeException('Akun AgriConnect tidak dikenali (nama pengguna salah).');
        }
        if (($cek['isNeedOtp'] ?? false) === true && ($otp === null || $otp === '')) {
            throw new \RuntimeException('Akun ini memerlukan kode OTP, dan Hub tidak meminta OTP. Masuk sekali lewat aplikasi AgriConnect, lalu hubungkan di sini.');
        }

        $isi = ['grantType' => 'password', 'username' => $username, 'password' => $sandi];
        if ($otp !== null && $otp !== '') {
            $isi['otp'] = $otp;
        }

        $r = $this->mentah('POST', $this->master().'/api/auth/login', null, $isi);
        $token = (string) ($r['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('AgriConnect menolak login (cek nama pengguna & sandi).');
        }

        $klaim = $this->klaim($token);
        $base = (string) (($klaim['instance_api'][0] ?? $klaim['instance_api'] ?? '') ?: '');
        if ($base === '') {
            throw new \RuntimeException('Token tidak memuat base data (instance_api).');
        }

        $sesi = [
            'tenant_id' => (string) (is_array($klaim['tenant_id'] ?? null)
                ? ($klaim['tenant_id'][0] ?? '') : ($klaim['tenant_id'] ?? '')),
            'url_api' => rtrim($base, '/'),
            'access_token' => $token,
            'refresh_token' => (string) ($r['refresh_token'] ?? ''),
            'username' => $username,
            'pegawai_id' => (string) ($klaim['actual_emp_id'] ?? $klaim['emp_id'] ?? ''),
        ];

        // authoritiesToken diterbitkan endpoint profil, bukan oleh login.
        // Header tenant WAJIB ikut — tanpa `X-TenantID` dijawab
        // "400 No Tenant Supplied".
        $profil = $this->mentah('GET', $sesi['url_api'].'/api/user/profile', $token,
            null, $this->header($sesi));
        $sesi['authorities_token'] = (string) ($profil['authoritiesToken'] ?? '');

        $u->forceFill([
            'agri_sesi' => Crypt::encryptString(json_encode($sesi)),
            'agri_username' => $username,
            'agri_tersambung_pada' => now(),
        ])->save();

        Cache::forget($this->kunciToken($u->id));
        $this->lupakan($u);

        return [
            'pesan' => trim(($profil['fullName'] ?? $username).' — '.($profil['positionName'] ?? '')),
            'profil' => $profil,
        ];
    }

    public function lepas(User $u): void
    {
        $u->forceFill([
            'agri_sesi' => null, 'agri_username' => null, 'agri_tersambung_pada' => null,
        ])->save();
        Cache::forget($this->kunciToken($u->id));
        $this->lupakan($u);
    }

    // --------------------------------------------------------------- panggilan

    /**
     * GET satu jalur data. `$jalur` ditulis tanpa base (`/api/user/goal`).
     *
     * @return array<string,mixed>|array<int,mixed>
     */
    public function ambil(User $u, string $jalur, array $kueri = []): mixed
    {
        $sesi = $this->sesi($u);
        if ($sesi === null) {
            throw new \RuntimeException('Akun ini belum tersambung ke AgriConnect.');
        }

        // Satu navigasi halaman AgriConnect memanggil server 3–5 kali (time-sheet
        // 6 bulan sendirian ~0,9 dtk; lookup Tujuan ~0,25 dtk). Simpan jawaban
        // GET 60 detik per pengguna — setiap TULISAN menaikkan versi, jadi
        // simpanan lama langsung tidak terpakai.
        // ponytail: TTL 60 dtk. Data yang diubah dari APK (bukan dari Hub) baru
        // terlihat paling lambat 1 menit kemudian; turunkan bila itu mengganggu.
        $kunci = 'ac.get.'.$u->id.'.'.sha1($jalur.'?'.http_build_query($kueri)).'.'.$this->versi($u);
        $simpan = Cache::get($kunci);
        if ($simpan !== null) {
            return $simpan;
        }

        $jawab = $this->minta($u, 'GET', $jalur, $kueri);
        if ($this->kodeTerakhir === 200) {
            Cache::put($kunci, $jawab, 60);
        }

        return $jawab;
    }

    /** Versi simpanan GET milik satu pengguna; naik setiap ada tulisan. */
    private function versi(User $u): int
    {
        return (int) Cache::get('ac.ver.'.$u->id, 0);
    }

    /** Buang semua simpanan GET + CICO pengguna ini setelah menulis/menghubungkan. */
    private function lupakan(User $u): void
    {
        Cache::put('ac.ver.'.$u->id, $this->versi($u) + 1, 86400);
        Cache::forget('ac.cico.'.$u->id);
    }

    /**
     * POST JSON ke jalur AgriConnect memakai sesi MILIK pengguna.
     *
     * Hanya untuk endpoint yang memang menulis data milik sendiri (mis.
     * `/api/user/my-profile/sosmed`). Endpoint pengajuan (`.../v1/request`)
     * TIDAK dipakai di sini karena butuh multipart + base64 payload — lihat
     * `absen()` untuk pola itu.
     *
     * @return array<string,mixed>
     */
    public function kirim(User $u, string $jalur, array $isi): array
    {
        $sesi = $this->sesi($u);
        if ($sesi === null) {
            throw new \RuntimeException('Akun ini belum tersambung ke AgriConnect.');
        }

        $jawab = $this->mentah('POST', $sesi['url_api'].$jalur, (string) ($sesi['access_token'] ?? ''),
            $isi, $this->header($sesi));

        // Tulisan dari Hub mengubah data yang mungkin sudah tersimpan di cache GET.
        $this->lupakan($u);

        return is_array($jawab) ? $jawab : [];
    }

    /** Jalur yang aman dipanggil walau jawabannya kosong — untuk dasbor. */
    public function coba(User $u, string $jalur, array $kueri = [], mixed $bawaan = []): mixed
    {
        try {
            return $this->ambil($u, $jalur, $kueri);
        } catch (\Throwable) {
            return $bawaan;
        }
    }

    /**
     * Semua panggilan lewat sini. Satu-satunya tempat yang tahu soal base data,
     * header, dan token kedaluwarsa.
     */
    private function minta(User $u, string $metode, string $jalur, array $kueri = [], int $percobaan = 0): mixed
    {
        $sesi = $this->sesi($u);
        if ($sesi === null) {
            throw new \RuntimeException('Sesi AgriConnect tidak ada.');
        }

        $token = (string) ($sesi['access_token'] ?? '');
        $jawab = $this->mentah($metode, $sesi['url_api'].$jalur, $token, $kueri === [] ? null : $kueri,
            $this->header($sesi), $kueri, metodeKueri: $metode === 'GET');

        // Token mati karena login lain (aplikasi pegawai / skrip). Ulang SEKALI
        // dengan token segar; kalau masih 401, baru menyerah.
        if ($percobaan === 0 && $this->kodeTerakhir === 401) {
            $this->segarkan($u);

            return $this->minta($u, $metode, $jalur, $kueri, 1);
        }

        return $jawab;
    }

    /** Login ulang memakai kredensial .env bila nama penggunanya sama. */
    private function segarkan(User $u): void
    {
        $sesi = $this->sesi($u);
        $user = (string) ($sesi['username'] ?? '');
        $sandi = (string) config('services.agriconnect.password');

        if ($user === '' || $sandi === '' || $user !== (string) config('services.agriconnect.username')) {
            throw new \RuntimeException('Sesi AgriConnect kedaluwarsa — hubungkan ulang dari halaman AgriConnect.');
        }

        $this->hubungkan($u, $user, $sandi);
    }

    private function header(array $sesi): array
    {
        $h = ['Authorization' => 'Bearer '.($sesi['access_token'] ?? '')];
        if (($sesi['tenant_id'] ?? '') !== '') {
            $h['X-TenantID'] = $sesi['tenant_id'];
        }
        if (($sesi['authorities_token'] ?? '') !== '') {
            $h['AuthorizationToken'] = $sesi['authorities_token'];
        }

        return $h;
    }

    /** HTTP satu kali; menyimpan kode status supaya pemanggil bisa memutuskan ulang. */
    public int $kodeTerakhir = 0;

    private function mentah(string $metode, string $url, ?string $token = null, ?array $isi = null,
        array $header = [], array $kueri = [], bool $metodeKueri = false): mixed
    {
        $h = $header + [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            // Aplikasi aslinya okhttp; sebagian endpoint memeriksa User-Agent.
            'User-Agent' => (string) config('services.agriconnect.ua', 'okhttp/4.12.0'),
        ];
        if ($token !== null && ! isset($h['Authorization'])) {
            $h['Authorization'] = 'Bearer '.$token;
        }

        $permintaan = Http::withHeaders($h)
            ->timeout((int) config('services.agriconnect.timeout', 30))
            ->withoutVerifying();

        $jawab = $metodeKueri
            ? $permintaan->get($url, $kueri)
            : $permintaan->{strtolower($metode)}($url, $isi ?? []);

        $this->kodeTerakhir = $jawab->status();
        $data = $jawab->json();

        if ($this->kodeTerakhir >= 400 && $this->kodeTerakhir !== 401) {
            $pesan = is_array($data) ? (string) ($data['message'] ?? '') : '';
            throw new \RuntimeException('AgriConnect menjawab HTTP '.$this->kodeTerakhir
                .($pesan !== '' ? ' — '.$pesan : ''));
        }
        if ($this->kodeTerakhir === 401 && $token === null) {
            throw new \RuntimeException('AgriConnect menolak kredensial (HTTP 401).');
        }

        return is_array($data) ? $data : [];
    }

    // ------------------------------------------------------------------ absen

    /**
     * Absen Masuk / Absen Pulang AgriConnect (Check In / Check Out) dari Hub.
     *
     * Alur ini BUKAN karangan: sama persis dengan yang dikirim APK 1.0.2 —
     * `POST /api/user/self-service/check-in-out/v1/request`, multipart dengan
     * `file` (foto) + `checkinout` = base64(JSON payload). Sudah diuji nyata
     * 2026-10-21: `{"message":"Your submit has been recorded.","error":false,
     * "id":"652790/ATT/X/2026"}`.
     *
     * `outOfOffice=false` = absen DI KANTOR. Titik lokasi TIDAK diambil dari
     * peramban: peramban bisa memberi titik apa saja (dan desktop sering menolak
     * izin), sedangkan AgriConnect menilai konsistensi titik absen. Titik diambil
     * dari baris CICO terakhir yang benar-benar berkoordinat — lihat `titik()`.
     *
     * @return array{id:string,pesan:string,titik:array{0:string,1:string}}
     */
    public function absen(User $u, string $tipe, string $foto, string $remark, ?array $titik = null, int $percobaan = 0): array
    {
        $tipe = strtoupper($tipe);
        if (! in_array($tipe, ['CHECKIN', 'CHECKOUT'], true)) {
            throw new \RuntimeException('Jenis absen tidak dikenal: '.$tipe.'.');
        }
        $sesi = $this->sesi($u);
        if ($sesi === null) {
            throw new \RuntimeException('Akun ini belum tersambung ke AgriConnect.');
        }

        // GPS perangkat bila dikirim (sudah divalidasi controller); kalau tidak,
        // titik absen sah terakhir supaya tidak berpindah-pindah.
        [$lat, $lng] = $titik ?? $this->titik($u);

        $data = [
            'actualLatEnc' => base64_encode($lat),
            'actualLngEnc' => base64_encode($lng),
            'outOfOffice' => false,
            'purpose' => 'DR',
            'remark' => $remark,
            'type' => $tipe,
            'scApps' => 'AgriConnect',
            'scVersion' => '1.0.2',
            'deviceId' => 'hub-web-'.$u->id,
            'deviceName' => 'Agrinas Hub',
            'deviceModel' => 'WSL2',
            'devicePlatform' => 'android',
            'deviceOs' => 'Android',
            'deviceOsVersion' => '13',
            'deviceAndroidSdkVersion' => '33',
            'deviceManufacturer' => 'WSL',
            'deviceVirtual' => false,
            'deviceWebviewVersion' => '120.0',
        ];

        $jawab = Http::withHeaders($this->header($sesi) + [
            'Accept' => 'application/json',
            'User-Agent' => (string) config('services.agriconnect.ua', 'okhttp/4.12.0'),
        ])
            ->timeout((int) config('services.agriconnect.timeout', 30))
            ->withoutVerifying()
            ->attach('file', $foto, 'absen.jpg')
            ->post($sesi['url_api'].'/api/user/self-service/check-in-out/v1/request', [
                'checkinout' => base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES)),
            ]);

        $kode = $jawab->status();
        $this->kodeTerakhir = $kode;

        // Token mati karena login lain (aplikasi pegawai). Ulang SEKALI.
        if ($kode === 401 && $percobaan === 0) {
            $this->segarkan($u);

            return $this->absen($u, $tipe, $foto, $remark, $titik, 1);
        }

        $isi = $jawab->json();
        if ($kode !== 200 || ! is_array($isi) || ($isi['error'] ?? true) !== false) {
            $pesan = is_array($isi) ? (string) ($isi['message'] ?? '') : '';
            throw new \RuntimeException('AgriConnect menolak absen (HTTP '.$kode
                .($pesan !== '' ? ' — '.$pesan : '').').');
        }

        // Baris baru sudah ada di server: buang cache CICO + simpanan GET supaya
        // permintaan berikutnya (halaman dimuat ulang) membaca keadaan yang benar.
        $this->lupakan($u);
        $this->cicoCache = [];

        return [
            'id' => (string) ($isi['id'] ?? ''),
            'pesan' => (string) ($isi['message'] ?? 'Absen tercatat.'),
            'titik' => [$lat, $lng],
        ];
    }

    /**
     * Report CheckInOut (backdate) — menu yang SAMA dengan APK 1.0.2, jadi
     * kirimannya masuk ke server yang sama dengan absen biasa:
     * `POST /api/user/self-service/check-in-out/backdate/v1/request`.
     *
     * Dipakai orang yang tidak bisa absen pada harinya (belum punya akses,
     * belum bisa login). Bentuk body disalin dari `onSubmit()` komponen
     * `report-checkinout` bundel APK: multipart dengan `file` (satu bagian per
     * lampiran, maks 5) + `checkinout` = base64(JSON). Beda dari `absen()`:
     * TIDAK ada koordinat, dan ada `absenceDate` = tanggal+jam yang dilaporkan.
     *
     * @param  array<int,array{isi:string,nama:string}>  $lampiran
     * @return array{id:string,pesan:string}
     */
    public function lapor(User $u, string $tipe, string $waktu, string $purpose, string $remark,
        array $lampiran = [], int $percobaan = 0): array
    {
        $tipe = strtoupper($tipe);
        if (! in_array($tipe, ['CHECKIN', 'CHECKOUT'], true)) {
            throw new \RuntimeException('Jenis laporan tidak dikenal: '.$tipe.'.');
        }
        $sesi = $this->sesi($u);
        if ($sesi === null) {
            throw new \RuntimeException('Akun ini belum tersambung ke AgriConnect.');
        }

        $data = [
            'outOfOffice' => false,
            'purpose' => $purpose,
            'remark' => $remark,
            'scApps' => 'AgriConnect',
            'scVersion' => '1.0.2',
            'type' => $tipe,
            'absenceDate' => $waktu,
            'deviceId' => 'hub-web-'.$u->id,
            'deviceName' => 'Agrinas Hub',
            'deviceModel' => 'WSL2',
            'devicePlatform' => 'android',
            'deviceOs' => 'Android',
            'deviceOsVersion' => '13',
            'deviceAndroidSdkVersion' => '33',
            'deviceManufacturer' => 'WSL',
            'deviceVirtual' => false,
            'deviceWebviewVersion' => '120.0',
        ];

        $permintaan = Http::withHeaders($this->header($sesi) + [
            'Accept' => 'application/json',
            'User-Agent' => (string) config('services.agriconnect.ua', 'okhttp/4.12.0'),
        ])
            ->timeout((int) config('services.agriconnect.timeout', 30))
            ->withoutVerifying();

        foreach ($lampiran as $l) {
            $permintaan = $permintaan->attach('file', $l['isi'], $l['nama']);
        }

        $jawab = $permintaan->post($sesi['url_api'].'/api/user/self-service/check-in-out/backdate/v1/request', [
            'checkinout' => base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES)),
        ]);

        $kode = $jawab->status();
        $this->kodeTerakhir = $kode;

        if ($kode === 401 && $percobaan === 0) {
            $this->segarkan($u);

            return $this->lapor($u, $tipe, $waktu, $purpose, $remark, $lampiran, 1);
        }

        $isi = $jawab->json();
        if ($kode !== 200 || ! is_array($isi) || ($isi['error'] ?? true) !== false) {
            $pesan = is_array($isi) ? (string) ($isi['message'] ?? '') : '';
            throw new \RuntimeException('AgriConnect menolak laporan (HTTP '.$kode
                .($pesan !== '' ? ' — '.$pesan : '').').');
        }

        $this->lupakan($u);
        $this->cicoCache = [];

        return [
            'id' => (string) ($isi['id'] ?? ''),
            'pesan' => (string) ($isi['message'] ?? 'Laporan tercatat.'),
        ];
    }

    /**
     * Pilihan "Tujuan" untuk Report CheckInOut — daftar RESMI dari server
     * (global key `TM_CHECKINOUT_PURPOSES`, jalur yang dipakai APK), bukan
     * daftar karangan. Bentuk balasan: [{name:"Daily Routine", value:"DR"}, …].
     *
     * @return array<string,string> kode => label
     */
    public function tujuan(User $u): array
    {
        $isi = $this->coba($u, '/api/lookup/global-key', ['name' => 'TM_CHECKINOUT_PURPOSES'], []);
        $hasil = [];
        foreach (is_array($isi) ? $isi : [] as $t) {
            $kode = is_array($t) ? (string) ($t['value'] ?? '') : '';
            if ($kode !== '') {
                $hasil[$kode] = (string) ($t['name'] ?? $kode);
            }
        }

        // Cadangan = daftar yang dibaca dari server 25 Okt 2026, dipakai hanya
        // kalau lookup-nya sedang tidak menjawab — supaya formulir tidak kosong.
        return $hasil !== [] ? $hasil : self::TUJUAN_CADANGAN;
    }

    private const TUJUAN_CADANGAN = [
        'DR' => 'Daily Routine',
        'MTG' => 'Meeting di luar kantor',
        'PDD' => 'Perjalanan Dinas Dalam Kota',
        'PDL' => 'Perjalanan Dinas Luar Kota',
        'SWD' => 'Seminar / Workshop Dalam Kota',
        'SWL' => 'Seminar / Workshop Luar Kota',
        'TD' => 'Training Dalam Kota',
        'TL' => 'Training Luar Kota',
        'WFH' => 'Work From Home',
    ];

    /**
     * Baris Check In / Check Out milik akun ini (Approved + In Progress).
     *
     * Baris LAMA (10–14 Okt) ada yang berkoordinat `null` — itu yang dulu dikirim
     * sebagai "None" dan dijawab HTTP 500, jadi di sini koordinat disaring.
     *
     * @return array<int,array<string,mixed>>
     */
    public function cico(User $u): array
    {
        // Satu halaman bisa memanggil ini dua kali (titik + keadaan hari ini).
        // Memo per-USER (bukan satu slot) supaya tidak tertukar antar akun.
        if (isset($this->cicoCache[$u->id])) {
            return $this->cicoCache[$u->id];
        }

        // Di halaman absensi baris CICO dipakai tiga kali (keadaan hari ini, titik,
        // penjaga ganda) dan diulang lagi di permintaan berikutnya saat orang
        // langsung menekan tombol absen. Dua GET berurutan ke AgriConnect itu
        // detik-detik menunggu di jam sibuk, jadi hasilnya disimpan 60 detik.
        // Setelah absen BERHASIL cache dibuang (lihat `absen()`) supaya baris
        // baru langsung terbaca; jawaban kosong TIDAK disimpan, supaya gangguan
        // sesaat tidak membuat halaman menampilkan "belum absen" selama semenit.
        $keluar = Cache::remember('ac.cico.'.$u->id, 60, function () use ($u) {
            $hasil = [];
            foreach (['Approved', 'In Progress'] as $status) {
                $r = $this->coba($u, '/api/user/self-service/check-in-out/status/my-request', [
                    'status' => $status, 'orderBy' => 'DESC',
                ], []);
                if (isset($r['content']) && is_array($r['content'])) {
                    $r = $r['content'];
                }
                foreach (is_array($r) ? $r : [] as $b) {
                    if (is_array($b)) {
                        $hasil[] = $b;
                    }
                }
            }

            return $hasil;
        });

        if ($keluar === []) {
            Cache::forget('ac.cico.'.$u->id);
        }

        return $this->cicoCache[$u->id] = $keluar;
    }

    /**
     * Titik absen: koordinat dari baris CICO terakhir yang PUNYA koordinat.
     *
     * @return array{0:string,1:string}
     */
    public function titik(User $u): array
    {
        $baris = $this->cico($u);
        usort($baris, fn ($a, $b) => (int) ($b['actualTimeMilliSecond'] ?? 0) <=> (int) ($a['actualTimeMilliSecond'] ?? 0));
        foreach ($baris as $b) {
            $lat = trim((string) ($b['actualLat'] ?? ''));
            $lng = trim((string) ($b['actualLng'] ?? ''));
            if ($lat !== '' && $lng !== '' && $lat !== '0' && $lng !== '0') {
                return [$lat, $lng];
            }
        }

        // Titik KDKMP yang sudah dipakai absen sah terakhir (20 Okt 2026).
        return ['-6.110524', '107.064212'];
    }

    /**
     * Keadaan absen HARI INI (zona Asia/Jakarta).
     *
     * @return array{masuk:?array<string,mixed>,pulang:?array<string,mixed>}
     */
    public function hariIni(User $u): array
    {
        $hari = now('Asia/Jakarta')->toDateString();
        $hasil = ['masuk' => null, 'pulang' => null];

        foreach ($this->cico($u) as $b) {
            $ms = (int) ($b['actualTimeMilliSecond'] ?? 0);
            if ($ms === 0) {
                continue;
            }
            $tgl = \Illuminate\Support\Carbon::createFromTimestampMs($ms, 'UTC')
                ->setTimezone('Asia/Jakarta')->toDateString();
            if ($tgl !== $hari) {
                continue;
            }
            $jenis = strtoupper((string) ($b['type'] ?? ''));
            if ($jenis === 'CHECKIN' && $hasil['masuk'] === null) {
                $hasil['masuk'] = $b;
            }
            if ($jenis === 'CHECKOUT' && $hasil['pulang'] === null) {
                $hasil['pulang'] = $b;
            }
        }

        return $hasil;
    }

    // ------------------------------------------------------------------ klaim

    /** Isi klaim JWT tanpa memverifikasi tanda tangan — kita hanya membacanya. */
    public function klaim(string $jwt): array
    {
        $bagian = explode('.', $jwt);
        if (count($bagian) < 2) {
            return [];
        }
        $isi = strtr($bagian[1], '-_', '+/');
        $isi .= str_repeat('=', (4 - strlen($isi) % 4) % 4);
        $json = base64_decode($isi, true);

        return is_string($json) ? (array) json_decode($json, true) : [];
    }

    private function kunciToken(int $userId): string
    {
        return 'agriconnect.sesi.'.$userId;
    }
}

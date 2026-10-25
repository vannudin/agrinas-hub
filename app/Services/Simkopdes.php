<?php

namespace App\Services;

use App\Support\JedaLogin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Klien tipis untuk api.simkopdes.go.id — akun pendamping KDKMP.
 *
 * Alur yang dipakai (semua sudah diverifikasi 2026-10-03 dengan akun nyata):
 *   login → JWT (~1 hari)  →  regional-facilitators/detail/{id}  →  period id
 *   →  regional-facilitator-cooperatives/by-period-id/{pid}     →  daftar koperasi binaan
 *   →  district/{id} | province/{id} | village/{id}             →  kode wilayah resmi
 *
 * `data` pada hampir semua respons terenkripsi AES-256-CBC dengan kunci & IV
 * tetap (ditanam di bundle frontend SIMKOPDES) — itu protokol mereka, bukan
 * rahasia kita. Kredensial akun tetap di .env.
 *
 * ponytail: kunci/IV protokol ditanam di kode, bukan di config — memindahkannya
 * hanya menyamarkan fakta bahwa nilainya publik.
 */
class Simkopdes
{
    /** Kunci/IV protokol payload SIMKOPDES (AES-256-CBC, dari bundle frontend). */
    private const PROTO_KEY = 'KUNCI_DIHAPUS';
    private const PROTO_IV = 'IV_DIHAPUS';

    /**
     * Jeda setelah SIMKOPDES menolak /auth/login dengan 403.
     *
     * Tanpa jeda ini blokirnya TIDAK PERNAH reda: setiap percobaan masuk pengguna
     * (termasuk yang diulang terus karena pesannya "coba lagi") adalah satu
     * bidikan baru ke IP server yang sedang diblokir, jadi hitungan mereka terus
     * naik. Diukur 23 Okt 2026: 454 penolakan dalam 2 jam, semua pengguna gagal
     * masuk dengan pesan "SIMKOPDES membatasi login".
     *
     * ponytail: penjaga global di cache file (satu server). Pindah ke Redis kalau
     * Hub dijalankan lebih dari satu instance.
     */
    private const BLOKIR_DETIK = 1200;

    /**
     * Batas panggilan /auth/login per email per hari. Salah ketik sandi Hub
     * memicu satu panggilan SIMKOPDES (jalur cadangan), jadi tanpa batas ini satu
     * orang bisa mengirim puluhan login ke SIMKOPDES dan ikut menenggelamkan IP.
     */
    private const COBA_MAKS = 5;

    private const KUNCI_BLOKIR = 'simkopdes.login.blokir';

    /** Apakah server sedang diblokir SIMKOPDES untuk /auth/login. */
    public function loginDiblokir(): bool
    {
        return (int) Cache::get(self::KUNCI_BLOKIR, 0) > time();
    }

    /** Sisa detik blokir (0 = tidak diblokir). Dipakai pesan ke pengguna. */
    public function sisaBlokir(): int
    {
        return max(0, (int) Cache::get(self::KUNCI_BLOKIR, 0) - time());
    }

    public function terkonfigurasi(): bool
    {
        return (string) config('services.simkopdes.password') !== ''
            && (string) config('services.simkopdes.facilitator_id') !== '';
    }

    /** Token JWT; di-cache 20 jam (umur token ~1 hari). */
    public function token(): string
    {
        return Cache::remember('simkopdes.token', now()->addHours(20), function () {
            $r = $this->mentah('POST', '/login', null, [
                'email' => config('services.simkopdes.email'),
                'password' => config('services.simkopdes.password'),
            ]);

            $tok = $r['token'] ?? null;
            if (! is_string($tok) || $tok === '') {
                throw new \RuntimeException('SIMKOPDES menolak login (cek email/sandi di .env).');
            }

            // Sekalian catat id pendamping MILIK AKUN INI (dari respons login) —
            // satu-satunya sumber yang pasti cocok. Tanpa ini, `facilitatorId()`
            // memakai SIMKOPDES_FACILITATOR_ID di .env, dan kalau nilainya tidak
            // sepadan dengan akunnya (pernah kejadian: email 50989 vs id 50551),
            // SEMUA pembacaan profil/periode dijawab 403.
            $fac = $this->facIdDari($this->buka(['data' => $r['user'] ?? null]));
            if ($fac > 0) {
                Cache::put('simkopdes.fac_id', $fac, now()->addHours(20));
            }

            return $tok;
        });
    }

    /**
     * Id pendamping akun yang sedang masuk. Dibaca dari sesi login terakhir;
     * config hanya dipakai sebagai cadangan terakhir (mis. respons login berubah).
     * Inilah yang membuat integrasi tidak lagi bisa 403 gara-gara .env tidak sinkron.
     */
    public function facilitatorId(): int
    {
        $id = (int) Cache::get('simkopdes.fac_id', 0);
        if ($id > 0) {
            return $id;
        }

        try {
            $this->token(); // mengisi cache fac_id bila belum ada
        } catch (\Throwable) {
            // jatuh ke cadangan di bawah
        }

        return (int) Cache::get('simkopdes.fac_id', (int) config('services.simkopdes.facilitator_id'));
    }

    /** Id pendamping dari payload profil SIMKOPDES (bentuk datar atau bersarang). */
    private function facIdDari(mixed $profil): int
    {
        if (! is_array($profil)) {
            return 0;
        }

        return (int) (($profil['regional_facilitator_id'] ?? 0)
            ?: (($profil['regional_facilitator']['regional_facilitator_id'] ?? 0)));
    }

    /**
     * Jalan pintas: buang token lalu ambil yang baru. Dipakai tombol "Sambungkan"
     * supaya sandi yang baru saja diperbaiki langsung diuji tanpa menunggu cache.
     */
    public function segarkanToken(): string
    {
        Cache::forget('simkopdes.token');
        // Periode ikut dibuang: kalau akunnya berganti, period id lama tak berlaku.
        Cache::forget('simkopdes.periode_id');

        return $this->token();
    }

    /**
     * Uji kredensial SIMKOPDES lalu kembalikan profil pemiliknya (atau null
     * kalau ditolak). Dipakai halaman masuk sebagai jalur cadangan: pendamping
     * bisa masuk ke Hub memakai akun SIMKOPDES-nya sendiri.
     *
     * @return array<string, mixed>|null
     */
    public function masuk(string $email, string $sandi): ?array
    {
        // Sedang diblokir: jangan bidik lagi. Percobaan pengguna yang diulang
        // justru memperpanjang blokir IP server (lihat BLOKIR_DETIK).
        if ($this->loginDiblokir()) {
            throw new \RuntimeException('SIMKOPDES /auth/login diblokir sementara.');
        }

        // Batas per email: salah ketik sandi Hub = satu login ke SIMKOPDES.
        $kunci = 'simkopdes.cobaan.'.mb_strtolower(trim($email));
        $coba = (int) Cache::get($kunci, 0);
        if ($coba >= self::COBA_MAKS) {
            throw new \RuntimeException('Batas percobaan SIMKOPDES untuk akun ini habis.');
        }
        Cache::put($kunci, $coba + 1, now()->addDay());

        try {
            $r = $this->mentah('POST', '/login', null, ['email' => $email, 'password' => $sandi]);
        } catch (\RuntimeException $e) {
            // 401 = kredensial memang salah. Gangguan lain (jaringan, 5xx)
            // dilempar ulang supaya halaman masuk bisa membedakan pesannya.
            if (! str_contains($e->getMessage(), 'HTTP 401')) {
                throw $e;
            }

            return null;
        }

        Cache::forget($kunci); // berhasil — hitungan percobaan akun ini direset

        // Profil di respons login disimpan di `user`, terenkripsi sama seperti `data`.
        $profil = $this->buka(['data' => $r['user'] ?? null]);

        // Id pendamping milik akun ini ikut dicatat — lihat `facilitatorId()`.
        $fac = $this->facIdDari($profil);
        if ($fac > 0) {
            Cache::put('simkopdes.fac_id', $fac, now()->addHours(20));
        }

        // Simpan token hasil login ini. SIMKOPDES hanya mengizinkan SATU sesi
        // aktif: login di sini mematikan token sebelumnya. Tanpa penyegaran ini,
        // token di cache jadi basi dan panggilan berikutnya (mis. detail profil
        // tepat setelah login) dijawab 403 — gejalanya profil tersimpan tipis
        // tanpa riwayat kerja/rekening/dokumen, dan muncul-hilang tergantung
        // token mana yang kebetulan tersimpan.
        $tok = $r['token'] ?? null;
        if (is_string($tok) && $tok !== '') {
            Cache::put('simkopdes.token', $tok, now()->addHours(20));
        }

        return is_array($profil) ? $profil : null;
    }

    /** GET satu jalur, payload `data` sudah didekripsi. */
    public function ambil(string $path, array $kueri = []): mixed
    {
        return $this->buka($this->mentah('GET', $path, $this->token(), $kueri));
    }

    /**
     * Koperasi binaan akun ini (sudah tervalidasi per 2026-10-03: tepat 1 baris
     * untuk facilitator 50551, `cooperative_id` 37038).
     *
     * @return array<int, array<string, mixed>>
     */
    public function koperasi(): array
    {
        $pid = $this->periodeId();
        $baris = $this->ambil("/regional-facilitator-cooperatives/by-period-id/{$pid}");
        $baris = is_array($baris) && array_is_list($baris) ? $baris : [$baris];

        return array_values(array_filter($baris, 'is_array'));
    }

    /** Id periode pendampingan (dibaca dari profil facilitator, bukan ditebak). */
    public function periodeId(): int
    {
        return (int) Cache::remember('simkopdes.periode_id', now()->addDay(), function () {
            $id = $this->facilitatorId();
            $profil = $this->ambil("/regional-facilitators/detail/{$id}");
            $periode = $profil['regional_facilitator_periods'] ?? [];

            if (! is_array($periode) || $periode === []) {
                throw new \RuntimeException("Profil facilitator {$id} tidak memuat periode pendampingan.");
            }

            // Ambil periode terbaru.
            $ids = array_filter(array_map(
                fn ($p) => (int) ($p['regional_facilitator_period_id'] ?? 0), $periode));
            if ($ids === []) {
                throw new \RuntimeException('Periode pendampingan tidak punya id.');
            }

            return max($ids);
        });
    }

    /**
     * Profil lengkap satu pendamping (identitas, riwayat kerja, pendidikan,
     * bank). Dipakai untuk menyimpan profil agar Hub tidak perlu menanyakan
     * hal yang sudah ada di SIMKOPDES.
     *
     * $id boleh diisi pendamping yang sedang masuk; tanpa itu dipakai id dari
     * konfigurasi (jalur CLI). Penting: token pendamping HANYA boleh membaca
     * dirinya sendiri (`/regional-facilitators/detail/{id_lain}` → 403), jadi
     * profil orang lain tidak mungkin ditarik dari satu akun — harus disimpan
     * saat pendamping itu sendiri login.
     *
     * @return array<string, mixed>
     */
    public function pendamping(?int $id = null): array
    {
        $id = $id ?? $this->facilitatorId();
        $p = $this->ambil("/regional-facilitators/detail/{$id}");
        $p = is_array($p) ? $p : [];

        // Riwayat kerja & pendidikan sudah ikut di payload detail; rekening bank
        // ada di endpoint terpisah. Keduanya ikut profil agar Hub tidak perlu
        // menanyakan ulang data yang sudah ada di SIMKOPDES.
        try {
            $p['account_banks'] = $this->ambil("/regional-facilitator-account-banks/by-regional-facilitator-id/{$id}");
        } catch (\Throwable) {
            $p['account_banks'] = [];
        }

        return $p;
    }

    /**
     * Profil koperasi binaan: baris `cooperative` + daftar dokumen legalnya.
     * Baris cooperative sudah ikut di respons `koperasi()` (tanpa panggilan
     * tambahan); yang perlu satu panggilan lagi hanyalah dokumen.
     *
     * @return array<string, mixed>
     */
    public function profilKoperasi(int $id): array
    {
        $c = [];
        foreach ($this->koperasi() as $baris) {
            $c = (array) (($baris['cooperative'] ?? null) ?: []);
            if ((int) ($c['cooperative_id'] ?? 0) === $id) {
                break;
            }
            $c = [];
        }

        if ($c === []) {
            $c = ['cooperative_id' => $id];
        }

        try {
            $c['documents'] = $this->ambil("/cooperative-documents/by-cooperative-id/{$id}");
        } catch (\Throwable) {
            // Dokumen bersifat pelengkap — profil tetap tersimpan walau gagal.
            $c['documents'] = [];
        }

        return $c;
    }

    /**
     * Kode wilayah resmi (BPS) untuk sebuah id master. Endpoint master wilayah
     * TIDAK terenkripsi — bentuknya {message, data:{code, name, ...}}.
     * Contoh: district/169 → code 32.16 (KAB. BEKASI).
     */
    public function kodeWilayah(string $jenis, int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        return Cache::remember("simkopdes.wilayah.{$jenis}.{$id}", now()->addDays(7), function () use ($jenis, $id) {
            $r = $this->mentah('GET', "/{$jenis}/{$id}", $this->token());
            $kode = $r['data']['code'] ?? null;

            return is_string($kode) && $kode !== '' ? $kode : null;
        });
    }

    /**
     * Satu panggilan HTTP mentah; melempar pesan yang enak dibaca saat gagal.
     *
     * Retry 401: SIMKOPDES memakai SANCTUM SESI-TUNGGAL — login dari tempat lain
     * (skrip lain, atau Bos membuka SIMKOPDES di peramban) mematikan token yang
     * kita simpan. Tanpa retry, halaman ini gagal acak dengan HTTP 401 sampai
     * cache 20 jam kedaluwarsa. Jadi sekali ditolak: buang cache, login ulang,
     * ulangi permintaan. Satu guard di sini berlaku untuk SEMUA pemanggil.
     */
    private function mentah(string $metode, string $path, ?string $token, array $isi = []): array
    {
        // Ritme: jangan menembak /login beruntun — lihat App\Support\JedaLogin.
        if ($path === '/login' && ! JedaLogin::tunggu()) {
            throw new \RuntimeException('SIMKOPDES membatasi login otomatis (ritme/jatah) — coba lagi nanti.');
        }

        $respons = $this->kirim($metode, $path, $token, $isi);

        if ($token !== null && $respons->status() === 401) {
            $respons = $this->kirim($metode, $path, $this->segarkanToken(), $isi);
        }

        if (! $respons->successful()) {
            // 403/429 = ditolak/dikuotakan; 404 = endpoint login mereka pindah
            // (pernah kejadian 23 Okt 2026: /auth/login → /login). Ketiganya
            // berarti berhenti membidik selama BLOKIR_DETIK, supaya ratusan
            // percobaan pengguna tidak menumpuk jadi pola penebak sandi.
            if ($path === '/login' && in_array($respons->status(), [403, 404, 429], true)) {
                Cache::put(self::KUNCI_BLOKIR, time() + self::BLOKIR_DETIK, self::BLOKIR_DETIK);
            }

            throw new \RuntimeException(
                "SIMKOPDES {$path} menjawab HTTP {$respons->status()}.");
        }

        return (array) $respons->json();
    }

    private function kirim(string $metode, string $path, ?string $token, array $isi = [], ?string $dasar = null)
    {
        // connectTimeout longgar: api.simkopdes.go.id kadang lambat menjawab
        // (terukur 3,9 dtk untuk GET, 2,6 dtk untuk login, sementara batas
        // *connect* bawaan klien hanya 10 dtk) -> sesekali "cURL error 28".
        //
        // SENGAJA TANPA retry(): klien ini harus meneruskan 401 apa adanya
        // (sandinya memang salah) supaya halaman masuk bisa membedakan "sandi
        // salah" dari "layanan tidak bisa dihubungi". retry() mengulang respons
        // 401 lalu melempar RequestException, sehingga salah sandi terbaca
        // sebagai gangguan jaringan — dan sanak pemanggil lain (uji, CLI) ikut
        // salah baca. Kegagalan sementara ditutup di pemanggilnya: profil yang
        // belum lengkap tidak disimpan dan dicoba lagi saat login berikutnya.
        $http = Http::acceptJson()->withUserAgent(JedaLogin::UA)->timeout(30)->connectTimeout(20);

        if ($token !== null) {
            $http = $http->withToken($token);
        }

        if ($dasar === null) {
            // Relay Cloudflare jadi jalur UTAMA, bukan cadangan: IP server ini
            // tidak boleh tercatat di log SIMKOPDES (dipakai untuk absensi,
            // kontrak, koperasi — bukan hanya login). Satu titik ini berlaku
            // untuk SEMUA pemanggil, termasuk segarkanToken().
            // ponytail: relay mati = panggilan SIMKOPDES gagal (sengaja, jangan
            // bocor ke IP sendiri). Pasang relay kedua kalau ketersediaan jadi
            // masalah nyata.
            $kunciRelay = (string) config('services.simkopdes.relay_key');
            $relay = (string) config('services.simkopdes.relay');

            if ($relay !== '') {
                $dasar = $relay;
                if ($kunciRelay !== '') {
                    $http = $http->withHeaders(['X-Relay-Key' => $kunciRelay]);
                }
            } else {
                $dasar = (string) config('services.simkopdes.url');
            }
        } else {
            $kunciRelay = (string) config('services.simkopdes.relay_key');
            if ($kunciRelay !== '') {
                $http = $http->withHeaders(['X-Relay-Key' => $kunciRelay]);
            }
        }

        return $metode === 'POST'
            ? $http->post($dasar.$path, $isi)
            : $http->get($dasar.$path, $isi);
    }

    /** Dekripsi `data` (AES-256-CBC) lalu jadikan array; teruskan apa adanya bila bukan teks terenkripsi. */
    private function buka(array $amplop): mixed
    {
        $data = $amplop['data'] ?? null;

        if (! is_string($data) || $data === '') {
            return $data;
        }

        $kunci = hash('sha256', self::PROTO_KEY, true);
        $teks = openssl_decrypt(base64_decode($data), 'aes-256-cbc', $kunci,
            OPENSSL_RAW_DATA, self::PROTO_IV);

        if ($teks === false) {
            throw new \RuntimeException('Payload SIMKOPDES gagal didekripsi.');
        }

        return json_decode($teks, true, 512, JSON_THROW_ON_ERROR);
    }
}

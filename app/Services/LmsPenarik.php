<?php

namespace App\Services;

use App\Models\LmsMateri;

/**
 * Menarik modul pelatihan dari LMS APN (Laravel + Inertia).
 *
 * LMS tidak punya API publik, tetapi setiap halaman Inertia mengirim props JSON
 * yang sama dengan yang dipakai aplikasinya sendiri — jadi cukup login sekali
 * (cookie sesi) lalu minta halaman dengan header X-Inertia. Tidak ada HTML yang
 * diparsing.
 *
 * Alur tiga langkah, karena datanya bertingkat di LMS:
 *   1. GET /learning-paths            -> daftar learning path (id + judul)
 *   2. GET /learning-paths/{id}       -> modul-modul di dalam path itu
 *   3. GET /learning-paths/{id}/modules/{mid} -> konten HTML tiap modul
 *
 * ponytail: daftar path tidak dipagini (endpoint mengembalikan seluruh path);
 * kalau nanti >100 path, tambahkan perulangan `last_page`.
 */
class LmsPenarik
{
    private string $base;
    private string $email;
    private string $sandi;

    /** Kode HTTP balasan terakhir — dipakai membedakan 200 / 409 / 401. */
    private int $kode = 0;

    public function __construct(string $base = '', string $email = '', string $sandi = '')
    {
        $this->base = rtrim($base ?: (string) config('services.lms.url'), '/');
        $this->email = $email ?: (string) config('services.lms.email');
        $this->sandi = $sandi ?: (string) config('services.lms.sandi');
    }

    /** Tarik seluruh modul dari LMS dan simpan (upsert) ke tabel lokal. */
    public function tarik(): array
    {
        $kuki = $this->masuk();
        $versi = $this->versi();
        $modul = [];
        $halaman = 1;

        do {
            $daftar = $this->inertia($kuki, $versi, '/learning-paths?page='.$halaman);
            $lp = $daftar['props']['learningPaths'] ?? [];

            foreach ($lp['data'] ?? [] as $p) {
                $hal = $this->inertia($kuki, $versi, '/learning-paths/'.$p['id']);
                foreach ((array) ($hal['props']['learningPath']['modules'] ?? []) as $m) {
                    $modul[] = [
                        'lms_id' => (int) $m['id'], 'path_id' => (int) $p['id'],
                        'path_judul' => (string) $p['title'], 'judul' => (string) $m['title'],
                        'slug' => (string) ($m['slug'] ?? ''), 'ringkas' => (string) ($m['excerpt'] ?? ''),
                        'menit' => (int) ($m['estimated_minutes'] ?? 0), 'posisi' => (int) ($m['position'] ?? 0),
                    ];
                }
            }
            $halaman++;
        } while (! empty($lp['next_page_url']) && $halaman < 20);

        // Konten penuh hanya ada di halaman modul, jadi diambil satu per satu.
        foreach ($modul as $i => $m) {
            try {
                $hal = $this->inertia($kuki, $versi, '/learning-paths/'.$m['path_id'].'/modules/'.$m['lms_id']);
                $modul[$i]['konten'] = (string) ($hal['props']['module']['content'] ?? '');
            } catch (\Throwable $e) {
                $modul[$i]['konten'] = '';   // modul tanpa konten (mis. kuis)
            }
        }

        return $this->simpan($modul);
    }

    /** Upsert per `lms_id` — menarik ulang tidak menggandakan baris. */
    private function simpan(array $modul): array
    {
        $baru = 0;
        $diperbarui = 0;

        foreach ($modul as $m) {
            $isi = ['path_id' => $m['path_id'], 'path_judul' => $m['path_judul'], 'judul' => $m['judul'],
                    'slug' => $m['slug'], 'ringkas' => $m['ringkas'], 'konten' => $m['konten'],
                    'menit' => $m['menit'], 'posisi' => $m['posisi'], 'diperbarui' => kini()];
            $ada = LmsMateri::query()->where('lms_id', $m['lms_id'])->first();

            if ($ada) {
                $ada->update($isi);
                $diperbarui++;
            } else {
                LmsMateri::create($isi + ['lms_id' => $m['lms_id'], 'dibuat' => kini()]);
                $baru++;
            }
        }

        return ['baru' => $baru, 'diperbarui' => $diperbarui, 'total' => count($modul)];
    }

    /** Login ke LMS; kembalikan cookie sesi sebagai string header. */
    private function masuk(): array
    {
        $jar = tempnam(sys_get_temp_dir(), 'lms');
        $this->curl(null, 'GET', '/login', [], null, null, $jar);
        $xsrf = $this->kuki($jar, 'XSRF-TOKEN') ?? '';
        $this->curl(null, 'POST', '/login',
            ['Content-Type: application/json', 'Accept: application/json', 'X-XSRF-TOKEN: '.$xsrf],
            json_encode(['login' => $this->email, 'password' => $this->sandi]), null, $jar);

        // 200 = masuk (isi {"two_factor":false}); 422 = kredensial salah.
        $sesi = $this->kuki($jar, 'lms-apn-session') ?? $this->kuki($jar, 'lms_session');
        $xsrf2 = $this->kuki($jar, 'XSRF-TOKEN') ?? $xsrf;
        $kode = $this->kode;
        @unlink($jar);

        if ($kode !== 200 || ! $sesi) {
            throw new \RuntimeException('Gagal masuk ke LMS (HTTP '.$kode.') — periksa LMS_EMAIL / LMS_SANDI.');
        }

        return ['sesi' => $sesi, 'xsrf' => $xsrf2];
    }

    /** Versi aset Inertia; diambil dari HTML /login supaya balasan tidak 409. */
    private function versi(): string
    {
        $html = $this->curl(null, 'GET', '/login');
        if (preg_match('/"version":"([^"]+)"/', (string) $html, $m)) {
            return $m[1];
        }

        return '';
    }

    /** GET satu halaman Inertia dan kembalikan props JSON-nya. */
    private function inertia(array $kuki, string $versi, string $jalur): array
    {
        $kepala = ['Accept: application/json', 'X-Inertia: true'];
        if ($versi !== '') {
            $kepala[] = 'X-Inertia-Version: '.$versi;
        }

        $json = json_decode((string) $this->curl(null, 'GET', $jalur, $kepala, null, $kuki), true);
        if (! is_array($json)) {
            throw new \RuntimeException('Balasan LMS bukan JSON untuk '.$jalur.' (HTTP '.$this->kode.').');
        }

        return $json;
    }

    /** Pemanggil curl tunggal. `$kuki` = cookie string; `$jar` = berkas cookie. */
    private function curl(?string $jar, string $metode, string $jalur, array $kepala = [],
                          ?string $badan = null, ?array $kuki = null,
                          ?string $jarKhusus = null): string
    {
        $c = curl_init($this->base.$jalur);
        $kepala[] = 'User-Agent: AgrinasHub/1.0';
        if ($kuki) {
            $kepala[] = 'Cookie: XSRF-TOKEN='.$kuki['xsrf'].'; lms-apn-session='.$kuki['sesi'];
        }
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $kepala,
            CURLOPT_CUSTOMREQUEST => $metode,
        ]);
        if ($jarKhusus) {
            curl_setopt($c, CURLOPT_COOKIEJAR, $jarKhusus);
            curl_setopt($c, CURLOPT_COOKIEFILE, $jarKhusus);   // wajib: baca balik XSRF
        }
        if ($badan !== null) {
            curl_setopt($c, CURLOPT_POSTFIELDS, $badan);
        }

        $hasil = curl_exec($c);
        $this->kode = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
        if ($hasil === false) {
            $pesan = curl_error($c);
            curl_close($c);
            throw new \RuntimeException('Gagal menghubungi LMS: '.$pesan);
        }
        curl_close($c);

        return (string) $hasil;
    }

    /** Ambil nilai satu cookie dari berkas cookie jar Netscape. */
    private function kuki(string $jar, string $nama): ?string
    {
        foreach ((array) @file($jar) as $baris) {
            if ($baris === '' || ($baris[0] === '#' && ! str_starts_with($baris, '#HttpOnly_'))) {
                continue;
            }
            $kolom = explode("\t", rtrim(str_replace('#HttpOnly_', '', $baris), "\n"));
            if (count($kolom) >= 7 && $kolom[5] === $nama) {
                return urldecode($kolom[6]);
            }
        }

        return null;
    }
}

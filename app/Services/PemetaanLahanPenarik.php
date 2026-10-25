<?php

namespace App\Services;

use App\Models\PemetaanLahan;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Penarik pemetaan lahan KDKMP tingkat desa dari `pemetaan-lahan.portalkdkmp.id`.
 *
 * TERBUKA TANPA LOGIN (terverifikasi 2026-10-09). Resep:
 *   GET /api/dashboard/detail-data-paginated?per_page=100&page=N
 *   GET /api/dashboard/detail-data-paginated?per_page=100&page=N&is_start_development=1
 *   GET /api/dashboard/detail-data-paginated?per_page=100&page=N&progress_100_percent=1
 *
 * Jebakan yang sudah diuji — jangan diulang:
 *  - `per_page` HANYA menerima 10 / 25 / 50 / 100. Nilai lain dijawab
 *    422 "Per page yang dipilih tidak valid" (termasuk 5 dan 20).
 *  - Filter wilayah memakai sintaks koma-array: `provinsi[]=Jawa Barat`
 *    (terbukti 3.541 baris = persis angka agregat provinsi itu). Tanpa `[]`
 *    diabaikan DIAM-DIAM — totalnya tetap nasional, jadi salahnya tidak kelihatan.
 *  - Tanpa dua filter status, respons hanya memuat 10 kolom dan TIDAK ada kolom
 *    progress. Status pembangunan diturunkan dari keanggotaan himpunan
 *    `progress_100_percent` (selesai) dan `is_start_development` (sedang jalan).
 *
 * 389 halaman × 3 himpunan. Dikerjakan dengan `Http::pool` (10 permintaan
 * sekaligus): berurutan butuh ± 14 menit, paralel ± 2 menit. Tanpa ini harvest
 * tidak selesai di dalam satu perintah (batas 10 menit).
 */
class PemetaanLahanPenarik
{
    private const BASE = 'https://pemetaan-lahan.portalkdkmp.id';

    private const PER_PAGE = 100;

    /** per_page yang benar-benar diterima sumber (terukur, bukan tebakan). */
    private const PER_PAGE_SAH = [10, 25, 50, 100];

    /** Berapa permintaan sekaligus. 10 sudah cukup; lebih besar kena tolak. */
    private const PARALEL = 10;

    /**
     * Tarik seluruh Indonesia lalu simpan. Idempoten (upsert per `pemetaan_id`).
     *
     * ponytail: seluruh Indonesia di satu jalan; kalau kelak perlu sering
     * diperbarui, tambahkan parameter wilayah (sumbernya mendukung `provinsi[]`)
     * supaya bisa disegarkan per provinsi.
     *
     * @param  callable|null  $lapor  fn(int $selesai, int $total, string $tahap)
     * @return array{baris:int, halaman:int, gagal:int, dipangkas:int}
     */
    public function tarik(?callable $lapor = null, int $maksHalaman = 0): array
    {
        // Stempel awal jalan: baris yang `ditarik`-nya lebih lama dari ini tidak
        // terlihat di harvest kali ini. Namanya `$cap` — `$mulai` sudah dipakai
        // himpunan `is_start_development` di bawah.
        $cap = now()->subSecond();
        $selesai = $this->himpunan('progress_100_percent', $lapor, $maksHalaman);
        $mulai = $this->himpunan('is_start_development', $lapor, $maksHalaman);

        $baris = 0;
        $gagal = 0;
        $halaman = 1;
        $maks = 1;
        // `--maks` membatasi jumlah halaman: dipakai uji supaya satu kali jalan
        // tidak menunggu seluruh Indonesia.
        $plafon = $maksHalaman > 0 ? $maksHalaman : PHP_INT_MAX;

        while ($halaman <= min($maks, $plafon)) {
            $gelombang = [];
            for ($i = 0; $i < self::PARALEL; $i++) {
                $gelombang[] = $halaman + $i;
            }
            $hasil = $this->banyak($gelombang, '');

            $batch = [];
            foreach ($hasil as $d) {
                if ($d === null) {
                    $gagal++;
                    continue;
                }
                $maks = max($maks, (int) ($d['pagination']['last_page'] ?? 1));
                foreach ($d['data'] as $r) {
                    $batch[] = $this->barisSiap($r, $selesai, $mulai);
                }
            }
            if ($batch) {
                $this->simpan($batch);
                $baris += count($batch);
            }

            $halaman += self::PARALEL;
            $this->lapor($lapor, $baris, $maks * self::PER_PAGE, 'baris');
        }

        // Sumber bisa MENGHAPUS titik. Upsert hanya menambah/menimpa, jadi tanpa
        // langkah ini baris yang sudah hilang di sumber tetap tinggal di sini dan
        // cerminnya melenceng (terukur 2026-10-19: 38.863 lokal vs 38.858 sumber).
        // Dipangkas HANYA bila harvest penuh & tanpa halaman gagal — kalau ada
        // yang gagal, "tidak tersentuh" belum tentu berarti "sudah dihapus".
        $pangkas = 0;
        if ($gagal === 0 && $this->hilang === 0 && $maksHalaman === 0) {
            $pangkas = DB::connection('hub')->table('pemetaan_lahan')
                ->where('ditarik', '<', $cap)->delete();
        }

        Log::info('pemetaan:tarik', ['baris' => $baris, 'halaman' => $maks,
            'gagal' => $gagal, 'dipangkas' => $pangkas]);

        return ['baris' => $baris, 'halaman' => $maks,
            'gagal' => $gagal + $this->hilang, 'dipangkas' => $pangkas];
    }

    /**
     * Kumpulkan id dari satu filter status (selesai 100% / sedang dibangun).
     * Hanya id — kolom progress tidak dikirim sumber, jadi keanggotaan himpunan
     * inilah satu-satunya penandanya.
     *
     * @return array<int, bool>
     */
    private function himpunan(string $param, ?callable $lapor, int $maksHalaman = 0): array
    {
        $ids = [];
        $halaman = 1;
        $maks = 1;
        $plafon = $maksHalaman > 0 ? $maksHalaman : PHP_INT_MAX;

        while ($halaman <= min($maks, $plafon)) {
            $gelombang = [];
            for ($i = 0; $i < self::PARALEL; $i++) {
                $gelombang[] = $halaman + $i;
            }
            // Halaman yang gagal HARUS diulang, bukan dilewati: di sini tidak ada
            // penghitung `gagal`, jadi `continue` diam-diam = id hilang tanpa
            // jejak. Terukur 2026-10-19: satu gelombang (10 halaman = 1.000 id)
            // hilang sehingga 1.000 baris "selesai" tercatat "belum dibangun".
            foreach ($this->banyakUlang($gelombang, "&{$param}=1") as $d) {
                if ($d === null) {
                    $this->hilang += count($gelombang); // dihitung, bukan disembunyikan
                    continue;
                }
                $maks = max($maks, (int) ($d['pagination']['last_page'] ?? 1));
                foreach ($d['data'] as $r) {
                    $ids[(int) $r['id']] = true;
                }
            }
            $halaman += self::PARALEL;
            $this->lapor($lapor, count($ids), $maks * self::PER_PAGE, $param);
        }

        return $ids;
    }

    /** Jumlah halaman yang tetap gagal setelah diulang (dilaporkan, bukan disembunyikan). */
    private int $hilang = 0;

    /**
     * `banyak()` + satu putaran ulang untuk halaman yang gagal. Satu kegagalan
     * jaringan sesaat tidak boleh berarti 1.000 baris salah status.
     */
    private function banyakUlang(array $halaman, string $ekstra): array
    {
        $hasil = $this->banyak($halaman, $ekstra);
        $gagal = array_keys(array_filter($hasil, fn ($d) => $d === null));
        if ($gagal !== []) {
            foreach ($this->banyak($gagal, $ekstra) as $h => $d) {
                if ($d !== null) {
                    $hasil[$h] = $d;
                }
            }
        }

        return $hasil;
    }

    /**
     * Ambil beberapa halaman SEKALIGUS. Mengembalikan [halaman => payload] —
     * halaman yang gagal bernilai null. Paralel itu wajib di sini: berurutan
     * 0,74 detik/halaman × 389 = 14 menit, di luar batas satu perintah.
     */
    private function banyak(array $halaman, string $ekstra): array
    {
        $paths = [];
        foreach ($halaman as $h) {
            $paths[$h] = '/api/dashboard/detail-data-paginated?per_page='.self::PER_PAGE."&page={$h}{$ekstra}";
        }

        try {
            $respons = Http::pool(function (Pool $pool) use ($paths) {
                foreach ($paths as $h => $p) {
                    $pool->as((string) $h)->timeout(60)
                        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
                        ->get(self::BASE.$p);
                }
            });
        } catch (\Throwable $e) {
            return array_fill_keys(array_keys($paths), null);
        }

        $keluar = [];
        foreach ($paths as $h => $p) {
            $r = $respons[(string) $h] ?? null;
            $d = ($r !== null && method_exists($r, 'ok') && $r->ok()) ? $r->json() : null;
            $keluar[$h] = (is_array($d) && isset($d['data']) && is_array($d['data'])) ? $d : null;
        }

        return $keluar;
    }

    /** Satu baris sumber → satu baris tabel. */
    private function barisSiap(array $r, array $selesai, array $mulai): array
    {
        $id = (int) $r['id'];
        $lok = PemetaanLahan::pecahLokasi((string) ($r['lokasi'] ?? ''));

        return [
            'pemetaan_id' => $id,
            'nama' => trim((string) ($r['nama'] ?? '')),
            'telepon' => (string) preg_replace('/\D/', '', (string) ($r['nomorTelepon'] ?? '')),
            'lokasi' => trim((string) ($r['lokasi'] ?? '')),
            'desa' => $lok['desa'],
            'kecamatan' => $lok['kecamatan'],
            'kabupaten' => $lok['kabupaten'],
            'provinsi' => trim((string) ($r['provinsi'] ?? '')),
            'luas' => PemetaanLahan::parseLuas($r['luasLahan'] ?? null),
            'potensi' => trim((string) ($r['potensi'] ?? '')),
            'latitude' => is_numeric($r['latitude'] ?? null) ? (float) $r['latitude'] : null,
            'longitude' => is_numeric($r['longitude'] ?? null) ? (float) $r['longitude'] : null,
            'pembangunan_100' => isset($selesai[$id]),
            'pembangunan_mulai' => isset($mulai[$id]),
            'ditarik' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** Upsert per `pemetaan_id` — tarik ulang tidak menggandakan baris. */
    private function simpan(array $batch): void
    {
        DB::connection('hub')->table('pemetaan_lahan')->upsert(
            $batch,
            ['pemetaan_id'],
            ['nama', 'telepon', 'lokasi', 'desa', 'kecamatan', 'kabupaten', 'provinsi',
                'luas', 'potensi', 'latitude', 'longitude',
                'pembangunan_100', 'pembangunan_mulai', 'ditarik', 'updated_at']
        );
    }

    /** `?->` pada variabel callable bukan sintaks PHP yang sah. */
    private function lapor(?callable $lapor, int $selesai, int $total, string $tahap): void
    {
        if ($lapor !== null) {
            $lapor($selesai, $total, $tahap);
        }
    }

    /** Dipakai uji: per_page yang sah, supaya jangan ditebak ulang. */
    public static function perPageSah(): array
    {
        return self::PER_PAGE_SAH;
    }
}

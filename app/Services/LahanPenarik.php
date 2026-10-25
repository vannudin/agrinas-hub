<?php

namespace App\Services;

use App\Models\Koperasi;
use App\Models\KoperasiLahan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Penarik data lahan & pembangunan KDKMP (verval aset) dari SIMKOPDES.
 *
 * Jalur yang dipakai (terverifikasi 2026-10-09): satu token akun operasional
 * cukup untuk membaca aset SELURUH pendamping lewat
 * `/cooperative-assets/by-regional-facilitator-id/{fid}` — fid diambil dari
 * `users.simkopdes_facilitator_id` (14 pendamping terdaftar).
 *
 * Yang TIDAK bisa ditarik, jangan dicari lagi:
 *  - `/cooperative-assets/by-cooperative-id/{id}` → 403 untuk hampir semua
 *    koperasi (hanya terbaca untuk koperasi milik akun pemanggil).
 *  - `/regional-facilitators` (daftar pendamping) → 403. Jadi kontak SEMUA
 *    pendamping tidak bisa dienumerasi dari Hub.
 *  - `/cooperatives/{id}` → 404.
 *
 * Nomor telepon yang tersedia: `cooperative.phone` (kontak koperasi) dan
 * `regional_facilitator_id` (pendamping penanggung jawab). Nomor HP Babinsa
 * TIDAK ADA di API ini — jangan dikarang.
 */
class LahanPenarik
{
    public function __construct(private readonly Simkopdes $simkopdes) {}

    /**
     * Tarik seluruh aset yang bisa dibaca akun ini, lalu simpan.
     *
     * @return array{baris:int, koperasi:int, gagal:array<int,string>, dilewati:int}
     */
    public function tarik(): array
    {
        $fids = DB::table('users')->whereNotNull('simkopdes_facilitator_id')
            ->distinct()->pluck('simkopdes_facilitator_id')
            ->map(fn ($v) => (int) $v)->filter()->values()->all();

        // Peta koperasi_simkopdes_id -> koperasi Hub. Sekaligus nama: sebagian
        // aset tidak memuat relasi `cooperative`, jadi namanya kosong tanpa ini.
        $peta = Koperasi::whereNotNull('koperasi_simkopdes_id')
            ->get(['id', 'nama', 'koperasi_simkopdes_id'])
            ->keyBy('koperasi_simkopdes_id');

        $ringkas = ['baris' => 0, 'koperasi' => [], 'gagal' => [], 'dilewati' => 0];

        foreach ($fids as $fid) {
            try {
                $d = $this->simkopdes->ambil("/cooperative-assets/by-regional-facilitator-id/{$fid}");
            } catch (\Throwable $e) {
                $ringkas['gagal'][] = "fid {$fid}: ".substr($e->getMessage(), 0, 90);
                continue;
            }

            $rows = is_array($d) && is_array($d['data'] ?? null) ? $d['data'] : (is_array($d) ? $d : []);
            foreach ($rows as $r) {
                if (! is_array($r) || empty($r['cooperative_asset_id'])) {
                    $ringkas['dilewati']++;
                    continue;
                }
                $kop = $peta[(int) ($r['cooperative_id'] ?? 0)] ?? null;
                KoperasiLahan::updateOrCreate(
                    ['asset_simkopdes_id' => (int) $r['cooperative_asset_id']],
                    [
                        'koperasi_id' => $kop?->id,
                        'koperasi_simkopdes_id' => $r['cooperative_id'] ?? null,
                        'nama' => (string) ($r['name'] ?? ''),
                        'nama_koperasi' => (string) ($r['cooperative']['name'] ?? $kop?->nama ?? ''),
                        'kontak' => (string) ($r['cooperative']['phone'] ?? ''),
                        'luas' => $this->angka($r['land_area'] ?? null),
                        'lebar' => $this->angka($r['land_width'] ?? null),
                        'panjang' => $this->angka($r['land_length'] ?? null),
                        'status_aset' => (string) ($r['asset_status'] ?? ''),
                        'kepemilikan' => (string) ($r['ownership'] ?? ''),
                        'status' => (string) ($r['status'] ?? ''),
                        'akses_jalan' => (string) ($r['road_access'] ?? ''),
                        'surveyor' => (string) ($r['surveyor'] ?? ''),
                        'pemilik_terdaftar' => (string) ($r['registered_owner_name'] ?? ''),
                        'alamat' => (string) ($r['address'] ?? ''),
                        'latitude' => $this->angka($r['latitude'] ?? null),
                        'longitude' => $this->angka($r['longitude'] ?? null),
                        'keterangan' => $this->json($r['asset_information'] ?? null),
                        'village_code' => (string) ($r['village_code'] ?? ''),
                        'berkas' => (string) ($r['document_primary_file'] ?? ''),
                        'foto' => (string) ($r['image_primary'] ?? ''),
                        'aset_diperbarui' => $this->waktu($r['updated_at'] ?? null),
                        'ditarik' => now(),
                    ]
                );
                $ringkas['baris']++;
                if ($kop) {
                    $ringkas['koperasi'][$kop->id] = true;
                }
            }
        }

        Log::info('lahan:tarik', ['baris' => $ringkas['baris'], 'koperasi' => count($ringkas['koperasi']),
            'gagal' => count($ringkas['gagal']), 'dilewati' => $ringkas['dilewati']]);

        $ringkas['koperasi'] = count($ringkas['koperasi']);

        return $ringkas;
    }

    /** SIMKOPDES mengirim angka sebagai STRING ("750.00"); kosong tetap null. */
    private function angka(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? (float) $v : null;
    }

    /** `asset_information` datang sebagai string JSON, kadang sudah array. */
    private function json(mixed $v): ?array
    {
        if (is_array($v)) {
            return $v;
        }
        if (! is_string($v) || $v === '') {
            return null;
        }
        $d = json_decode($v, true);

        return is_array($d) ? $d : null;
    }

    private function waktu(mixed $v): ?string
    {
        if (! is_string($v) || $v === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($v)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Data lahan & pembangunan KDKMP (verval aset) milik satu koperasi.
 *
 * Diisi oleh App\Services\LahanPenarik dari SIMKOPDES. Lihat migrasi
 * `koperasi_lahan` untuk alasan disimpan, bukan diambil saat halaman dibuka.
 */
class KoperasiLahan extends Model
{
    protected $connection = 'hub';
    protected $table = 'koperasi_lahan';

    protected $fillable = [
        'koperasi_id', 'asset_simkopdes_id', 'koperasi_simkopdes_id',
        'nama', 'nama_koperasi', 'kontak',
        'luas', 'lebar', 'panjang',
        'status_aset', 'kepemilikan', 'status', 'akses_jalan', 'surveyor',
        'pemilik_terdaftar', 'alamat', 'latitude', 'longitude', 'keterangan',
        'village_code', 'berkas', 'foto', 'aset_diperbarui', 'ditarik',
    ];

    protected function casts(): array
    {
        return [
            'luas' => 'float', 'lebar' => 'float', 'panjang' => 'float',
            'latitude' => 'float', 'longitude' => 'float',
            'aset_diperbarui' => 'datetime', 'ditarik' => 'datetime',
            'keterangan' => 'array',
        ];
    }

    /** Ada koordinat → bisa ditautkan ke peta. */
    public function adaTitik(): bool
    {
        return $this->latitude !== null && $this->longitude !== null
            && ($this->latitude !== 0.0 || $this->longitude !== 0.0);
    }

    /** Tautan Google Maps ke titik lahan. Satu definisi di helper tautan_peta(). */
    public function tautanPeta(): string
    {
        return tautan_peta((float) $this->latitude, (float) $this->longitude);
    }

    /** Bagian `asset_information` (JSON) yang layak ditampilkan, berlabel. */
    public function keteranganRapi(): array
    {
        $d = is_array($this->keterangan) ? $this->keterangan : [];
        $keluar = [];
        foreach ([
            'distance_to_market' => 'Jarak ke pasar',
            'distance_to_settlement' => 'Jarak ke permukiman',
            'water_access' => 'Akses air',
            'electricity_access' => 'Akses listrik',
            'internet_access' => 'Akses internet',
        ] as $k => $label) {
            if (! empty($d[$k])) {
                $keluar[$label] = is_scalar($d[$k]) ? (string) $d[$k] : json_encode($d[$k]);
            }
        }
        // `strategic_locations` = flag Yes/No; hanya yang "Yes" yang menarik dibaca.
        foreach (($d['strategic_locations'] ?? []) as $k => $v) {
            if (in_array(strtolower((string) $v), ['yes', 'ya', '1', 'true'], true)) {
                $keluar['Dekat '.str_replace('_', ' ', $k)] = 'Ya';
            }
        }

        return $keluar;
    }
}

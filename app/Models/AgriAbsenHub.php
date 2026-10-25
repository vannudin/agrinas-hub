<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu KEJADIAN absen (masuk / istirahat_mulai / istirahat_selesai / keluar).
 *
 * Sengaja bukan "satu baris per hari": istirahat dan sesi kerja lebih dari satu
 * hanya bisa dicatat kalau tiap kejadian punya barisnya sendiri. Ringkasan
 * harian dihitung dari kejadian-kejadian ini, bukan disimpan terpisah — supaya
 * tidak pernah ada dua angka yang saling bertentangan.
 */
class AgriAbsenHub extends Model
{
    protected $connection = 'hub';
    protected $table = 'agri_absen_hub';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'koperasi_id', 'tanggal', 'jenis', 'waktu', 'pada', 'zona',
        'foto', 'lat', 'lng', 'akurasi', 'lokasi', 'catatan', 'jarak_m', 'kecepatan',
        'ip', 'perangkat', 'dibuat',
    ];

    public const JENIS = [
        'masuk' => 'Masuk',
        'istirahat_mulai' => 'Mulai Istirahat',
        'istirahat_selesai' => 'Selesai Istirahat',
        'keluar' => 'Keluar',
    ];
}

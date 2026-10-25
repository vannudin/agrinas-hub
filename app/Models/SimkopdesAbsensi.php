<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris absensi KDKMP — cermin daftar hadir SIMKOPDES di sisi Hub.
 *
 * `sumber` menandai asalnya:
 *   SIMP : ditarik dari SIMKOPDES (satu-satunya sumber daftar hadir resmi)
 *   HUB  : lahir dari Check In di Hub, lalu dikirim ke SIMKOPDES
 *
 * Absensi SIMKOPDES tidak bisa diubah maupun dihapus — API-nya tidak punya
 * endpoint untuk itu. Baris di sini pun sengaja hanya ditambah: `kirim_pada`
 * diisi SEKALI setelah server menerima, dan tidak ada jalur yang mengubah
 * baris yang sudah terkirim. Itulah yang membuat pengiriman berulang aman.
 */
class SimkopdesAbsensi extends Model
{
    protected $connection = 'hub';
    protected $table = 'simkopdes_absensi';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'simkopdes_absensi_id', 'periode_id', 'tanggal', 'koperasi_simkopdes_id',
        'aktivitas', 'catatan', 'masuk', 'keluar', 'masuk_pada', 'keluar_pada',
        'lat', 'lng', 'foto_masuk_url', 'foto_keluar_url', 'masuk_foto', 'keluar_foto',
        'sumber', 'kirim_pada', 'ditarik_pada',
    ];
}

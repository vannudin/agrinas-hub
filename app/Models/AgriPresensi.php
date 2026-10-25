<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Presensi mandiri (Check In / Check Out) modul AgriConnect; satu baris per
 * pengguna per hari.
 *
 * Foto & koordinat bergaya SIMKOPDES: Check In membawa foto selfie dan titik
 * lokasi, keterangan tetap opsional. Keduanya bukti, jadi tidak boleh diubah
 * setelah tercatat — karena itu hanya kolom `*_foto`/`*_lat`/`*_lng` yang
 * fillable, dan tidak ada jalur di controller yang memperbaruinya.
 */
class AgriPresensi extends Model
{
    protected $connection = 'hub';
    protected $table = 'agri_presensi';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'koperasi_id', 'tanggal', 'masuk', 'keluar', 'catatan', 'dibuat',
        'masuk_foto', 'masuk_lat', 'masuk_lng', 'masuk_akurasi', 'masuk_lokasi', 'masuk_pada',
        'keluar_foto', 'keluar_lat', 'keluar_lng', 'keluar_akurasi', 'keluar_lokasi', 'keluar_pada',
    ];
}

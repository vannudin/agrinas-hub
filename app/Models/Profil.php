<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Profil pengguna — cerminan profil SIMKOPDES di sisi Hub.
 *
 * Tujuannya: login jadi ringan. Turunan sandi SIMKOPDES (`sandi` di tabel
 * `users`) memakai scrypt, dan ronde itu mahal; menyimpannya per percobaan
 * login langsung terasa. Karena itu sandi SIMKOPDES di-hash SEKALI saat ditarik
 * di sini, dan `users.sandi` tetap jalur utama saat login.
 *
 * `akhir_tarik` menandai umur salinan: sandi SIMKOPDES yang diganti di sana
 * membuat salinan ini basi, jadi halaman Profil menawarkan tarik ulang.
 */
class Profil extends Model
{
    protected $connection = 'hub';
    protected $table = 'profil';
    public $timestamps = false;

    protected $fillable = ['user_id', 'sumber', 'simkopdes_user_id', 'email',
        'nama', 'niak', 'user_code', 'telepon', 'peran_simkopdes', 'gender',
        'tempat_lahir', 'tanggal_lahir', 'agama', 'status_kawin',
        'golongan_darah', 'alamat', 'kode_pos', 'provinsi', 'kabupaten',
        'kecamatan', 'desa', 'koperasi_id', 'koperasi_simkopdes_id',
        'terverifikasi', 'profil', 'akhir_tarik'];

    protected function casts(): array
    {
        return ['profil' => 'array'];
    }
}

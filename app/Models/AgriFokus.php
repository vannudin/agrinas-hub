<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Langkah belajar per akun untuk satu pelajaran (`agri_materi`).
 *
 * E-Learn di aplikasi AgriConnect berbentuk path + progress. Di hub ini bentuk
 * itu dijalankan dengan satu baris per langkah: admin mengisi daftar langkah
 * pada pelajaran (dipisah baris baru), lalu tiap akun mencentangnya. Selesai =
 * semua langkah tercentang. Unik per (akun, pelajaran, langkah) supaya centang
 * dua kali tidak menggandakan baris — maupun poin.
 */
class AgriFokus extends Model
{
    protected $connection = 'hub';
    protected $table = 'agri_fokus';
    public $timestamps = false;

    protected $fillable = ['user_id', 'materi_id', 'langkah', 'selesai', 'dibuat', 'dicatat'];
}

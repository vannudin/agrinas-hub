<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pelajaran E-Learning berbasis teks; kalau isi kosong, kartunya menautkan ke tautan. */
class AgriMateri extends Model
{
    protected $connection = 'hub';
    protected $table = 'agri_materi';
    public $timestamps = false;

    protected $fillable = ['judul', 'ringkas', 'isi', 'tautan', 'menit', 'posisi', 'oleh', 'dibuat', 'langkah'];
}

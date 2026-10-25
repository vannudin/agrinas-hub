<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materi extends Model
{
    /** Disk Laravel yang dipakai; root = storage/app. */
    public const DISK = 'r2';

    protected $connection = 'hub';
    protected $table = 'materi';
    public $timestamps = false;

    protected $fillable = ['judul', 'kategori', 'nama_asli', 'berkas', 'mime',
                           'ukuran', 'catatan', 'oleh', 'dibuat', 'kelompok', 'thumb'];

    public const KATEGORI = ['MODUL' => 'Modul Pelatihan', 'SLIDE' => 'Bahan Tayang',
                             'TEMPLATE' => 'Kertas Kerja', 'JUKNIS' => 'Juknis / Panduan',
                             'DOKUMEN' => 'Dokumen', 'VIDEO' => 'Video'];
}

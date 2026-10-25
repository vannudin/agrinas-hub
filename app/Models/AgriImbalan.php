<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Katalog hadiah yang bisa ditukar poin (Marketplace). */
class AgriImbalan extends Model
{
    protected $connection = 'hub';
    protected $table = 'agri_imbalan';
    public $timestamps = false;

    protected $fillable = ['judul', 'keterangan', 'biaya', 'stok', 'aktif', 'dibuat'];
}

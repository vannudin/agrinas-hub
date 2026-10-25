<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu baris = satu pengguna menandai satu modul pelatihan selesai. */
class LmsProgres extends Model
{
    protected $connection = 'hub';
    protected $table = 'lms_progres';
    public $timestamps = false;

    protected $fillable = ['user_id', 'lms_id', 'selesai_pada'];
}

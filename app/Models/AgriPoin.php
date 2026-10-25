<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Mutasi StarPoint; saldo = SUM(jumlah) sehingga selalu bisa ditelusuri. */
class AgriPoin extends Model
{
    protected $connection = 'hub';
    protected $table = 'agri_poin';
    public $timestamps = false;

    protected $fillable = ['user_id', 'jumlah', 'hal', 'dibuat'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Gerai/outlet satu koperasi (salinan `/cooperative-outlets/by-cooperative-id`). */
class KoperasiGerai extends Model
{
    protected $connection = 'hub';
    protected $table = 'koperasi_gerai';

    protected $fillable = ['koperasi_id', 'outlet_simkopdes_id', 'nama', 'jenis',
        'alamat', 'telepon', 'status', 'mentah'];

    protected $casts = ['mentah' => 'array'];
}

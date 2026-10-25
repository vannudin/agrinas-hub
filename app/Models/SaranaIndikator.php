<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Katalog indikator sarana prasarana KDKMP (patokan program, bukan milik satu
 * koperasi). Contoh: Sarana Prasarana / AC / "3 Unit 2PK".
 */
class SaranaIndikator extends Model
{
    protected $connection = 'hub';
    protected $table = 'sarana_indikator';

    protected $fillable = ['indikator_simkopdes_id', 'jenis_pengembangan',
        'jenis_konstruksi', 'indikator', 'keterangan'];
}

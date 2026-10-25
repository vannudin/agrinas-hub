<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Riwayat extends Model
{
    protected $connection = 'hub';
    protected $table = 'riwayat';
    public $timestamps = false;

    protected $fillable = ['tugas_id', 'aktor', 'aksi', 'dari', 'ke', 'catatan', 'dibuat'];

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class, 'tugas_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Komentar extends Model
{
    protected $connection = 'hub';
    protected $table = 'komentar';
    public $timestamps = false;

    protected $fillable = ['tugas_id', 'oleh', 'nama', 'isi', 'dibuat'];

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class, 'tugas_id');
    }
}

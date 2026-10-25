<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kiriman extends Model
{
    protected $connection = 'hub';
    protected $table = 'kiriman';
    public $timestamps = false;

    protected $fillable = ['tugas_id', 'nama_asli', 'path', 'sha256', 'ukuran',
                           'catatan', 'oleh', 'dibuat'];

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(Tugas::class, 'tugas_id');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}

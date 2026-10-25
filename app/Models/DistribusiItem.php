<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistribusiItem extends Model
{
    protected $connection = 'hub';
    protected $table = 'distribusi_item';
    public $timestamps = false;

    protected $fillable = ['distribusi_id', 'barang_id', 'jumlah', 'jumlah_diterima'];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}

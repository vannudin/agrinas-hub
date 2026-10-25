<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris pesanan. `nama`, `satuan`, dan `harga` DISALIN dari katalog saat
 * memesan: katalog boleh berubah harga atau ganti nama, invoice lama tidak
 * boleh ikut berubah.
 */
class PesananItem extends Model
{
    protected $connection = 'hub';
    protected $table = 'pesanan_item';
    public $timestamps = false;

    protected $fillable = ['pesanan_id', 'barang_id', 'pasokan_id', 'nama', 'satuan', 'jumlah', 'harga'];

    protected $casts = ['jumlah' => 'integer', 'harga' => 'integer'];

    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class, 'pesanan_id');
    }

    public function subtotal(): int
    {
        return $this->jumlah * $this->harga;
    }
}

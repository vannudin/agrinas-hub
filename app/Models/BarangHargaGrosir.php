<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu jenjang harga grosir untuk sebuah barang.
 *
 * `min_jumlah` = pembelian mulai berapa satuan; `harga` = harga per satuan pada
 * jenjang itu. Barang bisa punya beberapa jenjang (mis. 5 → Rp 12.000, 20 →
 * Rp 11.000). Tidak ada kolom diskon: pengelola menuliskan harga akhirnya
 * langsung, jadi tidak ada perhitungan tersembunyi yang bisa keliru.
 */
class BarangHargaGrosir extends Model
{
    protected $connection = 'hub';
    protected $table = 'barang_harga_grosir';
    public $timestamps = false;

    protected $fillable = ['barang_id', 'min_jumlah', 'harga', 'dibuat'];

    protected $casts = ['barang_id' => 'integer', 'min_jumlah' => 'integer', 'harga' => 'integer'];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }
}

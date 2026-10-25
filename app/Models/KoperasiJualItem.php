<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris barang pada nota POS. `nama`/`satuan`/`harga` DISALIN saat nota
 * dibuat: harga jual bisa berubah besok, sedangkan nota lama harus tetap
 * menunjukkan apa yang benar-benar dibayar pembeli hari itu.
 */
class KoperasiJualItem extends Model
{
    protected $connection = 'hub';
    protected $table = 'koperasi_jual_item';

    protected $fillable = ['koperasi_jual_id', 'koperasi_id', 'koperasi_barang_id',
        'nama', 'satuan', 'harga', 'jumlah', 'subtotal'];

    protected $casts = ['harga' => 'integer', 'jumlah' => 'integer', 'subtotal' => 'integer'];
}

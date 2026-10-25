<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Riwayat penyesuaian stok barang koperasi — menjawab "kenapa stoknya berubah". */
class KoperasiBarangStok extends Model
{
    protected $connection = 'hub';
    protected $table = 'koperasi_barang_stok';
    public $timestamps = false;

    protected $fillable = ['koperasi_barang_id', 'koperasi_id', 'user_id', 'sebelum',
        'sesudah', 'selisih', 'sebab', 'catatan', 'tanggal', 'dibuat'];

    protected $casts = ['sebelum' => 'integer', 'sesudah' => 'integer', 'selisih' => 'integer'];
}

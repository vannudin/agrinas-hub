<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Riwayat pantauan pesanan: catatan perjalanan, permintaan ubah/hapus. */
class PesananTindakan extends Model
{
    public const TINDAKAN = [
        'catatan' => 'Catatan', 'minta' => 'Minta Ubah', 'hapus' => 'Minta Hapus',
        'siap' => 'Siap', 'terima' => 'Diterima',
    ];

    protected $connection = 'hub';
    protected $table = 'pesanan_tindakan';
    public $timestamps = false;

    protected $fillable = ['pesanan_id', 'oleh', 'tindakan', 'pesan', 'lampiran', 'dibuat'];
}

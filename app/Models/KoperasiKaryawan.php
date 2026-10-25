<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Karyawan koperasi — diisi manajer sendiri di /kop/karyawan.
 *
 * Bukan `users`: karyawan (kasir, juru tulis, operator gudang) belum tentu
 * punya akun Hub, dan akun yang tidak ada tidak boleh diciptakan hanya supaya
 * daftar karyawan bisa diisi.
 */
class KoperasiKaryawan extends Model
{
    /** Jabatan yang lazim di KDKMP; nilai lain tetap boleh diketik. */
    public const JABATAN = ['Asisten Manager', 'Pramuniaga', 'Kasir', 'Keamanan', 'Kepala Toko',
        'Kepala Gudang', 'Staff Gudang', 'Tenaga Medis', 'Apoteker', 'Staf Simpan Pinjam'];

    protected $connection = 'hub';
    protected $table = 'koperasi_karyawan';

    protected $fillable = ['koperasi_id', 'nama', 'jabatan', 'telepon', 'tanggal_masuk',
        'aktif', 'catatan'];

    protected $casts = ['aktif' => 'integer', 'tanggal_masuk' => 'date'];

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nota penjualan (POS) milik koperasi — dicatat kasir DI HUB.
 *
 * TIDAK dikirim ke SIMKOPDES: modul POS di sana milik koperasi, jadi menuliskan
 * transaksi ke sana berarti mencatat penjualan atas nama orang lain.
 */
class KoperasiJual extends Model
{
    protected $connection = 'hub';
    protected $table = 'koperasi_jual';
    public $timestamps = false;

    public const LUNAS = 'LUNAS';
    public const HUTANG = 'HUTANG';
    public const BATAL = 'BATAL';

    public const LABEL_STATUS = [self::LUNAS => 'Lunas', self::HUTANG => 'Hutang', self::BATAL => 'Batal'];

    /** Cara bayar — dipakai formulir kasir & laporan. */
    public const BAYAR = ['TUNAI' => 'Tunai', 'TRANSFER' => 'Transfer', 'QRIS' => 'QRIS', 'HUTANG' => 'Hutang (bayar nanti)'];

    protected $fillable = ['koperasi_id', 'kasir_id', 'nomor', 'pembeli', 'kontak', 'bayar',
        'total', 'dibayar', 'kembali', 'catatan', 'status', 'tanggal', 'dibuat'];

    protected $casts = ['total' => 'integer', 'dibayar' => 'integer', 'kembali' => 'integer'];

    public function item(): HasMany
    {
        return $this->hasMany(KoperasiJualItem::class, 'koperasi_jual_id');
    }

    public function labelStatus(): string
    {
        return self::LABEL_STATUS[$this->status] ?? (string) $this->status;
    }
}

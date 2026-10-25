<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Saldo Agrinas per koperasi: modal belanja awal Rp 400.000.000.
 *
 * SALDO TIDAK BISA DITARIK. Karena itu tidak ada kolom "sisa" yang disimpan di
 * sini — sisa selalu dihitung dari pesanan yang sudah dibayar (lihat sisa()),
 * supaya tidak ada jalur yang bisa memindahkan uang keluar tanpa jejak pesanan.
 */
class Saldo extends Model
{
    public const AWAL = 400000000;

    protected $connection = 'hub';
    protected $table = 'saldo';
    public $timestamps = false;

    protected $fillable = ['koperasi_id', 'saldo', 'catatan', 'dibuat', 'diperbarui'];

    public const LABEL_KANAL = [
        'AGRN' => 'Saldo Agrinas',
        'KAS' => 'Kas Koperasi',
    ];

    /** Ambil (buat bila belum ada) saldo koperasi dengan jatah awal. */
    public static function untuk(int $koperasiId): self
    {
        $s = self::where('koperasi_id', $koperasiId)->first();
        if (! $s) {
            $s = self::create([
                'koperasi_id' => $koperasiId, 'saldo' => self::AWAL,
                'catatan' => 'Modal belanja awal — tidak dapat ditarik atau diuangkan.',
                'dibuat' => kini(), 'diperbarui' => kini(),
            ]);
        }

        return $s;
    }

    /**
     * Terpakai = jumlah pesanan berkanal Saldo Agrinas yang sudah dibayar
     * (DIPESAN belum dipotong, DIBATALKAN tidak dihitung).
     */
    public function terpakai(): int
    {
        return (int) Pesanan::query()
            ->where('koperasi_id', $this->koperasi_id)
            ->where('kanal', 'AGRN')
            ->whereIn('status', ['DIBAYAR', 'DIKIRIM', 'DITERIMA'])
            ->sum('total');
    }

    public function sisa(): int
    {
        return max(0, (int) $this->saldo - $this->terpakai());
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice yang diterbitkan VENDOR untuk barang yang ia salurkan ke koperasi,
 * ditagihkan ke Agrinas.
 *
 * Alur tiga status saja: DIAJUKAN (vendor menerbitkan) → DIBAYAR (Agrinas
 * melunasi) atau DITOLAK (disertai catatan). Tahap "disetujui" sengaja tidak
 * ada: yang memutuskan membayar dan yang membayar adalah orang yang sama
 * (`bolehBayar`), jadi status perantara hanya menambah klik tanpa menambah
 * kendali.
 *
 * Tagihan TIDAK mengubah saldo atau kas. Potongan Saldo Agrinas sudah terjadi
 * saat koperasi membayar pesanan (kolom `pesanan.kanal`), sedangkan pembayaran
 * "sendiri" terjadi di luar Hub. Karena itu `kanal` di sini hanya SALINAN dari
 * pesanan asal — catatan cara uang berpindah, bukan perintah pemindahan uang.
 */
class VendorTagihan extends Model
{
    protected $connection = 'hub';
    protected $table = 'vendor_tagihan';
    public $timestamps = false;

    protected $fillable = ['kode', 'vendor_id', 'koperasi_id', 'pesanan_id', 'keterangan',
                           'jumlah', 'kanal', 'status', 'catatan', 'dibuat', 'diperbarui', 'dibayar'];

    protected $casts = ['jumlah' => 'integer'];

    public const STATUS = ['DIAJUKAN', 'DIBAYAR', 'DITOLAK'];

    public const LABEL = ['DIAJUKAN' => 'Menunggu Pembayaran', 'DIBAYAR' => 'Dibayar',
                          'DITOLAK' => 'Ditolak'];

    public const KELAS = ['DIAJUKAN' => 's-DIAJUKAN', 'DIBAYAR' => 's-SELESAI', 'DITOLAK' => 's-DITOLAK'];

    /** Cara koperasi membayar barang: dari Saldo Agrinas, atau kas sendiri. */
    public const KANAL = [
        'SALDO' => 'Saldo Agrinas',
        'SENDIRI' => 'Bayar sendiri (kas koperasi)',
    ];

    /** Peta `pesanan.kanal` → `vendor_tagihan.kanal`. Satu tempat, bukan di controller. */
    public const DARI_PESANAN = ['AGRN' => 'SALDO', 'KAS' => 'SENDIRI'];

    public const PINDAH = [
        'DIAJUKAN' => ['DIBAYAR', 'DITOLAK'],
        'DIBAYAR' => [],
        'DITOLAK' => [],
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class, 'pesanan_id');
    }

    public function boleh(string $ke): bool
    {
        return in_array($ke, self::PINDAH[$this->status] ?? [], true);
    }

    public function labelStatus(): string
    {
        return self::LABEL[$this->status] ?? (string) $this->status;
    }

    public function labelKanal(): string
    {
        return self::KANAL[$this->kanal] ?? (string) $this->kanal;
    }

    /** Kanal tagihan untuk satu pesanan; nilai asing jatuh ke SENDIRI (paling aman). */
    public static function kanalPesanan(?string $kanal): string
    {
        return self::DARI_PESANAN[$kanal] ?? 'SENDIRI';
    }

    /** Nomor tagihan berurutan TV-202610-0001; dari kode terbesar, bukan COUNT. */
    public static function kodeBaru(): string
    {
        $awalan = 'TV-'.now()->format('Ym').'-';
        $akhir = (string) self::query()->where('kode', 'like', $awalan.'%')->max('kode');
        $n = $akhir !== '' ? ((int) substr($akhir, -4)) + 1 : 1;

        return $awalan.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}

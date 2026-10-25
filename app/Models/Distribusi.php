<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Distribusi extends Model
{
    public const STATUS = ['DIAJUKAN', 'DIPROSES', 'DIKIRIM', 'DITERIMA', 'DITOLAK'];
    public const LABEL = ['DIAJUKAN' => 'Menunggu Diproses', 'DIPROSES' => 'Di Proses',
                          'DIKIRIM' => 'Dalam Pengiriman',
                          'DITERIMA' => 'Diterima', 'DITOLAK' => 'Ditolak'];

    /**
     * Perpindahan status yang sah. Dijaga di satu tempat supaya controller
     * tidak pernah menulis status yang mustahil (mis. DITERIMA dari DIAJUKAN).
     */
    public const PINDAH = [
        'DIAJUKAN' => ['DIPROSES', 'DIKIRIM', 'DITOLAK'],
        'DIPROSES' => ['DIKIRIM', 'DITOLAK'],
        'DIKIRIM'  => ['DITERIMA'],
        'DITERIMA' => [],
        'DITOLAK'  => [],
    ];

    protected $connection = 'hub';
    protected $table = 'distribusi';
    public $timestamps = false;

    protected $fillable = ['kode', 'koperasi_id', 'pemohon', 'status', 'catatan',
                           'catatan_agrinas', 'dibuat', 'diperbarui'];

    public function item(): HasMany
    {
        return $this->hasMany(DistribusiItem::class, 'distribusi_id');
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    public function boleh(string $ke): bool
    {
        return in_array($ke, self::PINDAH[$this->status] ?? [], true);
    }

    /**
     * Kapan surat jalan boleh diunduh.
     *
     * Terbit saat status DIKIRIM — termasuk setelah DITERIMA/DITOLAK, karena
     * pengawas (PIC/pusat) memegang wewenang pindah status dan harus tetap bisa
     * mengunduh bukti serah-terima setelah koperasi menerimanya.
     */
    public function bolehUnduh(): bool
    {
        return in_array($this->status, ['DIKIRIM', 'DITERIMA'], true);
    }

    /** Nama orang yang tercatat pada baris tanda tangan surat jalan. */
    public function labelPemohon(?string $nama): string
    {
        return $this->pemohon && $nama ? $nama : ($this->koperasi->nama ?? 'Manajer koperasi');
    }

    public static function label(?string $status): string
    {
        return self::LABEL[$status] ?? (string) $status;
    }

    /**
     * Kode surat jalan berurutan: DJ-20261002-0007
     *
     * Diambil dari kode TERBESAR, bukan COUNT(*): kalau ada baris yang dihapus,
     * COUNT akan menghasilkan nomor yang sudah terpakai dan unique index
     * menolak simpanan barunya.
     */
    public static function kodeBaru(): string
    {
        $awalan = 'DJ-'.now()->format('Ymd').'-';
        $akhir = (string) self::query()->where('kode', 'like', $awalan.'%')->max('kode');
        $n = $akhir !== '' ? ((int) substr($akhir, -4)) + 1 : 1;

        return $awalan.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}

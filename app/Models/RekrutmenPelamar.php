<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pelamar dari luar Hub. Hub hanya menyimpan sampai KEPUTUSAN (diterima /
 * ditolak / tarik diri) — wawancara & kontrak kerja dijalankan HRIS.
 */
class RekrutmenPelamar extends Model
{
    public const STATUS = ['MENUNGGU', 'DITERIMA', 'DITOLAK', 'TARIK_DIRI'];
    public const STATUS_LABEL = [
        'MENUNGGU' => 'Menunggu', 'DITERIMA' => 'Diterima',
        'DITOLAK' => 'Ditolak', 'TARIK_DIRI' => 'Tarik Diri',
    ];

    protected $connection = 'hub';
    protected $table = 'rekrutmen_pelamar';
    public $timestamps = false;

    protected $fillable = [
        'nama', 'email', 'telepon', 'jabatan', 'unit', 'sumber', 'status', 'catatan',
        'dilamar', 'user_id', 'dicatat_oleh', 'diputus_pada', 'diputus_oleh', 'dibuat',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Daftar pelamar untuk ditampilkan, sudah disaring di SQL.
     *
     * WAJIB berupa query — bukan `get()` lalu disaring di view: menyaring
     * setelah LIMIT hanya melihat N baris terbaru, sehingga pelamar lama tak
     * pernah ketemu walau pencarian diketik persis namanya.
     */
    public static function daftar(string $q = '', int $batas = 100, string $status = ''): \Illuminate\Database\Eloquent\Builder
    {
        $b = static::query()->orderByDesc('id');
        if (in_array($status, self::STATUS, true)) {
            $b->where('status', $status);
        }
        $q = trim($q);
        if ($q !== '') {
            $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $b->where(fn ($w) => $w->where('nama', 'like', $pola)
                ->orWhere('email', 'like', $pola)
                ->orWhere('telepon', 'like', $pola)
                ->orWhere('jabatan', 'like', $pola)
                ->orWhere('unit', 'like', $pola));
        }

        return $b->limit($batas);
    }

    public function labelStatus(): string
    {
        return self::STATUS_LABEL[$this->status] ?? (string) $this->status;
    }
}

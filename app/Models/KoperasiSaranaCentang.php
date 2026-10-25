<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban manajer atas satu indikator katalog sarana prasarana program:
 * "sarana ini sudah ada di koperasi saya", keterangan bebas, dan kondisinya
 * (BAIK = lengkap/berfungsi, RUSAK). `kondisi` WAJIB ada di $fillable —
 * tanpa itu updateOrCreate membuang nilainya diam-diam dan semua sarana
 * terbaca lengkap.
 *
 * Hanya dibuat untuk indikator yang DICENTANG (lihat migrasi
 * `koperasi_sarana_centang`). Tidak ada baris = belum tersedia.
 */
class KoperasiSaranaCentang extends Model
{
    protected $connection = 'hub';
    protected $table = 'koperasi_sarana_centang';

    protected $fillable = ['koperasi_id', 'sarana_indikator_id', 'tersedia', 'keterangan', 'kondisi'];

    protected function casts(): array
    {
        return ['tersedia' => 'boolean'];
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(SaranaIndikator::class, 'sarana_indikator_id');
    }
}

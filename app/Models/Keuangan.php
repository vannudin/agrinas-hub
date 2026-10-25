<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu hari pembukuan koperasi. Pemasukan & pengeluaran dalam rupiah bulat,
 * rinciannya teks bebas satu baris (mis. "penjualan pupuk 12 sak").
 */
class Keuangan extends Model
{
    protected $connection = 'hub';
    protected $table = 'keuangan';
    public $timestamps = false;

    protected $fillable = ['koperasi_id', 'tanggal', 'pemasukan', 'pengeluaran',
                           'rincian_pemasukan', 'rincian_pengeluaran', 'catatan',
                           'kategori_pemasukan', 'kategori_pengeluaran',
                           'kanal', 'ref', 'dicatat_oleh', 'dibuat', 'diperbarui'];

    protected $casts = ['pemasukan' => 'integer', 'pengeluaran' => 'integer'];

    /** Selisih hari itu: positif = surplus, negatif = defisit. */
    public function selisih(): int
    {
        return $this->pemasukan - $this->pengeluaran;
    }

    /** Label periode untuk tampilan (dipakai view & CSV). */
    public static function labelPeriode(string $periode): string
    {
        return \App\Http\Controllers\KeuanganController::PERIODE[$periode] ?? 'Bulanan';
    }

    /** Rupiah tanpa desimal: "Rp 1.250.000". */
    public static function rupiah(int|float|null $n): string
    {
        return 'Rp '.number_format((float) ($n ?? 0), 0, ',', '.');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan persetujuan digital — dipakai modul **Approval**: permohonan BIAYA
 * OPERASIONAL gerai koperasi (listrik, air, service AC, perjalanan dinas,
 * rembes pembelian, …) yang diputuskan admin pusat / petugas keuangan.
 */
class Persetujuan extends Model
{
    public const STATUS = ['DIAJUKAN', 'SETUJU', 'TOLAK'];

    public const LABEL = ['DIAJUKAN' => 'Menunggu', 'SETUJU' => 'Disetujui', 'TOLAK' => 'Ditolak'];

    /**
     * Kategori biaya operasional gerai. SATU daftar: dipakai pilihan formulir,
     * saringan daftar, dan pengelompokan rekap di /keuangan — supaya kategori
     * yang bisa diajukan tidak pernah berbeda dengan yang bisa disaring.
     */
    public const BIAYA = [
        'Listrik', 'Air', 'Telepon & Internet', 'Service AC', 'Service Kendaraan',
        'Bahan Bakar', 'Perjalanan Dinas', 'Rembes Pembelian', 'Sewa Tempat',
        'Kebersihan & Keamanan', 'Alat Tulis & Cetak', 'Lainnya',
    ];

    protected $connection = 'hub';
    protected $table = 'persetujuan';
    public $timestamps = false;

    protected $fillable = ['koperasi_id', 'judul', 'jenis', 'nominal', 'tanggal', 'isi',
        'pemohon_id', 'pemutus_id', 'status', 'catatan', 'dibuat', 'diputus'];

    public function pemohon()
    {
        return $this->belongsTo(User::class, 'pemohon_id');
    }

    public function pemutus()
    {
        return $this->belongsTo(User::class, 'pemutus_id');
    }

    public function koperasi()
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    public function label_status(): string
    {
        return self::LABEL[$this->status] ?? $this->status;
    }

    /** Nominal dalam rupiah; '-' bila belum diisi (pengajuan lama). */
    public function rupiah(): string
    {
        return $this->nominal === null || (int) $this->nominal === 0
            ? '—' : Keuangan::rupiah((int) $this->nominal);
    }
}

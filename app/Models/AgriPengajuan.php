<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan AgriConnect: Perjalanan Dinas, Klaim Imbalan, dan pengajuan umum.
 *
 * Satu bentuk, tiga jenis — tiga tabel berkolom identik hanya menambah tiga
 * tempat untuk bug yang sama. Kolom `nilai` = biaya perjalanan (rupiah) atau
 * poin yang diklaim, bergantung `jenis`. Statusnya memakai tiga kata yang sama
 * dengan modul lain di hub ini: Diajukan / Disetujui / Ditolak.
 */
class AgriPengajuan extends Model
{
    protected $connection = 'hub';
    protected $table = 'agri_pengajuan';
    public $timestamps = false;

    public const JENIS = [
        'PERJALANAN' => 'Perjalanan Dinas',
        'IMBALAN' => 'Klaim Imbalan',
        'UMUM' => 'Administrasi Umum',
    ];

    public const STATUS = ['DIAJUKAN' => 'Menunggu', 'SETUJU' => 'Disetujui', 'TOLAK' => 'Ditolak'];

    protected $fillable = ['user_id', 'koperasi_id', 'jenis', 'judul', 'keterangan', 'nilai',
        'tanggal', 'tanggal_akhir', 'status', 'catatan', 'pemutus_id', 'dibuat', 'diputus'];

    public function pemohon()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function koperasi()
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    public function label_jenis(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }

    public function label_status(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** Lambang kelas lencana: selaras dengan lencana status modul lain. */
    public function lencana(): string
    {
        return match ($this->status) {
            'SETUJU' => 'SELESAI',
            'TOLAK' => 'REVISI',
            default => 'DIAJUKAN',
        };
    }

    /** Satuan `nilai` berbeda per jenis — rupiah untuk perjalanan, poin untuk imbalan. */
    public function nilai_teks(): string
    {
        return $this->jenis === 'IMBALAN'
            ? number_format((int) $this->nilai, 0, ',', '.').' poin'
            : 'Rp'.number_format((int) $this->nilai, 0, ',', '.');
    }
}

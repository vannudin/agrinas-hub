<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Status operasional satu koperasi.
 *
 * Satu baris per koperasi. `operasional` DINILAI MANAJER lewat /kop (sejak
 * tarikan SIMKOPDES diputus 2026-10-25); SIMKOPDES tidak punya kolom "sudah
 * operasional", dan skor otomatis lama tidak lagi dihitung. `alasan` menyimpan
 * catatan manajer atas status yang dipilihnya.
 */
class KoperasiSarana extends Model
{
    protected $connection = 'hub';
    protected $table = 'koperasi_sarana';

    public const SUDAH = 'SUDAH';
    public const SEBAGIAN = 'SEBAGIAN';
    public const BELUM = 'BELUM';

    public const LABEL = [
        self::SUDAH => 'Sudah Operasional',
        self::SEBAGIAN => 'Operasional Sebagian',
        self::BELUM => 'Belum Operasional',
    ];

    /** Warna lencana mengikuti kelas yang sudah ada di hub.css (s-…). */
    public const KELAS = [self::SUDAH => 's-DISETUJUI', self::SEBAGIAN => 's-DIAJUKAN', self::BELUM => 's-DITOLAK'];

    protected $fillable = ['koperasi_id', 'koperasi_simkopdes_id', 'legal_stage',
        'legal_stage_sequence', 'operasional', 'skor_operasional', 'alasan', 'ditarik'];

    public function labelOperasional(): string
    {
        return self::LABEL[$this->operasional] ?? (string) $this->operasional;
    }

    public function kelasOperasional(): string
    {
        return self::KELAS[$this->operasional] ?? '';
    }
}

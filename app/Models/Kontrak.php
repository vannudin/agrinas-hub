<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu penempatan pendamping pada satu periode, lengkap dengan
 * kontraknya. Isinya salinan dari SIMKOPDES (ditarik saat pendamping login),
 * sehingga admin pusat & PIC bisa memeriksanya tanpa akun SIMKOPDES siapa pun.
 *
 * ponytail: tarik-saat-login, bukan pengintai latar — data kontrak berubah
 * beberapa kali setahun, jadi satu tarikan sehari sudah lebih dari cukup.
 */
class Kontrak extends Model
{
    protected $connection = 'hub';
    protected $table = 'kontrak';
    public $timestamps = false;

    protected $fillable = ['user_id', 'periode_id',
        'periode_mulai', 'periode_selesai', 'periode_status', 'periode_program',
        'penempatan_koperasi_id', 'penempatan_nama', 'penempatan_provinsi',
        'penempatan_wilayah', 'penempatan_jenis', 'penempatan_mulai', 'penempatan_selesai',
        'kontrak_id', 'kontrak_nomor', 'kontrak_komitmen', 'kontrak_jenis',
        'kontrak_status', 'kontrak_label', 'kontrak_mulai', 'kontrak_selesai',
        'kontrak_ttd', 'kontrak_sumber', 'kontrak_tautan',
        'sumber', 'ditarik_pada', 'dibuat', 'diperbarui'];

    /** Label Indonesia untuk status kontrak SIMKOPDES (yang aslinya Inggris). */
    public static function statusLabel(string $status, string $label = ''): string
    {
        return match (mb_strtoupper($status)) {
            'SIGNED' => 'Ditandatangani',
            'DRAFT' => 'Draf',
            'EXPIRED' => 'Berakhir',
            'REJECTED' => 'Ditolak',
            'WAITING' => 'Menunggu tanda tangan',
            '' => $label !== '' ? $label : 'Belum ada',
            default => $label !== '' ? $label : $status,
        };
    }

    /**
     * Kelas lencana warna — memakai gaya status yang SUDAH ada di hub.css,
     * jadi tidak ada warna baru yang perlu ditambahkan.
     */
    public static function statusKelas(string $status): string
    {
        return match (mb_strtoupper($status)) {
            'SIGNED' => 's-DISETUJUI',
            'DRAFT', 'WAITING' => 's-DIKERJAKAN',
            'REJECTED' => 's-DITOLAK',
            default => 's-BARU',
        };
    }
}

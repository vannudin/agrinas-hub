<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Salinan lokal satu modul pelatihan dari LMS APN. */
class LmsMateri extends Model
{
    protected $connection = 'hub';
    protected $table = 'lms_materi';
    public $timestamps = false;

    protected $fillable = ['lms_id', 'path_id', 'path_judul', 'judul', 'slug', 'ringkas',
                           'konten', 'menit', 'posisi', 'disunting_oleh', 'dibuat', 'diperbarui'];

    /** Modul pelatihan yang sudah dibaca pengguna ini (untuk progres). */
    public static function selesaiUntuk(int $userId): array
    {
        return LmsProgres::query()->where('user_id', $userId)->pluck('lms_id')->all();
    }

    /** Ringkasan progres: [selesai, total, persen] dari seluruh modul. */
    public static function ringkasanProgres(int $userId): array
    {
        $total = self::query()->count();
        $selesai = LmsProgres::query()->where('user_id', $userId)->count();

        return ['selesai' => $selesai, 'total' => $total,
                'persen' => $total ? (int) round($selesai / $total * 100) : 0];
    }
}

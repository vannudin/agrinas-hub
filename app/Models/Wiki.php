<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Halaman wiki berhierarki (induk_id). Satu tabel, bukan dua. */
class Wiki extends Model
{
    protected $connection = 'hub';
    protected $table = 'wiki';
    public $timestamps = false;

    protected $fillable = ['induk_id', 'koperasi_id', 'judul', 'isi', 'dibuat_oleh', 'dibuat', 'diperbarui'];

    public function anak()
    {
        return $this->hasMany(Wiki::class, 'induk_id');
    }

    public function induk()
    {
        return $this->belongsTo(Wiki::class, 'induk_id');
    }

    /** Koperasi pemilik halaman (null = berlaku umum). */
    public function koperasi()
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    /** Kedalaman maksimum 3 tingkat supaya tidak berputar (siklus induk). */
    public static function boleh_induk(int $id, ?int $calon): bool
    {
        $naik = 0;
        $kini = $calon;
        while ($kini && $naik < 4) {
            if ((int) $kini === $id) {
                return false;
            }
            $kini = self::whereKey($kini)->value('induk_id');
            $naik++;
        }

        return $naik < 4;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Keanggotaan ruang obrolan: siapa saja yang boleh membuka ruang ini. */
class AnggotaObrolan extends Model
{
    protected $connection = 'hub';
    protected $table = 'obrolan_anggota';
    public $timestamps = false;

    protected $fillable = ['ruang_id', 'user_id', 'dibuat'];

    /** Kunci percakapan pribadi: dua id yang diurutkan, jadi A-B == B-A. */
    public static function kunciPribadi(int $a, int $b): string
    {
        return $a < $b ? $a.':'.$b : $b.':'.$a;
    }
}

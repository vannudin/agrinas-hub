<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    /**
     * Peran vendor di rantai pasok komoditas bersubsidi — diambil dari
     * penjabaran mitra BUMN Agrinas, bukan karangan.
     */
    public const JENIS = [
        'BUMN'      => 'BUMN',
        'PRODUSEN'  => 'Produsen',
        'PENYALUR'  => 'Penyalur',
        'MITRA'     => 'Mitra Penyalur',
        'PENGELOLA' => 'Pengelola & Pengadaan',
        'LAIN'      => 'Lain-lain',
    ];

    protected $connection = 'hub';
    protected $table = 'vendor';
    public $timestamps = false;

    protected $fillable = ['kode', 'nama', 'jenis', 'komoditas', 'kontak', 'catatan',
                           'dibuat', 'diperbarui'];

    /** Kode berurutan VND-0006; diambil dari kode terbesar supaya hapus baris tak bikin tabrakan. */
    public static function kodeBaru(): string
    {
        $akhir = (string) self::query()->where('kode', 'like', 'VND-%')->max('kode');
        $n = $akhir !== '' ? ((int) substr($akhir, -4)) + 1 : 1;

        return 'VND-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function label(?string $jenis): string
    {
        return self::JENIS[$jenis] ?? (string) $jenis;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu suara manajer pada pemungutan "Manager Memilih Sistem".
 *
 * Dua pilihan saja (bukan tiga): tetap memakai tiga sistem yang berjalan
 * sekarang, atau pindah ke Agrinas Hub sebagai sistem terpusat. Opsi hibrida
 * sengaja tidak ada — pertanyaannya "sistem mana yang dipakai ke depan".
 *
 * Tabel `vote_suara` menyimpan satu baris per (topik, user_id); memilih ulang
 * menimpa baris lama, jadi hasil agregat tidak pernah menghitung orang dua kali.
 */
class VoteSuara extends Model
{
    protected $connection = 'hub';
    protected $table = 'vote_suara';

    /** Waktu ditulis sebagai teks WIB oleh controller (`kini()`). */
    public $timestamps = false;

    public const TOPIK = 'sistem-manajer';

    public const PILIHAN = [
        1 => 'Tetap menggunakan SIMKOPDES, Lark, dan AgriConnect',
        2 => 'Gunakan Agrinas Hub sebagai sistem terpusat',
    ];

    /**
     * Ambang suara yang disepakati: di bawah angka ini (atau bila manajer
     * menolak), Agrinas Hub dihapus dan tidak digunakan. Ditampilkan sebagai
     * catatan di halaman /vote — satu sumber angka, bukan tiga tempat.
     */
    public const MIN_SUARA = 15000;

    protected $fillable = ['topik', 'user_id', 'pilihan', 'catatan', 'dibuat', 'diperbarui'];
}

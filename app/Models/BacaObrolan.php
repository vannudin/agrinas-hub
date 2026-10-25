<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Posisi baca + sematan percakapan milik satu pengguna.
 *
 * `pesan_id` = id pesan terakhir yang sudah terlihat; jumlah yang belum dibaca
 * dihitung dari selisihnya (bukan kolom penghitung sendiri, yang bisa melenceng
 * kalau ada dua penulis).
 *
 * `dibaca_pada` = kapan terakhir dibuka — inilah dasar centang biru ala
 * WhatsApp: "sudah dibaca" hanya benar kalau lawan bicara MEMBUKA ruangnya.
 */
class BacaObrolan extends Model
{
    protected $connection = 'hub';
    protected $table = 'obrolan_baca';
    public $timestamps = false;

    protected $fillable = ['ruang_id', 'user_id', 'pesan_id', 'dibaca_pada', 'disematkan'];

    /** Disematkan di puncak daftar percakapan? */
    public function tersemat(): bool
    {
        return (string) $this->disematkan !== '';
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(RuangObrolan::class, 'ruang_id');
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

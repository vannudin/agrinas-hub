<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reaksi emoji pada satu pesan (Lark: "React to messages with emojis"). */
class ReaksiObrolan extends Model
{
    protected $connection = 'hub';
    protected $table = 'obrolan_reaksi';
    public $timestamps = false;

    protected $fillable = ['pesan_id', 'user_id', 'emoji', 'dibuat'];

    /** Emoji yang ditawarkan di tombol reaksi. Pendek saja: satu baris di ponsel. */
    public const PILIHAN = ['👍', '❤️', '😄', '😮', '😢', '🙏', '✅', '🔥'];

    public function pesan(): BelongsTo
    {
        return $this->belongsTo(PesanObrolan::class, 'pesan_id');
    }
}

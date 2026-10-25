<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aspirasi manajer ke pengelola (agrinas / HR / PIC): masukan atau keluhan.
 *
 * Arah balik dari `broadcast`: pengumuman turun (pengelola → manajer), aspirasi
 * naik (manajer → pengelola). Ditanggapi dengan satu kolom `tanggapan`, bukan
 * percakapan berantai — percakapan panjang sudah punya Ruang Kerja.
 */
class Aspirasi extends Model
{
    protected $connection = 'hub';
    protected $table = 'aspirasi';

    public const JENIS = ['MASUKAN' => 'Masukan', 'KELUHAN' => 'Keluhan'];

    public const STATUS = ['BARU' => 'Baru', 'DIBACA' => 'Dibaca', 'SELESAI' => 'Selesai'];

    /** Warna lencana per status — dipakai daftar pengelola & daftar pengirim. */
    public const WARNA_STATUS = ['BARU' => 'samar', 'DIBACA' => 'biru', 'SELESAI' => 'hijau'];

    protected $fillable = [
        'user_id', 'jenis', 'perihal', 'pesan', 'koperasi',
        'status', 'tanggapan', 'penanggap', 'ditanggapi', 'dibuat',
    ];

    protected function casts(): array
    {
        return ['ditanggapi' => 'datetime'];
    }

    public function pengirim()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? (string) $this->jenis;
    }

    public function labelStatus(): string
    {
        return self::STATUS[$this->status] ?? (string) $this->status;
    }

    public function warnaStatus(): string
    {
        return self::WARNA_STATUS[$this->status] ?? 'samar';
    }
}

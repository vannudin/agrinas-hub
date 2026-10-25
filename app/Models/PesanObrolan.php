<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris pesan. Kolomnya melayani dua keluarga fitur sekaligus:
 *
 *   Lark    : balas (balas_id), sebut (sebut), sematkan (disematkan),
 *             tarik (dihapus), sunting (disunting), terjadwal (kirim_pada)
 *   WhatsApp: lampiran foto/berkas/suara (jenis + lampiran*)
 */
class PesanObrolan extends Model
{
    protected $connection = 'hub';
    protected $table = 'obrolan_pesan';
    public $timestamps = false;

    protected $fillable = ['ruang_id', 'oleh', 'nama', 'isi', 'terjemahan', 'bahasa', 'dibuat',
        'balas_id', 'jenis', 'lampiran', 'lampiran_nama', 'lampiran_ukuran', 'lampiran_jenis',
        'sebut', 'disematkan', 'disunting', 'dihapus', 'kirim_pada'];

    /** Jenis lampiran yang dikenali; menentukan cara ia digambar. */
    public const JENIS = ['teks' => 'Teks', 'gambar' => 'Gambar', 'berkas' => 'Berkas', 'suara' => 'Pesan suara'];

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(RuangObrolan::class, 'ruang_id');
    }

    public function reaksi(): HasMany
    {
        return $this->hasMany(ReaksiObrolan::class, 'pesan_id');
    }

    /** Sudah ditarik pengirimnya? Isi sengaja masih tersimpan di baris, tapi
     *  tidak pernah dikirim ke peramban — lihat WorkspaceController::petakanPesan(). */
    public function ditarik(): bool
    {
        return (string) $this->dihapus !== '';
    }

    /** Tampil sekarang? Pesan terjadwal baru muncul setelah waktunya lewat. */
    public function tampil(): bool
    {
        return (string) $this->kirim_pada === '' || strcmp((string) $this->kirim_pada, kini()) <= 0;
    }

    /** Potongan teks untuk daftar percakapan (lampiran tak punya teks). */
    public function cuplikan(int $maks = 60): string
    {
        return match ((string) $this->jenis) {
            'gambar' => '📷 Foto',
            'suara' => '🎤 Pesan suara',
            'berkas' => '📎 '.($this->lampiran_nama !== '' ? $this->lampiran_nama : 'Berkas'),
            default => \Illuminate\Support\Str::limit(trim((string) $this->isi), $maks),
        };
    }
}

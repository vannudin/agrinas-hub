<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Permintaan/izin akses satu baris per (dokumen, pengguna). Kelas ini DIPAKAI
 * ULANG untuk tiga hal supaya tidak ada tabel & alur baru tiap kali:
 *
 *   permintaan calon penyunting (status='diminta', oleh pemohon_id)
 *   izin yang diberikan        (status='diberi',  oleh pemutus_id)
 *   izin yang dicabut          (status='ditolak', dipakai sebagai penanda "sudah
 *                               diputus" supaya barisnya tidak dibuat ulang terus)
 *
 * Konsekuensi praktisnya: `dokumen_akses` yang AKTIF = baris ber-status 'diberi'.
 * Untuk memeriksa izin, jangan bandingkan keberadaan baris — bandingkan statusnya.
 */
class DokumenAkses extends Model
{
    public const STATUS = ['diminta' => 'Minta izin', 'diberi' => 'Diberi', 'ditolak' => 'Ditolak'];

    protected $connection = 'hub';
    protected $table = 'dokumen_akses';
    public $timestamps = false;

    protected $fillable = ['dokumen_id', 'pengguna_id', 'pemohon_id', 'pemutus_id',
                           'status', 'pesan', 'diminta', 'diputus'];

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'pengguna_id');
    }

    public function dokumen()
    {
        return $this->belongsTo(Dokumen::class, 'dokumen_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan kerja pribadi: SATU baris = satu catatan, milik SATU pengguna.
 *
 * Dulu satu baris per (akun, tanggal) dengan dua kolom tetap (isi & kendala).
 * Sekarang banyak baris per hari supaya catatan bisa diberi JUDUL, dikelompokkan
 * dengan JENIS, dan diubah satu per satu — itu yang membuatnya terasa seperti
 * aplikasi catatan, bukan formulir laporan harian.
 *
 * Kepemilikan (`user_id`) adalah SATU-SATUNYA penentu siapa boleh membaca:
 * tidak ada lingkup koperasi/wilayah di sini. Catatan ini pribadi — pengelola
 * pusat pun tidak membacanya.
 */
class CatatanHarian extends Model
{
    /**
     * Jenis catatan. Nilainya dipakai di controller, view, dan gate — didefinisikan
     * di sini supaya daftarnya tidak bisa menyimpang antar berkas.
     */
    public const JENIS = [
        'kegiatan' => 'Kegiatan',
        'kendala' => 'Kendala',
        'rencana' => 'Rencana',
        'ide' => 'Ide',
        'penting' => 'Penting',
    ];

    /** Kata kunci pencarian cepat: label yang sering dipakai, sekali klik terisi. */
    public const SARAN_JUDUL = ['Kunjungan lapangan', 'Rapat internal', 'Kendala stok',
                                'Rencana besok', 'Laporan mingguan', 'Tindak lanjut'];

    protected $connection = 'hub';
    protected $table = 'catatan_harian';
    public $timestamps = false;

    protected $fillable = ['user_id', 'koperasi_id', 'tanggal', 'judul', 'jenis', 'isi', 'kendala',
                           'dibuat', 'diperbarui'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function label_jenis(): string
    {
        return self::JENIS[$this->jenis] ?? (string) $this->jenis;
    }
}

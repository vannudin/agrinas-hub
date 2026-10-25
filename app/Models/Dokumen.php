<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Berkas untuk editor ONLYOFFICE. `berkas` = path di disk local (storage/app/private),
 * `kunci` = document key ONLYOFFICE yang wajib berubah tiap isi dokumen berubah.
 *
 * Enum jenis & akses SENGAJA berupa konstanta di kelas ini, mengikuti pola
 * `Tugas::SASARAN` / `Keuangan::PERIODE`: daftar yang sama dipakai di controller,
 * model, view, dan gate — satu sumber, jadi mustahil menyimpang.
 */
class Dokumen extends Model
{
    /**
     * Jenis berkas yang bisa dibuat. `pdf` TIDAK disunting ONLYOFFICE (tidak ada
     * editor PDF) — berkasnya dibuat kosong lalu diunduh, dan dinaikkan versinya
     * dengan cara berkas PDF baru diterima dari pengguna.
     */
    public const JENIS = [
        'docx' => 'Dokumen (Word)',
        'xlsx' => 'Lembar Sebar (Excel)',
        'pptx' => 'Presentasi (PowerPoint)',
        'pdf' => 'PDF',
    ];

    /** Jenis yang bisa dibuka editor ONLYOFFICE. */
    public const JENIS_SUNTING = ['docx', 'xlsx', 'pptx'];

    public const LABEL_JENIS = ['docx' => 'Word', 'xlsx' => 'Excel', 'pptx' => 'PowerPoint', 'pdf' => 'PDF'];

    /**
     * Tingkat akses berkas — inilah yang menentukan siapa boleh membukanya:
     *
     *   pribadi  hanya pemilik (pemilik_id). Dulu kolom `pribadi`.
     *   koperasi pemilik + pengelola koperasi dokumen (perilaku lama dokumen
     *            non-pribadi yang menempel ke satu koperasi).
     *   publik   SIAPA PUN yang punya akun boleh MEMBUKA (lihat), tetapi TIDAK
     *            boleh menyunting (edit=false di ONLYOFFICE).
     *   tamu     sama dengan publik untuk melihat, DAN boleh menyunting —
     *            inilah "akses semua orang" yang bisa diberikan lewat tombol
     *            Bagikan. Dinaikkan satu tingkat dari publik, bukan langsung
     *            dari pribadi.
     */
    public const AKSES = [
        'pribadi' => 'Pribadi — hanya saya',
        'koperasi' => 'Koperasi — pengelola koperasi ini',
        'publik' => 'Publik — semua pengguna bisa melihat',
        'tamu' => 'Publik + izin sunting — semua pengguna bisa menyunting',
    ];

    /** Akses yang berarti "terbuka untuk semua pengguna". */
    public const AKSES_TERBUKA = ['publik', 'tamu'];

    protected $connection = 'hub';
    protected $table = 'dokumen';
    public $timestamps = false;

    protected $fillable = ['koperasi_id', 'judul', 'jenis', 'berkas', 'kunci', 'dibuat_oleh',
                           'pribadi', 'pemilik_id', 'akses', 'dibuat', 'diperbarui'];

    public function koperasi()
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function pemilik()
    {
        return $this->belongsTo(User::class, 'pemilik_id');
    }

    public function izin()
    {
        return $this->hasMany(DokumenAkses::class, 'dokumen_id');
    }

    public function label_jenis(): string
    {
        return self::LABEL_JENIS[$this->jenis] ?? $this->jenis;
    }

    public function label_akses(): string
    {
        return self::AKSES[$this->akses] ?? (string) $this->akses;
    }

    /** Terbuka untuk semua pengguna (boleh dilihat siapa pun)? */
    public function terbuka(): bool
    {
        return in_array($this->akses, self::AKSES_TERBUKA, true);
    }

    /** Bisa dibuka ONLYOFFICE? PDF tidak punya editor di sini. */
    public function bisaDisunting(): bool
    {
        return in_array($this->jenis, self::JENIS_SUNTING, true);
    }

    /** Kunci baru: dipakai pembuat dokumen dan setiap kali ONLYOFFICE menyimpan. */
    public function kunci_baru(): string
    {
        return bin2hex(random_bytes(8));
    }
}

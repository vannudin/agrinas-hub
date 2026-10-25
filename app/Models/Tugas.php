<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Wilayah;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tugas extends Model
{
    protected $connection = 'hub';
    protected $table = 'tugas';
    public $timestamps = false;

    /**
     * Sasaran penerima tugas, disimpan di kolom `sasaran` (kolom sendiri).
     *
     * Kolom `jenis` SENGAJA TIDAK dipakai ulang: ternyata ia sudah berisi
     * kategori nyata ('DOKUMEN', 'LAPORAN') yang ditampilkan sebagai "Jenis" di
     * daftar tugas — memakainya sebagai sasaran akan menghapus informasi yang
     * sudah ada. 'KOPERASI' = satu koperasi (koperasi_id terisi); sisanya
     * dipetakan lewat Wilayah::saring(), termasuk 'JABODETABEK' yang sudah
     * berdiri sendiri di sana.
     */
    public const SASARAN = [
        'KOPERASI' => 'Koperasi tertentu',
        'SEMUA' => 'Semua koperasi terdaftar',
        'JABODETABEK' => 'Jabodetabek',
        'PROVINSI' => 'Provinsi tertentu',
        'KABUPATEN' => 'Kabupaten/kota tertentu',
        'KECAMATAN' => 'Kecamatan tertentu',
    ];

    /** Sasaran yang butuh satu koperasi tertentu (bukan lingkup wilayah). */
    public const SASARAN_SATU = 'KOPERASI';

    /**
     * Constraint koperasi penerima tugas ini.
     *
     * Dipakai sebagai `where` pada query Koperasi — SATU tempat, jadi perhitungan
     * "tugas mana yang terlihat oleh koperasi mana" mustahil menyimpang antar
     * pemanggil. 'SEMUA' sengaja mengembalikan kondisi selalu-benar alih-alih
     * `whereIn(id, semua)` supaya querynya tetap satu bentuk.
     */
    public function saringPenerima(): \Closure
    {
        if ($this->sasaran === self::SASARAN_SATU) {
            return fn ($q) => $q->where('koperasi.id', (int) $this->koperasi_id);
        }

        if ($this->sasaran === 'SEMUA') {
            return fn ($q) => $q->whereRaw('1 = 1');
        }

        if ($this->sasaran === 'PROVINSI') {
            return fn ($q) => Wilayah::saring((string) $this->sasaran_wilayah)($q);
        }

        // KABUPATEN/KECAMATAN: cocokkan pada kolom `wilayah`. Pilihannya
        // diturunkan dari data yang ADA (lihat WorkspaceController::sasaranPilihan),
        // bukan dari daftar 514 kabupaten yang harus dipelihara.
        // ponytail: cocok berbasis teks, jadi kabupaten bernama sama di dua
        // provinsi akan terkena keduanya. Naikkan ke pencocokan berkode wilayah
        // kalau nama kembar itu benar-benar muncul.
        return function ($q) {
            $kunci = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $this->sasaran_wilayah).'%';
            $q->where('koperasi.wilayah', 'like', $kunci);
        };
    }

    /** Label sasaran untuk ditampilkan di daftar. */
    public function sasaranLabel(): string
    {
        if ($this->sasaran === self::SASARAN_SATU) {
            return $this->koperasi?->nama ?? 'Satu koperasi';
        }

        return self::SASARAN[$this->sasaran] ?? (string) $this->sasaran;
    }

    protected $fillable = ['koperasi_id', 'judul', 'deskripsi', 'jenis', 'prioritas',
                           'deadline', 'status', 'dibuat_oleh', 'catatan_agrinas',
                           'sasaran_wilayah', 'dibuat', 'diperbarui', 'induk_id',
                           'petugas_id', 'petugas_peran', 'petugas_wilayah',
                           'sasaran', 'sasaran_wilayah'];

    /** Urutan maju alur kerja; REVISI kembali ke DIAJUKAN, DITOLAK berhenti. */
    public const ALUR = ['BARU', 'DIKERJAKAN', 'DIAJUKAN', 'SELESAI'];

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** Pelaksana tugas bila ditunjuk langsung (NULL = manajer koperasinya). */
    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    /** Tugas ini untuk pengguna itu? Ditunjuk langsung ATAU koperasinya sendiri. */
    public function untuk(User $u): bool
    {
        if ((int) $this->petugas_id === (int) $u->id && $this->petugas_id) {
            return true;
        }

        return Koperasi::whereKey($this->koperasi_id)->where('manajer_id', $u->id)->exists();
    }

    public function kiriman(): HasMany
    {
        return $this->hasMany(Kiriman::class, 'tugas_id')->orderBy('id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(Riwayat::class, 'tugas_id')->orderBy('id');
    }

    public function komentar(): HasMany
    {
        return $this->hasMany(Komentar::class, 'tugas_id')->orderBy('id');
    }

    /** Status tujuan yang sah dari status sekarang. */
    public function langkahBerikut(): array
    {
        $i = array_search($this->status, self::ALUR, true);
        $maju = $i === false ? [] : array_slice(self::ALUR, $i + 1);

        return match ($this->status) {
            'BARU'       => ['DIKERJAKAN'],
            'DIKERJAKAN' => ['DIAJUKAN'],
            'DIAJUKAN'   => ['SELESAI', 'REVISI', 'DITOLAK'],
            'REVISI'     => ['DIAJUKAN', 'DITOLAK'],
            'DITOLAK'    => [],
            'SELESAI'    => [],
            default      => [],
        };
    }
}

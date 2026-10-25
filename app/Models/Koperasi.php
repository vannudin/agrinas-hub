<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Koperasi extends Model
{
    protected $connection = 'hub';
    protected $table = 'koperasi';
    public $timestamps = false;

    /** Label status untuk tampilan. Diambil dari model, bukan ditulis ulang di
     *  blade: dasbor dulu menampilkan "Aktif" hanya bila statusnya persis AKTIF
     *  dan membocorkan kode mentah untuk status lain. */
    public const LABEL_STATUS = ['AKTIF' => 'Aktif', 'NONAKTIF' => 'Nonaktif',
                                 'BINAAN' => 'Binaan', 'BARU' => 'Baru'];

    protected $fillable = ['kode', 'nama', 'wilayah', 'provinsi', 'alamat', 'no_rekening',
                           'manajer_id', 'status', 'versi', 'diperbarui',
                           'koperasi_simkopdes_id', 'pemetaan_lahan_id'];

    protected function casts(): array
    {
        return ['pemetaan_lahan_id' => 'integer'];
    }

    public function manajer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manajer_id');
    }

    /**
     * Titik pemetaan lahan yang dipilih manajer — SUMBER identitas koperasi
     * (nama desa, wilayah, provinsi, titik peta, proses pembangunan).
     *
     * Dibaca langsung, bukan disalin: `koperasi.wilayah` hanya salinan saat
     * disimpan, sedangkan pemetaan lahan bisa diperbarui di sumbernya. Halaman
     * yang menjawab "koperasi ini di mana & sudah sejauh apa" membaca dari sini.
     */
    public function titikLahan(): BelongsTo
    {
        return $this->belongsTo(PemetaanLahan::class, 'pemetaan_lahan_id');
    }

    /**
     * Baris koperasi untuk satu koperasi SIMKOPDES — yang sudah ada, atau baru
     * (belum disimpan) bila belum pernah tercatat.
     *
     * Kunci pencarian = `koperasi_simkopdes_id`, BUKAN `kode`. `kode` hanya kode
     * wilayah KABUPATEN, sedangkan kolomnya UNIQUE — satu kabupaten bisa memuat
     * banyak koperasi. Memakai `kode` sebagai kunci membuat koperasi KEDUA di
     * kabupaten yang sama dianggap milik koperasi lain, lalu pendampingnya
     * ditolak pautannya (`return 0`): login berhasil tetapi semua halaman kosong
     * ("Akun Anda belum ditautkan ke koperasi"). Terukur: Kab. Bekasi (32.16)
     * sudah dipegang koperasi 37038, sehingga pendamping koperasi 42562 di
     * kabupaten yang sama tidak pernah tertaut.
     *
     * `$kodeKab` = kode wilayah kabupaten (boleh kosong). Baris lama yang belum
     * punya `koperasi_simkopdes_id` diadopsi lewat kode itu; baris yang sudah
     * menunjuk koperasi LAIN tidak pernah diambil alih — koperasi itu diberi
     * kode berkembar `kode-id` supaya kolom UNIQUE-nya tetap sah.
     */
    public static function untukSumber(int $idSim, string $kodeKab): self
    {
        if ($idSim > 0) {
            $ada = static::query()->where('koperasi_simkopdes_id', $idSim)->first();
            if ($ada !== null) {
                return $ada;
            }
        }

        $kode = $kodeKab !== '' ? $kodeKab : 'SIMP-'.$idSim;
        $lama = static::query()->where('kode', $kode)->first();

        if ($lama !== null && ($lama->koperasi_simkopdes_id === null
                || (int) $lama->koperasi_simkopdes_id === $idSim)) {
            if ($idSim > 0 && $lama->koperasi_simkopdes_id === null) {
                $lama->forceFill(['koperasi_simkopdes_id' => $idSim])->save();
            }

            return $lama;
        }

        if ($lama !== null) {
            $kode .= '-'.$idSim;
            $bentrok = static::query()->where('kode', $kode)->first();
            if ($bentrok !== null) {
                return $bentrok;
            }
        }

        return new static(['kode' => $kode, 'status' => 'AKTIF', 'versi' => 1,
            'koperasi_simkopdes_id' => $idSim > 0 ? $idSim : null]);
    }

    public function tugas(): HasMany
    {
        return $this->hasMany(Tugas::class, 'koperasi_id');
    }
}

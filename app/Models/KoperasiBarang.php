<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Barang MILIK satu koperasi (inventaris koperasi), bukan katalog gudang Agrinas.
 *
 * Dipakai manajer untuk mengelola daftar barang yang benar-benar ada di koperasinya
 * (termasuk yang dibeli sendiri, bukan hanya kiriman Agrinas) dan memperbarui
 * stoknya harian. Admin Agrinas & PIC hanya MEMBACA daftar ini.
 */
class KoperasiBarang extends Model
{
    protected $connection = 'hub';
    protected $table = 'koperasi_barang';
    public $timestamps = false;

    /** Kategori yang dipakai di Hub — sengaja pendek supaya tidak jadi administrasi. */
    public const KATEGORI = [
        'SEMBAKO' => 'Sembako',
        'PERTANIAN' => 'Pertanian & Pupuk',
        'ALAT' => 'Alat & Perlengkapan',
        'KONSUMSI' => 'Makanan & Minuman',
        'LAINNYA' => 'Lainnya',
    ];

    /**
     * Satuan = daftar BERSAMA dari Barang, huruf kecil (tampilan koperasi memakai
     * huruf kecil sejak awal: "42 sak"). Satu sumber, jadi satuan baru tidak perlu
     * ditambahkan dua kali — dulu daftar di sini hanya 9 nilai dan berbeda dari
     * daftar pasokan/toko, sehingga PCS tidak ada padahal disebut "kaya pcs aja
     * ga ada".
     */
    public static function satuan(): array
    {
        return array_map('mb_strtolower', Barang::SATUAN);
    }

    /** Sebab penyesuaian stok — dipakai untuk riwayat yang bisa dibaca manusia. */
    public const SEBAB = [
        'HITUNG' => 'Hasil hitung ulang',
        'MASUK' => 'Barang masuk',
        'KELUAR' => 'Barang keluar / terjual',
        'RUSAK' => 'Rusak / susut',
    ];

    protected $fillable = ['koperasi_id', 'kode', 'barcode', 'nama', 'satuan', 'kategori',
        'stok', 'stok_min', 'harga', 'catatan', 'oleh_id', 'dibuat', 'diperbarui',
        'promo_jenis', 'promo_nilai', 'promo_min', 'promo_bonus_id'];

    protected $casts = ['stok' => 'integer', 'stok_min' => 'integer', 'harga' => 'integer',
        'promo_nilai' => 'integer', 'promo_min' => 'integer', 'promo_bonus_id' => 'integer'];

    /** Jenis promo yang bisa dipasang manajer. '' = tidak ada promo. */
    public const PROMO = [
        '' => 'Tanpa promo',
        'DISKON' => 'Diskon (%)',
        'GRATIS' => 'Bonus barang gratis',
    ];

    /**
     * Ambang jumlah beli agar promo berlaku. `promo_min` 0 berarti promo jalan
     * sejak 1 unit — bukan berarti promo mati.
     */
    public function promoMulai(): int
    {
        return max(1, (int) $this->promo_min);
    }

    /**
     * Potongan rupiah untuk pembelian `$jumlah` unit (0 bila tidak berlaku).
     *
     * Ini SATU-SATUNYA rumus diskon di server; halaman kasir menyalinnya di
     * hub-kop-pos.js untuk angka yang dibaca kasir, sedangkan nota tetap
     * dihitung ulang dari sini.
     */
    public function diskonUntuk(int $jumlah): int
    {
        if ($this->promo_jenis !== 'DISKON' || $jumlah < $this->promoMulai()) {
            return 0;
        }
        $persen = min(100, max(0, (int) $this->promo_nilai));

        return (int) round((int) $this->harga * $jumlah * $persen / 100);
    }

    /** Barang bonus yang diberikan (null bila bukan promo GRATIS / belum berlaku). */
    public function bonusUntuk(int $jumlah): ?array
    {
        if ($this->promo_jenis !== 'GRATIS' || $jumlah < $this->promoMulai()
            || (int) $this->promo_bonus_id <= 0 || (int) $this->promo_nilai <= 0) {
            return null;
        }

        return ['id' => (int) $this->promo_bonus_id, 'jumlah' => (int) $this->promo_nilai];
    }

    /** Label pendek untuk layar kasir & daftar barang. */
    public function labelPromo(): string
    {
        return match ($this->promo_jenis) {
            'DISKON' => 'Diskon '.min(100, max(0, (int) $this->promo_nilai)).'%'
                .((int) $this->promo_min > 1 ? ' min '.$this->promo_min.' ' : ''),
            'GRATIS' => 'Bonus '.$this->promo_nilai.' '
                .((int) $this->promo_min > 1 ? 'tiap beli '.$this->promo_min.' ' : ''),
            default => '',
        };
    }

    /** Peringatan stok menipis: hanya bila ambangnya memang diisi. */
    public function menipis(): bool
    {
        return $this->stok_min > 0 && $this->stok <= $this->stok_min;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pesanan barang dari katalog, dibayar dengan Saldo Agrinas atau kas koperasi. */
class Pesanan extends Model
{
    public const STATUS = ['DIPESAN', 'DIBAYAR', 'DIKIRIM', 'DITERIMA', 'DIBATALKAN'];

    /** Opsi penyerahan: dikirim ke koperasi, atau diambil sendiri di gudang. */
    public const ANTAR = ['KIRIM' => 'Dikirim ke koperasi', 'AMBIL' => 'Diambil di gudang'];

    /** Status sesuai opsi penyerahan + nomor surat jalan Agrinas. */
    public const LABEL = [
        'DIPESAN' => 'Menunggu Pembayaran', 'DIBAYAR' => 'Dibayar',
        'DIKIRIM' => 'Dikirim', 'DIAMBIL' => 'Siap Diambil', 'DITERIMA' => 'Diterima',
        'DIBATALKAN' => 'Dibatalkan',
    ];

    protected $connection = 'hub';
    protected $table = 'pesanan';
    public $timestamps = false;

    protected $fillable = ['kode', 'invoice', 'koperasi_id', 'penjual_id', 'pemesan', 'total', 'kanal',
                           'status', 'catatan', 'tanggal', 'dibayar', 'dikirim', 'diterima',
                           'antar', 'surat_jalan', 'diambil', 'dibuat', 'diperbarui'];

    protected $casts = ['total' => 'integer'];

    public function item(): HasMany
    {
        return $this->hasMany(PesananItem::class, 'pesanan_id');
    }

    public function koperasi()
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    public function pemesan()
    {
        return $this->belongsTo(User::class, 'pemesan');
    }

    public function label_status(): string
    {
        return self::LABEL[$this->status] ?? (string) $this->status;
    }

    public function label_antar(): string
    {
        return self::ANTAR[$this->antar] ?? (string) $this->antar;
    }

    /**
     * Nomor surat jalan Agrinas: SJ-YYYYMM-0001, terbit OTOMATIS saat pengelola
     * melepas barang (kirim atau siap diambil). Nomor inilah yang ditandatangani
     * manajer saat barang diterima — buktinya barang benar-benar sampai.
     */
    public static function suratJalanBaru(): string
    {
        $awalan = 'SJ-'.now()->format('Ym').'-';
        $akhir = (string) self::query()->where('surat_jalan', 'like', $awalan.'%')->max('surat_jalan');
        $n = $akhir !== '' ? ((int) substr($akhir, -4)) + 1 : 1;

        return $awalan.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public function tindakan()
    {
        return $this->hasMany(PesananTindakan::class, 'pesanan_id')->orderBy('id');
    }

    public static function kodeBaru(): string
    {
        $akhir = (string) self::query()->where('kode', 'like', 'PS-%')->max('kode');
        $n = $akhir !== '' ? ((int) substr($akhir, -4)) + 1 : 1;

        return 'PS-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** INV-YYYYMM-0001 — nomor invoice per bulan, gampang dirujuk manusia. */
    public static function invoiceBaru(): string
    {
        $awalan = 'INV-'.now()->format('Ym').'-';
        $akhir = (string) self::query()->where('invoice', 'like', $awalan.'%')->max('invoice');
        $n = $akhir !== '' ? ((int) substr($akhir, -4)) + 1 : 1;

        return $awalan.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}

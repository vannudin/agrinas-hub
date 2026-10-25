<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
/**
 * Pengajuan & pembayaran satu pasokan UMKM.
 *
 * Alur: DIAJUKAN (manajer koperasi, menunjuk rekening tujuan + melampirkan
 * berkas invoice bila ada) → DISETUJUI / DITOLAK (admin Agrinas atau PIC
 * wilayahnya) → DIBAYAR (admin Agrinas atau PIC, mengunggah bukti transfer).
 *
 * INVOICE diterbitkan SISTEM (`INV/<kode pasokan>`), bukan diketik pengaju:
 * yang dibayar Agrinas adalah satu pasokan yang sudah tercatat, jadi nomor
 * tagihannya harus bisa dilacak balik ke pasokan itu. Kolom
 * `invoice_nama/simpan/...` di bawah menyimpan BERKAS LAMPIRAN dari pengaju
 * bila ada, dan di layar selalu disebut "Lampiran" supaya tidak tertukar
 * dengan invoice terbitan Agrinas.
 *
 * Uang keluar dari kas Agrinas, jadi yang MEMUTUS dan MEMBAYAR bukan orang yang
 * mengajukan; dan setiap keputusan membuat baris BARU supaya riwayat penolakan
 * sebelumnya tidak hilang.
 */
class PasokanBayar extends Model
{
    protected $connection = 'hub';
    protected $table = 'pasokan_bayar';
    public $timestamps = false;

    public const STATUS = ['DIAJUKAN', 'DISETUJUI', 'DITOLAK', 'DIBAYAR'];

    public const LABEL = [
        'DIAJUKAN' => 'Diajukan',
        'DISETUJUI' => 'Disetujui',
        'DITOLAK' => 'Ditolak',
        'DIBAYAR' => 'Dibayar',
    ];

    /** Kelas lencana memakai warna status yang sudah ada di CSS Hub. */
    public const KELAS = [
        'DIAJUKAN' => 's-DIAJUKAN', 'DISETUJUI' => 's-DISETUJUI',
        'DITOLAK' => 's-DITOLAK', 'DIBAYAR' => 's-SELESAI',
    ];

    /**
     * Perpindahan yang sah. DITOLAK & DIBAYAR adalah ujung; DITOLAK bisa dibuka
     * lagi kalau koperasi memperbaiki pengajuannya. DIBAYAR hanya lewat jalur
     * `bayar()` yang mewajibkan bukti.
     */
    public const PINDAH = [
        'DIAJUKAN' => ['DISETUJUI', 'DITOLAK'],
        'DISETUJUI' => ['DIBAYAR', 'DITOLAK'],
        'DITOLAK' => ['DIAJUKAN'],
        'DIBAYAR' => [],
    ];

    /** Bank yang lazim dipakai UMKM/koperasi — pilihan cepat, bukan pembatasan. */
    public const BANK = ['BRI', 'Mandiri', 'BNI', 'BCA', 'BSI', 'BJB', 'Bank Jateng',
        'Bank Jatim', 'Bank Nagari', 'BPD lain', 'Bank Syariah lain', 'Lainnya'];

    protected $fillable = ['pasokan_id', 'koperasi_id', 'bank', 'no_rekening', 'atas_nama',
        'invoice_nomor', 'invoice_tanggal', 'invoice_nama', 'invoice_simpan', 'invoice_ukuran',
        'invoice_mime', 'bukti_nama', 'bukti_simpan', 'bukti_ukuran', 'bukti_mime', 'bukti_catatan',
        'status', 'catatan', 'diajukan_oleh', 'diputus_oleh', 'dibayar_oleh',
        'diajukan_pada', 'diputus_pada', 'dibayar_pada', 'dibuat'];

    protected $casts = ['invoice_ukuran' => 'integer', 'bukti_ukuran' => 'integer'];

    public function pasokan()
    {
        return $this->belongsTo(Pasokan::class, 'pasokan_id');
    }

    public function boleh(string $ke): bool
    {
        return in_array($ke, self::PINDAH[$this->status] ?? [], true);
    }

    public static function label(?string $s): string
    {
        return self::LABEL[$s] ?? (string) $s;
    }

    /**
     * Nomor invoice yang AKAN diterbitkan untuk pasokan berikutnya.
     *
     * Dipakai formulir pengajuan supaya nomor yang terlihat pengaju sama persis
     * dengan yang nanti tersimpan — dihitung dari kode pasokan berikutnya
     * (`Pasokan::kodeBaru()`), jadi ikut rumus yang sama, bukan disusun ulang.
     *
     * Sisa kecil yang diketahui: dua pengajuan yang benar-benar bersamaan bisa
     * melihat nomor yang sama; yang tersimpan tetap dua kode berbeda karena
     * kodenya diambil database saat simpan. Kolomnya hanya TAMPILAN di formulir.
     * ponytail: satu permintaan kode; kalau perlu nomor unik saat tampil,
     * terbitkan di dalam transaksi.
     */
    public static function invoiceBaru(): string
    {
        return 'INV/'.Pasokan::kodeBaru();
    }

    public function kelas(): string
    {
        return self::KELAS[$this->status] ?? 's-BARU';
    }

    /** Rekening lengkap? Tanpa ini pembayaran tidak mungkin dieksekusi. */
    public function rekeningLengkap(): bool
    {
        return trim((string) $this->no_rekening) !== '' && trim((string) $this->atas_nama) !== '';
    }

    public function adaInvoice(): bool
    {
        return (string) $this->invoice_simpan !== '';
    }

    public function adaBukti(): bool
    {
        return (string) $this->bukti_simpan !== '';
    }
}

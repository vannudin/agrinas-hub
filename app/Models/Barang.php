<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    /**
     * Satuan barang. Dipakai BERSAMA oleh katalog gudang, pasokan UMKM, dan toko —
     * jadi daftar ini yang jadi acuan, bukan salinan per modul.
     *
     * Dulu hanya 7 nilai (SAK/KARUNG/KG/TON/LITER/DUS/UNIT) sehingga satuan sehari-hari
     * koperasi seperti PCS, IKAT, dan BOTOL tidak bisa dipilih sama sekali — padahal
     * gula, telur, sayur, dan minuman dijual dalam satuan itu. Ditambah di sini,
     * bukan di satu formulir, supaya semua modul ikut lengkap sekaligus.
     */
    public const SATUAN = [
        // satuan hitung
        'PCS', 'UNIT', 'BUAH', 'BIJI', 'SET', 'PACK',
        // berat
        'KG', 'ONS', 'GRAM', 'TON', 'KUINTAL',
        // volume
        'LITER', 'ML', 'GALON',
        // kemasan
        'SAK', 'KARUNG', 'DUS', 'BOTOL', 'KALENG', 'SACHET', 'BUNGKUS', 'IKAT', 'RENCENG', 'TABUNG',
        // panjang & luas
        'METER', 'ROLL',
    ];
    public const KATEGORI = ['PANGAN' => 'Pangan', 'BENIH' => 'Benih', 'PUPUK' => 'Pupuk',
                             'ENERGI' => 'Energi', 'ALAT' => 'Alat & Mesin', 'LAIN' => 'Lain-lain'];

    /**
     * Saringan JENIS di etalase (/toko) — BUKAN kategori gudang di atas.
     *
     * Dua hal berbeda: `KATEGORI` menjawab "barang ini apa" (pangan, pupuk,
     * alat) untuk pengelola gudang; `JENIS_TOKO` menjawab "bagi pembeli ini
     * barang apa" (subsidi, lokal, non-subsidi) untuk penyaring etalase.
     * Menyatukan keduanya mustahil: barang lokal tidak punya kategori gudang,
     * dan satu kategori gudang (Pangan) berisi barang subsidi DAN komersial.
     *
     * 'LOKAL' bukan nilai kolom mana pun — ia menandai baris dari tabel
     * `pasokan`, digabungkan ke etalase oleh TokoController.
     */
    public const JENIS_TOKO = [
        'SUBSIDI' => 'Produk Subsidi',
        'LOKAL' => 'Produk Lokal & UMKM',
        'NONSUBSIDI' => 'Produk Non Subsidi',
    ];

    /**
     * Asal barang. Agrinas tidak berbelanja untuk dirinya sendiri — ia yang
     * menyediakan barang, dan sumbernya harus terbaca: gudang sendiri, vendor,
     * atau pengadaan dari koperasi (gabungan hasil petani).
     */
    public const SUMBER = ['GUDANG' => 'Gudang Agrinas', 'VENDOR' => 'Vendor',
        'KOPERASI' => 'Pengadaan koperasi'];

    protected $connection = 'hub';
    protected $table = 'barang';
    public $timestamps = false;

    protected $fillable = ['kode', 'nama', 'satuan', 'kategori', 'stok', 'stok_min',
                           'harga', 'gambar', 'catatan', 'vendor_id', 'sumber', 'aktif',
                           'subsidi', 'dibuat', 'diperbarui'];

    protected $casts = ['stok' => 'integer', 'stok_min' => 'integer', 'harga' => 'integer',
                        'subsidi' => 'integer'];

    /** Harga jual dalam rupiah; 0 = belum diberi harga (belum bisa dibeli). */
    public function harga_rupiah(): string
    {
        return 'Rp '.number_format((int) $this->harga, 0, ',', '.');
    }

    /** Inisial untuk gambar cadangan bila katalog belum punya foto. */
    public function inisial(): string
    {
        $kata = preg_split('/\s+/', trim((string) $this->nama)) ?: [];

        return mb_strtoupper(mb_substr($kata[0] ?? 'B', 0, 1).mb_substr($kata[1] ?? '', 0, 1));
    }

    /** Asal barang, siap ditampilkan di katalog & kelola. */
    public function label_sumber(): string
    {
        return self::SUMBER[$this->sumber] ?? (string) $this->sumber;
    }

    /** Penyedia barang ini (boleh kosong untuk katalog lama). */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /** Jenjang harga grosir, termurah lebih dulu. */
    public function hargaGrosir(): HasMany
    {
        return $this->hasMany(BarangHargaGrosir::class, 'barang_id')->orderBy('min_jumlah');
    }

    /**
     * Harga per satuan untuk sejumlah `$jumlah` — memperhitungkan jenjang grosir.
     *
     * Jenjang berlaku bila `jumlah >= min_jumlah`; yang terpakai adalah jenjang
     * TERBESAR yang terpenuhi (paling murah), bukan yang pertama ditemukan.
     */
    public function hargaUntuk(int $jumlah): int
    {
        // Jenjang terpakai = yang AMBANGNYA TERBESAR di antara yang terpenuhi.
        //
        // BUKAN `max()` atas harganya: jenjang 5+ Rp 11.900 dan 50+ Rp 10.600
        // keduanya terpenuhi pada jumlah 50, dan max() akan memilih Rp 11.900 —
        // pembeli 50 satuan ditagih harga jenjang 5. Ambangnya yang dibandingkan,
        // bukan harganya.
        $ambangTerpakai = 0;
        $harga = (int) $this->harga;
        foreach ($this->hargaGrosir as $g) {
            $min = (int) $g->min_jumlah;
            if ($jumlah >= $min && $min > $ambangTerpakai) {
                $ambangTerpakai = $min;
                $harga = (int) $g->harga;
            }
        }

        return $harga;
    }

    /** Satuan minimum agar harga grosir berlaku; 0 bila tidak ada jenjang. */
    public function grosirMulai(): int
    {
        $paling = $this->hargaGrosir->min('min_jumlah');

        return $paling ? (int) $paling : 0;
    }

    /**
     * Alamat gambar yang pasti bisa dimuat.
     *
     * Kolom `gambar` boleh berisi URL penuh ATAU nama berkas lokal
     * (`beras-medium.jpg` hasil `scripts/foto_katalog.py`). Nilai contoh yang
     * jelas tidak nyata (`https://contoh.id/...`) sengaja TIDAK dikembalikan
     * supaya katalog tidak menampilkan gambar rusak.
     */
    public function gambarUrl(): string
    {
        $g = trim((string) $this->gambar);
        if ($g === '') {
            return '';
        }
        // URL penuh: pakai apa adanya, kecuali domain contoh yang tidak pernah nyata.
        if (preg_match('~^https?://~i', $g)) {
            return preg_match('~^https?://(www\.)?contoh\.id/~i', $g) ? '' : $g;
        }
        if (! preg_match('~^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp)$~i', $g)) {
            return '';
        }
        // Berkas lokal dari scripts/foto_katalog.py; hanya kalau benar-benar ada.
        return is_file(public_path('img/katalog/'.$g)) ? asset('img/katalog/'.$g) : '';
    }
}

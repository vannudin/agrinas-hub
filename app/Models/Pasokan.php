<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pasokan dari UMKM lokal ke koperasi (di luar distribusi Agrinas). */
class Pasokan extends Model
{
    // TIGA status. "Diterima" sengaja tidak ada: penerimaan barang oleh koperasi
    // adalah urusan distribusi Agrinas, sedangkan pasokan UMKM berhenti pada
    // keputusan pengelola — Disetujui (hijau) berarti pasokan itu diterima.
    // `DIBAYAR` ditambahkan bersama alur pengajuan pembayaran: pasokan UMKM kini
    // punya ujung yang jelas (uang keluar + bukti transfer), bukan berhenti di
    // "Disetujui" tanpa jejak bayar.
    public const STATUS = ['DIAJUKAN', 'DISETUJUI', 'DIBAYAR', 'DITOLAK'];

    public const LABEL = ['DIAJUKAN' => 'Diajukan', 'DISETUJUI' => 'Disetujui',
                          'DIBAYAR' => 'Dibayar', 'DITOLAK' => 'Ditolak'];

    /**
     * Jenis produk lokal. Satu kolom di `pasokan`, bukan tabel baru: yang
     * membedakan hanya dari mana barang itu datang dan siapa yang mengurusnya,
     * sedangkan alur Catat -> Ajukan -> Disetujui -> Dibayar persis sama.
     *
     * 'UMKM' tetap menjadi nilai bawaan supaya 78 gate lama (yang mengirim
     * formulir tanpa `jenis`) tetap melewati validasi apa adanya.
     */
    public const JENIS = ['PERTANIAN' => 'Pertanian', 'PETERNAKAN' => 'Peternakan', 'UMKM' => 'UMKM'];

    /**
     * Komoditas lazim per jenis — dipakai sebagai <datalist> di formulir supaya
     * pengguna tidak mengetik dari nol. Teks BEBAS tetap diterima: daftar ini
     * contoh, bukan pembatas.
     */
    public const KOMODITAS = [
        'PERTANIAN' => ['Gabah Kering', 'Jagung Pipil', 'Kedelai', 'Kacang Tanah', 'Ubi Kayu',
            'Padi IR64', 'Cabai Merah', 'Bawang Merah', 'Tomat', 'Kentang', 'Kubis', 'Wortel',
            'Kangkung', 'Bayam', 'Kacang Panjang', 'Terong', 'Mangga', 'Pisang', 'Jeruk', 'Semangka'],
        'PETERNAKAN' => ['Ayam Broiler', 'Ayam Kampung', 'Telur Ayam', 'Sapi Potong', 'Kambing',
            'Domba', 'Babi', 'Ikan Lele', 'Ikan Nila', 'Ikan Mas', 'Udang', 'Bandeng', 'Telur Bebek'],
        'UMKM' => ['Keripik', 'Kopi Bubuk', 'Gula Aren', 'Kecap', 'Sambal Kemasan', 'Mie Kering',
            'Tahu', 'Tempe', 'Batik', 'Anyaman Bambu', 'Sapu', 'Minyak Kelapa', 'Kerajinan Kayu'],
    ];

    /** Label jenis untuk tampilan; nilai asing ditampilkan apa adanya. */
    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? (string) $this->jenis;
    }

    /**
     * Harga per satuan yang ditawarkan ke koperasi lain.
     *
     * Bila manajer belum mengisi harga jual, yang dipakai adalah harga belinya
     * dari UMKM — angka yang pasti diketahui, dan lebih baik daripada 0 yang
     * membuat produk mustahil dipesan.
     */
    public function hargaTawaran(): int
    {
        return (int) ($this->harga_jual > 0 ? $this->harga_jual : $this->harga_satuan);
    }

    /**
     * Sisa yang masih bisa dipesan koperasi lain dari tayangan ini.
     *
     * Dikurangi pesanan antar-koperasi yang belum dibatalkan. Tanpa ini dua
     * koperasi bisa memesan barang yang sama melebihi stok nyata. Koperasi
     * penjual sendiri tidak memesan produknya, jadi pesanan kanal AGRN/KAS
     * (dari gudang Agrinas) tidak pernah menyentuh `pasokan_id`.
     *
     * ponytail: dihitung saat dibaca; kalau tayangan banyak, ganti jadi kolom
     * `terjual` yang diperbarui di transaksi checkout.
     */
    public function sisaTayang(): int
    {
        $dipesan = (int) \App\Models\PesananItem::query()
            ->join('pesanan', 'pesanan.id', '=', 'pesanan_item.pesanan_id')
            ->where('pesanan_item.pasokan_id', $this->id)
            ->where('pesanan.status', '!=', 'DIBATALKAN')
            ->sum('pesanan_item.jumlah');

        return max(0, (int) $this->jumlah - $dipesan);
    }

    /**
     * Perpindahan status yang sah bagi pengelola (pusat/PIC), satu tempat —
     * dipakai view (untuk mengisi pilihan) DAN controller (untuk memvalidasi),
     * jadi keduanya mustahil menyimpang.
     *
     * Sengaja memuat jalan KOREKSI, bukan hanya maju: tanpa itu sebuah pasokan
     * yang sudah Disetujui/Diterima tidak bisa disentuh lagi sama sekali —
     * pengelola kehilangan kemampuan membetulkan data yang salah, dan halaman
     * berubah jadi buntu. Setiap koreksi tercatat di audit.
     */
    public const PINDAH = [
        'DIAJUKAN'  => ['DISETUJUI', 'DITOLAK'],
        'DISETUJUI' => ['DIAJUKAN', 'DITOLAK'],   // kembalikan ke antrean · batalkan
        'DITOLAK'   => ['DIAJUKAN', 'DISETUJUI'], // buka lagi · setujui
    ];

    /**
     * Urutan baris dalam SQL, diturunkan dari STATUS — bukan CASE yang ditulis
     * ulang. Dulu urutannya ditulis tangan di controller ("ditolak terakhir"),
     * sementara kartu status memakai STATUS: dua daftar untuk satu urutan yang
     * sama persis, dan itulah jenis duplikat yang cepat atau lambat menyimpang.
     */
    public static function urutSql(): string
    {
        $kasus = [];
        foreach (self::STATUS as $i => $s) {
            $kasus[] = "WHEN '".$s."' THEN ".$i;
        }

        return 'CASE pasokan.status '.implode(' ', $kasus).' ELSE 99 END';
    }

    /**
     * Semua nilai status yang dikenal — dipakai ATURAN VALIDASI.
     *
     * Sebelumnya controller menulis daftar sendiri sementara peta di atas
     * menawarkan lebih banyak nilai. Dua daftar terpisah itu menyimpang, dan
     * akibatnya server menolak pilihan yang ditampilkan sendiri oleh halaman
     * ("The selected status is invalid"). Daftar ini turunan dari STATUS, jadi
     * tak mungkin berbeda lagi.
     */
    public const SEMUA = self::STATUS;

    protected $connection = 'hub';
    protected $table = 'pasokan';
    public $timestamps = false;

    protected $fillable = ['kode', 'koperasi_id', 'jenis', 'umkm_nama', 'umkm_kontak', 'umkm_alamat',
                           'komoditas', 'jumlah', 'satuan', 'harga_satuan', 'harga_jual', 'tayang',
                           'tanggal', 'status',
                           'catatan', 'catatan_agrinas', 'pemohon', 'dibuat', 'diperbarui'];

    protected $casts = ['jumlah' => 'integer', 'harga_satuan' => 'integer',
                        'harga_jual' => 'integer', 'tayang' => 'integer'];

    public function koperasi()
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    /** Nilai transaksi = jumlah x harga satuan. */
    public function nilai(): int
    {
        return $this->jumlah * $this->harga_satuan;
    }

    /** Apakah pengelola boleh memindahkan status ini ke $ke? */
    public function boleh(string $ke): bool
    {
        return in_array($ke, self::PINDAH[$this->status] ?? [], true);
    }

    public static function label(?string $s): string
    {
        return self::LABEL[$s] ?? (string) $s;
    }

    /** Kode berurutan PSK-0001 dari kode terbesar supaya penghapusan tak bikin tabrakan. */
    public static function kodeBaru(): string
    {
        return 'PSK-'.self::urutBaru();
    }

    /**
     * Nomor invoice yang DITERBITKAN SISTEM — bukan diketik pengaju.
     *
     * Sempat nomor invoice diisi pengaju, dan itu salah dua kali: nomor yang ia
     * karang tidak ada hubungannya dengan dokumen apa pun (yang tersimpan
     * memang berkas buatannya sendiri, bukan terbitan Agrinas), dan berkas wajib
     * membuat satu pasokan tidak bisa dicatat sebelum UMKM mengirim gambar
     * invoice. Sekarang: pengaju hanya memberi rekening tujuan, berkas invoice
     * boleh dilampirkan bila ada, dan Agrinas MENGOGAHKAN invoice resminya
     * untuk keperluan pembayaran.
     *
     * Bentuk: INV/PSK-0178 — diturunkan dari kode pasokan, jadi satu pasokan
     * mustahil punya dua nomor dan nomornya bisa dilacak balik dalam sekali
     * lihat, tanpa kolom penghitung sendiri yang bisa menyimpang.
     */
    public function nomorInvoice(): string
    {
        return 'INV/'.$this->kode;
    }

    /** Urutan dokumen: dihitung dari database, sehingga dua pemanggilan berurutan aman. */
    private static function urutBaru(): string
    {
        $akhir = (string) self::query()->where('kode', 'like', 'PSK-%')->max('kode');
        $n = $akhir !== '' ? ((int) substr($akhir, -4)) + 1 : 1;

        return str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}

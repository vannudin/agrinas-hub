<?php

namespace App\Models;

/** Satu tempat untuk daftar nilai yang sah — dipakai validasi, filter, dan tampilan. */
final class Domain
{
    public const PERAN_AGRINAS = 'AGRINAS';
    public const PERAN_PIC = 'PIC';
    public const PERAN_MANAJER = 'MANAJER';
    /** Petugas kepegawaian: satu-satunya peran non-pusat yang boleh membuka meja SDM. */
    public const PERAN_HR = 'HR';
    /**
     * Petugas keuangan: mengurus pembayaran (produk/pesanan, pasokan UMKM,
     * operasional) tanpa memegang kendali pusat maupun gudang.
     *
     * Peran ini TIDAK ikut `PERAN_HRD`: ia tidak membuka meja SDM, dan tidak
     * boleh melepas/membatalkan barang — uang masuk-keluar, bukan stok.
     */
    public const PERAN_KEUANGAN = 'KEUANGAN';
    /**
     * Peran PENGAWASAN: TNI (Babinsa/Kodim) dan Dinas Koperasi.
     *
     * Keduanya HANYA MEMBACA — tidak punya satu pun pekerjaan tulis di Hub,
     * kecuali menyumbang materi pembelajaran (lihat PERAN_MATERI). Pagar
     * tulisnya satu tempat: middleware `pantau` menolak semua POST/PUT/DELETE.
     */
    public const PERAN_TNI = 'TNI';
    public const PERAN_DINAS = 'DINAS';
    public const PERAN_PANTAU = [self::PERAN_TNI, self::PERAN_DINAS];
    /**
     * Akun VENDOR: mitra penyedia komoditas (BULOG, Pupuk Indonesia, Pertamina,
     * ID FOOD) yang menyalurkan barang ke koperasi dan menagih Agrinas.
     *
     * Vendor BUKAN pengelola: ia tidak membuka dasbor, koperasi, akun, gudang,
     * atau keuangan. Satu-satunya halaman kerjanya `/mitra` (pesanan barangnya
     * sendiri + tagihan). Penaut ke baris `vendor` ada di `users.vendor_id`.
     */
    public const PERAN_VENDOR = 'VENDOR';
    /** Peran yang boleh mengunggah materi/juknis (hapus tetap milik pusat). */
    public const PERAN_MATERI = [self::PERAN_AGRINAS, self::PERAN_TNI, self::PERAN_DINAS];
    /** Peran yang boleh membuka meja SDM & Kepegawaian (`/admin/hr`). */
    public const PERAN_HRD = [self::PERAN_AGRINAS, self::PERAN_HR];

    /** Label peran untuk layar. */
    public const LABEL_PERAN = [
        'AGRINAS' => 'Admin Pusat', 'HR' => 'Petugas HR', 'KEUANGAN' => 'Keuangan',
        'PIC' => 'PIC Wilayah', 'MANAJER' => 'Manajer KDKMP',
        'TNI' => 'TNI Pengawasan', 'DINAS' => 'Dinas Koperasi Pengawasan',
        'VENDOR' => 'Vendor Penyalur',
    ];

    public const STATUS = ['BARU', 'DIKERJAKAN', 'DIAJUKAN', 'REVISI', 'DITOLAK', 'SELESAI'];

    /**
     * Kategori kas untuk Buku Kas. Satu kategori memasuki total pemasukan hari
     * itu, satu untuk total pengeluaran — sesuai bentuk tabel `keuangan`
     * (satu baris = satu hari, memuat total masuk & total keluar).
     *
     * Kuncinya dipakai di DB, labelnya di layar. Urutan sengaja dari yang paling
     * sering dipakai gerai supaya pilihan pertama langsung terpakai.
     */
    public const KATEGORI_PEMASUKAN = [
        'PENJUALAN' => 'Penjualan barang',
        'JASA' => 'Jasa & layanan',
        'SIMPANAN' => 'Simpanan anggota',
        'MODAL' => 'Setoran modal',
        'BANTUAN' => 'Bantuan & hibah',
        'LAIN_MASUK' => 'Lain-lain masuk',
    ];

    public const KATEGORI_PENGELUARAN = [
        'PEMBELIAN' => 'Pembelian barang',
        'OPERASIONAL' => 'Operasional gerai',
        'GAJI' => 'Gaji & upah',
        'TRANSPORT' => 'Transport & perjalanan',
        'LISTRIK' => 'Listrik, air, internet',
        'SEWA' => 'Sewa & retribusi',
        'LAIN_KELUAR' => 'Lain-lain keluar',
    ];

    /** Label kategori; nilai tak dikenal dikembalikan apa adanya ('' = tanpa kategori). */
    public static function labelKategori(string $jenis, ?string $kode): string
    {
        $kode = (string) $kode;
        if ($kode === '') {
            return '';
        }

        return ($jenis === 'masuk' ? self::KATEGORI_PEMASUKAN : self::KATEGORI_PENGELUARAN)[$kode] ?? $kode;
    }

    public const PRIORITAS = ['RENDAH', 'NORMAL', 'TINGGI'];
    public const JENIS = ['DOKUMEN', 'LAPORAN'];

    /** Label bahasa Indonesia untuk status tugas. */
    public const LABEL = [
        'BARU' => 'Baru', 'DIKERJAKAN' => 'Dikerjakan', 'DIAJUKAN' => 'Diajukan',
        'REVISI' => 'Perlu Revisi', 'DITOLAK' => 'Di Tolak', 'SELESAI' => 'Selesai',
    ];

    /**
     * Kelas lencana untuk status lembar SDM. Disimpan di sini, bukan di Blade,
     * supaya tabel, modal, dan kartu ringkas memakai warna yang sama.
     */
    public const KELAS_SDM = [
        'AKTIF' => 's-DISETUJUI', 'CUTI' => 's-DIKERJAKAN', 'NONAKTIF' => 's-BARU',
        'MENUNGGU' => 's-DIAJUKAN', 'DITERIMA' => 's-DISETUJUI',
        'DITOLAK' => 's-DITOLAK', 'TARIK_DIRI' => 's-BARU',
    ];

    /** Ikon SVG (path 24x24) untuk navigasi. */
    public const IKON = [
        'dasbor'     => 'M3 3h8v8H3zM13 3h8v5h-8zM13 10h8v11h-8zM3 13h8v8H3z',
        'koperasi'   => 'M4 21V9l8-6 8 6v12h-6v-6h-4v6z',
        'tugas_baru' => 'M11 4h2v7h7v2h-7v7h-2v-7H4v-2h7z',
        'akun'       => 'M12 12a4 4 0 100-8 4 4 0 000 8zm0 2c-4 0-8 2-8 5v1h16v-1c0-3-4-5-8-5z',
        'audit'      => 'M5 4h14v2H5zM5 9h14v2H5zM5 14h9v2H5zM5 19h9v2H5z',
        // Dulu tanda centang (samar & salah makna). Kini ikon dasbor: tiga
        // kolom bertingkat, sama-sama terbaca sebagai "dasbor/ringkasan".
        'kerja'      => 'M4 13h6v7H4zm10-9h6v16h-6zM4 4h6v6H4z',
        'sandi'      => 'M12 2a5 5 0 00-5 5v2H5v13h14V9h-2V7a5 5 0 00-5-5zm-3 7V7a3 3 0 116 0v2h-6z',
        'keluar'     => 'M10 3H4v18h6v-2H6V5h4zM14 7l-1.4 1.4L15.2 11H9v2h6.2l-2.6 2.6L14 17l5-5z',
        'ekspor'     => 'M12 3l4 4h-3v6h-2V7H8zM5 15h2v4h10v-4h2v6H5z',
        'siaran'     => 'M3 10v4h3l4 3V7l-4 3H3zm12.5 2a4.5 4.5 0 00-2-3.7v7.4a4.5 4.5 0 002-3.7zM14 2.2v2.1a7.5 7.5 0 010 15.4v2.1a9.5 9.5 0 000-19.6z',
        'materi'     => 'M6 2h8l6 6v14H6zm8 1.5V9h5.5zM8 12h8v2H8zm0 4h8v2H8z',
        // Lembar tagihan (dokumen dengan sudut terlipat + angka rupiah).
        // Tanpa entri ini butir menu "Tagihan Vendor" tampil sebagai kotak kosong:
        // `ikon()` mengembalikan <svg> tanpa path untuk kunci yang tak dikenal.
        'tagihan'    => 'M6 2h8l6 6v14H6zm8 1.5V9h5.5zM9 12h6v2H9zm0 4h4v2H9z',
        'rapat'      => 'M3 5h13a2 2 0 012 2v7a2 2 0 01-2 2H3a2 2 0 01-2-2V7a2 2 0 012-2zm17 3l3-2v10l-3-2z',
        // Kotak suara: surat masuk ke celah kotak. Tanpa entri ini butir
        // "Vote Sistem" tampil sebagai kotak kosong.
        'vote'       => 'M4 9h16v11H4zM7 9V6a2 2 0 012-2h6a2 2 0 012 2v3h-2V6H9v3zm4 3h2v5h-2z',
        // Ikon kamera: PhotoCraft = editor GAMBAR. 'gambar' belum ada di
        // Domain::IKON (sengaja — PhotoCraft tidak ikut bilah modul), jadi
        // tanpa entri ini kartunya muncul sebagai kotak kosong.
        'gambar'     => 'M9 3 7.6 5H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-3.6L15 3H9zm3 5.5A4.5 4.5 0 1 1 12 17a4.5 4.5 0 0 1 0-9z',
        // Ikon FILM (klapper/film-strip): PhotoCraft memakai kamera, jadi editor
        // video harus beda supaya tidak terbaca sebagai modul yang sama.
        // Kunci = 'video' (modul OpenCut, pengganti FilmCraft 2026-10-25).
        'video'      => 'M4 4h16a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zm1 2v2h2V6H5zm12 0v2h2V6h-2zM5 10v2h2v-2H5zm12 0v2h2v-2h-2zM5 14v2h2v-2H5zm12 0v2h2v-2h-2zM8 6v12h8V6H8zm3.5 2.2 4 3.8-4 3.8V8.2z',
        'workspace'  => 'M4 4h7v7H4zm9 0h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z',
        'distribusi' => 'M3 5h11v10H3zm11 3h4l3 3v4h-7zM7 18a2 2 0 100-4 2 2 0 000 4zm10 0a2 2 0 100-4 2 2 0 000 4z',
        // Kunci menu, bukan nama rute: 'stok-koperasi' dulu tak ada di peta ini,
        // jadi ikonnya kosong (path '' -> <svg> tanpa gambar). Menu memanggil
        // ikon($kunci), jadi setiap kunci di daftar_menu WAJIB punya entri.
        'stok-koperasi' => 'M3 4h18v4H3zm2 4h14v12H5zm3 3h8v2H8zm0 4h8v2H8z',
        // Ikon menu "Koperasi" (rute `kop`) — bangunan koperasi, sama dengan
        // 'koperasi'. Kuncinya 'kop' karena menu ADMIN sudah memakai 'koperasi'
        // untuk Koperasi Wilayah; kunci menu = kunci ikon, jadi keduanya
        // didaftarkan terpisah (kalau tidak, <svg>-nya kosong tanpa path).
        'kop'        => 'M4 21V9l8-6 8 6v12h-6v-6h-4v6z',
        // Barcode: lima batang tegak — dipakai tombol "Scan" di POS & Stok
        // Barang. Batangnya sengaja TIDAK seragam lebarnya supaya terbaca
        // sebagai barcode, bukan sebagai grafik batang.
        'barcode'    => 'M2 5h2v14H2zm3 0h1v14H5zm3 0h3v14H8zm4 0h1v14h-1zm3 0h2v14h-2zm3 0h1v14h-1zm2 0h2v14h-2z',
        'keuangan'   => 'M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-1a3 3 0 01-2.6-1.4l1.4-1.2A2 2 0 0012 14c.9 0 1.4-.4 1.4-1s-.5-.8-1.7-1.1c-1.5-.4-2.7-.9-2.7-2.4A2.4 2.4 0 0111 6.2V5h2v1.2c1.1.2 1.9.7 2.4 1.5l-1.4 1.1A1.9 1.9 0 0012.4 8c-.8 0-1.3.3-1.3.9s.6.7 1.8 1c1.6.4 2.6 1 2.6 2.5a2.5 2.5 0 01-2.5 2.5z',
        'umkm'       => 'M3 9l2-5h14l2 5v2h-2v9H5v-9H3zm5 3h8v6H8z',
        // Toko: etalase/kios dengan tenda dan barang di dalamnya.
        'toko'       => 'M4 3h16l2 5v2h-1v11H3V10H2V8zM6 10v9h5v-5h2v5h5v-9zM8 6l-1 3h10l-1-3z',
        'asisten'    => 'M12 3l1.7 4.3L18 9l-4.3 1.7L12 15l-1.7-4.3L6 9l4.3-1.7zM18.5 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z',
        // Keranjang: troli belanja. Dulu tab "Keranjang" memakai ikon 'distribusi'
        // (truk) — truk berarti pengiriman, bukan belanja, jadi ikonnya menyesatkan.
        'keranjang'  => 'M7 18a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM1 2h2.7l.9 2H21a1 1 0 011 1.2l-2.3 8.2a2 2 0 01-1.9 1.5H7.1l-.3 1.3H19v2H5.6a1.6 1.6 0 01-1.6-2l2.4-10.7L5.5 4.4H1z',
        'stok'       => 'M3 4h18v4H3zm2 4h14v12H5zm4 4h6v2H9z',
        'vendor'     => 'M3 4h18v3H3zM5 8h14v12H5zm4 3v9m6-9v9',
        'simkopdes'  => 'M12 3l8 4v10l-8 4-8-4V7zm0 2.3L6 8v7.4l6 3 6-3V8zM7 11h10v2H7z',
        'fitur'      => 'M4 4h7v7H4zm9 0h7v7h-7zM4 13h7v7H4zm9 0h7v7h-7z',
        // Enam modul Ruang Kerja.
        'pesan'      => 'M4 4h16a2 2 0 012 2v9a2 2 0 01-2 2H9l-5 4v-4H4a2 2 0 01-2-2V6a2 2 0 012-2zm3 4h10v2H7zm0 4h7v2H7z',
        'dokumen'    => 'M6 2h8l6 6v14H6zm8 1.5V9h5.5zM9 12h6v2H9zm0 4h6v2H9z',
        'catatan-harian' => 'M7 2v2H5v18h14V4h-2V2h-2v2H9V2zm-1 8h12v2H6zm0 4h12v2H6zm0 4h8v2H6z',
        'kalender'   => 'M7 2v2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2V2h-2v2H9V2zM5 9h14v11H5zm3 3h3v3H8z',
        'tugas'      => 'M9 3h6v2h4v16H5V5h4zm2 0v1h2V3zM8 11l1.5 1.5L13 8.5l1.4 1.4-4.9 5L7 12.4zm1 6h6v2H9z',
        'wiki'       => 'M4 4h7v16H4zm9 0h7v16h-7zm2 3h3v2h-3zm0 4h3v2h-3zm-6-2h3v2H9zm0 4h3v2H9z',
        'setuju'     => 'M12 2a10 10 0 100 20 10 10 0 000-20zm-1 14l-4-4 1.4-1.4L11 13.2l4.6-4.6L17 10z',
        // Pelatihan: buku terbuka + tanda centang (belajar lalu tuntas).
        'pelatihan'  => 'M4 4h7a2 2 0 012 2v13a2 2 0 00-2-2H4zm16 0h-5a2 2 0 00-2 2v13a2 2 0 012-2h5zM7 8h4v2H7z',
        // Produk Lokal & UMKM memakai kunci menu 'pasokan' (dulu hanya ada 'umkm' ->
        // ikon menu kosong). Satu entri ini menutup kunci tersebut.
        'pasokan'    => 'M3 9l2-5h14l2 5v2h-2v9H5v-9H3zm5 3h8v6H8z',
        // Profil: kartu identitas + garis-garis data.
        'profil'     => 'M12 12a4 4 0 100-8 4 4 0 000 8zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5z',
        // Kontrak: lembar dokumen dengan garis tanda tangan di kaki.
        'kontrak'    => 'M6 2h8l6 6v11a3 3 0 00-3 3H6zm8 1.5V9h5.5zM8 12h8v2H8zm0 4h5v2H8zM18 15.5l1.4 1.4 2.6-2.6V21a2 2 0 01-2 2h-2z',
        'ulangtahun' => 'M8 10V8a4 4 0 018 0v2h3v11H5V10zm2 0h4V8a2 2 0 10-4 0zM8 3V1h2v2zm6 0V1h2v2zM3 5l1.4 1.4L6 5 4.6 3.6zm16 0L17.6 5 19 6.4 20.4 5z',
        'pengumuman' => 'M4 5h16v11H8l-4 4zM7 8h10v2H7zm0 3h7v2H7z',
        // SDM: kartu identitas berjepit + garis data di bawahnya.
        'hr'         => 'M3 4h18v16H3zm3 3h3v3H6zm5 0h7v2h-7zm0 3h7v1.5h-7zM6 12h12v1.5H6zm0 3h8v1.5H6z',
        'konferensi' => 'M4 5h11a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V7a2 2 0 012-2zm14 4l4-3v12l-4-3z',
        'kinerja'    => 'M4 20h3V10H4zm6.5 0h3V4h-3zm6.5 0h3v-7h-3z',
        // Lonceng pengumuman — dipakai lencana notifikasi di kepala.
        // Tiga garis: ikon menu (hamburger) untuk tombol pojok kanan atas di ponsel.
        'menu'       => 'M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z',
        'notif'      => 'M12 22a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0012 22zm7-6.6V11a7 7 0 00-5.5-6.83V3a1.5 1.5 0 00-3 0v1.17A7 7 0 005 11v4.4l-1.6 1.6a1 1 0 00.7 1.7h15.8a1 1 0 00.7-1.7L19 15.4z',
        // Aspirasi: gelembung percakapan berisi baris masukan.
        'aspirasi'   => 'M20 2H4a2 2 0 00-2 2v17.2a.8.8 0 001.36.57L7.13 18H20a2 2 0 002-2V4a2 2 0 00-2-2zM7 8h10v2H7zm0 4h7v2H7z',
        // Pemetaan lahan: penanda peta (titik + lingkaran lokasi).
        'pemetaan'   => 'M12 2a7 7 0 00-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 00-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z',
        // Titik maps pada kartu koordinat (/kop/sarana). Dulu kartunya memanggil
        // ikon('lokasi') yang TIDAK ada di peta ini: `ikon()` mengembalikan <svg>
        // tanpa path, jadi kartu "Titik Maps" tampil kosong. Kunci = nama yang
        // sudah dipakai view, jadi didaftarkan di sini (bingkai peta + penanda).
        'lokasi'     => 'M3 3h18v18H3zm2 2v14h14V5zm7 2.5a3.2 3.2 0 013.2 3.2c0 2.6-3.2 6.3-3.2 6.3s-3.2-3.7-3.2-6.3A3.2 3.2 0 0112 7.5zm0 2a1.3 1.3 0 100 2.6 1.3 1.3 0 000-2.6z',
        // Analisa lintas modul memakai ikon yang sama dengan Kinerja (batang
        // bertingkat) — halaman baru, gambar yang sudah terbaca sebagai "analisa"
        // di aplikasi ini, jadi tidak perlu menggambar ikon kedua.
        'analisa'    => 'M4 20h3V10H4zm6.5 0h3V4h-3zm6.5 0h3v-7h-3z',
        // ---- Modul AgriConnect. Satu ikon untuk menu, satu per butirnya.
        // 'agri' = kartu ID berjepit: modul ini layanan mandiri pegawai.
        'agri'       => 'M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm4 4a2 2 0 100 4 2 2 0 000-4zm4 1h6v2h-6zm-4 5h10v2H8z',
        'ringkasan'  => 'M4 4h7v7H4zm9 0h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z',
        'presensi'   => 'M12 2a10 10 0 100 20 10 10 0 000-20zm1 5v5.6l4 2.4-1 1.7-5-3V7z',
        // Absen Agrinashub (kunci menu 'absen-hub'). Tanpa entri ini ikon()
        // menghasilkan <svg> tanpa path — kotak kosong di kisi menu.
        'absen_hub'  => 'M4 4h5v2H6v3H4V4zm11 0h5v5h-2V6h-3V4zM4 15h2v3h3v2H4v-5zm14 0h2v5h-5v-2h3v-3zM12 8a4 4 0 100 8 4 4 0 000-8z',
        // Perjalanan: koper dengan pegangan.
        'perjalanan' => 'M7 6V5a3 3 0 013-3h4a3 3 0 013 3v1h3v14H4V6zm2 0h6V5a1 1 0 00-1-1h-4a1 1 0 00-1 1zM9 10h2v2H9zm4 0h2v2h-2z',
        // Imbalan: bintang hadiah.
        'imbalan'    => 'M12 2l2.9 6.1 6.6.9-4.8 4.6 1.2 6.6L12 17.1 6.1 20.2l1.2-6.6L2.5 9l6.6-.9z',
        // Umpan balik: dua balon percakapan bertumpuk.
        'umpan'      => 'M3 4h12a2 2 0 012 2v6a2 2 0 01-2 2H8l-4 3v-3H3a1 1 0 01-1-1V5a1 1 0 011-1zm16 6h2a1 1 0 011 1v8a1 1 0 01-1 1h-1v3l-4-3h-5a2 2 0 01-2-2v-1h8a2 2 0 002-2z',
        // Regulasi: gulungan dokumen bergaris.
        'berkas'     => 'M6 2h8l6 6v14H6zm8 1.5V9h5.5zM8 12h8v2H8zm0 4h8v2H8zm0-8h4v2H8z',
        'ruang'      => 'M12 2a10 10 0 100 20 10 10 0 000-20zM2 12h20M12 2a15 15 0 010 20 15 15 0 010-20z',
        'struktur'   => 'M12 2a3 3 0 110 6 3 3 0 010-6zM4 14a3 3 0 110 6 3 3 0 010-6zm16 0a3 3 0 110 6 3 3 0 010-6zM11 8h2v3H7v2h2v2H5v-4a2 2 0 012-2h4zm0 3h6a2 2 0 012 2v2h-4v-2h-4z',
        // Menu AgriConnect HRIS. Kuncinya BERHUBUNGAN GANTUNG ('hris-akun'),
        // jadi `ikon()` mencocokkannya lewat terjemahan '-' → '_' di ujung
        // fungsi itu — kunci di sini WAJIB bergaris bawah, kalau tidak menu
        // baru keluar sebagai kotak kosong tanpa galat apa pun.
        // Kunci gembok: sesi akun AgriConnect.
        'hris_akun'    => 'M12 1a5 5 0 015 5v2h1a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V10a2 2 0 012-2h1V6a5 5 0 015-5zm0 2a3 3 0 00-3 3v2h6V6a3 3 0 00-3-3zm0 10a2 2 0 00-1 3.7V19h2v-2.3A2 2 0 0012 13z',
        // Rantai: butir menu "Hubungkan AgriConnect" (membuka dialog login).
        'hris_hubungkan' => 'M10.6 13.4a1 1 0 001.4 0l4.2-4.2a3.5 3.5 0 10-4.9-5l-2.1 2.1 1.4 1.4 2.1-2.1a1.5 1.5 0 012.1 2.1l-4.2 4.2a1 1 0 000 1.4zm2.8-2.8a1 1 0 00-1.4 0l-4.2 4.2a3.5 3.5 0 104.9 5l2.1-2.1-1.4-1.4-2.1 2.1a1.5 1.5 0 01-2.1-2.1l4.2-4.2a1 1 0 000-1.4z',
        // Kartu identitas: profil pegawai.
        'hris_profil'  => 'M3 5h18a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1zm1 2v10h16V7zm4 2a2 2 0 110 4 2 2 0 010-4zm-2 7c0-1.3 1.3-2.4 3-2.4s3 1.1 3 2.4zm9-5h5v2h-5zm0 4h5v2h-5zm0-7h5v2h-5z',
        // Kalender bercawang: absensi HRIS.
        'hris_absensi' => 'M7 1v2h10V1h2v2h2a1 1 0 011 1v17a1 1 0 01-1 1H3a1 1 0 01-1-1V4a1 1 0 011-1h2V1zm13 8H4v11h16zM7 12h3v3H7zm5 0h3v3h-3zm-5 5h3v3H7z',
        // Grafik menanjak: performa.
        'hris_performa' => 'M3 17l6-6 4 4 8-8v4h-2V9.4l-6 6-4-4L4 17.6zM3 20h18v2H3z',
        // Ikon baru untuk halaman kepegawaian yang dipisah (lihat
        // AgriController::MODUL). Tanpa entri di sini `ikon($kunci)`
        // mengembalikan <svg> ber-path kosong — kartu kotak kosong, dan uji
        // yang cuma menghitung jumlah kartu tetap lulus
        // (periksa_kunci_nav.php menangkap yang seperti ini).
        'hris_keluarga' => 'M9 4a3 3 0 110 6 3 3 0 010-6zm7 2a2.5 2.5 0 110 5 2.5 2.5 0 010-5zM2 20v-2c0-2.2 2.7-4 6-4s6 1.8 6 4v2H2zm14 0v-1.6c0-.9-.6-1.7-1.5-2.3.6-.1 1.2-.1 1.8-.1 1.6 0 3 .4 4 1.1V20h-4.3z',
        'hris_bpjs'    => 'M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1zm1 4v2h14V8H5zm0 4v2h5v-2H5zm7 0v2h7v-2h-7zm-7 4v2h5v-2H5zm7 0v2h4v-2h-4z',
        'hris_cuti'    => 'M7 1v2H5a2 2 0 00-2 2v15a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2h-2V1h-2v2H9V1zM5 10h14v10H5zm2.5 5.5L9 17l4-4 3 3v2h-2l-1-1-4 4-1.5-1.5z',
        'hris_pengajuan' => 'M6 2h9l5 5v15H6a2 2 0 01-2-2V4a2 2 0 012-2zm8 2v4h4zM8 12h8v2H8zm0 4h6v2H8z',
        'hris_gaji'    => 'M3 6h18a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V7a1 1 0 011-1zm1 5v6h16v-6a4 4 0 010 0v-2H4zm8 1a2 2 0 110 4 2 2 0 010-4z',
        'hris_info'    => 'M12 2a10 10 0 110 20 10 10 0 010-20zm0 2a8 8 0 100 16 8 8 0 000-16zm-1 4h2v2h-2zm0 3h2v5h-2z',
    ];

    public static function label(?string $status): string
    {
        return self::LABEL[$status] ?? (string) $status;
    }

    /** Aksi yang tercatat di jurnal audit. */
    public const AKSI = [
        'MASUK', 'MASUK_GAGAL', 'KELUAR', 'GANTI_SANDI',
        'KOPERASI_BARU', 'KOPERASI_UBAH',
        'AKUN_BARU', 'AKUN_SIMP', 'RESET_SANDI', 'AKUN_AKTIF_UBAH',
        'TUGAS_BARU', 'TUGAS_STATUS', 'TUGAS_KETERANGAN', 'KIRIMAN', 'KOMENTAR',
        'BARANG_BARU', 'BARANG_UBAH',
        'VENDOR_BARU', 'VENDOR_UBAH',
        'DISTRIBUSI_AJU', 'DISTRIBUSI_PROSES', 'DISTRIBUSI_KIRIM', 'DISTRIBUSI_TOLAK', 'DISTRIBUSI_TERIMA',
        'SIARAN',
        'KEUANGAN_SIMPAN', 'KEUANGAN_HAPUS', 'KEUANGAN_EKSPOR',
        'PASOKAN_AJU', 'PASOKAN_DISETUJUI', 'PASOKAN_DITOLAK', 'PASOKAN_TERIMA',
        'PELATIHAN_SELESAI', 'PELATIHAN_TARIK', 'PELATIHAN_SUNTING', 'PELATIHAN_HAPUS',
        // SDM: siklus kerja, penilaian kinerja berperiode, slip, dan rekrutmen.
        'SDM_SIMPAN', 'SDM_USUL', 'SDM_NILAI', 'SDM_SLIP', 'SDM_REKRUT', 'SDM_KEPUTUSAN',
    ];
}

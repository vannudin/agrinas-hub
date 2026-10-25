<?php

namespace App\Support;

/**
 * JOB DESCRIPTION Manajer Koperasi Desa/Kelurahan Merah Putih (KDKMP) —
 * Lampiran 1 PKWT PT Agrinas Pangan Nusantara (Persero).
 *
 * Disimpan sebagai DATA di PHP, bukan tabel + migrasi + seeder: isinya dokumen
 * kontrak yang berubah paling banyak sekali setahun, tidak per pengguna, dan
 * tidak ada yang perlu ditulis dari aplikasi. Kalau kelak tiap manajer harus
 * mencentang penyelesaian harian, barulah ini naik jadi tabel.
 *
 * Sumber: dokumen PKWT No. 00000/HCMM - PKWT KDKMP/IX/2026, Lampiran 1.
 * Setiap nama, deskripsi, dan estimasi menit sudah diverifikasi cocok dengan
 * teks PDF aslinya (39/39 baris) — jangan disunting dari ingatan.
 *
 * Struktur tiap baris: [no, nama, bagian, menit, poin_bmc, deskripsi]
 */
final class JobdescManajer
{
    /** Seluruh uraian tugas, urut nomor dokumen. */
    public const TASKS = [
        [1, 'Melakukan briefing pagi', 'wajib', 15, '', 'Melakukan briefing pagi; 1) mulai dengan Briefing Rencana Pencapaian EBITDA di hari tersebut, 2) dengan penjelasan Actual EBITDA Hari sebelum nya dan Plan EBITDA hari tersebut'],
        [2, 'Melakukan display barang', 'wajib', 40, '', 'Melakukan display barang di rak sesuai dengan planogram'],
        [3, 'Melakukan penerimaan barang (Good Receipt) di sistem pergudangan/ Warehouse Management System (WMS)', 'wajib', 20, '', 'Melakukan penerimaan barang (Good Receipt) di sistem pergudangan/ Warehouse Management System (WMS)'],
        [4, 'Melakukan Quality Control', 'wajib', 20, '', 'Melakukan Quality Control (QC) fisik cepat dan mencatatnya ke sistem kemitraan KDKMP'],
        [5, 'Melayani pembayaran', 'wajib', 50, '', 'Melayani pembayaran tunai, non-tunai'],
        [6, 'Memasang harga sesuai produk', 'wajib', 15, '', 'Memasang harga sesuai produk 15'],
        [7, 'Memindahkan barang dari gudang ke rak pajangan', 'wajib', 30, '', 'menggunakan metode FIFO/FEFO'],
        [8, 'Memindai barang belanjaan konsumen', 'wajib', 100, '', 'Memindai barang belanjaan konsumen 100'],
        [9, 'Menawarkan produk promo upselling', 'wajib', 15, '', 'Menawarkan produk promo upselling 15'],
        [10, 'Mencocokkan total uang tunai/non-tunai', 'wajib', 15, '', 'Mencocokkan total uang tunai/non-tunai di mesin kasir dengan laporan penjualan sistem POS dan melaporkan ke kepala/asisten toko'],
        [11, 'Menerima barang dari Principal', 'wajib', 45, '', 'Menerima barang dari Principal dan mencocokkan apakah sudah sesuai atau belum dengan po (Memeriksa kesesuaian jumlah karton, fisik produk, dan tanggal kedaluwarsa)'],
        [12, 'Menerima setoran hasil bumi/ternak', 'wajib', 15, '', 'Menerima setoran hasil bumi/ternak harian dari warga/petani lokal (misal: produk UMKM)'],
        [13, 'Menghitung modal awal laci kasir, membersihkan area toko, menyalakan lampu, AC, komputer POS kasir, mesin chiller/cold storage, membuka rolling door', 'wajib', 20, '', 'chiller/cold storage, membuka rolling door.'],
        [14, 'Mengonsolidasikan produk lokal', 'wajib', 25, '', 'gudang . (jika sudah ada DC)'],
        [15, 'Menyiapkan uang', 'wajib', 15, '', 'Melakukan briefing pagi; 1) mulai dengan Briefing Rencana Pencapaian EBITDA di hari tersebut, 2) dengan penjelasan Actual EBITDA Hari sebelum nya dan Plan EBITDA hari tersebut'],
        [16, 'Proses Penutupan Toko (Closing & Housekeeping)', 'wajib', 25, '', 'seluruh lantai gerai, mematikan peralatan listrik yang tidak terpakai (kecuali chiller produk segar), mengunci brankas, dan mengunci pintu gerai, Stock opname dan mengecek produk OUT OF STOCK (melakukan pendataan untuk dilaporkan ke PIC AGRINAS)'],
        [17, 'Mengecek target laba sebelum bunga, pajak, depresiasi, dan amortisasi / Earnings Before Interest, Taxes, Depreciation, and Amortization (EBITDA)', 'wajib', 20, '', 'ebitda aktual hari tersebut dan plan esok hari. Dengan 1) Merekam Actual Earning before EBITDA Hari tersebut, 2) Membuat Plan EBITDA Hari berikut, 3) Memeriksa Capacity Matrix, 4) Memaksimalkan Produktivitas dan Efisiensi semua sumber daya (4M: Man, Machine, Material, Method)'],
        [18, 'Integrasi Komunitas (Event Piggybacking)', 'pilihan', 60, 'Channels', 'Meliputi kegiatan membuka stan/layanan jemput bola atau sosialisasi di acara rutin desa (Posyandu, arisan, pengajian) tanpa harus mengeluarkan biaya membuat acara sendiri.'],
        [19, 'Intelijen Pesaing & Evaluasi Kinerja (Analitik)', 'pilihan', 60, 'Channels', 'Meliputi shadowing (observasi lapangan) terhadap bank keliling/tengkulak, A/B Testing saluran komunikasi, serta penghitungan biaya akuisisi pelanggan (CAC) dan rasio konversi.'],
        [20, 'Operasional Saluran Digital & Fisik Harian', 'pilihan', 60, 'Channels', 'Melakukan penerimaan barang (Good Receipt) di sistem pergudangan/ Warehouse Management System (WMS) Melakukan penerimaan barang (Good Receipt) di sistem pergudangan/ Warehouse Management System (WMS)'],
        [21, 'Akuisisi & Pengelolaan Komunitas (Get & Community)', 'pilihan', 60, 'Customer Relationships', 'Customer Relationships'],
        [22, 'Pengembangan & Manajemen Biaya (Grow & Cost Management)', 'pilihan', 30, 'Customer Relationships', 'Customer Relationships'],
        [23, 'Retensi & Layanan Mandiri (Keep & Personal/Self-Service)', 'pilihan', 120, 'Customer Relationships', 'Customer Relationships'],
        [24, 'Desain Arketipe & Standarisasi Pelayanan', 'pilihan', 60, 'Customer Segments', 'Mencakup sintesis data wawancara, pembuatan profil persona/arketipe (misal: "Pak Budi Petani Progresif"), pemetaan Customer Journey, dan pelatihan staf menggunakan'],
        [25, 'Manajemen Pelanggan Utama (MIC)', 'pilihan', 120, 'Customer Segments', 'Proses Penutupan Toko (Closing & Housekeeping) Membersihkan seluruh lantai gerai, mematikan peralatan listrik yang tidak terpakai (kecuali chiller produk segar), mengunci brankas, dan mengunci pintu gerai, Stock opname dan mengecek produk OUT OF STOCK (melakukan pendataan untuk dilaporkan ke PIC AGRINAS)'],
        [26, 'Pemetaan & Segmentasi Pelanggan', 'pilihan', 60, 'Customer Segments', 'Mencakup pengumpulan data demografi dari BPS/Desa, pelaksanaan Diskusi Kelompok Fokus (FGD), validasi lapangan (Gemba), dan penyesuaian layanan koperasi dengan segmen yang terbentuk.'],
        [27, 'Administrasi Keuangan & Evaluasi Layanan', 'pilihan', 60, 'Key Activities', 'Meliputi pencatatan kas harian, evaluasi penyelesaian keluhan pelanggan (SLA 1x24 jam), dan penetapan harga jual harian (Pricing Strategy).'],
        [28, 'Manajemen Kredit & Penagihan (Collection)', 'pilihan', 60, 'Key Activities', 'Meliputi analisis pengajuan kredit, survei/kunjungan jemput bola tabungan, dan penagihan angsuran agar rasio Non-Performing Loan (NPL) tetap di bawah 5%.'],
        [29, 'Operasional Rantai Pasok & Mutu (Supply Chain & QC)', 'pilihan', 60, 'Key Activities', 'Meliputi aktivitas pembelian/pemesanan barang ke supplier, pengecekan fisik (QC) untuk mencegah barang kedaluwarsa, dan penjagaan agar tidak ada kekosongan stok (Out of Stock).'],
        [30, 'Operasional Saluran & Layanan Digital (Channels & Pemasaran)', 'pilihan', 60, 'Key Activities', 'Memindahkan barang dari gudang ke rak pajangan Memindahkan barang dari gudang ke rak pajangan menggunakan metode FIFO/FEFO'],
        [31, 'Manajemen Risiko & Evaluasi SLA Pemasok', 'pilihan', 30, 'Key Partnerships', 'Meliputi penarikan data dari Dashboard Kinerja Pemasok, peninjauan keluhan atas keterlambatan/cacat produksi pemasok, dan pembaruan Register Risiko Mitra (mitigasi jika ada vendor yang gagal suplai).'],
        [32, 'Manajemen Vendor & Pengadaan Harian', 'pilihan', 30, 'Key Partnerships', 'Meliputi pemesanan stok harian/mingguan (PO/Purchase Order) kepada pemasok utama (Bulog, ID Food, grosir lokal), pengecekan kualitas/akurasi saat barang datang (> 98%), dan evaluasi lead time pengiriman (< 24 jam).'],
        [33, 'Negosiasi, Jaringan, & Kontrak Strategis (B2B/B2G)', 'pilihan', 30, 'Key Partnerships', 'Meliputi lobi dan negosiasi harga (target efisiensi 5-10%), peninjauan perpanjangan MoU, koordinasi dengan stakeholder (Kepala Desa, BUMN lokal, UMKM), dan penyusunan Peta Mitra (Partner Map).'],
        [34, 'Manajemen Keuangan & Likuiditas (Financial Resources)', 'pilihan', 60, 'Key Resources', 'Meliputi kegiatan rekonsiliasi kas harian, pengecekan ketersediaan uang di brankas untuk dana talangan, pemantauan saldo bank, serta memastikan sistem POS (kasir) dan mesin EDC berfungsi tanpa kendala.'],
        [35, 'Pengelolaan SDM & Data Intelektual (Human & Intellectual Resources)', 'pilihan', 30, 'Key Resources', 'Meliputi pembaharuan dan pengamanan basis data (database) anggota, pembinaan (coaching) atau evaluasi harian staf lini depan, serta pemeliharaan kontak/kemitraan dengan supplier (distributor).'],
        [36, 'Perawatan Fisik & Infrastruktur', 'pilihan', 60, 'Key Resources', 'Meliputi pengecekan kondisi kelayakan fasilitas (toko, gudang, rak), pemeliharaan armada'],
        [37, 'Evaluasi Kepuasan & Cross-Selling (Gain Creation)', 'pilihan', 60, 'Value Propositions', 'Meliputi pelaksanaan survei kepuasan pelanggan (CSAT/NPS), analisis data transaksi (repeat order), perancangan alur cross-selling (bundel layanan antar unit), dan pendokumentasian kisah sukses anggota.'],
        [38, 'Manajemen Solusi & Keluhan (Pain Management)', 'pilihan', 60, 'Value Propositions', 'Meliputi kegiatan observasi lapangan langsung (Gemba), identifikasi hambatan anggota dengan analisis 5 Whys, dan pengelolaan keluhan harian agar terespons maksimal dalam 2x24 jam (target zero unresolved complaint).'],
        [39, 'Riset & Pengembangan Inovasi (MVP & Value Canvas)', 'pilihan', 90, 'Value Propositions', 'Meliputi pengisian dan pembaruan Value Proposition Canvas, uji coba produk berskala kecil (MVP) sebelum investasi besar, dan evaluasi matriks produk versus segmen pelanggan.'],
    ];

    /** Tugas Harian (Daily Task) — WAJIB. Nomor 1–17. */
    public static function wajib(): array
    {
        return array_values(array_filter(self::TASKS, fn ($t) => $t[2] === 'wajib'));
    }

    /** Tugas Pilihan — dipetakan Business Model Canvas. Nomor 18–39. */
    public static function pilihan(): array
    {
        return array_values(array_filter(self::TASKS, fn ($t) => $t[2] === 'pilihan'));
    }

    /** Tugas pilihan dikelompokkan per poin BMC (Channels, Key Activities, …). */
    public static function perPoinBmc(): array
    {
        $keluar = [];
        foreach (self::pilihan() as $t) {
            $keluar[$t[4]][] = $t;
        }

        return $keluar;
    }

    /** Total estimasi menit satu kelompok tugas. */
    public static function totalMenit(array $tasks): int
    {
        return array_sum(array_column($tasks, 3));
    }
}

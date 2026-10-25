<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sarana prasarana & pembangunan koperasi + mesin penjualan (POS) koperasi.
 *
 * KENAPA TABEL BARU, padahal SIMKOPDES sudah punya datanya:
 *  - `koperasi_sarana` adalah SALINAN yang ditarik dari SIMKOPDES, sama seperti
 *    `koperasi_lahan` (verval aset) dan `kontrak`. Yang perlu disalin hanya
 *    empat hal: status koperasi (sampai tahap legal mana), daftar gerai/outlet,
 *    indikator sarana prasarana yang jadi patokan program, dan progres
 *    pembangunannya per pekan. Dibaca sebagai data Hub supaya halaman tidak
 *    menunggu SIMKOPDES (dan tidak kehabisan kuota 120 permintaan) setiap kali
 *    dibuka. Semua baris punya kunci alami dari SIMKOPDES dan ditulis dengan
 *    `updateOrCreate`, jadi tarikan boleh diulang tanpa menggandakan apa pun.
 *
 *  - `koperasi_jual` + `koperasi_jual_item` adalah milik HUB SENDIRI: ini
 *    penjualan yang benar-benar dicatat kasir koperasi di sini, menurunkan
 *    `koperasi_barang.stok`. TIDAK dituliskan balik ke SIMKOPDES — modul POS
 *    di sana milik koperasi (lihat catatan di skill `simkopdes-facilitator-api`,
 *    bagian "Batas & etika"), jadi menuliskan transaksi ke sana berarti
 *    mencatat penjualan atas nama orang lain.
 *
 * Stok POS menumpang `koperasi_barang` (inventaris koperasi) dan mutasinya
 * dicatat di `koperasi_barang_stok` yang sudah ada — satu sumber stok, bukan
 * angka kedua yang bisa berbeda.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        // ------------------------------------------------- status & sarana koperasi
        // Satu baris per koperasi (kunci: koperasi_id). Bentuknya ringkas dan
        // kolomnya bisa dibaca manusia, bukan JSON mentah: yang paling sering
        // ditanya "koperasi ini sudah operasional atau belum, tahapnya apa".
        $s->create('koperasi_sarana', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_id');
            $t->unsignedBigInteger('koperasi_simkopdes_id')->nullable();

            // Tahap legal (data SIMKOPDES): Registrasi → Diproses Notaris →
            // Selesai. `legal_stage_sequence` dipakai untuk membandingkan tahap
            // (angka), `legal_stage` untuk ditampilkan.
            $t->string('legal_stage', 80)->default('');
            $t->integer('legal_stage_sequence')->default(0);

            // Status operasional = PENILAIAN, bukan salinan: koperasi dianggap
            // operasional bila ia sudah berbadan hukum (tahap selesai), punya
            // gerai, dan sudah punya barang tercatat. Rinciannya disimpan supaya
            // halaman bisa berterus terang kenapa statusnya begitu — bukan cuma
            // stempel "Belum" tanpa alasan.
            $t->string('operasional', 24)->default('BELUM');   // SUDAH | SEBAGIAN | BELUM
            $t->integer('skor_operasional')->default(0);       // 0-100
            $t->string('alasan', 240)->default('');

            $t->timestamp('ditarik')->nullable();
            $t->timestamps();

            $t->unique('koperasi_id');
            $t->index('koperasi_simkopdes_id');
        });

        // Gerai/outlet koperasi (`/cooperative-outlets/by-cooperative-id/{id}`).
        // Kolomnya sengaja longgar: bentuk respons SIMKOPDES bisa berbeda antar
        // koperasi, jadi yang tidak dikenal disimpan sebagai `mentah` (JSON).
        $s->create('koperasi_gerai', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_id');
            $t->unsignedBigInteger('outlet_simkopdes_id')->nullable();
            $t->string('nama', 190)->default('');
            $t->string('jenis', 60)->default('');
            $t->string('alamat', 240)->default('');
            $t->string('telepon', 40)->default('');
            $t->string('status', 40)->default('');
            $t->text('mentah')->nullable();

            $t->timestamps();
            $t->unique(['koperasi_id', 'outlet_simkopdes_id'], 'uq_kop_gerai_sim');
            $t->index('koperasi_id');
        });

        // Indikator sarana prasarana yang MENJADI PATOKAN program (katalog
        // nasional, bukan milik satu koperasi): "AC 3 Unit 2PK", "GERAI RAK 3
        // Unit Rak Double Starting", dst. Katalognya dipakai untuk menjawab
        // "sarana apa yang seharusnya ada", sedangkan `koperasi_sarana_progres`
        // menjawab "sudah berapa persen dikerjakan".
        $s->create('sarana_indikator', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('indikator_simkopdes_id')->nullable();
            $t->string('jenis_pengembangan', 80)->default('');  // Sarana Prasarana | Bangunan | Kendaraan
            $t->string('jenis_konstruksi', 80)->default('');    // AC | GERAI RAK | …
            $t->string('indikator', 190)->default('');          // 3 Unit 2PK
            $t->text('keterangan')->nullable();

            $t->timestamps();
            $t->unique('indikator_simkopdes_id');
            $t->index('jenis_pengembangan');
        });

        // Progres pembangunan per koperasi per pekan, beserta rincian per
        // indikator. Pekan dengan nilai 0 TIDAK disimpan — SIMKOPDES selalu
        // mengembalikan 13 pekan walau belum ada pekerjaan apa pun, dan
        // menyimpannya berarti 13 baris kosong per koperasi yang membuat
        // "kapan terakhir dikerjakan" mustahil dibaca.
        $s->create('koperasi_sarana_progres', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_id');
            $t->integer('pekan');
            $t->decimal('nilai_pekan', 12, 2)->default(0);
            $t->decimal('nilai_kumulatif', 12, 2)->default(0);
            $t->text('rincian')->nullable();                    // JSON per indikator

            $t->timestamps();
            $t->unique(['koperasi_id', 'pekan'], 'uq_kop_sarana_pekan');
            $t->index('koperasi_id');
        });

        // ------------------------------------------------------------- POS koperasi
        // Penjualan yang dicatat kasir koperasi DI HUB. `nomor` = nomor nota yang
        // ditampilkan ke pembeli (`INV-YYYYMMDD-NNN`), dibuat server supaya tidak
        // ada dua nota bernomor sama.
        $s->create('koperasi_jual', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_id');
            $t->unsignedBigInteger('kasir_id')->nullable();     // siapa yang mencatat
            $t->string('nomor', 40);
            $t->string('pembeli', 120)->default('');
            $t->string('kontak', 40)->default('');
            $t->string('bayar', 16)->default('TUNAI');          // TUNAI | TRANSFER | QRIS | HUTANG
            $t->integer('total')->default(0);                   // rupiah utuh
            $t->integer('dibayar')->default(0);
            $t->integer('kembali')->default(0);                 // dihitung server, bukan klien
            $t->string('catatan', 200)->default('');
            // BATAL = nota dibatalkan; stok DIKEMBALIKAN saat pembatalan supaya
            // barang tidak hilang dari catatan. Barisnya tidak dihapus: nota
            // bernomor yang lenyap membuat riwayat tidak bisa dipercaya.
            $t->string('status', 16)->default('LUNAS');         // LUNAS | HUTANG | BATAL
            $t->string('tanggal', 10);                          // YYYY-MM-DD (WIB)
            $t->string('dibuat', 19)->nullable();

            $t->unique(['koperasi_id', 'nomor'], 'uq_kop_jual_nomor');
            $t->index(['koperasi_id', 'tanggal'], 'idx_kop_jual_hari');
        });

        // Rincian barang per nota. `nama`/`satuan`/`harga` DISALIN, bukan
        // ditautkan: harga jual bisa berubah besok, dan nota lama harus tetap
        // menunjukkan apa yang benar-benar dibayar pembeli hari itu.
        $s->create('koperasi_jual_item', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_jual_id');
            $t->unsignedBigInteger('koperasi_id');
            $t->unsignedBigInteger('koperasi_barang_id')->nullable();
            $t->string('nama', 160);
            $t->string('satuan', 16)->default('pcs');
            $t->integer('harga')->default(0);
            $t->integer('jumlah')->default(1);
            $t->integer('subtotal')->default(0);

            $t->timestamps();
            $t->index('koperasi_jual_id');
            $t->index('koperasi_id');
        });
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);
        $s->dropIfExists('koperasi_jual_item');
        $s->dropIfExists('koperasi_jual');
        $s->dropIfExists('koperasi_sarana_progres');
        $s->dropIfExists('sarana_indikator');
        $s->dropIfExists('koperasi_gerai');
        $s->dropIfExists('koperasi_sarana');
    }
};

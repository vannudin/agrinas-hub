<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventaris barang MILIK KOPERASI — berbeda dari tabel `barang`.
 *
 * `barang` = katalog gudang Agrinas (pusat) beserta stoknya; itu tetap dipakai
 * karena gudang pusat punya pengelolaannya sendiri. Koperasi memerlukan daftar
 * TERPISAH: barang yang benar-benar ada di koperasi, bisa ditambah/diubah manajer
 * sendiri (termasuk barang yang dibeli sendiri, bukan hanya kiriman Agrinas), dan
 * stoknya diperbarui harian.
 *
 * Kenapa tidak menambah kolom ke `barang`: satu baris `barang` dipakai bersama
 * seluruh koperasi, jadi menaruh stok di sana akan mencampur stok 20 koperasi
 * dalam satu angka.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        $s->create('koperasi_barang', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_id');
            $t->string('kode', 32)->default('');            // kode internal koperasi (opsional)
            $t->string('barcode', 64)->default('');         // hasil scan kamera (opsional)
            $t->string('nama', 160);
            $t->string('satuan', 16)->default('pcs');
            $t->string('kategori', 24)->default('LAINNYA');
            $t->integer('stok')->default(0);
            $t->integer('stok_min')->default(0);            // 0 = tanpa ambang peringatan
            $t->integer('harga')->default(0);               // harga jual, opsional
            $t->string('catatan', 200)->default('');
            $t->unsignedBigInteger('oleh_id')->nullable();  // siapa terakhir mengubah
            $t->string('dibuat', 19)->nullable();
            $t->string('diperbarui', 19)->nullable();
            $t->index(['koperasi_id', 'nama'], 'idx_kop_barang_nama');
            // Barcode unik PER KOPERASI (bukan global): dua koperasi boleh punya
            // produk ber-barcode sama, dan '' (tanpa barcode) tidak boleh bentrok.
            $t->unique(['koperasi_id', 'barcode'], 'uq_kop_barang_barcode');
        });

        // Riwayat perubahan stok harian = "update stok" yang bisa ditelusuri.
        // Tanpa ini, "stok hari ini" hanya angka terakhir dan tidak ada cara
        // menjawab "kenapa berkurang" — padahal itu pertanyaan yang selalu datang.
        $s->create('koperasi_barang_stok', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_barang_id');
            $t->unsignedBigInteger('koperasi_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->integer('sebelum')->default(0);
            $t->integer('sesudah')->default(0);
            $t->integer('selisih')->default(0);
            $t->string('sebab', 24)->default('HITUNG');     // HITUNG | MASUK | KELUAR | RUSAK
            $t->string('catatan', 200)->default('');
            $t->string('tanggal', 10);                      // YYYY-MM-DD (hari penyesuaian)
            $t->string('dibuat', 19)->nullable();
            $t->index(['koperasi_id', 'tanggal'], 'idx_kop_stok_hari');
        });
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);
        $s->dropIfExists('koperasi_barang_stok');
        $s->dropIfExists('koperasi_barang');
    }
};

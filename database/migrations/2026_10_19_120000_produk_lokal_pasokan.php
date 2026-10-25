<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produk Lokal & UMKM: pasokan dapat DITAYANGKAN ke /toko supaya bisa dipesan
 * koperasi lain (order antar-koperasi).
 *
 * Tiga kolom di `pasokan` (bukan tabel baru):
 *   jenis      — Pertanian / Peternakan / UMKM (permintaan Bos 2026-10-19)
 *   tayang     — 0/1, opsi manajer menampilkan produknya di etalase /toko
 *   harga_jual — harga jual ke koperasi lain; 0 = pakai harga beli dari UMKM
 *
 * `pesanan.penjual_id` membedakan pesanan dari gudang Agrinas (NULL — perilaku
 * lama, 60 gate lama tetap jalan) dari pesanan antar-koperasi (berisi id
 * koperasi penjual). `pesanan_item.pasokan_id` menautkan baris pesanan ke
 * pasokan yang dijual, supaya sisa yang bisa dipesan bisa dihitung — tanpa
 * kolom ini, dua koperasi bisa memesan barang yang sama melebihi stok nyata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hub')->table('pasokan', function (Blueprint $t) {
            $t->string('jenis', 20)->default('UMKM');
            $t->integer('tayang')->default(0);
            $t->integer('harga_jual')->default(0);
        });
        Schema::connection('hub')->table('pesanan', function (Blueprint $t) {
            $t->integer('penjual_id')->nullable();
        });
        Schema::connection('hub')->table('pesanan_item', function (Blueprint $t) {
            $t->integer('pasokan_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->table('pasokan', fn (Blueprint $t) => $t->dropColumn(['jenis', 'tayang', 'harga_jual']));
        Schema::connection('hub')->table('pesanan', fn (Blueprint $t) => $t->dropColumn('penjual_id'));
        Schema::connection('hub')->table('pesanan_item', fn (Blueprint $t) => $t->dropColumn('pasokan_id'));
    }
};

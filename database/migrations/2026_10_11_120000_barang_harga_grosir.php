<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Harga grosir: pembelian dalam jumlah besar lebih murah per satuan.
 *
 * Satu baris per jenjang, bukan kolom tetap (harga_1/harga_2/harga_3), supaya
 * jenjangnya bisa ditambah/dihapus pengelola tanpa migrasi baru:
 *   min_jumlah = 5  -> berlaku mulai 5 satuan
 * Semua jenjang dievaluasi terhadap JUMLAH BARIS YANG SAMA, jadi urutannya tidak
 * penting; yang terpakai adalah jenjang dengan min_jumlah TERBESAR yang terpenuhi.
 *
 * ponytail: satu tabel + satu relasi, bukan mesin diskon. Tambah diskon persen,
 * jadwal promo, atau harga per-koperasi kalau memang diminta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('hub')->statement('
            create table if not exists barang_harga_grosir (
                id integer primary key autoincrement,
                barang_id integer not null,
                min_jumlah integer not null,
                harga integer not null,
                dibuat varchar
            )');
        DB::connection('hub')->statement(
            'create index if not exists barang_harga_grosir_barang on barang_harga_grosir (barang_id, min_jumlah)');
    }

    public function down(): void
    {
        DB::connection('hub')->statement('drop table if exists barang_harga_grosir');
    }
};

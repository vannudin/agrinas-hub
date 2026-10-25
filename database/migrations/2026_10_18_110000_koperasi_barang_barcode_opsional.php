<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks unik barcode hanya berlaku untuk barcode BERISI.
 *
 * `uq_kop_barang_barcode` di migrasi 2026_10_10 memasang unique (koperasi_id,
 * barcode) di atas kolom NOT NULL berdefault ''. Akibatnya '' dianggap satu
 * nilai yang sama, sehingga menyimpan barang KEDUA tanpa barcode di satu
 * koperasi selalu gagal — dan gagalnya berupa 500 ke wajah pengguna, bukan
 * pesan yang bisa dipahami. Barcode memang opsional ("hasil scan kamera"),
 * jadi yang wajib unik hanyalah barcode yang benar-benar dipakai.
 *
 * Indeks parsial dipakai karena ia menyelesaikan ini tanpa membangun ulang
 * tabel (SQLite tak bisa mengubah kolom jadi nullable secara langsung).
 * ponytail: khas SQLite. Kalau hub kelak pindah ke MySQL, MySQL 8 juga
 * mendukung indeks unik parsial — sintaks `where`-nya sama.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);
        if (! $s->hasTable('koperasi_barang')) {
            return;
        }

        DB::connection($this->connection)->statement('drop index if exists uq_kop_barang_barcode');
        DB::connection($this->connection)->statement(
            "create unique index if not exists uq_kop_barang_barcode"
            ." on koperasi_barang (koperasi_id, barcode) where barcode <> ''"
        );
    }

    public function down(): void
    {
        DB::connection($this->connection)->statement('drop index if exists uq_kop_barang_barcode');
        DB::connection($this->connection)->statement(
            'create unique index if not exists uq_kop_barang_barcode'
            .' on koperasi_barang (koperasi_id, barcode)'
        );
    }
};

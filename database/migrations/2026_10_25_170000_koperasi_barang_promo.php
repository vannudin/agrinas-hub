<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROMO barang koperasi: diskon persen atau bonus barang gratis.
 *
 * Satu barang hanya punya SATU promo — koperasi desa tidak menjalankan mesin
 * promosi; yang nyata di lapangan cuma "beli 5 dapat potongan" dan "beli 10
 * dapat 1 gratis". Karena itu kolomnya menempel di `koperasi_barang`, bukan
 * tabel aturan promosi tersendiri.
 *
 * `promo_min` = jumlah beli minimal agar promo berlaku (0 = 1, artinya selalu).
 * `promo_bonus_id` hanya dipakai jenis GRATIS: barang apa yang diberikan cuma-cuma.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        if (! $s->hasColumn('koperasi_barang', 'promo_jenis')) {
            $s->table('koperasi_barang', function (Blueprint $t) {
                // '' = tanpa promo · 'DISKON' = potongan persen · 'GRATIS' = bonus barang.
                $t->string('promo_jenis', 10)->default('');
                // DISKON: persen 1–100. GRATIS: jumlah unit barang bonus.
                $t->integer('promo_nilai')->default(0);
                // Minimal jumlah beli; 0 dianggap 1 supaya promo tanpa ambang tetap jalan.
                $t->integer('promo_min')->default(0);
                // Barang bonus (id di tabel ini juga); 0 = belum dipilih.
                $t->integer('promo_bonus_id')->default(0);
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);
        foreach (['promo_jenis', 'promo_nilai', 'promo_min', 'promo_bonus_id'] as $k) {
            if ($s->hasColumn('koperasi_barang', $k)) {
                $s->table('koperasi_barang', fn (Blueprint $t) => $t->dropColumn($k));
            }
        }
    }
};

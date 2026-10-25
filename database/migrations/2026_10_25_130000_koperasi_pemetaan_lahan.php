<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan koperasi → titik pemetaan lahan (`pemetaan_lahan.id`).
 *
 * Manajer yang akunnya belum punya koperasi membuat datanya sendiri dengan
 * MEMILIH desa dari daftar pemetaan lahan (Select2 di /kop), bukan mengetik
 * ulang nama desa. Tanpa kolom ini pilihannya hilang setelah disimpan dan
 * `KoperasiController::padananLahan()` kembali menebak lewat kemiripan NAMA
 * desa/kecamatan — cara yang sudah terbukti salah untuk desa bernama sama.
 *
 * Bukan UNIQUE: satu desa bisa punya lebih dari satu titik lahan, dan satu
 * titik bisa dicatat dua koperasi (mis. koperasi lama + koperasi baru).
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        if ($s->hasTable('koperasi') && ! $s->hasColumn('koperasi', 'pemetaan_lahan_id')) {
            $s->table('koperasi', function (Blueprint $t) {
                $t->integer('pemetaan_lahan_id')->nullable();
                $t->index('pemetaan_lahan_id');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);

        if ($s->hasColumn('koperasi', 'pemetaan_lahan_id')) {
            $s->table('koperasi', fn (Blueprint $t) => $t->dropColumn('pemetaan_lahan_id'));
        }
    }
};

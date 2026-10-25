<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `koperasi.koperasi_simkopdes_id` — kunci pertama saat memadankan koperasi
 * binaan SIMKOPDES dengan baris Hub.
 *
 * Sebelumnya pencocokan hanya lewat kode wilayah kabupaten. Itu salah untuk
 * wilayah yang memuat lebih dari satu koperasi (dua koperasi beda di satu
 * kabupaten akan saling menimpa), dan baris hasil penarik CLI (kode wilayah
 * saja) tidak bisa dibedakan dari hasil login web. Dengan kolom ini pemadanan
 * eksak: id SIMKOPDES dulu, kode wilayah sebagai cadangan.
 *
 * NULL = baris lama yang belum dipadankan; diisi saat tarikan/jlogin berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if ($s->hasTable('koperasi') && ! $s->hasColumn('koperasi', 'koperasi_simkopdes_id')) {
            $s->table('koperasi', function (Blueprint $t) {
                $t->integer('koperasi_simkopdes_id')->nullable();
                $t->index('koperasi_simkopdes_id');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        if ($s->hasTable('koperasi') && $s->hasColumn('koperasi', 'koperasi_simkopdes_id')) {
            $s->table('koperasi', function (Blueprint $t) {
                $t->dropIndex(['koperasi_simkopdes_id']);
                $t->dropColumn('koperasi_simkopdes_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materi pelatihan dikelompokkan per modul: `kelompok` = judul modul, `thumb` =
 * kunci sampul (JPEG halaman pertama) di disk materi. Keduanya kosong untuk
 * materi lama, jadi halaman tetap jalan tanpa mengisi ulang data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hub')->table('materi', function (Blueprint $t) {
            $t->string('kelompok', 160)->default('');
            $t->string('thumb', 255)->default('');
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->table('materi', function (Blueprint $t) {
            $t->dropColumn(['kelompok', 'thumb']);
        });
    }
};

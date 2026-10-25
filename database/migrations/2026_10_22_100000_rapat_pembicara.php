<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar PEMBICARA UNDANGAN per rapat.
 *
 * Sebelum ini satu-satunya cara menjadi pembicara webinar adalah masuk sebagai
 * pengelola (orang pertama) atau diangkat dari dalam ruang — artinya admin tidak
 * bisa mengundang orang lebih dulu, dan orang yang diundang tidak tahu apa-apa.
 *
 * Disimpan sebagai CSV id pengguna (pola yang sama dengan `siaran_sesi` /
 * `siaran_oleh`): isinya puluhan id, bukan relasi yang perlu di-query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hub')->table('konferensi', function (Blueprint $t) {
            $t->text('pembicara')->default('');
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->table('konferensi', function (Blueprint $t) {
            $t->dropColumn('pembicara');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materi pelatihan yang ditarik dari LMS APN (https://lms.agrinaspangan.id).
 *
 * Disimpan salinan lokal supaya materi bisa dibaca dari Agrinas Hub walau LMS
 * sedang tidak bisa dihubungi, dan supaya admin bisa mengelola (CRUD) tanpa
 * menyentuh LMS. `lms_id` = id modul di LMS, dipakai sebagai kunci upsert saat
 * menarik ulang sehingga menarik dua kali tidak menggandakan baris.
 *
 * `lms_progres` mencatat modul mana yang sudah dibaca tiap pengguna — inilah
 * "progres pembelajaran"; persentase dihitung dari jumlah baris selesai dibagi
 * jumlah modul, tidak disimpan sebagai angka terpisah (satu sumber angka).
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasTable('lms_materi')) {
            $s->create('lms_materi', function (Blueprint $t) {
                $t->id();
                $t->integer('lms_id')->unique();       // id modul di LMS
                $t->integer('path_id');                // id learning path induk
                $t->string('path_judul');
                $t->string('judul');
                $t->string('slug')->default('');
                $t->string('ringkas')->default('');     // excerpt dari LMS
                $t->text('konten')->default('');        // HTML dari LMS
                $t->integer('menit')->default(0);       // estimated_minutes
                $t->integer('posisi')->default(0);
                $t->integer('disunting_oleh')->nullable();
                $t->string('dibuat');
                $t->string('diperbarui');
                $t->index('path_id');
                $t->index('posisi');
            });
        }

        if (! $s->hasTable('lms_progres')) {
            $s->create('lms_progres', function (Blueprint $t) {
                $t->id();
                $t->integer('user_id');
                $t->integer('lms_id');
                $t->string('selesai_pada');
                $t->unique(['user_id', 'lms_id']);
                $t->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('lms_progres');
        Schema::connection('hub')->dropIfExists('lms_materi');
    }
};

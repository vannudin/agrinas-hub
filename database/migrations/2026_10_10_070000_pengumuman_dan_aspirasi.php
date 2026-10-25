<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua tabel untuk fitur komunikasi dua arah:
 *
 * 1. `pengumuman_baca` — siapa sudah membaca pengumuman yang mana.
 *    Pengumumannya SENDIRI tidak butuh tabel baru: sudah ada `broadcast`
 *    (dipakai /admin/broadcast dan muncul di /pengumuman). Yang belum ada
 *    hanyalah penanda baca, karena itu ikon lonceng bisa tahu ada yang baru.
 *
 * 2. `aspirasi` — masukan/keluhan manajer ke pengelola (agrinas/HR/PIC).
 *    Belum ada jalur apa pun untuk arah ini; `broadcast` hanya satu arah
 *    (pengelola → manajer).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Satu baris = "user X sudah membaca pengumuman Y".
        // Kunci unik (user_id, siaran_id) supaya menandai baca dua kali tidak
        // menggandakan baris — dan sekali tandai, tidak perlu ditulis lagi.
        Schema::connection('hub')->create('pengumuman_baca', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('siaran_id');
            $table->timestamp('dibaca')->nullable();
            $table->unique(['user_id', 'siaran_id']);
            $table->index('user_id');
        });

        Schema::connection('hub')->create('aspirasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');            // pengirim (manajer)
            $table->string('jenis')->default('MASUKAN');      // MASUKAN | KELUHAN
            $table->string('perihal');
            $table->text('pesan');
            $table->string('koperasi')->default('');          // nama koperasi pengirim saat dikirim
            // Status penanganan pengelola. 'BARU' = belum disentuh.
            $table->string('status')->default('BARU');        // BARU | DIBACA | SELESAI
            $table->text('tanggapan')->nullable();
            $table->string('penanggap')->default('');         // email pengelola yang menjawab
            $table->timestamp('ditanggapi')->nullable();
            $table->string('dibuat')->default('');            // teks waktu, seragam `broadcast.dibuat`
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('aspirasi');
        Schema::connection('hub')->dropIfExists('pengumuman_baca');
    }
};

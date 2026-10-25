<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suara manajer pada pemungutan "Manager Memilih Sistem".
 *
 * Satu baris = satu suara per topik per manajer; kunci unik (topik, user_id)
 * yang menjamin itu, bukan pemeriksaan di aplikasi — mengubah pilihan berarti
 * menimpa baris yang sama (`updateOrCreate`), bukan menambah baris baru.
 *
 * `catatan` boleh kosong dan bukan alasan suara ditolak; kolomnya hanya
 * keterangan tambahan dari manajer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hub')->create('vote_suara', function (Blueprint $table) {
            $table->id();
            $table->string('topik');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('pilihan');           // 1 | 2 (VoteSuara::PILIHAN)
            $table->text('catatan')->nullable();
            $table->string('dibuat')->default('');            // teks waktu, seragam `aspirasi.dibuat`
            $table->string('diperbarui')->default('');
            $table->unique(['topik', 'user_id']);
            // Agregasi hasil: `group by pilihan` atas satu topik.
            $table->index(['topik', 'pilihan']);
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('vote_suara');
    }
};

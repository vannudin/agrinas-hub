<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centang "sarana ini sudah tersedia" per koperasi.
 *
 * Katalog `sarana_indikator` adalah patokan NASIONAL (50 indikator, bukan milik
 * satu koperasi). Yang perlu dicatat per koperasi hanyalah jawaban manajer atas
 * tiap indikator: sudah ada atau belum, plus keterangan bebas (jumlah, ukuran,
 * kondisi). Karena itu tabel terpisah — bukan menambah kolom di
 * `sarana_indikator`, yang akan menyalin katalog 50 baris untuk setiap koperasi.
 *
 * UNIQUE(koperasi_id, sarana_indikator_id): satu jawaban per indikator; simpan
 * ulang berarti MENIMPA, bukan menumpuk baris kembar.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        if (! $s->hasTable('koperasi_sarana_centang')) {
            $s->create('koperasi_sarana_centang', function (Blueprint $t) {
                $t->id();
                $t->integer('koperasi_id');
                $t->integer('sarana_indikator_id');
                // 1 = sudah tersedia di koperasi ini. Baris hanya dibuat untuk
                // yang DICENTANG; kotak kosong = tidak ada baris.
                $t->boolean('tersedia')->default(true);
                $t->string('keterangan', 200)->nullable();
                $t->timestamps();
                $t->unique(['koperasi_id', 'sarana_indikator_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('koperasi_sarana_centang');
    }
};

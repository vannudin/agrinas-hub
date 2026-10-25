<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua hal dalam satu migrasi, keduanya untuk halaman ringkasan /kop:
 *
 * 1. `koperasi_karyawan` — manajer mencatat karyawannya sendiri (permintaan Bos
 *    2026-10-25). Belum ada tabelnya; `users` bukan tempatnya karena karyawan
 *    belum tentu punya akun Hub.
 * 2. `koperasi_sarana_centang.kondisi` — centang sarana dulu hanya "ada/tidak",
 *    sehingga pertanyaan "berapa sarana RUSAK" mustahil dijawab. Nilai:
 *    BAIK (default, baris lama ikut terbaca lengkap) atau RUSAK.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        Schema::connection('hub')->create('koperasi_karyawan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_id')->index();
            $t->string('nama', 120);
            $t->string('jabatan', 60)->default('');
            $t->string('telepon', 40)->default('');
            $t->date('tanggal_masuk')->nullable();
            $t->integer('aktif')->default(1);
            $t->string('catatan', 200)->default('');
            $t->timestamps();
        });

        Schema::connection('hub')->table('koperasi_sarana_centang', function (Blueprint $t) {
            $t->string('kondisi', 8)->default('BAIK');
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('koperasi_karyawan');
        Schema::connection('hub')->table('koperasi_sarana_centang', fn (Blueprint $t) => $t->dropColumn('kondisi'));
    }
};

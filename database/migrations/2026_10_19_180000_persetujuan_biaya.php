<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approval = pengajuan BIAYA OPERASIONAL gerai koperasi (listrik, air, service
 * AC, perjalanan dinas, rembes pembelian, …) yang diputuskan admin/keuangan.
 *
 * Pengajuan seperti ini butuh dua hal yang tidak dimiliki pengajuan umum:
 * nominal uang dan tanggal biaya dibutuhkan — tanpa keduanya pemutus tidak
 * bisa menilai, dan petugas keuangan tidak tahu berapa yang harus dibayar.
 *
 * `jenis` yang sudah ada dipakai ulang sebagai kategori biaya; tidak ada tabel
 * baru, karena bentuk datanya sama (satu pengajuan = satu baris).
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);
        if (! $s->hasTable('persetujuan')) {
            return;
        }

        $s->table('persetujuan', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('persetujuan', 'nominal')) {
                $t->integer('nominal')->nullable();
            }
            if (! $s->hasColumn('persetujuan', 'tanggal')) {
                $t->string('tanggal', 10)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('persetujuan', function (Blueprint $t) {
            $t->dropColumn(['nominal', 'tanggal']);
        });
    }
};

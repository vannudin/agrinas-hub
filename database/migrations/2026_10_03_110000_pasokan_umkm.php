<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pasokan dari UMKM lokal ke koperasi — di luar jalur distribusi Agrinas.
 *
 * Distribusi Agrinas punya siklus sendiri (DIAJUKAN->DIKIRIM->DITERIMA) dengan
 * stok gudang pusat. Pasokan UMKM berbeda: manajer mencatat pembelian/kerja
 * sama dari UMKM sekitarnya, dan admin Agrinas ikut memantau. Karena itu tabel
 * terpisah, bukan menumpang `distribusi` dengan kolom penanda.
 *
 * Status sederhana: DIAJUKAN -> DISETUJUI -> DITERIMA, atau DITOLAK.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasTable('pasokan')) {
            $s->create('pasokan', function (Blueprint $t) {
                $t->id();
                $t->string('kode')->unique();
                $t->integer('koperasi_id');
                $t->string('umkm_nama');
                $t->string('umkm_kontak')->default('');
                $t->string('umkm_alamat')->default('');
                $t->string('komoditas');
                $t->integer('jumlah')->default(0);
                $t->string('satuan')->default('KG');
                $t->integer('harga_satuan')->default(0);   // rupiah
                $t->string('tanggal');
                $t->string('status')->default('DIAJUKAN');
                $t->string('catatan')->default('');
                $t->string('catatan_agrinas')->default('');
                $t->integer('pemohon')->nullable();
                $t->string('dibuat');
                $t->string('diperbarui');
                $t->index('koperasi_id');
                $t->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('pasokan');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur distribusi barang Agrinas -> koperasi.
 *
 * `barang.stok`  = stok gudang Agrinas (berkurang saat barang dikirim).
 * `distribusi`   = satu surat jalan/pengajuan; status berjalan
 *                  DIAJUKAN -> DIKIRIM -> DITERIMA (atau DITOLAK).
 * `jumlah_diterima` diisi manajer saat barang tiba; stok sisi koperasi
 * dihitung dari kolom itu (tidak perlu tabel stok terpisah).
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasTable('barang')) {
            $s->create('barang', function (Blueprint $t) {
                $t->id();
                $t->string('kode')->unique();
                $t->string('nama');
                $t->string('satuan')->default('SAK');
                $t->string('kategori')->default('PANGAN');
                $t->integer('stok')->default(0);
                $t->integer('stok_min')->default(0);     // ambang peringatan stok menipis
                $t->string('catatan')->default('');
                $t->string('dibuat');
                $t->string('diperbarui');
            });
        }

        if (! $s->hasTable('distribusi')) {
            $s->create('distribusi', function (Blueprint $t) {
                $t->id();
                $t->string('kode')->unique();
                $t->integer('koperasi_id');
                $t->integer('pemohon')->nullable();      // user id manajer pengaju
                $t->string('status')->default('DIAJUKAN');
                $t->string('catatan')->default('');          // dari manajer
                $t->string('catatan_agrinas')->default('');  // dari Agrinas (alasan tolak/no. kendaraan)
                $t->string('dibuat');
                $t->string('diperbarui');
            });
        }

        if (! $s->hasTable('distribusi_item')) {
            $s->create('distribusi_item', function (Blueprint $t) {
                $t->id();
                $t->integer('distribusi_id');
                $t->integer('barang_id');
                $t->integer('jumlah');                   // diminta
                $t->integer('jumlah_diterima')->default(0); // diterima manajer
            });
        }
    }

    public function down(): void
    {
        foreach (['distribusi_item', 'distribusi', 'barang'] as $t) {
            Schema::connection('hub')->dropIfExists($t);
        }
    }
};

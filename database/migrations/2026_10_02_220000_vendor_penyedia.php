<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor penyedia barang subsidi untuk gudang Agrinas.
 *
 * `barang.vendor_id` adalah kaitan yang membuat daftar vendor berguna: tiap
 * jenis stok tahu siapa penyedianya (pupuk dari Pupuk Indonesia, beras dari
 * BULOG, Minyakita dari ID FOOD, dst). Nullable supaya katalog lama tetap sah
 * walau penyedianya belum dicatat.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasTable('vendor')) {
            $s->create('vendor', function (Blueprint $t) {
                $t->id();
                $t->string('kode')->unique();
                $t->string('nama');
                $t->string('jenis')->default('BUMN');   // peran dalam rantai pasok
                $t->string('komoditas')->default('');
                $t->string('kontak')->default('');
                $t->string('catatan')->default('');
                $t->string('dibuat');
                $t->string('diperbarui');
            });
        }

        if (! $s->hasColumn('barang', 'vendor_id')) {
            $s->table('barang', fn (Blueprint $t) => $t->integer('vendor_id')->nullable());
        }
    }

    public function down(): void
    {
        $s = Schema::connection('hub');
        if ($s->hasColumn('barang', 'vendor_id')) {
            $s->table('barang', fn (Blueprint $t) => $t->dropColumn('vendor_id'));
        }
        $s->dropIfExists('vendor');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan keuangan harian koperasi.
 *
 * Satu baris = satu hari satu koperasi (kunci unik `koperasi_id`+`tanggal`),
 * berisi total pemasukan & pengeluaran plus rinciannya. Angka disimpan sebagai
 * bilangan bulat rupiah — tidak ada pecahan sen di pembukuan koperasi, dan
 * integer menghindari galat pembulatan float saat direkap.
 *
 * Laporan mingguan/bulanan/tahunan TIDAK disimpan sebagai tabel sendiri: itu
 * hanya SUM dari baris-baris ini per periode. Satu sumber angka, tak ada dua
 * tabel yang bisa saling menyimpang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasTable('keuangan')) {
            $s->create('keuangan', function (Blueprint $t) {
                $t->id();
                $t->integer('koperasi_id');
                $t->string('tanggal');                    // YYYY-MM-DD
                $t->integer('pemasukan')->default(0);      // rupiah
                $t->integer('pengeluaran')->default(0);    // rupiah
                $t->string('rincian_pemasukan')->default('');
                $t->string('rincian_pengeluaran')->default('');
                $t->string('catatan')->default('');
                $t->integer('dicatat_oleh')->nullable();
                $t->string('dibuat');
                $t->string('diperbarui');
                $t->unique(['koperasi_id', 'tanggal']);
                $t->index('tanggal');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('keuangan');
    }
};

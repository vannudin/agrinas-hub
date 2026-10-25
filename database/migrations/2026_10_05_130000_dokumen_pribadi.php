<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumen PRIBADI + obrolan yang benar-benar pribadi.
 *
 * `dokumen.pribadi` = 1 bila dokumen hanya milik pembuatnya (Dashboard pribadi),
 * 0 bila dokumen bersama koperasi. Sebelumnya semua dokumen milik koperasi/umum,
 * jadi tidak ada tempat menyimpan catatan atau lembar kerja milik sendiri.
 *
 * `obrolan_ruang.pemilik_id` menutup lubang privasi yang muncul dari dokumen
 * pribadi: ruang obrolan yang menempel pada dokumen pribadi TIDAK boleh memakai
 * `koperasi_id = NULL` sebagai "umum" — itu membuat pembahasannya terbaca semua
 * orang. Dengan pemilik_id, ruang hanya terlihat oleh pemiliknya (dan pengelola
 * lingkup bila ruangnya memang milik koperasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasColumn('dokumen', 'pribadi')) {
            $s->table('dokumen', function (Blueprint $t) {
                $t->integer('pribadi')->default(0);
                $t->integer('pemilik_id')->nullable();
            });
        }

        if (! $s->hasColumn('obrolan_ruang', 'pemilik_id')) {
            $s->table('obrolan_ruang', fn (Blueprint $t) => $t->integer('pemilik_id')->nullable());
        }
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        foreach (['dokumen' => ['pribadi', 'pemilik_id'], 'obrolan_ruang' => ['pemilik_id']] as $tabel => $kolom) {
            foreach ($kolom as $k) {
                if ($s->hasColumn($tabel, $k)) {
                    $s->table($tabel, fn (Blueprint $t) => $t->dropColumn($k));
                }
            }
        }
    }
};

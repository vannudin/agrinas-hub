<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percakapan pribadi antar pengguna Hub + keanggotaan grup.
 *
 * Sebelumnya ruang obrolan hanya punya dua bentuk akses: "umum" (koperasi_id
 * NULL) atau "lingkup koperasi". Itu cukup untuk forum, tapi TIDAK bisa
 * mengungkapkan percakapan dua orang: ruang tanpa koperasi terbaca semua orang.
 *
 * Karena itu ditambah kolom `kunci` sebagai penanda jenis akses:
 *   ''        -> ruang terbuka (perilaku lama, tidak berubah)
 *   'pribadi' -> hanya dua peserta di `obrolan_anggota`
 *   'anggota' -> hanya peserta terdaftar di `obrolan_anggota`
 *
 * Kunci percakapan pribadi = "min:max" dari dua id pengguna, jadi satu
 * percakapan A-B tidak pernah terbelah menjadi dua ruang (A-B dan B-A).
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasColumn('obrolan_ruang', 'kunci')) {
            $s->table('obrolan_ruang', function (Blueprint $t) {
                $t->string('kunci', 64)->default('');
                $t->index('kunci');
            });
        }

        if (! $s->hasTable('obrolan_anggota')) {
            $s->create('obrolan_anggota', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('ruang_id');
                $t->integer('user_id');
                $t->string('dibuat')->default('');
                $t->unique(['ruang_id', 'user_id'], 'obrolan_anggota_unik');
                $t->index('user_id');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        $s->dropIfExists('obrolan_anggota');
        if ($s->hasColumn('obrolan_ruang', 'kunci')) {
            $s->table('obrolan_ruang', fn (Blueprint $t) => $t->dropColumn('kunci'));
        }
    }
};

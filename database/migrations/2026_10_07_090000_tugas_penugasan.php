<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan tugas: siapa yang mengerjakan, dan keterangan dari admin.
 *
 * Sebelum ini setiap tugas selalu milik satu koperasi, jadi "siapa pelaksananya"
 * hanya bisa dijawab "manajer koperasi itu". Admin pusat & PIC wilayah perlu
 * menugaskan pekerjaan ANTAR pengelola (mis. PIC wilayah A membuat tugas untuk
 * PIC wilayah B, atau pusat menunjuk manajer tertentu), jadi pelaksananya harus
 * bisa ditulis eksplisit.
 *
 *   petugas_id       : pengguna tertentu sebagai pelaksana. NULL = kembali ke
 *                      perilaku lama (manajer koperasi tugas).
 *   petugas_peran    : 'PIC' | 'MANAJER' — dipakai saat petugas_id kosong tapi
 *                      jenis pelaksananya penting (mis. "semua PIC wilayah X").
 *   petugas_wilayah  : hanya untuk peran PIC; kosong = ikut wilayah koperasi.
 *   catatan_agrinas  : SUDAH ADA di tabel (kolom warisan) dan belum dipakai
 *                      untuk tugas — inilah keterangan dari admin. Tidak dibuat
 *                      kolom baru supaya tidak ada dua tempat untuk hal sama.
 *
 * Index pada petugas_id karena daftar "Untuk Saya" menyaring kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        $s->table('tugas', function (Blueprint $t) {
            $t->integer('petugas_id')->nullable();
            $t->string('petugas_peran', 8)->default('MANAJER');
            $t->string('petugas_wilayah', 60)->default('');
            $t->index('petugas_id');
        });
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        $s->table('tugas', function (Blueprint $t) {
            $t->dropIndex(['petugas_id']);
            $t->dropColumn(['petugas_id', 'petugas_peran', 'petugas_wilayah']);
        });
    }
};

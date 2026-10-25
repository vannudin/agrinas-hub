<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tampilan khusus akun PIC: `v_pic`.
 *
 * Akun PIC sengaja TIDAK dibuatkan tabel sendiri — login, jurnal, dan seluruh
 * penjagaan hak akses memakai satu tabel `users` (kolom `peran` = 'PIC' dan
 * `wilayah`), jadi tidak ada dua sumber kebenaran yang bisa berselisih.
 * Yang ditambahkan di sini hanya tampilan baca-saja supaya PIC bisa dilihat
 * dan diperiksa langsung lewat alat SQL tanpa menulis WHERE berulang:
 *
 *   select * from v_pic;
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('hub')->hasTable('users')) {
            return;
        }

        DB::connection('hub')->statement(
            "create view if not exists v_pic as
             select id, email, nama, wilayah, aktif, wajib_ganti, masuk_terakhir, dibuat
             from users where peran = 'PIC'"
        );
    }

    public function down(): void
    {
        DB::connection('hub')->statement('drop view if exists v_pic');
    }
};

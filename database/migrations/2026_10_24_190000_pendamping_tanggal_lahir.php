<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal lahir untuk AKTIVASI akun pendamping.
 *
 * Akun hasil impor daftar email SIMKOPDES belum punya sandi: pemiliknya
 * membuktikan diri dengan tanggal lahir, lalu membuat sandi Hub sendiri.
 * Disimpan di `users`, BUKAN di `profil.tanggal_lahir` — kolom profil berarti
 * "sudah ditarik dari SIMKOPDES" dan dipakai meja HR menghitung profil lengkap;
 * menaruh 33 ribu tanggal di sana akan mengacaukan hitungan itu.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        if ($s->hasTable('users') && ! $s->hasColumn('users', 'tanggal_lahir')) {
            $s->table('users', fn (Blueprint $t) => $t->string('tanggal_lahir', 10)->default(''));
        }
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);

        if ($s->hasColumn('users', 'tanggal_lahir')) {
            $s->table('users', fn (Blueprint $t) => $t->dropColumn('tanggal_lahir'));
        }
    }
};

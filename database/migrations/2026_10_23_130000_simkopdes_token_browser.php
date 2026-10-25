<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token SIMKOPDES yang diperoleh DARI BROWSER pengguna sendiri.
 *
 * Sejak 23-10-2026 endpoint login SIMKOPDES (`POST /api/login`) menjawab 403
 * untuk semua email, dari IP server maupun lewat relay Cloudflare — halaman
 * login mereka memuat reCAPTCHA. Artinya login otomatis dari server sudah tidak
 * mungkin, dan satu-satunya jalan yang jujur adalah membiarkan pengguna login
 * sendiri di browser (IP + captcha mereka), lalu menyerahkan token sesinya ke
 * Hub. Kolom ini menyimpan token itu, terenkripsi dengan APP_KEY.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        if (! $s->hasTable('users')) {
            return;
        }
        // Cek kolom di LUAR closure: objek Schema tidak tertangkap otomatis.
        if (! $s->hasColumn('users', 'simkopdes_token')) {
            $s->table('users', fn (Blueprint $t) => $t->text('simkopdes_token')->nullable());
        }
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);

        if ($s->hasTable('users') && $s->hasColumn('users', 'simkopdes_token')) {
            $s->table('users', fn (Blueprint $t) => $t->dropColumn('simkopdes_token'));
        }
    }
};

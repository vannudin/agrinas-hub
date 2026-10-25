<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi AgriConnect per pengguna Hub.
 *
 * Token AgriConnect terikat SATU sesi per akun (login lain mematikan milik kita),
 * dan base datanya berbeda per akun (dibaca dari klaim JWT `instance_api`), jadi
 * sesi tidak bisa disimpan di cache bersama ataupun di .env. Disimpan terenkripsi
 * (Laravel `Crypt`) di baris user — BUKAN sandi, hanya token.
 *
 * ponytail: kolom langsung di `users`, bukan tabel `agri_sesi` — satu baris per
 * pengguna, tidak ada riwayat yang perlu disimpan.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        Schema::connection('hub')->table('users', function (Blueprint $t) {
            if (! Schema::connection('hub')->hasColumn('users', 'agri_sesi')) {
                $t->text('agri_sesi')->nullable();
            }
            if (! Schema::connection('hub')->hasColumn('users', 'agri_username')) {
                $t->string('agri_username')->nullable();
            }
            if (! Schema::connection('hub')->hasColumn('users', 'agri_tersambung_pada')) {
                $t->timestamp('agri_tersambung_pada')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->table('users', function (Blueprint $t) {
            foreach (['agri_sesi', 'agri_username', 'agri_tersambung_pada'] as $k) {
                if (Schema::connection('hub')->hasColumn('users', $k)) {
                    $t->dropColumn($k);
                }
            }
        });
    }
};

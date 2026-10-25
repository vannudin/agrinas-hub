<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `rekrutmen_pelamar` — pelamar dari luar (belum punya akun Hub) beserta
 * keputusannya. Tahap yang sudah bisa dijawab Hub berhenti di keputusan:
 * MENUNGGU -> DITERIMA / DITOLAK / TARIK_DIRI.
 *
 * Keputusan DITERIMA mengangkat pelamar jadi pegawai lewat jalur yang sama
 * dengan pembuatan akun (hub:hr / form /admin/akun) lalu menghubungkan
 * `user_id` ke baris ini — jadi rekrutmen dan onboarding tidak jadi dua
 * kenyataan yang bisa berbeda.
 *
 * Wawancara, penawaran, dan kontrak kerja SENGAJA tidak dimodelkan: itu alur
 * HRIS, dan menyalin layarnya di sini hanya menghasilkan kolom yang selalu
 * kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if ($s->hasTable('rekrutmen_pelamar')) {
            return;
        }

        $s->create('rekrutmen_pelamar', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 120);
            $t->string('email', 160)->default('');
            $t->string('telepon', 32)->default('');
            $t->string('jabatan', 120)->default('');
            $t->string('unit', 120)->default('');
            $t->string('sumber', 80)->default('');            // dari mana lamaran datang
            $t->string('status', 16)->default('MENUNGGU');    // MENUNGGU | DITERIMA | DITOLAK | TARIK_DIRI
            $t->string('catatan', 500)->default('');
            $t->string('dilamar', 10)->default('');
            $t->integer('user_id')->nullable();               // terisi setelah diangkat
            $t->integer('dicatat_oleh')->nullable();
            $t->string('diputus_pada', 19)->default('');
            $t->string('diputus_oleh')->default('');
            $t->string('dibuat', 19)->default('');

            $t->index('status');
            $t->index('email');
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('rekrutmen_pelamar');
    }
};

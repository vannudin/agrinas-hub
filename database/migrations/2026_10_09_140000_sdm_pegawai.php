<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sdm_pegawai` — lembar kepegawaian yang MELEKAT pada akun: status
 * (aktif/cuti/nonaktif), masa kerja, jabatan/unit, atasan, dan penilaian
 * kinerja berperiode (nilai + catatan + siapa yang menilai).
 *
 * Kenapa SATU tabel, bukan empat (status / penilaian / atasan / slip):
 * keempatnya berbagi kunci yang sama (satu pegawai = satu baris) dan selalu
 * dibaca bersamaan di satu meja. Memecahnya memaksa join hanya untuk menampilkan
 * satu baris. Riwayat penilaian antar-periode memang hilang — itu batas yang
 * disadari: perubahan dianggap koreksi data, bukan babak yang harus diarsipkan.
 * ponytail: satu nilai per orang; naikkan ke tabel riwayat bila audit penilaian
 * per-periode diminta.
 *
 * Yang SENGAJA tidak disimpan: angka rupiah apa pun (gaji, tunjangan, potongan,
 * pajak). Penggajian dijalankan HRIS — tabel di sini hanya memuat rujukan
 * nomor/nama berkas supaya HR dan pegawai melihat slip yang SAMA, tanpa Hub
 * memegang duplikat angka yang bisa berbeda dari sumbernya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if ($s->hasTable('sdm_pegawai')) {
            return;
        }

        $s->create('sdm_pegawai', function (Blueprint $t) {
            $t->id();
            // Satu baris per akun: UNIQUE menahan dua lembar untuk orang yang sama.
            $t->integer('user_id')->unique();

            // Siklus kepegawaian (onboarding -> aktif -> offboarding).
            $t->string('status', 12)->default('AKTIF');   // AKTIF | CUTI | NONAKTIF
            $t->string('tanggal_awal', 10)->default('');   // onboarding
            $t->string('tanggal_akhir', 10)->default('');  // offboarding (kosong = masih jalan)
            $t->string('jabatan', 120)->default('');
            $t->string('unit', 120)->default('');
            $t->integer('atasan_id')->nullable();

            // Penilaian kinerja berperiode. `periode_nilai` = 'YYYY-MM'.
            $t->integer('nilai')->nullable();              // 1..100
            $t->string('periode_nilai', 7)->default('');
            $t->string('catatan_nilai', 500)->default('');
            $t->integer('nilai_oleh')->nullable();         // penilai (users.id)
            $t->string('nilai_pada', 19)->default('');

            // Rujukan slip gaji: nomor + nama berkas di HRIS. TANPA angka rupiah.
            $t->string('slip_nomor', 80)->default('');
            $t->string('slip_berkas', 160)->default('');
            $t->string('slip_pada', 19)->default('');
            $t->string('slip_oleh', 80)->default('');

            // Siklus rekrutmen -> onboarding: pelamar yang DITERIMA diangkat jadi
            // pegawai. Kuncinya `user_id` setelah onboarding, atau `email` sebelum.
            $t->string('rekrut_status', 16)->default('');  // '' | DITERIMA | DITOLAK | TARIK_DIRI
            $t->string('rekrut_pada', 19)->default('');
            $t->integer('rekrut_oleh')->nullable();

            $t->string('catatan', 500)->default('');
            $t->integer('dicatat_oleh')->nullable();
            $t->string('dibuat', 19)->default('');
            $t->string('diubah', 19)->nullable();

            $t->index('status');
            $t->index('periode_nilai');
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('sdm_pegawai');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presensi bergaya SIMKOPDES: Check In disertai FOTO dan KOORDINAT lokasi,
 * keterangan tetap opsional.
 *
 * Kolomnya ditambahkan ke `agri_presensi` yang sudah ada, bukan ke tabel baru:
 * foto dan koordinat adalah SIFAT dari satu catatan presensi, bukan entitas
 * tersendiri. Masuk dan keluar menyimpan pasangannya sendiri karena keduanya
 * bisa terjadi di tempat yang berbeda.
 *
 * Koordinat disimpan `double` apa adanya, TANPA validasi jarak/radius: hub ini
 * mencatat, bukan memutuskan. Kalau kelak ada kantor yang perlu digerbangi
 * radius, tambahkan titik acuan per koperasi lalu bandingkan di sisi server —
 * jangan pura-pura menggerbangi sekarang dengan angka yang belum ada.
 *
 * `agri_presensi` sengaja TIDAK memakai unique(user_id, tanggal) di migrasi
 * aslinya; keunikan hari ini ditegakkan controller. Migrasi ini tidak
 * mengubahnya — menambah unique di sini akan gagal kalau baris lama kembar.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);
        if (! $s->hasTable('agri_presensi')) {
            return;
        }

        // Cek kolom di LUAR closure: `$s` tidak tertangkap otomatis oleh closure
        // Schema::table, dan memanggilnya di dalam sana adalah error fatal.
        if ($s->hasColumn('agri_presensi', 'masuk_foto') || $s->hasColumn('agri_presensi', 'keluar_foto')) {
            return;
        }

        $s->table('agri_presensi', function (Blueprint $t) {
            $t->string('masuk_foto')->default('');
            $t->string('masuk_lat')->nullable();
            $t->string('masuk_lng')->nullable();
            $t->string('masuk_akurasi')->nullable();
            $t->string('masuk_lokasi')->default('');
            $t->string('masuk_pada')->default('');
            $t->string('keluar_foto')->default('');
            $t->string('keluar_lat')->nullable();
            $t->string('keluar_lng')->nullable();
            $t->string('keluar_akurasi')->nullable();
            $t->string('keluar_lokasi')->default('');
            $t->string('keluar_pada')->default('');
        });
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);
        foreach (['masuk_foto', 'masuk_lat', 'masuk_lng', 'masuk_akurasi', 'masuk_lokasi', 'masuk_pada',
                  'keluar_foto', 'keluar_lat', 'keluar_lng', 'keluar_akurasi', 'keluar_lokasi', 'keluar_pada'] as $k) {
            if ($s->hasColumn('agri_presensi', $k)) {
                $s->table('agri_presensi', fn (Blueprint $t) => $t->dropColumn($k));
            }
        }
    }
};

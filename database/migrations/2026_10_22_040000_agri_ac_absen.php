<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cermin absensi AgriConnect (time-sheet) — supaya pemantau di Hub (admin, HR,
 * PIC, keuangan) bisa MELIHAT kehadiran manajer tanpa akun AgriConnect sendiri.
 *
 * Kenapa cermin, bukan panggilan langsung: API mobile AgriConnect hanya mau
 * menjawab time-sheet MILIK pemilik token (parameter employmentId/employeeId
 * diabaikan, sudah diuji), dan endpoint integrasi resmi
 * `/api/integration/v1/employee/checkinout` menolak token pengguna (401, butuh
 * API key integrasi). Jadi satu-satunya jalan tanpa kredensial baru: tiap orang
 * yang sudah menyambung AgriConnect menarik time-sheet-nya sendiri, hasilnya
 * disimpan di sini, dan pemantau membaca cermin ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = DB::connection('hub');

        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agri_ac_absen (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                tanggal VARCHAR(10) NOT NULL,
                masuk VARCHAR(25) NOT NULL DEFAULT '',
                keluar VARCHAR(25) NOT NULL DEFAULT '',
                telat INTEGER NOT NULL DEFAULT 0,
                pulang_awal INTEGER NOT NULL DEFAULT 0,
                alpha INTEGER NOT NULL DEFAULT 0,
                libur INTEGER NOT NULL DEFAULT 0,
                jam_kerja VARCHAR(8) NOT NULL DEFAULT '',
                remark VARCHAR(255) NOT NULL DEFAULT '',
                disinkron VARCHAR(40) NOT NULL DEFAULT ''
            )
        SQL);

        // Satu baris per orang per tanggal: tarikan berikutnya MENIMPA, tidak
        // menumpuk. Tanpa ini, tiap kali halaman dibuka jumlah baris bertambah
        // dan rekap "hadir bulan ini" jadi berkali lipat.
        $c->statement('CREATE UNIQUE INDEX IF NOT EXISTS ux_ac_absen_orang_tanggal ON agri_ac_absen (user_id, tanggal)');
        $c->statement('CREATE INDEX IF NOT EXISTS ix_ac_absen_tanggal ON agri_ac_absen (tanggal)');
    }

    public function down(): void
    {
        DB::connection('hub')->statement('DROP TABLE IF EXISTS agri_ac_absen');
    }
};

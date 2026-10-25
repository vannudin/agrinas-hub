<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Absen Agrinashub — pencatatan kehadiran milik sistem ini sendiri.
 *
 * BENTUKNYA EVENT, BUKAN DUA KOLOM. Absen SIMKOPDES menyimpan `masuk` dan
 * `keluar` saja, sehingga istirahat tidak punya tempat dan durasi kerja bersih
 * tidak bisa dihitung. Di sini setiap kejadian adalah satu baris: masuk,
 * istirahat_mulai, istirahat_selesai, keluar. Konsekuensinya jam kerja bersih,
 * jumlah istirahat, dan pola hari kerja bisa dihitung tanpa menebak.
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = DB::connection('hub');

        $c->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS agri_absen_hub (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                koperasi_id INTEGER,
                tanggal VARCHAR(10) NOT NULL,
                jenis VARCHAR(20) NOT NULL,
                waktu VARCHAR(5) NOT NULL,
                pada VARCHAR(40) NOT NULL DEFAULT '',
                zona VARCHAR(64) NOT NULL DEFAULT '',
                foto VARCHAR(160) NOT NULL DEFAULT '',
                lat VARCHAR(32),
                lng VARCHAR(32),
                akurasi VARCHAR(32),
                lokasi VARCHAR(255) NOT NULL DEFAULT '',
                catatan VARCHAR(200) NOT NULL DEFAULT '',
                jarak_m INTEGER,
                ip VARCHAR(64) NOT NULL DEFAULT '',
                perangkat VARCHAR(255) NOT NULL DEFAULT '',
                dibuat VARCHAR(40) NOT NULL DEFAULT ''
            )
        SQL);

        $c->statement('CREATE INDEX IF NOT EXISTS ix_absen_hub_orang ON agri_absen_hub (user_id, tanggal)');
        $c->statement('CREATE INDEX IF NOT EXISTS ix_absen_hub_tanggal ON agri_absen_hub (tanggal)');

        // Satu "masuk" dan satu "keluar" per orang per hari. Istirahat boleh
        // berkali-kali, jadi yang dikunci hanya kedua ujungnya. Ini yang membuat
        // tombol yang tertekan dua kali (jaringan lambat, dua tab) tidak
        // menghasilkan dua jam masuk yang berbeda.
        // SATU index per jenis, bukan satu index untuk keduanya: index parsial
        // berlaku pada seluruh baris yang lolos WHERE, jadi `WHERE jenis IN
        // ('masuk','keluar')` membuat MASUK dan KELUAR saling bentrok — hari
        // pertama uji, "keluar" ditolak UNIQUE. Dipisah, masing-masing mengunci
        // ujungnya sendiri.
        $c->statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS ux_absen_masuk
            ON agri_absen_hub (user_id, tanggal) WHERE jenis = 'masuk'
        SQL);
        $c->statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS ux_absen_keluar
            ON agri_absen_hub (user_id, tanggal) WHERE jenis = 'keluar'
        SQL);
    }

    public function down(): void
    {
        DB::connection('hub')->statement('DROP TABLE IF EXISTS agri_absen_hub');
    }
};

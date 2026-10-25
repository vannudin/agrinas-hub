<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul AgriConnect di Agrinas Hub.
 *
 * Menu ini mengadopsi modul self-service aplikasi AgriConnect (Agrinas HRIS)
 * yang bisa dijalankan dengan data yang BENAR-BENAR ada di hub ini. Modul yang
 * tidak punya datanya sengaja tidak ditiru sebagai layar kosong, melainkan
 * ditautkan ke modul Hub yang sudah mengerjakan hal yang sama:
 *
 *   Messenger / Docs & Sheets     -> Ruang Kerja (obrolan_ruang, dokumen)
 *   Kalender & Approval           -> Ruang Kerja (konferensi, persetujuan)
 *   Koperasi & tim (My Team)      -> Koperasi + Akun (scope peran sudah jalan)
 *   Slip gaji / absensi payroll   -> tidak ada di hub: SDM & payroll tidak di sini
 *   Financial wellness, Perform   -> tumpang tindih Penugasan + poin StarPoint
 *
 * Tabel di bawah ini adalah yang benar-benar baru. Delapan tabel, satu berkas,
 * karena semuanya satu modul dan tidak ada gunanya memecahnya.
 *
 *   agri_presensi   Check In / Check Out mandiri (Time)
 *   agri_tugas      tugas pribadi, OVERTIME dan REMINDER (Task)
 *   agri_poin       buku besar StarPoint: satu baris = satu mutasi saldo
 *   agri_imbalan    katalog hadiah yang bisa ditukar poin (Marketplace)
 *   agri_umpan      umpan balik antar-rekan (Feedback)
 *   agri_berkas     berkas panduan/regulasi (Regulation)
 *   agri_materi     pelajaran E-Learning berbasis teks (E-Learn)
 *   agri_pengajuan  SATU tabel untuk tiga alur yang bentuknya sama — Perjalanan
 *                   Dinas, Klaim Imbalan, dan pengajuan administrasi umum.
 *                   Dulu sempat dibayangkan tiga tabel; tiga tabel dengan kolom
 *                   identik hanya menambah tiga tempat untuk bug yang sama.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        // ---- Presensi: satu baris per (pengguna, tanggal). Waktu disimpan
        // sebagai jam:menit lokal, bukan timestamp, karena yang dibaca manusia
        // adalah jam masuk dan jam keluar; tanggal memisahkannya.
        $s->create('agri_presensi', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('koperasi_id')->nullable();
            $t->string('tanggal', 10);
            $t->string('masuk', 5)->default('');
            $t->string('keluar', 5)->default('');
            $t->string('catatan', 200)->default('');
            $t->string('dibuat', 19)->nullable();
            $t->unique(['user_id', 'tanggal'], 'uq_presensi_hari');
        });

        $s->create('agri_tugas', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('koperasi_id')->nullable();
            $t->string('judul', 160);
            $t->text('keterangan')->nullable();
            // TASK | OVERTIME | REMINDER — satu daftar, tiga jenis. Dulu "overtime"
            // dan "reminder" tampil sebagai menu sendiri di aplikasi padahal
            // isinya sama: daftar pekerjaan berwaktu.
            $t->string('jenis', 12)->default('TASK');
            $t->string('prioritas', 8)->default('NORMAL');
            $t->string('tanggal', 10)->default('');
            $t->string('jam', 5)->default('');
            $t->string('status', 10)->default('BARU');   // BARU | PROSES | SELESAI
            $t->string('dibuat', 19)->nullable();
            $t->index(['user_id', 'status'], 'idx_agri_tugas_user');
        });

        // ---- StarPoint: buku besar, bukan kolom saldo. Saldo = SUM(jumlah),
        // sehingga selalu bisa ditelusuri dari mana poinnya datang.
        $s->create('agri_poin', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->integer('jumlah');                        // + perolehan, - penukaran
            $t->string('hal', 160);
            $t->string('dibuat', 19)->nullable();
            $t->index('user_id', 'idx_agri_poin_user');
        });

        $s->create('agri_imbalan', function (Blueprint $t) {
            $t->id();
            $t->string('judul', 160);
            $t->string('keterangan', 300)->default('');
            $t->integer('biaya')->default(0);
            $t->integer('stok')->default(0);              // 0 = tanpa batas
            $t->integer('aktif')->default(1);
            $t->string('dibuat', 19)->nullable();
        });

        $s->create('agri_umpan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('kepada_id');
            $t->unsignedBigInteger('oleh_id');
            $t->string('jenis', 16)->default('UMUM');     // UMUM | APRESIASI | SARAN
            $t->text('isi');
            $t->integer('anonim')->default(0);
            $t->string('dibuat', 19)->nullable();
            $t->index('kepada_id', 'idx_agri_umpan_kepada');
        });

        // ---- Berkas: regulasi & panduan. Berkas nyata hidup di disk `local`,
        // kolomnya hanya metadata + nama simpan (sama pola dengan tabel materi).
        $s->create('agri_berkas', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_id')->nullable();
            $t->string('kategori', 20)->default('REGULASI');   // REGULASI | PANDUAN | TEMPLATE
            $t->string('judul', 160);
            $t->string('keterangan', 300)->default('');
            $t->string('nama_asli', 200)->default('');
            $t->string('nama_simpan', 200)->default('');
            $t->string('mime', 120)->default('');
            $t->integer('ukuran')->default(0);
            $t->unsignedBigInteger('oleh')->nullable();
            $t->string('dibuat', 19)->nullable();
        });

        $s->create('agri_materi', function (Blueprint $t) {
            $t->id();
            $t->string('judul', 160);
            $t->string('ringkas', 300)->default('');
            // Diisi = pelajaran dibuka penuh (materi teks). Kalau kosong, yang
            // tampil hanya kartu yang menautkan ke `tautan` (video/berkas).
            $t->text('isi')->nullable();
            $t->string('tautan', 300)->default('');
            $t->integer('menit')->default(0);
            $t->integer('posisi')->default(0);
            $t->unsignedBigInteger('oleh')->nullable();
            $t->string('dibuat', 19)->nullable();
        });

        // ---- Satu tabel untuk Perjalanan Dinas, Klaim Imbalan, dan pengajuan
        // umum. Kolom `nilai` = biaya perjalanan atau poin yang diklaim.
        $s->create('agri_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('koperasi_id')->nullable();
            $t->string('jenis', 20)->default('UMUM');     // PERJALANAN | IMBALAN | UMUM
            $t->string('judul', 160);
            $t->text('keterangan')->nullable();
            $t->integer('nilai')->default(0);
            $t->string('tanggal', 10)->default('');       // tanggal kejadian / berangkat
            $t->string('tanggal_akhir', 10)->default(''); // kembali (perjalanan)
            $t->string('status', 10)->default('DIAJUKAN'); // DIAJUKAN | SETUJU | TOLAK
            $t->string('catatan', 300)->default('');
            $t->unsignedBigInteger('pemutus_id')->nullable();
            $t->string('dibuat', 19)->nullable();
            $t->string('diputus', 19)->nullable();
            $t->index(['status', 'jenis'], 'idx_agri_ajuan');
        });
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);
        foreach (['agri_pengajuan', 'agri_materi', 'agri_berkas', 'agri_umpan',
                  'agri_imbalan', 'agri_poin', 'agri_tugas', 'agri_presensi'] as $t) {
            $s->dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan modul AgriConnect: ulang tahun rekan, pengumuman, konferensi,
 * kinerja, dan E-Learn terarah.
 *
 * Hanya SATU tabel baru — `agri_fokus` — dan SATU kolom baru. Tiga dari lima
 * fitur tidak butuh tabel sama sekali, karena rumahnya sudah ada di hub ini;
 * memindahkannya ke tabel modul baru hanya menghasilkan dua tempat untuk satu
 * data:
 *
 *   Konferensi / Webinar  -> tabel `konferensi` (judul, mulai, selesai, jenis)
 *                            yang sudah dipakai Ruang Kerja.
 *   Pengumuman            -> tabel `broadcast` lewat model Siaran; admin
 *                            mengirimnya di /admin/broadcast, di sini dibaca.
 *   Kinerja               -> tidak menyimpan apa pun; ia merekap `agri_presensi`,
 *                            `agri_tugas`, dan `agri_poin` yang sudah ada.
 *
 * E-Learn di aplikasi AgriConnect berbentuk path + progress. Di hub ini:
 *   - `agri_materi.langkah` = daftar langkah pelajaran, satu per baris (diisi
 *     admin). Kolom, bukan tabel: daftarnya milik PELAJARAN, bukan per akun.
 *   - `agri_fokus` = centang per akun per langkah. Unik per
 *     (akun, pelajaran, langkah) supaya mencentang dua kali tidak menggandakan
 *     baris maupun poin.
 *   - Progres tingkat pelajaran tetap memakai `lms_progres` yang sudah ada.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        if ($s->hasTable('agri_materi') && ! $s->hasColumn('agri_materi', 'langkah')) {
            $s->table('agri_materi', function (Blueprint $t) {
                $t->text('langkah')->nullable();
            });
        }

        if (! $s->hasTable('agri_fokus')) {
            $s->create('agri_fokus', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('materi_id');
                $t->string('langkah', 160);
                $t->integer('selesai')->default(0);
                $t->string('dibuat', 19)->nullable();
                $t->string('dicatat', 19)->nullable();

                $t->unique(['user_id', 'materi_id', 'langkah'], 'uq_agri_fokus_langkah');
                $t->index(['user_id', 'materi_id'], 'idx_agri_fokus_user');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);
        $s->dropIfExists('agri_fokus');
        if ($s->hasTable('agri_materi') && $s->hasColumn('agri_materi', 'langkah')) {
            $s->table('agri_materi', function (Blueprint $t) {
                $t->dropColumn('langkah');
            });
        }
    }
};

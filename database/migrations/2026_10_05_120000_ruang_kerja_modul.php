<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enam modul Ruang Kerja: Messenger, Docs & Sheets, Catatan Harian, Kalender,
 * Tasks, Wiki & Approval.
 *
 * Kalender dan Tasks SENGAJA tidak dapat tabel baru — kalender membaca tabel
 * `konferensi` (jadwal rapat sudah ada di sana) dan tabel `tugas` (tenggat),
 * sedangkan Tasks memakai tabel `tugas` + `komentar` yang sudah jalan. Yang
 * benar-benar belum ada hanya empat tabel di bawah, plus satu kolom `induk_id`
 * pada `tugas` untuk memecah tugas besar menjadi langkah kecil.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        // Messenger: satu tabel untuk ruang (grup, utas topik, atau ruang yang
        // menempel pada dokumen/rapat/tugas) dan satu untuk pesannya. Kolom
        // `acuan`/`acuan_id` membuat "kirim konteks dari dokumen atau rapat"
        // tidak perlu tabel penghubung sendiri.
        Schema::connection($this->connection)->create('obrolan_ruang', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 120);
            $t->string('jenis', 20)->default('grup');   // grup | topik | dokumen | rapat | tugas
            $t->string('acuan', 20)->default('');       // jenis acuan bila menempel (dokumen|rapat|tugas)
            $t->unsignedBigInteger('acuan_id')->nullable();
            $t->unsignedBigInteger('koperasi_id')->nullable();
            $t->unsignedBigInteger('dibuat_oleh')->nullable();
            $t->string('dibuat', 19)->nullable();
            $t->index(['jenis', 'acuan_id'], 'idx_ruang_acuan');
            $t->index('koperasi_id', 'idx_ruang_koperasi');
        });

        Schema::connection($this->connection)->create('obrolan_pesan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ruang_id');
            $t->string('oleh', 120)->default('');
            $t->string('nama', 120)->default('');
            $t->text('isi');
            // Terjemahan mesin disimpan berdampingan supaya sekali diterjemahkan
            // bisa dipakai semua peserta (tidak menembak API tiap kali dibuka).
            $t->text('terjemahan')->nullable();
            $t->string('bahasa', 12)->default('');
            $t->string('dibuat', 19)->nullable();
            $t->index('ruang_id', 'idx_pesan_ruang');
        });

        // Docs & Sheets: berkas nyata disimpan di disk local; kolom `kunci` adalah
        // document key ONLYOFFICE (harus berubah tiap versi dokumen berubah).
        Schema::connection($this->connection)->create('dokumen', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_id')->nullable();
            $t->string('judul', 160);
            $t->string('jenis', 8)->default('docx');    // docx | xlsx
            $t->string('berkas', 200);                  // path di disk local
            $t->string('kunci', 40);                    // ONLYOFFICE document key
            $t->unsignedBigInteger('dibuat_oleh')->nullable();
            $t->string('dibuat', 19)->nullable();
            $t->string('diperbarui', 19)->nullable();
            $t->index('koperasi_id', 'idx_dok_koperasi');
        });

        // Catatan harian: satu baris per akun per tanggal. Admin pusat & PIC
        // memantau (baca saja) sesuai lingkup wilayahnya.
        Schema::connection($this->connection)->create('catatan_harian', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('koperasi_id')->nullable();
            $t->string('tanggal', 10);                  // Y-m-d
            $t->text('isi')->nullable();
            $t->text('kendala')->nullable();
            $t->string('dibuat', 19)->nullable();
            $t->string('diperbarui', 19)->nullable();
            $t->unique(['user_id', 'tanggal'], 'uq_catatan_hari');
            $t->index('tanggal', 'idx_catatan_tanggal');
        });

        // Wiki: hierarki lewat `induk_id` (satu tabel, bukan dua).
        Schema::connection($this->connection)->create('wiki', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('induk_id')->nullable();
            $t->unsignedBigInteger('koperasi_id')->nullable();
            $t->string('judul', 160);
            $t->text('isi')->nullable();
            $t->unsignedBigInteger('dibuat_oleh')->nullable();
            $t->string('dibuat', 19)->nullable();
            $t->string('diperbarui', 19)->nullable();
            $t->index('induk_id', 'idx_wiki_induk');
        });

        // Persetujuan digital: pengajuan -> keputusan (SETUJU/TOLAK).
        Schema::connection($this->connection)->create('persetujuan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('koperasi_id')->nullable();
            $t->string('judul', 160);
            $t->string('jenis', 40)->default('Umum');
            $t->text('isi')->nullable();
            $t->unsignedBigInteger('pemohon_id')->nullable();
            $t->unsignedBigInteger('pemutus_id')->nullable();
            $t->string('status', 12)->default('DIAJUKAN'); // DIAJUKAN | SETUJU | TOLAK
            $t->text('catatan')->nullable();
            $t->string('dibuat', 19)->nullable();
            $t->string('diputus', 19)->nullable();
            $t->index('status', 'idx_setuju_status');
        });

        // Tugas besar dipecah jadi langkah kecil: cukup satu kolom induk.
        Schema::connection($this->connection)->table('tugas', function (Blueprint $t) {
            $t->unsignedBigInteger('induk_id')->nullable()->after('koperasi_id');
            $t->index('induk_id', 'idx_tugas_induk');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('tugas', function (Blueprint $t) {
            $t->dropIndex('idx_tugas_induk');
            $t->dropColumn('induk_id');
        });
        foreach (['persetujuan', 'wiki', 'catatan_harian', 'dokumen', 'obrolan_pesan', 'obrolan_ruang'] as $tab) {
            Schema::connection($this->connection)->dropIfExists($tab);
        }
    }
};

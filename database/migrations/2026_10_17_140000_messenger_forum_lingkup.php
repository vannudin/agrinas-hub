<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forum pengelola + grup menurut lingkup (peran / provinsi / kabupaten /
 * kecamatan / koperasi).
 *
 * TIDAK ada tabel baru. Keanggotaan tetap di `obrolan_anggota` — jadi hak
 * akses (WorkspaceController::ruangQuery) tidak perlu diubah sama sekali:
 * lingkup apa pun yang dipilih, hasilnya tetap "daftar orang" yang menjadi
 * anggota ruang. Ini yang membuat grup wilayah tidak mengubah aturan izin.
 *
 *   topik  = pembuka/pembahasan forum (forum tanpa topik = grup biasa)
 *   lingkup= dari mana pesertanya diturunkan, mis. "wilayah:JAWA BARAT",
 *            "kabupaten:BOGOR", "kecamatan:CIBADAK", "peran:PIC", "semua".
 *            '' = dipilih manual.
 *
 * Dipakai juga untuk melaporkan & menyegarkan peserta saat ada pengelola baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        $s->table('obrolan_ruang', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('obrolan_ruang', 'topik')) {
                $t->string('topik', 500)->default('');
            }
            if (! $s->hasColumn('obrolan_ruang', 'lingkup')) {
                $t->string('lingkup', 64)->default('');
                $t->index('lingkup', 'idx_ruang_lingkup');
            }
        });
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        foreach (['topik', 'lingkup'] as $kolom) {
            if ($s->hasColumn('obrolan_ruang', $kolom)) {
                $s->table('obrolan_ruang', fn (Blueprint $t) => $t->dropColumn($kolom));
            }
        }
    }
};

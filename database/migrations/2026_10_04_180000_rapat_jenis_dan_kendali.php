<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rapat jadi dua bentuk + kanal kendali.
 *
 * - `jenis`          : 'meeting' (semua orang bicara) atau 'webinar' (pembicara → penonton).
 * - `siaran_penyiar` : daftar sessionId yang BOLEH menerbitkan gambar/suara. Di webinar
 *                      hanya pembicara; di meeting setiap peserta dimasukkan saat gabung.
 * - `kendali`        : JSON satu kolom untuk obrolan, angkat tangan, reaksi, perintah
 *                      pengelola (kunci mikrofon, tendang, naikkan jadi pembicara). Satu
 *                      kanal dengan nomor urut — klien menarik "yang baru sejak N", jadi
 *                      tidak perlu tabel per fitur.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasTable('konferensi')) {
            return;
        }

        $s->table('konferensi', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('konferensi', 'jenis')) {
                $t->string('jenis', 12)->default('meeting');
            }
            if (! $s->hasColumn('konferensi', 'siaran_penyiar')) {
                $t->text('siaran_penyiar')->default('');
            }
            if (! $s->hasColumn('konferensi', 'kendali')) {
                $t->text('kendali')->default('');
            }
        });
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasTable('konferensi')) {
            return;
        }

        $s->table('konferensi', function (Blueprint $t) use ($s) {
            $t->dropColumn(array_values(array_filter([
                $s->hasColumn('konferensi', 'jenis') ? 'jenis' : null,
                $s->hasColumn('konferensi', 'siaran_penyiar') ? 'siaran_penyiar' : null,
                $s->hasColumn('konferensi', 'kendali') ? 'kendali' : null,
            ])));
        });
    }
};

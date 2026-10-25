<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kanal siaran Cloudflare Realtime untuk sebuah rapat.
 *
 * `siaran_sesi` = sessionId SFU milik PENYIAR (satu orang per rapat) dan
 * `siaran_oleh` = user yang sedang menyiarkan. Peserta lain menonton dengan
 * sessionId itu — jadi harus disimpan agar bertahan lintas proses, bukan di
 * memori PHP.
 *
 * Kosong = belum ada yang menyiarkan; rapat jatuh kembali ke mode tautan biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if ($s->hasTable('konferensi') && ! $s->hasColumn('konferensi', 'siaran_sesi')) {
            $s->table('konferensi', function (Blueprint $t) {
                $t->string('siaran_sesi', 64)->default('');
                $t->string('siaran_oleh', 64)->default('');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        if ($s->hasTable('konferensi') && $s->hasColumn('konferensi', 'siaran_sesi')) {
            $s->table('konferensi', function (Blueprint $t) {
                $t->dropColumn(['siaran_sesi', 'siaran_oleh']);
            });
        }
    }
};

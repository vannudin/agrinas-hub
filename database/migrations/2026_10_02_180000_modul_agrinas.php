<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel baru modul Agrinas (broadcast, materi/juknis, konferensi) + tabel
 * conferencing. Dibuat lewat migration, bukan oleh SKEMA Flask — Flask tidak
 * memakai tabel ini, dan sengaja tidak diubah agar dua aplikasi tetap sinkron.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasTable('broadcast')) {
            $s->create('broadcast', function (Blueprint $t) {
                $t->id();
                $t->string('kanal');                     // EMAIL | WHATSAPP
                $t->string('perihal')->default('');
                $t->text('pesan');
                $t->string('sasaran')->default('');      // SEMUA | MANAJER | AGRINAS | kop:<KODE>
                $t->integer('jumlah')->default(0);       // penerima berhasil
                $t->string('pengirim')->default('');
                $t->string('dibuat');
            });
        }

        if (! $s->hasTable('materi')) {
            $s->create('materi', function (Blueprint $t) {
                $t->id();
                $t->string('judul');
                $t->string('kategori')->default('DOKUMEN');
                $t->string('nama_asli');
                $t->string('berkas');                    // nama berkas di storage/app/materi
                $t->string('mime')->default('');
                $t->integer('ukuran')->default(0);
                $t->string('catatan')->default('');
                $t->integer('oleh')->nullable();
                $t->string('dibuat');
            });
        }

        if (! $s->hasTable('konferensi')) {
            $s->create('konferensi', function (Blueprint $t) {
                $t->id();
                $t->string('judul');
                $t->string('ruang');                     // slug ruang Jitsi
                $t->string('mulai');
                $t->string('selesai')->default('');
                $t->string('catatan')->default('');
                $t->integer('oleh')->nullable();
                $t->string('dibuat');
            });
        }
    }

    public function down(): void
    {
        foreach (['konferensi', 'materi', 'broadcast'] as $t) {
            Schema::connection('hub')->dropIfExists($t);
        }
    }
};

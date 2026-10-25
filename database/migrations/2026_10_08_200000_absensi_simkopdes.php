<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi DUA ARAH dengan SIMKOPDES.
 *
 * Tiga hal, satu tujuan: daftar hadir Hub dan SIMKOPDES tidak boleh berbeda.
 *
 * 1. `users.simkopdes_facilitator_id` — kunci yang benar untuk memadankan baris
 *    absensi SIMKOPDES ke pemiliknya di Hub. Kolom `simkopdes_id` yang lama
 *    TIDAK dipakai untuk ini: isinya tidak konsisten (jalur login mengisi
 *    user id, jalur CLI mengisi facilitator id), sementara baris absensi hanya
 *    menyebut facilitator id. Karena `simkopdes_id` cuma dipakai sebagai
 *    penanda "ini pendamping", nilainya dibiarkan apa adanya di sini.
 *
 * 2. `users.simkopdes_sandi` — sandi SIMKOPDES manajer, TERENKRIPSI (APP_KEY).
 *    Disimpan karena memang tidak ada jalan lain: menulis absensi atas nama
 *    seseorang menuntut kredensialnya sendiri (lihat SimkopdesAbsensi), dan
 *    sandi SIMKOPDES tidak bisa diturunkan dari hash di `users.sandi`.
 *    Dikosongkan begitu penanda centang sinkronisasi dilepas.
 *
 * 3. Tabel `simkopdes_absensi` — cermin daftar hadir SIMKOPDES + baris yang
 *    kita kirim ke sana. Absensi SIMKOPDES tidak bisa diubah/dihapus (API-nya
 *    tidak menyediakan itu), jadi tabel ini yang menjadi catatan idempoten:
 *    satu tanggal = satu kiriman.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        if ($s->hasTable('users')) {
            // Cek kolom di LUAR closure: objek Schema tidak tertangkap otomatis.
            if (! $s->hasColumn('users', 'simkopdes_facilitator_id')) {
                $s->table('users', fn (Blueprint $t) => $t->integer('simkopdes_facilitator_id')->nullable());
            }
            if (! $s->hasColumn('users', 'simkopdes_sandi')) {
                $s->table('users', fn (Blueprint $t) => $t->text('simkopdes_sandi')->nullable());
            }
        }

        if ($s->hasTable('simkopdes_absensi')) {
            return;
        }

        $s->create('simkopdes_absensi', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            // Id baris di SIMKOPDES. NULL = baris ini lahir di Hub (baru dikirim).
            // Unik supaya tarikan berulang tidak menggandakan baris yang sama;
            // di SQLite beberapa NULL tetap diizinkan pada kolom unik.
            $t->integer('simkopdes_absensi_id')->nullable()->unique();
            $t->integer('periode_id')->nullable();
            $t->string('tanggal', 10)->default('');
            $t->integer('koperasi_simkopdes_id')->nullable();
            $t->string('aktivitas', 200)->default('');
            $t->string('catatan', 500)->default('');
            $t->string('masuk', 5)->default('');
            $t->string('keluar', 5)->default('');
            // Titik waktu penuh dari SIMKOPDES (`Y-m-d H:i:s`), disimpan apa
            // adanya supaya jam tersimpannya bisa ditelusuri asalnya.
            $t->string('masuk_pada')->default('');
            $t->string('keluar_pada')->default('');
            $t->string('lat', 32)->nullable();
            $t->string('lng', 32)->nullable();
            // Tautan foto bertanda tangan yang berlaku ~2,5 jam. Cuplikannya
            // disalin ke disk Hub supaya bukti tidak hilang (lihat SimkopdesAbsensi).
            $t->text('foto_masuk_url')->nullable();
            $t->text('foto_keluar_url')->nullable();
            $t->string('masuk_foto')->default('');
            $t->string('keluar_foto')->default('');
            $t->string('sumber', 16)->default('SIMP');
            $t->string('kirim_pada')->default('');
            $t->string('ditarik_pada')->default('');
            $t->index(['user_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);

        if ($s->hasTable('simkopdes_absensi')) {
            $s->drop('simkopdes_absensi');
        }

        foreach (['simkopdes_facilitator_id', 'simkopdes_sandi'] as $kol) {
            if ($s->hasTable('users') && $s->hasColumn('users', $kol)) {
                $s->table('users', fn (Blueprint $t) => $t->dropColumn($kol));
            }
        }
    }
};

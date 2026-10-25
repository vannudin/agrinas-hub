<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Koneksi Lark milik MANAJER KOPERASI (fitur ini hanya untuk manajer).
 *
 * Menyimpan: identitas Lark, token OAuth (terenkripsi di model), dan hasil
 * sinkronisasi. Data sinkron disimpan sebagai JSON per kategori, bukan tabel
 * per jenis data Lark — tujuannya MENAMPILKAN kembali apa yang ada di Lark
 * (kalender, tugas, chat, berkas), bukan menggandakan seluruh model Lark ke
 * dalam basis data Hub. Kalau kelak perlu dikelola (mis. tugas Lark bisa
 * dicentang di Hub), barulah dipecah ke tabel sendiri.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        if (! $s->hasTable('lark_koneksi')) {
            $s->create('lark_koneksi', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');          // manajer pemilik koneksi
                $t->unsignedBigInteger('koperasi_id')->nullable();

                // Nomor HP = penanda yang dimasukkan manajer saat mengoneksikan,
                // dan yang dicocokkan dengan nomor pada akun Lark-nya.
                $t->string('no_hp', 32)->default('');

                $t->string('open_id', 190)->default('');
                $t->string('union_id', 190)->default('');
                $t->string('lark_user_id', 190)->default('');
                $t->string('nama', 190)->default('');
                $t->string('email', 190)->default('');
                $t->string('hp_lark', 32)->default('');
                $t->string('avatar', 300)->default('');
                $t->string('departemen', 190)->default('');
                $t->string('jabatan', 190)->default('');

                // Token OAuth — terenkripsi lewat cast di model (setara sandi).
                $t->text('access_token')->nullable();
                $t->text('refresh_token')->nullable();
                $t->string('scope', 500)->default('');
                $t->string('kedaluwarsa_pada', 19)->nullable();

                // Hasil tarikan terakhir, per kategori. JSON — lihat catatan di atas.
                $t->text('data_profil')->nullable();
                $t->text('data_kalender')->nullable();
                $t->text('data_tugas')->nullable();
                $t->text('data_chat')->nullable();
                $t->text('data_berkas')->nullable();
                $t->text('catatan_sinkron')->nullable();   // kategori mana yang gagal
                $t->string('sinkron_pada', 19)->nullable();

                $t->string('dibuat', 19)->nullable();
                $t->string('diperbarui', 19)->nullable();
                $t->unique('user_id', 'uq_lark_koneksi_user');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('lark_koneksi');
    }
};

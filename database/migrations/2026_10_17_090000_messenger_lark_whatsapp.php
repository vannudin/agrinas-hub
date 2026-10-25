<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messenger: meniru cara kerja Lark Messenger, dirasakan seperti WhatsApp.
 *
 * Lark mengelompokkan percakapan (belum dibaca, pribadi, grup, sebutan,
 * disematkan) dan menempelkan aksi PADA pesan (balas, reaksi, sematkan,
 * teruskan, sunting, tarik). WhatsApp menguatkan hal berbeda: gelembung dua
 * warna, centang dibaca, lampiran foto/berkas/suara, dan daftar yang selalu
 * tahu mana yang belum dibaca.
 *
 * Yang TIDAK dibuat tabelnya, dengan alasan:
 *   - "bisukan"  : tidak ada notifikasi untuk dibisukan (belum ada push) — YAGNI
 *   - "flag"     : kebutuhan "tandai untuk nanti" sudah ditutup `disematkan`
 *   - arsip      : tidak ada layar arsip terpisah; menyembunyikan baris tanpa
 *                  tempat menemukannya kembali = kehilangan, bukan fitur
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        // ------------------------------------------------------------- pesan
        // `kirim_pada` = pesan terjadwal (Lark "send later"): pesan tersimpan
        // sekarang, baru TERLIHAT saat waktunya lewat. NULL = kirim langsung,
        // jadi semua baris lama tetap sah tanpa diisi.
        $s->table('obrolan_pesan', function (Blueprint $t) {
            if (! Schema::connection($this->connection)->hasColumn('obrolan_pesan', 'balas_id')) {
                $t->unsignedBigInteger('balas_id')->nullable();
            }
            if (! Schema::connection($this->connection)->hasColumn('obrolan_pesan', 'jenis')) {
                $t->string('jenis', 12)->default('teks');    // teks | gambar | berkas | suara
            }
            if (! Schema::connection($this->connection)->hasColumn('obrolan_pesan', 'lampiran')) {
                $t->string('lampiran', 190)->default('');
                $t->string('lampiran_nama', 190)->default('');
                $t->unsignedBigInteger('lampiran_ukuran')->default(0);
                $t->string('lampiran_jenis', 120)->default('');
            }
            if (! Schema::connection($this->connection)->hasColumn('obrolan_pesan', 'sebut')) {
                $t->string('sebut', 190)->default('');       // ",12,44," = id yang disebut
            }
            if (! Schema::connection($this->connection)->hasColumn('obrolan_pesan', 'disematkan')) {
                $t->string('disematkan', 19)->default('');   // waktu semat; '' = tidak disematkan
            }
            if (! Schema::connection($this->connection)->hasColumn('obrolan_pesan', 'disunting')) {
                $t->string('disunting', 19)->default('');
            }
            if (! Schema::connection($this->connection)->hasColumn('obrolan_pesan', 'dihapus')) {
                $t->string('dihapus', 19)->default('');      // waktu tarik; isi lama dimusnahkan
            }
            if (! Schema::connection($this->connection)->hasColumn('obrolan_pesan', 'kirim_pada')) {
                $t->string('kirim_pada', 25)->nullable();
                $t->index('kirim_pada', 'idx_pesan_kirim_pada');
            }
        });

        // --------------------------------------------------------------- baca
        // Satu baris per (ruang, pengguna) menyimpan dua hal: sampai pesan
        // berapa ia sudah membaca, dan apakah ruangnya disematkan di puncak
        // daftar. Digabung supaya tak perlu tabel kedua untuk satu kolom.
        if (! $s->hasTable('obrolan_baca')) {
            $s->create('obrolan_baca', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('ruang_id');
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('pesan_id')->default(0);
                $t->unsignedBigInteger('dibaca_pada')->default(0);
                // Sematan percakapan (Lark "pin a chat to the top" / WhatsApp
                // "sematkan obrolan"): waktu disematkan, '' = tidak.
                $t->string('disematkan', 25)->default('');
                $t->unique(['ruang_id', 'user_id'], 'obrolan_baca_unik');
                $t->index('user_id', 'idx_baca_user');
            });
        }

        // ------------------------------------------------------------- reaksi
        if (! $s->hasTable('obrolan_reaksi')) {
            $s->create('obrolan_reaksi', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('pesan_id');
                $t->unsignedBigInteger('user_id');
                $t->string('emoji', 16);
                $t->string('dibuat', 25)->default('');
                $t->unique(['pesan_id', 'user_id', 'emoji'], 'obrolan_reaksi_unik');
                $t->index('pesan_id', 'idx_reaksi_pesan');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection($this->connection);
        $s->dropIfExists('obrolan_reaksi');
        $s->dropIfExists('obrolan_baca');

        foreach (['balas_id', 'jenis', 'lampiran', 'lampiran_nama', 'lampiran_ukuran',
            'lampiran_jenis', 'sebut', 'disematkan', 'disunting', 'dihapus', 'kirim_pada'] as $kolom) {
            if ($s->hasColumn('obrolan_pesan', $kolom)) {
                $s->table('obrolan_pesan', fn (Blueprint $t) => $t->dropColumn($kolom));
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `profil` — salinan profil SIMKOPDES per pengguna Hub (identitas, wilayah,
 * koperasi tempat bertugas, dan blob profil lengkap untuk ditampilkan).
 *
 * Dua kolom di tabel `users` perlu ditambah agar tarikan tidak menghapus
 * data milik pengguna: `simkopdes_id` (kunci upsert; dulu akun dicocokkan
 * lewat email saja) dan `sandi_sumber` (menandai sandi mana yang sah sebagai
 * jalur masuk, sehingga Hub tak perlu menyimpan sandi SIMKOPDES asli).
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasTable('profil')) {
            $s->create('profil', function (Blueprint $t) {
                $t->id();
                $t->integer('user_id')->unique();
                $t->string('sumber')->default('SIMKOPDES');

                // Identitas
                $t->integer('simkopdes_user_id')->nullable();
                $t->string('email')->default('');
                $t->string('nama')->default('');
                $t->string('niak')->default('');
                $t->string('user_code')->default('');
                $t->string('telepon')->default('');
                $t->string('peran_simkopdes')->default('');
                $t->string('gender')->default('');
                $t->string('tempat_lahir')->default('');
                $t->string('tanggal_lahir')->default('');
                $t->string('agama')->default('');
                $t->string('status_kawin')->default('');
                $t->string('golongan_darah')->default('');

                // Alamat
                $t->string('alamat')->default('');
                $t->string('kode_pos')->default('');
                $t->string('provinsi')->default('');
                $t->string('kabupaten')->default('');
                $t->string('kecamatan')->default('');
                $t->string('desa')->default('');

                // Penempatan
                $t->integer('koperasi_id')->nullable();
                $t->integer('koperasi_simkopdes_id')->nullable();
                $t->string('terverifikasi')->default('');

                // Blob profil lengkap (riwayat kerja, pendidikan, bank, documents).
                $t->text('profil')->nullable();
                $t->string('akhir_tarik')->default('');
                $t->index('simkopdes_user_id');
            });
        }

        if ($s->hasTable('users')) {
            foreach ([
                'simkopdes_id' => fn (Blueprint $t) => $t->integer('simkopdes_id')->nullable(),
                'koperasi_simkopdes_id' => fn (Blueprint $t) => $t->integer('koperasi_simkopdes_id')->nullable(),
                'sandi_sumber' => fn (Blueprint $t) => $t->string('sandi_sumber')->default('LOKAL'),
            ] as $nama => $buat) {
                if (! $s->hasColumn('users', $nama)) {
                    $s->table('users', $buat);
                }
            }

            // Login jalur 1 mencari baris users per email pada SETIAP percobaan
            // masuk, dan penarikan profil mencari per simkopdes_id.
            $s->table('users', fn (Blueprint $t) => $t->index('email', 'users_email_idx'));
            $s->table('users', fn (Blueprint $t) => $t->index('simkopdes_id', 'users_simkopdes_id_idx'));
        }
    }

    public function down(): void
    {
        $s = Schema::connection('hub');
        $s->dropIfExists('profil');

        if ($s->hasTable('users')) {
            // Index WAJIB dilepas SEBELUM kolomnya. SQLite menolak drop column
            // selama masih ada index yang menunjuk kolom itu:
            // "error in index users_simkopdes_id_idx after drop column:
            //  no such column" — dan kegagalan itu juga meninggalkan migrasi
            // di keadaan setengah jadi (tabel profil sudah terhapus, kolom
            // masih ada).
            foreach (['users_email_idx', 'users_simkopdes_id_idx'] as $index) {
                try {
                    $s->table('users', fn (Blueprint $t) => $t->dropIndex($index));
                } catch (\Throwable) {
                    // Index tidak ada (mis. migrasi belum sempat membuatnya).
                }
            }

            foreach (['simkopdes_id', 'koperasi_simkopdes_id', 'sandi_sumber'] as $nama) {
                if ($s->hasColumn('users', $nama)) {
                    $s->table('users', fn (Blueprint $t) => $t->dropColumn($nama));
                }
            }
        }
    }
};

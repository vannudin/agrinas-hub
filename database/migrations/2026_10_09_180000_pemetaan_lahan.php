<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemetaan lahan KDKMP tingkat DESA — cermin data publik
 * `pemetaan-lahan.portalkdkmp.id` (388.21 ribu → 38.821 baris, tanpa login).
 *
 * Satu baris = satu calon lokasi KDKMP: desa + kecamatan + kabupaten, luas
 * lahan, potensi, titik koordinat, dan **nama + nomor telepon Babinsa** yang
 * bertanggung jawab. Nomor Babinsa HANYA ada di sumber ini — tidak ada di
 * SIMKOPDES (aset hanya memuat `surveyor` berupa nama).
 *
 * Kenapa disalin, bukan dibaca langsung saat halaman dibuka: butuh ~750
 * permintaan untuk seluruh Indonesia dan sumbernya bisa lambat/menolak.
 * Setelah tersalin, filter & paging dikerjakan SQL — seketika.
 *
 * `pemetaan_id` = id baris di sumber; dipakai sebagai kunci idempotensi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hub')->create('pemetaan_lahan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pemetaan_id')->unique();
            $table->string('nama')->default('');          // Babinsa / penanggung jawab
            $table->string('telepon')->default('');       // nomor Babinsa (tanpa 0 depan)
            $table->string('lokasi')->default('');        // "Desa, Kecamatan, Kabupaten"
            $table->string('desa')->default('');
            $table->string('kecamatan')->default('');
            $table->string('kabupaten')->default('');
            $table->string('provinsi')->default('');
            $table->decimal('luas', 14, 2)->nullable();
            $table->string('potensi')->default('');
            $table->decimal('latitude', 16, 10)->nullable();
            $table->decimal('longitude', 16, 10)->nullable();
            // Dua filter sumber menentukan status pembangunan per baris — kolomnya
            // tidak ada di respons, jadi diturunkan dari keanggotaan himpunan.
            $table->boolean('pembangunan_100')->default(false);
            $table->boolean('pembangunan_mulai')->default(false);
            $table->timestamp('ditarik')->nullable();
            $table->timestamps();

            $table->index('provinsi');
            $table->index('kabupaten');
            $table->index('kecamatan');
            $table->index('desa');
            $table->index('pembangunan_100');
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('pemetaan_lahan');
    }
};

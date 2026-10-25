<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `kontrak` — satu baris per (pendamping × periode × penempatan), memuat tiga
 * hal sekaligus seperti yang diminta: PERIODE pendampingan, PENEMPATAN
 * (koperasi + masa tugas), dan KONTRAK (nomor, jenis, tanggal, status).
 *
 * Di SIMKOPDES ketiganya memang hidup di tingkat periode yang sama
 * (`regional_facilitator_periods` → `regional-facilitator-cooperatives` →
 * `regional-facilitator-period-contracts`), jadi memecahnya jadi tiga tabel
 * hanya akan memaksa tiga join untuk menampilkan satu baris.
 *
 * Kunci unik memakai `penempatan_koperasi_id` = 0 (bukan NULL) untuk periode
 * yang belum punya penempatan: SQLite menganggap setiap NULL berbeda, jadi
 * UNIQUE dengan kolom nullable tidak akan pernah menahan duplikat.
 *
 * `kontrak_tautan` adalah link tanda tangan (dcid.io) — stabil. Berkas PDF-nya
 * SENGAJA tidak disimpan: `contract_file` adalah URL presigned GCS berumur
 * ~1 jam, jadi menyalinnya cuma menyimpan alamat mati. Unduhan dibuat ulang
 * saat diminta lewat rute `kontrak.berkas`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if ($s->hasTable('kontrak')) {
            return;
        }

        $s->create('kontrak', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            $t->integer('periode_id');

            // Periode pendampingan (masa berlaku penugasan).
            $t->string('periode_mulai')->default('');
            $t->string('periode_selesai')->default('');
            $t->string('periode_status')->default('');
            $t->string('periode_program')->default('');

            // Penempatan: koperasi tempat bertugas + masa tugas.
            $t->integer('penempatan_koperasi_id')->default(0);
            $t->string('penempatan_nama')->default('');
            $t->string('penempatan_provinsi')->default('');
            $t->string('penempatan_wilayah')->default('');
            $t->string('penempatan_jenis')->default('');
            $t->string('penempatan_mulai')->default('');
            $t->string('penempatan_selesai')->default('');

            // Kontrak.
            $t->integer('kontrak_id')->nullable();
            $t->string('kontrak_nomor')->default('');
            $t->string('kontrak_komitmen')->default('');
            $t->string('kontrak_jenis')->default('');
            $t->string('kontrak_status')->default('');
            $t->string('kontrak_label')->default('');
            $t->string('kontrak_mulai')->default('');
            $t->string('kontrak_selesai')->default('');
            $t->string('kontrak_ttd')->default('');
            $t->string('kontrak_sumber')->default('');
            $t->string('kontrak_tautan')->default('');

            $t->string('sumber')->default('SIMKOPDES');
            $t->string('ditarik_pada')->default('');
            $t->string('dibuat')->default('');
            $t->string('diperbarui')->default('');

            $t->unique(['user_id', 'periode_id', 'penempatan_koperasi_id'], 'kontrak_unik');
            $t->index('kontrak_status', 'kontrak_status_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('hub')->dropIfExists('kontrak');
    }
};

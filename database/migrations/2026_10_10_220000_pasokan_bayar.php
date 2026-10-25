<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur pengajuan & pembayaran pasokan UMKM.
 *
 * MASALAH: pasokan UMKM dicatat lalu langsung "DIAJUKAN" tanpa berkas apa pun, dan
 * begitu Disetujui tidak ada jejak pembayaran. Padahal uangnya keluar dari kas
 * Agrinas, dan yang membayar bukan orang yang mengajukan — jadi bukti bayar harus
 * menempel pada pengajuannya.
 *
 * Bentuk yang dipilih: SATU tabel keputusan (`pasokan_bayar`) yang menyimpan
 * rekening tujuan, nomor & berkas invoice, serta bukti pembayaran. Alasan:
 *  - alur hanya satu tingkat persetujuan (bukan berjenjang), jadi tabel anak
 *    terpisah untuk "pengajuan" vs "pembayaran" akan jadi dua baris yang selalu
 *    berpasangan — pemisahan tanpa manfaat;
 *  - riwayat keputusan tetap terjaga karena setiap keputusan membuat BARIS BARU
 *    (bukan menimpa), jadi "pernah ditolak karena apa" tidak hilang.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $s = Schema::connection($this->connection);

        $s->create('pasokan_bayar', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pasokan_id');
            $t->unsignedBigInteger('koperasi_id');

            // Rekening tujuan: milik UMKM pemasok. Disimpan di sini (bukan di
            // tabel umkm) karena satu UMKM bisa berganti rekening antar transaksi,
            // dan yang harus bisa diaudit adalah rekening yang DIPAKAI saat itu.
            $t->string('bank', 60)->default('');
            $t->string('no_rekening', 40)->default('');
            $t->string('atas_nama', 120)->default('');

            // Invoice dari UMKM (boleh dari koperasi bila UMKM tidak menerbitkan).
            $t->string('invoice_nomor', 60)->default('');
            $t->string('invoice_tanggal', 10)->default('');
            $t->string('invoice_nama', 180)->default('');     // nama berkas asli
            $t->string('invoice_simpan', 200)->default('');   // path di disk
            $t->integer('invoice_ukuran')->default(0);
            $t->string('invoice_mime', 100)->default('');

            // Bukti pembayaran: diunggah PEMBAYAR (admin Agrinas / PIC).
            $t->string('bukti_nama', 180)->default('');
            $t->string('bukti_simpan', 200)->default('');
            $t->integer('bukti_ukuran')->default(0);
            $t->string('bukti_mime', 100)->default('');
            $t->string('bukti_catatan', 300)->default('');

            // status: DIAJUKAN | DISETUJUI | DITOLAK | DIBAYAR
            $t->string('status', 12)->default('DIAJUKAN');
            $t->string('catatan', 300)->default('');          // alasan penolakan / catatan
            $t->unsignedBigInteger('diajukan_oleh')->nullable();
            $t->unsignedBigInteger('diputus_oleh')->nullable();
            $t->unsignedBigInteger('dibayar_oleh')->nullable();
            $t->string('diajukan_pada', 19)->nullable();
            $t->string('diputus_pada', 19)->nullable();
            $t->string('dibayar_pada', 19)->nullable();
            $t->string('dibuat', 19)->nullable();
            $t->index(['pasokan_id', 'id'], 'idx_pasokan_bayar');
            $t->index(['koperasi_id', 'status'], 'idx_pasokan_bayar_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('pasokan_bayar');
    }
};

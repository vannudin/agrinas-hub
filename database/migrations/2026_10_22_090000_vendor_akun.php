<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun VENDOR + tagihan vendor ke Agrinas.
 *
 * Kenapa akun vendor perlu kolom di `users`, bukan tabel akun sendiri: login,
 * sandi (scrypt), aktif/nonaktif, ganti-sandi wajib, dan jurnal audit sudah
 * ditangani `users` + MasukController. Tabel akun kedua berarti jalur login
 * kedua yang harus dijaga terpisah — dan itulah sumber celah. Yang perlu
 * ditambahkan hanya PENAUT ke baris `vendor`: satu akun = satu vendor, sehingga
 * barang, pesanan, dan tagihan bisa disaring tanpa kolom tebakan.
 *
 * `vendor_tagihan` = invoice yang DITERBITKAN vendor untuk barang yang sudah ia
 * salurkan ke koperasi, lalu ditagihkan ke Agrinas. `kanal` menyalin cara
 * koperasi membayar pesanan asalnya:
 *   SALDO   = dari Saldo Agrinas koperasi (uangnya sudah di Agrinas, Agrinas
 *             yang melunasi vendor);
 *   SENDIRI = kas koperasi sendiri (koperasi transfer langsung ke vendor,
 *             Agrinas hanya mencatat pelunasan).
 * Nilainya DISALIN dari `pesanan.kanal` saat tagihan terbit, bukan diketik
 * ulang: dua angka untuk satu fakta adalah cara termudah membuat laporan uang
 * berbeda dari kenyataan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasColumn('users', 'vendor_id')) {
            $s->table('users', function (Blueprint $t) {
                $t->integer('vendor_id')->nullable();
            });
        }

        if (! $s->hasTable('vendor_tagihan')) {
            $s->create('vendor_tagihan', function (Blueprint $t) {
                $t->id();
                $t->string('kode')->unique();          // TV-202610-0001
                $t->integer('vendor_id');
                $t->integer('koperasi_id')->nullable();
                $t->integer('pesanan_id')->nullable(); // pesanan asal barang
                $t->string('keterangan')->default('');
                $t->integer('jumlah')->default(0);
                $t->string('kanal')->default('SALDO'); // SALDO | SENDIRI
                $t->string('status')->default('DIAJUKAN'); // DIAJUKAN -> DIBAYAR | DITOLAK
                $t->string('catatan')->default('');    // catatan keputusan Agrinas
                $t->string('dibuat');
                $t->string('diperbarui');
                $t->string('dibayar')->default('');
                $t->index('vendor_id');
                $t->index('status');
                // Satu pesanan = satu tagihan. Tanpa pagar ini, menekan tombol
                // Tagih dua kali membuat Agrinas menerima dua invoice untuk
                // barang yang sama — uang keluar dua kali, tanpa galat apa pun.
                $t->unique('pesanan_id');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('hub');
        $s->dropIfExists('vendor_tagihan');
    }
};

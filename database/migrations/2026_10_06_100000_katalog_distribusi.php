<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog Agrinas: asal barang, status tayang, cara penyerahan, surat jalan,
 * dan catatan pemantauan kiriman.
 *
 * Kenapa kolom-kolom ini:
 *   - `barang.sumber`  : Agrinas TIDAK berbelanja seperti manajer; ia
 *                        MENYEDIAKAN barang. Sumbernya bisa gudang Agrinas
 *                        sendiri, vendor, atau pengadaan dari koperasi
 *                        (jagung/beras gabungan petani). Kolom ini yang
 *                        membuat asal barang terbaca, bukan tersembunyi di
 *                        `vendor_id` saja.
 *   - `barang.aktif`   : barang yang sudah dipakai di pesanan/distribusi tidak
 *                        boleh dihapus (riwayatnya rusak). "Hapus" = turunkan
 *                        dari katalog, bukan DELETE.
 *   - `pesanan.antar`  : opsi penyerahan — KIRIM ke koperasi atau AMBIL di
 *                        gudang. Menentukan langkah berikutnya: DIKIRIM vs
 *                        DIAMBIL, dan itu terlihat di halaman Pesanan.
 *   - `pesanan.surat_jalan`: nomor surat yang ditandatangani manajer saat
 *                        barang diterima (terbit otomatis dari sistem).
 *   - `pesanan_tindakan`: jejak pantauan pengelola — catatan perjalanan,
 *                        minta ubah, atau minta hapus. Riwayat, bukan
 *                        pengganti status.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        $s->table('barang', function (Blueprint $t) {
            $t->string('sumber', 16)->default('GUDANG');
            $t->integer('aktif')->default(1);
        });

        $s->table('pesanan', function (Blueprint $t) {
            $t->string('antar', 8)->default('KIRIM');
            $t->string('surat_jalan', 32)->default('');
            $t->string('diambil', 24)->default('');
        });

        if (! $s->hasTable('pesanan_tindakan')) {
            $s->create('pesanan_tindakan', function (Blueprint $t) {
                $t->increments('id');
                $t->integer('pesanan_id');
                $t->integer('oleh');
                // kirim | siap | terima | catatan | minta | hapus
                $t->string('tindakan', 16);
                $t->string('pesan', 400)->default('');
                $t->string('lampiran', 300)->default('');
                $t->string('dibuat', 24)->default('');
                $t->index('pesanan_id');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        $s->dropIfExists('pesanan_tindakan');
        foreach (['pesanan' => ['antar', 'surat_jalan', 'diambil'], 'barang' => ['sumber', 'aktif']] as $tabel => $kolom) {
            foreach ($kolom as $k) {
                if ($s->hasColumn($tabel, $k)) {
                    $s->table($tabel, fn (Blueprint $t) => $t->dropColumn($k));
                }
            }
        }
    }
};

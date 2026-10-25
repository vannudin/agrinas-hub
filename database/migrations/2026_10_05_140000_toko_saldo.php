<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toko barang: katalog berharga, keranjang, pesanan, dan SALDO AGRINAS.
 *
 * Aturan Saldo (permintaan Bos):
 *   - Manajer menerima jatah awal Rp 400.000.000 sebagai MODAL BELANJA.
 *   - Saldo itu tidak bisa ditarik atau diuangkan — hanya untuk membeli barang.
 *   - Barang UMKM pun bisa dibayar dari saldo yang sama, dengan invoice terbit.
 *
 * Karena itu saldo TIDAK disimpan sebagai kolom "bisa diubah" saja: sisa saldo
 * selalu dihitung (saldo awal − pesanan yang sudah dibayar dan belum dibatalkan).
 * Kalau saldo disimpan sebagai angka berjalan, satu bug pemotongan langsung
 * jadi uang hilang tanpa jejak; dengan cara ini setiap rupiah terikat pada satu
 * pesanan. `harga` di barang = harga jual ke koperasi (bukan harga beli Agrinas).
 *
 * Pembelian dengan saldo TIDAK menulis ke `keuangan`: tidak ada kas koperasi
 * yang keluar, sama persis dengan alur distribusi Agrinas yang sudah ada. Hanya
 * kanal "kas koperasi" yang mencatat pengeluaran, dan itu ditandai kolom
 * `kanal` + `ref` supaya bisa dibedakan dari biaya operasional biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('hub');

        if (! $s->hasColumn('barang', 'harga')) {
            $s->table('barang', function (Blueprint $t) {
                $t->integer('harga')->default(0);       // harga jual per satuan (rupiah)
                $t->string('gambar')->default('');      // URL gambar produk (opsional)
            });
        }

        if (! $s->hasTable('saldo')) {
            $s->create('saldo', function (Blueprint $t) {
                $t->id();
                $t->integer('koperasi_id');
                $t->integer('saldo')->default(400000000);   // modal belanja, tidak bisa ditarik
                $t->string('catatan')->default('');
                $t->string('dibuat');
                $t->string('diperbarui');
                $t->unique('koperasi_id');
            });
        }

        if (! $s->hasTable('pesanan')) {
            $s->create('pesanan', function (Blueprint $t) {
                $t->id();
                $t->string('kode')->unique();          // nomor pesanan PS-0001
                $t->string('invoice')->default('');    // nomor invoice INV-202610-0001
                $t->integer('koperasi_id');
                $t->integer('pemesan')->nullable();
                $t->integer('total')->default(0);
                $t->string('kanal')->default('AGRN');  // AGRN = Saldo Agrinas, KAS = kas koperasi
                $t->string('status')->default('DIPESAN'); // DIPESAN -> DIBAYAR -> DIKIRIM -> DITERIMA | DIBATALKAN
                $t->string('catatan')->default('');
                $t->string('tanggal')->default('');
                $t->string('dibayar')->default('');
                $t->string('dikirim')->default('');
                $t->string('diterima')->default('');
                $t->string('dibuat');
                $t->string('diperbarui');
                $t->index('koperasi_id');
                $t->index('status');
            });
        }

        if (! $s->hasTable('pesanan_item')) {
            $s->create('pesanan_item', function (Blueprint $t) {
                $t->id();
                $t->integer('pesanan_id');
                $t->integer('barang_id')->nullable();
                $t->string('nama');                     // disalin: katalog boleh berubah, invoice tidak
                $t->string('satuan')->default('UNIT');
                $t->integer('jumlah')->default(0);
                $t->integer('harga')->default(0);       // harga saat dibeli
                $t->index('pesanan_id');
            });
        }

        // Menandai pergerakan kas yang berasal dari pembelian (bukan biaya biasa).
        if (! $s->hasColumn('keuangan', 'kanal')) {
            $s->table('keuangan', function (Blueprint $t) {
                $t->string('kanal')->default('');
                $t->string('ref')->default('');
            });
        }

        // Harga jual awal katalog Agrinas. Tanpa harga, katalog tak bisa dibeli
        // sama sekali; nilai di bawah adalah harga wajar per satuan dan bisa
        // diubah kapan saja dari Stok Gudang -> Ubah barang.
        foreach ([
            'BRG-0001' => 320000, 'BRG-0002' => 12500, 'BRG-0003' => 11800,
            'BRG-0004' => 6300, 'BRG-0005' => 14500, 'BRG-0006' => 13500,
            'BRG-0007' => 600000, 'BRG-0008' => 310000, 'BRG-0009' => 17500,
            'BRG-0010' => 16500, 'BRG-0011' => 7200, 'BRG-0012' => 5600,
            'BRG-0013' => 260000, 'BRG-0014' => 89000, 'BRG-0015' => 96000,
        ] as $kode => $harga) {
            \Illuminate\Support\Facades\DB::connection('hub')
                ->table('barang')->where('kode', $kode)->where('harga', 0)->update(['harga' => $harga]);
        }
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        foreach (['pesanan_item', 'pesanan', 'saldo'] as $t) {
            $s->dropIfExists($t);
        }
        foreach (['barang' => ['harga', 'gambar'], 'keuangan' => ['kanal', 'ref']] as $tabel => $kolom) {
            foreach ($kolom as $k) {
                if ($s->hasColumn($tabel, $k)) {
                    $s->table($tabel, fn (Blueprint $t) => $t->dropColumn($k));
                }
            }
        }
    }
};

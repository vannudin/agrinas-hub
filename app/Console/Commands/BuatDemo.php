<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use App\Support\ScryptHasher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Bangun basis data DEMO: satu akun untuk setiap peran + sedikit data contoh.
 *
 * Skema diambil dari database/demo/skema.sql (struktur tabel, tanpa data
 * pribadi). Skrip ini TIDAK menyentuh basis data kerja: ia membuat berkas
 * sqlite baru lalu mengarahkan koneksi `hub` ke berkas itu.
 *
 *   php artisan hub:demo
 *
 * Sandi semua akun demo tercetak di akhir — hanya untuk pengembangan lokal.
 */
class BuatDemo extends Command
{
    protected $signature = 'hub:demo {--path= : Lokasi berkas sqlite (bawaan database/demo/hub-demo.sqlite)}';

    protected $description = 'Buat basis data demo: 1 akun per peran + data contoh';

    public const SANDI = 'Demo#Koperasi2026';

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('demo/hub-demo.sqlite');
        $skema = database_path('demo/skema.sql');

        if (! is_file($skema)) {
            $this->error("Skema tidak ditemukan: {$skema}");

            return self::FAILURE;
        }

        @mkdir(dirname($path), 0775, true);
        foreach ([$path, $path.'-wal', $path.'-shm'] as $berkas) {
            if (is_file($berkas)) {
                unlink($berkas);
            }
        }

        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec(file_get_contents($skema));
        $this->line('skema diterapkan: '.$path);

        // Arahkan koneksi aplikasi ke berkas demo supaya cast model (mis. kolom
        // tanggal lahir yang tersandi) tetap bekerja seperti di produksi.
        config(['database.connections.hub.database' => $path]);
        DB::purge('hub');

        $sekarang = now()->toDateTimeString();
        $hash = ScryptHasher::buat(self::SANDI);

        $pemetaanId = DB::table('pemetaan_lahan')->insertGetId([
            'pemetaan_id' => 900001,
            'nama' => 'Contoh Pemetaan Lahan',
            'telepon' => '081200000001',
            'lokasi' => 'Desa Sukamaju, Cibadak, Sukabumi',
            'desa' => 'Sukamaju',
            'kecamatan' => 'Cibadak',
            'kabupaten' => 'Sukabumi',
            'provinsi' => 'Jawa Barat',
            'luas' => 12.5,
            'potensi' => 'Pertanian (Padi, Jagung)',
            'latitude' => -6.8915,
            'longitude' => 106.7849,
            'pembangunan_100' => 1,
            'pembangunan_mulai' => 1,
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ]);

        $koperasiId = DB::table('koperasi')->insertGetId([
            'kode' => 'KDKMP-DEMO-001',
            'nama' => 'Koperasi Desa Merah Putih Sukamaju',
            'wilayah' => 'Jawa Barat',
            'provinsi' => 'Jawa Barat',
            'alamat' => 'Jl. Contoh No. 1, Sukamaju, Cibadak, Sukabumi',
            'no_rekening' => '0000000000',
            'status' => 'AKTIF',
            'versi' => 1,
            'pemetaan_lahan_id' => $pemetaanId,
            'diperbarui' => $sekarang,
        ]);

        $vendorId = DB::table('vendor')->insertGetId([
            'kode' => 'VND-0001',
            'nama' => 'CV Contoh Pangan Nusantara',
            'jenis' => 'SWASTA',
            'komoditas' => 'Beras, Gula',
            'kontak' => '081200000002',
            'catatan' => 'Vendor contoh untuk pengembangan',
            'dibuat' => $sekarang,
            'diperbarui' => $sekarang,
        ]);

        foreach ([
            ['BRS-001', 'Beras Medium', 'SAK', 'PANGAN', 50, 550000],
            ['GLA-001', 'Gula Pasir', 'SAK', 'PANGAN', 50, 780000],
        ] as [$kode, $nama, $satuan, $kategori, $stok, $harga]) {
            $barangId = DB::table('barang')->insertGetId([
                'kode' => $kode,
                'nama' => $nama,
                'satuan' => $satuan,
                'kategori' => $kategori,
                'stok' => $stok,
                'stok_min' => 10,
                'harga' => $harga,
                'vendor_id' => $vendorId,
                'sumber' => 'GUDANG',
                'aktif' => 1,
                'dibuat' => $sekarang,
                'diperbarui' => $sekarang,
            ]);
            DB::table('koperasi_barang')->insert([
                'koperasi_id' => $koperasiId,
                'kode' => $kode,
                'nama' => $nama,
                'satuan' => $satuan,
                'kategori' => $kategori,
                'stok' => $stok,
                'stok_min' => 10,
                'harga' => $harga,
                'dibuat' => $sekarang,
                'diperbarui' => $sekarang,
            ]);
        }

        $akun = [
            ['demo.agrinas@contoh.test', 'Demo Admin Agrinas', Domain::PERAN_AGRINAS, ['wilayah' => '']],
            ['demo.pic@contoh.test', 'Demo PIC Jawa Barat', Domain::PERAN_PIC, ['wilayah' => 'Jawa Barat']],
            ['demo.manajer@contoh.test', 'Demo Manajer Koperasi', Domain::PERAN_MANAJER, ['koperasi_id' => $koperasiId, 'wilayah' => 'Jawa Barat']],
            ['demo.hr@contoh.test', 'Demo HR Pusat', Domain::PERAN_HR, []],
            ['demo.keuangan@contoh.test', 'Demo Petugas Keuangan', Domain::PERAN_KEUANGAN, []],
            ['demo.tni@contoh.test', 'Demo Babinsa', Domain::PERAN_TNI, ['wilayah' => 'Jawa Barat']],
            ['demo.dinas@contoh.test', 'Demo Dinas Koperasi', Domain::PERAN_DINAS, ['wilayah' => 'Jawa Barat']],
            ['demo.vendor@contoh.test', 'Demo Vendor', Domain::PERAN_VENDOR, ['vendor_id' => $vendorId]],
        ];

        foreach ($akun as [$email, $nama, $peran, $tambahan]) {
            $u = User::create(array_merge([
                'email' => $email,
                'nama' => $nama,
                'peran' => $peran,
                'sandi' => $hash,
                'sandi_sumber' => 'LOKAL',
                'aktif' => 1,
                'wajib_ganti' => 0,
                'tanggal_lahir' => '1990-01-01',
                'dibuat' => $sekarang,
            ], $tambahan));

            if ($peran === Domain::PERAN_MANAJER) {
                DB::table('koperasi')->where('id', $koperasiId)->update(['manajer_id' => $u->id]);
            }
        }

        DB::table('kontrak')->insert([
            'user_id' => User::where('email', 'demo.manajer@contoh.test')->value('id'),
            'periode_id' => 900001,
            'periode_mulai' => '2026-01-01',
            'periode_selesai' => '2026-12-31',
            'periode_status' => 'AKTIF',
            'periode_program' => 'KDKMP',
            'penempatan_koperasi_id' => $koperasiId,
            'penempatan_nama' => 'Koperasi Desa Merah Putih Sukamaju',
            'penempatan_provinsi' => 'Jawa Barat',
            'penempatan_wilayah' => 'Jawa Barat',
            'penempatan_jenis' => 'MANAJER',
            'dibuat' => $sekarang,
            'diperbarui' => $sekarang,
        ]);

        $this->newLine();
        $this->info('Basis data demo siap: '.$path);
        $this->table(['Peran', 'Email', 'Sandi'], array_map(
            fn ($a) => [$a[2], $a[0], self::SANDI],
            $akun
        ));
        $this->line('Arahkan .env: HUB_DB='.$path);

        return self::SUCCESS;
    }
}

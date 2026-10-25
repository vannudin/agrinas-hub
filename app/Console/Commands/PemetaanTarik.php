<?php

namespace App\Console\Commands;

use App\Services\PemetaanLahanPenarik;
use Illuminate\Console\Command;

/**
 * Tarik pemetaan lahan KDKMP tingkat desa (termasuk nama & nomor HP Babinsa).
 *
 *   php artisan pemetaan:tarik                  # seluruh Indonesia
 *   php artisan pemetaan:tarik --maks=40 --diam  # batasi halaman (uji)
 *
 * ± 1.167 permintaan dengan `Http::pool` 10 sekaligus → ± 2 menit. Idempoten
 * (upsert per `pemetaan_id`), jadi boleh diulang; halaman yang gagal tinggal
 * ditarik ulang.
 */
class PemetaanTarik extends Command
{
    protected $signature = 'pemetaan:tarik {--diam : Tanpa keluaran berkala} {--maks=0 : Batasi jumlah halaman (untuk uji)}';

    protected $description = 'Tarik pemetaan lahan KDKMP (desa, luas, potensi, Babinsa) dari portalkdkmp';

    public function handle(PemetaanLahanPenarik $penarik): int
    {
        $mulai = microtime(true);
        $terakhir = 0;
        // Catatan kemajuan ke berkas: harvest dijalankan TERDETEKSI (`docker exec -d`)
        // karena ± 9 menit — keluarannya tidak sampai ke pemanggil, jadi kemajuan
        // harus bisa dibaca dari tempat lain.
        $catat = function (int $selesai, int $total, string $tahap) {
            file_put_contents(storage_path('logs/pemetaan-tarik.log'),
                now()->toDateTimeString()." {$tahap} {$selesai}\n", FILE_APPEND);
        };

        $r = $penarik->tarik(function (int $selesai, int $total, string $tahap) use (&$terakhir, $catat) {
            $catat($selesai, $total, $tahap);
            if ($this->option('diam')) {
                return;
            }
            // Lapor tiap ± 2.000 baris saja; kalau tiap gelombang, keluarannya
            // membanjiri layar tanpa menambah informasi.
            if ($selesai - $terakhir < 2000) {
                return;
            }
            $terakhir = $selesai;
            $this->line("  {$tahap}: {$selesai} baris");
        }, (int) $this->option('maks'));

        $detik = round(microtime(true) - $mulai, 1);
        $pesan = "pemetaan: {$r['baris']} baris dari {$r['halaman']} halaman, {$r['gagal']} gagal, "
            ."{$r['dipangkas']} dipangkas (sudah tidak ada di sumber), {$detik}s.";
        $catat($r['baris'], 0, "SELESAI gagal={$r['gagal']} dipangkas={$r['dipangkas']} detik={$detik}");
        $this->info($pesan);

        if ($r['gagal'] > 0) {
            $this->warn("{$r['gagal']} permintaan gagal — jalankan ulang untuk melengkapinya (idempoten).");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Agriconnect;
use App\Support\AbsenAc;
use Illuminate\Console\Command;

/**
 * Tarik time-sheet AgriConnect SEMUA orang yang sudah menyambung akunnya, lalu
 * simpan ke cermin `agri_ac_absen`.
 *
 *   php artisan hub:tarik-absen [--bulan=6]
 *
 * Kenapa perlu: pemantau (admin/HR/PIC/keuangan) tidak punya akun AgriConnect,
 * dan API AgriConnect hanya mau menjawab time-sheet milik pemilik token. Jadi
 * data manajer hanya bisa masuk kalau ditarik dari sesi masing-masing orang —
 * halaman Absensi melakukannya saat orang itu membuka halamannya, perintah ini
 * melakukannya untuk semua orang sekaligus (idempoten: satu baris per tanggal).
 */
class AbsenAcTarik extends Command
{
    protected $signature = 'hub:tarik-absen {--bulan=6 : berapa bulan ke belakang yang ditarik}';

    protected $description = 'Tarik absensi AgriConnect semua akun tersambung ke cermin agri_ac_absen';

    public function handle(Agriconnect $ac): int
    {
        $bulan = max(1, (int) $this->option('bulan'));
        $akhir = now('Asia/Jakarta')->toDateString();
        $awal = now('Asia/Jakarta')->subMonthsNoOverflow($bulan - 1)->startOfMonth()->toDateString();

        $orang = User::whereNotNull('agri_sesi')->where('agri_sesi', '!=', '')->get();
        $this->info("menyambung: {$orang->count()} akun, rentang {$awal} … {$akhir}");

        $total = 0;
        $kosong = 0;
        $gagal = 0;

        foreach ($orang as $u) {
            if (! $ac->tersambung($u)) {
                $this->warn("- {$u->nama}: sesi tidak terbaca, dilewati");
                $gagal++;

                continue;
            }

            $baris = $ac->coba($u, '/api/user/time-sheet', ['start' => $awal, 'end' => $akhir], []);
            if (! is_array($baris)) {
                $this->warn("- {$u->nama}: tarikan ditolak (kode {$ac->kodeTerakhir})");
                $gagal++;

                continue;
            }

            $n = AbsenAc::simpan($u->id, $baris);
            $total += $n;
            if ($n === 0) {
                $kosong++;
            }
            $this->line("- {$u->nama}: {$n} baris");
        }

        $this->info("selesai: {$total} baris tersimpan, {$kosong} akun tanpa data, {$gagal} gagal.");

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}

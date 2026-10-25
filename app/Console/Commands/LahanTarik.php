<?php

namespace App\Console\Commands;

use App\Services\LahanPenarik;
use Illuminate\Console\Command;

/**
 * Tarik data lahan & pembangunan KDKMP (verval aset) dari SIMKOPDES.
 *
 *   php artisan lahan:tarik
 *
 * Dipanggil manual dari halaman Koperasi (tombol) dan boleh dijadwalkan —
 * idempoten (updateOrCreate per cooperative_asset_id), jadi aman diulang.
 */
class LahanTarik extends Command
{
    protected $signature = 'lahan:tarik';

    protected $description = 'Tarik data lahan/pembangunan KDKMP dari SIMKOPDES (verval aset)';

    public function handle(LahanPenarik $penarik): int
    {
        $r = $penarik->tarik();
        $this->info("lahan: {$r['baris']} aset, {$r['koperasi']} koperasi tertaut, {$r['dilewati']} dilewati.");

        foreach ($r['gagal'] as $g) {
            $this->warn("gagal: {$g}");
        }

        return self::SUCCESS;
    }
}

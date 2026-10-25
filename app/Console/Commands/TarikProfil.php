<?php

namespace App\Console\Commands;

use App\Services\ProfilPenarik;
use Illuminate\Console\Command;

/**
 * Tarik profil + akun pendamping SIMKOPDES ke Hub.
 *
 *   php artisan profil:tarik --sandi='SANDI_SIMKOPDES'
 *   php artisan profil:tarik                       # tanpa menyalin sandi
 *
 * Memberi `--sandi` SEKALI sudah cukup: hasil hash-nya disimpan di
 * `users.sandi`, jadi login berikutnya tidak perlu memanggil SIMKOPDES lagi.
 */
class TarikProfil extends Command
{
    protected $signature = 'profil:tarik {--sandi= : Sandi SIMKOPDES pendamping (disalin sekali, lalu di-hash)}';

    protected $description = 'Tarik profil pendamping + koperasi binaan dari SIMKOPDES ke Hub';

    public function handle(ProfilPenarik $penarik): int
    {
        if (! $penarik->terkonfigurasi()) {
            $this->error('SIMKOPDES belum diatur: isi SIMKOPDES_PASSWORD (dan FACILITATOR_ID) di .env.');

            return self::FAILURE;
        }

        $sandi = $this->option('sandi') ?: null;

        try {
            $r = $penarik->tarik($sandi);
        } catch (\Throwable $e) {
            $this->error('Gagal menarik profil: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($r['koperasi'] as $k) {
            $this->line(sprintf('  %-10s %s', $k['kode'], $k['nama']));
        }

        $this->info("Pendamping: {$r['pendamping']} · Profil: {$r['profil']} · Sandi disalin: {$r['sandi']}");
        $this->info("Koperasi: {$r['koperasi_baru']} baru, {$r['koperasi_ubah']} diperbarui, {$r['koperasi_tetap']} tetap.");

        if ($r['sandi'] === 0) {
            $this->line('Sandi tidak disalin (salinan di Hub sudah ada). Beri --sandi untuk menyegarkannya.');
        }

        return self::SUCCESS;
    }
}

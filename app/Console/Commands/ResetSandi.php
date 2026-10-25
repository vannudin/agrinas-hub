<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ScryptHasher;
use Illuminate\Console\Command;

class ResetSandi extends Command
{
    protected $signature = 'hub:sandi {email} {--sandi= : Pakai sandi ini, bukan acak}';
    protected $description = 'Atur ulang sandi akun lalu tampilkan sekali (setara cli.py reset-sandi)';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $u = User::where('email', $email)->first();

        if (! $u) {
            $this->error("Akun tidak ditemukan: {$email}");

            return self::FAILURE;
        }

        $sandi = $this->option('sandi') ?: self::sandiAcak();
        // `sandi_sumber='HUB'`: sandi ini milik Hub, bukan salinan SIMKOPDES —
        // penanda ini yang menghentikan jalur 2 menembak SIMKOPDES saat pengguna
        // salah ketik (lihat MasukController::aksi()).
        $u->forceFill(['sandi' => ScryptHasher::buat($sandi), 'sandi_sumber' => 'HUB', 'wajib_ganti' => 1])->save();

        jurnal(null, 'RESET_SANDI', $email, 'via artisan');
        $this->line('Sandi baru (wajib ganti saat masuk):');
        $this->line(json_encode(['email' => $email, 'sandi' => $sandi, 'peran' => $u->peran]));

        return self::SUCCESS;
    }

    /** Sandi acak yang selalu memuat huruf kecil, besar, angka, dan tanda. */
    private static function sandiAcak(int $panjang = 14): string
    {
        $abjad = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%*+-_';
        $tanda = '!@#%*+-_';

        do {
            $pw = '';
            for ($i = 0; $i < $panjang; $i++) {
                $pw .= $abjad[random_int(0, strlen($abjad) - 1)];
            }
        } while (! preg_match('/[a-z]/', $pw) || ! preg_match('/[A-Z]/', $pw)
              || ! preg_match('/\d/', $pw) || ! strpbrk($pw, $tanda));

        return $pw;
    }
}

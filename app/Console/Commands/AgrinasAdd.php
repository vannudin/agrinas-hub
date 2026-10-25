<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use App\Support\ScryptHasher;
use Illuminate\Console\Command;

class AgrinasAdd extends Command
{
    protected $signature = 'hub:agrinas {email} {nama}';
    protected $description = 'Buat akun Agrinas (admin) atau angkat akun yang ada menjadi Agrinas';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $nama = trim($this->argument('nama'));

        $u = User::where('email', $email)->first();
        $sandi = null;

        if ($u) {
            $u->forceFill(['peran' => Domain::PERAN_AGRINAS, 'wajib_ganti' => 0])->save();
            $this->info("Akun {$email} diangkat menjadi Agrinas.");
        } else {
            $sandi = 'Agrinas#'.substr(bin2hex(random_bytes(6)), 0, 10);
            User::create([
                'email' => $email, 'nama' => $nama,
                'peran' => Domain::PERAN_AGRINAS,
                'sandi' => ScryptHasher::buat($sandi),
                'wajib_ganti' => 1, 'aktif' => 1, 'dibuat' => kini(),
            ]);
            $this->info("Akun Agrinas {$email} dibuat.");
        }

        jurnal(null, 'AKUN_BARU', $email, 'via artisan (agrinas)');

        if ($sandi) {
            $this->line(json_encode(['email' => $email, 'sandi' => $sandi, 'peran' => 'AGRINAS']));
        }

        return self::SUCCESS;
    }
}

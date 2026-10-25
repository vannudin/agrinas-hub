<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use App\Support\ScryptHasher;
use Illuminate\Console\Command;

/**
 * Buat akun petugas HR — pemakai menu SDM & Kepegawaian.
 *
 *   php artisan hub:hr "Nama Petugas" hr@agrinas.co.id [--sandi=] [--aktif]
 *
 * Sama polanya dengan hub:pic & hub:agrinas: kalau emailnya sudah ada, akun itu
 * yang diangkat (sandi lama dibiarkan bila --sandi tidak disebut — jangan reset
 * sandi orang tanpa diminta). Peran HR dibatasi ke meja SDM saja; kendali pusat
 * (akun, broadcast, vendor, SIMKOPDES) tetap milik peran AGRINAS.
 */
class HrAdd extends Command
{
    protected $signature = 'hub:hr {nama} {email} {--sandi=} {--aktif}';
    protected $description = 'Buat atau angkat akun petugas HR (pemakai menu SDM & Kepegawaian)';

    public function handle(): int
    {
        $nama = trim((string) $this->argument('nama'));
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $sandiMentah = $this->option('sandi');
        $sandi = (string) ($sandiMentah ?: 'Hr#'.substr(bin2hex(random_bytes(6)), 0, 10));
        $wajib = $this->option('aktif') ? 0 : 1;

        $u = User::where('email', $email)->first();

        if ($u) {
            $isi = ['nama' => $nama, 'peran' => Domain::PERAN_HR, 'aktif' => 1, 'wajib_ganti' => $wajib];
            if ($sandiMentah) {
                $isi['sandi'] = ScryptHasher::buat($sandi);
                $isi['sandi_sumber'] = 'HUB';
            }
            $u->forceFill($isi)->save();
            $this->info("Akun {$email} diangkat menjadi Petugas HR.");
        } else {
            User::create([
                'email' => $email, 'nama' => $nama, 'peran' => Domain::PERAN_HR,
                'sandi' => ScryptHasher::buat($sandi), 'wajib_ganti' => $wajib,
                'aktif' => 1, 'dibuat' => kini(),
            ]);
            $this->info("Akun Petugas HR {$email} dibuat.");
        }

        jurnal(null, 'AKUN_BARU', $email, 'HR (via artisan)');
        $this->line(json_encode(['email' => $email, 'sandi' => $sandi, 'nama' => $nama,
            'peran' => Domain::PERAN_HR], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}

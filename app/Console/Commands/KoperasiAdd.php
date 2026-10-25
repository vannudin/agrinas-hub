<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Koperasi;
use App\Models\User;
use App\Support\ScryptHasher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KoperasiAdd extends Command
{
    protected $signature = 'hub:koperasi {kode} {nama} {--manajer=} {--email=} {--wilayah=} {--alamat=}';
    protected $description = 'Tambah koperasi beserta akun manajernya';

    public function handle(): int
    {
        $kode = mb_strtoupper(trim($this->argument('kode')));
        $nama = trim($this->argument('nama'));

        if (Koperasi::where('kode', $kode)->exists()) {
            $this->error("Kode koperasi sudah dipakai: {$kode}");

            return self::FAILURE;
        }

        return DB::connection('hub')->transaction(function () use ($kode, $nama) {
            $manajerId = null;
            $sandi = null;
            $email = $this->option('email');

            if ($email) {
                $email = mb_strtolower(trim($email));
                if (User::where('email', $email)->exists()) {
                    $this->error("Email sudah dipakai: {$email}");

                    return self::FAILURE;
                }
                $sandi = 'SandiUji#'.substr(bin2hex(random_bytes(6)), 0, 10);
                $m = User::create([
                    'email' => $email,
                    'nama'  => $this->option('manajer') ?: 'Manajer '.$nama,
                    'peran' => Domain::PERAN_MANAJER,
                    'sandi' => ScryptHasher::buat($sandi),
                    'wajib_ganti' => 1, 'aktif' => 1, 'dibuat' => kini(),
                ]);
                $manajerId = $m->id;
            }

            $k = Koperasi::create([
                'kode' => $kode, 'nama' => $nama,
                'wilayah' => $this->option('wilayah'),
                'alamat' => $this->option('alamat'),
                'manajer_id' => $manajerId, 'status' => 'AKTIF',
                'versi' => 1, 'diperbarui' => kini(),
            ]);

            jurnal(null, 'KOPERASI_BARU', $kode, 'via artisan'.($email ? ", manajer={$email}" : ''));

            $this->info("Koperasi {$kode} dibuat (id={$k->id}).");
            if ($sandi) {
                $this->line(json_encode(['email' => $email, 'sandi' => $sandi, 'peran' => 'MANAJER']));
            }

            return self::SUCCESS;
        });
    }
}

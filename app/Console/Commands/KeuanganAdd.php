<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use App\Support\ScryptHasher;
use Illuminate\Console\Command;

/**
 * Buat akun Petugas Keuangan — pemakai pembayaran & buku kas.
 *
 *   php artisan hub:keuangan "Nama Petugas" keuangan@agrinas.co.id [--sandi=] [--aktif]
 *
 * Sama polanya dengan hub:hr & hub:pic: kalau emailnya sudah ada, akun itu yang
 * diangkat (sandi lama dibiarkan bila --sandi tidak disebut — jangan reset sandi
 * orang tanpa diminta). Peran KEUANGAN memegang pembayaran pasokan UMKM, pelunasan
 * order toko, dan buku kas; ia TIDAK mendapat kendali pusat maupun wewenang gudang.
 */
class KeuanganAdd extends Command
{
    protected $signature = 'hub:keuangan {nama} {email} {--sandi=} {--aktif}';
    protected $description = 'Buat atau angkat akun petugas keuangan (pembayaran & buku kas)';

    public function handle(): int
    {
        $nama = trim((string) $this->argument('nama'));
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $sandiMentah = $this->option('sandi');
        $sandi = (string) ($sandiMentah ?: 'Kgn#'.substr(bin2hex(random_bytes(6)), 0, 10));
        $wajib = $this->option('aktif') ? 0 : 1;

        $u = User::where('email', $email)->first();

        if ($u) {
            $isi = ['nama' => $nama, 'peran' => Domain::PERAN_KEUANGAN, 'aktif' => 1, 'wajib_ganti' => $wajib];
            if ($sandiMentah) {
                $isi['sandi'] = ScryptHasher::buat($sandi);
                $isi['sandi_sumber'] = 'HUB';
            }
            $u->forceFill($isi)->save();
            $this->info("Akun {$email} diangkat menjadi Petugas Keuangan.");
        } else {
            User::create([
                'email' => $email, 'nama' => $nama, 'peran' => Domain::PERAN_KEUANGAN,
                'sandi' => ScryptHasher::buat($sandi), 'wajib_ganti' => $wajib,
                'aktif' => 1, 'dibuat' => kini(),
            ]);
            $this->info("Akun Petugas Keuangan {$email} dibuat.");
        }

        jurnal(null, 'AKUN_BARU', $email, 'Keuangan (via artisan)');
        $this->line(json_encode(['email' => $email, 'sandi' => $sandi, 'nama' => $nama,
            'peran' => Domain::PERAN_KEUANGAN], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}

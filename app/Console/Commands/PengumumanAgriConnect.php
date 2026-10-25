<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Siaran;
use App\Models\User;
use App\Support\AkunAgriConnect;
use Illuminate\Console\Command;

/**
 * Terbitkan pengumuman PRIBADI berisi kredensial AgriConnect untuk akun manajer
 * SIMKOPDES (email bertanda `+sppi`).
 *
 *   php artisan hub:pengumuman-agriconnect --pratinjau
 *   php artisan hub:pengumuman-agriconnect
 *
 * Satu baris `broadcast` per orang dengan sasaran `usr:<id>` — jadi tidak ada
 * pengumuman bersama yang membocorkan sandi orang lain, dan lonceng hanya
 * menyala untuk pemiliknya. Aman dijalankan berulang: yang sudah punya
 * pengumuman ini dilewati (kecuali --paksa).
 *
 * Nomor kontrak yang tak berangka dilewati dan DICATAT, bukan ditebak.
 *
 * Sejak 2026-10-22 pembuatannya juga dipanggil jalur baca lonceng
 * (AkunAgriConnect::terbitkan), jadi akun yang masuk setelah command ini
 * dijalankan tetap kebagian tanpa perlu menyetel cron.
 */
class PengumumanAgriConnect extends Command
{
    protected $signature = 'hub:pengumuman-agriconnect {--pratinjau} {--paksa} {--email= : batasi ke satu akun (untuk gate/uji)}';

    protected $description = 'Terbitkan pengumuman pribadi kredensial AgriConnect untuk akun manajer SIMKOPDES';

    /**
     * Penerima: akun MANAJER aktif dengan email bertanda `+sppi`.
     *
     * Akun lain (AGRINAS/HR/PIC/KEUANGAN/VENDOR) TIDAK pernah ikut — permintaan
     * Bos 2026-10-22: "jangan kirim ke akun selain akun simkopdes". Statis supaya
     * gate bisa memeriksa daftarnya tanpa mengirim apa pun.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,User>
     */
    public static function penerima(?string $email = null)
    {
        return User::query()
            ->where('peran', Domain::PERAN_MANAJER)
            ->where('aktif', 1)
            ->where('email', 'like', '%+sppi@%')
            ->when($email !== null && $email !== '', fn ($w) => $w->where('email', $email))
            ->orderBy('id')
            // `peran` WAJIB ikut: AkunAgriConnect::terbitkan() memeriksa peran
            // pada model ini. Tanpa kolomnya guard membaca null → semua akun
            // dianggap bukan manajer dan tidak ada pengumuman yang terbit.
            ->get(['id', 'nama', 'email', 'peran']);
    }

    public function handle(): int
    {
        $pratinjau = (bool) $this->option('pratinjau');
        $paksa = (bool) $this->option('paksa');

        $pengguna = self::penerima((string) $this->option('email'));

        $dibuat = 0;
        $sudah = 0;
        $tanpaNomor = [];

        foreach ($pengguna as $u) {
            if ($pratinjau) {
                $sandi = AkunAgriConnect::sandi(AkunAgriConnect::nomorKontrak((int) $u->id));
                if ($sandi === null) {
                    $tanpaNomor[] = $u->email;
                    continue;
                }
                $ada = Siaran::query()->where('sasaran', 'usr:'.$u->id)
                    ->where('perihal', AkunAgriConnect::PERIHAL)->exists();
                if ($ada && ! $paksa) {
                    $sudah++;
                    continue;
                }
                $this->line("[pratinjau] {$u->email} → ".AkunAgriConnect::email((string) $u->email)." / {$sandi}");
                $dibuat++;
                continue;
            }

            $status = AkunAgriConnect::terbitkan($u, $paksa);
            if ($status === 'dibuat') {
                $this->line("{$u->email} → dibuat");
                $dibuat++;
            } elseif ($status === 'sudah') {
                $sudah++;
            } elseif ($status === 'tanpa-nomor') {
                $tanpaNomor[] = $u->email;
            }
        }

        $this->info(($pratinjau ? 'Pratinjau: ' : '')
            ."{$dibuat} pengumuman pribadi, {$sudah} sudah pernah dibuat, "
            .count($tanpaNomor).' akun tanpa nomor kontrak.');

        if ($tanpaNomor) {
            $this->warn('Tanpa nomor kontrak (tidak dibuat, perlu dilengkapi): '.implode(', ', $tanpaNomor));
        }

        return self::SUCCESS;
    }
}

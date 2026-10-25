<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use App\Support\ScryptHasher;
use App\Support\Wilayah;
use Illuminate\Console\Command;

/**
 * Buat / perbarui akun PIC penanggung jawab wilayah.
 *
 *   php artisan hub:pic "Nama PIC" nama@agrinas.co.id JABODETABEK
 *
 * Satu wilayah satu PIC: akun PIC lama di wilayah itu diturunkan jadi Manajer
 * supaya tidak ada dua orang memegang lingkup yang sama.
 */
class PicAdd extends Command
{
    protected $signature = 'hub:pic {nama} {email} {wilayah} {--sandi=} {--aktif}';
    protected $description = 'Buat atau perbarui akun PIC penanggung jawab wilayah';

    public function handle(): int
    {
        $nama = trim((string) $this->argument('nama'));
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $wilayah = Wilayah::normal((string) $this->argument('wilayah'));

        // Terima bentuk bebas: "jawa barat", "DKI JAKARTA" -> JABODETABEK.
        if (! Wilayah::sah($wilayah)) {
            $wilayah = Wilayah::dariProvinsi((string) $this->argument('wilayah'));
        }
        if (! Wilayah::sah($wilayah)) {
            $this->error('Wilayah tidak dikenal: '.$this->argument('wilayah'));
            $this->line('Pilihan: '.implode(', ', array_keys(Wilayah::PILIHAN)));

            return self::FAILURE;
        }

        foreach (User::where('peran', Domain::PERAN_PIC)->where('wilayah', $wilayah)
            ->where('email', '!=', $email)->get() as $lama) {
            $lama->forceFill(['peran' => Domain::PERAN_MANAJER])->save();
            $this->warn("{$lama->email} diturunkan jadi Manajer (wilayah dipegang PIC baru).");
        }

        // Sandi eksplisit dari admin juga dipasang ulang pada akun yang sudah ada;
        // kalau tidak disebut, sandi lama dibiarkan (jangan reset tanpa diminta).
        $sandiMentah = $this->option('sandi');
        $sandi = (string) ($sandiMentah ?: 'Pic#'.substr(bin2hex(random_bytes(6)), 0, 10));
        // --aktif: akun langsung siap dipakai (tanpa penanda wajib ganti sandi).
        $wajib = $this->option('aktif') ? 0 : 1;
        $u = User::where('email', $email)->first();

        if ($u) {
            $isi = ['nama' => $nama, 'peran' => Domain::PERAN_PIC, 'wilayah' => $wilayah,
                'aktif' => 1, 'wajib_ganti' => $wajib];
            if ($sandiMentah) {
                $isi['sandi'] = ScryptHasher::buat($sandi);
                $isi['sandi_sumber'] = 'HUB';
            }
            $u->forceFill($isi)->save();
            $this->info("Akun {$email} diangkat menjadi PIC ".Wilayah::label($wilayah).'.');
        } else {
            User::create([
                'email' => $email, 'nama' => $nama, 'peran' => Domain::PERAN_PIC,
                'wilayah' => $wilayah, 'sandi' => ScryptHasher::buat($sandi),
                'wajib_ganti' => $wajib, 'aktif' => 1, 'dibuat' => kini(),
            ]);
            $this->info('Akun PIC '.Wilayah::label($wilayah)." dibuat: {$email}");
        }

        jurnal(null, 'AKUN_BARU', $email, 'PIC '.$wilayah.' (via artisan)');
        $this->line(json_encode(['email' => $email, 'sandi' => $sandi, 'peran' => 'PIC',
            'wilayah' => $wilayah, 'wilayah_label' => Wilayah::label($wilayah), 'nama' => $nama],
            JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

/**
 * Impor daftar email pendamping SIMKOPDES menjadi akun Hub.
 *
 * Sumbernya PDF "Daftar Email Pendamping SIMKOPDES" yang sudah diekstrak jadi
 * JSON [{nama, email, tanggal_lahir}] (tanggal lahir ISO Y-m-d, mungkin kosong).
 * Aturan Bos: HANYA email `+sppi` yang diimpor; `+ba` dilewati; email yang sudah
 * ada di `users` tidak disentuh. Sandi sengaja dikosongkan — pintu masuknya
 * aktivasi sekali pakai (tanggal lahir + sandi baru), lihat MasukController.
 */
class ImporPendamping extends Command
{
    protected $signature = 'hub:impor-pendamping {berkas : JSON hasil ekstrak PDF} {--kering : hitung saja, jangan tulis}';

    protected $description = 'Impor akun pendamping SIMKOPDES (+sppi saja) dari daftar JSON';

    public function handle(): int
    {
        $berkas = (string) $this->argument('berkas');
        if (! is_file($berkas)) {
            $this->error("Berkas tidak ada: {$berkas}");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($berkas), true);
        if (! is_array($data)) {
            $this->error('JSON tidak terbaca.');

            return self::FAILURE;
        }

        $kering = (bool) $this->option('kering');
        $now = kini();
        $baru = [];
        $lewatBa = 0;
        $lewatSandi = 0;

        foreach ($data as $r) {
            $email = mb_strtolower(trim((string) ($r['email'] ?? '')));
            if ($email === '' || ! str_contains($email, '@')) {
                continue;
            }
            if (! str_contains($email, '+sppi')) {
                $lewatBa++;
                continue;
            }
            $baru[$email] = [
                'email' => $email,
                // PDF dari Excel membawa apostrof pelindung di depan nama.
                'nama' => trim(ltrim((string) ($r['nama'] ?? ''), "'")),
                'tanggal_lahir' => (string) ($r['tanggal_lahir'] ?? ''),
            ];
        }

        $sudah = 0;
        foreach (array_chunk(array_keys($baru), 400) as $potong) {
            foreach (User::whereIn('email', $potong)->pluck('email') as $e) {
                unset($baru[$e]);
                $sudah++;
            }
        }

        $siap = [];
        foreach ($baru as $x) {
            if ($x['tanggal_lahir'] === '') {
                $lewatSandi++; // tanpa tanggal lahir aktivasi mustahil; tetap dicatat
            }
            $siap[] = [
                'email' => $x['email'],
                'nama' => $x['nama'] !== '' ? $x['nama'] : $x['email'],
                'peran' => Domain::PERAN_MANAJER,
                'sandi' => '',            // kosong = belum aktivasi
                'sandi_sumber' => 'HUB',
                'wajib_ganti' => 0,
                'aktif' => 1,
                'wilayah' => '',
                'dibuat' => $now,
                // `User::insert()` melewati cast model, jadi enkripsinya dilakukan
                // di sini. Tanpa ini tanggal lahir masuk sebagai teks biasa dan
                // dibaca sebagai payload rusak ("The payload is invalid").
                'tanggal_lahir' => $x['tanggal_lahir'] === '' ? '' : Crypt::encryptString($x['tanggal_lahir']),
            ];
        }

        $this->line(sprintf('sumber=%d sppi=%d sudah_ada=%d bukan_sppi=%d tanpa_tgl=%d akan_dibuat=%d',
            count($data), count($baru) + $sudah, $sudah, $lewatBa, $lewatSandi, count($siap)));

        if ($kering) {
            return self::SUCCESS;
        }

        foreach (array_chunk($siap, 400) as $potong) {
            User::insert($potong);
        }

        $this->info('dibuat='.count($siap));

        return self::SUCCESS;
    }
}

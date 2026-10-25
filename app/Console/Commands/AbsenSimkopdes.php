<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SimkopdesAbsensi;
use Illuminate\Console\Command;

/**
 * Absensi KDKMP lewat CLI — jalur yang sama dengan tombol di halaman Hub.
 *
 *   sync      tarik daftar hadir dari SIMKOPDES (turun)
 *   terhubung hubungkan akun manajer: uji sandi, simpan facilitator id, tarik
 *
 * ponytail: tanpa `kirim` di CLI. Menulis absensi sungguhan hanya lewat tombol
 * di halaman yang memperlihatkan foto & titik lokasinya lebih dulu; perintah
 * baris yang bisa mengirim bukti kehadiran tanpa memperlihatkannya adalah
 * kemudahan yang lebih berbahaya daripada berguna.
 */
class AbsenSimkopdes extends Command
{
    protected $signature = 'absen:simkopdes
        {aksi=sync : sync | terhubung}
        {--email= : Akun manajer (kosong = semua manajer yang terhubung)}
        {--sandi= : Sandi SIMKOPDES, untuk aksi `terhubung`}
        {--halaman=40 : Batas halaman saat menarik}';

    protected $description = 'Sinkronkan absensi KDKMP (SIMKOPDES ↔ Hub)';

    public function handle(SimkopdesAbsensi $svc): int
    {
        $aksi = (string) $this->argument('aksi');
        $email = trim((string) $this->option('email'));

        $daftar = $email !== ''
            ? User::where('email', mb_strtolower($email))->get()
            : User::whereNotNull('simkopdes_facilitator_id')->get();

        if ($daftar->isEmpty()) {
            $this->error('Tidak ada manajer yang cocok.');

            return self::FAILURE;
        }

        foreach ($daftar as $u) {
            $this->line("— {$u->email} (user {$u->id})");

            if ($aksi === 'terhubung') {
                $sandi = (string) $this->option('sandi');
                if ($sandi === '') {
                    $this->error('  butuh --sandi=<sandi SIMKOPDES>');

                    continue;
                }
                $this->hubungkan($svc, $u, $sandi);

                continue;
            }

            try {
                $r = $svc->tarik($u, null, (int) $this->option('halaman'));
                $this->info("  ditarik = {$r['baris']} baris ({$r['halaman']} halaman"
                    .($r['berhenti'] ? ', selesai' : ', batas halaman').')');
            } catch (\Throwable $e) {
                $this->error('  gagal: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function hubungkan(SimkopdesAbsensi $svc, User $u, string $sandi): void
    {
        try {
            $svc->simpanSandi($u, $sandi);
            $svc->segarkanToken($u);
            $profil = $svc->ambilProfil($u);
        } catch (\Throwable $e) {
            $svc->lupakanSandi($u);
            $this->error('  gagal: '.$e->getMessage());

            return;
        }

        $fac = (int) ($profil['regional_facilitator']['regional_facilitator_id'] ?? 0);
        $pid = (int) ($profil['regional_facilitator_periods'][0]['regional_facilitator_period_id'] ?? 0);
        if ($fac <= 0 || $pid <= 0) {
            $this->error('  profil SIMKOPDES tidak membawa facilitator id / periode.');

            return;
        }

        $u->forceFill(['simkopdes_facilitator_id' => $fac, 'aktif' => 1])->save();

        $r = $svc->tarik($u, $pid);
        $this->info("  terhubung fac={$fac} periode={$pid} koperasi={$svc->koperasiId($u)}"
            ." → ditarik {$r['baris']} baris");
    }
}

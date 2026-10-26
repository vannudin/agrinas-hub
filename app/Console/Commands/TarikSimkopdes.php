<?php

namespace App\Console\Commands;

use App\Models\Koperasi;
use App\Services\Simkopdes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tarik koperasi binaan dari API SIMKOPDES tanpa membuka peramban.
 *
 *   php artisan simkopdes:tarik            # tarik + tulis
 *   php artisan simkopdes:tarik --kering   # tarik saja, tidak menulis apa pun
 */
class TarikSimkopdes extends Command
{
    protected $signature = 'simkopdes:tarik {--kering : Tarik saja, jangan tulis ke basis data}';

    protected $description = 'Tarik daftar koperasi binaan dari API SIMKOPDES ke tabel koperasi';

    public function handle(Simkopdes $simkopdes): int
    {
        if (! $simkopdes->terkonfigurasi()) {
            $this->error('SIMKOPDES belum diatur: isi SIMKOPDES_PASSWORD (dan FACILITATOR_ID) di .env.');

            return self::FAILURE;
        }

        try {
            $koperasi = $simkopdes->koperasi();
            $periode = $simkopdes->periodeId();
        } catch (\Throwable $e) {
            $this->error('Gagal menarik data: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Periode pendampingan: {$periode}");
        $this->info('Koperasi binaan diterima: '.count($koperasi));

        $kering = (bool) $this->option('kering');
        $baru = $ubah = $tetap = 0;

        foreach ($koperasi as $k) {
            $k = (array) $k;
            $c = (array) ($k['cooperative'] ?? []);
            $id = (int) ($c['cooperative_id'] ?? $k['cooperative_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $kab = trim(implode(', ', array_filter([
                (string) ($c['village_name'] ?? ''), (string) ($c['subdistrict_name'] ?? ''),
                (string) ($c['district_name'] ?? ''),
            ])));
            $kode = (string) ($this->kodeKabupaten($simkopdes, $c) ?? '');
            $nama = trim((string) ($c['name'] ?? ''));

            if ($kering) {
                $this->line(sprintf('  %-12s %s', $kode !== '' ? $kode : 'SIMP-'.$id,
                    $nama !== '' ? $nama : '(tanpa nama)'));
                continue;
            }

            // Kunci = id koperasi SIMKOPDES, BUKAN kode wilayah: satu kabupaten
            // bisa memuat beberapa koperasi dan kolom `kode` bersifat UNIQUE.
            // Lihat catatan lengkap di Koperasi::untukSumber().
            $ada = Koperasi::untukSumber($id, $kode);
            $this->line(sprintf('  %-12s %s', $ada->kode, $nama !== '' ? $nama : '(tanpa nama)'));

            $isi = ['nama' => $nama !== '' ? $nama : ($ada->nama ?? 'Koperasi'),
                'wilayah' => $kab !== '' ? $kab : ($ada->wilayah ?? ''),
                'alamat' => (string) ($c['address'] ?? '') ?: ($ada->alamat ?? ''),
                'status' => 'AKTIF'];

            if (! $ada->exists) {
                $ada->forceFill($isi + ['versi' => 1, 'diperbarui' => kini()])->save();
                $baru++;
            } elseif (array_intersect_assoc($isi, $ada->only(array_keys($isi))) === $isi) {
                $tetap++;
            } else {
                $ada->forceFill($isi + ['versi' => (int) $ada->versi + 1, 'diperbarui' => kini()])->save();
                $ubah++;
            }
        }

        if ($kering) {
            $this->warn('Mode kering: tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        jurnal(null, 'SIMP_TARIK', 'koperasi', "baru={$baru} ubah={$ubah} tetap={$tetap}");
        $this->info("Selesai: {$baru} baru, {$ubah} diperbarui, {$tetap} tetap.");

        return self::SUCCESS;
    }

    private function kodeKabupaten(Simkopdes $s, array $c): ?string
    {
        $id = (int) ($c['districtId'] ?? 0);

        return $id > 0 ? $s->kodeWilayah('district', $id) : null;
    }
}

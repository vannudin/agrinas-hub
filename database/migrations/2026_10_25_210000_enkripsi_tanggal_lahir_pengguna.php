<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enkripsi `users.tanggal_lahir` yang masih tersimpan sebagai teks biasa.
 *
 * Kolom ini menyimpan bukti identitas untuk aktivasi akun (tanggal lahir dari
 * SIMKOPDES). Sejak 2026-10-25 model User memakai cast `encrypted`, jadi semua
 * tulisan baru otomatis terenkripsi; migrasi ini menyusulkan baris lama.
 *
 * Aman dijalankan berulang: nilai yang sudah bisa didekripsi dilewati.
 */
return new class extends Migration
{
    private const KOLOM = 'tanggal_lahir';

    public function up(): void
    {
        $this->jalankan(fn (string $isi) => Crypt::encryptString($isi));
    }

    public function down(): void
    {
        $this->jalankan(fn (string $isi) => $isi, dekripsi: true);
    }

    /**
     * @param  callable(string):string  $ubah
     */
    private function jalankan(callable $ubah, bool $dekripsi = false): void
    {
        if (! Schema::connection('hub')->hasColumn('users', self::KOLOM)) {
            return;
        }

        $db = DB::connection('hub');
        $sudah = 0;
        $diubah = 0;

        $db->table('users')->select('id', self::KOLOM)->orderBy('id')->chunk(500,
            function ($baris) use ($db, $ubah, $dekripsi, &$sudah, &$diubah) {
                foreach ($baris as $r) {
                    $isi = (string) ($r->{self::KOLOM} ?? '');
                    if ($isi === '') {
                        continue; // kosong tetap '' — accessor User membacanya sebagai ''
                    }

                    $terenkripsi = true;
                    try {
                        Crypt::decryptString($isi);
                    } catch (\Throwable $e) {
                        $terenkripsi = false;
                    }

                    // Naik: lewati yang sudah terenkripsi. Turun: hanya yang terenkripsi.
                    if ($terenkripsi && ! $dekripsi) {
                        $sudah++;
                        continue;
                    }
                    if (! $terenkripsi && $dekripsi) {
                        $sudah++;
                        continue;
                    }

                    $baru = $terenkripsi ? Crypt::decryptString($isi) : $isi;
                    $db->table('users')->where('id', $r->id)->update([self::KOLOM => $ubah($baru)]);
                    $diubah++;
                }
            });

        fwrite(STDERR, sprintf("tanggal_lahir: %d diubah, %d dilewati\n", $diubah, $sudah));
    }
};

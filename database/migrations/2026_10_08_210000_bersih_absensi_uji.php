<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bersihkan sisa baris UJI yang pernah lolos ke DB produksi dari gate lama.
 *
 * Dua kelompok, keduanya mustahil berasal dari SIMKOPDES maupun dari Hub:
 *   - id NEGATIF (-9001..-9012): penanda uji gate; terbukti artefak karena
 *     tanggalnya Agustus 2026 sementara baris asalnya baru ditarik 8 Okt 2026;
 *   - id 888xxx: palet uji yang masih dipakai `uji_absen_kdkmp.php` sampai hari
 *     ini, jadi baris yang tertinggal berarti gate pernah terputus di tengah.
 *
 * Data NYATA tidak disentuh: id asli SIMKOPDES berupa bilangan positif panjang
 * (mis. 1255987), dan baris Hub ber-id NULL.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    public function up(): void
    {
        $db = DB::connection($this->connection);

        if (! $db->getSchemaBuilder()->hasTable('simkopdes_absensi')) {
            return;
        }

        $h = $db->table('simkopdes_absensi');

        // Dihitung lebih dulu supaya jumlah yang dibuang tercatat di keluaran
        // `migrate` — pembersihan yang tidak terlihat sama mencurigakannya
        // dengan penghapusan yang tidak diinginkan.
        $negatif = (clone $h)->whereBetween('simkopdes_absensi_id', [-99000, -1])->count();
        $palet = (clone $h)->whereBetween('simkopdes_absensi_id', [888000, 888999])->count();

        $db->table('simkopdes_absensi')->whereBetween('simkopdes_absensi_id', [-99000, -1])->delete();
        $db->table('simkopdes_absensi')->whereBetween('simkopdes_absensi_id', [888000, 888999])->delete();

        echo "  bersih absensi uji: {$negatif} baris ber-id negatif, {$palet} baris palet 888xxx dibuang\n";
    }

    public function down(): void
    {
        // Tidak ada yang bisa dipulihkan: baris uji memang layak hilang.
    }
};

<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Menyimpan time-sheet AgriConnect ke cermin `agri_ac_absen`.
 *
 * Dipakai di DUA tempat dan itu alasannya: halaman Absensi (saat orang yang
 * bersangkutan membuka halamannya) dan perintah `hub:tarik-absen` (tarikan
 * berkala). Kalau logikanya ditulis dua kali, cepat atau lambat keduanya
 * menyimpan bentuk yang berbeda — dan pemantau membaca angka yang tidak sama
 * dengan yang dilihat orangnya sendiri.
 */
final class AbsenAc
{
    /**
     * @param  array<int,mixed>  $baris  jawaban /api/user/time-sheet apa adanya
     * @return int jumlah baris yang disimpan
     */
    public static function simpan(int $userId, array $baris): int
    {
        $sekarang = now('Asia/Jakarta')->format('Y-m-d H:i:s');
        $rapi = [];

        foreach ($baris as $b) {
            if (! is_array($b)) {
                continue;
            }
            $tanggal = substr((string) ($b['startDate'] ?? ''), 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) !== 1) {
                continue;
            }

            $rapi[$tanggal] = [
                'user_id' => $userId,
                'tanggal' => $tanggal,
                'masuk' => (string) ($b['actualInTime'] ?? ''),
                'keluar' => (string) ($b['actualOutTime'] ?? ''),
                'telat' => ($b['isLateIn'] ?? false) === true ? 1 : 0,
                'pulang_awal' => ($b['isEarlyOut'] ?? false) === true ? 1 : 0,
                'alpha' => ($b['isAlpha'] ?? false) === true ? 1 : 0,
                'libur' => ($b['isDayOff'] ?? false) === true ? 1 : 0,
                'jam_kerja' => (string) ($b['workingHours'] ?? ''),
                'remark' => mb_substr((string) ($b['remark'] ?? ''), 0, 255),
                'disinkron' => $sekarang,
            ];
        }

        if ($rapi === []) {
            return 0;
        }

        // Kunci unik (user_id, tanggal) yang membuat ini MENIMPA, bukan menumpuk.
        DB::connection('hub')->table('agri_ac_absen')->upsert(
            array_values($rapi),
            ['user_id', 'tanggal'],
            ['masuk', 'keluar', 'telat', 'pulang_awal', 'alpha', 'libur', 'jam_kerja', 'remark', 'disinkron'],
        );

        return count($rapi);
    }
}

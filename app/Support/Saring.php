<?php

namespace App\Support;

/**
 * Cari + filter + paginasi untuk daftar yang SUDAH ada di memori (jawaban API
 * AgriConnect), bukan untuk tabel DB.
 *
 * Kenapa bukan `Halaman::batasi()`: helper itu memotong QUERY supaya DB tidak
 * mengirim seluruh tabel. Di sini sumbernya array hasil satu panggilan HTTP —
 * seluruh barisnya memang sudah di tangan, jadi memotong array tidak menambah
 * biaya apa pun. `Halaman` tetap dipakai untuk nomor halaman & jendelanya,
 * supaya bentuk paging-nya sama dengan 34 halaman Hub yang lain.
 *
 * ponytail: filter = satu nilai yang harus cocok (lewat callable) + pencarian
 * teks biasa; kalau kelak butuh rentang tanggal, tambahkan parameter di sini,
 * jangan bikin helper kedua.
 */
final class Saring
{
    /**
     * @param  array<int,mixed>  $baris
     * @param  array<int,string>  $kolom  kunci yang ikut dicari
     * @param  callable|null  $cocok  fn(array $baris, string $nilai): bool — dipakai bila $nilai != ''
     * @return array{baris:array<int,mixed>,halaman:Halaman,q:string,nilai:string,jumlah:int}
     */
    public static function olah(
        array $baris,
        array $kolom,
        string $q,
        ?callable $cocok = null,
        string $nilai = '',
        string $kunciHalaman = 'halaman',
        int $perHalaman = 10,
    ): array {
        $baris = array_values(array_filter($baris, 'is_array'));
        $q = trim($q);
        $nilai = trim($nilai);

        if ($q !== '') {
            $jarum = mb_strtolower($q);
            $baris = array_values(array_filter($baris, function (array $b) use ($jarum, $kolom) {
                foreach ($kolom as $k) {
                    $v = $b[$k] ?? null;
                    if (is_scalar($v) && mb_strpos(mb_strtolower((string) $v), $jarum) !== false) {
                        return true;
                    }
                }

                return false;
            }));
        }

        if ($nilai !== '' && $cocok !== null) {
            $baris = array_values(array_filter($baris, fn (array $b) => $cocok($b, $nilai)));
        }

        $jumlah = count($baris);
        $halaman = Halaman::buat($jumlah, request()->query($kunciHalaman), $perHalaman);

        return [
            'baris' => array_slice($baris, $halaman->offset(), $halaman->perHalaman),
            'halaman' => $halaman,
            'q' => $q,
            'nilai' => $nilai,
            'jumlah' => $jumlah,
        ];
    }
}

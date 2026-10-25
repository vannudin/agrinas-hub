<?php
namespace App\Support;

/**
 * Penulis ZIP murni PHP.
 *
 * Kenapa bukan ZipArchive: kontainer aplikasi ini TIDAK punya ekstensi zip
 * (terverifikasi: `extension_loaded('zip')` = false), sementara berkas Word,
 * Excel, dan PowerPoint modern semuanya adalah ZIP berisi XML. PhpSpreadsheet
 * juga tidak ada dan memasangnya butuh ekstensi zip + ~30 MB vendor untuk
 * membuat SATU berkas kosong. Yang tersedia hanya zlib (raw DEFLATE) — cukup
 * untuk menulis arsip yang sah, dan berkasnya kecil (template kosong).
 *
 * Format yang ditulis: ZIP klasik (bukan zip64). Batasnya jelas —
 * ponytail: tak ada zip64, jadi tak cocok untuk isi >4 GB atau >65535 entri.
 * Kalau suatu saat perlu menulis berkas besar, pakai ekstensi zip.
 */
final class Zip
{
    /** @param array<string,string> $berkas nama di arsip => isi biner */
    public static function buat(array $berkas): string
    {
        $lokal = '';
        $pusat = '';
        $offset = 0;
        $n = 0;

        foreach ($berkas as $nama => $isi) {
            $nama = (string) $nama;
            $isi = (string) $isi;
            $crc = crc32($isi);
            $mentah = strlen($isi);
            // Level 6 = kompromi lazim; isi template didominasi XML berulang.
            $padat = gzdeflate($isi, 6);
            $metode = 8;
            if ($padat === false || strlen($padat) >= $mentah) {
                $padat = $isi;       // sudah tak bisa dipadatkan lagi
                $metode = 0;
            }
            $simpul = strlen($padat);

            $judul = "\x50\x4b\x03\x04"                      // signature berkas lokal
                .pack('v', 20)                               // versi minimum
                .pack('v', 0)                                // flag
                .pack('v', $metode)
                .pack('v', self::jam())                      // waktu
                .pack('v', self::tanggal())
                .pack('V', $crc)
                .pack('V', $simpul)
                .pack('V', $mentah)
                .pack('v', strlen($nama))
                .pack('v', 0);                               // panjang extra

            $lokal .= $judul.$nama.$padat;

            $pusat .= "\x50\x4b\x01\x02"
                .pack('v', 20)                               // versi pembuat
                .pack('v', 20)                               // versi minimum
                .pack('v', 0)
                .pack('v', $metode)
                .pack('v', self::jam())
                .pack('v', self::tanggal())
                .pack('V', $crc)
                .pack('V', $simpul)
                .pack('V', $mentah)
                .pack('v', strlen($nama))
                .pack('v', 0)                                // extra
                .pack('v', 0)                                // komentar
                .pack('v', 0)                                // nomor disk
                .pack('v', 0)                                // atribut internal
                .pack('V', 32)                               // atribut eksternal (arsip)
                .pack('V', $offset)
                .$nama;

            $offset += strlen($judul) + strlen($nama) + $simpul;
            $n++;
        }

        $akhir = "\x50\x4b\x05\x06"
            .pack('v', 0).pack('v', 0)                       // nomor disk
            .pack('v', $n).pack('v', $n)                     // entri di disk & total
            .pack('V', strlen($pusat))
            .pack('V', $offset)
            .pack('v', 0);                                   // panjang komentar

        return $lokal.$pusat.$akhir;
    }

    /** Waktu DOS: bit 11-15 jam, 0-4 menit/2. Tanggal tetap supaya keluaran deterministik. */
    private static function jam(): int
    {
        return 12 << 11;
    }

    private static function tanggal(): int
    {
        return ((2026 - 1980) << 9) | (1 << 5) | 1;
    }
}

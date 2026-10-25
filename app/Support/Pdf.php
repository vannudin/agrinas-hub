<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Penulis PDF minimal — cukup untuk dokumen satu halaman berisi tabel.
 *
 * Kenapa tidak memakai pustaka: menambahkan dompdf ke `composer install` berarti
 * seluruh instalasi harus bisa menarik paket dari internet, sedangkan isi surat
 * jalan ini hanya teks, garis, dan dua tanda tangan pada satu halaman A4.
 * Menulis sendiri bentuk PDF-nya (± 150 baris) jauh lebih kecil daripada satu
 * dependensi baru beserta aset font-nya.
 *
 * ponytail: hanya teks Latin-1 (WinAnsi), satu halaman, tanpa pemenggalan kata.
 * Kalau suatu hari butuh > 1 halaman / Unicode / gambar, pindah ke dompdf —
 * antarmuka `dariBlade()/keluaran()` tidak perlu berubah.
 */
class Pdf
{
    public const LEBAR = 595.28;   // A4 dalam titik (1/72 inci)
    public const TINGGI = 841.89;

    /** @var list<array{0: float, 1: float, 2: int, 3: float, 4: string}> */
    private array $teks = [];
    /** @var list<array{0: float, 1: float, 2: float, 3: float}> */
    private array $garis = [];

    private function __construct(private string $judul = '') {}

    /** Mulai dokumen baru. */
    public static function lembar(string $judul = ''): self
    {
        return new self($judul);
    }

    /**
     * Render satu blade menjadi PDF satu halaman.
     *
     * Blade-nya menulis baris teks/garis ke `$p` (objek ini), bukan HTML —
     * jadi seluruh tata letak tetap di berkas view, sementara bentuk PDF-nya
     * satu tempat: kelas ini.
     */
    public static function dariBlade(string $view, array $data = [], string $judul = ''): self
    {
        $p = new self($judul);
        app('view')->make($view, $data + ['p' => $p])->render();

        return $p;
    }

    // ------------------------------------------------------------- menulis isi

    /** Satu baris teks. $x,$y dihitung dari kiri-atas (lebih enak dibaca). */
    public function teks(float $x, float $y, string $isi, float $ukuran = 10, bool $tebal = false): void
    {
        // Ukuran disimpan PER BARIS: satu nilai bersama membuat setiap baris
        // tercetak sebesar baris terakhir yang ditulis.
        $this->teks[] = [$x, self::TINGGI - $y, $tebal ? 2 : 1, $ukuran, $this->bersih($isi)];
    }

    /** Baris horizontal. */
    public function garis(float $x, float $y, float $x2, ?float $y2 = null): void
    {
        $this->garis[] = [$x, self::TINGGI - $y, $x2, self::TINGGI - ($y2 ?? $y)];
    }

    /** Lebar perkiraan teks (Helvetica ~0.5 em per huruf). */
    public static function lebarTeks(string $isi, float $ukuran = 10): float
    {
        return mb_strlen($isi) * $ukuran * 0.5;
    }

    // --------------------------------------------------------------- keluaran

    /** Byte PDF siap diunduh. */
    public function keluaran(): string
    {
        $isi = $this->halaman();

        $isiDatar = $this->deflate($isi);
        $filter = ' /Filter /FlateDecode';

        $objek = [];
        $objek[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objek[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objek[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::LEBAR.' '.self::TINGGI.']'
            .' /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>';
        $objek[4] = '<< /Length '.strlen($isiDatar).$filter.' >>'."\nstream\n".$isiDatar."\nendstream";
        $objek[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objek[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        if ($this->judul !== '') {
            $objek[7] = '<< /Title ('.$this->bersih($this->judul).') /Producer (Agrinas Hub) >>';
        }

        $out = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $offset = [];
        foreach ($objek as $n => $badan) {
            $offset[$n] = strlen($out);
            $out .= $n.' 0 obj'."\n".$badan."\nendobj\n";
        }

        $xref = strlen($out);
        $maks = max(array_keys($objek));
        $out .= 'xref'."\n".'0 '.($maks + 1)."\n"."0000000000 65535 f \n";
        for ($n = 1; $n <= $maks; $n++) {
            $out .= isset($offset[$n])
                ? sprintf("%010d 00000 n \n", $offset[$n])
                : "0000000000 65535 f \n";
        }
        $out .= 'trailer'."\n".'<< /Size '.($maks + 1).' /Root 1 0 R /Info '
            .(isset($objek[7]) ? '7 0 R' : '1 0 R').' >>'."\n".'startxref'."\n".$xref."\n%%EOF";

        return $out;
    }

    /** Simpan ke berkas. */
    public function simpan(string $path): void
    {
        file_put_contents($path, $this->keluaran());
    }

    // ---------------------------------------------------------------- internal

    private function halaman(): string
    {
        $g = [];
        $g[] = '0.5 w';
        foreach ($this->garis as [$x, $y, $x2, $y2]) {
            $g[] = sprintf('%.2F %.2F m %.2F %.2F l S', $x, $y, $x2, $y2);
        }
        foreach ($this->teks as [$x, $y, $font, $ukuran, $isi]) {
            $g[] = sprintf("BT /F%d %.2F Tf %.2F %.2F Td (%s) Tj ET", $font, $ukuran, $x, $y,
                $this->escape($isi));
        }

        return implode("\n", $g);
    }

    private function deflate(string $isi): string
    {
        $z = gzcompress($isi, 6);
        if ($z === false) {
            throw new InvalidArgumentException('Isi PDF tidak bisa dikompresi.');
        }

        return $z;
    }

    /** Teks PDF harus WinAnsi; karakter di luar itu diganti titik. */
    private function bersih(string $isi): string
    {
        $out = '';
        foreach (preg_split('//u', $isi, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $out .= mb_ord($ch) <= 255 ? $ch : '.';
        }

        return $out;
    }

    private function escape(string $isi): string
    {
        return strtr($isi, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }
}

<?php

namespace App\Support;

/**
 * Sandi dua lapis.
 *
 *   lapis 1 — HMAC-SHA256 ber-kunci `APP_KEY`: sandi mentah TIDAK pernah masuk
 *             ke fungsi hash maupun ke berkas basis data;
 *   lapis 2 — scrypt r=8, p=1 (sama dengan bawaan libsodium, OPSLIMIT_INTERACTIVE)
 *             atas hasil lapis 1, salt 32 bita acak per akun.
 *
 * Bocornya berkas basis data saja tidak cukup: tanpa APP_KEY penyerang tidak
 * bisa menguji tebakan sandi secara luring, karena lapis 1 memakai kunci itu.
 * Dua lapis juga berarti hash lama (satu lapis, `scrypt$…`) tetap bisa diperiksa
 * dan dinaikkan diam-diam ke dua lapis saat pemiliknya berhasil masuk.
 *
 * Biaya: 16384 * 32 = opslimit 524288, memori 128*N*8 bita (16 MiB) per percobaan.
 *
 * ponytail: kunci lapis 1 diturunkan dari APP_KEY, jadi mengganti APP_KEY
 * membatalkan semua sandi. Kalau perlu rotasi kunci, simpan kunci lapis 1 di
 * env tersendiri (SANDI_PEPPER) dan tambahkan versi kunci pada format.
 */
final class ScryptHasher
{
    public const N = 16384;

    /** Awalan format dua lapis. Tanpa awalan ini = warisan satu lapis. */
    private const VERSI = 'scrypt2';

    /**
     * Kunci lapis 1. Diturunkan (bukan disimpan) dari APP_KEY sehingga satu
     * rahasia saja yang perlu dijaga, sama seperti enkripsi kolom.
     */
    private static function kunci(): string
    {
        return hash_hmac('sha256', 'agrinas-hub-sandi-lapis-1', (string) config('app.key'), true);
    }

    /** Lapis 1: ubah sandi menjadi sidik ber-kunci sebelum di-scrypt. */
    private static function lapis1(string $sandi): string
    {
        return hash_hmac('sha256', $sandi, self::kunci());
    }

    /** Kebutuhan memori scrypt = 128 * N * r bita (r = 8), minimum yang diterima libsodium. */
    private static function memlimit(int $n): int
    {
        return max(128 * $n * 8, SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_MEMLIMIT_INTERACTIVE);
    }

    public static function buat(string $sandi, int $n = self::N): string
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_SALTBYTES);
        $hitung = sodium_crypto_pwhash_scryptsalsa208sha256(32, self::lapis1($sandi), $salt, $n * 32, self::memlimit($n));

        return sprintf('%s$%d$8$1$%s$%s', self::VERSI, $n, self::b64($salt), self::b64($hitung));
    }

    public static function cek(string $sandi, ?string $tersimpan): bool
    {
        $bagian = explode('$', (string) $tersimpan);
        if (count($bagian) !== 6) {
            return false;
        }

        [$versi, $n, $r, $p, $saltB64, $hashB64] = $bagian;
        if ($versi !== self::VERSI && $versi !== 'scrypt') {
            return false;
        }
        if ((int) $r !== 8 || (int) $p !== 1) {
            return false; // libsodium mengunci r=8, p=1
        }
        $salt = base64_decode(strtr($saltB64, '-_', '+/'), true);
        if ($salt === false || strlen($salt) !== SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_SALTBYTES) {
            return false;
        }

        // Format warisan menyimpan hasil scrypt atas sandi mentah.
        $masukan = $versi === self::VERSI ? self::lapis1($sandi) : $sandi;

        $hitung = sodium_crypto_pwhash_scryptsalsa208sha256(32, $masukan, $salt, (int) $n * 32, self::memlimit((int) $n));

        return hash_equals($hashB64, self::b64($hitung));
    }

    /** Benar bila hash tersimpan masih satu lapis dan pantas dinaikkan. */
    public static function perluDiperbarui(?string $tersimpan): bool
    {
        return strncmp((string) $tersimpan, self::VERSI.'$', strlen(self::VERSI) + 1) !== 0;
    }

    private static function b64(string $b): string
    {
        return rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
    }
}

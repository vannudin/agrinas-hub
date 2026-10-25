<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Penjaga ritme panggilan login ke SIMKOPDES.
 *
 * SIMKOPDES membatasi /login lewat `x-ratelimit-limit: 10` dan mencatat asal
 * permintaan per IP. Tanpa jeda, Hub mengirim login beruntun dari satu IP
 * (terukur 23 Okt 2026: 140 akun login dalam satu jam, 171 gagal) sehingga dari
 * sisi mereka pola itu terlihat seperti skrip penebak sandi, bukan pengguna.
 *
 * Aturannya, sebelum setiap /login:
 *   - jaga jarak minimal JARAK_DETIK sejak login terakhir (≤10 per menit),
 *   - tambahkan jeda acak kecil supaya jaraknya tidak persis seperti mesin,
 *   - tolak (jangan menggantung) bila BATAS_PER_MENIT sudah terpakai.
 *
 * ponytail: penghitung jendela disimpan di cache file dengan baca-ubah-tulis
 * tanpa kunci, jadi dua proses yang benar-benar serentak bisa lolos bersamaan.
 * Cukup untuk satu Hub; naikkan ke Redis + lock kalau login latar jadi ramai.
 */
class JedaLogin
{
    /** Jarak minimal antar-login. Kuota SIMKOPDES: 10 per jendela. */
    private const JARAK_DETIK = 6.0;

    /** Batas login per 60 detik — di bawah kuota mereka, dengan sisa untuk retry. */
    private const BATAS_PER_MENIT = 9;

    /**
     * Batas login otomatis per hari untuk SELURUH server. Tanpa ini, satu
     * gangguan di sisi mereka (endpoint pindah / minta captcha) berubah jadi
     * ratusan login sia-sia dari satu IP — 23 Okt 2026: 140 akun/jam, semua 403.
     */
    private const BATAS_PER_HARI = 40;

    private const KUNCI_TERAKHIR = 'simkopdes.login.terakhir';
    private const KUNCI_JENDELA = 'simkopdes.login.jendela';
    private const KUNCI_HARIAN = 'simkopdes.login.harian';

    /** Identitas klien yang jujur — bukan UA bawaan Guzzle yang berbau skrip. */
    public const UA = 'AgrinasHub/1.0 (+https://agrinashub.com)';

    /** true = boleh lanjut (sudah menunggu seperlunya); false = jatah habis. */
    public static function tunggu(): bool
    {
        if (count(self::jendela()) >= self::BATAS_PER_MENIT) {
            return false;
        }

        if ((int) Cache::get(self::KUNCI_HARIAN, 0) >= self::BATAS_PER_HARI) {
            return false;
        }

        $jeda = self::JARAK_DETIK - (microtime(true) - (float) Cache::get(self::KUNCI_TERAKHIR, 0.0));
        if ($jeda > 0) {
            usleep((int) ($jeda * 1000000));
        }

        usleep(random_int(150000, 450000));

        $sekarang = microtime(true);
        Cache::put(self::KUNCI_TERAKHIR, $sekarang, 300);

        $jendela = self::jendela();
        $jendela[] = $sekarang;
        Cache::put(self::KUNCI_JENDELA, $jendela, 120);
        Cache::put(self::KUNCI_HARIAN, (int) Cache::get(self::KUNCI_HARIAN, 0) + 1, now()->addDay());

        return true;
    }

    /** Login dalam 60 detik terakhir. */
    private static function jendela(): array
    {
        $batas = microtime(true) - 60;

        return array_values(array_filter(
            (array) Cache::get(self::KUNCI_JENDELA, []),
            static fn ($t) => (float) $t > $batas
        ));
    }
}

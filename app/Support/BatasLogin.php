<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Pembatas percobaan masuk: 5 kegagalan per email, terkunci 15 menit.
 * ponytail: disimpan di cache file (satu server). Pindah ke Redis kalau multi-instance.
 */
final class BatasLogin
{
    private const MAKS = 5;
    private const DETIK = 900;

    public static function kunci(string $email): string
    {
        return 'gagal-login:'.mb_strtolower(trim($email));
    }

    public static function boleh(string $email): bool
    {
        return count(Cache::get(self::kunci($email), [])) < self::MAKS;
    }

    public static function catat(string $email): void
    {
        $kunci = self::kunci($email);
        $jejak = Cache::get($kunci, []);
        $jejak[] = time();
        Cache::put($kunci, array_slice($jejak, -self::MAKS), self::DETIK);
    }

    public static function bersihkan(string $email): void
    {
        Cache::forget(self::kunci($email));
    }
}

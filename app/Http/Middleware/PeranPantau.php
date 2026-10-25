<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pagar TULIS untuk peran pengawasan (TNI & Dinas Koperasi).
 *
 * Peran ini memantau, bukan mengerjakan: seluruh halaman boleh dibaca, tetapi
 * tidak ada satu pun aksi yang boleh mengubah data. Dipasang sekali di grup
 * `auth` (berlaku untuk SEMUA rute login), bukan ditempel per halaman — supaya
 * rute baru otomatis ikut terlindungi.
 *
 * Satu-satunya pengecualian: unggah materi pembelajaran (`admin.materi.unggah`),
 * yang memang diminta Bos boleh dilakukan kedua peran ini. Hapus materi tetap
 * milik pusat (rutenya di grup `agrinas`).
 */
class PeranPantau
{
    /**
     * Rute POST yang tetap dibuka untuk pengawasan:
     *  - `admin.materi.unggah`: menyumbang materi pembelajaran (permintaan Bos).
     *  - `profil.sandi`: WAJIB. Akun baru dibuat dengan `wajib_ganti = 1`, jadi
     *    tanpa pengecualian ini pengawasan terkunci selamanya di halaman ganti
     *    sandi — tidak bisa masuk sama sekali.
     */
    private const BOLEH_TULIS = ['admin.materi.unggah', 'profil.sandi'];

    /**
     * Rute BACA yang tetap bukan milik pengawasan: Asisten AI (memanggil model
     * bahasa atas biaya perusahaan) dan Ruang Kerja/Message (percakapan —
     * tanpa hak kirim jadi jalan buntu). Hanya `daftar_menu()` yang menyembunyikan
     * menu tidak cukup: alamat yang diketik langsung tetap harus ditolak.
     */
    private const TOLAK_BACA = ['asisten', 'workspace'];

    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        if ($u && $u->pantau()) {
            $namaRute = (string) $request->route()?->getName();
            foreach (self::TOLAK_BACA as $awalan) {
                if ($namaRute === $awalan || str_starts_with($namaRute, $awalan.'.')) {
                    abort(403, 'Akun pengawasan membaca data; Asisten AI dan Ruang Kerja bukan bagiannya.');
                }
            }
        }

        if ($u && $u->pantau() && ! $request->isMethodSafe()
            && ! in_array($request->route()?->getName(), self::BOLEH_TULIS, true)) {
            abort(403, 'Peran pengawasan hanya boleh membaca. Aksi ini khusus pengelola.');
        }

        return $next($request);
    }
}

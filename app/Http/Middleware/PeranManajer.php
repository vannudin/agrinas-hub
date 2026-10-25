<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi rute ke MANAJER koperasi saja.
 *
 * Dipakai rute manajer yang isinya urusan pribadi manajer (dulu halaman Lark:
 * koneksi akun Lark pribadi). Alias 'manajer' di bootstrap/app.php dipertahankan
 * karena uji regresi memeriksa bahwa rute manajer benar-benar digerbangi alias
 * ini — menghapusnya akan membuat pemeriksaan itu tidak lagi bermakna.
 *
 * Diperlukan sebagai MIDDLEWARE, bukan sekadar `$u->manajerKoperasi()` di dalam
 * controller, karena satu rute bisa punya beberapa aksi (konek/sinkron/lepas) dan
 * satu penjagaan di gerbang rute menutup semuanya sekaligus.
 */
class PeranManajer
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            return redirect('/masuk');
        }
        if (! $u->manajerKoperasi()) {
            abort(403, 'Halaman ini khusus manajer koperasi.');
        }

        return $next($request);
    }
}

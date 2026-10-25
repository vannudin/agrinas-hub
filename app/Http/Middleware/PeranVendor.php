<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pagar halaman kerja vendor (`/mitra`).
 *
 * Dua syarat, bukan satu: perannya VENDOR **dan** akunnya tertaut ke satu baris
 * `vendor`. Akun VENDOR tanpa tautan tidak punya vendor untuk menyaring barang,
 * sehingga halamannya akan menampilkan seluruh pesanan Agrinas — persis
 * kebocoran yang ingin dicegah. Syaratnya dibaca dari `User::vendorAkun()` agar
 * menu, penjaga rute, dan controller memakai definisi yang sama.
 */
class PeranVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            return redirect('/masuk');
        }
        if (! $u->vendorAkun()) {
            abort(403, 'Halaman ini khusus akun vendor yang tertaut ke satu vendor.');
        }

        return $next($request);
    }
}

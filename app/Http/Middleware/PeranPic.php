<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi rute ke peran Agrinas pusat, petugas HR, ATAU PIC wilayah.
 *
 * PIC tidak boleh memakai kendali khusus pusat (tambah/ubah koperasi, akun,
 * siaran); itu diperiksa lagi di dalam controller masing-masing. Petugas HR ikut
 * karena lingkupnya memang lintas wilayah (lihat User::lingkupPenuh) — pagar
 * wilayah akan memotong daftar koperasi yang harus ia periksa, sementara
 * halaman SDM sendiri tetap dijaga penjaga `hrd`.
 */
class PeranPic
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            return redirect('/masuk');
        }
        if (! $u->agrinas() && ! $u->pic() && ! $u->hrd() && ! $u->pantau()) {
            abort(403, 'Halaman ini khusus pengguna Agrinas, petugas HR, PIC wilayah, atau pengawasan.');
        }

        return $next($request);
    }
}

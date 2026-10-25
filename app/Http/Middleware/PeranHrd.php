<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi rute ke admin Agrinas pusat ATAU petugas HR.
 *
 * Dipakai meja SDM & Kepegawaian: isinya identitas, kontak, dan kontrak SELURUH
 * pegawai lintas wilayah — kewenangan kepegawaian, bukan kewenangan wilayah,
 * jadi PIC TIDAK termasuk. Sengaja terpisah dari `agrinas` supaya peran HR bisa
 * dibuka tanpa memberi akses ke kendali pusat yang lain (akun, broadcast,
 * SIMKOPDES, vendor).
 */
class PeranHrd
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            return redirect('/masuk');
        }
        if (! $u->hrd()) {
            abort(403, 'Halaman ini khusus admin pusat atau petugas HR.');
        }

        return $next($request);
    }
}

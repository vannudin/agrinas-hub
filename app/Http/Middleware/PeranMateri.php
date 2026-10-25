<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Materi pembelajaran: pusat, TNI, dan Dinas Koperasi boleh membuka daftar
 * kelola & mengunggah. Dipisah dari `agrinas` supaya dua peran pengawasan bisa
 * menyumbang materi tanpa ikut membuka kendali pusat (akun, siaran, vendor).
 */
class PeranMateri
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            return redirect('/masuk');
        }
        if (! in_array($u->peran, Domain::PERAN_MATERI, true)) {
            abort(403, 'Halaman ini khusus admin pusat, TNI, dan Dinas Koperasi.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Batasi rute ke pengguna berperan AGRINAS. */
class PeranAgrinas
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            return redirect('/masuk');
        }
        if ($u->peran !== Domain::PERAN_AGRINAS) {
            abort(403, 'Halaman ini khusus pengguna Agrinas.');
        }

        return $next($request);
    }
}

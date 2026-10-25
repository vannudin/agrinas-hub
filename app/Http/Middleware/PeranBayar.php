<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi rute ke pengelola yang berwenang MENGELUARKAN UANG: admin pusat,
 * PIC wilayah, dan petugas keuangan.
 *
 * Kenapa bukan `wilayah`: middleware itu juga membuka halaman pusat (dasbor,
 * koperasi, pemetaan, audit) yang bukan pekerjaan keuangan. Jadi pagar uang
 * dipisah — sempit dan tepat sasaran, satu gerbang untuk seluruh aksi bayar.
 *
 * Manajer koperasi tetap DITOLAK: ia pihak yang mengajukan, dan pengaju tidak
 * boleh menyetujui/membayar pengajuannya sendiri (diperiksa lagi di controller).
 */
class PeranBayar
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) {
            return redirect('/masuk');
        }
        // PIC ikut di sini — gate pasokan menegaskan PIC berwenang memutus
        // pembayaran koperasi di wilayahnya. Yang TIDAK boleh bagi PIC adalah
        // mencatat pelunasan ORDER TOKO (mengubah kas), dan itu diperiksa di
        // controller lewat `bolehBayar()` (pusat/keuangan saja).
        if (! ($u->agrinas() || $u->pic() || $u->keuangan())) {
            abort(403, 'Persetujuan & pembayaran hanya untuk admin pusat, PIC wilayah, atau petugas keuangan.');
        }

        return $next($request);
    }
}

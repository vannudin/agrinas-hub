<?php

use App\Http\Middleware\PeranAgrinas;
use App\Http\Middleware\PeranBayar;
use App\Http\Middleware\PeranHrd;
use App\Http\Middleware\PeranManajer;
use App\Http\Middleware\PeranMateri;
use App\Http\Middleware\PeranPantau;
use App\Http\Middleware\PeranPic;
use App\Http\Middleware\PeranVendor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Cache konfigurasi bisa lahir dari proses LAIN (artisan yang dijalankan di
// host, bukan di dalam container) dan memuat base path host. Begitu cache itu
// ada, session/cache menulis ke path yang tidak ada di dalam container dan
// SELURUH halaman jadi 500 — sudah terjadi dua kali (21–22 Okt 2026). Cache
// dengan base path asing DIBUANG, bukan dihormati: lebih murah membaca .env
// ulang daripada satu instance mati karena berkas yang salah tempat.
$cacheKonfig = __DIR__.'/cache/config.php';
if (is_file($cacheKonfig)
    && ! str_contains((string) @file_get_contents($cacheKonfig), dirname(__DIR__).'/storage/framework/sessions')) {
    @unlink($cacheKonfig);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // cloudflared berjalan di loopback: di baliknya Laravel hanya melihat
        // http, sehingga url() menghasilkan http:// untuk og:image/canonical.
        // Percayai HANYA loopback (bukan '*') supaya header X-Forwarded-Proto
        // dari jaringan tidak bisa dipalsukan.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        // Rute masuk kita bernama `masuk`, bukan `login` bawaan Laravel.
        // Tanpa ini, middleware auth mengarahkan ke rute yang tidak ada -> 500.
        $middleware->redirectGuestsTo('/masuk');

        // Callback ONLYOFFICE datang dari server dokumen, bukan peramban: tidak
        // ada sesi dan tidak ada token CSRF, jadi request-nya akan selalu 419.
        // Penjaganya bukan CSRF melainkan tanda tangan URL (middleware `signed`)
        // plus rahasia JWT yang hanya diketahui kedua server. Dikecualikan hanya
        // untuk jalur callback/berkas itu sendiri, bukan seluruh aplikasi.
        $middleware->validateCsrfTokens(except: [
            'workspace/dokumen/*',
        ]);

        $middleware->alias([
            'agrinas' => PeranAgrinas::class,
            // 'wilayah' = Agrinas pusat ATAU PIC wilayah (kendali khusus pusat
            // diperiksa lagi di controller).
            'wilayah' => PeranPic::class,
            // 'hrd' = admin pusat ATAU petugas HR. Terpisah dari 'agrinas' supaya
            // peran HR bisa membuka meja SDM tanpa ikut membuka kendali pusat.
            'hrd' => PeranHrd::class,
            // 'bayar' = siapa yang boleh MENGELUARKAN UANG: pusat, PIC, dan
            // petugas keuangan (peran KEUANGAN). Terpisah dari 'wilayah' karena
            // PIC & keuangan tidak boleh membuka halaman pusat (dasbor, akun).
            'bayar' => PeranBayar::class,
            // 'manajer' = manajer koperasi saja (rute berisi urusan pribadi
            // pribadi manajer; admin pusat & PIC memang bukan pemiliknya).
            'manajer' => PeranManajer::class,
            // 'pantau' = pagar TULIS peran pengawasan (TNI & Dinas Koperasi):
            // semua boleh dibaca, semua POST/PUT/DELETE ditolak kecuali unggah
            // materi. Dipasang di grup `auth`, jadi rute baru ikut terjaga.
            'pantau' => PeranPantau::class,
            // 'materi' = pusat + TNI + Dinas: kelola & unggah materi pembelajaran.
            'materi' => PeranMateri::class,
            // 'vendor' = akun mitra penyalur yang tertaut ke satu baris `vendor`
            // (halaman kerja /mitra: pesanan barangnya + tagihan ke Agrinas).
            'vendor' => PeranVendor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

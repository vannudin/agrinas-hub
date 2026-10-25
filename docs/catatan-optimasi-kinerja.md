# Catatan Optimasi Kinerja AgrinasHub — BELUM DIKERJAKAN

Dicatat 22 Okt 2026 atas permintaan Bos. **Jangan dijalankan sebelum Bos memberi izin.**
Tidak ada perubahan kode/konfigurasi/produksi yang dilakukan saat pencatatan ini.

## Keputusan awal: TIDAK migrasi ke Rust

Pertanyaan Bos: migrasi ke Rust atau "combine" dengan JS+AJAX agar lebih optimal.
Jawaban: **tidak**, karena bottleneck bukan di PHP.

Bukti ukur (22 Okt 2026, dari host WSL):

| Hal | Angka |
|---|---|
| `/admin/akun` server-side | 42 ms, HTML 77 KB |
| `/pasokan` | 50 ms |
| `/admin/rapat` | 28 ms |
| `/kerja` | 23 ms |
| 20 permintaan paralel `/admin/akun` | median 72 ms, maks 124 ms |
| TTFB produksi `https://agrinashub.com/masuk` | **438 ms** |
| opcache | terpasang (`opcache.so` ada) tapi **TIDAK AKTIF** |
| aset | `hub.css` 261 KB, JS 528 KB / 22 berkas |
| kode | 143 berkas `app/`+Blade, ±47.000 baris, 59 gate regresi |
| server | `php artisan serve --host=0.0.0.0 --port=8091 --no-reload`, `PHP_CLI_SERVER_WORKERS=8` |

Bacaan: 438 ms TTFB vs 16–50 ms di lokal → >95% waktu habis di jaringan
(Starlink CGNAT + Cloudflare), bukan PHP. Rewrite Rust 47k baris = 6–12 bulan
+ risiko regresi, imbalannya 15–25 ms yang tak terasa.

AJAX/JS juga bukan percepatan: 22 berkas JS sudah ada, `/toko` sudah
gulir-tak-berujung (`hub-toko.js`). AJAX memindahkan waktu render, query tetap sama.

## Rencana berurutan (murah → mahal), SEMUA BELUM DIJALANKAN

### 1. Aktifkan opcache — aman, manfaat nyata, BELUM
- `opcache.so` ada di `/usr/local/lib/php/extensions/*/`, `php -m` tidak memuatnya,
  `opcache.enable_cli=0`.
- Perlu `opcache.enable=1` + `opcache.enable_cli=1` (SAPI = CLI, karena `artisan serve`).
- Dampak: ~10–25 ms/req. Tidak mengubah output/HTML/desain.
- Biaya: **restart kontainer** `agrinas-laravel` (~3 detik downtime).
- `validate_timestamps` default 2 detik → berkas yang diedit tetap terbaca.

### 2. `artisan config:cache route:cache view:cache` — aman, 1 syarat, BELUM
- **WAJIB dijalankan di dalam kontainer**: `docker exec agrinas-laravel php artisan config:cache`.
  Cache yang dibuat dari host memuat base path host → seluruh halaman 500
  (sudah terjadi 2× pada 21–22 Okt). `bootstrap/app.php` baris 22–26 sekarang
  membuang cache ber-base-path asing sebagai jaring pengaman.
- Diperiksa: **0 closure** di `routes/*.php` (route:cache aman), **0** `env()`
  di luar `config/` (config:cache aman).
- Konsekuensi operasional: setelah cache ada, perubahan `.env`/rute TIDAK
  berlaku sampai `php artisan optimize:clear`. Disiplin deploy berubah.

### 3. Aset: TIDAK perlu minify — cukup purge, BELUM diverifikasi ulang saat ubah
- Diukur: `content-encoding: gzip`, `cf-cache-status: HIT`,
  `cache-control: public, max-age=604800` → `hub.css` 261 KB sudah terkirim ~40 KB.
- Minify manual hemat ~7 KB dan berisiko merusak `calc()`/media query. **Jangan.**
- Yang benar: purge Cloudflare untuk `css`/`js` setiap `hub.css` berubah
  (cache `immutable` 1 tahun bisa menyajikan versi basi).

### 4. php-fpm + nginx — JANGAN sekarang
- Satu-satunya langkah yang mengubah cara aplikasi dijalankan (entrypoint,
  permission `storage/`, `PATH_INFO`, env) → risiko 500.
- Manfaat nol selama trafik masih longgar di 8 worker. Tunda sampai terbukti perlu.

### 5. Indeks SQLite — nanti, ukur dulu
- `CREATE INDEX` tidak mengubah data maupun HTML; risiko hanya query plan berubah.
- Cadangkan `agrinas_hub.db` (±27 MB di `/data/agrinas_hub.db`) sebelum menyentuh.
- Jangan menebak: pakai `EXPLAIN QUERY PLAN` pada query yang memang lambat.

## Cara mengerjakan dengan aman (usul, belum dilakukan)
1. Nyalakan opcache di **wadah uji** (`--rm`, port 8099, image + mount + env sama
   dengan produksi) dan ukur `/admin/akun` sebelum/sesudah.
2. Baru setelah angkanya membaik: restart kontainer produksi.
3. Ukur ulang TTFB produksi sebelum/sesudah tiap langkah; satu langkah per commit
   catatan agar penyebab perubahan jelas.

## Berkas yang akan disentuh kalau nanti dieksekusi
- `docker` (restart kontainer + `exec` artisan) — tidak ada berkas repo.
- `bootstrap/cache/` (hasil `artisan config:cache route:cache view:cache`).
- Tidak ada perubahan pada `resources/views/`, `public/css`, `public/js`, `app/`.

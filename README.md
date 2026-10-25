<div align="center">

# Agrinas Hub

**Ruang kerja digital untuk koperasi desa: satu tempat untuk operasional, keuangan, distribusi, dan pelaporan.**

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20?logo=laravel&logoColor=white)](https://laravel.com/)
[![SQLite](https://img.shields.io/badge/SQLite-3-003b57?logo=sqlite&logoColor=white)](https://sqlite.org/)
[![Lisensi](https://img.shields.io/badge/lisensi-MIT-2ea44f)](LICENSE)
[![Uji](https://github.com/USERNAME/agrinas-hub/actions/workflows/ci.yml/badge.svg)](https://github.com/USERNAME/agrinas-hub/actions/workflows/ci.yml)

</div>

---

## Daftar Isi

- [Tentang](#tentang)
- [Fitur](#fitur)
- [Peran dan hak akses](#peran-dan-hak-akses)
- [Tumpukan teknologi](#tumpukan-teknologi)
- [Struktur repositori](#struktur-repositori)
- [Menjalankan di komputer lokal](#menjalankan-di-komputer-lokal)
- [Akun demo](#akun-demo)
- [Variabel lingkungan](#variabel-lingkungan)
- [Basis data](#basis-data)
- [Pengujian](#pengujian)
- [Catatan keamanan](#catatan-keamanan)
- [Berkontribusi](#berkontribusi)
- [Lisensi](#lisensi)

---

## Tentang

Agrinas Hub adalah aplikasi web untuk mengelola koperasi desa dari hulu ke hilir.
Aplikasi ini menyatukan pekerjaan yang biasanya tersebar di banyak berkas Excel dan
grup obrolan: data koperasi dan wilayah, kepegawaian dan absensi, katalog barang
serta toko koperasi, penjualan dan buku kas, distribusi pasokan dari gudang,
kontrak kerja sama, dokumen, pelatihan, sampai ruang komunikasi internal.

Aplikasi dirancang untuk banyak peran sekaligus — pengelola pusat, penanggung jawab
wilayah, manajer koperasi, HR, petugas keuangan, pengawas (TNI/Dinas), dan vendor
mitra — dengan satu aturan yang dijaga konsisten: **menu, penjaga rute, dan isi
halaman memakai definisi peran yang sama**, sehingga tidak ada pengguna yang
melihat tautan menuju halaman yang menolaknya.

> **English summary** — Agrinas Hub is a Laravel 13 web application for managing
> Indonesian village cooperatives: members, HR and attendance, product catalogue and
> point-of-sale, cash book, supply distribution, contracts, documents, training and
> internal messaging, with role-based access for eight distinct roles. This
> repository ships without any production secret and includes a demo database with
> one account per role.

## Fitur

### Operasional koperasi

| Modul | Ringkasan |
| --- | --- |
| **Dasbor** | Ringkasan angka koperasi, pekerjaan harian, dan pintasan per peran. |
| **Koperasi** | Data induk koperasi, gerai, sarana, karyawan, dan status operasional. |
| **Pemetaan lahan** | Lokasi, luas, potensi, dan tahapan pembangunan tiap calon koperasi. |
| **Katalog & toko** | Katalog barang koperasi, harga jual dan harga grosir berjenjang, pesanan, surat jalan. |
| **Stok** | Stok barang, penyesuaian, dan riwayat perubahan (hitung fisik). |
| **Distribusi** | Pasokan dari gudang ke koperasi, pengajuan, keputusan, dan penerimaan. |
| **Keuangan** | Buku kas, pemasukan/pengeluaran, saldo, dan rekap. |

### Kepegawaian

| Modul | Ringkasan |
| --- | --- |
| **HR & SDM** | Data pegawai, kontrak (PKWT), penempatan, dan status kepegawaian. |
| **Absensi** | Kehadiran harian dengan foto masuk/pulang serta rekap per periode. |
| **Kontrak** | Periode kontrak, penempatan koperasi, dan riwayat per orang. |
| **Pelatihan** | Materi, progres belajar, dan penyelesaian pelatihan. |

### Kolaborasi dan tata kelola

| Modul | Ringkasan |
| --- | --- |
| **Pesan** | Ruang obrolan per koperasi/wilayah, pesan pribadi, tanda dibaca. |
| **Aspirasi** | Kanal laporan dan keluhan pengguna beserta tindak lanjutnya. |
| **Pengumuman** | Pengumuman tersegmen dengan status baca per akun. |
| **Dokumen** | Unggah, izin akses, dan pratinjau dokumen (Word/Excel/PowerPoint). |
| **Rapat** | Penjadwalan rapat, kehadiran, dan presentasi di dalam rapat. |
| **Asisten AI** | Tanya-jawab operasional di dalam aplikasi (endpoint bergaya OpenAI, opsional). |
| **Audit** | Jejak tindakan penting (masuk, ubah data, ekspor) untuk penelusuran. |

## Peran dan hak akses

| Peran | Kode | Beranda | Lingkup |
| --- | --- | --- | --- |
| Admin pusat | `AGRINAS` | `/admin` | Seluruh negeri, semua modul. |
| HR pusat | `HR` | `/admin` | Meja SDM & kepegawaian. |
| Penanggung jawab wilayah | `PIC` | `/admin` | Koperasi di wilayahnya saja. |
| Manajer koperasi | `MANAJER` | `/kerja` | Satu koperasi: operasional, stok, kas, karyawan. |
| Petugas keuangan | `KEUANGAN` | `/keuangan` | Buku kas dan pembayaran, tanpa kendali pusat. |
| Pengawas TNI | `TNI` | `/admin` | Hanya-baca, wilayahnya sendiri. |
| Dinas Koperasi | `DINAS` | `/admin` | Hanya-baca + unggah materi. |
| Vendor mitra | `VENDOR` | `/mitra` | Pesanan, pengiriman, dan tagihan milik vendornya. |

Aturan penyaringan berlapis: peran menentukan menu yang tampil, middleware
menentukan rute yang boleh dibuka, dan controller menyaring data sesuai wilayah
atau koperasi milik pengguna. Ketiga lapisan memakai helper yang sama
(`daftar_menu()`, `boleh_rute()`, `rute_dasbor()`) supaya tidak pernah menyimpang.

## Tumpukan teknologi

- **PHP 8.3+** dengan **Laravel 13**
- **SQLite** (mode WAL) sebagai basis data — tanpa server terpisah
- **Blade** + CSS/JS tanpa proses build wajib (aset ada di `public/`)
- **PHPUnit** untuk uji otomatis
- Integrasi opsional: Cloudflare R2 (berkas), OnlyOffice (editor dokumen),
  Cloudflare Realtime SFU (rapat), Turnstile (captcha), gerbang WhatsApp,
  dan endpoint AI bergaya OpenAI

## Struktur repositori

```text
app/
├── Console/Commands/       perintah artisan (termasuk hub:demo)
├── Http/Controllers/       controller per modul
├── Http/Middleware/        penjaga peran (agrinas, hrd, manajer, pic, …)
├── Models/                 model Eloquent + Domain (konstanta peran)
├── Services/               integrasi luar (WhatsApp, AI, dokumen)
└── Support/                helper bersama: menu, wilayah, sandi, pembatas login

database/
├── demo/skema.sql          struktur tabel (tanpa data pribadi)
├── migrations/             riwayat migrasi Laravel
└── seeders/                seeder bawaan

resources/views/            tampilan Blade per modul
routes/web.php              seluruh rute web + penjaganya
public/                     aset statis (css, js, img)
docs/                       catatan teknis
tests/                      uji PHPUnit
```

## Menjalankan di komputer lokal

**Prasyarat:** PHP 8.3 atau lebih baru dengan ekstensi `pdo_sqlite`, `sodium`,
`mbstring`, `openssl`, `dom`, `fileinfo`, `zip`, serta Composer 2.

```bash
# 1. Pasang dependensi
composer install

# 2. Siapkan konfigurasi lokal
cp .env.example .env
php artisan key:generate

# 3. Buat basis data demo (8 akun, satu per peran + data contoh)
php artisan hub:demo

# 4. Jalankan
php artisan serve --port=8000
```

Buka <http://localhost:8000>. Halaman masuk ada di `/masuk`.

Perintah `hub:demo` membuat berkas `database/demo/hub-demo.sqlite` dari
`database/demo/skema.sql` lalu mengisinya dengan data contoh. Perintah ini tidak
pernah menyentuh basis data kerja; jalankan kapan pun untuk mengembalikan data
demo ke kondisi awal.

## Akun demo

Semua akun memakai sandi yang sama: **`Demo#Koperasi2026`**

| Peran | Email | Beranda |
| --- | --- | --- |
| Admin pusat | `demo.agrinas@contoh.test` | `/admin` |
| HR pusat | `demo.hr@contoh.test` | `/admin` |
| PIC wilayah | `demo.pic@contoh.test` | `/admin` |
| Manajer koperasi | `demo.manajer@contoh.test` | `/kerja` |
| Petugas keuangan | `demo.keuangan@contoh.test` | `/keuangan` |
| Pengawas TNI | `demo.tni@contoh.test` | `/admin` |
| Dinas Koperasi | `demo.dinas@contoh.test` | `/admin` |
| Vendor mitra | `demo.vendor@contoh.test` | `/mitra` |

Akun ini **hanya untuk pengembangan lokal**. Jangan menyalinnya ke server mana pun
yang dapat diakses publik.

## Variabel lingkungan

Seluruh konfigurasi dibaca dari `.env`; contoh lengkap ada di
[`.env.example`](.env.example). Yang wajib untuk pengembangan:

| Kunci | Keterangan |
| --- | --- |
| `APP_KEY` | Kunci aplikasi (dibuat oleh `php artisan key:generate`). |
| `APP_URL` | Alamat aplikasi, dipakai penjagaan captcha dan tautan. |
| `DB_CONNECTION` | `hub` untuk basis data aplikasi. |
| `HUB_DB` | Lokasi berkas SQLite, mis. `database/demo/hub-demo.sqlite`. |
| `SESSION_DRIVER`, `CACHE_STORE` | `file` untuk pengembangan lokal. |
| `QUEUE_CONNECTION` | `sync` untuk pengembangan lokal. |
| `MAIL_MAILER` | `log` agar surel tidak benar-benar terkirim. |

Integrasi berikut **opsional** dan boleh dibiarkan kosong; fitur terkait akan
melapor belum dikonfigurasi tanpa mengganggu bagian lain:

`AI_*` (asisten AI) · `TURNSTILE_*` (captcha) · `R2_*` (penyimpanan berkas) ·
`ONLYOFFICE_*` (editor dokumen) · `CF_REALTIME_*` (rapat video) · `WA_URL`
(gerbang WhatsApp) · `SIMKOPDES_*`, `AC_*`, `LMS_*` (integrasi eksternal).

> **Repositori ini tidak pernah memuat kredensial.** Jangan mengomit `.env`,
> kunci API, tanda tangan JWT, atau berkas basis data produksi. Untuk nilai rahasia
> gunakan *GitHub Actions secrets* atau `.env` lokal yang tidak diomit.

## Basis data

- Skema asli berupa SQLite warisan; relasi antar tabel **tidak** dipaksakan oleh
  mesin basis data (`foreign_key_constraints => false`), jadi keutuhan data dijaga
  oleh aplikasi. Hapus data induk hanya setelah anak-anaknya dipindahkan atau
  dihapus.
- `database/demo/skema.sql` adalah struktur tabel tanpa data pribadi, dipakai
  `hub:demo`.
- Kolom sandi menyimpan hash **dua lapis** (`scrypt2$…`): HMAC-SHA256 ber-kunci
  `APP_KEY` lalu scrypt libsodium. Hash warisan satu lapis tetap dapat diperiksa
  dan dinaikkan diam-diam saat pemiliknya berhasil masuk.
- Kolom tanggal lahir disimpan terenkripsi (AES-256-GCM) dengan kunci `APP_KEY`.
  **Mengganti `APP_KEY` membatalkan seluruh sandi dan data tersandi.**

## Pengujian

```bash
# seluruh uji
php artisan test

# memeriksa sintaks semua berkas PHP
find app routes config database -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

# uji cepat dengan akun demo (aplikasi harus sedang berjalan)
bash tests/manual/uji_demo.sh
```

Uji otomatis memakai basis data sementara; jangan mengarahkannya ke basis data
kerja. Untuk menguji halaman yang memanggil layanan luar, gunakan `Http::fake()`.

## Catatan keamanan

- Tidak ada kredensial di dalam repositori, riwayat commit, maupun berkas contoh.
- Pembatas percobaan masuk: 5 kegagalan per email, terkunci 15 menit
  (`app/Support/BatasLogin.php`), dihitung sebelum pemeriksaan sandi.
- Captcha Cloudflare Turnstile dapat diaktifkan lewat `TURNSTILE_*`; bila kunci
  kosong, captcha dilewati sehingga pengembangan lokal tidak terhambat.
- Semua formulir memakai token CSRF; sesi disimpan di sisi server.
- Laporkan temuan kerentanan secara **pribadi** (lihat [`SECURITY.md`](SECURITY.md)),
  bukan melalui issue publik.

## Berkontribusi

Panduan lengkap ada di [`CONTRIBUTING.md`](CONTRIBUTING.md). Ringkasnya:

1. Buat cabang dari `main`: `feat/nama-fitur` atau `fix/nama-bug`.
2. Jaga perubahan tetap kecil dan terfokus; satu pull request satu tujuan.
3. Jalankan `php artisan test` sebelum mengirim.
4. Tulis pesan commit gaya *Conventional Commits* (`feat:`, `fix:`, `docs:`, `refactor:`).
5. Jelaskan **apa** dan **mengapa** di deskripsi pull request, sertakan langkah uji.

## Lisensi

Dirilis di bawah [MIT License](LICENSE).

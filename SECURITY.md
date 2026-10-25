# Kebijakan Keamanan

## Melaporkan kerentanan

Jangan membuka issue publik untuk masalah keamanan. Laporkan secara pribadi ke
pengelola repositori melalui **Security → Report a vulnerability** di GitHub, atau
kirim surel ke alamat pengelola yang tercantum pada profil organisasi.

Sertakan:

- jenis masalah dan dampaknya,
- langkah reproduksi yang paling singkat,
- peran akun yang dipakai (jangan mengirim kredensial),
- versi commit yang diuji.

Kami berusaha membalas dalam 3 hari kerja dan memberi kabar perkembangan sampai
perbaikan dirilis. Penemu masalah akan dicantumkan pada catatan rilis bila
menghendaki.

## Cakupan

Berlaku untuk kode di repositori ini. Lingkungan kerja, basis data, dan kredensial
produksi tidak termasuk cakupan dan tidak boleh diuji tanpa izin tertulis.

## Praktik yang dijaga repositori ini

- Tidak ada `.env`, kunci API, sandi, atau berkas basis data di dalam riwayat commit.
- `.gitignore` menolak `.env*`, `vendor/`, `storage/`, dan `database/demo/*.sqlite*`.
- Sandi disimpan sebagai hash dua lapis: HMAC-SHA256 ber-kunci `APP_KEY` lalu
  scrypt libsodium (`app/Support/ScryptHasher.php`).
- Kolom sensitif (tanggal lahir) disimpan terenkripsi AES-256-GCM.
- Pembatas percobaan masuk 5 kegagalan per email dengan jeda 15 menit, diperiksa
  sebelum kata sandi diverifikasi (`app/Support/BatasLogin.php`).
- Captcha Turnstile opsional pada halaman masuk dan aktivasi akun.
- Semua formulir memakai token CSRF; sesi disimpan di sisi server.

## Bila kunci terlanjur bocor

1. Ganti kunci di penyedia layanan (bukan sekadar menghapus dari commit).
2. Rotasi `APP_KEY` hanya bila data tersandi memang harus dibuat ulang — mengganti
   `APP_KEY` membatalkan seluruh sandi dan kolom terenkripsi.
3. Bersihkan riwayat commit dengan `git filter-repo`, lalu paksa semua kolaborator
   mengklon ulang repositori.
4. Catat kejadian dan tindakan pada catatan rilis.

# Catatan Perubahan

Semua perubahan penting proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/) dan
penomoran versi mengikuti [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

### Ditambahkan

- Berkas demo `database/demo/skema.sql` serta perintah `php artisan hub:demo`
  untuk membuat basis data contoh berisi satu akun per peran.
- Dokumentasi repositori: README, panduan kontribusi, kode etik, kebijakan
  keamanan, dan templat issue/pull request.
- Integrasi berkelanjutan: pemeriksaan sintaks PHP dan uji otomatis.

### Diperbaiki

- Akun vendor mitra kini mendarat di `/mitra` setelah masuk; sebelumnya diarahkan
  ke `/admin` yang menolaknya dengan 403.

### Keamanan

- Seluruh contoh kredensial dihapus dari dokumentasi kode; berkas `.env` dan
  basis data tidak diomit.

## [0.1.0] — 2026-10-25

Rilis awal untuk kolaborasi tim: modul operasional koperasi, kepegawaian,
keuangan, distribusi, dokumen, pelatihan, pesan, dan tata kelola dengan delapan
peran pengguna.

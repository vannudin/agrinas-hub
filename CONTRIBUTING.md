# Panduan Kontribusi

Terima kasih sudah ikut mengembangkan Agrinas Hub. Dokumen ini menjelaskan cara
menyiapkan lingkungan, aturan penulisan kode, dan alur pengiriman perubahan.

## 1. Menyiapkan lingkungan

```bash
git clone https://github.com/USERNAME/agrinas-hub.git
cd agrinas-hub
composer install
cp .env.example .env
php artisan key:generate
php artisan hub:demo
php artisan serve
```

Gunakan akun demo (lihat [README](README.md#akun-demo)) untuk masuk sesuai peran
yang sedang Anda kerjakan. Jangan memakai data produksi di lingkungan lokal.

## 2. Alur kerja cabang

| Awalan cabang | Dipakai untuk |
| --- | --- |
| `feat/` | Fitur baru |
| `fix/` | Perbaikan bug |
| `refactor/` | Perbaikan struktur tanpa mengubah perilaku |
| `docs/` | Dokumentasi saja |
| `chore/` | Perkakas, dependensi, konfigurasi |

Alur: buat cabang dari `main` → kerjakan → jalankan uji → buka pull request →
minimal satu peninjau menyetujui → *squash merge*.

## 3. Gaya kode

- Ikuti gaya bawaan Laravel: `PascalCase` untuk kelas, `camelCase` untuk metode,
  `snake_case` untuk kolom basis data.
- Satu controller satu modul; logika berat dipindahkan ke `app/Support/` atau
  `app/Services/`.
- Blade tidak memuat logika bisnis: siapkan data di controller.
- Komentar ditulis dalam bahasa Indonesia, menjelaskan **mengapa**, bukan
  mengulang **apa** yang sudah terbaca dari kode.
- Jangan menambah pustaka baru bila stdlib atau pustaka yang sudah terpasang
  cukup. Setiap dependensi baru harus dijelaskan alasannya di pull request.
- Jaga berkas tetap di bawah 500 baris; bila lebih, pecah berdasarkan tanggung
  jawab.

## 4. Basis data

- Perubahan struktur memakai migrasi baru di `database/migrations/`.
  Jangan menyunting migrasi yang sudah pernah dijalankan.
- Skema warisan tidak memaksakan *foreign key*; keutuhan relasi dijaga aplikasi.
  Saat menghapus data induk, pindahkan atau hapus dulu data anaknya.
- Jangan menaruh data pribadi di seeder maupun di `database/demo/skema.sql`.

## 5. Uji

```bash
php artisan test                       # seluruh uji
php artisan test --filter=SandiTest    # satu kelas uji
```

Aturan:

- Setiap perbaikan bug disertai uji yang **gagal sebelum** perbaikan dan **lulus
  sesudahnya**.
- Uji tidak boleh memanggil layanan luar sungguhan: pakai `Http::fake()`,
  `Storage::fake()`, dan basis data sementara.
- Uji tidak boleh bergantung pada tanggal hari ini, nomor urut id, atau teks
  halaman yang sering berubah — turunkan nilai dari data, bukan dari hafalan.

## 6. Pesan commit

Gunakan *Conventional Commits*:

```text
feat(toko): tambah harga grosir berjenjang
fix(masuk): bedakan email tidak terdaftar dari sandi salah
docs(readme): lengkapi langkah menjalankan lokal
```

Badan commit menjelaskan alasan perubahan dan dampaknya, bukan sekadar daftar
berkas yang diubah.

## 7. Pull request

Sertakan:

1. **Ringkasan** — apa yang berubah dan mengapa.
2. **Cara menguji** — perintah atau langkah yang sudah dijalankan beserta hasilnya.
3. **Dampak** — halaman, peran, atau data yang terpengaruh.
4. **Tangkapan layar** untuk perubahan tampilan (cukup satu, sebelum/sesudah).

Kriteria peninjauan: perubahan kecil dan terfokus, uji hijau, tidak ada rahasia di
dalam diff, dan tidak ada penurunan kualitas data.

## 8. Melaporkan bug

Buka issue dengan templat yang tersedia. Sertakan langkah reproduksi, hasil yang
diharapkan, hasil sebenarnya, peran akun, dan tangkapan layar bila ada.
Untuk kerentanan keamanan, ikuti [`SECURITY.md`](SECURITY.md) — jangan membuka
issue publik.

# Arsitektur

Dokumen ini menjelaskan susunan aplikasi dan alasan di balik beberapa keputusan
teknis yang sering ditanyakan saat pertama kali membaca kode.

## Lapisan aplikasi

```text
Permintaan HTTP
   │
   ├── routes/web.php ........ daftar rute + penjaga peran (middleware)
   │
   ├── Middleware ............ PeranAgrinas, PeranHrd, PeranManajer, PeranPic,
   │                           PeranBayar, PeranMateri, PeranPantau, PeranVendor
   │
   ├── Controller ............ satu controller per modul; menyiapkan data
   │
   ├── Support/ .............. aturan lintas modul (menu, wilayah, sandi,
   │                           pembatas login, ekspor)
   ├── Models/ ............... Eloquent + `Domain` (konstanta peran & label)
   └── resources/views ........ Blade per modul
```

Aturan yang dipegang: **satu definisi peran untuk tiga tempat**. `daftar_menu()`,
`boleh_rute()`, dan `rute_dasbor()` membaca middleware rute yang sama, sehingga
menu yang tampil tidak mungkin menunjuk halaman yang menolak pengguna.

## Basis data

- SQLite mode WAL, satu berkas (`HUB_DB`).
- Skema warisan: relasi tidak dipaksakan mesin basis data
  (`foreign_key_constraints => false`). Keutuhan data dijaga aplikasi; sebelum
  menghapus baris induk, pindahkan atau hapus dulu baris anaknya.
- Kolom waktu disimpan sebagai teks WIB apa adanya, sementara `app.timezone`
  bernilai UTC. Saat membandingkan waktu di kode, gunakan
  `Carbon::parse($nilai, 'Asia/Jakarta')` agar tidak terjadi selisih 7 jam.
- `database/demo/skema.sql` memuat struktur tanpa data pribadi dan dipakai
  perintah `hub:demo`.

## Keamanan berlapis

| Lapis | Berkas | Perilaku |
| --- | --- | --- |
| Kata sandi | `app/Support/ScryptHasher.php` | HMAC-SHA256 ber-kunci `APP_KEY`, lalu scrypt libsodium. Format `scrypt2$…`; hash lama `scrypt$…` tetap diperiksa dan dinaikkan saat login berhasil. |
| Data sensitif | `app/Models/User.php` | Tanggal lahir disimpan terenkripsi AES-256-GCM dengan kunci `APP_KEY`. |
| Batas percobaan | `app/Support/BatasLogin.php` | 5 kegagalan per email, kunci 15 menit, disimpan di cache. |
| Captcha | `app/Http/Controllers/MasukController.php` | Turnstile diperiksa sebelum kuota percobaan dipakai; host non-publik dilewati supaya pengembangan lokal tidak terhambat. |
| Otorisasi | `app/Http/Middleware/*` | Penjaga peran per rute; penyaringan data per wilayah/koperasi di controller. |

Catatan penting: mengganti `APP_KEY` membatalkan seluruh kata sandi dan kolom
terenkripsi, karena kunci lapis pertama diturunkan dari `APP_KEY`.

## Integrasi luar (opsional)

| Integrasi | Berkas | Dipakai untuk |
| --- | --- | --- |
| Cloudflare R2 | `config/filesystems.php` | Penyimpanan berkas unggahan. |
| OnlyOffice | `app/Support/OnlyOffice.php` | Editor dokumen, spreadsheet, presentasi. |
| Cloudflare Realtime SFU | `app/Support/SfuRapat.php` | Rapat video dan presentasi. |
| Gerbang WhatsApp | `app/Services/Whatsapp.php` | Pengiriman pesan keluar. |
| Asisten AI | `app/Services/AsistenAi.php` | Tanya-jawab dalam aplikasi. |
| SIMKOPDES / AgriConnect | `app/Services/`, `app/Support/AkunAgriConnect.php` | Sinkronisasi data eksternal. |

Semua integrasi membaca kredensial dari `.env`. Bila kuncinya kosong, fitur
terkait melapor belum dikonfigurasi dan bagian lain aplikasi tetap berjalan.

## Konvensi penamaan

- Tabel dan kolom: `snake_case`, bahasa Indonesia (`koperasi`, `pemetaan_lahan`,
  `dibuat`, `diperbarui`).
- Kelas: `PascalCase`; metode: `camelCase`.
- Peran disimpan sebagai teks huruf besar: `AGRINAS`, `HR`, `PIC`, `MANAJER`,
  `KEUANGAN`, `TNI`, `DINAS`, `VENDOR` — konstantanya ada di `app/Models/Domain.php`.

## Menambah modul baru

1. Tambahkan rute di `routes/web.php` beserta middleware penjaga peran.
2. Tambahkan controller di `app/Http/Controllers/`.
3. Tambahkan tampilan di `resources/views/<modul>/`.
4. Daftarkan menu di `daftar_menu()` (`app/Support/helpers.php`) dengan kunci ikon
   yang sudah ada.
5. Tambahkan uji di `tests/Feature/`.

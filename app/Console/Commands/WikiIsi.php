<?php

namespace App\Console\Commands;

use App\Models\Wiki;
use Illuminate\Console\Command;

/**
 * Isi awal Wiki: panduan mengoperasikan Agrinas Hub, diturunkan dari fitur yang
 * BENAR-BENAR ada di aplikasi ini (bukan teks contoh).
 *
 * Idempoten: halaman yang judulnya sudah ada DILEWATI, jadi aman dijalankan
 * berulang dan tidak menimpa tulisan yang sudah disunting orang.
 *
 *   php artisan wiki:isi
 *   php artisan wiki:isi --paksa     # timpa isi halaman bawaan (judul tetap)
 *
 * Hierarki: satu akar "Panduan Agrinas Hub", lalu satu halaman per modul.
 * Ditulis sebagai daftar datar ber-`induk` supaya urutan & kedalaman mudah
 * dibaca, lalu disusun dua lintasan (induk dulu, anak kemudian).
 */
class WikiIsi extends Command
{
    protected $signature = 'wiki:isi {--paksa : Timpa isi halaman bawaan yang sudah ada}';

    protected $description = 'Isi Wiki dengan panduan mengoperasikan Agrinas Hub (idempoten)';

    /** @var array<int, array{0:string,1:?string,2:string}> judul, induk, isi */
    private array $halaman = [
        ['Panduan Agrinas Hub', null, <<<'MD'
**Agrinas Hub** adalah satu tempat kerja untuk pengelola koperasi KDKMP: koperasi & gerai, barang, keuangan, distribusi, SDM, dan ruang kerja tim.

## Peran dan apa yang terbuka

| Peran | Fokus kerja |
|---|---|
| Admin Pusat (AGRINAS) | Semua koperasi: verifikasi, akun, katalog, pemetaan lahan, audit |
| Petugas HR | SDM & kepegawaian, pelatihan, materi, broadcast |
| PIC Wilayah | Koperasi di wilayahnya: distribusi, stok, pasokan, aspirasi manajer |
| Petugas Keuangan | Kas koperasi, pembayaran pasokan & pesanan toko, pengajuan biaya gerai |
| Manajer Koperasi | Koperasinya sendiri: penjualan, stok, tugas, pengajuan biaya |

Menu yang tampil selalu sama dengan hak akses rute — tidak ada menu yang membuka 403.

## Cara membaca panduan ini

- **Ruang Kerja** — modul kerja tim (pesan, dokumen, catatan, kalender, tugas).
- **Koperasi & Gerai** — pekerjaan harian: katalog, kasir, stok, distribusi.
- **Keuangan** — buku kas dan persetujuan biaya operasional.
- **Pengelolaan** — akun, wilayah, pemetaan, SDM, pelatihan, konferensi.

Setiap halaman ditulis dengan pola sama: **fungsi → siapa yang memakai → langkah → hal yang mudah salah**.
MD],
        ['Mulai Cepat: Masuk, Peran, dan Menu', 'Panduan Agrinas Hub', <<<'MD'
## Masuk

1. Buka halaman **Masuk**, isi email dan sandi.
2. Sandi awal diberikan pengelola. Bila akun baru dibuat dari data SIMKOPDES, gunakan sandi SIMKOPDES Anda.
3. Setelah masuk, akun manajer **langsung tertaut** ke koperasinya — tidak perlu menautkan manual.

**Sandi salah 5 kali dalam 15 menit → akun terkunci sementara.** Tunggu 15 menit atau minta admin membersihkan penghitung (`cache:clear`).

## Berpindah modul

- **Sidebar (desktop)** dan **bilah bawah (ponsel)**: Dashboard, Asisten AI, Message, Semua Menu.
- **Semua Menu** memuat seluruh menu peran Anda dalam bentuk ikon.
- **Ruang Kerja** memuat modul kerja tim — Wiki dan Approval ada di sini, bukan di sidebar.

## Ganti sandi

Menu **Profil** → bagian sandi. Isi sandi lama, sandi baru, lalu simpan. Profil juga menampilkan data yang ditarik dari SIMKOPDES.
MD],
        ['Ruang Kerja: Ringkasan Modul', null, <<<'MD'
**Ruang Kerja** (`/workspace`) adalah pusat modul kerja tim. Isinya kartu per modul; klik kartu untuk masuk.

| Modul | Untuk apa |
|---|---|
| Wiki | Tutorial & pengetahuan: cara mengoperasikan sistem, fungsi, dan SOP |
| Approval | Pengajuan biaya operasional gerai ke admin/keuangan |
| Messenger | Obrolan tim, lampiran, pesan suara, terjemahan |
| Docs & Sheets | Dokumen & lembar sebar kolaboratif (ONLYOFFICE) |
| Catatan Harian | Catatan kerja harian manajer; admin & PIC memantau |
| Calendar | Jadwal rapat, tenggat tugas, mulai panggilan video |
| Tasks | Pekerjaan lintas peran, dipecah jadi langkah |
| PhotoCraft | Editor gambar (buka PSD, sunting lapisan, ekspor PNG/JPG) |
| OpenCut | Editor video (susun klip, potong, efek, ekspor MP4) |

**Lingkup data mengikuti peran:** manajer hanya koperasinya, PIC hanya wilayahnya, admin pusat melihat semua. Ini berlaku di semua modul, bukan hanya daftar koperasi.
MD],
        ['Messenger: Obrolan Tim', 'Ruang Kerja: Ringkasan Modul', <<<'MD'
## Fungsi
Pusat komunikasi tim: obrolan grup, percakapan pribadi, lampiran berkas, pesan suara, dan terjemahan pesan.

## Langkah
1. Buka **Message** dari sidebar atau bilah bawah.
2. **Obrolan baru**: tombol tambah → pilih rekan (atau tulis nama) → kirim pesan pertama.
3. **Lampiran**: ikon klip di kotak tulis. **Pesan suara**: ikon mikrofon.
4. **Terjemahan**: pada pesan, pilih terjemahkan — bahasa sasaran ada di pengaturan halaman.
5. **Sematkan** pesan penting supaya tetap di atas.

## Hal yang mudah salah
- Lencana angka di menu **Message** = jumlah pesan belum dibaca; membuka ruang akan mengosongkannya.
- Tugas dan dokumen punya ruang obrolannya sendiri — dibuat otomatis saat tugas/dokumen dibuat, jangan dibuat manual.
MD],
        ['Docs & Sheets: Dokumen Kolaboratif', 'Ruang Kerja: Ringkasan Modul', <<<'MD'
## Fungsi
Dokumen dan lembar sebar kolaboratif berbasis ONLYOFFICE. Berkas tersimpan di Hub, penyuntingan berjalan di peramban.

## Langkah
1. **Docs & Sheets** → **Dokumen Baru** → pilih jenis (dokumen / lembar sebar / presentasi).
2. Klik dokumen untuk membuka penyunting di tab baru.
3. **Bagikan**: minta akses / beri akses ke rekan. Tanpa akses, dokumen tidak muncul di daftar mereka.
4. **Unggah** berkas yang sudah ada (docx/xlsx/pptx) lewat tombol unggah.

## Hal yang mudah salah
- Dokumen yang sedang disunting orang lain bisa terkunci sementara — tunggu, jangan memaksa tutup.
- Perubahan tersimpan otomatis oleh penyunting; tombol simpan pada halaman Hub hanya untuk metadata.
MD],
        ['Catatan Harian', 'Ruang Kerja: Ringkasan Modul', <<<'MD'
## Fungsi
Catatan kerja harian: rencana, kendala, hasil. Manajer mengisi; admin pusat dan PIC memantau untuk melihat kondisi lapangan.

## Langkah
1. Pilih tanggal, pilih jenis (rencana / kendala / hasil).
2. Tulis judul dan isi, lalu simpan.
3. Catatan lama bisa dihapus bila salah tanggal.

## Hal yang mudah salah
- Satu catatan = satu tanggal + satu jenis; jangan menumpuk semua hal dalam satu catatan panjang.
- Catatan manajer hanya terlihat oleh manajer itu dan pengelolanya.
MD],
        ['Calendar: Jadwal & Rapat', 'Ruang Kerja: Ringkasan Modul', <<<'MD'
## Fungsi
Jadwal rapat dan tenggat tugas dalam satu kalender. Dari acara rapat Anda bisa langsung memulai panggilan video.

## Langkah
1. **Calendar** → tambah acara: judul, waktu mulai, jenis.
2. Buka acara → **Mulai panggilan** untuk rapat video (halaman Konferensi).
3. Tenggat tugas otomatis muncul sebagai penanda pada tanggalnya.

## Hal yang mudah salah
- Waktu ditampilkan dalam WIB. Acara yang sedang berlangsung ditandai "berlangsung", bukan "akan datang".
- Manajer tidak menjadwalkan rapat pusat; jadwal rapat lintas wilayah dibuat admin/PIC.
MD],
        ['Tasks: Pekerjaan Lintas Peran', 'Ruang Kerja: Ringkasan Modul', <<<'MD'
## Fungsi
Memecah pekerjaan besar menjadi langkah kecil dengan pelaksana, tenggat, dan progres. Berbeda dari **Tugas Saya** di AgriConnect yang hanya milik sendiri.

## Langkah
1. **Tasks** → **Tugas Baru**: judul, koperasi, pelaksana, tenggat.
2. Buka tugas → tambah **langkah** (checklist) dan ubah status.
3. Pelaksana mengirim **progres**: catatan + lampiran bukti.
4. Pengelola memberi **keterangan**: instruksi, alasan revisi, atau penolakan.

## Alur status
`DIAJUKAN → DIKERJAKAN → SELESAI`, dengan `REVISI` bila perlu perbaikan.

## Hal yang mudah salah
- Status bukan hiasan: verifikasi hanya bisa dilakukan pada tugas yang sudah diajukan.
- Lampiran progres disimpan per kiriman; kiriman lama tidak tertimpa.
MD],
        ['Wiki: Cara Mengisi Pengetahuan', 'Ruang Kerja: Ringkasan Modul', <<<'MD'
## Fungsi
Menyimpan pengetahuan yang dipakai berulang: SOP, panduan fitur, jawaban atas pertanyaan yang sering muncul. Hierarki lewat **halaman induk**.

## Langkah
1. **Wiki** → **Halaman Baru**: judul, halaman induk (opsional), koperasi (kosongkan untuk berlaku umum).
2. Tulis isi dengan **Markdown**: `**tebal**`, `- daftar`, `## judul bagian`, `| tabel |`.
3. Simpan → halaman terbuka. Sunting kapan saja lewat bagian **Sunting Halaman**.

## Aturan penulisan
- Satu halaman = satu topik. Judul yang jelas lebih berguna daripada halaman raksasa.
- Tulis langkah bernomor untuk prosedur, tabel untuk perbandingan.
- HTML mentah dibuang (keamanan), jadi jangan menempel skrip.

## Hal yang mudah salah
- Menyimpan halaman **tanpa memilih induk** tidak akan melepas induknya — induk hanya berubah bila kolomnya diisi.
- Memilih keturunan sebagai induk ditolak (lingkaran hierarki).
MD],
        ['Approval: Pengajuan Biaya Operasional Gerai', 'Ruang Kerja: Ringkasan Modul', <<<'MD'
## Fungsi
Mengajukan biaya operasional gerai koperasi ke **admin pusat / petugas keuangan**: listrik, air, telepon & internet, service AC, service kendaraan, bahan bakar, perjalanan dinas, rembes pembelian, sewa tempat, kebersihan & keamanan, alat tulis, dan lainnya.

## Siapa yang mengajukan dan memutuskan
- **Mengajukan:** manajer koperasi (gerainya), atau pengelola yang mencatatkan atas nama koperasi.
- **Memutuskan:** admin pusat, petugas keuangan, dan PIC untuk koperasi di wilayahnya.
- **Manajer tidak bisa memutuskan** pengajuannya sendiri.

## Langkah mengajukan
1. Buka **Approval** → **Ajukan Biaya**.
2. Isi **keperluan** (contoh: "Bayar tagihan listrik gerai Oktober"), **kategori biaya**, dan **nominal** dalam rupiah.
3. Isi **tanggal dibutuhkan** bila ada tenggat pembayaran, dan **uraian** berisi rincian serta alasan.
4. Pilih koperasi (manajer otomatis koperasinya). Kirim → status **Menunggu**.

## Langkah memutuskan
1. Pada baris pengajuan, tekan **Putuskan**.
2. Tulis catatan keputusan (alasan, arahan), lalu **Setujui** atau **Tolak**.
3. Status berubah menjadi Disetujui / Ditolak, dan nama pemutus beserta waktunya tercatat.

## Hal yang mudah salah
- **Nominal wajib** dan minimal Rp 1 — pengajuan tanpa nominal tidak bisa diputuskan dengan benar.
- Kategori harus dari daftar; kategori baru ditambahkan lewat `App\Models\Persetujuan::BIAYA` agar pilihan formulir dan saringan tetap sama.
- Angka **"Disetujui"** di ringkasan = total nominal yang sudah disetujui dalam lingkup Anda, bukan jumlah pengajuan.
MD],
        ['PhotoCraft: Editor Gambar', 'Ruang Kerja: Ringkasan Modul', <<<'MD'
## Fungsi
Editor gambar seperti aplikasi desktop, berjalan di peramban: buka PSD/PSB, sunting lapisan, ekspor PNG/JPG.

## Langkah
1. **PhotoCraft** → buka berkas atau buat dokumen baru (ukuran & resolusi).
2. Panel lapisan di kanan: tambah, sembunyikan, ubah urutan.
3. **Ekspor** → pilih PNG atau JPG.

## Hal yang mudah salah
- Editor berjalan di kontainer terpisah; berkas besar butuh waktu memuat. Tunggu sampai kanvas tampil sebelum menyunting.
- Simpan dokumen sebelum menutup tab — perubahan yang belum disimpan hilang.
MD],
        ['OpenCut: Editor Video', 'Ruang Kerja: Ringkasan Modul', <<<'MD'
## Fungsi
Menyusun klip video: potong, urutkan, beri efek, lalu ekspor MP4. Berjalan di peramban.

## Langkah
1. **OpenCut** → buat proyek baru, pilih rasio (mis. 16:9 atau 9:16).
2. Impor klip, susun di garis waktu, potong bagian yang tidak perlu.
3. Tambah teks/efek, lalu **Ekspor MP4**.

## Hal yang mudah salah
- Klip besar memperlambat pratinjau; turunkan resolusi pratinjau saat menyunting, naikkan lagi saat ekspor.
- Ekspor berjalan di server — jangan tutup tab sebelum proses selesai.
MD],
        ['Koperasi & Gerai', null, <<<'MD'
Satu koperasi KDKMP dikelola lewat beberapa halaman. Urutan kerjanya:

1. **Koperasi** (`Koperasi` di menu) — data, status operasional, sarana prasarana, kasir POS.
2. **Toko Barang** — katalog belanja koperasi: barang, harga grosir, keranjang, invoice.
3. **Stok** — persediaan barang koperasi (termasuk impor/ekspor CSV).
4. **Distribusi** — pengajuan & pengiriman barang dari gudang Agrinas ke koperasi.
5. **Keuangan** — catatan kas harian, pembayaran, dan rekap.

Manajer melihat koperasinya sendiri; pengelola melihat koperasi dalam lingkupnya.
MD],
        ['Koperasi: Data, Sarana, dan POS', 'Koperasi & Gerai', <<<'MD'
## Fungsi
Satu halaman untuk kondisi koperasi: identitas & legalitas, status operasional (buka/tutup gerai), sarana prasarana, daftar lahan, dan **kasir POS** penjualan harian.

## Langkah
1. **Koperasi** → pilih koperasi (manajer langsung ke koperasinya).
2. **Status operasional**: ubah status gerai dan simpan — perubahan tercatat.
3. **Sarana**: tambah sarana (nama, jumlah, kondisi). Gunakan tombol tambah di kanan atas tabel.
4. **Kasir POS**: pilih barang, masukkan jumlah, proses pembayaran → nota tercatat sebagai penjualan hari itu.

## Hal yang mudah salah
- Harga di POS mengikuti jenjang grosir yang berlaku: sistem memilih ambang terbesar yang terpenuhi, bukan harga termurah.
- Harga ditulis untuk **satu kemasan penuh** (SAK, TON) — jangan menulis harga per kilogram bila satuannya SAK.
- Menghapus koperasi tidak menghapus data anaknya secara otomatis; pastikan data terkait sudah dibereskan.
MD],
        ['Toko Barang: Katalog dan Pesanan', 'Koperasi & Gerai', <<<'MD'
## Fungsi
Katalog barang yang bisa dibeli koperasi dari Agrinas, plus pemantauan pesanan.

## Langkah (manajer — pembeli)
1. **Toko Barang** → telusuri etalase (gulir berkelanjutan, bukan halaman).
2. Masukkan barang ke keranjang, atur jumlah (dalam kemasan: SAK/TON/PCS).
3. Periksa rincian keranjang — harga otomatis memakai jenjang grosir yang berlaku.
4. Buat pesanan → invoice muncul; pembayaran dicatat oleh petugas keuangan.

## Langkah (pengelola — penjual)
1. **Kelola Katalog** → tambah/ubah barang: nama, satuan, harga, jenjang harga grosir, stok.
2. **Pesanan Toko** → pantau pesanan masuk, ubah status, terbitkan surat jalan.
3. Tandai **dibayar** setelah dana diterima (hanya pusat & keuangan).

## Hal yang mudah salah
- Harga grosir disimpan per jenjang ambang; jangan mengisi satu harga untuk semua jumlah.
- Pesanan yang sudah diproses tidak boleh diubah harganya — buat pesanan baru bila salah.
MD],
        ['Stok Koperasi & Stok Gudang', 'Koperasi & Gerai', <<<'MD'
## Fungsi
Dua stok berbeda dan jangan dicampur:
- **Stok Gudang** — persediaan milik Agrinas (pusat), dipakai untuk distribusi.
- **Stok Koperasi** — inventaris milik koperasi sendiri.

## Langkah
1. **Stok Gudang** (admin): tambah barang, ubah jumlah, lihat riwayat keluar-masuk.
2. **Stok Koperasi** (manajer): tambah barang, perbarui stok harian.
3. **Impor/ekspor CSV**: unduh template → isi → unggah. Template sengaja bisa diunduh siapa pun yang boleh membuka halamannya.

## Hal yang mudah salah
- Impor CSV menimpa baris dengan kode barang yang sama — periksa template sebelum mengunggah.
- Stok koperasi hanya bisa diubah manajer koperasi tersebut; pengelola hanya melihat.
MD],
        ['Distribusi: Pengajuan dan Pengiriman', 'Koperasi & Gerai', <<<'MD'
## Fungsi
Mengajukan barang dari gudang Agrinas ke koperasi, lalu memantau pengirimannya.

## Langkah
1. **Distribusi** → **Pengajuan Baru**: pilih barang dan jumlah.
2. Kirim pengajuan → status **Diajukan**.
3. Pengelola menyetujui (**Disetujui**, hijau) atau menolak (**Ditolak**) dengan alasan.
4. Barang dikirim; stok gudang berkurang, stok koperasi bertambah.

## Hal yang mudah salah
- Hanya ada tiga status: Diajukan / Disetujui / Ditolak — tidak ada "Diterima"; pasokan berhenti di keputusan pengelola.
- Jumlah diajukan tidak boleh melebihi stok gudang yang tersedia saat itu.
MD],
        ['Produk Lokal & UMKM: Pasokan', 'Koperasi & Gerai', <<<'MD'
## Fungsi
Mencatat pasokan produk lokal/UMKM ke koperasi, termasuk harga dan pembayarannya.

## Langkah
1. **Produk Lokal & UMKM** → tambah pasokan: pemasok, barang, jumlah, harga.
2. Ajukan → pengelola memutuskan (Disetujui / Ditolak).
3. Setelah disetujui, petugas keuangan menandai pembayaran dan mengunggah bukti.

## Hal yang mudah salah
- Bukti pembayaran wajib diunggah agar status pembayaran sah.
- Baris berstatus `sumber=SIMP` adalah catatan resmi dari SIMKOPDES — jangan ditimpa dengan catatan lokal.
MD],
        ['Keuangan: Buku Kas dan Rekap', 'Koperasi & Gerai', <<<'MD'
## Fungsi
Mencatat uang masuk dan keluar koperasi per hari, lalu merekapnya per periode. Juga menampung pembayaran pesanan toko dan pasokan UMKM.

## Langkah
1. **Keuangan** → pilih koperasi (pengelola) atau langsung ke kas koperasi sendiri (manajer).
2. **Catatan harian**: tanggal, pemasukan, pengeluaran, keterangan → simpan. Satu catatan per hari per koperasi.
3. Ubah **periode** (harian/bulanan/tahunan) untuk melihat rekap; **Buku Kas** bisa disaring dan dicari.
4. **Unduh CSV** untuk pembukuan di luar aplikasi.

## Hal yang mudah salah
- Mode bawaan pengelola adalah **gabungan seluruh koperasi**; pilih satu koperasi untuk mencatat.
- Rekap dihitung dari catatan harian — perbaiki catatannya, bukan angka rekapnya.
- Biaya operasional gerai **tidak** dicatat di sini sebelum disetujui; ajukan dulu lewat **Approval**.
MD],
        ['Pengelolaan: Akun, Wilayah, dan Pemetaan', null, <<<'MD'
Halaman pengelolaan dipakai admin pusat (dan sebagian oleh HR/PIC sesuai hak aksesnya).

| Halaman | Fungsi |
|---|---|
| Akun | Satu tabel semua pengguna, satu formulir tambah akun dengan pilihan peran |
| Koperasi | Halaman kerja satu koperasi (data, sarana, POS) — tombol Buka di daftar membukanya di tab baru. Daftar & data lahan ada di tautan "Daftar Koperasi & Lahan" di halaman itu |
| Pemetaan Lahan | Data lahan KDKMP: desa, luas, potensi, Babinsa |
| SDM & Kepegawaian | Data pegawai, profil, kelengkapan dokumen |
| Pelatihan & Materi | Materi belajar dan progres penyelesaian |
| Broadcast | Pengumuman ke kelompok pengguna |
| Audit | Jejak tindakan penting di sistem |
| Konferensi | Rapat video (semua peran) |
| AgriConnect | Data kepegawaian & absensi dari sistem HRIS |
MD],
        ['Akun: Menambah dan Mengatur Pengguna', 'Pengelolaan: Akun, Wilayah, dan Pemetaan', <<<'MD'
## Fungsi
Satu halaman untuk semua pengguna: daftar, saring, dan tambah akun dengan memilih peran.

## Langkah
1. **Akun** → tombol **Tambah** (kanan atas tabel).
2. Isi nama, email, peran (Admin Pusat / PIC / HR / Keuangan / Manajer), dan wilayah bila perlu.
3. Simpan → akun langsung bisa dipakai masuk.
4. **Saring** daftar dengan kotak cari, pilihan peran, dan status.

## Hal yang mudah salah
- Peran menentukan menu dan hak akses — salah memilih peran membuat orang melihat halaman yang bukan pekerjaannya.
- Nonaktifkan akun lewat status, jangan menghapus, agar jejak auditnya tetap terbaca.
- Akun manajer yang dibuat dari data SIMKOPDES otomatis tertaut ke koperasinya saat login pertama.
MD],
        ['Pemetaan Lahan: Data Lahan KDKMP', 'Pengelolaan: Akun, Wilayah, dan Pemetaan', <<<'MD'
## Fungsi
Data lahan tingkat desa: luas, status, potensi, koordinat, serta nama & nomor HP Babinsa. Dipakai untuk verifikasi aset dan penelusuran lapangan.

## Langkah
1. **Pemetaan Lahan** → cari desa/kecamatan/koperasi.
2. Saring menurut provinsi, status, atau ketersediaan koordinat.
3. Klik baris untuk melihat rincian; koordinat bisa dibuka ke peta.

## Hal yang mudah salah
- Sumber kebenaran data adalah API pemetaan lahan; penyaringan lokal hanya menampilkan, bukan mengubah sumber.
- Data tanpa koordinat tetap sah — jangan dianggap rusak.
MD],
        ['SDM & Kepegawaian', 'Pengelolaan: Akun, Wilayah, dan Pemetaan', <<<'MD'
## Fungsi
Meja SDM: data pegawai, kelengkapan profil, dan dokumen kepegawaian. Sebagian data ditarik dari AgriConnect HRIS.

## Langkah
1. **SDM & Kepegawaian** → cari pegawai (nama/email), saring yang profilnya belum lengkap.
2. Buka pegawai → lengkapi data yang kosong, unggah dokumen.
3. **Payroll CSV** untuk rekap yang diolah di luar aplikasi.

## Hal yang mudah salah
- Data yang berasal dari HRIS bersifat cermin — perbaiki di sumbernya bila berbeda.
- HR tidak membuka kendali pusat (akun, broadcast, penjadwalan rapat); itu milik admin.
MD],
        ['Pelatihan & Materi', 'Pengelolaan: Akun, Wilayah, dan Pemetaan', <<<'MD'
## Fungsi
Materi belajar untuk manajer dan pengelola: bahan bacaan, modul, dan progres penyelesaian.

## Langkah
1. **Materi** → baca materi yang tersedia (Markdown, tabel, gambar).
2. **Pelatihan** → buka modul, tandai selesai; bilah progres menunjukkan capaian.
3. Pengelola menambah materi/modul lewat halaman admin.

## Hal yang mudah salah
- Progres dihitung dari modul yang ditandai selesai; menandai tanpa membaca tetap tercatat — gunakan dengan jujur.
MD],
        ['Konferensi: Rapat Video', 'Pengelolaan: Akun, Wilayah, dan Pemetaan', <<<'MD'
## Fungsi
Rapat video di dalam Hub (Cloudflare Realtime) — tanpa aplikasi tambahan. Semua peran bisa ikut.

## Langkah
1. **Konferensi** → pilih rapat atau buat rapat baru (admin/PIC).
2. Masuk ke ruang: izinkan kamera & mikrofon saat diminta.
3. **Presentasi**: tukar jalur kamera dengan berkas atau layar — peserta melihat berkas, bukan wajah.
4. Akhiri rapat; catatan rapat bisa disimpan sebagai dokumen.

## Hal yang mudah salah
- Kamera **tidak bisa** aktif pada alamat `http://` di jaringan lokal — wajib `https://`.
- Jangan menutup tab tanpa keluar: sesi hantu membuat peserta lain gagal bergabung. Sistem membersihkan sesi hantu otomatis dalam ~45 detik.
MD],
        ['AgriConnect: Absensi dan Data Pegawai', 'Pengelolaan: Akun, Wilayah, dan Pemetaan', <<<'MD'
## Fungsi
Pintu ke AgriConnect HRIS: absensi (check-in/check-out), cuti, data keluarga, BPJS, performa, perjalanan dinas.

## Langkah
1. **AgriConnect** → pilih seksi (Absensi, Cuti, Keluarga, BPJS, Performa).
2. **Absensi**: check-in saat mulai kerja, check-out saat pulang. Lokasi diambil dari perangkat — pastikan GPS aktif dan Anda berada di lokasi tugas.
3. Riwayat absensi menampilkan waktu, koordinat, dan status persetujuan.

## Hal yang mudah salah
- Check-out **menimpa** catatan hari yang sama, bukan membuat baris baru — jangan check-out dua kali di hari berbeda tanpa check-in baru.
- Absensi hanya bisa lewat aplikasi; tidak ada jalur ubah/hapus setelah tercatat.
- Keterangan absen ditulis apa adanya; jangan mengarang alasan.
MD],
    ];

    public function handle(): int
    {
        $paksa = (bool) $this->option('paksa');
        $dibuat = 0;
        $dilewati = 0;
        $id = [];

        foreach ($this->halaman as [$judul, $induk, $isi]) {
            $ada = Wiki::where('judul', $judul)->whereNull('koperasi_id')->first();
            if ($ada && ! $paksa) {
                $id[$judul] = $ada->id;
                $dilewati++;

                continue;
            }
            if ($ada) {
                $ada->forceFill(['isi' => $isi, 'diperbarui' => kini()])->save();
                $id[$judul] = $ada->id;
                $dibuat++;

                continue;
            }

            $w = Wiki::create([
                'induk_id' => $induk !== null ? ($id[$induk] ?? null) : null,
                'koperasi_id' => null, 'judul' => $judul, 'isi' => $isi,
                'dibuat_oleh' => null, 'dibuat' => kini(), 'diperbarui' => kini(),
            ]);
            $id[$judul] = $w->id;
            $dibuat++;
        }

        $this->info("Wiki: {$dibuat} halaman ditulis, {$dilewati} dilewati (sudah ada).");
        $this->line('Total halaman wiki: '.Wiki::count());

        return self::SUCCESS;
    }
}

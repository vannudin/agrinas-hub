@extends('layouts.utama')
@section('kelas_tubuh', 'beranda-tubuh')
@section('judul', 'Beranda')
@section('isi')
<div class="situs">

  <header class="pub-bar">
    <a class="pub-merek" href="/" aria-label="PT Agrinas Pangan Nusantara (Persero) — beranda">
      {{-- Dua logo ditampilkan terpisah seperti agrinaspalma.co.id: Danantara, lalu Agrinas Pangan. --}}
      <img class="merek-danantara" src="/img/logo-danantara-kecil.png" alt="Danantara Indonesia"
           width="420" height="105">
      <span class="merek-pemisah" aria-hidden="true"></span>
      <img class="merek-agrinas" src="/img/logo-agrinas-kecil.png" alt="Agrinas Pangan Nusantara"
           width="160" height="76">
    </a>
    <nav class="pub-nav" aria-label="Navigasi halaman">
      <a href="#tentang">Tentang</a>
      <a href="#mandat">Mandat</a>
      <a href="#lini-bisnis">Lini Bisnis</a>
      <a href="#layanan">Agrinas Hub</a>
      <a href="#direksi">Direksi</a>
      <a href="#media">Media</a>
      <a href="#kontak">Kontak</a>
    </nav>
    {{-- Di bilah desktop tidak pernah ada tautan masuk; hanya menu ☰ (<=1060px)
         yang punya .masuk-menu. Tombol ini yang menutup celah itu. --}}
    <a class="tombol utama masuk-bar" href="{{ route('masuk') }}">Masuk</a>
    {{-- Di layar sempit menu tetap ada, dibuka tanpa JavaScript pakai <details>. --}}
    <details class="pub-menu">
      <summary aria-label="Buka menu navigasi"><span aria-hidden="true">☰</span> Menu</summary>
      <nav aria-label="Navigasi halaman (mobile)">
        <a href="#tentang">Tentang</a>
        <a href="#mandat">Mandat KDKMP</a>
        <a href="#lini-bisnis">Lini Bisnis</a>
        <a href="#layanan">Agrinas Hub</a>
        <a href="#direksi">Jajaran Direksi</a>
        <a href="#media">Pusat Informasi</a>
        <a href="#kontak">Kontak</a>
        <a class="masuk-menu" href="{{ route('masuk') }}">Masuk ke akun</a>
      </nav>
    </details>
  </header>

  <section class="pub-hero">
    {{-- Hero = elemen LCP. Dulu SELALU mengirim 1440px (444 KB) walau di ponsel
         412px hanya butuh ±480px. srcset membuat peramban memilih sendiri;
         `fetchpriority="high"` menandai bahwa gambar inilah yang paling
         menentukan waktu tampil. `sizes="100vw"` benar karena hero selebar layar.
         Gambar asli TIDAK diubah — ia tetap jadi pilihan terbesar. --}}
    <img class="pub-hero-gambar"
         src="/img/hero-panen.jpg"
         srcset="/img/hero-panen-480.jpg 480w,
                 /img/hero-panen-800.jpg 800w,
                 /img/hero-panen-1200.jpg 1200w,
                 /img/hero-panen.jpg 1440w"
         sizes="100vw"
         fetchpriority="high" decoding="async"
         alt="Petani memanen padi di sawah Indonesia" width="1440" height="913">
    <div class="pub-hero-isi">
      <p class="pub-hero-merek">PT Agrinas Pangan Nusantara (Persero)</p>
      <h1>Petani Sejahtera, Negara Berdaulat</h1>
      <p>Badan usaha milik negara di sektor pertanian dan konsultansi konstruksi. Kami
        membangun kemandirian pangan desa melalui lini bisnis sarana produksi, logistik,
        dan pendampingan usaha tani.</p>
      <div class="tombol-baris">
        <a class="tombol utama" href="#tentang">Tentang perusahaan</a>
        <a class="tombol samar" href="#lini-bisnis">Lini bisnis</a>
      </div>
    </div>
  </section>

  <section class="pub-bagian" id="tentang">
    <div class="pub-kepala-bagian">
      <p class="pub-eyebrow">Tentang kami</p>
      <h2>Perusahaan negara yang tumbuh dari tradisi konsultansi konstruksi</h2>
      <p>Badan usaha milik negara yang kini bergerak di bidang pertanian dan konsultansi
        konstruksi, dengan mayoritas saham dipegang negara melalui Danantara.</p>
    </div>

    <div class="pub-dua">
      <div class="pub-cerita">
        <h3>Jejak perusahaan</h3>
        <ol class="pub-lini">
          <li><b>1948</b><span>Berawal sebagai NV Architectenbureau Job en Sprey di Jl. Cikini Raya No. 1, Jakarta Pusat, melayani jasa perencanaan dan pengawasan gedung.</span></li>
          <li><b>1961</b><span>Dinasionalisasi, lalu ditetapkan menjadi perusahaan negara dengan nama PN Yodya Karya.</span></li>
          <li><b>1972</b><span>Status berubah menjadi persero, kantor pusat pindah ke Jl. D.I. Panjaitan Kav. 8, Cawang, Jakarta Timur. Layanan meluas ke survei, manajemen konstruksi, dan konsultansi non-konstruksi.</span></li>
          <li><b>2025</b><span>Nama berubah menjadi PT Agrinas Pangan Nusantara (Persero) dan bidang usaha diperluas ke pertanian.</span></li>
        </ol>
      </div>

      <aside class="pub-kartu pub-identitas">
        <h3>Identitas perusahaan</h3>
        <dl>
          <dt>Bentuk badan</dt><dd>BUMN &mdash; Persero</dd>
          <dt>Bidang usaha</dt><dd>Pertanian dan konsultansi konstruksi</dd>
          <dt>Pemegang saham</dt><dd>Negara Republik Indonesia melalui Danantara</dd>
          <dt>Kantor pusat</dt><dd>Jl. D. I. Panjaitan Kav. 8, Cawang, Jakarta Timur 13340</dd>
          <dt>Telepon</dt><dd>(021) 8193951, 8194038</dd>
          <dt>Faksimili</dt><dd>(021) 8193519</dd>
          <dt>Sertifikasi</dt><dd>ISO 9001:2015 &middot; ISO 14001:2015 &middot; ISO 37001:2016 &middot; ISO 45001:2018</dd>
        </dl>
      </aside>
    </div>
  </section>

  <section class="pub-bagian pub-latar-lembut" id="lini-bisnis">
    <div class="pub-kepala-bagian">
      <p class="pub-eyebrow">Lini bisnis</p>
      <h2>Dari konsultansi konstruksi ke rantai pangan desa</h2>
      <p>Bidang usaha perusahaan mencakup pertanian, perdagangan sarana produksi,
        distribusi pangan, dan konsultansi teknik &mdash; dikerjakan bersama koperasi desa.</p>
    </div>
    <div class="pub-kartu-grid">
      <article class="pub-kartu">
        <h3>Sarana produksi &amp; pangan</h3>
        <p>Penyaluran pupuk, benih, beras program Stabilisasi Pasokan dan Harga Pangan,
          bantuan pangan pemerintah, serta LPG 3 kilogram lewat jaringan koperasi.</p>
      </article>
      <article class="pub-kartu">
        <h3>Logistik &amp; distribusi desa</h3>
        <p>Menyiapkan sarana angkut dan jaringan distribusi agar barang sampai ke desa
          dengan biaya wajar dan pasokan yang terukur.</p>
      </article>
      <article class="pub-kartu">
        <h3>Konsultansi &amp; rekayasa</h3>
        <p>Jasa perencanaan, pengawasan, dan manajemen konstruksi &mdash; keahlian lama
          perusahaan, kini disertifikasi ISO 9001, 14001, 37001, dan 45001.</p>
      </article>
      <article class="pub-kartu">
        <h3>Layanan keuangan mitra</h3>
        <p>Menghubungkan warga desa dengan layanan keuangan tanpa kantor cabang dan
          pembiayaan usaha keluarga melalui mitra seperti BRILink dan PNM Mekaar.</p>
      </article>
      <article class="pub-kartu">
        <h3>Pendampingan &amp; pelatihan</h3>
        <p>Penguatan kapasitas pengurus dan pengelola usaha desa: pencatatan, tata kelola,
          pemasaran hasil tani, dan pemanfaatan sistem kerja Agrinas Hub.</p>
      </article>
      <article class="pub-kartu">
        <h3>Agrinas Hub</h3>
        <p>Ruang kerja digital perusahaan: menerbitkan tugas, mengirim berkas, dan
          memverifikasi hasil kerja mitra binaan dengan jejak audit yang utuh.</p>
      </article>
    </div>
  </section>

  <section class="pub-bagian pub-gelap" id="mandat">
    <div class="pub-kepala-bagian">
      <p class="pub-eyebrow">Mandat negara</p>
      <h2>Menjalankan penugasan di sektor pangan</h2>
      <p>PT Agrinas Pangan Nusantara mendapat penugasan dari Presiden Republik Indonesia
        untuk membina dan mengoperasikan Koperasi Desa/Kelurahan Merah Putih &mdash; salah
        satu program yang kami jalankan. Tata kelolanya diatur melalui Peraturan Presiden
        Nomor 82 Tahun 2026.</p>
    </div>

    <div class="pub-dua">
      <div>
        <h3>Barang yang disalurkan</h3>
        <ul class="pub-daftar">
          <li>Pupuk untuk kebutuhan pertanian desa</li>
          <li>Beras program Stabilisasi Pasokan dan Harga Pangan (SPHP)</li>
          <li>Bantuan pangan pemerintah</li>
          <li>LPG 3 kilogram</li>
        </ul>
      </div>
      <div>
        <h3>Layanan bagi warga</h3>
        <ul class="pub-daftar">
          <li>BRILink &mdash; layanan keuangan tanpa kantor cabang</li>
          <li>PNM Mekaar &mdash; pembiayaan Membina Ekonomi Keluarga Sejahtera</li>
          <li>Pembayaran tagihan listrik</li>
          <li>Koperasi beroperasi setiap hari dengan pembagian shift pengurus</li>
        </ul>
      </div>
    </div>

    <figure class="pub-kutipan">
      <blockquote>
        KDKMP disiapkan menjadi salah satu saluran penyaluran pupuk, beras SPHP, bantuan
        pangan, serta LPG 3 kilogram.
      </blockquote>
      <figcaption>Menteri Koordinator Bidang Pangan, rapat koordinasi KDKMP, 29 September 2026</figcaption>
    </figure>
  </section>

  <section class="pub-bagian" id="layanan">
    <div class="pub-kepala-bagian">
      <p class="pub-eyebrow">Agrinas Hub</p>
      <h2>Ruang kerja digital perusahaan</h2>
      <p>Satu tempat untuk menerbitkan tugas, mengirim berkas, dan memverifikasi hasil
        kerja mitra binaan &mdash; dengan jejak audit yang dapat dipertanggungjawabkan.
        Salah satu pemanfaatannya menghubungkan Agrinas dengan manajer koperasi.</p>
    </div>

    <div class="pub-kartu-grid">
      <article class="pub-kartu">
        <h3>Alur tugas yang jelas</h3>
        <p>Status bertahap: baru, dikerjakan, diajukan, selesai. Tidak ada tugas yang
          menggantung tanpa ujung.</p>
      </article>
      <article class="pub-kartu">
        <h3>Berkas terverifikasi</h3>
        <p>Setiap berkas yang dikirim dicatat sidik SHA-256, sehingga keasliannya dapat
          diperiksa ulang kapan saja.</p>
      </article>
      <article class="pub-kartu">
        <h3>Jejak audit utuh</h3>
        <p>Setiap tindakan tercatat dan tidak dapat dihapus dari antarmuka. Riwayat kerja
          tetap dapat dipertanggungjawabkan.</p>
      </article>
      <article class="pub-kartu">
        <h3>Halaman mitra publik</h3>
        <p>Data mitra binaan tanpa berkas dan tanpa data pribadi ditampilkan terbuka
          melalui tautan berkode.</p>
      </article>
      <article class="pub-kartu">
        <h3>Satu akun, dua peran</h3>
        <p>Petugas Agrinas dan pengelola mitra masuk lewat pintu yang sama, dengan menu
          dan kewenangan yang menyesuaikan perannya.</p>
      </article>
      <article class="pub-kartu">
        <h3>Keamanan akun</h3>
        <p>Setiap pengguna mengelola sendiri kata sandinya, dan sandi awal wajib
          diganti sebelum ruang kerja dapat dipakai sepenuhnya.</p>
      </article>
    </div>

    <ol class="pub-langkah">
      <li><b>Terbitkan tugas</b>Agrinas membuat tugas untuk mitra tertentu, dengan tenggat dan prioritas.</li>
      <li><b>Kerjakan</b>Mitra melaksanakan, lalu mengirim berkas pendukung.</li>
      <li><b>Ajukan hasil</b>Hasil kerja diajukan untuk diverifikasi Agrinas.</li>
      <li><b>Verifikasi</b>Agrinas menyetujui atau meminta revisi &mdash; semuanya tercatat.</li>
    </ol>
  </section>

  <section class="pub-bagian pub-latar-lembut pub-dua pub-binaan">
    <figure class="pub-gambar">
      <img src="/img/koperasi-gerai.jpg"
           alt="Warga berbelanja kebutuhan pokok di gerai koperasi desa"
           width="1200" height="800" loading="lazy">
      <figcaption>Gerai koperasi desa &mdash; beras, minyak goreng, dan kebutuhan pokok dijual
        langsung kepada warga.</figcaption>
    </figure>
    <div>
      <p class="pub-eyebrow">Mitra binaan</p>
      <h2>Melayani kepentingan masyarakat desa</h2>
      <p>Koperasi desa berfungsi sebagai tempat berkumpul dan melayani kebutuhan warga:
        menyalurkan barang bersubsidi, menjadi mitra pembiayaan, dan menghubungkan desa
        dengan rantai pasok nasional.</p>
      <p>Melalui Agrinas Hub, pendampingan koperasi binaan tercatat rapi: tugas, berkas,
        dan verifikasinya dapat ditelusuri kembali.</p>
    </div>
  </section>

  @include('beranda-direksi')
  @include('beranda-media')

  @include('publik.kaki')

</div>
@endsection

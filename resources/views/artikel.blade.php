@extends('layouts.utama')
@section('kelas_tubuh', 'beranda-tubuh')
@section('judul', 'Pusat Informasi')
@section('deskripsi', 'Kumpulan pemberitaan media tentang PT Agrinas Pangan Nusantara (Persero) dan Koperasi Desa/Kelurahan Merah Putih (KDKMP).')
@section('robots', 'index, follow, max-image-preview:large')
@php $hlm = max(1, request()->integer('halaman', 1)); @endphp
@section('kanonik', $hlm > 1 ? route('artikel', ['halaman' => $hlm]) : route('artikel'))

@section('isi')
<div class="situs">

  <header class="pub-bar">
    <a class="pub-merek" href="/" aria-label="PT Agrinas Pangan Nusantara (Persero) — beranda">
      <img class="merek-danantara" src="/img/logo-danantara-kecil.png" alt="Danantara Indonesia"
           width="420" height="105">
      <span class="merek-pemisah" aria-hidden="true"></span>
      <img class="merek-agrinas" src="/img/logo-agrinas-kecil.png" alt="Agrinas Pangan Nusantara"
           width="160" height="76">
    </a>
    <nav class="pub-nav" aria-label="Navigasi halaman">
      <a href="/#tentang">Tentang</a>
      <a href="/#mandat">Mandat</a>
      <a href="/#lini-bisnis">Lini Bisnis</a>
      <a href="/#layanan">Agrinas Hub</a>
      <a href="/#direksi">Direksi</a>
      <a href="{{ route('artikel') }}" aria-current="page">Media</a>
      <a href="/#kontak">Kontak</a>
    </nav>
    {{-- Di bilah desktop tidak pernah ada tautan masuk; hanya menu ☰ (<=1060px)
         yang punya .masuk-menu. Tombol ini yang menutup celah itu. --}}
    <a class="tombol utama masuk-bar" href="{{ route('masuk') }}">Masuk</a>
    <details class="pub-menu">
      <summary aria-label="Buka menu navigasi"><span aria-hidden="true">☰</span> Menu</summary>
      <nav aria-label="Navigasi halaman (mobile)">
        <a href="/#tentang">Tentang</a>
        <a href="/#mandat">Mandat KDKMP</a>
        <a href="/#lini-bisnis">Lini Bisnis</a>
        <a href="/#layanan">Agrinas Hub</a>
        <a href="/#direksi">Jajaran Direksi</a>
        <a href="{{ route('artikel') }}">Pusat Informasi</a>
        <a href="/#kontak">Kontak</a>
        <a class="masuk-menu" href="{{ route('masuk') }}">Masuk ke akun</a>
      </nav>
    </details>
  </header>

  <section class="pub-bagian">
    <div class="pub-kepala-bagian">
      <p class="pub-eyebrow"><a href="/#media">Pusat informasi</a></p>
      <h2>Semua artikel Agrinas Pangan</h2>
      <p>{{ $jumlah }} pemberitaan terverifikasi &mdash; menampilkan {{ count($artikel) }} per halaman.</p>
    </div>

    @if (count($artikel))
      <div class="pub-media">
        @foreach ($artikel as $a)
          <a class="pub-artikel{{ $a['gambar'] ? '' : ' tanpa-gambar' }}"
             href="{{ $a['url'] }}" target="_blank" rel="noopener noreferrer">
            @if ($a['gambar'])
              <div class="gambar">
                <img src="{{ $a['gambar'] }}" alt="" loading="lazy">
                <span class="badge"><img src="/img/logo-lockup-kecil.png" alt="Agrinas Pangan &middot; Danantara"></span>
              </div>
            @endif
            <div class="isi-artikel">
              <p class="meta">
                @if ($a['media'])<span class="media">{{ $a['media'] }}</span>@endif
                @if ($a['tanggal'])<span>{{ $a['tanggal'] }}</span>@endif
              </p>
              <h3>{{ $a['judul'] }}</h3>
              @if ($a['ringkasan'])<p>{{ $a['ringkasan'] }}</p>@endif
              <span class="baca">Baca di {{ $a['media'] ?: 'sumber asli' }} &rarr;</span>
            </div>
          </a>
        @endforeach
      </div>

      @if ($totalHalaman > 1)
        {{-- Paging: tombol di ujung, nomor halaman di tengah. Tombol yang tidak
             berlaku tetap dirender (tanpa-isi) supaya nomor halaman tidak
             bergeser saat berpindah halaman. --}}
        <nav class="pub-halaman" aria-label="Navigasi halaman artikel">
          <a class="tombol samar{{ $halaman > 1 ? '' : ' tanpa-isi' }}"
             @if ($halaman > 1) href="{{ route('artikel', ['halaman' => $halaman - 1]) }}" rel="prev"
             @else aria-hidden="true" tabindex="-1" @endif>&larr; Sebelumnya</a>

          <ol class="pub-halaman-angka">
            @for ($n = 1; $n <= $totalHalaman; $n++)
              <li>
                @if ($n === $halaman)
                  <span aria-current="page">{{ $n }}</span>
                @else
                  <a href="{{ route('artikel', ['halaman' => $n]) }}"
                     aria-label="Halaman {{ $n }}">{{ $n }}</a>
                @endif
              </li>
            @endfor
          </ol>

          <a class="tombol samar{{ $halaman < $totalHalaman ? '' : ' tanpa-isi' }}"
             @if ($halaman < $totalHalaman) href="{{ route('artikel', ['halaman' => $halaman + 1]) }}" rel="next"
             @else aria-hidden="true" tabindex="-1" @endif>Berikutnya &rarr;</a>
        </nav>
      @endif

      <p class="pub-media-catatan">Setiap artikel tertaut ke penerbit aslinya. Hak cipta berita
        dan foto tetap milik media bersangkutan; dimuat di sini sebagai rujukan informasi publik.</p>
    @else
      <p class="pub-media-catatan">Belum ada artikel terverifikasi yang dimuat.</p>
    @endif
  </section>

  @include('publik.kaki')

</div>
@endsection

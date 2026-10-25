@extends('layouts.utama')

@section('judul', 'Halaman tidak untuk peran Anda')
@section('isi')
<header class="kepala">
  <div>
    <h1>Halaman ini bukan untuk peran Anda</h1>
    <p class="tenang">
      {{-- 403 juga bisa datang untuk TAMU (tanda tangan URL kedaluwarsa, token
           CSRF salah, pemindai menembak rute bertanda tangan). Tanpa penjagaan
           ini halaman galat itu sendiri meledak: "Attempt to read property
           peran on null" — 500 pada jalur yang seharusnya cuma 403. --}}
      @php $u403 = auth()->user(); @endphp
      @if ($u403)
      Akun Anda masuk sebagai
      <b>{{ \App\Models\Domain::LABEL_PERAN[$u403->peran] ?? $u403->peran }}</b>@if ($u403->pic() && $u403->wilayah) — {{ \App\Support\Wilayah::label($u403->wilayah) }}@endif.
      @else
      Anda belum masuk — silakan <a href="{{ route('masuk') }}">masuk</a> lebih dulu, atau tautan yang dipakai sudah kedaluwarsa.
      @endif
      Menu di sebelah kiri sudah disaring sesuai wewenang Anda, jadi halaman ini
      biasanya hanya terbuka lewat tautan lama atau alamat yang diketik langsung.
    </p>
  </div>
</header>

<section class="blok">
  <h2>Yang bisa Anda lakukan</h2>
  <p class="bantuan">
    Kembali ke beranda peran Anda, atau buka daftar menu lengkap yang memang
    tersedia untuk akun ini.
  </p>
  <div class="tombol-baris">
    @if ($u403)
      <a class="tombol utama" href="{{ rute_dasbor($u403) }}">Ke beranda</a>
    @else
      <a class="tombol utama" href="{{ route('masuk') }}">Masuk</a>
    @endif
    <a class="tombol samar" href="{{ route('fitur') }}">Semua Menu</a>
    @if ($u403 && $u403->agrinas())
      <a class="tombol samar" href="{{ route('admin.akun') }}">Kelola Akun</a>
    @endif
  </div>
</section>

<section class="blok">
  <h2>Butuh akses ini?</h2>
  <p class="bantuan">
    Wewenang melekat pada peran akun, bukan pada halaman. Minta Admin Pusat
    menyesuaikan peran akun Anda di <b>Akun Pengguna</b> bila pekerjaan Anda memang
    memerlukan halaman ini.
  </p>
</section>
@endsection

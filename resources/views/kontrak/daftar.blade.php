@extends('layouts.utama')

@section('judul', 'Kontrak')

@section('isi')
<header class="kepala">
  <div>
    <h1>Kontrak Pendamping</h1>
    <p class="tenang">
      Kontrak, periode pendampingan, dan penempatan koperasi dalam satu tabel —
      <b>{{ number_format($total, 0, ',', '.') }} baris</b> tersalin dari SIMKOPDES.
      @if ($user->agrinas())
        Pusat melihat kontrak seluruh pendamping.
      @elseif ($user->pic())
        Yang tampil hanya pendamping di wilayah <b>{{ \App\Support\Wilayah::pendek((string) $user->wilayah) }}</b>.
      @else
        Yang tampil hanya kontrak Anda sendiri.
      @endif
    </p>
  </div>
  <div class="tombol-baris">
    @if ($sandiSiap)
      <form method="post" action="{{ route('kontrak.tarik') }}">
        @csrf
        <button class="tombol utama" type="submit">Tarik Kontrak Saya</button>
      </form>
    @endif
  </div>
</header>

{{--
  Kontrak ikut tersalin OTOMATIS saat pendamping masuk lewat SIMKOPDES — sama
  seperti absensi. Tombol di atas hanya jalan pintas kalau datanya berubah
  setelah login.
--}}
@if ($user->manajerKoperasi() && ! $sandiSiap)
  <p class="bantuan">
    Kontrak Anda belum tersalin karena akun SIMKOPDES belum terhubung. Hubungkan
    lebih dulu di halaman <a href="{{ route('agri.presensi') }}#absen">Presensi &amp; Absensi</a> —
    setelah itu kontrak ikut tersalin sendiri tiap kali Anda masuk.
  </p>
@endif

{{-- Wadah ber-`data-isi`: hub.js menukar ISI-nya untuk paging, sementara
     hub-kontrak.js menukar hanya <tbody> saat menyaring supaya fokus mengetik
     tidak hilang. Tanpa JavaScript semuanya tetap form GET & tautan biasa. --}}
<section class="blok" id="kt" data-isi="{{ route('kontrak') }}" data-daftar="{{ route('kontrak.baris') }}">
  <h2>Daftar Kontrak
    <small class="pudar" id="kt-jumlah">{{ number_format($jumlah, 0, ',', '.') }} baris</small>
  </h2>

  {{-- Kartu status = ringkasan + penyaring. "Semua" selalu ada supaya penyaring
       tidak pernah buntu. Tanpa JavaScript kartunya tautan biasa. --}}
  <div class="kartu-baris kartu-status">
    <a class="kartu angka {{ $saringStatus === 'SIGNED' ? 'aksi-hijau' : '' }}"
       href="{{ route('kontrak', array_filter(['status' => 'SIGNED', 'q' => $cari])) }}">
      <small>Ditandatangani</small><b>{{ $stat['SIGNED'] ?? 0 }}</b>
    </a>
    <a class="kartu angka {{ $saringStatus === 'DRAFT' ? 'aksi-hijau' : '' }}"
       href="{{ route('kontrak', array_filter(['status' => 'DRAFT', 'q' => $cari])) }}">
      <small>Draf</small><b>{{ $stat['DRAFT'] ?? 0 }}</b>
    </a>
    <a class="kartu angka {{ $saringStatus === 'REJECTED' ? 'aksi-hijau' : '' }}"
       href="{{ route('kontrak', array_filter(['status' => 'REJECTED', 'q' => $cari])) }}">
      <small>Ditolak</small><b>{{ $stat['REJECTED'] ?? 0 }}</b>
    </a>
    <a class="kartu angka {{ $saringStatus === '' ? 'aksi-hijau' : '' }}"
       href="{{ route('kontrak', array_filter(['q' => $cari])) }}">
      <small>Semua</small><b>{{ number_format($total, 0, ',', '.') }}</b>
    </a>
  </div>

  {{-- SENGAJA TANPA `data-otomatis`: atribut itu membuat hub.js mengirim
       formulir tiap ketikan dan menukar SELURUH wadah [data-isi], sehingga
       kotak cari ikut dibuang dan fokus hilang. Penukaran <tbody> ditangani
       hub-kontrak.js; Enter tetap bekerja lewat jalur hub.js. --}}
  <form class="saring" method="get" action="{{ route('kontrak') }}">
    <label class="cari">
      <span class="sr">Cari nama, email, nomor kontrak, atau koperasi</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off"
             placeholder="Cari nama, nomor kontrak, atau koperasi…">
    </label>
    <label class="pilih">
      <select name="status" aria-label="Saring berdasarkan status kontrak">
        <option value="">Semua status</option>
        <option value="SIGNED" @selected($saringStatus === 'SIGNED')>Ditandatangani ({{ $stat['SIGNED'] ?? 0 }})</option>
        <option value="DRAFT" @selected($saringStatus === 'DRAFT')>Draf ({{ $stat['DRAFT'] ?? 0 }})</option>
        <option value="REJECTED" @selected($saringStatus === 'REJECTED')>Ditolak ({{ $stat['REJECTED'] ?? 0 }})</option>
      </select>
    </label>
    <a class="tombol samar bersih-saring" href="{{ route('kontrak') }}"
       @if ($cari === '' && $saringStatus === '') hidden @endif>Bersihkan</a>
    <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr>
      <th>Pendamping</th><th>Periode</th><th>Penempatan</th><th>Kontrak</th>
      <th>Status</th><th>Tanggal</th><th>Sumber</th><th class="kolom-aksi">Berkas</th>
    </tr></thead>
    <tbody id="kt-baris">
      @include('kontrak._baris', ['kontrak' => $kontrak, 'pemilik' => $user->id,
        'kosong' => ($cari !== '' || $saringStatus !== '')])
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>

<script src="/js/hub-kontrak.js?v={{ filemtime(public_path('js/hub-kontrak.js')) }}" defer></script>
@endsection

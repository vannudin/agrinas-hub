@extends('layouts.utama')
@section('judul', 'Produk')
@section('isi')
@php
  $saldos = $saldos ?? [];
  // Admin/PIC boleh memilih koperasi yang dibelanjakan; manajer terkunci ke
  // koperasinya (koperasiBelanja() sudah menentukannya).
  $bolehPilih = auth()->user()->manajerKoperasi() ? collect() : $koperasiPilihan;
  // $manajer datang dari controller (bagianKatalog) supaya partial kartu
  // menerima nilai yang SAMA di jalur muat-penuh maupun jalur gulir AJAX.
  $manajer = $manajer ?? (bool) auth()->user()->manajerKoperasi();
  $jenisAktif = $jenisAktif ?? '';
  $cariAktif = $cariAktif ?? '';
@endphp
<header class="kepala">
  <div>
    <h1>Produk</h1>
    <p class="tenang">
      @if ($kop) Belanja untuk <b>{{ $kop->kode }} — {{ $kop->nama }}</b>
      @else Akun ini belum ditautkan ke koperasi. @endif
    </p>
  </div>
  {{-- Ikon keranjang di sini DIHAPUS (permintaan Bos 2026-10-21): bilah modul
       di bawah sudah punya butir "Keranjang" sendiri, jadi ikon kedua di kepala
       hanya mengulang tautan yang sama dan mendorong judul koperasi ke kiri.
       Jumlah barang TIDAK hilang — ia tampil sebagai lencana pada butir
       "Keranjang" di modul-nav (lihat toko/_nav.blade.php), satu tempat saja. --}}
</header>
@include('toko._nav', ['mod' => 'katalog', 'keranjang' => $keranjang])

@if ($bolehPilih->isNotEmpty())
  {{-- Berpindah koperasi mengosongkan keranjang (keranjang dikunci satu
       koperasi) — diberitahukan di formulirnya supaya tidak mengagetkan. --}}
  <form method="get" action="{{ route('toko') }}" class="katalog-baris">
    <label>Belanja untuk koperasi
      <select name="koperasi_id" onchange="this.form.submit()">
        @foreach ($bolehPilih as $k)
          <option value="{{ $k->id }}" @selected($kop && $kop->id === $k->id)>{{ $k->kode }} — {{ $k->nama }}</option>
        @endforeach
      </select>
    </label>
    <noscript><button class="tombol" type="submit">Ganti</button></noscript>
  </form>
@endif

@include('toko._saldo', ['kop' => $kop, 'saldos' => $saldos])

<section class="blok">
  {{-- SATU daftar untuk semua produk. Dulu gudang & produk lokal punya dua
       kisi berjudul sendiri, masing-masing dengan penghitung dan penutup
       "sudah ditampilkan" — di ponsel itu jadi dua blok besar dan dua angka
       membingungkan ("14 barang" lalu "0 produk"). Bagi pembeli keduanya sama:
       barang yang bisa dibeli. Pembedanya sekarang penyaring `jenis`. --}}
  <h2>Katalog
    <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ number_format($halaman->jumlah, 0, ',', '.') }} produk</small>
  </h2>

  {{-- Pencarian + penyaring memakai formulir GET biasa: bekerja tanpa JS, dan
       tetap benar saat halaman dibuka dengan penyaring yang sudah terpasang. --}}
  <form class="saring saring-katalog" method="get" action="{{ route('toko') }}" data-otomatis>
    @if ($kop && ! $manajer)<input type="hidden" name="koperasi_id" value="{{ $kop->id }}">@endif
    <label class="cari">
      <span class="sr">Cari produk</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cariAktif }}" placeholder="Cari nama atau kode produk…">
    </label>
    <label class="pilih">
      <span class="sr">Jenis produk</span>
      <select name="jenis">
        <option value="">Semua produk</option>
        @foreach ($jenis as $k => $l)
          <option value="{{ $k }}" @selected($jenisAktif === $k)>{{ $l }}</option>
        @endforeach
      </select>
    </label>
    {{-- Tombol "Terapkan" hanya untuk peramban tanpa JS; dengan JS formulir
         ini dikirim otomatis saat isian berubah (lihat `data-otomatis`). --}}
    <noscript><button class="tombol" type="submit">Terapkan</button></noscript>
    <a class="tombol samar bersih-saring" href="{{ route('toko', $kop ? ['koperasi_id' => $kop->id] : []) }}"
       @if ($cariAktif === '' && $jenisAktif === '') hidden @endif>Bersihkan</a>
  </form>

  {{-- Wadah gulir: `data-isi` = potongan yang datang saat digulir. Satu partial
       untuk muat penuh & AJAX, jadi kartunya tidak mungkin berbeda. --}}
  <div id="katalog-wadah" data-isi="{{ route('toko') }}"
       data-params="q,jenis,koperasi_id,halaman">
    @include('toko._katalog-isi', ['barang' => $barang, 'halaman' => $halaman,
      'cariAktif' => $cariAktif, 'jenisAktif' => $jenisAktif])
  </div>
</section>
@endsection

@push('skrip')
  {{-- Gulir-tak-berujung. Dipisah ke berkasnya sendiri karena hub.js dimuat di
       SEMUA halaman; perilaku yang hanya dipakai satu halaman tidak boleh
       menambah berat halaman lain. --}}
  <script src="{{ asset('js/hub-toko.js') }}" defer></script>
@endpush

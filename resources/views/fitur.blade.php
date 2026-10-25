@extends('layouts.utama')

@section('judul', 'Semua Fitur')
@section('isi')
<header class="kepala">
  <div>
    <h1>Semua Fitur</h1>
  </div>
</header>

<section class="blok">
  <h2>Menu</h2>
  {{-- Tidak ada kartu "Semua Fitur" di sini: memasang halaman ini sebagai
       tautan ke dirinya sendiri tidak ada gunanya, dan tadi terbaca sebagai
       tujuan yang sama dua kali. --}}
  <div class="kisi-fitur">
    @foreach ($menu as [$kunci, $rute, $label])
      <a class="kartu-fitur{{ $aktif === $kunci ? ' ini' : '' }}" href="{{ route($rute) }}"
         {!! $aktif === $kunci ? 'aria-current=page' : '' !!}>
        <span class="kartu-fitur-ikon">{!! ikon($kunci, 'ik-fitur') !!}</span>
        <span class="kartu-fitur-label">{{ $label }}</span>
      </a>
    @endforeach
  </div>
  <p class="bantuan">Bilah bawah hanya memuat 4 menu agar nyaman di layar ponsel; semua menu tetap bisa diakses dari sini.</p>
</section>
@endsection

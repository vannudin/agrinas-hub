@extends('layouts.utama')

@section('judul', 'Audit')
@section('isi')
<header class="kepala">
  <div><h1>Jurnal Audit</h1>
    <p class="tenang">Jejak tindakan — terbaru lebih dulu. Halaman {{ $halaman->halaman }} dari {{ $halaman->total }}.</p></div>
</header>

{{-- Ringkasan tren saja di sini: analisa penuhnya (cakupan, sebaran, kesiapan,
     rincian per provinsi) ada di halaman Analisa. Label aksi datang dari
     App\Support\Analisa supaya tabel ini dan panel aksi teratas di halaman
     Analisa tidak pernah memberi nama berbeda untuk satu aksi yang sama. --}}
@include('partials.analisa', [
    'an' => $analisa,
    'anBagian' => ['tren'],
    'anRinci' => false,
])

<section class="blok">
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Target</th><th>Keterangan</th><th>IP</th></tr></thead>
    <tbody>
    @forelse ($catatan as $c)
      <tr>
        <td data-l="Waktu">{{ waktu_id($c->dibuat) }}</td>
        <td data-l="Aktor">
          @if ($c->nama_aktor)
            <span class="judul-sel">{{ $c->nama_aktor }}</span><small class="pudar">{{ $c->email_aktor }}</small>
          @else
            {{ $c->aktor ?: 'sistem' }}
          @endif
        </td>
        <td data-l="Aksi"><span class="lencana s-BARU">{{ \App\Support\Analisa::labelAksi((string) $c->aksi) }}</span></td>
        <td data-l="Target">{{ $c->target ?: '—' }}</td>
        <td data-l="Keterangan">{{ $c->detail ?: '—' }}</td>
        <td data-l="IP">{{ $c->ip ?: '—' }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="kosong">Jurnal masih kosong.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>
@endsection

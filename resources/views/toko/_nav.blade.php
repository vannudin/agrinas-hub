{{-- Bilah modul Toko. Isinya mengikuti peran:
       · manajer koperasi — Katalog, Keranjang, Pesanan (dia yang berbelanja);
       · Agrinas pusat    — Pesanan + Kelola Katalog (tidak berbelanja);
       · PIC wilayah      — Pesanan + Kelola Katalog saja.
     Katalog & Keranjang disembunyikan untuk pengelola karena rutenya memang
     dialihkan ke Pesanan — tab yang selalu memantul bukan pilihan. --}}
@php
  $manajer = auth()->user()->manajerKoperasi();
  $tab = $manajer
    ? [['katalog', 'toko', 'umkm', 'Katalog'], ['keranjang', 'toko.keranjang', 'keranjang', 'Keranjang']]
    : [];
  $tab[] = ['pesanan', 'toko.pesanan', 'materi', 'Pesanan'];
  if (! $manajer) {
    $tab[] = ['kelola', 'toko.kelola', 'stok', 'Kelola Katalog'];
  }
@endphp
<nav class="modul-nav" aria-label="Modul Toko">
  @foreach ($tab as [$kunci, $rute, $ik, $label])
    <a href="{{ route($rute) }}" class="{{ ($mod ?? '') === $kunci ? 'ini' : '' }}"
       {!! ($mod ?? '') === $kunci ? 'aria-current=page' : '' !!}>{!! ikon($ik, 'ik kecil') !!}<span>{{ $label }}</span>
      {{-- Penanda jumlah barang: HANYA di butir "Keranjang". `aria-label` ikut
           menyebut angkanya karena lencana ini `aria-hidden` — pembaca layar
           tidak boleh mendengar tautan "Keranjang" tanpa jumlahnya. --}}
      @if ($kunci === 'keranjang' && ($keranjang['jumlah'] ?? 0) > 0)
        <span class="lencana-notif lencana-nav" aria-hidden="true">{{ $keranjang['jumlah'] }}</span>
        <span class="hanya-pembaca">{{ $keranjang['jumlah'] }} barang di keranjang</span>
      @endif</a>
  @endforeach
</nav>

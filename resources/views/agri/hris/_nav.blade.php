{{--
  Navigasi halaman FITUR AgriConnect (/agri/hris/*), bukan kisi 21 kartu.

  KENAPA TERPISAH DARI agri/_nav: kisi modul adalah DAFTAR TUJUAN. Menampakannya
  di atas setiap fitur membuat isi halaman terdorong jauh ke bawah — di ponsel
  pengguna harus menggulir melewati 21 kartu dulu untuk melihat kalender
  absensi (keluhan Bos 2026-10-21). Halaman fitur cukup membawa navigasi
  antarfitura yang ringkas + jalan pulang, seperti halaman lain.

  Daftarnya TETAP diturunkan dari AgriController::MODUL (butir ber-rute
  `agri.hris.*`), supaya tidak ada daftar kedua yang bisa menyimpang.
--}}
@php
  $butir = array_values(array_filter(
      \App\Http\Controllers\AgriController::MODUL,
      fn ($m) => $m[0] !== 'hris-hubungkan' && str_starts_with((string) $m[1], 'agri.hris.')
  ));
  // Peran pemantau tidak punya akun AgriConnect pribadi — lencana "Tersambung"
  // di sini akan menunjuk sesi orang lain yang kebetulan tertaut.
  $agriSambung = auth()->check() && auth()->user()->manajerKoperasi()
      && app(\App\Services\Agriconnect::class)->tersambung(auth()->user());
  $iniSub = $mod ?? '';
@endphp
<nav class="agri-subnav" aria-label="Fitur AgriConnect">
  <a class="agri-subnav-pulang" href="{{ route('agri.beranda') }}">&larr; AgriConnect</a>
  <div class="agri-subnav-butir">
    @foreach ($butir as $m)
      <a href="{{ route($m[1]) }}"@if ($iniSub === $m[0]) class="ini" aria-current=page @endif>{{ $m[2] }}</a>
    @endforeach
  </div>
  @if ($agriSambung)
    <span class="agri-subnav-status">Tersambung</span>
  @else
    <button type="button" class="agri-subnav-sambung" data-buka="modal-agri">Hubungkan akun</button>
  @endif
</nav>
@include('agri._hubung')
@push('skrip')
  {{-- Cari + filter + paging untuk semua tabel daftar AgriConnect HRIS. --}}
  <script src="/js/hub-saring-tabel.js?v={{ filemtime(public_path('js/hub-saring-tabel.js')) }}" defer></script>
@endpush

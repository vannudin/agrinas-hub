@extends('layouts.utama')

@section('judul', 'Ulang Tahun')
@section('isi')
<header class="kepala">
  <div>
    <h1>Ulang Tahun</h1>
    <p class="tenang">Rekan kerja yang berulang tahun — dari tanggal lahir di profil pegawai.</p>
  </div>
</header>
@include('agri._nav', ['mod' => 'ulangtahun'])

<section class="blok">
  <div class="kartu-baris">
    <div class="kartu angka"><small>Orang Terdata</small><b>{{ number_format($jumlahOrang, 0, ',', '.') }}</b>
      <small class="pudar">Punya tanggal lahir di profil.</small></div>
    <div class="kartu angka"><small>Ulang Tahun Bulan Ini</small><b>{{ number_format($bulanIni->count(), 0, ',', '.') }}</b>
      <small class="pudar">{{ now()->locale('id')->translatedFormat('F Y') }}</small></div>
  </div>
  @if ($jumlahOrang === 0)
    <p class="bantuan">
      Belum ada pegawai dengan tanggal lahir terisi. Tanggal lahir ikut terisi saat
      profil ditarik dari <a href="{{ route('profil') }}">halaman Profil</a>.
    </p>
  @endif
</section>

<section class="blok">
  <h2>Pilih Bulan</h2>
  <p class="pilihan-sebaris">
    @foreach (range(1, 12) as $b)
      <a class="tombol {{ $b === $bulan ? 'utama' : 'samar' }}"
         href="{{ route('agri.ulangtahun', ['bulan' => $b]) }}"
         @if ($b === $bulan) aria-current="page" @endif>
        {{ \Carbon\Carbon::create()->month($b)->locale('id')->translatedFormat('M') }}
      </a>
    @endforeach
  </p>
</section>

<section class="blok">
  <h2>{{ \Carbon\Carbon::create()->month($bulan)->locale('id')->translatedFormat('F') }}
    <small class="pudar">{{ $bulanIni->count() }} orang</small></h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Tanggal</th><th>Nama</th><th>Umur</th><th>Koperasi</th></tr></thead>
    <tbody>
      @forelse ($bulanIni as $r)
        <tr>
          <td data-l="Tanggal"><span class="lencana">{{ $r->hari }}</span></td>
          <td data-l="Nama"><span class="judul-sel">{{ $r->nama }}</span>
            @if ($r->email !== '')<small class="pudar">{{ $r->email }}</small>@endif</td>
          <td data-l="Umur">{{ $r->umur > 0 ? $r->umur.' tahun' : '—' }}</td>
          <td data-l="Koperasi">{{ $r->koperasi !== '' ? $r->koperasi : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Tidak ada yang berulang tahun bulan ini.</td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
</section>

@if ($setahun->count() > 1)
<section class="blok">
  <h2>Setahun</h2>
  <ul class="daftar-ringkas">
    @foreach ($setahun as $b => $orang)
      <li>
        <a href="{{ route('agri.ulangtahun', ['bulan' => $b]) }}">
          {{ \Carbon\Carbon::create()->month($b)->locale('id')->translatedFormat('F') }}</a>
        <span class="pudar">{{ $orang->count() }} orang</span>
      </li>
    @endforeach
  </ul>
</section>
@endif
@endsection

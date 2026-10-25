@extends('layouts.utama')

@section('judul', 'Struktur & Tim')
@section('isi')
<header class="kepala">
  <div>
    <h1>Struktur &amp; Tim</h1>
    <p class="tenang">Peta pengelola: admin pusat, PIC wilayah, dan manajer koperasi.</p>
  </div>
</header>
@include('agri._nav', ['mod' => 'struktur'])

{{-- Kartu ringkasan SENGAJA di luar wadah AJAX (#st): kalau ikut ditukar,
     angkanya dihitung ulang dari permintaan yang sama dan saringan bisa
     membuat "Manajer Koperasi" berubah jadi jumlah hasil saring — angka
     ringkasan berhenti jadi ringkasan. --}}
<section class="blok">
  <div class="kartu-baris">
    <div class="kartu angka"><small>Admin Pusat</small><b>{{ $pusat->count() }}</b>
      <small class="pudar">Lingkup seluruh negeri.</small></div>
    <div class="kartu angka"><small>PIC Wilayah</small><b>{{ $pic->count() }}</b>
      <small class="pudar">Lingkup wilayah masing-masing.</small></div>
    <div class="kartu angka"><small>Manajer Koperasi</small><b>{{ number_format($jumlahManajer, 0, ',', '.') }}</b>
      <small class="pudar">Koperasi yang sudah punya manajer aktif.</small></div>
    <div class="kartu angka"><small>Koperasi Terjangkau Anda</small><b>{{ number_format($jumlahKoperasi, 0, ',', '.') }}</b>
      <small class="pudar">{{ $saya->lingkupPenuh() ? 'Semua koperasi.' : 'Sesuai wilayah/koperasi Anda.' }}</small></div>
  </div>
  <p class="bantuan">
    Tugas Saya (AgriConnect) tidak terlihat oleh siapa pun selain Anda. Yang
    bersifat lintas peran biasanya dikerjakan di
    <a href="{{ route('workspace.tugas') }}">Tasks Ruang Kerja</a>.
  </p>
</section>

{{-- Cakupan kerja: berapa koperasi yang benar-benar punya jejak di tiap modul.
     Kartu di atas hanya menghitung AKUN; blok ini yang memberi tahu apakah
     pekerjaannya berjalan. Partialnya sama dengan halaman Analisa, jadi satu
     definisi "punya jejak" untuk seluruh aplikasi. --}}
@include('partials.analisa', [
    'an' => ['cakupan' => $cakupan],
    'anBagian' => ['cakupan'],
    'anRinci' => false,
])

<section class="blok">
  <h2>Admin Pusat</h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Nama</th><th>Email</th></tr></thead>
    <tbody>
      @forelse ($pusat as $p)
        <tr><td data-l="Nama"><span class="judul-sel">{{ $p->nama }}</span></td><td data-l="Email">{{ $p->email }}</td></tr>
      @empty
        <tr><td colspan="2" class="kosong">Tidak ada akun admin pusat aktif.</td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
</section>

<section class="blok">
  <h2>PIC Penanggung Jawab Wilayah</h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Wilayah</th><th>Nama</th><th>Email</th></tr></thead>
    <tbody>
      @forelse ($pic as $p)
        <tr>
          <td data-l="Wilayah"><span class="lencana">{{ \App\Support\Wilayah::pendek((string) $p->wilayah) }}</span></td>
          <td data-l="Nama"><span class="judul-sel">{{ $p->nama }}</span></td>
          <td data-l="Email">{{ $p->email }}</td>
        </tr>
      @empty
        <tr><td colspan="3" class="kosong">Belum ada akun PIC wilayah aktif.</td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
</section>

<section class="blok" id="st" data-isi="{{ route('agri.struktur') }}" data-params="q,wilayah,halaman">
  <h2>Manajer Koperasi
    <small class="pudar">
      @if ($cari !== '' || $wilayah !== '')
        {{ number_format($halaman->jumlah, 0, ',', '.') }} hasil saringan
      @else
        {{ number_format($halaman->jumlah, 0, ',', '.') }} akun aktif
      @endif
    </small>
  </h2>

  <form class="saring" method="get" action="{{ route('agri.struktur') }}">
    <label class="cari">
      <span class="sr">Cari manajer atau koperasi</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari nama, surel, koperasi, atau daerah…">
    </label>
    <label>
      <span class="sr">Wilayah</span>
      <select name="wilayah">
        <option value="">Semua wilayah</option>
        @foreach ($wilayahPilihan as $k => $l)
          <option value="{{ $k }}" @selected($wilayah === $k)>{{ $l }}</option>
        @endforeach
      </select>
    </label>
    <button class="tombol" type="submit">Terapkan</button>
    <a class="tombol samar bersih-saring" href="{{ route('agri.struktur') }}"
       @if ($cari === '' && $wilayah === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Manajer</th><th>Koperasi</th><th>Wilayah</th><th>Email</th></tr></thead>
    <tbody>
      @forelse ($koperasi as $k)
        @php $m = $k->manajer; @endphp
        <tr>
          <td data-l="Manajer">
            @if ($m)
              <span class="judul-sel">{{ $m->nama }}</span>
              @if ($m->id === $saya->id) <small class="pudar">akun Anda</small>@endif
            @else
              <span class="pudar">— belum ditetapkan</span>
            @endif
          </td>
          <td data-l="Koperasi">
            <span class="judul-sel">{{ $k->nama }}</span>
            @if ($k->kode !== '') <small class="pudar">{{ $k->kode }}</small>@endif
            @if ($k->wilayah !== '') <small class="pudar">{{ $k->wilayah }}</small>@endif
          </td>
          @php $ling = \App\Support\Wilayah::lingkup((string) $k->provinsi, (string) $k->wilayah); @endphp
          <td data-l="Wilayah"><span class="lencana">{{ $ling !== '' ? \App\Support\Wilayah::pendek($ling) : '—' }}</span></td>
          <td data-l="Email">{{ $m->email ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">
          @if ($cari !== '' || $wilayah !== '')
            Tidak ada manajer/koperasi yang cocok dengan saringan ini.
          @else
            Belum ada manajer koperasi aktif.
          @endif
        </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
  <p class="bantuan">
    Daftar penuh akun beserta pengelolaannya ada di
    <a href="{{ route('admin.akun') }}">Akun</a> (kewenangan admin pusat).
  </p>
</section>
@endsection

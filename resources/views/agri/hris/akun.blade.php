@extends('layouts.utama')
@section('judul', 'Akun AgriConnect')

@section('isi')
<div class="kepala">
  <h1>AgriConnect HRIS</h1>
  <p class="tenang">Layanan mandiri pegawai. Hubungkan akun AgriConnect Anda sekali, lalu seluruh fiturnya terbuka di Hub.</p>
</div>

@include('agri.hris._nav', ['mod' => 'hris-akun'])

@if ($tersambung)
  <div class="blok">
    <h2>Sesi aktif</h2>
    <p>Tersambung sebagai <strong>{{ $namaAgri }}</strong>
      @if ($sejak) sejak {{ \Illuminate\Support\Carbon::parse($sejak)->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB @endif.
    </p>
    <p class="tenang">Data pegawai dibaca langsung dari AgriConnect setiap halaman dibuka — tidak disalin ke Hub.</p>
    <form method="post" action="{{ route('agri.hris.keluar') }}">
      @csrf
      <button class="tombol peringatan" type="submit">Lepas sesi AgriConnect</button>
    </form>
  </div>
@else
  <div class="blok">
    <h2>Masuk dengan akun AgriConnect</h2>
    @if (! $terkonfigurasi)
      <p class="lencana s-REVISI">Akun operasional AgriConnect belum diatur di server (AC_USERNAME/AC_PASSWORD). Hubungi pengelola.</p>
    @endif
    <p>Gunakan nama pengguna &amp; sandi yang sama seperti di aplikasi AgriConnect.</p>
    <form method="post" action="{{ route('agri.hris.masuk') }}" class="grid-form">
      @csrf
      <label for="username">Nama pengguna</label>
      <input id="username" name="username" required autocomplete="username"
             value="{{ old('username') }}" placeholder="mis. irpan-cslw">

      <label for="sandi">Sandi</label>
      <input id="sandi" name="sandi" type="password" required autocomplete="current-password">

      <div class="lebar-penuh">
        <button class="tombol utama" type="submit">Hubungkan</button>
      </div>
    </form>
    <p class="tenang">Sandi tidak disimpan. Yang disimpan hanya token sesi (terenkripsi) di akun Hub Anda.</p>
  </div>
@endif

<div class="blok">
  <h2>Menu AgriConnect &amp; tempatnya di Hub</h2>
  <p class="tenang">Susunan ini mengikuti menu aplikasi AgriConnect. Kolom kanan menunjukkan di halaman mana hal yang sama dikerjakan di Hub.</p>
  <div class="tabel-bungkus">
    <table>
      <thead>
        <tr><th>Bagian</th><th>Menu AgriConnect</th><th>Tempatnya di Hub</th></tr>
      </thead>
      <tbody>
        @foreach ($peta as [$bagian, $judul, $rute, $ket])
          <tr>
            <td data-l="Bagian"><span class="pudar">{{ $bagian }}</span></td>
            <td data-l="Menu AgriConnect">{{ $judul }}</td>
            <td data-l="Tempatnya di Hub">
              @if ($rute && \Illuminate\Support\Facades\Route::has($rute))
                <a href="{{ route($rute) }}">{{ $ket }}</a>
              @else
                <span class="pudar">{{ $ket }}</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection

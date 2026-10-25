@extends('layouts.utama')

@section('judul', 'AgriConnect')
@section('isi')
<header class="kepala">
  <div>
    <h1>AgriConnect</h1>
    <p class="tenang">Layanan mandiri pegawai — diadopsi dari aplikasi AgriConnect / Agrinas HRIS.</p>
  </div>
</header>
@include('agri._nav', ['mod' => 'ringkasan'])

<section class="blok">
  <h2>Hari ini</h2>
  <div class="kartu-baris">
    <div class="kartu angka">
      <small>Presensi</small>
      <b class="panjang">
        @if ($presensiHariIni)
          {{ $presensiHariIni->masuk !== '' ? 'Masuk '.$presensiHariIni->masuk : 'Masuk —' }}
        @else
          Belum Check In
        @endif
      </b>
      <small class="pudar">
        @if ($presensiHariIni && $presensiHariIni->keluar !== '')
          Keluar {{ $presensiHariIni->keluar }}
        @elseif ($presensiHariIni)
          Belum Check Out
        @else
          <a href="{{ route('agri.presensi') }}">Check In sekarang</a>
        @endif
      </small>
    </div>
    <div class="kartu angka"><small>Tugas Aktif</small><b>{{ number_format($tugasBaru, 0, ',', '.') }}</b>
      <small class="pudar"><a href="{{ route('agri.tugas') }}">Buka daftar tugas</a></small></div>
    <div class="kartu angka"><small>Saldo StarPoint</small><b>{{ number_format($saldo, 0, ',', '.') }}</b>
      <small class="pudar"><a href="{{ route('agri.imbalan') }}">Riwayat &amp; hadiah</a></small></div>
    <div class="kartu angka"><small>Umpan Balik Diterima</small><b>{{ number_format($umpanDiterima, 0, ',', '.') }}</b>
      <small class="pudar"><a href="{{ route('agri.umpan') }}">Lihat</a></small></div>
    @if ($menungguPutus > 0)
      <div class="kartu angka"><small>Menunggu Keputusan Anda</small><b>{{ number_format($menungguPutus, 0, ',', '.') }}</b>
        <small class="pudar"><a href="{{ route('agri.perjalanan') }}">Putuskan pengajuan</a></small></div>
    @elseif ($ajuanMenunggu > 0)
      <div class="kartu angka"><small>Pengajuan Anda</small><b>{{ number_format($ajuanMenunggu, 0, ',', '.') }}</b>
        <small class="pudar"><a href="{{ route('agri.perjalanan') }}">Menunggu keputusan</a></small></div>
    @endif
  </div>
</section>

<section class="blok">
  <h2>Yang sengaja tidak dibawa dari aplikasi</h2>
  <p class="bantuan">
    Modul ini mengadopsi layanan mandiri AgriConnect, bukan menyalin seluruh aplikasinya.
    Yang tidak dibawa, dan alasannya:
  </p>
  <ul class="daftar-alasan">
    <li><b>Slip gaji, absensi payroll, dan <i>financial wellness</i></b> — hub ini tidak
      menyimpan data SDM &amp; penggajian; menyalin layarnya hanya menghasilkan halaman kosong.</li>
    <li><b>Onboarding/Offboarding, Career, Assessment, Interview</b> — alur kepegawaian HR
      formal, di luar lingkup hub koordinasi koperasi.</li>
    <li><b>Messenger, Docs &amp; Sheets, Kalender, Approval</b> — sudah dikerjakan
      <a href="{{ route('agri.ruang') }}">Ruang Kerja</a>. Disalin ke sini akan jadi dua
      tempat untuk satu percakapan, dan versi yang lebih lemah.</li>
    <li><b>Koperasi &amp; tim</b> — sudah ada di halaman Koperasi dan Akun; peta pengelolanya
      ada di <a href="{{ route('agri.struktur') }}">Struktur &amp; Tim</a>.</li>
    <li><b>Hierarchy, Organization Insight, Auction, Volunteering</b> — memerlukan data
      organisasi/aset yang tidak ada di hub ini.</li>
  </ul>
</section>
@endsection

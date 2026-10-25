@extends('layouts.utama')

@section('judul', 'Kinerja')
@section('isi')
<header class="kepala">
  <div>
    <h1>Kinerja</h1>
    <p class="tenang">Rekap pribadi dari data yang sudah Anda isi sendiri — kehadiran, tugas, dan poin.</p>
  </div>
</header>
@include('agri._nav', ['mod' => 'kinerja'])

<section class="blok">
  <form class="saring" method="get" action="{{ route('agri.kinerja') }}">
    <label>
      <span class="sr">Bulan</span>
      <input type="month" name="bulan" value="{{ $bulan }}" max="{{ now()->format('Y-m') }}">
    </label>
    <button class="tombol" type="submit">Lihat</button>
  </form>
  <p class="bantuan">
    Halaman ini tidak menyimpan apa pun. Angkanya dibaca ulang dari
    <a href="{{ route('agri.presensi') }}">Presensi</a>,
    <a href="{{ route('agri.tugas') }}">Tugas Saya</a>, dan
    <a href="{{ route('agri.imbalan') }}">StarPoint</a> — jadi selalu sama dengan isinya.
  </p>
</section>

<section class="blok">
  <h2>{{ \Carbon\Carbon::createFromFormat('Y-m', $bulan)->locale('id')->translatedFormat('F Y') }}</h2>
  <div class="kartu-baris">
    <div class="kartu angka"><small>Hari Hadir</small><b>{{ number_format($hariHadir, 0, ',', '.') }}</b>
      <small class="pudar">{{ $lengkap }} hari lengkap masuk &amp; keluar</small></div>
    <div class="kartu angka"><small>Jam Kerja</small><b>{{ number_format($jamKerja, 0, ',', '.') }}</b>
      <small class="pudar">{{ $menitKerja }} menit dari jam masuk–keluar</small></div>
    <div class="kartu angka"><small>Terlambat</small><b>{{ number_format($terlambat, 0, ',', '.') }}</b>
      <small class="pudar">Masuk setelah {{ $jamMasuk }}</small></div>
    <div class="kartu angka"><small>Tugas Selesai</small><b>{{ number_format($tugasSelesai, 0, ',', '.') }}</b>
      <small class="pudar">dari {{ $tugasTotal }} tugas bulan ini</small></div>
    <div class="kartu angka"><small>Lembur</small><b>{{ number_format($lembur, 0, ',', '.') }}</b>
      <small class="pudar">Tugas berjenis Overtime</small></div>
    <div class="kartu angka"><small>Poin Bulan Ini</small><b>{{ number_format($poinBulan, 0, ',', '.') }}</b>
      <small class="pudar">Saldo kini {{ number_format($saldo, 0, ',', '.') }}</small></div>
    <div class="kartu angka"><small>Umpan Diterima</small><b>{{ number_format($umpanDiterima, 0, ',', '.') }}</b>
      <small class="pudar">{{ $umpanDikirim }} yang Anda kirim</small></div>
  </div>
</section>

<section class="blok">
  <h2>Catatan</h2>
  <ul class="daftar-alasan">
    <li>Penilaian kinerja formal (goal, performance review, assessment) tidak
      diadopsi: itu alur kepegawaian HR dan butuh penilai, bobot, serta periode
      yang tidak ada di hub ini.</li>
    <li>Slip gaji dan absensi payroll juga tidak ada di sini — hub ini tidak
      menyimpan data penggajian.</li>
    <li>Yang tampil di atas murni hasil kerja Anda sendiri, dan hanya terlihat
      oleh Anda.</li>
  </ul>
</section>
@endsection

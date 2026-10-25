@extends('layouts.utama')
@section('judul', 'Jobdesc Manajer')
@section('isi')
<header class="kepala">
  <div>
    <h1>Job Description Manajer KDKMP</h1>
    <p class="tenang">
      Lampiran 1 PKWT No. 00000/HCMM - PKWT KDKMP/IX/2026 — {{ $jumlah }} uraian tugas:
      <b>{{ count($wajib) }} tugas harian wajib</b> ({{ number_format($menitWajib, 0, ',', '.') }} menit/hari)
      dan <b>{{ count(\App\Support\JobdescManajer::pilihan()) }} tugas pilihan</b>
      ({{ number_format($menitPilihan, 0, ',', '.') }} menit) yang dipetakan ke Business Model Canvas.
    </p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('workspace.tugas') }}">Kembali ke Tugas</a>
  </div>
</header>
@include('workspace._nav', ['mod' => 'tugas'])

<section class="blok">
  <h2>A. Tugas Harian (Daily Task) — Wajib
    <small class="pudar">{{ count($wajib) }} tugas · {{ number_format($menitWajib, 0, ',', '.') }} menit</small>
  </h2>
  <div class="jd-daftar">
    @foreach ($wajib as [$no, $nama, $bagian, $menit, $bmc, $deskripsi])
      <article class="jd-kartu jd-wajib">
        <header class="jd-kepala">
          <span class="jd-no">{{ $no }}</span>
          <h3>{{ $nama }}</h3>
          <span class="lencana kecil jd-menit">{{ $menit }} menit</span>
        </header>
        <p class="jd-desk">{{ $deskripsi }}</p>
      </article>
    @endforeach
  </div>
</section>

<section class="blok">
  <h2>B. Tugas Pilihan — Pemetaan Business Model Canvas
    <small class="pudar">{{ number_format($menitPilihan, 0, ',', '.') }} menit</small>
  </h2>
  <p class="bantuan">Tugas pilihan dijalankan sesuai kebutuhan pengembangan usaha koperasi,
    dikelompokkan menurut poin BMC.</p>
  @foreach ($pilihan as $poin => $daftar)
    <h3 class="jd-poin">{{ $poin }}
      <small class="pudar">{{ count($daftar) }} tugas</small>
    </h3>
    <div class="jd-daftar">
      @foreach ($daftar as [$no, $nama, $bagian, $menit, $bmc, $deskripsi])
        <article class="jd-kartu">
          <header class="jd-kepala">
            <span class="jd-no">{{ $no }}</span>
            <h3>{{ $nama }}</h3>
            <span class="lencana kecil jd-menit">{{ $menit }} menit</span>
          </header>
          <p class="jd-desk">{{ $deskripsi }}</p>
        </article>
      @endforeach
    </div>
  @endforeach
</section>

<section class="blok">
  <p class="bantuan">
    Sumber: <b>Lampiran 1 PKWT</b> (Job Description Manajer Koperasi Desa/Kelurahan Merah Putih).
    Estimasi waktu bersifat indikatif dan ruang lingkup tugas dapat disesuaikan Perusahaan sesuai
    kebutuhan operasional serta pengembangan usaha koperasi. Dalam hal terdapat perbedaan
    penafsiran, ketentuan dalam batang tubuh Perjanjian yang berlaku.
  </p>
</section>
@endsection

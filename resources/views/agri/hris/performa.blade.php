@extends('layouts.utama')
@section('judul', 'Performa (AgriConnect)')
@section('deskripsi', 'Tujuan, penilaian kinerja, umpan balik, dan asesmen karir dari AgriConnect HRIS.')

@section('isi')
@php
  $blok = [
    'Tujuan & Sasaran' => ['tujuan', ['name' => 'Sasaran', 'goalType' => 'Jenis',
        'startDate' => 'Mulai', 'endDate' => 'Selesai', 'status' => 'Status',
        'progress' => 'Capaian', 'weight' => 'Bobot']],
    'Penilaian Kinerja (selesai)' => ['selesai', ['period' => 'Periode', 'score' => 'Nilai',
        'grade' => 'Peringkat', 'status' => 'Status', 'evaluatorName' => 'Penilai']],
    'Penilaian Berjalan' => ['berjalan', ['period' => 'Periode', 'status' => 'Status',
        'startDate' => 'Mulai', 'endDate' => 'Selesai']],
    'Riwayat Penilaian' => ['riwayat', ['period' => 'Periode', 'score' => 'Nilai',
        'grade' => 'Peringkat', 'evaluatorName' => 'Penilai', 'createdDate' => 'Tanggal']],
    'Survei' => ['survei', ['title' => 'Survei', 'status' => 'Status', 'startDate' => 'Mulai',
        'endDate' => 'Selesai']],
    'Umpan Balik Diterima' => ['umpan', ['participantName' => 'Dari', 'type' => 'Jenis',
        'status' => 'Status', 'createdDate' => 'Tanggal']],
    'Riwayat Umpan Balik' => ['riwayat_umpan', ['participantName' => 'Dari', 'type' => 'Jenis',
        'status' => 'Status', 'createdDate' => 'Tanggal']],
    'Asesmen Karir' => ['asesmen', ['title' => 'Asesmen', 'type' => 'Jenis',
        'status' => 'Status', 'startDate' => 'Mulai', 'endDate' => 'Selesai']],
    'Evaluasi Karir' => ['evaluasi_karir', ['title' => 'Evaluasi', 'type' => 'Jenis',
        'status' => 'Status', 'createdDate' => 'Tanggal']],
  ];
  $ada = array_filter($blok, fn ($b) => ($data[$b[0]] ?? []) !== []);
  $kosong = array_keys(array_diff_key($blok, $ada));
  $jumlah = array_sum(array_map(fn ($b) => count($data[$b[0]] ?? []), $blok));
@endphp

<div class="kepala">
  <h1>Performa</h1>
  <p class="tenang">Tujuan, penilaian kinerja, umpan balik, dan asesmen karir dari AgriConnect HRIS.</p>
</div>

@include('agri.hris._nav', ['mod' => 'hris-performa'])

<div class="kisi-kartu">
  <div class="kartu angka"><span class="pudar">Total catatan</span><strong>{{ $jumlah }}</strong>
    <p class="bantuan">dari {{ count($blok) }} bagian</p></div>
  <div class="kartu angka"><span class="pudar">Bagian berisi</span><strong>{{ count($ada) }}</strong></div>
  <div class="kartu angka"><span class="pudar">Tujuan aktif</span><strong>{{ count($data['tujuan'] ?? []) }}</strong></div>
</div>

@if ($ada === [])
  <div class="blok">
    <h2>Belum ada catatan performa</h2>
    <p class="tenang">
      AgriConnect belum memuat tujuan, penilaian, umpan balik, maupun asesmen
      karir untuk akun ini. Sembilan bagian diperiksa dan semuanya kosong —
      bukan gagal dibaca.
    </p>
  </div>
@else
  @foreach ($ada as $judul => [$kunci, $kolom])
    @include('agri.hris._daftar', ['judul' => $judul, 'baris' => $data[$kunci] ?? [], 'kolom' => $kolom])
  @endforeach

  @if ($kosong !== [])
    <div class="blok">
      <h2>Bagian yang masih kosong</h2>
      <p class="bantuan">{{ implode(' · ', $kosong) }} — belum ada datanya di AgriConnect.</p>
    </div>
  @endif
@endif
@endsection

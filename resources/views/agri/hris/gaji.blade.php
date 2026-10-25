@extends('layouts.utama')
@section('judul', 'Penggajian (AgriConnect)')
@section('deskripsi', 'Slip gaji dan bukti potong pajak 1721-A1 milik sendiri, dibaca langsung dari payroll AgriConnect HRIS.')

@section('isi')
@php
  $saldoAda = collect($saldo)->contains(fn ($s) => is_array($s) && $s !== []);
@endphp

<div class="kepala">
  <h1>Penggajian</h1>
  <p class="tenang">Slip gaji dan bukti potong pajak Anda dari payroll AgriConnect.</p>
</div>

@include('agri.hris._nav', ['mod' => 'hris-gaji'])

<div class="kisi-kartu">
  <div class="kartu angka"><span class="pudar">Slip gaji</span><strong>{{ count($slip) }}</strong></div>
  <div class="kartu angka"><span class="pudar">Tahun pajak 1721-A1</span><strong>{{ count($pajak) }}</strong></div>
  @if ($saldoAda)
    <div class="kartu angka"><span class="pudar">Saldo tercatat</span>
      <strong>{{ collect($saldo)->filter(fn ($s) => is_array($s) && $s !== [])->count() }}</strong>
      <p class="bantuan">cuti / benefit</p></div>
  @endif
</div>

@if ($slip === [] && $pajak === [])
  <div class="blok">
    <h2>Belum ada slip gaji</h2>
    <p class="tenang">
      Payroll AgriConnect belum menerbitkan slip gaji maupun bukti potong 1721-A1
      untuk akun ini. Hub tidak menaksir angka gaji — yang tampil hanya yang
      benar-benar diterbitkan.
    </p>
  </div>
@else
  @if ($slip !== [])
    @include('agri.hris._daftar', [
      'judul' => 'Slip gaji',
      'baris' => $slip,
      'kolom' => ['period' => 'Periode', 'netSalary' => 'Gaji bersih',
                  'grossSalary' => 'Bruto', 'status' => 'Status'],
    ])
  @endif
  @if ($pajak !== [])
    @include('agri.hris._daftar', [
      'judul' => 'Bukti potong pajak (1721-A1)',
      'baris' => $pajak,
      'kolom' => ['taxYear' => 'Tahun pajak', 'year' => 'Tahun', 'status' => 'Status'],
    ])
  @endif
@endif

@if ($saldoAda)
  <div class="blok">
    <h2>Saldo yang memengaruhi gaji</h2>
    @foreach ($saldo as $nama => $s)
      @if (is_array($s) && $s !== [])
        @include('agri.hris._daftar', ['judul' => 'Saldo '.strtolower($nama), 'baris' => $s, 'kolom' => []])
      @endif
    @endforeach
  </div>
@endif
@endsection

@extends('layouts.utama')
@section('judul', 'BPJS & NPWP (AgriConnect)')
@section('deskripsi', 'NPWP, BPJS Kesehatan & Ketenagakerjaan, asuransi, dan rekening penggajian dari AgriConnect HRIS.')

@section('isi')
@php
  // Halaman ini TIDAK mengarang nomor. Yang belum diisi di AgriConnect
  // dinyatakan kosong apa adanya — nomor jaminan yang salah baca lebih
  // berbahaya daripada kolom yang jelas-jelas kosong.
  $peta = [
    'NPWP' => ['npwp', 'Nomor pokok wajib pajak (pajak penghasilan).'],
    'NIK (KTP)' => ['nationalId', 'Nomor induk kependudukan.'],
    'Nomor KK' => ['familyId', 'Nomor kartu keluarga.'],
    'BPJS Kesehatan' => ['bpjsKesehatanNo', 'Nomor kartu BPJS Kesehatan.'],
    'BPJS Ketenagakerjaan' => ['bpjsTkNo', 'Nomor kepesertaan BPJS Ketenagakerjaan.'],
    'Asuransi' => ['insuranceNo', 'Nomor polis asuransi tambahan.'],
    'Bank' => ['bank', 'Bank penerima gaji.'],
    'Nomor rekening' => ['accountNo', 'Rekening penerima gaji.'],
    'Nama pemilik rekening' => ['accountName', 'Nama pada rekening.'],
    'Cabang' => ['branchName', 'Cabang bank penerima gaji.'],
  ];
  $terisi = array_filter($peta, fn ($m) => filled($diri[$m[0]] ?? null));
  $saldoAda = collect($saldo)->contains(fn ($s) => is_array($s) && $s !== []);
@endphp

<div class="kepala">
  <h1>BPJS &amp; NPWP</h1>
  <p class="tenang">Data jaminan, pajak, dan rekening penggajian dari AgriConnect HRIS.</p>
</div>

@include('agri.hris._nav', ['mod' => 'hris-bpjs'])

<div class="kisi-kartu">
  <div class="kartu angka"><span class="pudar">Nomor terisi</span><strong>{{ count($terisi) }}</strong>
    <p class="bantuan">dari {{ count($peta) }} kolom administrasi</p></div>
  <div class="kartu angka"><span class="pudar">BPJS Kesehatan</span>
    <strong class="panjang">{{ filled($diri['bpjsKesehatanNo'] ?? null) ? 'Terdaftar' : 'Belum ada' }}</strong></div>
  <div class="kartu angka"><span class="pudar">Rekening gaji</span>
    <strong class="panjang">{{ filled($diri['accountNo'] ?? null) ? ($diri['bank'] ?? 'Terdaftar') : 'Belum ada' }}</strong></div>
</div>

<div class="blok">
  <div class="blok-kepala">
    <h2>Jaminan, pajak &amp; rekening</h2>
    <button class="tombol samar mini" type="button" data-buka="modal-bpjs">Ubah</button>
  </div>
  <dl class="rincian">
    @foreach ($peta as $label => [$kunci, $keterangan])
      @php $nilai = $diri[$kunci] ?? null; @endphp
      <dt title="{{ $keterangan }}">{{ $label }}</dt>
      <dd>
        @if (filled($nilai))
          <b>{{ $nilai }}</b>
        @else
          <span class="pudar">Belum diisi di AgriConnect</span>
        @endif
      </dd>
    @endforeach
  </dl>
  <p class="bantuan">
    Kosong di sini berarti datanya memang belum ada di akun AgriConnect Anda —
    bukan gagal dibaca. NPWP, BPJS Kesehatan, dan rekening gaji bisa Anda ubah
    lewat tombol <b>Ubah</b> (terkirim sebagai pengajuan ke AgriConnect, berlaku
    setelah disetujui SDM). NIK, nomor KK, BPJS Ketenagakerjaan, dan nomor polis
    asuransi diisi SDM.
  </p>
</div>

<dialog class="modal" id="modal-bpjs" aria-labelledby="modal-bpjs-judul">
  <form method="post" action="{{ route('agri.hris.profil.biodata') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="modal-bpjs-judul">Ubah NPWP, BPJS &amp; Rekening</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <div class="grid-form">
        <label>NPWP
          <input name="npwp" maxlength="32" value="{{ old('npwp', $diri['npwp'] ?? '') }}"
                 placeholder="00.000.000.0-000.000"></label>
        <label>BPJS Kesehatan
          <input name="bpjsKesehatanNo" maxlength="30" inputmode="numeric"
                 value="{{ old('bpjsKesehatanNo', $diri['bpjsKesehatanNo'] ?? '') }}"
                 placeholder="Nomor kartu BPJS Kesehatan"></label>
      </div>
      <div class="grid-form">
        <label>Bank penerima gaji
          <input name="bank" maxlength="60" value="{{ old('bank', $diri['bank'] ?? '') }}" placeholder="Contoh: bni"></label>
        <label>Cabang
          <input name="branchName" maxlength="60" value="{{ old('branchName', $diri['branchName'] ?? '') }}"></label>
      </div>
      <div class="grid-form">
        <label>Nomor rekening
          <input name="accountNo" maxlength="40" inputmode="numeric" value="{{ old('accountNo', $diri['accountNo'] ?? '') }}"></label>
        <label>Nama pemilik rekening
          <input name="accountName" maxlength="120" value="{{ old('accountName', $diri['accountName'] ?? '') }}"></label>
      </div>
      <label>Keterangan perubahan <span class="bantuan">(opsional, dibaca SDM)</span>
        <input name="remark" maxlength="255" placeholder="Contoh: pembaruan nomor rekening gaji" value="{{ old('remark') }}"></label>
      <p class="bantuan">Terkirim sebagai pengajuan ke AgriConnect. Nomor jaminan
        dan pajak yang salah lebih berbahaya daripada kolom kosong — periksa
        sekali lagi sebelum mengirim.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Kirim ke AgriConnect</button>
    </footer>
  </form>
</dialog>

@if ($saldoAda)
  <div class="blok">
    <h2>Saldo cuti &amp; benefit</h2>
    @foreach ($saldo as $nama => $s)
      @include('agri.hris._daftar', ['judul' => 'Saldo '.$nama, 'baris' => $s, 'kolom' => []])
    @endforeach
  </div>
@endif
@endsection

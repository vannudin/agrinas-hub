@extends('layouts.utama')
@section('judul', 'Data Keluarga (AgriConnect)')
@section('deskripsi', 'Anggota keluarga, status perkawinan, dan kontak darurat dari AgriConnect HRIS.')

@section('isi')
<div class="kepala">
  <h1>Data Keluarga</h1>
  <p class="tenang">Dibaca langsung dari AgriConnect HRIS. Tidak ada data yang disimpan di Hub.</p>
</div>

@include('agri.hris._nav', ['mod' => 'hris-keluarga'])

@php
  $darurat = array_values(array_filter($anggota, fn ($a) => ($a['emergencyContact'] ?? false) === true));
  $kontak = array_filter([
      'Telepon utama' => $diri['mainPhone'] ?? null,
      'Telepon lain' => $diri['otherPhone'] ?? null,
      'Email kantor' => $diri['officeEmail'] ?? null,
      'Email pribadi' => $diri['personalMail'] ?? null,
      'Tanggal perkawinan' => $diri['maritalDate'] ?? null,
  ], fn ($v) => filled($v));

  // Tanggal dari AgriConnect datang sebagai "08 Mar 1997" untuk anggota
  // keluarga, tetapi <input type="date"> hanya menerima yyyy-MM-dd.
  $iso = fn ($v) => filled($v) && strtotime((string) $v) ? date('Y-m-d', strtotime((string) $v)) : '';

  // Satu dialog melayani Tambah & Ubah, jadi tiap tombol membawa seluruh
  // isian barisnya lewat `data-awal` (dibaca hub.js). `id` kosong = Tambah.
  $awal = fn (array $a = []) => json_encode([
      'id' => $a['id'] ?? '',
      'name' => $a['name'] ?? '',
      'relationship' => $a['relationship'] ?? '',
      'emergencyContact' => ($a['emergencyContact'] ?? false) ? '1' : '0',
      'decease' => ($a['decease'] ?? false) ? '1' : '0',
      'phoneNo' => $a['phoneNo'] ?? '',
      'gender' => $a['gender'] ?? '',
      'birthPlace' => $a['birthPlace'] ?? '',
      'birthDate' => $iso($a['birthDate'] ?? null),
      'maritalStatus' => $a['maritalStatus'] ?? '',
      'maritalDate' => $iso($a['maritalDate'] ?? null),
      'nationalIdentifier' => $a['nationalIdentifier'] ?? '',
      'email' => $a['email'] ?? '',
      'bpjsKesehatanNo' => $a['bpjsKesehatanNo'] ?? '',
      'faskes' => $a['faskes'] ?? '',
      'address' => $a['address'] ?? '',
  ], JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);

  $relasi = ['Spouse' => 'Suami/Istri', 'Child' => 'Anak', 'Parent' => 'Orang tua', 'Sibling' => 'Saudara'];
  $kawin = ['Belum Menikah', 'Menikah', 'Cerai hidup', 'Cerai mati'];
  // Daftar lazim di atas TIDAK cukup: kalau baris lama memakai nilai lain,
  // <select> akan diam-diam menampilkan opsi pertama dan menimpa datanya.
  foreach ($anggota as $a) {
      $r = $a['relationship'] ?? null;
      if (filled($r) && ! isset($relasi[$r])) { $relasi[$r] = $r; }
      $s = $a['maritalStatus'] ?? null;
      if (filled($s) && ! in_array($s, $kawin, true)) { $kawin[] = $s; }
  }
@endphp

<div class="kisi-kartu">
  <div class="kartu angka"><span class="pudar">Anggota terdaftar</span><strong>{{ count($anggota) }}</strong></div>
  <div class="kartu angka"><span class="pudar">Kontak darurat</span><strong>{{ count($darurat) }}</strong></div>
  <div class="kartu angka"><span class="pudar">Status perkawinan</span>
    <strong class="panjang">{{ $diri['status'] ?? '—' }}</strong></div>
</div>

@if ($darurat !== [])
  <div class="blok">
    <h2>Kontak darurat</h2>
    <p class="bantuan">Yang dihubungi perusahaan bila terjadi sesuatu pada Anda.</p>
    <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Nama</th><th>Hubungan</th><th>Telepon</th><th>Alamat</th></tr></thead>
        <tbody>
          @foreach ($darurat as $a)
            <tr>
              <td data-l="Nama"><b>{{ $a['name'] ?? '—' }}</b></td>
              <td data-l="Hubungan">{{ $a['relationship'] ?? '—' }}</td>
              <td data-l="Telepon">{{ $a['phoneNo'] ?? '—' }}</td>
              <td data-l="Alamat">{{ $a['address'] ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

<div class="blok">
  <div class="blok-kepala">
    <h2>Anggota keluarga</h2>
    <button class="tombol mini" type="button" data-buka="modal-keluarga"
            data-judul-dialog="Tambah Anggota Keluarga" data-awal="{{ $awal() }}">Tambah</button>
  </div>
  @if ($anggota === [])
    <p class="tenang">Belum ada anggota keluarga yang terdaftar di AgriConnect.</p>
  @else
    <div class="tabel-bungkus">
      <table>
        <thead>
          <tr><th>Nama</th><th>Hubungan</th><th>Tanggal lahir</th><th>Telepon</th><th>Status</th></tr>
        </thead>
        <tbody>
          @foreach ($anggota as $a)
            <tr>
              <td data-l="Nama">
                <button class="tautan-mini" type="button" data-buka="modal-keluarga"
                        data-judul-dialog="Ubah Data Keluarga"
                        data-awal="{{ $awal($a) }}">{{ $a['name'] ?? '—' }}</button>
                @if (filled($a['nationalIdentifier'] ?? null))
                  <small>NIK {{ $a['nationalIdentifier'] }}</small>
                @endif</td>
              <td data-l="Hubungan">{{ $a['relationship'] ?? '—' }}</td>
              <td data-l="Tanggal lahir">{{ $a['birthDate'] ?? '—' }}</td>
              <td data-l="Telepon">{{ $a['phoneNo'] ?? '—' }}</td>
              <td data-l="Status">
                @if (($a['emergencyContact'] ?? false) === true)
                  <span class="lencana s-SELESAI">kontak darurat</span>
                @elseif (($a['decease'] ?? false) === true)
                  <span class="lencana s-BARU">wafat</span>
                @else
                  <span class="pudar">tanggungan</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

@if ($kontak !== [])
  <div class="blok">
    <h2>Kontak Anda</h2>
    <dl class="rincian">
      @foreach ($kontak as $label => $nilai)
        <dt>{{ $label }}</dt><dd>{{ $nilai }}</dd>
      @endforeach
    </dl>
  </div>
@endif

<dialog class="modal" id="modal-keluarga" aria-labelledby="modal-keluarga-judul">
  <form method="post" action="{{ route('agri.hris.keluarga.simpan') }}">
    @csrf
    <input type="hidden" name="id" value="{{ old('id') }}">
    <header class="modal-kepala">
      <h2 id="modal-keluarga-judul">Tambah Anggota Keluarga</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <div class="grid-form">
        <label>Hubungan
          <select name="relationship" required>
            <option value="">— pilih —</option>
            @foreach ($relasi as $k => $nama)
              <option value="{{ $k }}" @selected(old('relationship') === $k)>{{ $nama }}</option>
            @endforeach
          </select></label>
        <label>Nama lengkap
          <input name="name" maxlength="120" required value="{{ old('name') }}"></label>
      </div>

      <div class="grid-form">
        <label>Jenis kelamin
          <select name="gender">
            <option value="">—</option>
            <option value="Male" @selected(old('gender') === 'Male')>Laki-laki</option>
            <option value="Female" @selected(old('gender') === 'Female')>Perempuan</option>
          </select></label>
        <label>Kontak darurat
          <select name="emergencyContact">
            <option value="0" @selected(old('emergencyContact', '0') === '0')>Tidak</option>
            <option value="1" @selected(old('emergencyContact') === '1')>Ya — dihubungi bila terjadi sesuatu</option>
          </select></label>
      </div>

      <div class="grid-form">
        <label>Tempat lahir
          <input name="birthPlace" maxlength="80" value="{{ old('birthPlace') }}"></label>
        <label>Tanggal lahir
          <input type="date" name="birthDate" value="{{ old('birthDate') }}"></label>
      </div>

      <div class="grid-form">
        <label>Status perkawinan
          <select name="maritalStatus">
            <option value="">—</option>
            @foreach ($kawin as $s)
              <option value="{{ $s }}" @selected(old('maritalStatus') === $s)>{{ $s }}</option>
            @endforeach
          </select></label>
        <label>Tanggal perkawinan
          <input type="date" name="maritalDate" value="{{ old('maritalDate') }}"></label>
      </div>

      <div class="grid-form">
        <label>NIK
          <input name="nationalIdentifier" maxlength="32" inputmode="numeric" value="{{ old('nationalIdentifier') }}"></label>
        <label>Telepon
          <input name="phoneNo" maxlength="30" inputmode="tel" value="{{ old('phoneNo') }}"></label>
      </div>

      <div class="grid-form">
        <label>Email
          <input type="email" name="email" maxlength="120" value="{{ old('email') }}"></label>
        <label>BPJS Kesehatan anggota
          <input name="bpjsKesehatanNo" maxlength="30" inputmode="numeric" value="{{ old('bpjsKesehatanNo') }}"></label>
      </div>

      <div class="grid-form">
        <label>Faskes
          <input name="faskes" maxlength="60" value="{{ old('faskes') }}" placeholder="Contoh: Faskes003"></label>
        <label>Status hidup
          <select name="decease">
            <option value="0" @selected(old('decease', '0') === '0')>Hidup</option>
            <option value="1" @selected(old('decease') === '1')>Wafat</option>
          </select></label>
      </div>

      <label>Alamat
        <textarea name="address" rows="2" maxlength="255">{{ old('address') }}</textarea></label>

      <label>Keterangan perubahan <span class="bantuan">(opsional, dibaca SDM)</span>
        <input name="remark" maxlength="255" placeholder="Contoh: penambahan anak kedua" value="{{ old('remark') }}"></label>

      <p class="bantuan">Terkirim sebagai pengajuan ke AgriConnect; data keluarga
        berubah setelah disetujui SDM.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Kirim ke AgriConnect</button>
    </footer>
  </form>
</dialog>
@endsection

@extends('layouts.utama')
@section('judul', 'Profil Pegawai (AgriConnect)')
@section('deskripsi', 'Identitas, kepegawaian, pendidikan, dan dokumen pegawai — dibaca langsung dari AgriConnect HRIS.')

@section('isi')
@php
  $d = $data['diri'] ?? [];
  $k = $data['kerja'] ?? [];
  // Nomor pegawai HANYA ada di /api/user/profile (bukan personal/employment).
  $p = $data['profil'] ?? [];

  // Data tunggal disajikan sebagai pasangan label → nilai (dl.rincian), bukan
  // tabel ber-<th> di dalam tbody: di tabel, label bersaing lebar dengan nilai
  // dan terbaca sebagai kolom yang "nempel" (keluhan Bos 2026-10-22).
  // Nilai enum dari API berbahasa Inggris ("Male", "Married"); yang dibaca
  // pegawai Indonesianya. Nilai yang tidak dikenal ditampilkan apa adanya.
  $indo = ['Male' => 'Laki-laki', 'Female' => 'Perempuan',
      'Single' => 'Belum menikah', 'Married' => 'Menikah',
      'Divorced' => 'Cerai hidup', 'Widow' => 'Cerai mati', 'Widower' => 'Cerai mati',
      'Widowed' => 'Cerai mati'];
  $terj = fn ($v) => $v === null ? null : ($indo[$v] ?? $v);

  $identitas = array_filter([
      'Nama lengkap' => $d['fullName'] ?? ($p['fullName'] ?? null),
      'Nama panggilan' => $d['nickname'] ?? null,
      'Jenis kelamin' => $terj($d['gender'] ?? null),
      'Agama' => $d['religion'] ?? null,
      'Status perkawinan' => $terj($d['status'] ?? null),
      'Golongan darah' => $d['bloodType'] ?? null,
      'Tempat, tanggal lahir' => trim(($d['birthPlace'] ?? '').', '.($d['birthDate'] ?? ''), ', '),
      'Pendidikan terakhir' => $d['lastEducation'] ?? null,
      'Telepon' => $d['mainPhone'] ?? null,
      'Email kantor' => $d['officeEmail'] ?? null,
      'Email pribadi' => $d['personalMail'] ?? null,
  ], fn ($v) => filled($v));

  $kepegawaian = array_filter([
      'Nomor pegawai' => $p['employeeNo'] ?? ($k['employeeNo'] ?? null),
      'Jabatan' => $k['positionName'] ?? null,
      'Jenjang jabatan' => $k['positionLevelName'] ?? null,
      'Unit / organisasi' => $k['organizationName'] ?? null,
      'Perusahaan' => $k['companyName'] ?? null,
      'Golongan' => $k['gradeName'] ?? null,
      'Jenis kontrak' => $k['type'] ?? null,
      'Mulai kontrak' => $k['periodStart'] ?? null,
      'Akhir kontrak' => $k['periodEnd'] ?? null,
      'Tanggal masuk' => $k['hireDate'] ?? null,
      'Masa kerja' => $k['lengthOfService'] ?? null,
      'Atasan' => $k['managerName'] ?? null,
      'Lokasi kerja' => $k['locationName'] ?? null,
  ], fn ($v) => filled($v));

  $namaSosmed = ['FACEBOOK' => 'Facebook', 'INSTAGRAM' => 'Instagram', 'LINKEDIN' => 'LinkedIn'];
@endphp

<div class="kepala">
  <h1>Profil Pegawai</h1>
  <p class="tenang">Dibaca langsung dari AgriConnect HRIS. Tidak ada data yang disalin ke Hub.</p>
</div>

@include('agri.hris._nav', ['mod' => 'hris-profil'])

<div class="kisi-kartu">
  <div class="kartu angka"><span class="pudar">Nomor pegawai</span>
    <strong class="panjang">{{ $kepegawaian['Nomor pegawai'] ?? '—' }}</strong></div>
  <div class="kartu angka"><span class="pudar">Jabatan</span>
    <strong class="panjang">{{ $kepegawaian['Jabatan'] ?? '—' }}</strong></div>
  <div class="kartu angka"><span class="pudar">Unit</span>
    <strong class="panjang">{{ $kepegawaian['Unit / organisasi'] ?? '—' }}</strong></div>
</div>

<div class="blok">
  <div class="blok-kepala">
    <h2>Identitas</h2>
    <button class="tombol samar mini" type="button" data-buka="modal-biodata">Ubah</button>
  </div>
  @if ($identitas === [])
    <p class="tenang">Data identitas belum diisi di AgriConnect.</p>
  @else
    <dl class="rincian">
      @foreach ($identitas as $label => $nilai)
        <dt>{{ $label }}</dt><dd>{{ $nilai }}</dd>
      @endforeach
    </dl>
  @endif
</div>

<div class="blok">
  <h2>Kepegawaian</h2>
  @if ($kepegawaian === [])
    <p class="tenang">Data kepegawaian belum tersedia pada akun ini.</p>
  @else
    <dl class="rincian">
      @foreach ($kepegawaian as $label => $nilai)
        <dt>{{ $label }}</dt><dd>{{ $nilai }}</dd>
      @endforeach
    </dl>
  @endif
</div>

{{-- Dua jalur tulis ke AgriConnect, dan bedanya penting bagi pegawai:
     media sosial tersimpan LANGSUNG (POST /api/user/my-profile/sosmed),
     sedangkan biodata & keluarga berbentuk PENGAJUAN ber-approval
     (POST /api/user/self-service/biodata|family) — baru berlaku setelah SDM
     menyetujui. Karena itu biodata punya keterangan "menunggu persetujuan". --}}
<div class="blok">
  <div class="blok-kepala">
    <h2>Sosial media</h2>
    <button class="tombol samar mini" type="button" data-buka="modal-sosmed">Ubah</button>
  </div>
  <dl class="rincian">
    @foreach ($namaSosmed as $tipe => $label)
      <dt>{{ $label }}</dt>
      <dd>
        @if (filled($sosmed[$tipe]['path'] ?? null))
          <a href="{{ $sosmed[$tipe]['path'] }}" target="_blank" rel="noopener">{{ $sosmed[$tipe]['path'] }}</a>
        @else
          <span class="pudar">Belum diisi</span>
        @endif
      </dd>
    @endforeach
  </dl>
  <p class="bantuan">Tersimpan langsung ke AgriConnect, tanpa menunggu persetujuan.</p>
</div>

<dialog class="modal" id="modal-sosmed" aria-labelledby="modal-sosmed-judul">
  <form method="post" action="{{ route('agri.hris.profil.sosmed') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="modal-sosmed-judul">Ubah Sosial Media</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      @foreach ($namaSosmed as $tipe => $label)
        <label>{{ $label }}
          <input name="{{ strtolower($tipe) }}" maxlength="180"
                 value="{{ old(strtolower($tipe), $sosmed[$tipe]['path'] ?? '') }}"
                 placeholder="https://{{ strtolower($label) }}.com/nama-anda">
          @error(strtolower($tipe))<span class="pesan rusak">{{ $message }}</span>@enderror
        </label>
      @endforeach
      <p class="bantuan">Kosongkan kolom untuk menghapus tautannya.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Simpan ke AgriConnect</button>
    </footer>
  </form>
</dialog>

<dialog class="modal" id="modal-biodata" aria-labelledby="modal-biodata-judul">
  <form method="post" action="{{ route('agri.hris.profil.biodata') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="modal-biodata-judul">Ubah Biodata</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      {{-- Opsi status memakai nilai yang BENAR-BENAR ada di AgriConnect
           ("Menikah", bukan "Married"); nilai lama yang tak ada di daftar
           ditambahkan supaya tidak diam-diam berubah jadi opsi pertama. --}}
      @php
        $statusKawin = ['Belum Menikah', 'Menikah', 'Cerai hidup', 'Cerai mati'];
        $sekarang = $d['status'] ?? null;
        if (filled($sekarang) && ! in_array($sekarang, $statusKawin, true)) { $statusKawin[] = $sekarang; }
        $iso = fn ($v) => filled($v) && strtotime((string) $v) ? date('Y-m-d', strtotime((string) $v)) : '';
      @endphp

      <div class="grid-form">
        <label>Status perkawinan
          <select name="status">
            @foreach ($statusKawin as $s)
              <option value="{{ $s }}" @selected(old('status', $sekarang) === $s)>{{ $s }}</option>
            @endforeach
          </select></label>
        <label>Tanggal perkawinan <span class="bantuan">(kosongkan bila belum)</span>
          <input type="date" name="maritalDate" value="{{ old('maritalDate', $iso($d['maritalDate'] ?? null)) }}"></label>
      </div>

      <div class="grid-form">
        <label>Tempat lahir
          <input name="birthPlace" maxlength="80" value="{{ old('birthPlace', $d['birthPlace'] ?? '') }}"></label>
        <label>Tanggal lahir
          <input type="date" name="birthDate" value="{{ old('birthDate', $iso($d['birthDate'] ?? null)) }}"></label>
      </div>

      <div class="grid-form">
        <label>Golongan darah
          <select name="bloodType">
            @foreach (['', 'A', 'B', 'AB', 'O'] as $g)
              <option value="{{ $g }}" @selected(old('bloodType', $d['bloodType'] ?? '') === $g)>{{ $g === '' ? '—' : $g }}</option>
            @endforeach
          </select></label>
        <label>Nomor HP
          <input name="mainPhone" maxlength="30" inputmode="tel" value="{{ old('mainPhone', $d['mainPhone'] ?? '') }}"></label>
      </div>

      <div class="grid-form">
        <label>HP lain <span class="bantuan">(opsional)</span>
          <input name="otherPhone" maxlength="30" inputmode="tel" value="{{ old('otherPhone', $d['otherPhone'] ?? '') }}"></label>
        <label>Email pribadi
          <input type="email" name="personalMail" maxlength="120" value="{{ old('personalMail', $d['personalMail'] ?? '') }}"></label>
      </div>

      <div class="grid-form">
        <label>NIK (KTP)
          <input name="nationalId" maxlength="32" inputmode="numeric" value="{{ old('nationalId', $d['nationalId'] ?? '') }}"></label>
        <label>Nomor KK
          <input name="familyId" maxlength="32" inputmode="numeric" value="{{ old('familyId', $d['familyId'] ?? '') }}"></label>
      </div>

      <label>Keterangan perubahan <span class="bantuan">(opsional, dibaca SDM)</span>
        <input name="remark" maxlength="255" placeholder="Contoh: perbaikan tanggal lahir" value="{{ old('remark') }}"></label>

      <p class="bantuan">Terkirim sebagai pengajuan ke AgriConnect. Data berubah setelah
        disetujui SDM — bukan langsung. NPWP, BPJS, dan rekening ada di
        <a href="{{ route('agri.hris.bpjs') }}">BPJS &amp; NPWP</a>.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Kirim ke AgriConnect</button>
    </footer>
  </form>
</dialog>

@include('agri.hris._daftar', [
  'judul' => 'Alamat',
  'baris' => $data['alamat'] ?? [],
  'kolom' => ['type' => 'Jenis', 'address' => 'Alamat', 'district' => 'Kecamatan',
              'subDistrict' => 'Desa/Kel', 'city' => 'Kota', 'province' => 'Provinsi',
              'postalCode' => 'Kode pos'],
])

@include('agri.hris._daftar', [
  'judul' => 'Pendidikan',
  'baris' => $data['pendidikan'] ?? [],
  'kolom' => ['degree' => 'Jenjang', 'major' => 'Jurusan', 'school' => 'Institusi',
              'city' => 'Kota', 'yearJoined' => 'Masuk', 'yearGraduated' => 'Lulus', 'gpa' => 'IPK'],
])

@include('agri.hris._daftar', [
  'judul' => 'Pengalaman Kerja',
  'baris' => $data['pengalaman'] ?? [],
  'kolom' => ['companyName' => 'Perusahaan', 'positionName' => 'Jabatan',
              'startDate' => 'Mulai', 'endDate' => 'Selesai', 'description' => 'Keterangan'],
])

@include('agri.hris._daftar', [
  'judul' => 'Kompetensi',
  'baris' => $data['kompetensi'] ?? [],
  'kolom' => ['name' => 'Kompetensi', 'level' => 'Tingkat', 'description' => 'Keterangan'],
])

@include('agri.hris._daftar', [
  'judul' => 'Dokumen',
  'baris' => $data['berkas'] ?? [],
  'kolom' => ['type' => 'Jenis', 'name' => 'Nama', 'documentNo' => 'Nomor',
              'startDate' => 'Mulai', 'endDate' => 'Berakhir'],
])

@include('agri.hris._daftar', [
  'judul' => 'Sertifikat',
  'baris' => $data['sertifikat'] ?? [],
  'kolom' => ['name' => 'Nama', 'publisher' => 'Penerbit', 'startDate' => 'Mulai',
              'endDate' => 'Berakhir', 'certificateNo' => 'Nomor'],
])

<div class="blok">
  <h2>Halaman terkait</h2>
  <p class="bantuan">
    Anggota keluarga &amp; kontak darurat ada di <a href="{{ route('agri.hris.keluarga') }}">Data Keluarga</a>;
    NPWP, BPJS, dan rekening penggajian ada di <a href="{{ route('agri.hris.bpjs') }}">BPJS &amp; NPWP</a>.
  </p>
</div>
@endsection

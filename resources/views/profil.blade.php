@extends('layouts.utama')

@section('judul', 'Profil')
@section('isi')
@php
  $u = auth()->user();
  $inisial = mb_strtoupper(mb_substr(trim($u->nama), 0, 1));
  $peran = \App\Models\Domain::LABEL_PERAN[$u->peran] ?? 'Manajer Koperasi';
  $kerja = (array) ($mentah['work_experiences'] ?? []);
  $didik = (array) ($mentah['educations'] ?? []);
  $bank = (array) ($mentah['account_banks'] ?? []);
  $dokumen = (array) ($mentah['documents'] ?? []);
@endphp

{{-- Kartu identitas di depan: dulu halaman ini langsung membuka dengan tabel
     dua kolom "Nama/Email/Peran" yang tinggi dan banyak ruang kosong. --}}
<section class="profil-hero">
  <span class="profil-avatar" aria-hidden="true">{{ $inisial }}</span>
  <div class="identitas">
    <h2>{{ $u->nama }}</h2>
    <p>{{ $u->email }}</p>
    <div class="profil-lencana">
      <span class="lencana">{{ $peran }}</span>
      @if ($u->pic() && $u->wilayah)
        <span class="lencana">{{ \App\Support\Wilayah::label($u->wilayah) }}</span>
      @endif
      <span class="lencana {{ $u->aktif ? 's-SELESAI' : 's-REVISI' }}">{{ $u->aktif ? 'Aktif' : 'Nonaktif' }}</span>
      @if ($u->sandi_sumber === 'SIMKOPDES')
        <span class="lencana s-DIAJUKAN">Sandi mengikuti SIMKOPDES</span>
      @endif
    </div>
  </div>
  {{-- Tombol "Tarik ulang dari SIMKOPDES" dibuang 2026-10-24: Hub berdiri
       sendiri, panggilan ke SIMKOPDES dari server selalu ditolak (captcha/403)
       sehingga tombol itu hanya menghasilkan pesan gagal. --}}
</section>

{{-- Identitas diisi pemiliknya sendiri. Dulu blok ini hanya baca: 33 ribu
     akun pendamping tak punya salinan SIMKOPDES, jadi tak punya jalan mengisi
     apa pun. Kolom yang mengubah hak akses (peran, koperasi) sengaja absen. --}}
<section class="blok">
  <h2>Identitas</h2>
  <p class="bantuan">Isian ini dipakai meja HR &amp; payroll. Tanggal lahir di sini hanya untuk
    tampilan — kunci aktivasi akun tetap yang tersimpan saat aktivasi.</p>
  @if ($profil)
    @if ($profil->peran_simkopdes || $profil->tempat_lahir)
      <p class="bantuan">
        @if ($profil->peran_simkopdes) Peran di SIMKOPDES: <b>{{ $profil->peran_simkopdes }}</b>.@endif
        @if ($profil->tempat_lahir) Tercatat: {{ trim($profil->tempat_lahir.($profil->tanggal_lahir ? ', '.tanggal_id($profil->tanggal_lahir) : ''), ', ') }}.@endif
      </p>
    @endif
    <p class="bantuan">Salinan terakhir ditarik {{ $profil->akhir_tarik ? waktu_id($profil->akhir_tarik) : '—' }} dari SIMKOPDES.@if ($profil->terverifikasi) Email {{ strtolower($profil->terverifikasi) }} terverifikasi.@endif</p>
  @endif
  <form method="post" action="{{ route('profil.simpan') }}" class="kartu grid-form">
    @csrf
    @php
      $isian = [
        'nama' => ['Nama lengkap', 'text', true],
        'niak' => ['NIK', 'text', false],
        'telepon' => ['Telepon', 'tel', false],
        'tempat_lahir' => ['Tempat lahir', 'text', false],
        'tanggal_lahir' => ['Tanggal lahir', 'date', false],
        'gender' => ['Jenis kelamin', 'text', false],
        'agama' => ['Agama', 'text', false],
        'status_kawin' => ['Status kawin', 'text', false],
        'golongan_darah' => ['Golongan darah', 'text', false],
        'alamat' => ['Alamat', 'text', false],
        'kode_pos' => ['Kode pos', 'text', false],
        'desa' => ['Desa', 'text', false],
        'kecamatan' => ['Kecamatan', 'text', false],
        'kabupaten' => ['Kabupaten', 'text', false],
        'provinsi' => ['Provinsi', 'text', false],
      ];
    @endphp
    @foreach ($isian as $kolom => [$label, $tipe, $wajib])
      @php
        $nilai = $kolom === 'nama' ? $u->nama : ($profil->{$kolom} ?? '');
        $nilai = old($kolom, $nilai);
      @endphp
      <label for="p-{{ $kolom }}">{{ $label }}
        <input id="p-{{ $kolom }}" name="{{ $kolom }}" type="{{ $tipe }}"
          value="{{ $nilai }}" @if ($wajib) required @endif
          @if ($kolom === 'tanggal_lahir') max="{{ now()->toDateString() }}" @endif>
      </label>
    @endforeach
    <div class="kolom-aksi">
      <button class="tombol utama" type="submit">Simpan profil</button>
    </div>
  </form>
</section>

@if ($koperasi->isNotEmpty())
<section class="blok">
  <h2>Koperasi</h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Kode</th><th>Koperasi</th><th>Wilayah</th><th>Alamat</th><th>Status</th></tr></thead>
    <tbody>
    @foreach ($koperasi as $k)
      <tr>
        <td data-l="Kode"><code>{{ $k->kode }}</code></td>
        <td data-l="Koperasi"><span class="judul-sel">{{ $k->nama }}</span></td>
        <td data-l="Wilayah">{{ $k->wilayah ?: '—' }}</td>
        <td data-l="Alamat">{{ $k->alamat ?: '—' }}</td>
        <td data-l="Status">{{ $k->status === 'AKTIF' ? 'Aktif' : $k->status }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
  </div>
</section>
@endif

@if ($kerja !== [] || $didik !== [] || $bank !== [])
<section class="blok">
  <h2>Riwayat &amp; rekening</h2>
  <div class="identitas-grid">
    @foreach ($kerja as $w)
      <div>
        <small>Pengalaman kerja</small>
        <b>{{ $w['position'] ?? '—' }}</b>
        <span class="pudar">{{ $w['institution_name'] ?? '' }}
          @if (! empty($w['start_employment']))
            · {{ tanggal_id($w['start_employment'], 'M Y') }}–{{ $w['end_employment'] ? tanggal_id($w['end_employment'], 'M Y') : 'kini' }}
          @endif
        </span>
      </div>
    @endforeach
    @foreach ($didik as $d)
      <div>
        <small>Pendidikan</small>
        <b>{{ $d['education_level'] ?? '—' }} {{ $d['education_major'] ?? '' }}</b>
        <span class="pudar">{{ $d['education_institution'] ?? '' }}
          @if (! empty($d['graduation_year'])) · lulus {{ $d['graduation_year'] }}@endif
        </span>
      </div>
    @endforeach
    @foreach ($bank as $b)
      <div class="rekening-kartu">
        <small>Rekening</small>
        <b>{{ $b['account_number'] ?? '—' }}</b>
        <span class="pudar">{{ $b['bank_name'] ?? '' }}@if (! empty($b['account_name'])) · {{ $b['account_name'] }}@endif
          @if (! empty($b['branch_name'])) · {{ $b['branch_name'] }}@endif</span>
      </div>
    @endforeach
  </div>
  @if ($dokumen !== [])
    <p class="bantuan">{{ count($dokumen) }} dokumen legal koperasi tersimpan di SIMKOPDES.</p>
  @endif
</section>
@endif

<section class="blok sempit" id="sandi">
  <h2>Ganti sandi</h2>
  <form method="post" action="{{ route('profil.sandi') }}" class="kartu">
    @csrf
    <label for="lama">Sandi lama
      <input id="lama" name="lama" type="password" required autocomplete="current-password">
    </label>
    <label for="baru">Sandi baru
      <input id="baru" name="baru" type="password" required minlength="10" autocomplete="new-password">
      <small class="pudar">Minimal 10 karakter.</small>
    </label>
    <label for="ulang">Ulangi sandi baru
      <input id="ulang" name="ulang" type="password" required minlength="10" autocomplete="new-password">
    </label>
    <button class="tombol utama lebar" type="submit">Simpan sandi baru</button>
  </form>
  @if ($u->sandi_sumber === 'SIMKOPDES')
    <p class="bantuan">Sandi halaman ini masih mengikuti sandi SIMKOPDES Anda. Menggantinya di
      sini membuat Hub memakai sandi sendiri, jadi SIMKOPDES tidak perlu dihubungi lagi saat masuk.</p>
  @endif
</section>
@endsection

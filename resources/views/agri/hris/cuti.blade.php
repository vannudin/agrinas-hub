@extends('layouts.utama')
@section('judul', 'Cuti & Izin (AgriConnect)')
@section('deskripsi', 'Pengajuan cuti, izin, dan sakit milik sendiri beserta status persetujuannya dari AgriConnect HRIS.')

@section('isi')
<div class="kepala">
  <h1>Cuti &amp; Izin</h1>
  <p class="tenang">Pengajuan cuti, izin, dan sakit Anda — dibaca langsung dari AgriConnect HRIS.</p>
</div>

@include('agri.hris._nav', ['mod' => 'hris-cuti'])

@php
  // Rekap diturunkan dari baris, bukan angka terpisah yang bisa beda dari daftarnya.
  $hitung = fn (string $kata) => count(array_filter($baris, fn ($b) => in_array($kata,
      array_filter([$b['status'] ?? null, $b['approvalStatus'] ?? null, $b['requestStatus'] ?? null]), true)));
@endphp

<div class="kisi-kartu">
  <div class="kartu angka"><span class="pudar">Total pengajuan</span><strong>{{ count($baris) }}</strong></div>
  @foreach ($jenis as $kode => $label)
    <div class="kartu angka">
      <span class="pudar">{{ $label }}</span>
      <strong>{{ count(array_filter($baris, fn ($b) => ($b['_jenis'] ?? '') === $label)) }}</strong>
    </div>
  @endforeach
</div>

<div class="blok">
  <h2>Riwayat pengajuan</h2>
  <p class="bantuan">
    Halaman ini hanya membaca. Mengajukan cuti/izin/sakit baru tetap lewat
    aplikasi AgriConnect — pengajuan yang sah butuh tanda tangan atasan dan
    lampiran, bukan tombol di hub koordinasi.
  </p>
  @if ($baris === [])
    <p class="tenang">Belum ada pengajuan cuti, izin, maupun sakit pada akun ini.</p>
  @else
    <div class="tabel-bungkus">
      <table>
        <thead>
          <tr>
            <th>Jenis</th><th>Mulai</th><th>Selesai</th><th>Durasi</th>
            <th>Alasan</th><th>Status</th><th>Diajukan</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($baris as $b)
            @php
              $status = (string) ($b['status'] ?? $b['approvalStatus'] ?? $b['requestStatus'] ?? '');
              $kelas = match (strtolower($status)) {
                  'approved', 'disetujui' => 's-SELESAI',
                  'rejected', 'ditolak' => 's-DITOLAK',
                  'canceled', 'dibatalkan' => 's-DITOLAK',
                  default => 's-DIAJUKAN',
              };
              $durasi = $b['totalDay'] ?? $b['day'] ?? $b['duration'] ?? null;
              $mulai = $b['startDate'] ?? $b['date'] ?? null;
              $selesai = $b['endDate'] ?? $b['date'] ?? null;
            @endphp
            <tr>
              <td data-l="Jenis">{{ $b['_jenis'] ?? '—' }}
                @if (filled($b['absenceType'] ?? null)) <small class="pudar">{{ $b['absenceType'] }}</small> @endif</td>
              <td data-l="Mulai">{{ $mulai ?? '—' }}</td>
              <td data-l="Selesai">{{ $selesai ?? '—' }}</td>
              <td data-l="Durasi">{{ filled($durasi) ? $durasi.' hari' : '—' }}</td>
              <td data-l="Alasan">{{ $b['reason'] ?? $b['note'] ?? $b['remark'] ?? '—' }}</td>
              <td data-l="Status">
                @if ($status !== '') <span class="lencana {{ $kelas }}">{{ $status }}</span>
                @else <span class="pudar">menunggu</span> @endif</td>
              <td data-l="Diajukan">{{ $b['requestDate'] ?? $b['createdDate'] ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

@if ($saldo !== [])
  <div class="blok">
    <h2>Saldo cuti</h2>
    @include('agri.hris._daftar', ['judul' => 'Saldo per jenis', 'baris' => $saldo, 'kolom' => []])
  </div>
@endif
@endsection

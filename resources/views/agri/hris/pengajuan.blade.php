@extends('layouts.utama')
@section('judul', 'Pengajuan (AgriConnect)')
@section('deskripsi', 'Seluruh pengajuan mandiri pegawai — cuti, lembur, klaim biaya, pinjaman, dinas — beserta status persetujuannya dari AgriConnect HRIS.')

@section('isi')
<div class="kepala">
  <h1>Pengajuan</h1>
  <p class="tenang">Semua pengajuan Anda di AgriConnect, satu daftar. Dibaca langsung dari HRIS.</p>
</div>

@include('agri.hris._nav', ['mod' => 'hris-pengajuan'])

<div class="kisi-kartu">
  <div class="kartu angka"><span class="pudar">Total pengajuan</span><strong>{{ $ringkas['total'] }}</strong></div>
  <div class="kartu angka"><span class="pudar">Menunggu</span><strong>{{ $ringkas['menunggu'] }}</strong></div>
  <div class="kartu angka"><span class="pudar">Disetujui</span><strong>{{ $ringkas['disetujui'] }}</strong></div>
  <div class="kartu angka"><span class="pudar">Ditolak</span><strong>{{ $ringkas['ditolak'] }}</strong></div>
  <div class="kartu angka"><span class="pudar">Perlu keputusan saya</span><strong>{{ $perluSaya }}</strong>
    <p class="bantuan">pengajuan bawahan</p></div>
</div>

<div class="blok">
  <div class="blok-kepala">
    <h2>Riwayat pengajuan</h2>
    <span class="pudar">{{ $daftar['jumlah'] }} baris</span>
  </div>
  <p class="bantuan">
    Halaman ini hanya membaca. Mengajukan baru tetap lewat aplikasi AgriConnect —
    pengajuan sah butuh lampiran dan tanda tangan atasan.
  </p>

  @if ($gagal !== [])
    <p class="pesan rusak">Jenis yang tidak bisa dibaca saat ini: {{ implode(', ', $gagal) }}.
      Sisanya tetap ditampilkan.</p>
  @endif

  @include('agri._saring', [
    'q' => $daftar['q'], 'nilai' => $daftar['nilai'], 'namaFilter' => 'keadaan',
    'pilihan' => $keadaan, 'placeholder' => 'Cari jenis, alasan, atau tanggal…',
  ])

  @if ($daftar['baris'] === [])
    <p class="kosong">
      @if ($daftar['q'] !== '' || $daftar['nilai'] !== '')
        Tidak ada pengajuan yang cocok dengan saringan ini.
      @else
        Belum ada pengajuan dari {{ count($jenis) }} jenis yang diperiksa
        ({{ implode(', ', $jenis) }}) pada akun ini.
      @endif
    </p>
  @else
    <div class="tabel-bungkus">
      <table>
        <thead>
          <tr>
            <th>Jenis</th><th>Diajukan</th><th>Periode</th><th>Keterangan</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($daftar['baris'] as $b)
            @php
              $status = (string) ($b['status'] ?? $b['approvalStatus'] ?? $b['requestStatus'] ?? '');
              $kelas = match (strtolower($status)) {
                  'approved', 'disetujui' => 's-SELESAI',
                  'rejected', 'canceled', 'ditolak', 'dibatalkan' => 's-DITOLAK',
                  default => 's-DIAJUKAN',
              };
              $mulai = $b['startDate'] ?? $b['date'] ?? $b['departureDate'] ?? null;
              $selesai = $b['endDate'] ?? $b['returnDate'] ?? $mulai;
              $ket = $b['reason'] ?? $b['note'] ?? $b['remark'] ?? $b['title'] ?? $b['purpose'] ?? '—';
            @endphp
            <tr>
              <td data-l="Jenis">{{ $b['_jenis'] }}</td>
              <td data-l="Diajukan">{{ $b['requestDate'] ?? $b['createdDate'] ?? '—' }}</td>
              <td data-l="Periode">{{ filled($mulai) ? $mulai.($selesai && $selesai !== $mulai ? ' → '.$selesai : '') : '—' }}</td>
              <td data-l="Keterangan">{{ is_string($ket) ? \Illuminate\Support\Str::limit(strip_tags($ket), 90) : '—' }}</td>
              <td data-l="Status">
                @if (trim($status) !== '')<span class="lencana {{ $kelas }}">{{ $status }}</span>
                @else<span class="pudar">menunggu</span>@endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @include('partials.halaman', ['halaman' => $daftar['halaman'], 'ikon' => 'app'])
  @endif
</div>
@endsection

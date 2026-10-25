@extends('layouts.utama')

@section('judul', $orang ? 'Absensi '.$orang->nama : 'Absensi Manajer')
@section('isi')
@php
  // Halaman Absensi AgriConnect untuk PEMANTAU (admin pusat, HR, PIC, keuangan).
  // Mereka tidak punya akun AgriConnect dan tidak perlu punya: yang dibaca di
  // sini adalah cermin `agri_ac_absen` yang diisi dari sesi AgriConnect
  // masing-masing orang (halaman Absensinya sendiri, atau `hub:tarik-absen`).
  $jam = fn (?string $iso): string => $iso === null || $iso === ''
      ? '—'
      : \Illuminate\Support\Carbon::parse($iso, 'Asia/Jakarta')->format('H:i');
  $lencana = function ($b) {
      if ($b === null) return ['Belum ada data', 's-BARU'];
      if ((int) $b->libur === 1) return ['Libur', 's-DIPROSES'];
      if ((int) $b->alpha === 1) return ['Alpha', 's-DITOLAK'];
      if ($b->masuk === '') return ['Belum absen', 's-BARU'];
      if ((int) $b->telat === 1) return ['Terlambat', 's-DIAJUKAN'];
      return ['Hadir', 's-SELESAI'];
  };
@endphp

<header class="kepala">
  <div>
    <h1>{{ $orang ? 'Absensi '.$orang->nama : 'Absensi Manajer' }}</h1>
    <p class="tenang">
      @if ($orang)
        Rincian absensi AgriConnect satu orang — data cermin Hub, hanya baca.
      @else
        Kehadiran para manajer menurut AgriConnect. Halaman ini <b>hanya membaca</b>:
        admin/HR/PIC tidak perlu punya akun AgriConnect untuk melihatnya.
      @endif
    </p>
  </div>
  @if ($orang)
    <a class="tombol samar" href="{{ route('agri.hris.absensi', array_filter(['q' => $cari])) }}">&larr; Semua orang</a>
  @endif
</header>

@if ($orang)
  {{-- -------------------------------------------------------------- rincian --}}
  <section class="blok">
    <div class="kepala-bagian">
      <h2>Absensi {{ $orang->nama }}
        <small class="pudar">
          {{ $koperasi?->nama ?? 'koperasi belum tertaut' }}
          @if ($koperasi?->provinsi) · {{ $koperasi->provinsi }}@endif
        </small>
      </h2>
    </div>

    <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th>Jam kerja</th><th>Keadaan</th><th>Keterangan</th></tr></thead>
        <tbody>
          @forelse ($rincian as $b)
            @php [$label, $kelas] = $lencana($b); @endphp
            <tr>
              <td data-l="Tanggal"><b>{{ tanggal_id($b->tanggal, 'd M Y') }}</b>
                <small class="pudar">{{ tanggal_id($b->tanggal, 'l') }}</small></td>
              <td data-l="Masuk">{{ $jam($b->masuk) }}</td>
              <td data-l="Keluar">{{ $jam($b->keluar) }}</td>
              <td data-l="Jam kerja">{{ $b->jam_kerja !== '' ? substr($b->jam_kerja, 0, 5) : '—' }}</td>
              <td data-l="Keadaan"><span class="lencana {{ $kelas }}">{{ $label }}</span></td>
              <td data-l="Keterangan">{{ $b->remark !== '' ? $b->remark : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="kosong">Belum ada absensi AgriConnect yang tercatat untuk orang ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])

    <p class="bantuan">
      Data terakhir ditarik: {{ $rincian->first()?->disinkron ? tanggal_id(substr($rincian->first()->disinkron, 0, 10), 'd M Y').' '.substr($rincian->first()->disinkron, 11, 5) : '—' }} WIB.
      Sumbernya time-sheet AgriConnect milik orang ini, bukan tebakan Hub.
    </p>
  </section>
@else
  {{-- ---------------------------------------------------------------- daftar --}}
  <section class="blok">
    <div class="kepala-bagian">
      <h2>Orang dengan absensi AgriConnect <small class="pudar">{{ number_format($jumlah, 0, ',', '.') }} orang</small></h2>
    </div>

    @include('agri._saring', [
      'aksi' => route('agri.hris.absensi'),
      'q' => $cari,
      'placeholder' => 'nama atau email…',
      'petunjuk' => 'Rekap bulan berjalan (sejak '.tanggal_id($awalBulan, 'd M Y').'). Sumber: time-sheet AgriConnect yang ditarik dari sesi masing-masing orang.',
    ])

    <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Nama</th><th>Hari ini</th><th>Hadir bulan ini</th><th>Terlambat</th><th>Alpha</th><th>Data ditarik</th></tr></thead>
        <tbody>
          @forelse ($baris as $b)
            @php
              $k = $hariIni[$b->id] ?? null;
              [$label, $kelas] = $lencana($k);
              $r = $rekap[$b->id] ?? null;
            @endphp
            <tr>
              <td data-l="Nama">
                <a class="judul-sel" href="{{ route('agri.hris.absensi', array_filter(['q' => $cari, 'orang' => $b->id])) }}">{{ $b->nama }}</a>
                <small class="pudar">{{ $b->email }}</small>
              </td>
              <td data-l="Hari ini">
                <span class="lencana {{ $kelas }}">{{ $label }}</span>
                @if ($k !== null && $k->masuk !== '')
                  <small class="pudar">{{ $jam($k->masuk) }}@if ($k->keluar !== '') – {{ $jam($k->keluar) }}@endif</small>
                @endif
              </td>
              <td data-l="Hadir bulan ini">{{ (int) ($r->hadir ?? 0) }} hari</td>
              <td data-l="Terlambat">{{ (int) ($r->telat ?? 0) }} hari</td>
              <td data-l="Alpha">{{ (int) ($r->alpha ?? 0) }} hari</td>
              <td data-l="Data ditarik"><small class="pudar">{{ $r?->sinkron ? substr($r->sinkron, 0, 16) : '—' }}</small></td>
            </tr>
          @empty
            <tr><td colspan="6" class="kosong">
              {{ $cari !== '' ? 'Tidak ada yang cocok dengan pencarian ini.' : 'Belum ada absensi AgriConnect yang masuk. Minta manajer membuka menu Absensi sekali, atau jalankan tarikan berkala.' }}
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])

    <p class="bantuan">
      Yang tampil hanya orang yang absennya sudah pernah ditarik dari AgriConnect. Tarikan berkala:
      <code>php artisan hub:tarik-absen</code> (satu baris per orang per tanggal, aman diulang).
    </p>
  </section>
@endif
@endsection

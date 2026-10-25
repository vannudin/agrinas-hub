@extends('layouts.utama')

@section('judul', $orang ? 'Presensi '.$orang->nama : 'Presensi Manajer')
@section('isi')
@php
  // Halaman presensi untuk PERAN PEMANTAU (admin, HR, PIC, keuangan): yang
  // dicari bukan formulir Check In milik sendiri, melainkan kehadiran para
  // manajer koperasi. Manajer tetap memakai halaman presensi lamanya.
  $peta = fn ($lat, $lng) => 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng.'#map=17/'.$lat.'/'.$lng;
  $lencana = function (?array $k) {
      if ($k === null || $k['masuk'] === '') return ['Belum Check In', 's-BARU'];
      return $k['keluar'] === '' ? ['Sudah masuk — belum keluar', 's-DIAJUKAN'] : ['Lengkap', 's-SELESAI'];
  };
@endphp

<header class="kepala">
  <div>
    <h1>{{ $orang ? 'Presensi '.$orang->nama : 'Presensi Manajer' }}</h1>
    <p class="tenang">
      @if ($orang)
        Rincian kehadiran satu manajer: daftar hadir SIMKOPDES (catatan resmi) dan Check In Hub, beserta foto &amp; titik lokasinya.
      @else
        Kehadiran seluruh manajer koperasi dalam lingkup Anda — hari ini, jumlah hari hadir, dan keterlambatan bulan berjalan.
      @endif
    </p>
  </div>
  @if ($orang)
    <a class="tombol samar" href="{{ route('agri.presensi', array_filter(['q' => $cari])) }}">&larr; Semua manajer</a>
  @endif
</header>

@if ($orang)
  {{-- ------------------------------------------------------------ rincian --}}
  <section class="blok">
    <div class="kepala-bagian">
      <h2>Kehadiran {{ $orang->nama }}
        <small class="pudar">
          {{ $koperasi?->nama ?? 'koperasi belum tertaut' }}
          @if ($koperasi?->provinsi) · {{ $koperasi->provinsi }}@endif
        </small>
      </h2>
    </div>

    <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Sumber</th><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th>Bukti</th><th>Kegiatan</th></tr></thead>
        <tbody>
          @forelse ($rincian['baris'] as $b)
            <tr>
              <td data-l="Sumber"><span class="pudar">{{ $b->sumber }}</span></td>
              <td data-l="Tanggal"><span class="judul-sel">{{ tanggal_id($b->tanggal, 'd M Y') }}</span>
                <small class="pudar">{{ tanggal_id($b->tanggal, 'l') }}</small></td>
              <td data-l="Masuk">{{ $b->masuk !== '' ? $b->masuk : '—' }}</td>
              <td data-l="Keluar">{{ $b->keluar !== '' ? $b->keluar : '—' }}</td>
              <td data-l="Bukti">
                <div class="presensi-bukti">
                  @if ($b->fotoMasuk !== '')
                    <a href="{{ $b->fotoMasuk }}" target="_blank" rel="noopener" title="Foto absen masuk">
                      <img class="foto-presensi" src="{{ $b->fotoMasuk }}" alt="Foto absen masuk {{ tanggal_id($b->tanggal, 'd M Y') }}" loading="lazy">
                    </a>
                  @endif
                  @if ($b->fotoKeluar !== '')
                    <a href="{{ $b->fotoKeluar }}" target="_blank" rel="noopener" title="Foto absen keluar">
                      <img class="foto-presensi" src="{{ $b->fotoKeluar }}" alt="Foto absen keluar {{ tanggal_id($b->tanggal, 'd M Y') }}" loading="lazy">
                    </a>
                  @endif
                  @if ($b->fotoMasuk === '' && $b->fotoKeluar === '') <span class="pudar">—</span> @endif
                  @if ($b->lat !== '' && $b->lng !== '')
                    <a class="peta-tautan" href="{{ $peta($b->lat, $b->lng) }}" target="_blank" rel="noopener">Peta</a>
                  @endif
                </div>
              </td>
              <td data-l="Kegiatan">{{ $b->kegiatan !== '' ? $b->kegiatan : '—' }}
                @if ($b->catatan !== '') <small class="pudar">{{ $b->catatan }}</small> @endif</td>
            </tr>
          @empty
            <tr><td colspan="6" class="kosong">Belum ada absensi tercatat untuk manajer ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.halaman', ['halaman' => $rincian['halaman'], 'ikon' => 'app', 'kunci' => 'halaman'])

    <p class="bantuan">
      Halaman ini <b>hanya membaca</b>. Absensi SIMKOPDES tidak bisa diubah maupun dihapus — termasuk oleh pemantau.
    </p>
  </section>
@else
  {{-- -------------------------------------------------------------- daftar --}}
  <section class="blok">
    <div class="kepala-bagian">
      <h2>Manajer koperasi <small class="pudar">{{ number_format($jumlah, 0, ',', '.') }} orang</small></h2>
    </div>

    @include('agri._saring', [
      'aksi' => route('agri.presensi'),
      'q' => $cari,
      'placeholder' => 'nama atau email manajer…',
      'petunjuk' => 'Keterlambatan dihitung dari jam masuk > '.$jamMasuk.' (WIB). Sumber: daftar hadir SIMKOPDES dan Check In Hub.',
    ])

    <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Manajer</th><th>Koperasi</th><th>Hari ini</th><th>Hadir bulan ini</th><th>Terlambat</th></tr></thead>
        <tbody>
          @forelse ($baris as $b)
            @php
              $r = $rekap[$b->id] ?? ['hariIni' => null, 'hadir' => 0, 'terlambat' => 0, 'rataMasuk' => ''];
              [$label, $kelas] = $lencana($r['hariIni']);
            @endphp
            <tr>
              <td data-l="Manajer">
                <a class="judul-sel" href="{{ route('agri.presensi', array_filter(['q' => $cari, 'orang' => $b->id])) }}">{{ $b->nama }}</a>
                <small class="pudar">{{ $b->email }}</small>
              </td>
              <td data-l="Koperasi">
                {{ $koperasi[$b->id]->nama ?? 'belum tertaut' }}
                @if (isset($koperasi[$b->id]))
                  <small class="pudar">{{ $koperasi[$b->id]->provinsi ?: $koperasi[$b->id]->wilayah }}</small>
                @endif
              </td>
              <td data-l="Hari ini">
                <span class="lencana {{ $kelas }}">{{ $label }}</span>
                @if ($r['hariIni'] !== null && $r['hariIni']['masuk'] !== '')
                  <small class="pudar">{{ $r['hariIni']['masuk'] }}@if ($r['hariIni']['keluar'] !== '') – {{ $r['hariIni']['keluar'] }}@endif · {{ $r['hariIni']['dari'] }}</small>
                @endif
              </td>
              <td data-l="Hadir bulan ini">{{ $r['hadir'] }} hari
                @if ($r['rataMasuk'] !== '') <small class="pudar">rata {{ $r['rataMasuk'] }}</small> @endif
              </td>
              <td data-l="Terlambat">{{ $r['terlambat'] }} hari</td>
            </tr>
          @empty
            <tr><td colspan="5" class="kosong">
              {{ $cari !== '' ? 'Tidak ada manajer yang cocok dengan pencarian ini.' : 'Belum ada manajer koperasi dalam lingkup Anda.' }}
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])

    <p class="bantuan">
      Angka “hadir” dan “terlambat” dihitung dari bulan berjalan (Asia/Jakarta). Yang tampil hanya manajer dalam
      lingkup Anda: PIC dibatasi wilayahnya, admin/HR/keuangan melihat semuanya.
    </p>
  </section>
@endif
@endsection

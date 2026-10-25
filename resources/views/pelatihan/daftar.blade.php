@extends('layouts.utama')

@section('judul', 'Pelatihan')
@section('isi')
<header class="kepala">
  <div><h1>Pelatihan</h1>
    <p class="tenang">Materi pelatihan dari LMS Agrinas — baca, lalu tandai selesai.</p></div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('materi') }}">Materi &amp; Juknis</a>
  </div>
</header>

<div class="petak">
  <div class="kartu angka aksi-hijau"><small>Modul Selesai</small><b class="t-hijau">{{ $ringkas['selesai'] }}</b></div>
  <div class="kartu angka"><small>Total Modul</small><b>{{ $ringkas['total'] }}</b></div>
  <div class="kartu angka {{ $ringkas['persen'] >= 100 ? 'aksi-hijau' : '' }}"><small>Progres</small>
    <b class="{{ $ringkas['persen'] >= 100 ? 't-hijau' : '' }}">{{ $ringkas['persen'] }}%</b></div>
  <div class="kartu angka"><small>Jalur Belajar</small><b>{{ $path->count() }}</b></div>
</div>

<section class="blok">
  <h2>Progres Pembelajaran</h2>
  <div class="jejak-baris">
    <div class="jejak" role="progressbar" aria-valuenow="{{ $ringkas['persen'] }}" aria-valuemin="0" aria-valuemax="100">
      <span style="width: {{ $ringkas['persen'] }}%"></span>
    </div>
    <b>{{ $ringkas['selesai'] }}/{{ $ringkas['total'] }}</b>
  </div>
</section>

@forelse ($path as $p)
  <section class="blok">
    <h2>{{ $p['judul'] }}
      <small class="pudar">{{ $p['selesai'] }}/{{ $p['jumlah'] }} modul · {{ $p['persen'] }}%</small>
    </h2>
    <div class="jejak-baris" style="margin-bottom: 1rem;">
      <div class="jejak" role="progressbar" aria-valuenow="{{ $p['persen'] }}" aria-valuemin="0" aria-valuemax="100">
        <span style="width: {{ $p['persen'] }}%"></span>
      </div>
      <b>{{ $p['persen'] }}%</b>
    </div>
    <div class="tabel-bungkus">
    <table>
      <thead><tr><th>Modul</th><th>Durasi</th><th>Status</th><th class="kolom-aksi"></th></tr></thead>
      <tbody>
      @foreach ($p['modul'] as $m)
        <tr>
          <td data-l="Modul"><span class="judul-sel">{{ $m->judul }}</span>
            @if ($m->ringkas)<small class="pudar">{{ \Illuminate\Support\Str::limit($m->ringkas, 130) }}</small>@endif</td>
          <td data-l="Durasi">{{ $m->menit ? $m->menit.' menit' : '—' }}</td>
          <td data-l="Status">
            @if (in_array($m->lms_id, $selesai, true))
              <span class="lencana s-DITERIMA">Selesai</span>
            @else
              <span class="lencana s-BARU">Belum</span>
            @endif
          </td>
          <td class="kolom-aksi"><a class="tombol utama mini" href="{{ route('pelatihan.baca', $m->lms_id) }}">
            {{ in_array($m->lms_id, $selesai, true) ? 'Baca Ulang' : 'Baca' }}</a></td>
        </tr>
      @endforeach
      </tbody>
    </table>
    </div>
  </section>
@empty
  <section class="blok">
    <p class="bantuan">Belum ada materi pelatihan. Hubungi admin Agrinas untuk menarik materi dari LMS.</p>
  </section>
@endforelse
@endsection

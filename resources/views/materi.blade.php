@extends('layouts.utama')

@section('judul', 'Materi Pelatihan')
@section('isi')
<header class="kepala">
  <div>
    <h1>Materi Pelatihan</h1>
    <p class="tenang">Modul, bahan tayang, dan kertas kerja pelatihan manajer KDKMP 2026.
      Pilih modul, lalu baca langsung di peramban atau unduh untuk dipelajari luring.</p>
  </div>
</header>

<section class="blok">
  <form class="saring" method="get" action="{{ route('materi') }}">
    @if ($saringKategori !== '')
      <input type="hidden" name="kategori" value="{{ $saringKategori }}">
    @endif
    <label class="sr" for="q">Cari materi</label>
    <input id="q" name="q" value="{{ $cari }}" placeholder="Cari judul materi pelatihan…">
    <button class="tombol samar" type="submit">Cari</button>
  </form>

  <nav class="materi-keping" aria-label="Saring jenis materi">
    <a class="{{ $saringKategori === '' ? 'aktif' : '' }}" href="{{ route('materi', $cari !== '' ? ['q' => $cari] : []) }}">Semua <b>{{ $jumlah }}</b></a>
    @foreach ($kategori as $kode => $label)
      @continue(($perKategori[$kode] ?? 0) === 0)
      <a class="{{ $saringKategori === $kode ? 'aktif' : '' }}"
         href="{{ route('materi', array_filter(['kategori' => $kode, 'q' => $cari])) }}">{{ $label }} <b>{{ $perKategori[$kode] }}</b></a>
    @endforeach
  </nav>

  <p class="pudar materi-rentang">Menampilkan {{ $halaman->dari() }}–{{ $halaman->sampai() }}
    dari {{ $jumlah }} materi · {{ $halaman->perHalaman }} per halaman.</p>
</section>

@forelse ($kelompok as $namaModul => $isi)
  <section class="blok">
    <h2>{{ $namaModul }}
      <small class="pudar">{{ $perKelompok[$namaModul] ?? $isi->count() }} berkas</small>
    </h2>
    <div class="materi-kisi">
      @foreach ($isi as $m)
        <article class="materi-kartu">
          <a class="materi-sampul" href="{{ route('materi.baca', $m->id) }}" target="_blank" rel="noopener"
             aria-label="Baca {{ $m->judul }}">
            @if ($m->thumb !== '')
              <img src="{{ route('materi.sampul', $m->id) }}" alt="Sampul {{ $m->judul }}" loading="lazy" decoding="async">
            @else
              <span class="materi-ikon">{{ mb_strtoupper(pathinfo((string) $m->nama_asli, PATHINFO_EXTENSION) ?: 'PDF') }}</span>
            @endif
          </a>
          <div class="materi-isi">
            <span class="lencana">{{ $kategori[$m->kategori] ?? $m->kategori }}</span>
            <h3>{{ $m->judul }}</h3>
            <p class="pudar">{{ ukuran_id($m->ukuran) }} · {{ pathinfo((string) $m->nama_asli, PATHINFO_EXTENSION) ?: 'berkas' }}</p>
            <div class="tombol-baris">
              <a class="tombol utama mini" href="{{ route('materi.baca', $m->id) }}" target="_blank" rel="noopener">Baca</a>
              <a class="tombol samar mini" href="{{ route('materi.unduh', $m->id) }}">Unduh</a>
            </div>
          </div>
        </article>
      @endforeach
    </div>
  </section>
@empty
  <section class="blok">
    <p class="kosong">
      @if ($cari !== '' || $saringKategori !== '')
        Tidak ada materi yang cocok dengan saringan ini.
        <a href="{{ route('materi') }}">Tampilkan semua</a>.
      @else
        Belum ada materi dari Agrinas.
      @endif
    </p>
  </section>
@endforelse

@if ($halaman->total > 1)
  <section class="blok">
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
  </section>
@endif
@endsection

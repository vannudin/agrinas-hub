@extends('layouts.utama')

@section('judul', 'E-Learning')
@section('isi')
<header class="kepala">
  <div>
    <h1>E-Learning</h1>
    <p class="tenang">Pelajaran singkat berbasis teks beserta progres belajar Anda.</p>
  </div>
  @if ($pusat)
    <div class="tombol-baris">
      <button class="tombol utama" type="button" data-buka="materi-baru">Pelajaran Baru</button>
    </div>
  @endif
</header>
@include('agri._nav', ['mod' => 'pelatihan'])

<section class="blok" id="el" data-isi="{{ route('agri.pelatihan') }}" data-params="q,halaman">
  <div class="kartu-baris">
    <div class="kartu angka"><small>Pelajaran Tersedia</small><b>{{ number_format($jumlahMateri, 0, ',', '.') }}</b></div>
    <div class="kartu angka"><small>Sudah Anda Selesaikan</small><b>{{ number_format($jumlahSelesai, 0, ',', '.') }}</b></div>
  </div>

  <form class="saring" method="get" action="{{ route('agri.pelatihan') }}">
    <label class="cari">
      <span class="sr">Cari pelajaran</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari judul atau ringkasan…">
    </label>
    <a class="tombol samar bersih-saring" href="{{ route('agri.pelatihan') }}" @if ($cari === '') hidden @endif>Bersihkan</a>
  </form>

  @if ($daftar->isEmpty())
    <p class="kosong">
      @if ($cari !== '') Tidak ada pelajaran yang cocok.
      @elseif ($pusat) Belum ada pelajaran — tambahkan lewat tombol Pelajaran Baru.
      @else Belum ada pelajaran yang dipublikasikan. @endif
    </p>
  @else
    <div class="kartu-baris">
      @foreach ($daftar as $m)
        <a class="kartu" href="{{ route('agri.pelatihan.baca', $m->id) }}">
          <b>{{ $m->judul }}</b>
          <small class="pudar">{{ $m->ringkas !== '' ? $m->ringkas : '—' }}</small>
          <p class="bantuan">
            {{ $m->menit }} menit
            @if (isset($selesai[$m->id])) · <span class="lencana s-SELESAI">Selesai</span>@endif
          </p>
        </a>
      @endforeach
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
  @endif
</section>

@if ($pusat)
<dialog class="modal" id="materi-baru" aria-labelledby="materi-baru-judul">
  <form method="post" action="{{ route('agri.pelatihan.simpan') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="materi-baru-judul">Pelajaran Baru</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Judul <input name="judul" maxlength="160" required placeholder="Contoh: Membaca laporan keuangan koperasi"></label>
      <label>Ringkasan <span class="bantuan">(opsional, satu baris untuk kartu)</span>
        <input name="ringkas" maxlength="300" placeholder="Apa yang dipelajari di pelajaran ini"></label>
      <label>Isi pelajaran <span class="bantuan">(teks; dukung penomoran sederhana)</span>
        <textarea name="isi" rows="10" maxlength="20000" required
          placeholder="Tulis isi pelajaran di sini…"></textarea></label>
      <label>Perkiraan menit <input type="number" name="menit" min="1" max="600" step="1" value="5" required></label>
      <label>Langkah belajar <span class="bantuan">(opsional — satu langkah per baris)</span>
        <textarea name="langkah" rows="4" maxlength="4000"
          placeholder="Contoh:&#10;Buka menu Pasokan UMKM&#10;Catat satu pasokan UMKM&#10;Kirim untuk disetujui"></textarea></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan Pelajaran</button>
    </footer>
  </form>
</dialog>
@endif

<script src="/js/hub-agri.js?v={{ filemtime(public_path('js/hub-agri.js')) }}" defer></script>
@endsection

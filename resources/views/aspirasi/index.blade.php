@extends('layouts.utama')

@section('judul', 'Aspirasi')
@section('isi')
<header class="kepala">
  <div>
    <h1>Aspirasi</h1>
    <p class="tenang">Sampaikan masukan atau keluhan kepada pengelola (Agrinas, HR, PIC).</p>
  </div>
</header>

<section class="blok">
  <h2>Kirim Aspirasi</h2>
  <p class="bantuan">
    Tulis sejelas mungkin — pengelola membalas lewat halaman ini, dan tanggapannya
    muncul di daftar bawah. Tidak ada batas jumlah pengiriman.
  </p>
  <form method="post" action="{{ route('aspirasi.simpan') }}" class="petak-form">
    @csrf
    <label>
      <span>Jenis</span>
      <select name="jenis" required>
        @foreach ($jenis as $kode => $label)
          <option value="{{ $kode }}" @selected(old('jenis', 'MASUKAN') === $kode)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label class="lebar">
      <span>Perihal</span>
      <input type="text" name="perihal" maxlength="160" required value="{{ old('perihal') }}"
             placeholder="mis. Penyaluran pupuk terlambat dua pekan">
    </label>
    <label class="lebar">
      <span>Uraian</span>
      <textarea name="pesan" rows="5" maxlength="4000" required
                placeholder="Jelaskan keadaan, kapan terjadi, dan apa yang diharapkan…">{{ old('pesan') }}</textarea>
    </label>
    <div class="tombol-baris">
      <button class="tombol" type="submit">Kirim ke Pengelola</button>
    </div>
  </form>
</section>

<section class="blok">
  <h2>Aspirasi Saya <small class="pudar">{{ number_format($halaman->jumlah, 0, ',', '.') }}</small></h2>
  <form class="saring" method="get" action="{{ route('aspirasi') }}" data-otomatis>
    <label class="cari">
      <span class="sr">Cari aspirasi</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari perihal atau uraian…">
    </label>
    <a class="tombol samar bersih-saring" href="{{ route('aspirasi') }}" @if ($cari === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="kabar-daftar">
    @forelse ($daftar as $a)
      <article class="kabar">
        <header>
          <h3>{{ $a->perihal }}
            <span class="lencana {{ $a->labelJenis() === 'Keluhan' ? 'merah' : '' }}">{{ $a->labelJenis() }}</span>
            <span class="lencana {{ $a->warnaStatus() }}">{{ $a->labelStatus() }}</span>
          </h3>
          <small class="pudar">Dikirim {{ $a->dibuat ? waktu_id($a->dibuat) : '—' }}</small>
        </header>
        <p>{{ $a->pesan }}</p>
        @if ($a->tanggapan)
          <div class="tanggapan">
            <small class="pudar">Tanggapan pengelola
              @if ($a->penanggap) · {{ $a->penanggap }} @endif
              @if ($a->ditanggapi) · {{ waktu_id($a->ditanggapi) }} @endif</small>
            <p>{{ $a->tanggapan }}</p>
          </div>
        @else
          <small class="pudar">Belum ditanggapi.</small>
        @endif
      </article>
    @empty
      <p class="kosong">
        {{ $cari !== '' ? 'Tidak ada aspirasi yang cocok.' : 'Belum ada aspirasi. Yang Bos kirim akan tampil di sini beserta tanggapannya.' }}
      </p>
    @endforelse
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>
@endsection

@extends('layouts.utama')

@section('judul', $materi->judul)
@section('isi')
<header class="kepala">
  <div>
    <h1>{{ $materi->judul }}</h1>
    <p class="tenang">{{ $materi->ringkas !== '' ? $materi->ringkas : 'Pelajaran E-Learning' }}
      · {{ $materi->menit }} menit</p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('agri.pelatihan') }}">Kembali</a>
  </div>
</header>
@include('agri._nav', ['mod' => 'pelatihan'])

<section class="blok">
  <p class="bantuan">
    @if ($selesai)
      <span class="lencana s-SELESAI">Sudah Anda tandai selesai</span>
    @else
      Tandai selesai setelah selesai membaca; progresnya tersimpan per akun.
    @endif
  </p>

  <article class="materi-isi">{{ $materi->isi }}</article>

  @unless ($selesai)
    <form method="post" action="{{ route('agri.pelatihan.selesai', $materi->id) }}" class="tombol-baris">
      @csrf
      <button class="tombol utama" type="submit">Tandai Selesai</button>
    </form>
  @endunless

  @if (trim((string) $materi->tautan) !== '')
    <p class="bantuan">Bahan tambahan: <a href="{{ $materi->tautan }}" rel="noopener noreferrer"
      target="_blank">buka tautan</a></p>
  @endif
</section>

@php
  $tercentangJumlah = count(array_intersect($langkah, array_keys($tercentang)));
@endphp
@if ($langkah !== [] || $pusat)
<section class="blok">
  <h2>Langkah Belajar <small class="pudar">{{ $tercentangJumlah }}/{{ count($langkah) }}</small></h2>
  @if ($langkah === [])
    <p class="bantuan">Belum ada langkah untuk pelajaran ini.</p>
  @else
    <ul class="daftar-langkah">
      @foreach ($langkah as $i => $l)
        <li class="{{ isset($tercentang[$l]) ? 'sudah' : '' }}">
          @if (isset($tercentang[$l]))
            <span class="lencana s-SELESAI" aria-hidden="true">&#10003;</span>
            <span>{{ $l }}</span>
          @else
            <form method="post" action="{{ route('agri.pelatihan.centang', [$materi->id, $i + 1]) }}" class="sebaris">
              @csrf
              <button class="tombol samar" type="submit"
                aria-label="Tandai langkah {{ $i + 1 }} selesai">Tandai</button>
            </form>
            <span>{{ $l }}</span>
          @endif
        </li>
      @endforeach
    </ul>
    @if ($tercentangJumlah >= count($langkah))
      <p class="pesan ok" role="status">Semua langkah selesai — pelajaran ini ditandai tuntas.</p>
    @else
      <p class="bantuan">
        Tiap langkah memberi {{ \App\Http\Controllers\AgriController::POIN_LANGKAH }} poin, sekali saja.
        Menekan "Tandai" dua kali tidak menambah poin.
      </p>
    @endif
  @endif

  @if ($pusat)
    <form method="post" action="{{ route('agri.pelatihan.langkah', $materi->id) }}">
      @csrf
      <label>Atur langkah <span class="bantuan">(satu langkah per baris; kosongkan untuk menghapus)</span>
        <textarea name="langkah" rows="4" maxlength="4000">{{ $materi->langkah }}</textarea></label>
      <div class="tombol-baris">
        <button class="tombol" type="submit">Simpan Langkah</button>
      </div>
    </form>
  @endif
</section>
@endif

<section class="blok">
  <h2>Pelajaran lain</h2>
  <div class="kartu-baris">
    @foreach ($tetangga as $t)
      @continue($t->id === $materi->id)
      <a class="kartu" href="{{ route('agri.pelatihan.baca', $t->id) }}">
        <b>{{ $t->judul }}</b>
      </a>
    @endforeach
  </div>
</section>
@endsection

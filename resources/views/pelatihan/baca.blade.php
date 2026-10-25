@extends('layouts.utama')

@section('judul', $m->judul)
@section('isi')
<header class="kepala">
  <div>
    <p class="tenang">{{ $m->path_judul }} · Modul {{ $m->posisi }}{{ $m->menit ? ' · '.$m->menit.' menit' : '' }}</p>
    <h1>{{ $m->judul }}</h1>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('pelatihan') }}">Daftar</a>
  </div>
</header>

<section class="blok">
  <div class="jejak-baris" style="margin-bottom: 1rem;">
    <div class="jejak" role="progressbar" aria-valuenow="{{ $ringkas['persen'] }}" aria-valuemin="0" aria-valuemax="100">
      <span style="width: {{ $ringkas['persen'] }}%"></span>
    </div>
    <b>{{ $ringkas['selesai'] }}/{{ $ringkas['total'] }} modul</b>
  </div>

  @if ($m->ringkas)<p class="bantuan">{{ $m->ringkas }}</p>@endif

  {{-- Konten berasal dari LMS; admin Agrinas yang menyuntingnya. Dicetak
       mentah supaya format modul utuh, jadi penyaring skrip (`html_aman`)
       dipasang di sini — lihat app/Support/helpers.php. --}}
  <div class="materi-isi">{!! html_aman($m->konten) ?: '<p class="bantuan">Modul ini belum punya konten teks.</p>' !!}</div>
</section>

<section class="blok">
  <form method="post" action="{{ route('pelatihan.selesai', $m->lms_id) }}">
    @csrf
    <div class="tombol-baris">
      <button class="tombol {{ $sudah ? 'samar' : 'utama' }}" type="submit">
        {{ $sudah ? 'Batalkan tanda selesai' : 'Tandai selesai' }}</button>
      @if ($sesudah)<a class="tombol utama" href="{{ route('pelatihan.baca', $sesudah->lms_id) }}">Modul berikutnya →</a>@endif
      @if ($sebelum)<a class="tombol samar" href="{{ route('pelatihan.baca', $sebelum->lms_id) }}">← Sebelumnya</a>@endif
    </div>
  </form>
</section>
@endsection

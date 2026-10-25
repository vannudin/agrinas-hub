@extends('layouts.utama')

{{-- Halaman penuh Surat Jalan. Alamat lama tetap hidup: dipakai untuk menautkan
     satu surat jalan langsung, dan sebagai jalur tanpa JavaScript dari daftar.
     Isinya = partial yang SAMA dengan modal, jadi tak ada markup kedua. --}}
@section('judul', 'Surat Jalan '.$dis->kode)
@section('isi')
<header class="kepala">
  <div>
    <h1>Surat Jalan <code>{{ $dis->kode }}</code></h1>
    <p class="tenang">
      {{ $dis->koperasi->nama ?? '—' }} · <span class="lencana s-{{ $dis->status }}">{{ \App\Models\Distribusi::label($dis->status) }}</span>
    </p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('distribusi.daftar') }}">Kembali</a>
  </div>
</header>

<section class="blok sempit">
  @include('distribusi._rincian', ['di_modal' => false])
</section>
@endsection

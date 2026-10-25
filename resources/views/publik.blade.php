@extends('layouts.utama')

@section('judul', $k->nama)
@section('robots', 'noindex, follow')
@section('isi')
<div class="publik-kartu-bungkus">
  <article class="kartu-publik">
    <div class="kepala-kartu">
      <span class="lencana">{{ $k->status === 'AKTIF' ? 'Aktif' : $k->status }}</span>
      <h1>{{ $k->nama }}</h1>
      <p class="kode-koperasi">{{ $k->kode }}</p>
    </div>
    <div class="badan">
      <dl class="pub-identitas">
        <dt>Wilayah</dt><dd>{{ $k->wilayah ?: '—' }}</dd>
        <dt>Alamat</dt><dd>{{ $k->alamat ?: '—' }}</dd>
        <dt>Ringkasan tugas</dt>
        <dd>
          @forelse ($ringkas as $st => $n)
            <span class="lencana s-{{ $st }}">{{ App\Models\Domain::label($st) }}: {{ $n }}</span>
          @empty
            —
          @endforelse
        </dd>
      </dl>
      <p class="bantuan">Halaman ini hanya menampilkan data koperasi, tanpa berkas dan tanpa data pribadi.</p>
      <p class="bantuan"><a href="{{ route('beranda') }}">&larr; Kembali ke beranda</a></p>
    </div>
  </article>
</div>
@endsection

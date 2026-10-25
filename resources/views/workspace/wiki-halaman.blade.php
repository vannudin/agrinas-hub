@extends('layouts.utama')
@section('judul', $hal->judul)
@section('isi')
@php
  // Pilihan induk: seluruh halaman dalam lingkup pengguna, kecuali dirinya
  // sendiri dan keturunannya (memilih keturunan = lingkaran hierarki).
  $terlarang = [$hal->id => true];
  $kumpul = function ($id) use (&$kumpul, &$terlarang, $semua) {
      foreach ($semua->where('induk_id', $id) as $a) { $terlarang[$a->id] = true; $kumpul($a->id); }
  };
  $kumpul($hal->id);
  $calonInduk = $semua->reject(fn ($h) => isset($terlarang[$h->id]));
@endphp

<header class="kepala">
  <div>
    <h1>{{ $hal->judul }}</h1>
    <p class="tenang">
      @if ($hal->induk) Bagian dari <a href="{{ route('workspace.wiki.lihat', $hal->induk->id) }}">{{ $hal->induk->judul }}</a> · @endif
      diperbarui {{ waktu_id($hal->diperbarui ?: $hal->dibuat) }}
    </p>
  </div>
</header>
@include('workspace._nav', ['mod' => 'wiki'])

<section class="blok">
  <h2>Isi</h2>
  @if (trim((string) $hal->isi) === '')
    <p class="kosong">Halaman ini belum punya isi. Tulis di bagian <b>Sunting Halaman</b> di bawah.</p>
  @else
    {{-- Markdown dirender lewat App\Support\Markdown: HTML mentah dibuang dan
         tautan berbahaya ditolak (`html_input='strip'`), jadi `{!! !!}` di sini
         aman. Sebelumnya isi hanya di-nl2br sehingga tabel & daftar Markdown
         tampil sebagai tanda bintang dan pipa mentah — padahal ini modul
         pengetahuan tempat orang menempel SOP bertabel. --}}
    <div class="materi-isi">{!! App\Support\Markdown::keHtml($hal->isi) !!}</div>
  @endif

  @if ($anak->isNotEmpty())
    <h3>Sub-halaman <small class="pudar">{{ $anak->count() }}</small></h3>
    <ul class="wiki-pohon">
      @foreach ($anak as $a)<li><a href="{{ route('workspace.wiki.lihat', $a->id) }}">{{ $a->judul }}</a></li>@endforeach
    </ul>
  @endif
</section>

<section class="blok">
  <h2>{{ $boleh_ubah ? 'Sunting Halaman' : 'Halaman Baca Saja' }}</h2>
  @if (! $boleh_ubah)
    <p class="bantuan"><b>Baca saja.</b> Halaman wiki hanya dapat disunting admin Agrinas.
      Punya koreksi? Hubungi admin pusat.</p>
  @else
  <form method="post" action="{{ route('workspace.wiki.simpan', $hal->id) }}" class="grid-form">
    @csrf
    <label>Judul<input name="judul" required maxlength="160" value="{{ $hal->judul }}"></label>
    {{-- 🔴 `induk_id` WAJIB dikirim. Form ini dulu tidak punya kolomnya,
         sementara action-nya selalu menulis `induk_id => null` — jadi setiap
         "Simpan" melepas halaman dari induknya tanpa peringatan. --}}
    <label>Halaman Induk
      <select name="induk_id">
        <option value="">— Tidak ada (halaman utama) —</option>
        @foreach ($calonInduk as $p)
          <option value="{{ $p->id }}" @selected((int) $hal->induk_id === (int) $p->id)>{{ $p->judul }}</option>
        @endforeach
      </select>
    </label>
    <p class="bantuan lebar-penuh">Isi menerima <b>Markdown</b>: <code>**tebal**</code>,
      <code>- daftar</code>, <code>| tabel |</code>, <code>## judul bagian</code>.</p>
    <label class="lebar-penuh">Isi<textarea name="isi" rows="12" maxlength="40000">{{ $hal->isi }}</textarea></label>
    <div class="lebar-penuh">
      <button class="tombol utama" type="submit">Simpan</button>
      <a class="tombol samar" href="{{ route('workspace.wiki') }}">Kembali ke daftar</a>
    </div>
  </form>
  @endif
</section>

@if ($boleh_ubah)
<section class="blok">
  <h2>Gambar</h2>
  <p class="bantuan">Gambar diperkecil otomatis ke maks 1280 px dan disimpan sebagai WebP
    (biasanya 100–200 KB) supaya halaman tetap ringan dibuka di ponsel.
    Diterima: JPG, PNG, WebP, GIF — maks 10 MB.</p>
  <form method="post" action="{{ route('workspace.wiki.gambar', $hal->id) }}"
        enctype="multipart/form-data" class="grid-form" id="wiki-gambar-form">
    @csrf
    <label class="lebar-penuh">Berkas gambar
      <input type="file" name="gambar" accept="image/jpeg,image/png,image/webp,image/gif" required>
    </label>
    <div class="lebar-penuh">
      <button class="tombol utama" type="submit">Unggah &amp; Sisipkan</button>
      <span class="pudar" id="wiki-gambar-status"></span>
    </div>
  </form>
  @push('skrip')
  <script>
  // Unggah gambar TANPA memuat ulang: markdown hasilnya langsung ditempel ke
  // textarea isi, jadi admin melihat gambarnya sebelum menekan Simpan.
  (function () {
    var f = document.getElementById('wiki-gambar-form');
    if (!f) return;
    var status = document.getElementById('wiki-gambar-status');
    f.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var berkas = f.querySelector('input[type=file]').files[0];
      if (!berkas) return;
      status.textContent = 'Mengunggah & memperkecil…';
      var data = new FormData();
      data.append('gambar', berkas);
      data.append('_token', document.querySelector('meta[name=csrf-token]').content);
      fetch(f.action, {
        method: 'POST', body: data,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      }).then(function (j) {
        var ta = document.querySelector('textarea[name=isi]');
        if (ta) ta.value = ta.value.replace(/\s+$/, '') + '\n\n' + j.markdown + '\n';
        status.textContent = 'Selesai: ' + j.nama + ' — jangan lupa tekan Simpan.';
        f.reset();
      }).catch(function (e) { status.textContent = 'Gagal: ' + e.message; });
    });
  })();
  </script>
  @endpush
@endif
@endsection

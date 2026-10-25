@extends('layouts.utama')

@section('judul', 'Materi')
@section('isi')
<header class="kepala">
  <div><h1>Materi &amp; Juknis</h1>
    <p class="tenang">Unggah dan kelola dokumen pendukung untuk para manajer koperasi.</p></div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('materi') }}">Lihat sebagai manajer</a>
  </div>
</header>

<div class="petak">
  <div class="kartu angka"><small>Total Materi</small><b>{{ number_format($jumlah, 0, ',', '.') }}</b></div>
  <div class="kartu angka"><small>Juknis</small><b>{{ $perKategori['JUKNIS'] ?? 0 }}</b></div>
  <div class="kartu angka"><small>Template</small><b>{{ $perKategori['TEMPLATE'] ?? 0 }}</b></div>
  <div class="kartu angka"><small>Video</small><b>{{ $perKategori['VIDEO'] ?? 0 }}</b></div>
</div>

{{-- Unggah: kartu tersendiri, bukan formulir telanjang di badan halaman. Tombol
     "Pilih Berkas" memicu <input type=file> tersembunyi; seluruh kotak juga
     menerima seret-lepas. Dua-duanya jalur asli peramban — tanpa JavaScript
     inputnya tetap terlihat dan formulirnya tetap terkirim. --}}
<section class="blok">
  <h2>Unggah Materi</h2>
  <form id="mm-unggah" method="post" action="{{ route('admin.materi.unggah') }}"
        enctype="multipart/form-data" class="grid-form-mm" data-maks-kb="{{ $maksKb }}">
    @csrf
    <div class="mm-jatuh" data-jatuh>
      <input id="mm-berkas" class="mm-berkas" name="berkas" type="file" required
             accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.mp4">
      <label class="mm-jatuh-isi" for="mm-berkas">
        <svg class="mm-jatuh-ikon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M4 15v3a2 2 0 002 2h12a2 2 0 002-2v-3"/>
        </svg>
        <b>Seret berkas ke sini</b>
        <span>atau <u>pilih dari perangkat</u></span>
        <small class="bantuan">PDF, Word, Excel, PowerPoint, gambar, atau MP4 · maks
          {{ round($maksKb / 1024, 1) }} MB</small>
      </label>
      <div class="mm-terpilih" data-terpilih hidden>
        <svg class="mm-terpilih-ikon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M6 2h8l6 6v14H6zM14 2v6h6"/></svg>
        <span class="mm-terpilih-teks">
          <b data-terpilih-nama>—</b>
          <small data-terpilih-ukur></small>
        </span>
        <button class="tombol samar mini" type="button" data-ganti>Ganti</button>
      </div>
      <div class="mm-jalur" data-jalur hidden role="status">
        <div class="jejak"><span data-jalur-bilah style="width:0%"></span></div>
        <small data-jalur-teks>Mengunggah…</small>
      </div>
    </div>

    <div class="mm-isian">
      <label>Judul
        <input name="judul" required maxlength="160" value="{{ old('judul') }}"
               placeholder="Juknis Pengisian Laporan Bulanan">
      </label>
      <label>Kategori
        <select name="kategori" required>
          @foreach ($kategori as $k => $v)
            <option value="{{ $k }}" @selected(old('kategori') === $k)>{{ $v }}</option>
          @endforeach
        </select>
      </label>
      <label>Catatan <span class="bantuan">(opsional)</span>
        <input name="catatan" maxlength="500" value="{{ old('catatan') }}"
               placeholder="Untuk manajer yang baru bergabung…">
      </label>
    </div>

    @if ($errors->any())
      <p class="pesan rusak lebar-penuh-mm" role="alert">{{ $errors->first() }}</p>
    @endif

    <div class="mm-aksi">
      <p class="bantuan" data-siap-status role="status">Seret atau pilih satu berkas dulu.</p>
      {{-- Tombol TIDAK dirender `disabled`: tanpa JavaScript ia akan terkunci
           selamanya dan formulirnya mustahil dipakai. Penguncian adalah tugas
           periksa() yang jalan sejak halaman dimuat; tanpa JS, `required` pada
           input berkas yang mencegah kiriman kosong. --}}
      <button class="tombol utama" type="submit" data-kirim>Unggah Materi</button>
    </div>
  </form>
</section>

{{-- Wadah ber-`data-isi`/`data-daftar`: hub.js menukar ISI wadah untuk paging,
     skrip halaman menukar HANYA <tbody> untuk pencarian/saringan — kotak cari
     tidak ikut dibuang, jadi fokus mengetik tidak hilang tiap huruf. --}}
<section class="blok" id="mm" data-isi="{{ route('admin.materi') }}"
         data-daftar="{{ route('admin.materi.baris') }}">
  <h2>Daftar Materi
    <small class="pudar" id="mm-jumlah">{{ number_format($jumlah, 0, ',', '.') }} materi</small>
  </h2>

  <form class="saring" method="get" action="{{ route('admin.materi') }}">
    <label class="cari">
      <span class="sr">Cari materi</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off"
             placeholder="Cari judul, berkas, atau catatan…">
    </label>
    <label class="pilih">
      <span class="sr">Kategori</span>
      <select name="kategori" aria-label="Saring berdasarkan kategori">
        <option value="">Semua kategori</option>
        @foreach ($kategori as $k => $v)
          @if (($perKategori[$k] ?? 0) > 0)
            <option value="{{ $k }}" @selected($saringKategori === $k)>{{ $v }} ({{ $perKategori[$k] }})</option>
          @endif
        @endforeach
      </select>
    </label>
    <a class="tombol samar bersih-saring" href="{{ route('admin.materi') }}"
       @if ($cari === '' && $saringKategori === '') hidden @endif>Bersihkan</a>
    <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Judul</th><th>Kategori</th><th>Berkas</th><th>Ukuran</th><th>Diunggah</th><th class="kolom-aksi"></th></tr></thead>
    <tbody id="mm-baris">
      @include('admin._materi-baris', ['daftar' => $daftar, 'kategori' => $kategori,
        'kosong' => ($cari !== '' || $saringKategori !== '')])
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>

<script src="/js/hub-materi.js?v={{ filemtime(public_path('js/hub-materi.js')) }}" defer></script>
@endsection

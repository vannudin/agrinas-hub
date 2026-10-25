@extends('layouts.utama')

@section('judul', 'Distribusi')
@section('isi')
<header class="kepala">
  <div>
    <h1>{{ $agrinas ? 'Distribusi Barang' : 'Permintaan Barang' }}</h1>
    <p class="tenang">
      @if ($agrinas)
        {{ $halaman->jumlah }} surat jalan — alur: diajukan manajer → barang dikirim → diterima koperasi.
      @else
        Ajukan kebutuhan barang koperasi, lalu tandai terima saat barang tiba.
      @endif
    </p>
  </div>
  {{-- Tombol aksi Agrinas disembunyikan di layar ponsel: baris tombol tak muat
       semeja dengan judul sehingga terpotong layar. Aksinya tetap terjangkau
       lewat bilah bawah (Dasbor/Distribusi/Stok Gudang) dan halaman Semua Fitur.
       Tombol manajer ("Ajukan Barang") TIDAK ikut disembunyikan karena ia tidak
       ada di bilah bawah. --}}
  <div class="tombol-baris{{ $agrinas ? ' hanya-desktop' : '' }}">
    @if ($agrinas)
      <a class="tombol samar" href="{{ route('stok') }}">Stok Gudang</a>
    @else
      <a class="tombol utama" href="{{ route('distribusi.pengajuan') }}">Ajukan Barang</a>
      <a class="tombol samar" href="{{ route('distribusi.stok-koperasi') }}">Stok Koperasi</a>
    @endif
  </div>
</header>

<section class="blok" id="ds" data-isi="{{ route('distribusi.daftar') }}" data-daftar="{{ route('distribusi.baris') }}">
  <h2>Surat Jalan
    <small class="pudar" id="ds-jumlah">{{ number_format($jumlah, 0, ',', '.') }} surat jalan</small>
  </h2>

  {{-- Bilah saring DILUAR wadah yang ditukar hub.js untuk paging: kalau berada
       di dalamnya, mengetik cari akan membuang pagernya. --}}
  <form class="saring" method="get" action="{{ route('distribusi.daftar') }}">
    <label class="cari">
      <span class="sr">Cari surat jalan</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off"
             placeholder="Cari kode surat jalan atau koperasi…">
    </label>
    <label class="pilih">
      <select name="status_baris" aria-label="Saring berdasarkan status">
        <option value="">Semua status</option>
        @foreach (\App\Models\Distribusi::STATUS as $s)
          @if (($stat[$s] ?? 0) > 0)
            <option value="{{ $s }}" @selected($saringStatus === $s)>{{ \App\Models\Distribusi::label($s) }} ({{ $stat[$s] }})</option>
          @endif
        @endforeach
      </select>
    </label>
    @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
    <a class="tombol samar bersih-saring" href="{{ route('distribusi.daftar') }}"
       @if ($cari === '' && $saringStatus === '') hidden @endif>Bersihkan</a>
    <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
  </form>
  {{-- Baris ini dulu berdiri sendiri di bawah judul halaman, sehingga tinggi
       kepala /distribusi berbeda ~106px dari halaman lain. Sebagai saringan
       ia memang milik daftar, jadi tempatnya di dalam blok daftar. --}}
  @if ($agrinas)
    <div class="kartu-baris">
      @foreach (\App\Models\Distribusi::STATUS as $s)
        <a class="kartu angka{{ $status === $s ? ' ini' : '' }}" href="{{ route('distribusi.daftar', ['status' => $s]) }}">
          <small>{{ \App\Models\Distribusi::label($s) }}</small>
          <b>{{ $stat[$s] ?? 0 }}</b>
        </a>
      @endforeach
    </div>
  @endif
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Kode</th><th>Koperasi</th><th>Item</th><th>Status</th><th>Diperbarui</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody id="ds-baris">
      @include('distribusi._baris', ['daftar' => $daftar, 'agrinas' => $agrinas,
        'kosong' => ($cari !== '' || $saringStatus !== '')])
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>



{{-- Modal rincian: SATU <dialog> untuk seluruh tabel. WAJIB di luar wadah
     [data-isi]: hub.js menukar ISI wadah itu setiap kali halaman berganti, dan
     elemen yang berada di dalamnya ikut terbuang — itulah sebabnya tombol
     Rincian berhenti membuka modal setelah ganti halaman. --}}
<dialog class="modal" id="rincian-modal" aria-labelledby="rincian-modal-judul">
  <div id="rincian-modal-isi"><p class="bantuan sedang-muat">Memuat rincian…</p></div>
  <form method="dialog" class="rincian-modal-tutup">
    <button class="tombol samar mini ikon-tutup" aria-label="Tutup rincian">&times;</button>
  </form>
</dialog>

<script src="/js/hub-distribusi.js?v={{ filemtime(public_path('js/hub-distribusi.js')) }}" defer></script>
@endsection

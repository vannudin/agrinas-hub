@extends('layouts.utama')

@section('judul', 'Stok Gudang')
@section('isi')
<header class="kepala">
  <div>
    <h1>Stok Gudang Agrinas</h1>
    <p class="tenang">{{ number_format($jumlah, 0, ',', '.') }} jenis barang{{ $menipis ? " · $menipis menipis" : '' }} — stok berkurang otomatis saat barang dikirim.</p>
  </div>
</header>

{{-- Wadah ber-`data-isi`: hub.js menukar ISI-nya untuk tautan/form yang tetap
     di halaman ini (saring, paging), jadi tabel tidak pernah memuat ulang
     halaman penuh. Tanpa JavaScript semuanya tetap form GET & tautan biasa. --}}
<section class="blok" id="stok" data-isi="{{ route('stok') }}" data-daftar="{{ route('barang.daftar') }}">
  <div class="kepala-blok">
    <h2>Tabel Barang <small class="pudar" id="stok-jumlah">{{ number_format($jumlah, 0, ',', '.') }} barang</small></h2>
    {{-- Tombol tambah di pojok KANAN ATAS tabel. Tanpa JavaScript ia tetap
         berfungsi: <a href="#tambah"> melompat ke formulir di bawah dan
         formulirnya memakai action POST biasa. --}}
    <a class="tombol utama" href="#tambah" data-buka-tambah>Tambah Barang</a>
  </div>

  {{-- Satu bilah saring. Tiap kontrol dibungkus label ber-arah baris supaya
       TIDAK ada caption di atas isian: caption menambah satu baris tinggi pada
       wrapper-nya, sehingga label 63px bersebelahan dengan isian 43px dan bilah
       tampak bergerigi. Tinggi baris dikunci di --tb-saring agar mustahil
       berbeda 1px antara <input> (punya border) dan <select>. --}}
  <form class="saring" method="get" action="{{ route('stok') }}">
    <label class="cari">
      <span class="sr">Cari barang</span>
      {{-- Ikon dekoratif: aria-hidden supaya pembaca layar tak membacanya dua kali. --}}
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off"
             placeholder="Cari nama atau kode barang…">
    </label>
    <label class="pilih">
      <select name="kategori" aria-label="Saring berdasarkan kategori">
        <option value="">Semua kategori</option>
        @foreach ($kategori as $k => $l)
          <option value="{{ $k }}" @selected($saringKategori === $k)>{{ $l }}</option>
        @endforeach
      </select>
    </label>
    {{-- Selalu dirender, disembunyikan `hidden` bila tak ada saringan.
         Alasannya: pencarian di halaman ini berjalan lewat AJAX (hanya baris
         tabel yang ditukar), jadi tombol yang hanya dirender server tidak akan
         pernah muncul saat pengguna mulai mengetik — ia baru ada kalau halaman
         dimuat ulang. Tanpa JavaScript keadaan awalnya yang benar (server tahu
         dari query), jadi tidak ada yang hilang. --}}
    <a class="tombol samar bersih-saring" href="{{ route('stok') }}"
       @if ($cari === '' && $saringKategori === '') hidden @endif>Bersihkan</a>
    <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Kode</th><th>Nama</th><th>Vendor</th><th>Kategori</th><th>Stok</th><th>Harga Jual</th><th>Ambang</th><th>Diperbarui</th><th class="kolom-aksi">Ubah</th></tr></thead>
    <tbody id="stok-baris">
      @include('distribusi._stok-baris', ['barang' => $barang, 'cari' => $cari, 'saringKategori' => $saringKategori])
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>

{{-- ---------------------------------------------------------------- modal ---}}
{{-- Tambah & Ubah memakai SATU <dialog>: yang membedakan hanya action dan
     judulnya. Isi kolomnya identik, jadi dua formulir terpisah hanya akan
     menyimpang satu sama lain. --}}
<dialog class="modal" id="barang-modal" aria-labelledby="barang-modal-judul">
  <form method="post" id="barang-form" action="{{ route('barang.simpan') }}" data-aksi>
    @csrf
    <header class="modal-kepala">
      <h2 id="barang-modal-judul">Tambah Barang</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>

    <div class="modal-badan">
      <p class="bantuan" id="barang-modal-kabar" role="status" hidden></p>
      <p class="pesan rusak" id="barang-modal-galat" role="alert" hidden></p>

      <div class="grid-form">
        <label class="lebar-penuh">Nama barang
          <input name="nama" maxlength="120" required placeholder="Contoh: Beras Medium"></label>
        <label>Kode <span class="bantuan">(opsional)</span>
          <input name="kode" maxlength="24" placeholder="otomatis: BRG-0016"></label>
        <label>Satuan
          <select name="satuan">@foreach ($satuan as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select>
        </label>
        <label>Kategori
          <select name="kategori">@foreach ($kategori as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        </label>
        <label>Vendor penyedia
          <select name="vendor_id"><option value="">— belum ditetapkan —</option>
            @foreach ($vendor as $v)<option value="{{ $v->id }}">{{ $v->nama }}</option>@endforeach</select>
        </label>
        <label>Stok gudang<input type="number" name="stok" value="0" min="0" max="100000000" step="1" required></label>
        <label>Harga jual (Rp)<input type="number" name="harga" value="0" min="0" max="1000000000" step="100" required></label>
        <label>Ambang peringatan<input type="number" name="stok_min" value="0" min="0" max="100000000" step="1"></label>
        <label class="lebar-penuh">Catatan
          <input name="catatan" maxlength="200" placeholder="mis. kemasan 50 kg"></label>
      </div>
      @if ($vendor->isEmpty())
        <p class="bantuan">Belum ada vendor terdaftar — <a href="{{ route('vendor') }}">tambahkan vendor</a> dulu supaya asal pasokan barang bisa dicatat.</p>
      @endif
    </div>

    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit" id="barang-modal-simpan">Tambah Barang</button>
    </footer>
  </form>
</dialog>

{{-- Jalur tanpa JavaScript: formulir biasa di bawah tabel, satu-satunya yang
     dikirim server saat JS tidak jalan. Tidak digandakan untuk modal. --}}
<noscript>
  <section class="blok sempit" id="tambah">
    <h2>Tambah Barang</h2>
    <p class="tenang">JavaScript dimatikan, jadi formulir ini yang dipakai — sama isinya dengan modal di atas.</p>
    <form method="post" action="{{ route('barang.simpan') }}">
      @csrf
      <label>Nama barang<input name="nama" maxlength="120" required></label>
      <label>Kode <span class="bantuan">(opsional)</span><input name="kode" maxlength="24"></label>
      <div class="grid-form">
        <label>Vendor penyedia<select name="vendor_id"><option value="">— belum ditetapkan —</option>
          @foreach ($vendor as $v)<option value="{{ $v->id }}">{{ $v->nama }}</option>@endforeach</select></label>
        <label>Kategori<select name="kategori">@foreach ($kategori as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
        <label>Satuan<select name="satuan">@foreach ($satuan as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select></label>
        <label>Stok awal<input type="number" name="stok" value="0" min="0" required></label>
        <label>Harga jual (Rp)<input type="number" name="harga" value="0" min="0" required></label>
        <label>Ambang peringatan<input type="number" name="stok_min" value="0" min="0"></label>
      </div>
      <label>Catatan<input name="catatan" maxlength="200"></label>
      <button class="tombol utama" type="submit">Tambah Barang</button>
    </form>
  </section>
</noscript>

<script src="/js/hub-stok.js?v={{ filemtime(public_path('js/hub-stok.js')) }}" defer></script>
@endsection

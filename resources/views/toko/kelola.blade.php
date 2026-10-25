@extends('layouts.utama')
@section('judul', 'Kelola Katalog')
@section('isi')
@php
  // admin/PIC (pengelola): menyediakan barang & melepas kiriman.
  // Manajer: memantau pesanannya sendiri + menandai barang diterima.
  $pengelola = ! auth()->user()->manajerKoperasi();
@endphp
<header class="kepala">
  <div>
    <h1>Kelola Katalog &amp; Pesanan</h1>
    <p class="tenang">
      @if ($pengelola)
        Agrinas menyediakan barang — kelola katalog (tanpa muat ulang), pantau order dari manajer,
        lalu terbitkan surat jalan. Manajer yang menandatangani saat barang diterima.
      @else
        Status pesanan Anda: apa yang sudah dikirim, apa yang siap diambil di gudang.
        Tandai <b>Diterima</b> untuk menandatangani surat jalan — itu bukti resminya.
      @endif
    </p>
  </div>
</header>
@include('toko._nav', ['mod' => 'kelola', 'keranjang' => ['jumlah' => 0]])

@if ($pengelola)
{{-- =============================================== KATALOG (AJAX, tanpa muat ulang) --}}
<section class="blok" id="kat" data-isi="{{ route('toko.kelola') }}" data-daftar="{{ route('toko.barang.daftar') }}">
  <div class="kepala-bagian">
    <h2>Katalog Agrinas <small class="pudar" id="kat-jumlah">{{ number_format($jumlah, 0, ',', '.') }} barang</small></h2>
    {{-- Tambah barang = tombol di POJOK KANAN ATAS tabel; formulirnya di modal
         (satu <dialog> melayani Tambah & Sunting). --}}
    <div class="tombol-baris">
      <button class="tombol utama" type="button" data-buka="kat-modal" data-mode="tambah"
              data-judul-dialog="Tambah Produk">Tambah Produk</button>
    </div>
  </div>

  <p class="bantuan" id="kat-kabar" role="status" hidden></p>

  <form class="katalog-baris" id="kat-saring" method="get" action="{{ route('toko.kelola') }}">
    <label>Cari<input type="search" name="q" placeholder="nama atau kode…" autocomplete="off"></label>
    <label>Kategori
      <select name="kategori"><option value="">Semua</option>
        @foreach ($kategori as $k => $l)<option value="{{ $k }}" @selected(request('kategori') === $k)>{{ $l }}</option>@endforeach</select>
    </label>
    <label>Sumber
      <select name="sumber"><option value="">Semua</option>
        @foreach ($sumber as $k => $l)<option value="{{ $k }}" @selected(request('sumber') === $k)>{{ $l }}</option>@endforeach</select>
    </label>
    <label>Status
      <select name="aktif">
        <option value="aktif" @selected(! in_array(request('aktif'), ['turun', 'semua'], true))>Tayang</option>
        <option value="turun" @selected(request('aktif') === 'turun')>Diturunkan</option>
        <option value="semua" @selected(request('aktif') === 'semua')>Semua</option></select>
    </label>
    <noscript><button class="tombol" type="submit">Saring</button></noscript>
  </form>

  <div class="tabel-bungkus">
    <table>
      <thead><tr><th>Barang</th><th>Kategori</th><th>Stok</th><th>Harga</th><th>Tayang</th><th class="kolom-aksi">Aksi</th></tr></thead>
      <tbody id="kat-baris">@include('toko._katalog-baris', ['barang' => $barang, 'kategori' => $kategori, 'dipakai' => $dipakai])</tbody>
    </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
  <p class="bantuan">Harga 0 berarti barang tampil “belum berharga”, jadi belum bisa dimasukkan keranjang.
    Barang yang pernah dipakai di pesanan <b>diturunkan</b>, bukan dihapus — riwayat pengiriman tetap utuh.
    Tombol <b>Tambah Produk</b> &amp; <b>Sunting</b> membuka formulir di jendela modal, tanpa meninggalkan tabel ini.</p>
</section>

{{-- Satu <dialog> melayani TAMBAH & SUNTING: tombol pembuka hanya membawa
     data-mode; isian baris diambil hub-katalog.js dari atribut data-* tombol
     Sunting di baris itu (jadi tidak ada salinan data yang bisa basi).
     Ditaruh DI LUAR #kat supaya tidak ikut terbuang saat daftar ditukar AJAX. --}}
<dialog class="modal" id="kat-modal" aria-labelledby="kat-modal-judul">
  <form method="post" id="kat-form" data-aksi action="{{ route('toko.barang.simpan') }}"
        data-simpan="{{ route('toko.barang.simpan') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="kat-modal-judul">Tambah Produk</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>

    <div class="modal-badan">
      <div class="grid-form">
        <label>Kode <span class="bantuan">(boleh dikosongkan)</span>
          <input name="kode" maxlength="24" placeholder="otomatis: BRG-0001"></label>
        <label>Nama<input name="nama" required maxlength="120"></label>
        <label>Satuan<select name="satuan">@foreach ($satuan as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select></label>
        <label>Kategori<select name="kategori">@foreach ($kategori as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
        <label>Stok<input type="number" name="stok" value="0" min="0" required></label>
        <label>Stok minimum<input type="number" name="stok_min" value="0" min="0"></label>
        <label>Harga jual / satuan (Rp)<input type="number" name="harga" value="0" min="0" required></label>
        <label>Sumber<select name="sumber">@foreach ($sumber as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
        {{-- Penanda SUBSIDI. Checkbox, bukan pilihan kategori: satu barang
             bersubsidi ATAU tidak — dan kategori gudangnya (Pangan/Pupuk)
             tidak menentukan itu. Nilai tersembunyi 0 memastikan kotak yang
             dilepas benar-benar mengirim "0", bukan tidak mengirim apa pun. --}}
        <label class="centang-subsidi">
          <input type="hidden" name="subsidi" value="0">
          <input type="checkbox" name="subsidi" value="1">
          <span>Produk subsidi
            <span class="bantuan">Tampil di penyaring “Produk Subsidi” pada katalog</span></span>
        </label>
      </div>
      <label>Vendor <span class="bantuan">(bila sumber = Vendor)</span>
        <select name="vendor_id"><option value="">—</option>
          @foreach ($vendor as $v)<option value="{{ $v->id }}">{{ $v->nama }}</option>@endforeach</select></label>
      {{-- Gambar = BERKAS yang diunggah, bukan tautan. `gambar` (hidden) menyimpan
           nama berkas yang sudah ada supaya menyunting tanpa memilih gambar baru
           tidak menghapus fotonya. --}}
      <label>Gambar produk <span class="bantuan">(jpg/png/webp, maks 4 MB — kosongkan bila tidak diubah)</span>
        <input type="file" name="gambar_berkas" accept="image/jpeg,image/png,image/webp">
        <input type="hidden" name="gambar" value=""></label>
      {{-- Harga grosir: satu baris per jenjang, "mulai: harga" dipisah koma.
           Dipilih dari pada tabel berulang supaya modal yang sama tetap bekerja
           tanpa JS, dan supaya menyunting harga grosir cukup sekali ketik. --}}
      <label>Harga grosir <span class="bantuan">(jumlah minimal : harga per satuan, dipisah koma —
          mis. <code>5:12000, 20:11000</code>. Harga harus lebih murah dari harga satuan.)</span>
        <input name="grosir" id="grosir-isian" maxlength="400" placeholder="5:12000, 20:11000, 50:10500"></label>
    </div>

    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan</button>
    </footer>
  </form>
</dialog>
@endif

{{-- ================================================== PESANAN: pantau & surat jalan --}}
<section class="blok">
  <h2>
    @if ($pengelola) Order Distribusi dari Manajer @else Pesanan Saya @endif
    <small class="pudar">{{ $pesanan->count() }} pesanan</small>
  </h2>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Invoice</th><th>Koperasi</th><th>Penyerahan</th><th>Total</th><th>Status</th><th>Surat Jalan</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    @forelse ($pesanan as $p)
      <tr>
        <td data-l="Invoice"><a class="judul-sel" href="{{ route('toko.invoice', $p->id) }}">{{ $p->invoice ?: $p->kode }}</a>
          <small class="pudar">{{ tanggal_id($p->tanggal, 'd M Y H:i') }} · {{ \App\Models\Saldo::LABEL_KANAL[$p->kanal] ?? $p->kanal }}</small></td>
        <td data-l="Koperasi">{{ $p->nama_koperasi ?: '—' }}
          @if (! empty($p->nama_pemesan))<small class="pudar">{{ $p->nama_pemesan }}</small>@endif</td>
        <td data-l="Penyerahan"><span class="lencana">{{ $p->label_antar() }}</span></td>
        <td data-l="Total"><b>{{ \App\Models\Keuangan::rupiah($p->total) }}</b></td>
        <td data-l="Status"><span class="lencana">{{ $p->label_status() }}</span></td>
        <td data-l="Surat Jalan">
          @if ($p->surat_jalan)
            <code>{{ $p->surat_jalan }}</code>
            @if ($p->status === 'DITERIMA')<small class="pudar">ditandatangani {{ tanggal_id($p->diterima, 'd M Y') }}</small>@endif
          @else <span class="pudar">— terbit saat dilepas</span> @endif
        </td>
        <td class="kolom-aksi">
          <div class="tombol-baris">
            {{-- Semua tombol tindakan membuka SATU jendela modal (gaya Zoom/Angular
                 Hub): aksi = action formulir, mode = isian mana yang tampil.
                 Sebelumnya tiap tombol adalah formulir sebaris + <details> "Pantau",
                 jadi tidak ada jendela dan isian lampiran meminta tautan. --}}
            @if ($pengelola && $p->status === 'DIBAYAR')
              <button class="tombol mini" type="button" data-pesanan data-mode="lepas"
                      data-aksi="{{ route('toko.pesanan.lepas', $p->id) }}"
                      data-judul="Lepas &amp; Surat Jalan" data-antar="{{ $p->antar }}"
                      data-ringkas="{{ $p->invoice ?: $p->kode }} · {{ $p->nama_koperasi ?: 'Tanpa koperasi' }}">Lepas &amp; Surat Jalan</button>
            @endif
            @if ($pengelola && $p->status === 'DIPESAN' && $p->kanal === 'KAS')
              <button class="tombol samar mini" type="button" data-pesanan data-mode="bayar"
                      data-aksi="{{ route('toko.pesanan.bayar', $p->id) }}" data-judul="Tandai Dibayar"
                      data-ringkas="{{ $p->invoice ?: $p->kode }} · {{ \App\Models\Keuangan::rupiah($p->total) }}">Tandai Dibayar</button>
            @endif
            @if (in_array($p->status, ['DIKIRIM', 'DIAMBIL'], true))
              <button class="tombol utama mini" type="button" data-pesanan data-mode="terima"
                      data-aksi="{{ route('toko.pesanan.terima', $p->id) }}" data-judul="Tandai Diterima"
                      data-ringkas="{{ $p->invoice ?: $p->kode }} · {{ $p->label_antar() }}">Tandai Diterima</button>
            @endif
            <button class="tombol samar mini" type="button" data-pesanan data-mode="pantau"
                    data-aksi="{{ route('toko.pesanan.pantau', $p->id) }}" data-judul="Pantau Pesanan"
                    data-ringkas="{{ $p->invoice ?: $p->kode }} · {{ $p->label_status() }}">Pantau</button>
            @php $jejak = \App\Models\PesananTindakan::where('pesanan_id', $p->id)->orderByDesc('id')->limit(5)->get(); @endphp
            @if ($jejak->isNotEmpty())
              {{-- Riwayat = bacaan, bukan tindakan: tetap di baris supaya terlihat
                   tanpa membuka apa pun. --}}
              <details class="jejak-pesanan">
                <summary class="tombol samar mini">Riwayat ({{ $jejak->count() }})</summary>
                <ul class="jejak">
                  @foreach ($jejak as $t)
                    <li><b>{{ \App\Models\PesananTindakan::TINDAKAN[$t->tindakan] ?? $t->tindakan }}</b>
                      {{ $t->pesan }}
                      @if ($t->lampiran)
                        <a href="{{ preg_match('~^https?://~i', $t->lampiran) ? $t->lampiran : route('toko.lampiran', $t->id) }}"
                           rel="noopener noreferrer nofollow" target="_blank">lampiran</a>
                      @endif
                      <small class="pudar">{{ tanggal_id($t->dibuat, 'd M Y H:i') }}</small></li>
                  @endforeach
                </ul>
              </details>
            @endif
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="7" class="kosong">Belum ada order dari manajer.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</section>

{{-- Satu <dialog> melayani SEMUA tombol tindakan pesanan. Formulirnya satu; yang
     berubah hanya action, judul, dan isian yang tampil (mode). Ditaruh di luar
     tabel supaya tidak ikut tersalin saat baris dirender ulang. --}}
<dialog class="modal" id="pesanan-modal" aria-labelledby="pesanan-judul">
  <form method="post" id="pesanan-form" data-aksi>
    @csrf
    <header class="modal-kepala">
      <h2 id="pesanan-judul">Tindakan Pesanan</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan" id="pesanan-ringkas"></p>

      <label data-untuk="lepas">Cara penyerahan
        <select name="antar">
          @foreach (\App\Models\Pesanan::ANTAR as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select></label>

      <div data-untuk="pantau">
        <label>Tindakan
          <select name="tindakan">
            <option value="catatan">Catatan perjalanan</option>
            <option value="minta">Minta ubah</option>
            <option value="hapus">Minta hapus</option>
          </select></label>
        <label>Pesan<textarea name="pesan" rows="3" maxlength="400" placeholder="mis. kurir tiba Sabtu pagi"></textarea></label>
        <label>Lampiran <span class="bantuan">(gambar atau PDF, maks 10 MB — opsional)</span>
          <input type="file" name="lampiran_berkas" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
      </div>

      <p class="bantuan" data-untuk="bayar">Pesanan ini ditandai sudah dibayar. Lanjutkan?</p>
      <p class="bantuan" data-untuk="terima">Barang sudah diterima? Surat jalan akan ditandatangani atas nama koperasi.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Kirim</button>
    </footer>
  </form>
</dialog>

@push('skrip')
<script src="/js/hub-katalog.js?v={{ filemtime(public_path('js/hub-katalog.js')) }}" defer></script>
@endpush
@endsection

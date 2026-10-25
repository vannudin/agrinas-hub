@extends('layouts.utama')
@section('judul', 'Keranjang')
@section('isi')
@php
  $unit = array_sum(array_column($keranjang['baris'], 'jumlah'));
  $s = $kop ? ($saldos[$kop->id] ?? null) : null;
  $bisaPesan = $keranjang['kurang'] === [] && $keranjang['total'] > 0;
@endphp
<header class="kepala">
  <div>
    <h1>Keranjang</h1>
    <p class="tenang">
      @if ($kop) {{ $kop->kode }} — {{ $kop->nama }} · {{ $keranjang['jumlah'] }} jenis barang
      @else Keranjang belum terhubung ke koperasi. @endif
    </p>
  </div>
</header>
@include('toko._nav', ['mod' => 'keranjang', 'keranjang' => $keranjang])

{{-- Langkah belanja: memberi tahu posisi pembeli tanpa harus menebak. --}}
<ol class="krj-langkah" aria-label="Langkah pemesanan">
  <li class="ini" aria-current="step"><span>1</span> Keranjang</li>
  <li><span>2</span> Pesanan dibuat</li>
  <li><span>3</span> Barang disiapkan</li>
</ol>

@include('toko._saldo', ['kop' => $kop, 'saldos' => $saldos ?? []])

@if ($keranjang['baris'] === [])
  <section class="blok">
    <div class="krj-kosong">
      <p><b>Keranjang masih kosong.</b></p>
      <p class="pudar">Pilih produk di katalog untuk mulai berbelanja.</p>
      <a class="tombol utama" href="{{ route('toko') }}">Buka katalog</a>
    </div>
  </section>
@else
  {{--
    SATU kartu untuk daftar, satu kartu untuk ringkasan.

    Versi sebelumnya menaruh <section class="blok"> di dalam kisi ini dan tiap
    barang juga berkartu — hasilnya kotak di dalam kotak, dan halaman terlihat
    berantakan. Sekarang tidak ada .blok di jalur ini sama sekali: daftar barang
    adalah satu wadah putih dengan garis pemisah antar baris, seperti halaman
    keranjang toko daring.

    Lajur baris dan lajur judul kolom memakai `grid-template-columns` yang SAMA
    (lihat .krj-kepala-kolom & .krj-baris) — itu yang menjamin subtotal tidak
    pernah bergeser menempel ke nama produk, berapa pun panjang namanya.
  --}}
  <div class="krj">
    <div class="krj-utama">
      <div class="krj-kartu">
        <div class="krj-kartu-kepala">
          <h2>Isi Keranjang</h2>
          <span class="pudar">{{ $keranjang['jumlah'] }} jenis ·
            {{ number_format($unit, 0, ',', '.') }} unit</span>
        </div>

        <div class="krj-kepala-kolom" aria-hidden="true">
          <span>Produk</span><span>Harga</span><span>Jumlah &amp; aksi</span>
          <span style="text-align:right">Subtotal</span>
        </div>

        <ul class="krj-daftar">
          @foreach ($keranjang['baris'] as $b)
            <li class="krj-baris">
              <div class="krj-produk">
                <span class="krj-gambar" aria-hidden="true">{{ mb_strtoupper(mb_substr($b['nama'], 0, 2)) }}</span>
                <div class="krj-info">
                  <h3>{{ $b['nama'] }}</h3>
                  @if ($b['lokal'])
                    <p class="krj-meta">Produk lokal {{ $b['penjual'] }} · tersedia
                      {{ number_format($b['stok'], 0, ',', '.') }} {{ $b['satuan'] }}
                      @if ($b['jumlah'] > $b['stok']) · <span class="t-merah">melebihi sisa</span>@endif</p>
                  @else
                    <p class="krj-meta">{{ $b['kode'] }} · stok gudang
                      {{ number_format($b['stok'], 0, ',', '.') }} {{ $b['satuan'] }}
                      @if ($b['jumlah'] > $b['stok']) · <span class="t-merah">melebihi stok</span>@endif</p>
                  @endif
                  <p class="krj-harga">
                    @if ($b['grosir'])
                      <b>{{ \App\Models\Keuangan::rupiah($b['harga']) }}</b>
                      <s>{{ \App\Models\Keuangan::rupiah($b['harga_dasar']) }}</s>
                      <span class="krj-tanda">harga grosir</span>
                    @else
                      {{ \App\Models\Keuangan::rupiah($b['harga']) }}
                      @if ($b['grosir_mulai'] > 0)
                        <span class="krj-tanda">beli {{ number_format($b['grosir_mulai'], 0, ',', '.') }}+ jadi grosir</span>
                      @endif
                    @endif
                    <span class="pudar">/ {{ $b['satuan'] }}</span>
                  </p>
                </div>
              </div>

              {{-- Harga lajur tersendiri hanya di desktop; di ponsel harga
                   menempel di bawah nama (dibaca sebagai keterangan). --}}
              <div class="krj-harga-kolom">
                @if ($b['grosir'])
                  <b>{{ \App\Models\Keuangan::rupiah($b['harga']) }}</b>
                  <s class="pudar">{{ \App\Models\Keuangan::rupiah($b['harga_dasar']) }}</s>
                @else
                  {{ \App\Models\Keuangan::rupiah($b['harga']) }}
                @endif
              </div>

              {{-- Ubah jumlah + hapus dalam SATU baris, bersebelahan.
                   Tombol hapus dulu punya lajur sendiri di ujung kanan, sehingga
                   posisinya jauh dari tombol Ubah dan terlihat seperti hiasan
                   yang nyasar. Sekarang keduanya berdampingan: aksi atas baris
                   ini ada di satu tempat, dan tombol hapus berwarna merah
                   supaya beda tegas dari aksi biasa. --}}
              <div class="krj-aksi">
                <form class="krj-jumlah" method="post" action="{{ route('toko.keranjang.ubah') }}">
                  @csrf
                  @if ($b['lokal'])
                    <input type="hidden" name="pasokan_id" value="{{ $b['id'] }}">
                  @else
                    <input type="hidden" name="barang_id" value="{{ $b['id'] }}">
                  @endif
                  <label class="sr" for="u{{ $b['kunci'] }}">Jumlah {{ $b['nama'] }}</label>
                  <input id="u{{ $b['kunci'] }}" type="number" name="jumlah"
                         value="{{ $b['jumlah'] }}" min="0" max="1000000" step="1">
                  <button class="tombol samar mini" type="submit">Ubah</button>
                </form>

                <form class="krj-hapus" method="post" action="{{ route('toko.keranjang.ubah') }}">
                  @csrf
                  @if ($b['lokal'])
                    <input type="hidden" name="pasokan_id" value="{{ $b['id'] }}">
                  @else
                    <input type="hidden" name="barang_id" value="{{ $b['id'] }}">
                  @endif
                  <input type="hidden" name="jumlah" value="0">
                  <button class="krj-hapus-tombol" type="submit"
                          aria-label="Hapus {{ $b['nama'] }} dari keranjang"
                          title="Hapus dari keranjang">
                    <svg class="krj-hapus-ikon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true" focusable="false">
                      <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>
                      <path d="M10 11v5M14 11v5"/>
                    </svg>
                    <span class="krj-hapus-teks">Hapus</span>
                  </button>
                </form>
              </div>

              <div class="krj-sub"><small>Subtotal</small>
                {{ \App\Models\Keuangan::rupiah($b['subtotal']) }}</div>
            </li>
          @endforeach
        </ul>

        <div class="krj-kaki">
          <a class="tombol samar" href="{{ route('toko') }}">&larr; Lanjut belanja</a>
          <span class="pudar">{{ number_format($unit, 0, ',', '.') }} unit ·
            {{ $keranjang['jumlah'] }} jenis</span>
        </div>
      </div>
    </div>

    <aside class="krj-ringkas">
      <div class="krj-ringkas-kotak">
        <h2>Ringkasan Pesanan</h2>

        <dl class="krj-total">
          <div><dt>Jumlah barang</dt><dd>{{ number_format($unit, 0, ',', '.') }} unit</dd></div>
          <div><dt>Jenis produk</dt><dd>{{ $keranjang['jumlah'] }}</dd></div>
          <div class="krj-total-baris"><dt>Total</dt>
            <dd>{{ \App\Models\Keuangan::rupiah($keranjang['total']) }}</dd></div>
          @if ($s)
            <div class="pudar"><dt>Saldo Agrinas</dt>
              <dd>{{ \App\Models\Keuangan::rupiah($s['sisa']) }}</dd></div>
            <div class="pudar"><dt>Sisa setelah pesanan</dt>
              <dd>{{ \App\Models\Keuangan::rupiah(max(0, $s['sisa'] - $keranjang['total'])) }}</dd></div>
          @endif
        </dl>

        @if ($keranjang['kurang'] !== [])
          <p class="pesan rusak" role="alert">Stok gudang tidak cukup untuk:
            {{ implode(', ', $keranjang['kurang']) }}. Perbaiki jumlahnya dulu.</p>
        @endif

        <form method="post" action="{{ route('toko.checkout') }}" class="krj-checkout">
          @csrf

          <fieldset class="krj-pilih">
            <legend>Bayar dengan</legend>
            <label class="krj-opsi">
              <input type="radio" name="kanal" value="AGRN" checked
                @if (! $s || $s['sisa'] < $keranjang['total']) disabled @endif>
              <span><b>Saldo Agrinas</b>
                <small>{{ $s ? \App\Models\Keuangan::rupiah($s['sisa']) : '—' }} ·
                  dipotong saat pesanan dibuat</small></span>
            </label>
            <label class="krj-opsi">
              <input type="radio" name="kanal" value="KAS">
              <span><b>Kas koperasi</b>
                <small>Dicatat sebagai pengeluaran saat ditandai dibayar</small></span>
            </label>
          </fieldset>

          <fieldset class="krj-pilih">
            <legend>Cara penyerahan</legend>
            <label class="krj-opsi">
              <input type="radio" name="antar" value="KIRIM" checked>
              <span><b>Dikirim</b><small>Agrinas mengantar + surat jalan</small></span>
            </label>
            <label class="krj-opsi">
              <input type="radio" name="antar" value="AMBIL">
              <span><b>Diambil sendiri</b><small>Status “Siap Diambil” di gudang</small></span>
            </label>
          </fieldset>

          <label class="krj-catatan">Catatan
            <input name="catatan" maxlength="200" placeholder="mis. kirim pagi, hubungi Pak Adi"></label>

          <button class="tombol utama lebar" type="submit"
            @if (! $bisaPesan) disabled @endif>Buat Pesanan &amp; Invoice</button>

          <p class="bantuan">Tidak ada pembayaran daring di halaman ini.</p>
        </form>
      </div>
    </aside>
  </div>
@endif
@endsection

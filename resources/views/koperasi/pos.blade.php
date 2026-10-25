@extends('layouts.utama')

@section('judul', 'POS Penjualan')
@section('isi')
@include('koperasi._kepala', ['ruteBagian' => 'kop.pos'])

@if ($koperasi)
  @php
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
    // Tombol kasir hanya untuk MANAJER. Alasannya bukan soal izin teknis saja:
    // yang menyerahkan barang & menerima uang di gerai adalah pengurus koperasi,
    // jadi catatan penjualan harus datang dari dia — bukan dari pusat/PIC yang
    // tidak memegang kasnya. Pengelola tetap MELIHAT seluruh riwayat & rekapnya.
    $kasir = $bolehTulis;
  @endphp

  @include('koperasi._nav', ['mod' => 'pos', 'koperasi' => $koperasi])

  {{-- REKAP. Uang yang masuk hari ini harus terbaca sebelum kasir mulai
       mencatat, karena itu yang dicocokkan dengan laci saat tutup toko.
       Kartunya memakai `.kop-stat` (ikon + angka besar), sama dengan halaman
       Data — supaya tiga halaman modul ini terbaca sebagai satu aplikasi. --}}
  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('keuangan', 'ik') !!}Rekap Penjualan</span></h2>
    <div class="petak">
      <div class="kop-stat">
        <span class="kop-stat-ikon">{!! ikon('keuangan', 'ik') !!}</span>
        <span class="kop-stat-teks">
          <small>Hari Ini</small>
          <b>{{ $rp($rekap['hari']['total']) }}</b>
          <i>{{ $rp($rekap['hari']['nota']) }} nota · Rp</i>
        </span>
      </div>
      <div class="kop-stat">
        <span class="kop-stat-ikon">{!! ikon('setuju', 'ik') !!}</span>
        <span class="kop-stat-teks">
          <small>7 Hari Terakhir</small>
          <b>{{ $rp($rekap['minggu']['total']) }}</b>
          <i>{{ $rp($rekap['minggu']['nota']) }} nota · Rp</i>
        </span>
      </div>
      <div class="kop-stat">
        <span class="kop-stat-ikon">{!! ikon('analisa', 'ik') !!}</span>
        <span class="kop-stat-teks">
          <small>Bulan Ini</small>
          <b>{{ $rp($rekap['bulan']['total']) }}</b>
          <i>{{ $rp($rekap['bulan']['nota']) }} nota · Rp</i>
        </span>
      </div>
      <div class="kop-stat">
        <span class="kop-stat-ikon">{!! ikon('siaran', 'ik') !!}</span>
        <span class="kop-stat-teks">
          <small>Belum Dibayar</small>
          <b>{{ $rp($rekap['hutang']) }}</b>
          <i>nota berstatus hutang · Rp</i>
        </span>
      </div>
    </div>
    @if ($rekap['terlaris']->isNotEmpty())
      <h3 class="petak-judul">Terlaris Bulan Ini</h3>
      <ul class="daftar-alasan">
        @foreach ($rekap['terlaris'] as $t)
          <li>{{ $t->nama }} — {{ $rp($t->jumlah) }} terjual, {{ $rp($t->nilai) }} Rp</li>
        @endforeach
      </ul>
    @endif
  </section>

  @if ($kasir)
    <section class="blok">
      <h2><span class="kop-judul">{!! ikon('keranjang', 'ik') !!}Kasir — Nota Baru</span></h2>
      <p class="bantuan">
        Ketuk barang untuk memasukkannya ke keranjang, lalu simpan. Stok berkurang
        otomatis dan tercatat di riwayat Stok Barang, jadi angka stok dan angka
        penjualan tidak bisa berbeda.
      </p>

      {{-- Kolom cari memakai form GET biasa: tanpa JavaScript pun daftar barang
           bisa disaring, dan hasilnya halaman yang sama. Tombol scan di
           sebelahnya MEMAKAI alat yang sudah ada (`#scan-modal` +
           hub-stok-koperasi.js) — hasilnya masuk ke kolom ini lalu disaring,
           jadi tidak ada logika scan kedua yang bisa menyimpang. --}}
      <form class="saring" id="pos-form-cari" method="get" action="{{ route('kop.pos', ['koperasi' => $koperasi->id]) }}">
        <label class="cari">
          <span class="sr">Cari barang</span>
          <input type="search" name="q" value="{{ $cari }}"
                 placeholder="Cari nama, kode, atau barcode barang…" autocomplete="off">
        </label>
        <button class="tombol samar ikon-kiri" type="button" data-buka="scan-modal"
                data-scan-untuk="pos-form-cari" data-scan-isi="q"
                title="Pindai barcode dengan kamera, lalu daftar di bawah disaring">{!! ikon('barcode', 'ik kecil') !!}<span>Scan</span></button>
        <button class="tombol" type="submit">Saring</button>
        @if ($cari !== '')
          <a class="tombol samar" href="{{ route('kop.pos', ['koperasi' => $koperasi->id]) }}">Bersihkan</a>
        @endif
      </form>

      @if ($barang->isEmpty())
        <p class="kosong">
          Belum ada barang di inventaris koperasi ini.
          @if ($bolehTulis)
            Tambahkan dulu di <a href="{{ route('distribusi.stok-koperasi') }}">Stok Barang</a> —
            kasir hanya bisa menjual barang yang sudah terdaftar.
          @else
            Daftar barang diisi manajer koperasi di halaman Stok Barang.
          @endif
        </p>
      @else
        <form method="post" action="{{ route('kop.pos.jual', ['koperasi' => $koperasi->id]) }}" id="pos-form">
          @csrf
          <input type="hidden" name="item" value="[]" data-pos-item>

          <h3 class="petak-judul">Barang</h3>
          <div class="tabel-bungkus">
            <table class="tabel-pos">
              <thead><tr>
                <th>Barang</th><th class="angka-kanan">Harga</th>
                <th class="angka-kanan">Stok</th><th class="kolom-aksi">Kasir</th>
              </tr></thead>
              <tbody>
                @foreach ($barang as $b)
                  <tr>
                    <td data-l="Barang">
                      {{ $b->nama }}
                      @if ($b->kode !== '') <small class="pudar">{{ $b->kode }}</small> @endif
                      @if ($b->menipis()) <span class="lencana s-DITOLAK">Menipis</span> @endif
                      {{-- Promo terlihat SEBELUM kasir mengetuk: pembeli sering
                           bertanya "yang diskon yang mana?". --}}
                      @if ($b->promo_jenis !== '')
                        <span class="lencana s-DISETUJUI">{{ $b->labelPromo() }}</span>
                      @endif
                    </td>
                    {{-- Satuan menempel pada harga ("625.000 / SAK"), bukan kolom
                         sendiri: kasir membaca harga dan satuannya sebagai satu
                         besaran. Di ponsel ini juga memangkas SATU sel per barang
                         (terukur: 5 sel = kartu 205px; dengan 4 sel + dua sel
                         pendek sebaris turun jadi ±100px, dan 300 barang
                         sebelumnya menuntut gulir 61.500px). --}}
                    <td data-l="Harga" class="angka-kanan">
                      {{ $rp($b->harga) }} <small class="pudar">/ {{ $b->satuan }}</small>
                    </td>
                    <td data-l="Stok" class="angka-kanan">{{ $rp($b->stok) }}</td>
                    <td class="kolom-aksi">
                      {{-- Stok habis: tombolnya MATI, bukan dibiarkan lalu gagal
                           saat disimpan — kegagalan yang bisa dicegah di depan
                           tidak perlu dijadikan pesan galat. --}}
                      <button class="tombol mini tambah" type="button"
                              data-id="{{ $b->id }}" data-nama="{{ $b->nama }}"
                              data-satuan="{{ $b->satuan }}" data-harga="{{ (int) $b->harga }}"
                              data-stok="{{ (int) $b->stok }}"
                              {{-- Promo dibawa apa adanya; keranjang hanya MENIRU
                                   rumus server untuk angka yang dibaca kasir. --}}
                              data-promo-jenis="{{ $b->promo_jenis }}"
                              data-promo-nilai="{{ (int) $b->promo_nilai }}"
                              data-promo-min="{{ (int) $b->promo_min }}"
                              data-promo-bonus="{{ $b->promo_bonus_id > 0 ? ($bonusNama[$b->promo_bonus_id] ?? '') : '' }}"
                              @disabled((int) $b->stok <= 0)>
                        {{ (int) $b->stok <= 0 ? 'Stok habis' : '+ Keranjang' }}
                      </button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <h3 class="petak-judul">Keranjang</h3>
          <div class="tabel-bungkus">
            <table>
              <thead><tr>
                <th>Barang</th><th class="angka-kanan">Harga</th><th class="angka-kanan">Jumlah</th>
                <th class="angka-kanan">Subtotal</th><th class="kolom-aksi">Aksi</th>
              </tr></thead>
              <tbody data-pos-keranjang>
                <tr><td colspan="5" class="kosong" data-pos-kosong>Keranjang masih kosong — ketuk “+ Keranjang” pada barang di atas.</td></tr>
              </tbody>
            </table>
          </div>

          <div class="grid-form kop-form-2">
            <label>Total
              <input readonly value="0" data-pos-total-hidup>
              <span class="bantuan">dihitung ulang di server saat disimpan — angka ini hanya untuk kasir membaca</span>
            </label>
            <label>Pembeli <span class="bantuan">(opsional)</span>
              <input name="pembeli" maxlength="120" placeholder="Nama pembeli"></label>
            <label>Kontak <span class="bantuan">(opsional)</span>
              <input name="kontak" maxlength="40" placeholder="Nomor HP pembeli"></label>
            <label>Cara Bayar
              <select name="bayar" required data-pos-bayar>
                @foreach ($bayar as $k => $l)
                  <option value="{{ $k }}" @selected($k === 'TUNAI')>{{ $l }}</option>
                @endforeach
              </select></label>
            <label>Jumlah Dibayar
              <input type="number" name="dibayar" min="0" max="999999999" step="1" value="0" data-pos-dibayar></label>
            <label class="lebar-penuh">Catatan <span class="bantuan">(opsional)</span>
              <input name="catatan" maxlength="200" placeholder="Mis. dibeli untuk posyandu"></label>
          </div>

          <p class="bantuan" data-pos-kembali hidden>Kembalian: <b>0</b> Rp</p>
          <div class="tombol-baris pos-aksi">
            <button class="tombol samar" type="button" data-pos-kosongkan>Kosongkan Keranjang</button>
            <button class="tombol utama" type="submit">Simpan Nota</button>
          </div>
        </form>
      @endif
    </section>
  @endif

  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('dokumen', 'ik') !!}Riwayat Nota</span></h2>
    <div class="tabel-bungkus">
      <table class="tabel-nota">
        <thead><tr>
          <th>Nomor</th><th>Tanggal</th><th>Pembeli</th><th class="angka-kanan">Total</th>
          <th>Bayar</th><th>Status</th><th class="kolom-aksi">Aksi</th>
        </tr></thead>
        <tbody>
          @forelse ($nota as $n)
            <tr>
              <td data-l="Nomor"><code>{{ $n->nomor }}</code></td>
              <td data-l="Tanggal">{{ tanggal_id($n->tanggal) }}</td>
              <td data-l="Pembeli">
                {{ $n->pembeli !== '' ? $n->pembeli : '—' }}
                @php $it = $itemPeta[$n->id] ?? collect(); @endphp
                @if ($it->isNotEmpty())
                  <small class="pudar">{{ $it->count() }} barang: {{ $it->take(3)->pluck('nama')->implode(', ') }}{{ $it->count() > 3 ? '…' : '' }}</small>
                @endif
              </td>
              <td data-l="Total" class="angka-kanan">{{ $rp($n->total) }}</td>
              <td data-l="Bayar">{{ App\Models\KoperasiJual::BAYAR[$n->bayar] ?? $n->bayar }}</td>
              <td data-l="Status">
                <span class="lencana {{ $n->status === App\Models\KoperasiJual::BATAL ? 's-DITOLAK' : ($n->status === App\Models\KoperasiJual::HUTANG ? 's-DIAJUKAN' : 's-DISETUJUI') }}">
                  {{ $n->labelStatus() }}</span>
              </td>
              <td class="kolom-aksi">
                <a class="tombol mini" href="{{ route('kop.nota', ['id' => $n->id, 'koperasi' => $koperasi->id]) }}">Nota</a>
                @if ($kasir && $n->status !== App\Models\KoperasiJual::BATAL)
                  {{-- Konfirmasi lebih dulu: pembatalan mengembalikan stok, dan
                       itu perubahan yang tidak boleh terjadi karena salah ketuk.
                       `data-konfirmasi` ditangani hub.js (satu tempat, semua
                       halaman) — bukan skrip baru di sini. --}}
                  <form method="post" action="{{ route('kop.pos.batal', ['id' => $n->id, 'koperasi' => $koperasi->id]) }}"
                        class="sebaris" data-konfirmasi="Batalkan nota {{ $n->nomor }}? Stok barangnya akan dikembalikan.">
                    @csrf
                    <button class="tombol mini samar" type="submit">Batalkan</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="kosong">
              {{ $cari !== '' ? 'Tidak ada nota yang cocok dengan pencarian ini.' : 'Belum ada penjualan tercatat.' }}
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
  </section>
@endif
@endsection

@push('skrip')
{{-- Alat scan dipasang di halaman ini juga: kolom pencarian POS sering diisi
     dengan memindai barcode produk, dan alatnya sudah ada — yang kurang cuma
     markup-nya. Tanpa `scanCari`, hasil scan TIDAK memanggil server: ia hanya
     mengisi kolom cari lalu formulirnya dikirim, sehingga penyaringan tetap
     dikerjakan jalur yang sudah diuji. --}}
@include('partials.scan-barcode', [
    'scanPesan' => 'Arahkan kamera ke barcode produk — kolom pencarian akan terisi dan daftar barang disaring.',
])
<script src="/js/hub-kop-pos.js?v={{ filemtime(public_path('js/hub-kop-pos.js')) }}" defer></script>
<script src="/js/hub-stok-koperasi.js?v={{ filemtime(public_path('js/hub-stok-koperasi.js')) }}" defer></script>
@endpush

@extends('layouts.utama')

@section('judul', 'Nota '.$nota->nomor)
@section('isi')
{{-- Halaman nota berdiri sendiri (bukan modal): tujuannya DIBACA ULANG dan
     DICETAK, dan modal menyembunyikan justru bagian yang perlu dipandang.
     Tampilan cetaknya diatur di CSS (@media print), bukan berkas terpisah. --}}
{{-- Ukuran kertas ditentukan dari SINI, bukan dari hub.css: nota dicetak ke
     printer kasir (kertas gulung 80mm), sedangkan halaman lain memakai A4.
     Gaya ini yang terakhir dibaca, jadi ia menang atas `@page` di hub.css.
     Tinggi 297mm (panjang gulungan standar) — `80mm auto` DITOLAK Chrome
     (aturannya dibuang, kertas jatuh ke A4); sudah diuji. Ganti angka 297 di
     sini bila gulungan kasir Bos lebih pendek, mis. 80mm 150mm. --}}
<style id="gaya-kertas">@page { size: 80mm 297mm; margin: 2mm 3mm; }</style>

<section class="blok nota-cetak">
  {{-- Kop nota: identitas koperasi harus ikut tercetak. Sebelumnya blok ini
       memakai kelas `.kepala` yang disembunyikan aturan cetak global, jadi
       lembar yang keluar dari printer tidak menyebut koperasi mana pun. --}}
  <header class="kepala nota-kop">
    <div>
      <h1>{{ $koperasi->nama }}</h1>
      <p class="tenang">
        {{ $koperasi->wilayah ?: '—' }}@if ($koperasi->alamat) · {{ $koperasi->alamat }}@endif
      </p>
      <p class="tenang">Nota <b>{{ $nota->nomor }}</b> · {{ tanggal_id($nota->tanggal) }}</p>
    </div>
    <div class="tombol-baris">
      <a class="tombol samar" href="{{ route('kop.pos', ['koperasi' => $koperasi->id]) }}">Kembali ke POS</a>
      {{-- Dua tombol = dua ukuran kertas. Kasir memakai yang pertama (80mm);
           A4 hanya untuk arsip/laporan. --}}
      <button class="tombol" type="button" data-cetak="struk">Cetak Struk 80mm</button>
      <button class="tombol samar" type="button" data-cetak="a4">Cetak A4</button>
    </div>
  </header>

  @if ($nota->status === App\Models\KoperasiJual::BATAL)
    <p class="pesan rusak" role="alert">
      Nota ini sudah <b>dibatalkan</b> — stok barangnya sudah dikembalikan ke gudang koperasi.
    </p>
  @endif

  <dl class="rincian">
    <dt>Tanggal</dt><dd>{{ tanggal_id($nota->tanggal) }}</dd>
    <dt>Kasir</dt><dd>{{ $kasir ?: '—' }}</dd>
    <dt>Pembeli</dt><dd>{{ $nota->pembeli !== '' ? $nota->pembeli : '—' }}</dd>
    <dt>Kontak</dt><dd>{{ $nota->kontak !== '' ? $nota->kontak : '—' }}</dd>
    <dt>Cara Bayar</dt><dd>{{ App\Models\KoperasiJual::BAYAR[$nota->bayar] ?? $nota->bayar }}</dd>
    <dt>Status</dt>
    <dd><span class="lencana {{ $nota->status === App\Models\KoperasiJual::BATAL ? 's-DITOLAK' : ($nota->status === App\Models\KoperasiJual::HUTANG ? 's-DIAJUKAN' : 's-DISETUJUI') }}">{{ $nota->labelStatus() }}</span></dd>
    @if ($nota->catatan !== '')
      <dt>Catatan</dt><dd>{{ $nota->catatan }}</dd>
    @endif
  </dl>

  {{-- DUA kolom, bukan lima. Struk kertas 80mm hanya selebar ±74mm: lima kolom
       di situ membuat setiap nama barang terpotong dan angka rupiah turun baris
       (keluhan "tata letak kolom berantakan"). Jumlah × harga dipindah ke bawah
       nama barang — bentuk yang dipakai struk kasir mana pun, dan terbaca sama
       di ponsel. --}}
  <div class="tabel-bungkus">
    <table class="nota-item">
      <thead><tr>
        <th>Barang</th><th class="angka-kanan">Subtotal</th>
      </tr></thead>
      <tbody>
        @foreach ($item as $it)
          @php
            $gratis = (float) $it->harga === 0.0 && (float) $it->subtotal === 0.0;
            $diskon = (float) $it->subtotal < 0;
          @endphp
          <tr class="{{ $diskon ? 'baris-diskon' : ($gratis ? 'baris-gratis' : '') }}">
            <td data-l="Barang">
              <span class="nota-nama">{{ $it->nama }}</span>
              @if ($gratis)
                <span class="lencana s-DISETUJUI">GRATIS</span>
              @elseif (! $diskon)
                <small class="pudar nota-rinci">
                  {{ number_format((float) $it->jumlah, 0, ',', '.') }} {{ $it->satuan }}
                  × {{ number_format((float) $it->harga, 0, ',', '.') }}
                </small>
              @endif
            </td>
            <td data-l="Subtotal" class="angka-kanan">
              {{ $diskon ? '−' : '' }}{{ number_format(abs((float) $it->subtotal), 0, ',', '.') }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- Total DI LUAR tabel. Sebelumnya ia `<tfoot>` ber-colspan: di ponsel sel
       tabel dilipat jadi kartu sehingga label Total/Dibayar/Kembalian menumpuk
       dan angkanya tidak lagi satu kolom dengan labelnya. --}}
  <dl class="nota-total">
    <div><dt>Total</dt><dd>{{ number_format((float) $nota->total, 0, ',', '.') }}</dd></div>
    <div><dt>Dibayar</dt><dd>{{ number_format((float) $nota->dibayar, 0, ',', '.') }}</dd></div>
    @if ($nota->kembali > 0)
      <div><dt>Kembalian</dt><dd>{{ number_format((float) $nota->kembali, 0, ',', '.') }}</dd></div>
    @endif
  </dl>

  <p class="nota-terima">Terima kasih — barang yang sudah dibeli tidak dapat ditukar
    tanpa nota ini.</p>

  <p class="bantuan">
    Harga di nota ini DISALIN saat transaksi dibuat, bukan ditautkan ke barangnya —
    jadi menaikkan harga jual besok tidak mengubah nota hari ini.
  </p>
</section>
@endsection

@push('skrip')
{{-- Pemilih ukuran kertas: struk kasir 80mm (bawaan) atau A4 untuk arsip.
     `@page` harus diganti SEBELUM dialog cetak dibuka, dan aturan `@page`
     yang disisipkan belakangan memang yang dipakai peramban. --}}
<script>
  (function () {
    var gaya = document.getElementById('gaya-kertas');
    document.querySelectorAll('[data-cetak]').forEach(function (b) {
      b.addEventListener('click', function () {
        var a4 = b.getAttribute('data-cetak') === 'a4';
        gaya.textContent = a4 ? '@page { size: A4; margin: 12mm; }' : '@page { size: 80mm 297mm; margin: 2mm 3mm; }';
        document.body.classList.toggle('kertas-a4', a4);
        window.print();
      });
    });
  })();
</script>
@endpush

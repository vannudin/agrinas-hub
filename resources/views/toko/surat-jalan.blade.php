@extends('layouts.utama')
@section('judul', 'Surat Jalan '.$pesanan->surat_jalan)
@section('isi')
{{-- Surat jalan: dokumen serah-terima yang ditandatangani manajer saat barang
     diterima. Halaman cetak — simpan sebagai PDF lewat tombol Cetak.
     Nomor terbit otomatis dari sistem saat pengelola melepas barang, jadi tidak
     ada penomoran manual yang bisa bentrok. --}}
<header class="kepala">
  <div>
    <h1>Surat Jalan</h1>
    <p class="tenang">{{ $pesanan->surat_jalan }} · invoice {{ $pesanan->invoice ?: $pesanan->kode }}</p>
  </div>
  <div class="tombol-baris">
    <button class="tombol" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <a class="tombol samar" href="{{ route('toko.kelola') }}">Kembali</a>
  </div>
</header>

<article class="invoice-lembar">
  <div class="invoice-kepala">
    <div>
      <img src="/img/logo-agrinas.png" alt="Agrinas Pangan Nusantara" width="150" height="72">
      <h2>PT Agrinas Pangan Nusantara (Persero)</h2>
      <small class="pudar">Surat jalan pengiriman barang — bukan bukti tagihan</small>
    </div>
    <div class="invoice-nomor">
      <small class="pudar">Nomor surat jalan</small>
      <b>{{ $pesanan->surat_jalan }}</b>
      <span class="invoice-lencana">{{ $pesanan->label_status() }}</span>
    </div>
  </div>

  <div class="invoice-pihak">
    <div>
      <small>Dikirim kepada</small>
      <b>{{ $koperasi?->nama ?? '—' }}</b>
      <span class="pudar">{{ $koperasi?->kode }}{{ $koperasi?->alamat ? ' · '.$koperasi->alamat : '' }}</span>
    </div>
    <div>
      <small>Cara penyerahan</small>
      <b>{{ $pesanan->label_antar() }}</b>
      <span class="pudar">
        @if ($pesanan->antar === 'AMBIL')
          Barang diambil sendiri di gudang Agrinas.
        @else
          Barang diantar ke alamat koperasi.
        @endif
      </span>
    </div>
    <div>
      <small>Tanggal dilepas</small>
      <b>{{ $pesanan->dikirim ? tanggal_id($pesanan->dikirim, 'd M Y H:i') : '—' }}</b>
      <span class="pudar">Pesanan {{ $pesanan->kode }}</span>
    </div>
    <div>
      <small>Diterima oleh</small>
      <b>{{ $pemesan?->nama ?? '—' }}</b>
      <span class="pudar">{{ $pesanan->diterima ? 'pada '.tanggal_id($pesanan->diterima, 'd M Y H:i') : 'belum ditandatangani' }}</span>
    </div>
  </div>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Barang</th><th>Satuan</th><th>Jumlah dikirim</th></tr></thead>
    <tbody>
    @foreach ($item as $i)
      <tr>
        <td data-l="Barang"><span class="judul-sel">{{ $i->nama }}</span></td>
        <td data-l="Satuan">{{ $i->satuan }}</td>
        <td data-l="Jumlah"><b>{{ number_format($i->jumlah, 0, ',', '.') }}</b></td>
      </tr>
    @endforeach
    </tbody>
  </table>
  </div>

  <div class="ttd-kisi">
    <div>
      <small>Diserahkan oleh</small>
      <span class="ttd-ruang"></span>
      <b>Gudang Agrinas Pangan Nusantara</b>
    </div>
    <div>
      <small>Diterima oleh</small>
      <span class="ttd-ruang"></span>
      <b>{{ $pemesan?->nama ?? ($koperasi?->nama ?? 'Manajer koperasi') }}</b>
    </div>
  </div>

  <p class="invoice-catatan">
    Barang di atas diterima dalam jumlah dan kondisi baik. Tanda tangan di halaman ini
    adalah bukti serah-terima. Simpan sebagai PDF lewat tombol <b>Cetak / Simpan PDF</b>.
  </p>
</article>
@endsection

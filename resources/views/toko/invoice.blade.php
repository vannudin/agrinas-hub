@extends('layouts.utama')
@section('judul', 'Invoice '.$pesanan->invoice)
@section('isi')
<header class="kepala">
  <div>
    <h1>Invoice</h1>
    <p class="tenang">{{ $pesanan->invoice }} · {{ $pesanan->kode }}</p>
  </div>
  <div class="tombol-baris">
    <button class="tombol" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <a class="tombol samar" href="{{ route('toko.pesanan') }}">Kembali</a>
  </div>
</header>

<article class="invoice-lembar">
  <div class="invoice-kepala">
    <div>
      <img src="/img/logo-agrinas.png" alt="Agrinas Pangan Nusantara" width="150" height="72">
      <h2>PT Agrinas Pangan Nusantara (Persero)</h2>
      <small class="pudar">Ruang kerja digital Agrinas Hub — invoice pembelian barang</small>
    </div>
    <div class="invoice-nomor">
      <small class="pudar">Nomor invoice</small>
      <b>{{ $pesanan->invoice }}</b>
      <span class="invoice-lencana">{{ $pesanan->label_status() }}</span>
    </div>
  </div>

  <div class="invoice-pihak">
    <div>
      <small>Ditagihkan kepada</small>
      <b>{{ $koperasi?->nama ?? '—' }}</b>
      <span class="pudar">{{ $koperasi?->kode }}{{ $koperasi?->alamat ? ' · '.$koperasi->alamat : '' }}</span>
    </div>
    <div>
      <small>Tanggal pesanan</small>
      <b>{{ tanggal_id($pesanan->tanggal, 'd M Y H:i') }}</b>
      <span class="pudar">Nomor pesanan {{ $pesanan->kode }}</span>
    </div>
    <div>
      <small>Dipesan oleh</small>
      <b>{{ $pemesan?->nama ?? '—' }}</b>
      <span class="pudar">{{ $pemesan?->email }}</span>
    </div>
    <div>
      <small>Cara pembayaran</small>
      <b>{{ \App\Models\Saldo::LABEL_KANAL[$pesanan->kanal] ?? $pesanan->kanal }}</b>
      <span class="pudar">
        @if ($pesanan->kanal === 'AGRN')
          Dipotong dari modal belanja Agrinas — tidak dapat ditarik atau diuangkan.
        @else
          Dibayar dari kas koperasi{{ $pesanan->dibayar ? ' pada '.tanggal_id($pesanan->dibayar, 'd M Y') : ' (menunggu pembayaran)' }}.
        @endif
      </span>
    </div>
  </div>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Barang</th><th>Satuan</th><th>Jumlah</th><th>Harga</th><th>Subtotal</th></tr></thead>
    <tbody>
    @foreach ($item as $i)
      <tr>
        <td data-l="Barang"><span class="judul-sel">{{ $i->nama }}</span></td>
        <td data-l="Satuan">{{ $i->satuan }}</td>
        <td data-l="Jumlah">{{ number_format($i->jumlah, 0, ',', '.') }}</td>
        <td data-l="Harga">{{ \App\Models\Keuangan::rupiah($i->harga) }}</td>
        <td data-l="Subtotal"><b>{{ \App\Models\Keuangan::rupiah($i->subtotal()) }}</b></td>
      </tr>
    @endforeach
    </tbody>
  </table>
  </div>

  <p class="invoice-total">Total: {{ \App\Models\Keuangan::rupiah($pesanan->total) }}</p>

  @if (trim((string) $pesanan->catatan) !== '')
    <p class="invoice-catatan"><b>Catatan:</b> {{ $pesanan->catatan }}</p>
  @endif
  <p class="invoice-catatan">
    Invoice ini terbit otomatis dari Agrinas Hub. Simpan sebagai PDF lewat tombol
    <b>Cetak / Simpan PDF</b> (pilih “Simpan sebagai PDF” pada dialog cetak).
  </p>
</article>
@endsection

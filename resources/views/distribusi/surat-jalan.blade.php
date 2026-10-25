@extends('layouts.utama')
@section('judul', 'Surat Jalan '.$dis->kode)
@section('isi')
{{-- Surat jalan siap cetak. Isinya sama dengan berkas PDF yang dibuat server
     (distribusi.surat-jalan-pdf): satu bentuk, dua penyajian. --}}
<header class="kepala">
  <div>
    <h1>Surat Jalan</h1>
    <p class="tenang">{{ $dis->kode }} · {{ $dis->koperasi->nama ?? '—' }}</p>
  </div>
  <div class="tombol-baris">
    <a class="tombol utama" href="{{ route('distribusi.pdf', $dis->id) }}">Unduh PDF</a>
    <button class="tombol" type="button" onclick="window.print()">Cetak</button>
    <a class="tombol samar" href="{{ route('distribusi.daftar') }}">Kembali</a>
  </div>
</header>

<section class="blok">
<article class="invoice-lembar">
  <div class="invoice-kepala">
    <div>
      <img src="/img/logo-agrinas.png" alt="Agrinas Pangan Nusantara" width="150" height="72">
      <h2>PT Agrinas Pangan Nusantara (Persero)</h2>
      <small class="pudar">Surat jalan pengiriman barang — bukan bukti tagihan</small>
    </div>
    <div class="invoice-nomor">
      <small class="pudar">Nomor surat jalan</small>
      <b>{{ $dis->kode }}</b>
      <span class="invoice-lencana">{{ \App\Models\Distribusi::label($dis->status) }}</span>
    </div>
  </div>

  <div class="invoice-pihak">
    <div>
      <small>Dikirim kepada</small>
      <b>{{ $dis->koperasi->nama ?? '—' }}</b>
      <span class="pudar">{{ $dis->koperasi->kode ?? '' }}{{ ($dis->koperasi->wilayah ?? '') !== '' ? ' · '.$dis->koperasi->wilayah : '' }}</span>
    </div>
    <div>
      <small>Tanggal</small>
      <b>{{ tanggal_id($dis->diperbarui, 'd M Y H:i') }}</b>
      <span class="pudar">Status {{ \App\Models\Distribusi::label($dis->status) }}</span>
    </div>
    <div>
      <small>Diminta oleh</small>
      <b>{{ optional($pemohon)->nama ?? '—' }}</b>
      <span class="pudar">Permintaan koperasi</span>
    </div>
  </div>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Nama barang</th><th>Diminta</th><th>Satuan</th><th>Kode</th></tr></thead>
    <tbody>
    @foreach ($dis->item as $i)
      <tr>
        <td data-l="Barang"><span class="judul-sel">{{ $i->barang->nama ?? '—' }}</span></td>
        <td data-l="Diminta"><b>{{ number_format($i->jumlah, 0, ',', '.') }}</b></td>
        <td data-l="Satuan">{{ $i->barang->satuan ?? '' }}</td>
        <td data-l="Kode"><code>{{ $i->barang->kode ?? '' }}</code></td>
      </tr>
    @endforeach
    </tbody>
  </table>
  </div>

  @if (trim((string) $dis->catatan_agrinas) !== '')
    <p class="invoice-catatan"><b>Keterangan:</b> {{ $dis->catatan_agrinas }}</p>
  @endif

  <div class="ttd-kisi">
    <div>
      <small>Diserahkan oleh</small>
      <span class="ttd-ruang"></span>
      <b>Gudang Agrinas Pangan Nusantara</b>
    </div>
    <div>
      <small>Diterima oleh</small>
      <span class="ttd-ruang"></span>
      <b>{{ optional($pemohon)->nama ?? ($dis->koperasi->nama ?? 'Manajer koperasi') }}</b>
    </div>
  </div>
</article>
</section>
@endsection

@extends('layouts.utama')

@section('judul', 'Tagihan ke Agrinas')

{{--
  Daftar invoice yang sudah diterbitkan vendor ini. Yang bisa dilihat vendor
  hanyalah status dan catatan keputusan Agrinas — bukan tombol apa pun: pihak
  yang menagih tidak boleh melunasi tagihannya sendiri.
  Pelunasan dicatat di /admin/vendor/tagihan (pusat & petugas keuangan).
--}}

@section('isi')
<header class="kepala">
  <div>
    <h1>Tagihan ke Agrinas</h1>
    <p class="tenang">{{ $vendor->nama }} — invoice terbit per pesanan yang barangnya sudah dikirim.</p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('mitra.index') }}">Pesanan &amp; Distribusi</a>
  </div>
</header>

<section class="blok">
  <div class="kartu-baris kartu-status">
    <div class="kartu angka">
      <small>Menunggu pembayaran</small>
      <b>Rp {{ number_format($diajukan, 0, ',', '.') }}</b>
    </div>
    <div class="kartu angka">
      <small>Sudah dibayar</small>
      <b>Rp {{ number_format($dibayar, 0, ',', '.') }}</b>
    </div>
  </div>
</section>

<section class="blok">
  <h2>Daftar Tagihan
    <small class="pudar">{{ number_format($jumlah, 0, ',', '.') }} tagihan</small>
  </h2>

  <div class="tabel-bungkus">
  <table>
    <thead><tr>
      <th>Tagihan</th><th>Koperasi</th><th>Jumlah</th><th>Cara bayar</th>
      <th>Status</th><th>Catatan Agrinas</th>
    </tr></thead>
    <tbody>
      @forelse ($tagihan as $t)
        <tr>
          <td data-l="Tagihan"><code>{{ $t->kode }}</code>
            <small class="pudar">{{ $t->keterangan }}</small></td>
          <td data-l="Koperasi"><span class="judul-sel">{{ $t->koperasi->nama ?? '—' }}</span></td>
          <td data-l="Jumlah">Rp {{ number_format((int) $t->jumlah, 0, ',', '.') }}</td>
          <td data-l="Cara bayar">{{ $t->labelKanal() }}</td>
          <td data-l="Status"><span class="lencana {{ \App\Models\VendorTagihan::KELAS[$t->status] ?? 's-BARU' }}">{{ $t->labelStatus() }}</span>
            @if ($t->dibayar !== '')<small class="pudar">{{ \Illuminate\Support\Carbon::parse($t->dibayar)->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</small>@endif</td>
          <td data-l="Catatan">{{ $t->catatan !== '' ? $t->catatan : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">
          Belum ada tagihan. Tagihan terbit dari halaman
          <a href="{{ route('mitra.index') }}">Pesanan &amp; Distribusi</a> setelah
          barang dikirim ke koperasi.
        </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>

  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>
@endsection

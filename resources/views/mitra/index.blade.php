@extends('layouts.utama')

@section('judul', 'Pesanan & Distribusi')

{{--
  Halaman kerja VENDOR penyalur. Vendor hanya melihat pesanan yang memuat
  barang miliknya (`barang.vendor_id` = vendor akun ini), dan hanya pesanan
  yang sudah dibayar koperasi — barang belum boleh berangkat sebelum itu.

  Dua aksi, urut sesuai kenyataan di lapangan:
    1. Kirim       -> DIBAYAR menjadi DIKIRIM + nomor surat jalan terbit.
    2. Tagih       -> invoice ke Agrinas; hanya setelah barang berangkat.
  Jumlah tagihan tidak diketik vendor: diambil dari total pesanan.
--}}

@section('isi')
<header class="kepala">
  <div>
    <h1>{{ $vendor->nama }}</h1>
    <p class="tenang">{{ \App\Models\Vendor::label($vendor->jenis) }} ·
      {{ $vendor->komoditas ?: 'komoditas mengikuti katalog Agrinas' }}</p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('mitra.tagihan') }}">Tagihan ke Agrinas</a>
  </div>
</header>

<section class="blok">
  <div class="kartu-baris kartu-status">
    <div class="kartu angka">
      <small>Belum dikirim</small><b>{{ $belumKirim }}</b>
    </div>
    <div class="kartu angka">
      <small>Tagihan menunggu bayar</small>
      <b>Rp {{ number_format($tagihanDiajukan, 0, ',', '.') }}</b>
    </div>
    <div class="kartu angka">
      <small>Tagihan sudah dibayar</small>
      <b>Rp {{ number_format($tagihanDibayar, 0, ',', '.') }}</b>
    </div>
  </div>
</section>

<section class="blok">
  <h2>Pesanan Berisi Barang Anda
    <small class="pudar">{{ number_format($jumlah, 0, ',', '.') }} pesanan</small>
  </h2>

  <div class="tabel-bungkus">
  <table>
    <thead><tr>
      <th>Invoice</th><th>Koperasi</th><th>Total</th><th>Pembayaran</th>
      <th>Status</th><th class="kolom-aksi">Aksi</th>
    </tr></thead>
    <tbody>
      @forelse ($pesanan as $p)
        <tr>
          <td data-l="Invoice"><code>{{ $p->invoice ?: $p->kode }}</code>
            <small class="pudar">{{ $p->surat_jalan ?: 'surat jalan belum terbit' }}</small></td>
          <td data-l="Koperasi"><span class="judul-sel">{{ $p->koperasi->nama ?? '—' }}</span></td>
          <td data-l="Total">Rp {{ number_format((int) $p->total, 0, ',', '.') }}</td>
          <td data-l="Pembayaran">{{ \App\Models\Saldo::LABEL_KANAL[$p->kanal] ?? $p->kanal }}</td>
          <td data-l="Status"><span class="lencana {{ ['DIBAYAR' => 's-DIKIRIM', 'DIKIRIM' => 's-DIPROSES', 'DITERIMA' => 's-SELESAI'][$p->status] ?? 's-BARU' }}">{{ $p->label_status() }}</span></td>
          <td class="kolom-aksi">
            @if ($p->status === 'DIBAYAR')
              <form method="post" action="{{ route('mitra.kirim', $p->id) }}">
                @csrf
                <button class="tombol utama mini" type="submit">Kirim</button>
              </form>
            @elseif (isset($sudahDitagih[$p->id]))
              <small class="pudar">{{ $sudahDitagih[$p->id] }}</small>
            @else
              <form method="post" action="{{ route('mitra.tagih', $p->id) }}">
                @csrf
                <button class="tombol samar mini" type="submit">Tagih ke Agrinas</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">
          Belum ada pesanan yang memuat barang Anda. Pesanan muncul di sini setelah
          koperasi membeli barang tersebut dari katalog Agrinas dan pembayarannya tercatat.
        </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>

  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>
@endsection

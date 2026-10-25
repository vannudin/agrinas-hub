@extends('layouts.utama')
@section('judul', 'Pesanan')
@section('isi')
{{-- Tiga peran, satu tabel: keputusan tampilannya datang dari controller
     ($pengelola / $agrinas), bukan dihitung ulang di sini — supaya tombol yang
     terlihat dan aksi yang diizinkan server tidak mungkin berbeda. --}}
<header class="kepala">
  <div>
    <h1>Pesanan Barang</h1>
    <p class="tenang">
      @if ($agrinas)
        Order dari manajer koperasi: pantau status, lepas barang dari gudang, lalu
        terbitkan surat jalan. Harga &amp; ketersediaan barang ada di
        <a href="{{ route('toko.kelola') }}">Kelola Katalog</a>.
      @elseif ($pengelola)
        Pesanan koperasi di wilayah Anda. Anda memantau dan menindaklanjuti ke
        Agrinas pusat; melepas barang &amp; pembayaran dikerjakan pusat.
      @else
        Status barang yang Anda pesan — sudah dikirim, siap diambil di gudang, atau
        sudah diterima. Tanda tangani surat jalan saat barang sampai.
      @endif
    </p>
  </div>
</header>
@include('toko._nav', ['mod' => 'pesanan', 'keranjang' => ['jumlah' => 0]])

@include('toko._saldo', ['kop' => $kop, 'saldos' => $saldos])

@if ($pengelola)
  <p class="pesan ingat" role="status">Ini daftar <b>order dari manajer koperasi</b> saja.
    Agrinas tidak berbelanja seperti manajer — pengelolaan harga &amp; ketersediaan barang ada di
    <a href="{{ route('toko.kelola') }}">Kelola Katalog</a>.</p>
@endif

<section class="blok">
  <h2>Daftar Pesanan <small class="pudar">{{ $daftar->count() }} pesanan</small></h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Invoice</th><th>Koperasi</th><th>Penyerahan</th><th>Total</th><th>Status</th><th>Surat Jalan</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    @forelse ($daftar as $p)
      @php
        // Satu daftar, dua sisi. `$penjualSaya` = order yang MASUK ke produk
        // koperasi pengguna ini; `$pembeliSaya` = order yang ia beli.
        $penjualSaya = $p->penjual_id !== null && (int) $p->penjual_id === ($kopSendiri ?? 0);
        $lokal = $p->penjual_id !== null;
      @endphp
      <tr data-id="{{ $p->id }}">
        <td data-l="Invoice">
          <a class="judul-sel" href="{{ route('toko.invoice', $p->id) }}">{{ $p->invoice ?: $p->kode }}</a>
          <small class="pudar">{{ $p->kode }} · {{ tanggal_id($p->tanggal, 'd M Y') }} · {{ $kanal[$p->kanal] ?? $p->kanal }}
            @if (! empty($p->nama_pemesan)) · oleh {{ $p->nama_pemesan }}@endif</small>
          @if ($lokal)
            <small class="tanda">{{ $penjualSaya ? 'Order masuk' : 'Order antar-koperasi' }}</small>
          @endif
        </td>
        <td data-l="Koperasi">
          @if ($penjualSaya)
            {{-- Bagi penjual, yang penting adalah SIAPA PEMBELINYA, bukan
                 dirinya sendiri. --}}
            dari {{ $p->nama_koperasi ?: '—' }}
            <small class="pudar">pembeli</small>
          @else
            {{ $p->nama_koperasi ?: '—' }}
            @if ($lokal && $p->penjual_id)
              <small class="pudar">dijual oleh koperasi #{{ $p->penjual_id }}</small>
            @endif
          @endif
        </td>
        <td data-l="Penyerahan"><span class="lencana">{{ $p->label_antar() }}</span></td>
        <td data-l="Total"><b>{{ \App\Models\Keuangan::rupiah($p->total) }}</b></td>
        <td data-l="Status"><span class="lencana s-{{ $p->status === 'DIBATALKAN' ? 'REVISI' : ($p->status === 'DITERIMA' ? 'SELESAI' : 'DIAJUKAN') }}">{{ $p->label_status() }}</span></td>
        <td data-l="Surat Jalan">
          @if ($p->surat_jalan)
            <a href="{{ route('toko.surat-jalan', $p->id) }}"><code>{{ $p->surat_jalan }}</code></a>
            @if ($p->status === 'DITERIMA')<small class="pudar">tuntas</small>@endif
          @else <span class="pudar">— terbit saat dilepas</span> @endif
        </td>
        <td class="kolom-aksi">
          <a class="tombol samar mini" href="{{ route('toko.invoice', $p->id) }}">Invoice</a>

          {{-- Menandai dibayar = kewenangan pusat (mengubah kas koperasi).
               Order antar-koperasi: pembeli yang menandai, karena pengeluarannya
               masuk buku kas koperasi pembeli itu sendiri. --}}
          @if (($bolehBayar && ! $lokal && $p->kanal === 'KAS' && $p->status === 'DIPESAN')
               || (! $pengelola && $lokal && ! $penjualSaya && $p->kanal === 'KAS' && $p->status === 'DIPESAN'))
            <form method="post" action="{{ route('toko.pesanan.bayar', $p->id) }}" class="sebaris">
              @csrf<button class="tombol samar mini" type="submit">Tandai Dibayar</button>
            </form>
          @endif

          {{-- Melepas barang: gudang Agrinas (pusat) atau gudang koperasi
               penjual (order antar-koperasi). --}}
          @if ((($agrinas && ! $lokal) || ($penjualSaya)) && $p->status === 'DIBAYAR')
            <form method="post" action="{{ route('toko.pesanan.lepas', $p->id) }}" class="sebaris">
              @csrf
              <select name="antar" aria-label="Cara penyerahan">
                @foreach (\App\Models\Pesanan::ANTAR as $k => $l)<option value="{{ $k }}" @selected($p->antar === $k)>{{ $l }}</option>@endforeach
              </select>
              <button class="tombol samar mini" type="submit">Lepas</button>
            </form>
          @endif

          {{-- Konfirmasi serah-terima ada di tangan koperasi yang MENERIMA
               barang. Penjual tidak boleh mengonfirmasi penerimaan barangnya
               sendiri — itu bukti yang harus datang dari pembeli. --}}
          @if (! $pengelola && ! $penjualSaya && $p->status === 'DIAMBIL')
            <form method="post" action="{{ route('toko.pesanan.diambil', $p->id) }}" class="sebaris">
              @csrf<button class="tombol mini" type="submit">Sudah Diambil</button>
            </form>
          @elseif (! $pengelola && ! $penjualSaya && $p->status === 'DIKIRIM')
            <form method="post" action="{{ route('toko.pesanan.terima', $p->id) }}" class="sebaris">
              @csrf<button class="tombol mini" type="submit">Terima</button>
            </form>
          @endif

          {{-- Pembatalan: pesanan gudang dibatalkan pusat (stok dikembalikan);
               order antar-koperasi dibatalkan koperasi penjualnya sebelum
               barang dilepas. --}}
          @if (($agrinas && ! $lokal && in_array($p->status, ['DIPESAN', 'DIBAYAR'], true))
               || ($penjualSaya && in_array($p->status, ['DIPESAN', 'DIBAYAR'], true)))
            <form method="post" action="{{ route('toko.pesanan.batal', $p->id) }}" class="sebaris"
                  onsubmit="return confirm('Batalkan pesanan ini? Stok dikembalikan dan saldo dipulihkan.')">
              @csrf<button class="tombol samar mini" type="submit">Batalkan</button>
            </form>
          @endif

          {{-- Tindak lanjut = jalur PIC (dan pusat). Dulu ini <details> yang harus
               diklik dulu (dua klik untuk satu catatan); sekarang satu formulir
               ringkas yang langsung terlihat. --}}
          @if ($pengelola)
            <details class="ubah" @if (($jejak[$p->id] ?? collect())->isNotEmpty()) open @endif>
              <summary class="tombol samar mini">Tindak Lanjut{{ ($jejak[$p->id] ?? collect())->isNotEmpty() ? ' ('.count($jejak[$p->id]).')' : '' }}</summary>
              <form method="post" action="{{ route('toko.pesanan.pantau', $p->id) }}" class="sebaris rapat">
                @csrf
                <select name="tindakan" aria-label="Tindakan tindak lanjut">
                  <option value="catatan">Catatan</option>
                  @if ($agrinas)
                    <option value="minta">Minta ubah</option>
                    <option value="hapus">Minta hapus</option>
                  @endif
                </select>
                <input name="pesan" maxlength="400" required placeholder="mis. mohon dicek kurirnya">
                <button class="tombol utama mini" type="submit">Catat</button>
              </form>
              @if (($jejak[$p->id] ?? collect())->isNotEmpty())
                <ul class="jejak">
                  @foreach ($jejak[$p->id] as $t)
                    <li><b>{{ \App\Models\PesananTindakan::TINDAKAN[$t->tindakan] ?? $t->tindakan }}</b>
                      {{ $t->pesan }}
                      <small class="pudar">{{ tanggal_id($t->dibuat, 'd M Y H:i') }}</small></li>
                  @endforeach
                </ul>
              @endif
            </details>
          @elseif (($jejak[$p->id] ?? collect())->isNotEmpty())
            {{-- Manajer tidak mencatat tindak lanjut, tapi harus TAHU bahwa
                 pengelola sedang menindaklanjuti pesanannya. --}}
            <details class="ubah">
              <summary class="tombol samar mini">Tindak Lanjut ({{ count($jejak[$p->id]) }})</summary>
              <ul class="jejak">
                @foreach ($jejak[$p->id] as $t)
                  <li><b>{{ \App\Models\PesananTindakan::TINDAKAN[$t->tindakan] ?? $t->tindakan }}</b>
                    {{ $t->pesan }}
                    <small class="pudar">{{ tanggal_id($t->dibuat, 'd M Y H:i') }}</small></li>
                @endforeach
              </ul>
            </details>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="7" class="kosong">
        Belum ada pesanan.
        @if (! $pengelola) <a href="{{ route('toko') }}">Mulai dari katalog</a>. @endif
      </td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  <p class="bantuan">Lampiran tindak lanjut (mis. foto tanda terima) bisa diunggah lewat Ruang Kerja → Dokumen,
    lalu tautannya ditempel di awal catatan.</p>
</section>
@endsection
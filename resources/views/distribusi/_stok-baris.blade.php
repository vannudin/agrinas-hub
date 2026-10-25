{{--
  Baris stok gudang. Dipakai DUA jalur dengan markup yang SAMA: tabel saat
  halaman dimuat penuh, dan potongan AJAX saat saring/paging tanpa muat ulang.
  Satu berkas, jadi tampilan hasil AJAX mustahil berbeda dari hasil muat penuh.

  Tombol Ubah membawa datanya di atribut `data-*`; modal diisi JavaScript dari
  sini (server yang mengirim nilainya, bukan JSON hasil tebak-tebakan).
--}}
@forelse ($barang as $b)
  <tr data-id="{{ $b->id }}">
    <td data-l="Kode"><code>{{ $b->kode }}</code></td>
    <td data-l="Nama"><span class="judul-sel">{{ $b->nama }}</span>
      @if ($b->catatan !== '')<small class="pudar">{{ $b->catatan }}</small>@endif
    </td>
    <td data-l="Vendor">{{ $b->vendor?->nama ?: '—' }}</td>
    <td data-l="Kategori">{{ \App\Models\Barang::KATEGORI[$b->kategori] ?? $b->kategori }}</td>
    <td data-l="Stok">
      <b>{{ number_format($b->stok, 0, ',', '.') }}</b> {{ $b->satuan }}
      @if ($b->stok_min > 0 && $b->stok <= $b->stok_min)
        <span class="lencana s-REVISI">Menipis</span>
      @endif
    </td>
    <td data-l="Harga Jual">{{ $b->harga_rupiah() }}</td>
    <td data-l="Ambang">{{ $b->stok_min > 0 ? number_format($b->stok_min, 0, ',', '.') : '—' }}</td>
    <td data-l="Diperbarui">{{ waktu_id($b->diperbarui) }}</td>
    <td class="kolom-aksi">
      <button class="tombol samar mini" type="button" data-ubah="{{ route('barang.ubah', $b->id) }}"
              data-kode="{{ $b->kode }}" data-nama="{{ $b->nama }}" data-vendor="{{ $b->vendor_id }}"
              data-kategori="{{ $b->kategori }}" data-satuan="{{ $b->satuan }}"
              data-stok="{{ (int) $b->stok }}" data-stok-min="{{ (int) $b->stok_min }}"
              data-harga="{{ (int) $b->harga }}"
              data-catatan="{{ $b->catatan }}">Ubah</button>
    </td>
  </tr>
@empty
  <tr><td colspan="9" class="kosong">
    @if (($cari ?? '') !== '' || ($saringKategori ?? '') !== '')
      Tidak ada barang yang cocok dengan saringan ini.
      <a href="{{ route('stok') }}">Tampilkan semua</a>.
    @else
      Gudang masih kosong — tekan <b>Tambah Barang</b> di kanan atas tabel.
    @endif
  </td></tr>
@endforelse

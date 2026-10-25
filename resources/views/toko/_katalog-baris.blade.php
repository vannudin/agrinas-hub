{{-- Baris katalog Agrinas. Dipakai DUA jalur: tabel saat halaman dimuat penuh
     (kelola.blade.php) dan potongan AJAX (barang_daftar → response()->view).
     Satu partial = satu markup; JS tak pernah menyusun HTML sendiri, jadi
     tampilan hasil fetch tidak mungkin berbeda dari hasil muat penuh. --}}
@php $dipakai = $dipakai ?? []; @endphp
@forelse ($barang as $b)
  <tr data-id="{{ $b->id }}" class="{{ $b->aktif ? '' : 'baris-turun' }}">
    <td data-l="Barang">
      <span class="judul-sel">{{ $b->nama }}</span>
      <small class="pudar"><code>{{ $b->kode }}</code> · {{ $b->satuan }} ·
        {{ $b->label_sumber() }}{{ $b->vendor ? ' — '.$b->vendor->nama : '' }}</small>
    </td>
    <td data-l="Kategori">{{ $kategori[$b->kategori] ?? $b->kategori }}</td>
    <td data-l="Stok"><b data-sel="stok">{{ number_format($b->stok, 0, ',', '.') }}</b>
      @if ($b->stok_min > 0 && $b->stok <= $b->stok_min) <span class="t-kuning">menipis</span>@endif</td>
    <td data-l="Harga"><b data-sel="harga">{{ (int) $b->harga > 0 ? \App\Models\Keuangan::rupiah($b->harga) : '—' }}</b></td>
    <td data-l="Tayang">
      <span class="lencana {{ $b->aktif ? '' : 'merah' }}" data-sel="tayang">{{ $b->aktif ? 'Tayang' : 'Diturunkan' }}</span>
    </td>
    <td class="kolom-aksi">
      {{-- Jalur tanpa-JS tetap hidup: tanpa JavaScript tombolnya tidak membuka
           apa pun, jadi catatan di bawah tabel menjelaskan Sunting butuh JS.
           Semua isian dibawa sebagai data-* dan dibaca ulang oleh hub-katalog.js
           dari baris yang diklik — tidak ada salinan data yang bisa basi. --}}
      <button class="tombol samar mini" type="button" data-buka="kat-modal" data-mode="sunting"
              data-judul-dialog="Sunting Produk"
              data-aksi="{{ route('toko.barang.perbarui', $b->id) }}"
              data-b="1" data-nama="{{ $b->nama }}" data-kode="{{ $b->kode }}"
              data-satuan="{{ $b->satuan }}" data-kategori="{{ $b->kategori }}"
              data-stok="{{ (int) $b->stok }}" data-stok-min="{{ (int) $b->stok_min }}"
              data-harga="{{ (int) $b->harga }}" data-sumber="{{ $b->sumber }}"
              data-subsidi="{{ (int) $b->subsidi }}"
              data-vendor="{{ $b->vendor_id }}" data-gambar="{{ $b->gambar }}"
              data-grosir="{{ $b->hargaGrosir->sortBy('min_jumlah')->map(fn ($j) => $j->min_jumlah.':'.$j->harga)->implode(',') }}">Sunting</button>
      @if ($b->aktif)
        {{-- Tombolnya mengikuti kenyataan: belum pernah dipakai = dihapus permanen,
             sudah dipakai pesanan = hanya diturunkan (riwayat tetap utuh). --}}
        @php $sudahDipakai = in_array((int) $b->id, $dipakai, true); @endphp
        <button class="tombol samar mini" type="button" data-url="{{ route('toko.barang.hapus', $b->id) }}"
                data-turun data-nama="{{ $b->nama }}" data-pakai="{{ $sudahDipakai ? 1 : 0 }}"
                >{{ $sudahDipakai ? 'Turunkan' : 'Hapus' }}</button>
      @else
        <button class="tombol utama mini" type="button" data-url="{{ route('toko.barang.aktifkan', $b->id) }}"
                data-pulih data-nama="{{ $b->nama }}">Tayangkan</button>
      @endif
    </td>
  </tr>
@empty
  <tr><td colspan="6" class="kosong">Tidak ada barang yang cocok. Ubah penyaring, atau tambah barang baru.</td></tr>
@endforelse

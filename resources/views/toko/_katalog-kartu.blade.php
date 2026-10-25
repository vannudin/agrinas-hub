@php
  // Satu kartu untuk SEMUA produk — gudang maupun lokal. Dulu berkas ini
  // bercabang `$dariLokal` di belasan tempat karena barisnya model berbeda;
  // sekarang controller sudah menyeragamkan bentuknya, jadi yang tersisa hanya
  // perbedaan yang benar-benar terlihat pembeli (asal, lencana, jenjang grosir).
  $b = $b ?? null;
  $lokal = ($b->asal ?? 'gudang') === 'lokal';
  $bisaBeli = $b->harga > 0 && $b->sisa > 0 && $kop && ! $b->milikSendiri;
  $palingMurah = collect($b->grosir)->min('harga');
  $potong = ($palingMurah && $b->harga > 0) ? round((1 - $palingMurah / $b->harga) * 100) : 0;
@endphp
<article class="kat-kartu{{ $b->sisa <= 0 ? ' kosong' : '' }}" data-kunci="{{ $b->asal }}:{{ $b->id }}">
  <div class="kat-gambar">
    @if ($b->gambar)
      <img src="{{ $b->gambar }}" alt="Foto {{ $b->nama }}" loading="lazy" width="640" height="640">
    @else
      <span class="kat-inisial" aria-hidden="true">{{ mb_strtoupper(mb_substr($b->nama, 0, 2)) }}</span>
    @endif
    <span class="kat-lencana {{ $b->sisa <= 0 ? 'habis' : 'j-'.strtolower($b->jenisKode) }}">{{ $b->lencana }}</span>
    @if ($potong > 0 && $b->sisa > 0)
      <span class="kat-hemat">Hemat {{ $potong }}%</span>
    @endif
  </div>
  <div class="kat-badan">
    <h3>{{ $b->nama }}</h3>
    @if ($lokal)
      {{-- Asal produk ikut tampil di kartu: pembeli tahu barang ini dari
           koperasi mana, bukan dari gudang Agrinas. --}}
      <span class="kat-sisa">Dari {{ $b->koperasi ?: 'koperasi lain' }}</span>
    @endif
    @if ($b->harga > 0)
      <span class="kat-harga">{{ \App\Models\Keuangan::rupiah($b->harga) }} <small>/ {{ $b->satuan }}</small></span>
    @else
      <span class="kat-harga pudar">Belum ada harga</span>
    @endif

    {{-- Jenjang grosir tampil di kartu: pembeli tahu syaratnya sebelum
         memasukkan barang, bukan setelah sampai keranjang. Satu pil per
         jenjang, ringkas — bukan kotak terpisah yang menghabiskan tinggi. --}}
    @if (count($b->grosir) > 0 && $b->harga > 0)
      <ul class="kat-grosir">
        @foreach ($b->grosir as $j)
          <li><span>{{ number_format($j['min'], 0, ',', '.') }}+</span>
              <b>{{ \App\Models\Keuangan::rupiah($j['harga']) }}</b></li>
        @endforeach
      </ul>
    @endif

    <span class="kat-sisa">{{ $lokal ? 'Tersedia' : 'Stok gudang' }}
      {{ number_format($b->sisa, 0, ',', '.') }} {{ $b->satuan }}
      @if (! $lokal && $b->stok_min > 0 && $b->sisa <= $b->stok_min) · <span class="t-kuning">menipis</span>@endif</span>

    <div class="kat-aksi">
      @if ($bisaBeli)
        <form method="post" action="{{ route('toko.keranjang.tambah') }}">
          @csrf
          <input type="hidden" name="asal" value="{{ $b->asal }}">
          @if ($lokal)
            {{-- Produk lokal tidak punya baris `barang`; yang dikirim id
                 pasokannya. --}}
            <input type="hidden" name="pasokan_id" value="{{ $b->id }}">
          @else
            <input type="hidden" name="barang_id" value="{{ $b->id }}">
          @endif
          @if (! $manajer)<input type="hidden" name="koperasi_id" value="{{ $kop->id }}">@endif
          <label class="sr" for="j{{ $b->asalNomor }}">Jumlah {{ $b->nama }}</label>
          <input id="j{{ $b->asalNomor }}" type="number" name="jumlah" value="1" min="1"
                 max="{{ max(1, $b->sisa) }}" step="1">
          <button class="tombol utama tombol-ikon" type="submit"
                  title="Masukkan ke keranjang"
                  aria-label="Masukkan {{ $b->nama }} ke keranjang">{!! ikon('keranjang') !!}</button>
        </form>
      @else
        <button class="tombol samar" type="button" disabled>
          {{ $b->sisa <= 0 ? 'Stok habis' : ($b->harga <= 0 ? 'Belum berharga'
            : ($b->milikSendiri ? 'Produk koperasi Anda' : 'Pilih koperasi dulu')) }}
        </button>
      @endif
    </div>
  </div>
</article>

{{--
  Kotak hasil pencarian koperasi — khusus halaman stok koperasi.

  Markupnya SENGAJA identik dengan keuangan/_koperasi-kotak.blade.php
  (ul.pilih-daftar > li > a.pilih-baris), karena CSS `.cari-koperasi` sudah ada
  dan hanya bekerja pada bentuk itu. Yang berbeda hanya tujuan tautannya.
  Dibuat terpisah, bukan disalin-pakai, karena berkas keuangan mengunci rute dan
  parameter ke halaman keuangan (?periode, ?acuan) yang tidak ada di sini.
--}}
<ul class="pilih-daftar" id="kop-hasil" data-hasil-kop @if (($q ?? '') === '') hidden @endif>
  @forelse ($hasil as $k)
    <li>
      <a data-baris-kop class="pilih-baris{{ $k->id === $pilihanId ? ' centang' : '' }}"
         href="{{ route('distribusi.stok-koperasi', ['koperasi' => $k->id]) }}">
        <span class="pilih-nama"><code>{{ $k->kode }}</code> {{ $k->nama }}</span>
        <small>{{ $k->wilayah ?: '—' }}</small>
      </a>
    </li>
  @empty
    <li class="pilih-kosong">
      {{ ($q ?? '') === '' ? 'Ketik minimal 2 huruf untuk mencari.' : 'Tidak ada koperasi yang cocok dengan “'.$q.'”.' }}
    </li>
  @endforelse
</ul>

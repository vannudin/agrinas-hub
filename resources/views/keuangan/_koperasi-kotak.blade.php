{{--
  Kotak hasil pencarian KDMP. Dirender DUA kali dengan markup yang SAMA: di dalam
  halaman (tanpa JavaScript: ketik lalu Enter) dan sebagai potongan AJAX untuk
  kotak mengambang (dengan JavaScript). Satu berkas, jadi dua jalur itu mustahil
  menampilkan hasil berbeda — dan JavaScript cukup menukar `innerHTML`-nya.

  Barisnya <a> biasa: tanpa JavaScript ia tautan yang membuka ?koperasi=ID,
  dengan JavaScript hub.js menukar isi wadah [data-isi] tanpa muat ulang.
--}}
<ul class="pilih-daftar" id="kop-hasil" data-hasil-kop @if (($q ?? '') === '') hidden @endif>
  @forelse ($hasil as $k)
    <li>
      <a data-baris-kop class="pilih-baris{{ $k->id === $pilihanId ? ' centang' : '' }}"
         href="{{ route('keuangan', ['koperasi' => $k->id, 'periode' => $periode, 'acuan' => $acuan]) }}">
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

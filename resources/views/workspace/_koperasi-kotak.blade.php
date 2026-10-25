{{--
  Kotak hasil pencarian koperasi untuk "Tugas Cepat". Dirender DUA kali dengan
  markup SAMA: di dalam halaman (tanpa JavaScript: ketik lalu Enter) dan sebagai
  potongan AJAX untuk kotak mengambang. Satu berkas, jadi dua jalur itu mustahil
  menampilkan hasil berbeda.

  Barisnya <button type="button" data-pilih-kop> — bukan <a>: memilih koperasi
  di sini TIDAK berpindah halaman, ia hanya mengisi input tersembunyi pada
  formulir Tugas Cepat.
--}}
<ul class="pilih-daftar" id="kop-hasil" data-hasil-kop @if (($q ?? '') === '') hidden @endif>
  @forelse ($hasil as $k)
    <li>
      <button class="pilih-baris{{ $k->id === ($pilihanId ?? 0) ? ' centang' : '' }}" type="button"
              data-pilih-kop="{{ $k->id }}"
              data-label="{{ $k->kode }} — {{ $k->nama }}">
        <span class="pilih-nama"><code>{{ $k->kode }}</code> {{ $k->nama }}</span>
        <small>{{ $k->wilayah ?: '—' }}</small>
      </button>
    </li>
  @empty
    <li class="pilih-kosong">
      {{ ($q ?? '') === '' ? 'Ketik minimal 2 huruf untuk mencari koperasi.' : 'Tidak ada koperasi yang cocok dengan “'.$q.'”.' }}
    </li>
  @endforelse
</ul>

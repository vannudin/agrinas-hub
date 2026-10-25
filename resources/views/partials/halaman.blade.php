@php
  // $halaman = App\Support\Halaman; $ikon = 'app' (dasbor) | 'pub'.
  // $kunci = nama parameter halaman (default 'halaman'). Halaman dengan DUA
  // daftar berhalaman mengirim kunci berbeda — lihat tautan_halaman().
  $ikon = $ikon ?? 'app';
  // JANGAN `$kunci = $kunci ?? 'halaman'`. `@include` mewarisi variabel induk,
  // dan `$kunci` adalah nama loop yang lazim di halaman ini
  // (`@foreach ($wilayah as $kunci => $label)`), jadi partial akan memakainya
  // sebagai nama parameter halaman: tautan "halaman 2" keluar sebagai
  // `?PAPUA BARAT DAYA=2` dan paging tampak mati. Panggil dengan nilai eksplisit.
  $kunci = $kunci ?? request()->query('halaman_kunci') ?? 'halaman';
  // @include mewarisi seluruh variabel induk, jadi variabel loop yang kebetulan
  // bernama `$kunci` (`@foreach ($tab as $kunci => …)`) ikut terpakai sebagai
  // nama parameter halaman: tautan "halaman 2" keluar `?masuk=2` dan paging
  // tampak mati. Kunci halaman SELALU berawalan 'halaman'.
  // Kunci halaman SELALU memuat kata "halaman" ('halaman', 'phalaman',
  // 'ahalaman', 'halaman_koperasi'); nama variabel loop tidak pernah. Jadi ini
  // penjaga yang benar: jangan menuntut awalan, karena kunci sah seperti
  // 'phalaman' justru ditolak oleh aturan berawalan.
  if (! is_string($kunci) || stripos($kunci, 'halaman') === false) {
      $kunci = 'halaman';
  }
  $kelas = $ikon === 'pub' ? 'pub-halaman' : 'paging';
  $kelasAngka = $ikon === 'pub' ? 'pub-halaman-angka' : 'paging-angka';
@endphp
@if ($halaman->total > 1)
  <nav class="{{ $kelas }}" aria-label="Navigasi halaman">
    <a class="tombol samar{{ $halaman->halaman > 1 ? '' : ' tanpa-isi' }}"
       @if ($halaman->halaman > 1) href="{{ tautan_halaman($halaman->halaman - 1, $kunci) }}" rel="prev" @else aria-hidden="true" tabindex="-1" @endif
    >&larr; <span class="kata">Sebelumnya</span></a>

    <ol class="{{ $kelasAngka }}">
      @foreach ($halaman->jendela() as $n)
        <li>
          @if ($n === '…')
            <span class="celah" aria-hidden="true">…</span>
          @elseif ($n === $halaman->halaman)
            <span aria-current="page">{{ $n }}</span>
          @else
            <a href="{{ tautan_halaman($n, $kunci) }}" aria-label="Halaman {{ $n }}">{{ $n }}</a>
          @endif
        </li>
      @endforeach
    </ol>

    <a class="tombol samar{{ $halaman->halaman < $halaman->total ? '' : ' tanpa-isi' }}"
       @if ($halaman->halaman < $halaman->total) href="{{ tautan_halaman($halaman->halaman + 1, $kunci) }}" rel="next" @else aria-hidden="true" tabindex="-1" @endif
    ><span class="kata">Berikutnya</span> &rarr;</a>
  </nav>
@endif

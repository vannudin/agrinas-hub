{{--
  Bar saring bersama untuk daftar AgriConnect: cari + filter + tombol bersihkan.

  Dipakai banyak halaman, jadi bentuknya satu: kalau tiap halaman menulis
  formulirnya sendiri, kolom pencarian akan berbeda lebar dan tombolnya berpindah
  tempat antar halaman — persis keluhan "desainnya tidak seragam".

  Parameter:
    $aksi        URL daftar (form GET), biasanya request()->url()
    $q           teks pencarian aktif
    $nilai       nilai filter aktif
    $namaFilter  nama parameter filter ('keadaan', 'jenis', …)
    $pilihan     [nilai => label]; kosong = tanpa <select>
    $placeholder teks contoh di kolom cari
    $petunjuk    (opsional) satu baris keterangan di bawah bar
--}}
@php
  $aksi = $aksi ?? request()->url();
  $q = $q ?? '';
  $nilai = $nilai ?? '';
  $namaFilter = $namaFilter ?? 'saring';
  $pilihan = $pilihan ?? [];
  $placeholder = $placeholder ?? 'Cari…';
  // Parameter lain (mis. `bulan`) harus ikut terbawa saat menyaring — hanya
  // kunci pencarian/filter/halaman yang dibuang, karena nilainya diisi form.
  $sisa = collect(request()->query())->except(['q', $namaFilter])
      ->reject(fn ($v, $k) => preg_match('/^halaman(_|$)/', (string) $k))->all();
@endphp
<form class="saring" method="get" action="{{ $aksi }}" role="search">
  @foreach ($sisa as $k => $v)
    @if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
  @endforeach
  <label class="cari">
    <span class="sr">Cari</span>
    <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
      <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
    </svg>
    <input type="search" name="q" value="{{ $q }}" placeholder="{{ $placeholder }}" autocomplete="off">
  </label>
  @if ($pilihan !== [])
    <label class="pilih">
      <span class="sr">{{ ucfirst($namaFilter) }}</span>
      <select name="{{ $namaFilter }}">
        <option value="">Semua {{ strtolower($namaFilter) }}</option>
        @foreach ($pilihan as $v => $l)
          <option value="{{ $v }}"@selected((string) $nilai === (string) $v)>{{ $l }}</option>
        @endforeach
      </select>
    </label>
  @endif
  <button class="tombol" type="submit">Saring</button>
  @if ($q !== '' || $nilai !== '')
    <a class="tombol samar bersih-saring" href="{{ $aksi }}">Bersihkan</a>
  @endif
</form>

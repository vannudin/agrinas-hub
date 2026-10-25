{{--
  Wadah gulir-tak-berujung (bukan paging).

  Potongan ini HANYA isi wadahnya: deretan kartu + penanda "muat lagi" di bawah.
  Dipakai DUA jalur dengan markup yang sama persis:
    - muat penuh  → @include di katalog.blade.php
    - gulir AJAX  → TokoController::katalog_muat (response()->view)

  Isinya kini SATU daftar gabungan (barang gudang + produk lokal). Dulu ada dua
  partial kembar karena dua kisi digulir terpisah; sekarang satu, jadi penanda
  gulir tidak mungkin menarik halaman kisi yang salah.

  Kenapa penanda, bukan paging: katalog adalah etalase (menelusuri), bukan tabel
  kerja. Etalase menambah baris saat digulir; mengklik nomor halaman memutus
  alur dan membuat orang kehilangan tempatnya.

  Tanpa JS tetap hidup: penanda "muat lagi" adalah <a href> halaman berikutnya
  yang benar-benar bisa diklik — jadi tidak ada jalan buntu kalau skrip gagal
  dimuat.
--}}
<div class="katalog-kisi">
  @forelse ($barang as $b)
    @include('toko._katalog-kartu', ['b' => $b])
  @empty
    <p class="kosong">
      @if (($cariAktif ?? '') !== '' || ($jenisAktif ?? '') !== '')
        Tidak ada produk yang cocok dengan pencarian Anda.
      @else
        Belum ada produk di katalog.
      @endif
    </p>
  @endforelse
</div>

@php
  $kueriBerikut = array_filter(array_merge(request()->query(), ['halaman' => $halaman->halaman + 1]),
      fn ($v, $k) => $k === 'halaman' || ($v !== '' && $v !== null), ARRAY_FILTER_USE_BOTH);
@endphp
  @if ($halaman->halaman < $halaman->total)
    @php
      // route('toko') menyesuaikan prefix sendiri (/toko untuk manajer,
      // /admin/toko untuk pengelola) — url()->current() tidak salah, tapi
      // menuliskan nama rute lebih jelas dan tahan perubahan prefix.
      $tautan = route('toko', $kueriBerikut);
    @endphp
    <div class="katalog-lagi" data-katalog-lagi>
      <a class="tombol samar" href="{{ $tautan }}" data-url="{{ $tautan }}" rel="next">Muat lagi</a>
      <noscript><span class="pudar">Gulir tidak jalan tanpa JavaScript — tekan tombol di samping.</span></noscript>
    </div>
@else
  {{-- Halaman terakhir (termasuk saat seluruh katalog muat di satu halaman):
       penutup ini yang memberitahu "sudah mentok" — dan itulah sinyal berhenti
       bagi pengamat gulir. --}}
  <p class="katalog-habis pudar" data-katalog-habis>Semua produk sudah ditampilkan
    ({{ number_format($halaman->jumlah, 0, ',', '.') }} produk).</p>
@endif

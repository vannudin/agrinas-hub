{{--
  Pemilih koperasi + kepala halaman, dipakai ketiga halaman modul Koperasi.

  Dua keadaan yang ditangani sekaligus:
   · MANAJER: koperasinya sudah pasti, jadi pemilihnya tidak ditampilkan sama
     sekali (bukan disembunyikan CSS) — ia tidak punya pilihan lain.
   · PUSAT/PIC/HR: memilih lewat daftar yang bisa DIKETIK (datalist), bukan
     <select> berisi ribuan <option>. Halaman /admin/koperasi punya pemilih
     ber-AJAX sendiri; di sini cukup satu kotak cari yang jatuh ke pencarian
     server (?q=) — polos, tanpa skrip baru, dan tetap bekerja tanpa JavaScript.

  `$koperasi` null = belum ada yang dipilih; itu keadaan yang sah dan halaman
  tetap berguna (memperlihatkan daftar + cara memilih), bukan halaman kosong.
--}}
@php
  $u = auth()->user();
  $manajer = $u->manajerKoperasi();
@endphp

@if (! $koperasi)
  <header class="kepala">
    <div>
      <h1>Koperasi</h1>
      <p class="tenang">
        @if ($manajer)
          Akun Anda belum ditautkan ke koperasi mana pun di Hub, jadi tidak ada data yang bisa ditampilkan.
        @else
          Pilih satu koperasi untuk melihat data, sarana prasarana, dan kasir penjualannya.
        @endif
      </p>
    </div>
    @if ($manajer)
      <div class="tombol-baris">
        {{-- Tautan ke /kop, BUKAN langsung ke formulir: dialog "Tambah Koperasi"
             (Select2 pemetaan lahan) hidup di halaman itu. Halaman Sarana/POS
             juga memakai kepala ini, jadi tombolnya harus tetap punya tujuan
             yang sama dari mana pun ia dibuka. --}}
        <a class="tombol utama" href="{{ route('kop') }}">Buat Data Koperasi</a>
      </div>
    @endif
  </header>
@else
  {{-- KEPALA. Bentuknya SENGAJA sama dengan halaman lain (`.kepala`): judul +
       deskripsi satu baris di kiri, tombol aksi di kanan. Versi kepala gelap
       (`kop-hero`) dibuang atas permintaan Bos 2026-10-18 — modul Koperasi harus
       menyatu dengan pola kepala halaman Hub, bukan punya gayanya sendiri. --}}
  <header class="kepala kepala-kop">
    <div>
      <h1>{{ $koperasi->nama }}</h1>
      <p class="tenang">
        <code>{{ $koperasi->kode }}</code>
        @if ($koperasi->wilayah) · {{ $koperasi->wilayah }} @endif
        @if ($koperasi->provinsi) · {{ $koperasi->provinsi }} @endif
        @if ($koperasi->koperasi_simkopdes_id) · SIMKOPDES {{ $koperasi->koperasi_simkopdes_id }} @endif
        @if (isset($sarana) && $sarana)
          {{-- Status diringkas ke baris deskripsi: di kepala gelap dulu jadi
               lencana, tapi kepala ini hanya memuat SATU baris teks (dipotong
               ellipsis oleh `.kepala .tenang`), jadi status ditaruh di situ
               supaya tetap terbaca tanpa menambah baris. --}}
          · {{ $sarana->labelOperasional() }}
        @endif
      </p>
    </div>
    <div class="tombol-baris">
      {{-- Kembali ke daftar. Halaman ini adalah halaman KOPERASI ITU SENDIRI
           (daftar pemilih tidak lagi ikut dirender di bawahnya), jadi jalan
           pulang ke daftar disediakan di sini. --}}
      @unless ($manajer)
        <a class="tombol samar ikon-kiri" href="{{ route($ruteBagian ?? 'kop') }}"
           title="Kembali ke daftar koperasi">{!! ikon('koperasi', 'ik kecil') !!}<span>Ganti Koperasi</span></a>
      @endunless
      @if ($bolehTulis)
        <a class="tombol samar ikon-kiri" href="{{ route('keuangan') }}"
           title="Buku kas & rekap keuangan koperasi">{!! ikon('keuangan', 'ik kecil') !!}<span>Keuangan Koperasi</span></a>
        <a class="tombol samar ikon-kiri" href="{{ route('distribusi.stok-koperasi') }}"
           title="Inventaris barang koperasi: tambah, impor CSV, dan perbarui stok">{!! ikon('stok-koperasi', 'ik kecil') !!}<span>Stok Barang</span></a>
      @endif
    </div>
  </header>
@endif

{{-- RINGKASAN SEMUA KOPERASI — DI ATAS tabel daftar (permintaan Bos
     2026-10-25: dulu kartunya jatuh di bawah daftar karena bloknya ditaruh
     sesudah include ini). Hanya dikirim halaman data; halaman Sarana/POS/
     Karyawan tidak mengoper `$ringkasSemua`, jadi tidak ikut dirender. --}}
@if (! $koperasi && ! empty($ringkasSemua))
  @include('koperasi._ringkas')
@endif

{{-- Daftar pemilih HANYA dirender saat belum ada koperasi terpilih. Dulu daftar
     ini ikut tampil DI BAWAH kepala halaman koperasi, sehingga isi koperasi
     terbaca sebagai sisipan di bawah daftar (keluhan Bos 2026-10-20). --}}
@unless ($manajer || $koperasi)
  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('koperasi', 'ik') !!}Pilih Koperasi</span></h2>
    @if (Route::has('admin.koperasi') && boleh_rute(auth()->user(), 'admin.koperasi'))
      <p class="bantuan">
        Mencari data lahan, penanggung jawab, atau menyaring menurut status?
        Buka <a href="{{ route('admin.koperasi') }}"><b>Daftar Koperasi &amp; Lahan</b></a>.
      </p>
    @endif
    <p class="bantuan">Yang tampil hanya koperasi dalam lingkup Anda.</p>
    {{-- Bentuk bilah saring SAMA dengan halaman lain (.saring + label.cari +
         svg.ikon-cari), dan TANPA `list=`/datalist: panah jatuh bawaan datalist
         itulah yang tampak berantakan di kolom pencarian, sementara daftarnya
         sendiri tak berguna begitu tabelnya berhalaman. Pencarian tetap jatuh
         ke server (?q=) seperti /admin/koperasi & /admin/pemetaan. --}}
    <form class="saring" method="get" action="{{ route('kop') }}">
      <label class="cari">
        <span class="sr">Cari koperasi</span>
        <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
        </svg>
        <input type="search" name="q" value="{{ $cariKop }}" autocomplete="off"
               placeholder="Cari kode, nama, atau wilayah koperasi…">
      </label>
      <button class="tombol" type="submit">Cari</button>
      @if ($cariKop !== '')
        <a class="tombol samar" href="{{ route('kop') }}">Bersihkan</a>
      @endif
    </form>

    <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Kode</th><th>Nama</th><th>Wilayah</th><th class="kolom-aksi">Buka</th></tr></thead>
        <tbody>
          @forelse ($pilihan as $p)
            <tr>
              <td data-l="Kode"><code>{{ $p->kode }}</code></td>
              <td data-l="Nama">{{ $p->nama }}</td>
              <td data-l="Wilayah">{{ $p->wilayah ?: '—' }}</td>
              <td class="kolom-aksi">
                {{-- Pindah halaman biasa, TANPA tab baru (permintaan Bos
                     2026-10-20). Daftar pemilih tidak lagi dirender di halaman
                     detail, jadi tidak ada isi yang tersisip di bawah daftar. --}}
                <a class="tombol mini"
                   href="{{ route($ruteBagian ?? 'kop', ['koperasi' => $p->id]) }}">Buka</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="kosong">
              {{ $cariKop !== '' ? 'Tidak ada koperasi yang cocok dengan pencarian ini.' : 'Belum ada koperasi dalam lingkup Anda.' }}
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{-- Halaman daftar: paging 10 baris seperti daftar lain di Hub. Hanya
         dirender bila memang ada lebih dari satu halaman. --}}
    @include('partials.halaman', ['halaman' => $halamanKop, 'ikon' => 'app'])
  </section>
@endunless

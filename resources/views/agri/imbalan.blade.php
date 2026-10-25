@extends('layouts.utama')

@section('judul', 'Imbalan & Poin')
@section('isi')
<header class="kepala">
  <div>
    <h1>Imbalan &amp; Poin</h1>
    <p class="tenang">Saldo StarPoint, riwayat mutasinya, dan katalog hadiah yang bisa ditukar.</p>
  </div>
</header>
@include('agri._nav', ['mod' => 'imbalan'])

<section class="blok">
  <div class="kartu-baris">
    <div class="kartu angka"><small>Saldo StarPoint</small><b>{{ number_format($saldo, 0, ',', '.') }}</b>
      <small class="pudar">Diperoleh dari presensi, tugas selesai, dan umpan balik.</small></div>
    <div class="kartu angka"><small>Hadiah Tersedia</small><b>{{ number_format($halamanHadiah->jumlah, 0, ',', '.') }}</b>
      <small class="pudar">Katalog dikelola admin Agrinas.</small></div>
  </div>
</section>

<section class="blok" id="hd" data-isi="{{ route('agri.imbalan') }}" data-params="q,halaman,halaman_hadiah">
  <div class="cat-kepala">
    <h2>Katalog Hadiah <small class="pudar">tukar dengan poin</small></h2>
    @if ($pusat)
      <div class="tombol-baris">
        <button class="tombol utama" type="button" data-buka="imbalan-modal"
                data-judul-dialog="Imbalan Baru"
                data-awal="{{ json_encode(['id' => '', 'judul' => '', 'keterangan' => '', 'biaya' => 500, 'stok' => 0]) }}">Imbalan Baru</button>
      </div>
    @endif
  </div>

  <form class="saring" method="get" action="{{ route('agri.imbalan') }}">
    <label class="cari">
      <span class="sr">Cari hadiah</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari hadiah…">
    </label>
    <a class="tombol samar bersih-saring" href="{{ route('agri.imbalan') }}" @if ($cari === '') hidden @endif>Bersihkan</a>
  </form>

  @if ($hadiah->isEmpty())
    <p class="kosong">
      @if ($cari !== '') Tidak ada hadiah yang cocok.
      @elseif ($pusat) Katalog masih kosong — tambahkan imbalan pertama lewat tombol Imbalan Baru.
      @else Katalog hadiah belum diisi admin Agrinas. @endif
    </p>
  @else
    <div class="kartu-baris">
      @foreach ($hadiah as $g)
        <div class="kartu">
          <b>{{ $g->judul }}</b>
          <small class="pudar">{{ $g->keterangan !== '' ? $g->keterangan : '—' }}</small>
          <p class="bantuan">
            <b>{{ number_format($g->biaya, 0, ',', '.') }} poin</b>
            · {{ $g->stok === 0 ? 'stok tanpa batas' : 'sisa '.number_format($g->stok, 0, ',', '.') }}
          </p>
          {{-- Nilai yang bisa ditukar hanya bila poin cukup DAN stoknya ada.
               Dulu tombolnya nonaktif tanpa alasan yang terbaca; sekarang
               sebabnya ditulis, supaya pegawai tidak menerka-nerka. --}}
          @php $habis = $g->stok !== 0 && $g->stok < 1; @endphp
          <div class="tombol-baris">
            @if ($habis)
              <span class="bantuan">Stok habis — hubungi admin Agrinas.</span>
            @elseif ($saldo < $g->biaya)
              <span class="bantuan">Kurang {{ number_format($g->biaya - $saldo, 0, ',', '.') }} poin lagi.</span>
            @else
              <form method="post" action="{{ route('agri.imbalan.tukar', $g->id) }}"
                    data-konfirmasi="Tukar {{ number_format($g->biaya, 0, ',', '.') }} poin dengan &quot;{{ $g->judul }}&quot;?">
                @csrf
                <button class="tombol utama mini" type="submit">Tukar</button>
              </form>
            @endif
            @if ($pusat)
              <button class="tombol samar mini" type="button" data-buka="imbalan-modal"
                      data-judul-dialog="Ubah Imbalan"
                      data-awal="{{ json_encode(['id' => $g->id, 'judul' => $g->judul, 'keterangan' => $g->keterangan, 'biaya' => $g->biaya, 'stok' => $g->stok]) }}">Ubah</button>
              <form method="post" action="{{ route('agri.imbalan.hapus', $g->id) }}"
                    data-konfirmasi="Hapus imbalan &quot;{{ $g->judul }}&quot; dari katalog?">
                @csrf @method('DELETE')
                <button class="tombol samar mini" type="submit">Hapus</button>
              </form>
            @endif
          </div>
        </div>
      @endforeach
    </div>
    @include('partials.halaman', ['halaman' => $halamanHadiah, 'ikon' => 'app', 'kunci' => 'halaman_hadiah'])
  @endif
</section>

<section class="blok">
  <h2>Riwayat Mutasi Poin</h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Waktu</th><th>Keterangan</th><th>Mutasi</th></tr></thead>
    <tbody>
      @forelse ($mutasi as $m)
        <tr>
          <td data-l="Waktu">{{ waktu_id($m->dibuat) }}</td>
          <td data-l="Keterangan">{{ $m->hal }}</td>
          <td data-l="Mutasi">
            <span class="lencana s-{{ $m->jumlah >= 0 ? 'SELESAI' : 'REVISI' }}">
              {{ $m->jumlah >= 0 ? '+' : '' }}{{ number_format($m->jumlah, 0, ',', '.') }}
            </span>
          </td>
        </tr>
      @empty
        <tr><td colspan="3" class="kosong">Belum ada mutasi poin.</td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>

{{-- SATU <dialog> melayani Tambah & Ubah; tombol pembuka mengisi judul dialog
     (data-judul-dialog) dan nilai field (data-awal) — keduanya sudah ditangani
     hub.js, jadi tidak perlu skrip per halaman maupun formulir ganda.
     Ditaruh DI LUAR wadah [data-isi] supaya tidak terbuang saat paging/saring.
     Penentu tambah-vs-ubah adalah `id` di body: ada isinya = menyunting. --}}
@if ($pusat)
<dialog class="modal" id="imbalan-modal" aria-labelledby="imbalan-modal-judul">
  <form method="post" action="{{ route('agri.imbalan.simpan') }}">
    @csrf
    <input type="hidden" name="id" value="">
    <header class="modal-kepala">
      <h2 id="imbalan-modal-judul">Imbalan Baru</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Nama imbalan <input name="judul" maxlength="160" required placeholder="Contoh: Voucher belanja Rp50.000"></label>
      <label>Keterangan <span class="bantuan">(opsional)</span>
        <input name="keterangan" maxlength="300" placeholder="Syarat, masa berlaku, cara pengambilan"></label>
      <div class="grid-form">
        <label>Biaya <span class="bantuan">(poin)</span>
          <input type="number" name="biaya" min="0" max="10000000" step="1" value="500" required></label>
        <label>Stok <span class="bantuan">(0 = tanpa batas)</span>
          <input type="number" name="stok" min="0" max="100000" step="1" value="0" required></label>
      </div>
      <p class="bantuan">Acuan: presensi harian bernilai 10 poin, jadi 500 poin ≈ 50 hari hadir.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan Imbalan</button>
    </footer>
  </form>
</dialog>
@endif

<script src="/js/hub-agri.js?v={{ filemtime(public_path('js/hub-agri.js')) }}" defer></script>
@endsection

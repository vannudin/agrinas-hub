@extends('layouts.utama')

@section('judul', 'Umpan Balik')
@section('isi')
@php $labelJenis = \App\Models\AgriUmpan::JENIS; @endphp
<header class="kepala">
  <div>
    <h1>Umpan Balik</h1>
    <p class="tenang">Catatan, apresiasi, dan saran antar-rekan kerja.</p>
  </div>
  <div class="tombol-baris">
    <button class="tombol utama" type="button" data-buka="umpan-baru">Kirim Umpan Balik</button>
  </div>
</header>
@include('agri._nav', ['mod' => 'umpan'])

<section class="blok" id="um" data-isi="{{ route('agri.umpan') }}" data-params="arah,q,halaman">
  <div class="kartu-baris kartu-status">
    <a class="kartu angka {{ $arah === 'masuk' ? 'aksi-hijau' : '' }}"
       href="{{ route('agri.umpan', array_filter(['arah' => 'masuk', 'q' => $cari])) }}">
      <small>Diterima</small><b>{{ $ringkas['masuk'] }}</b>
    </a>
    <a class="kartu angka {{ $arah === 'keluar' ? 'aksi-hijau' : '' }}"
       href="{{ route('agri.umpan', array_filter(['arah' => 'keluar', 'q' => $cari])) }}">
      <small>Dikirim</small><b>{{ $ringkas['keluar'] }}</b>
    </a>
  </div>

  <form class="saring" method="get" action="{{ route('agri.umpan') }}">
    <label class="cari">
      <span class="sr">Cari umpan balik</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari isi umpan balik…">
    </label>
    <input type="hidden" name="arah" value="{{ $arah }}">
    <a class="tombol samar bersih-saring" href="{{ route('agri.umpan', ['arah' => $arah]) }}"
       @if ($cari === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>{{ $arah === 'masuk' ? 'Dari' : 'Untuk' }}</th><th>Jenis</th><th>Isi</th><th>Waktu</th></tr></thead>
    <tbody>
      @forelse ($daftar as $m)
        <tr>
          <td data-l="{{ $arah === 'masuk' ? 'Dari' : 'Untuk' }}">
            @if ($m->anonim && $arah === 'masuk')
              <span class="judul-sel">Anonim</span>
            @else
              <span class="judul-sel">{{ ($arah === 'masuk' ? $m->oleh : $m->kepada)?->nama ?? '—' }}</span>
            @endif
          </td>
          <td data-l="Jenis"><span class="lencana">{{ $m->label_jenis() }}</span></td>
          <td data-l="Isi">{{ $m->isi }}</td>
          <td data-l="Waktu">{{ waktu_id($m->dibuat) }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">
          @if ($cari !== '') Tidak ada umpan balik yang cocok dengan pencarian ini.
          @elseif ($arah === 'masuk') Belum ada umpan balik yang diterima.
          @else Belum ada umpan balik yang dikirim. @endif
        </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>

<dialog class="modal" id="umpan-baru" aria-labelledby="umpan-baru-judul">
  <form method="post" action="{{ route('agri.umpan.simpan') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="umpan-baru-judul">Kirim Umpan Balik</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Untuk
        <select name="kepada_id" required>
          <option value="">— pilih rekan kerja —</option>
          @foreach ($penerima as $p)
            <option value="{{ $p->id }}">{{ $p->nama }} ({{ \App\Models\Domain::LABEL_PERAN[$p->peran] ?? $p->peran }})</option>
          @endforeach
        </select></label>
      <label>Jenis
        <select name="jenis" required>
          @foreach ($labelJenis as $k => $nama)<option value="{{ $k }}">{{ $nama }}</option>@endforeach
        </select></label>
      <label>Isi <textarea name="isi" rows="4" maxlength="1000" required
        placeholder="Tulis umpan baliknya — spesifik lebih berguna daripada umum"></textarea></label>
      <label class="pilihan-sebaris">
        <input type="checkbox" name="anonim" value="1"> Sembunyikan nama saya dari penerima
      </label>
      <p class="bantuan">Mengirim umpan balik memberi
        {{ \App\Http\Controllers\AgriController::POIN_UMPAN }} poin. Anonim hanya menyembunyikan nama di
        daftar penerima; admin tetap dapat menelusurinya bila disalahgunakan.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Kirim</button>
    </footer>
  </form>
</dialog>

<script src="/js/hub-agri.js?v={{ filemtime(public_path('js/hub-agri.js')) }}" defer></script>
@endsection

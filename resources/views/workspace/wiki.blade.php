@extends('layouts.utama')
@section('judul', 'Wiki')
@section('isi')
<header class="kepala">
  <div>
    <h1>Wiki</h1>
    <p class="tenang">Tutorial &amp; pengetahuan mengoperasikan sistem: fungsi, fitur, dan SOP tiap modul Agrinas Hub.</p>
  </div>
  @if ($boleh_ubah)
  <div class="tombol-baris">
    <button class="tombol utama" type="button" data-buka="wiki-baru">
      <svg class="ik" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Halaman Baru
    </button>
  </div>
  @endif
</header>
@include('workspace._nav', ['mod' => 'wiki'])

<section class="blok">
  <h2>Halaman Wiki <small class="pudar">{{ number_format($halaman->jumlah, 0, ',', '.') }} halaman</small></h2>

  <form class="saring" method="get" action="{{ route('workspace.wiki') }}" data-otomatis>
    <label class="cari">
      <svg class="ikon-cari" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
      <input id="q" name="q" value="{{ $q }}" placeholder="Cari judul atau isi halaman…" aria-label="Cari halaman wiki">
    </label>
    <label class="pilih">
      <span class="sr">Koperasi</span>
      <select name="koperasi" aria-label="Saring koperasi">
        <option value="">Semua koperasi &amp; umum</option>
        @foreach ($koperasi as $k)
          <option value="{{ $k->id }}" @selected($kopId === (int) $k->id)>{{ $k->kode }} — {{ $k->nama }}</option>
        @endforeach
      </select>
    </label>
    <button class="tombol samar" type="submit">Filter</button>
    @if ($cari)
      <a class="tombol samar" href="{{ route('workspace.wiki') }}">Bersihkan</a>
    @endif
  </form>

  @if ($cari)
    {{-- Mode saring: DAFTAR DATAR berhalaman. Hierarki dimatikan karena 10 baris
         pertama bisa berisi anak tanpa induknya — pohon yang terpotong begitu
         justru menyesatkan. --}}
    @if ($baris->isEmpty())
      <p class="kosong">Tidak ada halaman yang cocok dengan saringan ini.</p>
    @else
      <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Halaman</th><th>Induk</th><th>Koperasi</th><th>Diperbarui</th><th class="kolom-aksi">Aksi</th></tr></thead>
        <tbody>
        @foreach ($baris as $w)
          <tr>
            <td data-l="Halaman"><span class="judul-sel">{{ $w->judul }}</span>
              <small class="pudar">{{ \Illuminate\Support\Str::limit(trim((string) $w->isi), 90) ?: 'belum ada isi' }}</small>
            </td>
            <td data-l="Induk">
              @if ($w->induk)
                <a href="{{ route('workspace.wiki.lihat', $w->induk->id) }}">{{ $w->induk->judul }}</a>
              @else
                <span class="pudar">— tingkat utama —</span>
              @endif
            </td>
            <td data-l="Koperasi">{{ $w->koperasi?->nama ?? 'Umum' }}</td>
            <td data-l="Diperbarui">{{ waktu_id($w->diperbarui ?: $w->dibuat) }}</td>
            <td class="kolom-aksi">
              <a class="tombol samar mini" href="{{ route('workspace.wiki.lihat', $w->id) }}">Buka</a>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
      </div>
      @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
    @endif
  @else
    @if (empty($pohon))
      <p class="kosong">Belum ada halaman. Buat halaman pertama lewat tombol <b>Halaman Baru</b> di atas.</p>
    @else
      <ul class="wiki-pohon">
        @foreach ($pohon as $simpul)
          @include('workspace._wiki-simpul', ['simpul' => $simpul])
        @endforeach
      </ul>
      <p class="bantuan">Klik halaman untuk membuka, membaca, dan menyuntingnya.</p>
    @endif
  @endif
</section>

{{-- ================================ Dialog: halaman baru (khusus admin) --}}
@if ($boleh_ubah)
<dialog class="modal" id="wiki-baru" aria-labelledby="wiki-baru-judul">
  <form method="post" action="{{ route('workspace.wiki.baru') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="wiki-baru-judul">Halaman Baru</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Judul halaman
        <input name="judul" required maxlength="160" placeholder="SOP Penerimaan Barang" autocomplete="off">
      </label>
      <label>Halaman Induk
        <select name="induk_id">
          <option value="">— Tidak ada (halaman utama) —</option>
          @foreach ($wiki as $w)<option value="{{ $w->id }}">{{ $w->judul }}</option>@endforeach
        </select>
      </label>
      <label>Koperasi
        <select name="koperasi_id">
          <option value="">Umum</option>
          @foreach ($koperasi as $k)<option value="{{ $k->id }}">{{ $k->kode }} — {{ $k->nama }}</option>@endforeach
        </select>
      </label>
      <label>Isi <span class="bantuan">(Markdown: **tebal**, - daftar, | tabel |)</span>
        <textarea name="isi" rows="8" maxlength="40000" placeholder="Tulis isi halaman…"></textarea>
      </label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Buat &amp; Buka</button>
    </footer>
  </form>
</dialog>
@endif
@endsection

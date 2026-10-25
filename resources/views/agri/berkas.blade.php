@extends('layouts.utama')

@section('judul', 'Regulasi')
@section('isi')
@php $labelKategori = \App\Models\AgriBerkas::KATEGORI; @endphp
<header class="kepala">
  <div>
    <h1>Regulasi &amp; Panduan</h1>
    <p class="tenang">Berkas regulasi, panduan kerja, dan template yang berlaku.</p>
  </div>
  @if ($pusat)
    <div class="tombol-baris">
      <button class="tombol utama" type="button" data-buka="berkas-baru">Unggah Berkas</button>
    </div>
  @endif
</header>
@include('agri._nav', ['mod' => 'berkas'])

<section class="blok" id="bk" data-isi="{{ route('agri.berkas') }}" data-params="kategori,q,halaman">
  <div class="kartu-baris kartu-status">
    @foreach ($labelKategori as $k => $nama)
      <a class="kartu angka {{ $kategori === $k ? 'aksi-hijau' : '' }}"
         href="{{ route('agri.berkas', array_filter(['kategori' => $k, 'q' => $cari])) }}">
        <small>{{ $nama }}</small><b>{{ $ringkasKategori[$k] ?? 0 }}</b>
      </a>
    @endforeach
    <a class="kartu angka {{ $kategori === '' ? 'aksi-hijau' : '' }}"
       href="{{ route('agri.berkas', array_filter(['q' => $cari])) }}">
      <small>Semua</small><b>{{ array_sum($ringkasKategori) }}</b>
    </a>
  </div>

  <form class="saring" method="get" action="{{ route('agri.berkas') }}">
    <label class="cari">
      <span class="sr">Cari berkas</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari judul atau keterangan…">
    </label>
    @if ($kategori !== '')<input type="hidden" name="kategori" value="{{ $kategori }}">@endif
    <a class="tombol samar bersih-saring" href="{{ route('agri.berkas') }}"
       @if ($cari === '' && $kategori === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Judul</th><th>Kategori</th><th>Ukuran</th><th>Diunggah</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
      @forelse ($daftar as $b)
        <tr>
          <td data-l="Judul"><span class="judul-sel">{{ $b->judul }}</span>
            @if ($b->keterangan !== '')<small class="pudar">{{ $b->keterangan }}</small>@endif
          </td>
          <td data-l="Kategori"><span class="lencana">{{ $b->label_kategori() }}</span></td>
          <td data-l="Ukuran">{{ $b->ukuran > 0 ? ukuran_id((int) $b->ukuran) : '—' }}</td>
          <td data-l="Diunggah">{{ waktu_id($b->dibuat) }}</td>
          <td class="kolom-aksi">
            <a class="tombol samar mini" href="{{ route('agri.berkas.unduh', $b->id) }}">Unduh</a>
            @if ($pusat)
              <form method="post" action="{{ route('agri.berkas.hapus', $b->id) }}" class="sebaris"
                    data-konfirmasi="Hapus berkas &quot;{{ $b->judul }}&quot;?">
                @csrf @method('DELETE')
                <button class="tombol peringatan mini" type="submit">Hapus</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">
          @if ($cari !== '' || $kategori !== '') Tidak ada berkas yang cocok dengan saringan ini.
          @elseif ($pusat) Belum ada berkas. Unggah yang pertama lewat tombol di atas.
          @else Belum ada berkas regulasi yang diunggah. @endif
        </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>

@if ($pusat)
<dialog class="modal" id="berkas-baru" aria-labelledby="berkas-baru-judul">
  <form method="post" action="{{ route('agri.berkas.unggah') }}" enctype="multipart/form-data">
    @csrf
    <header class="modal-kepala">
      <h2 id="berkas-baru-judul">Unggah Berkas</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Judul <input name="judul" maxlength="160" required placeholder="Contoh: SOP Penyaluran Pasokan 2026"></label>
      <label>Kategori
        <select name="kategori" required>
          @foreach ($labelKategori as $k => $nama)<option value="{{ $k }}">{{ $nama }}</option>@endforeach
        </select></label>
      <label>Keterangan <span class="bantuan">(opsional)</span>
        <input name="keterangan" maxlength="300" placeholder="Ringkasan isi atau masa berlaku"></label>
      <label>Berkas <span class="bantuan">(maks 20 MB — PDF, Word, Excel, PowerPoint, gambar, ZIP)</span>
        <input type="file" name="berkas" required
          accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.png,.jpg,.jpeg"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Unggah</button>
    </footer>
  </form>
</dialog>
@endif

<script src="/js/hub-agri.js?v={{ filemtime(public_path('js/hub-agri.js')) }}" defer></script>
@endsection

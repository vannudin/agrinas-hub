@extends('layouts.utama')
@section('judul', 'Dokumen')
@section('isi')
@php
  // Ikon per jenis. SVG sebaris (bukan pustaka ikon): hanya empat bentuk, dan
  // halaman ini tidak boleh bergantung pada permintaan jaringan tambahan.
  $ikon = [
    'docx' => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/><path d="M9 12h6M9 16h6M9 8h2"/>',
    'xlsx' => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/><path d="M9 11l6 6M15 11l-6 6"/>',
    'pptx' => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/><rect x="9" y="11" width="6" height="6" rx="1"/>',
    'pdf'  => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/><path d="M9 15h1.5a1.5 1.5 0 010 3H9v-3zm0 0v-4m0 4v3M12.5 11H14a3 3 0 010 6h-1.5z"/>',
  ];
  $warna = ['docx' => 'doc', 'xlsx' => 'xls', 'pptx' => 'ppt', 'pdf' => 'pdf'];

  // Satu-satunya sumber tautan halaman: helper tautan_halaman() mempertahankan
  // parameter aktif, jadi tab/filter tidak hilang saat berpindah halaman.
  $tautanTab = fn ($t) => route('workspace.dokumen', array_filter([
      'tab' => $t, 'jenis' => $jenis, 'q' => $q,'koperasi' => request('koperasi')], fn ($v) => $v !== null && $v !== ''));
@endphp

<header class="kepala">
  <div>
    <h1>Dokumen</h1>
    <p class="tenang">Buat, sunting bersama, dan bagikan berkas Word, Excel, PowerPoint, dan PDF.</p>
  </div>
  <div class="tombol-baris">
    {{-- Pintu ke alat gambar: orang yang sedang mengurus berkas di sini juga
         yang paling mungkin butuh menyunting gambar. --}}
    <a class="tombol samar" href="{{ route('workspace.gambar') }}">
      <svg class="ik" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3 7.6 5H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-3.6L15 3H9z"/><circle cx="12" cy="12.5" r="4.5"/></svg>
      PhotoCraft
    </a>
    {{-- Ikon New Document. `data-buka` memakai dialog yang sudah ada (.modal) —
         satu mekanisme untuk seluruh aplikasi, bukan modal baru. --}}
    <button class="tombol utama" type="button" data-buka="dok-baru">
      <svg class="ik" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      New Document
    </button>
  </div>
</header>
@include('workspace._nav', ['mod' => 'dokumen'])

@if (! $siap)
  <div class="pesan ingat">
    Editor ONLYOFFICE belum tersambung. Isi <code>ONLYOFFICE_URL</code> dan <code>ONLYOFFICE_JWT_SECRET</code> di
    <code>.env</code>, lalu jalankan <code>scripts/onlyoffice_jalankan.sh</code>.
  </div>
@endif

{{-- Kartu jenis: satu klik = dialog New Document dengan jenis itu terpilih.
     Bukan formulir yang langsung mengirim: nama dokumen wajib diisi, jadi
     mengirim tanpa nama hanya akan menghasilkan galat validasi. --}}
<section class="blok">
  <h2>Buat Cepat <small class="pudar">klik salah satu jenis berkas</small></h2>
  <div class="kartu-jenis">
    @foreach (App\Models\Dokumen::JENIS as $k => $label)
      <button class="dok-pilih" type="button" data-buka="dok-baru" data-jenis="{{ $k }}"
              data-awal="{{ json_encode(['jenis' => $k]) }}">
        <span class="dok-ikon {{ $warna[$k] }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $ikon[$k] !!}</svg></span>
        <b>{{ App\Models\Dokumen::LABEL_JENIS[$k] }}</b>
        <small class="pudar">{{ $label }}</small>
      </button>
    @endforeach
  </div>
  <p class="bantuan">Semua jenis dibuat sebagai <b>Bersama</b> (koperasi Anda) kecuali Anda
    memilih lain di dialog. Ingin Pribadi atau Publik? Pilih di bagian Akses.</p>
</section>

<section class="blok" id="dok-daftar">
  <h2>Dokumen Saya
    <small class="pudar">{{ number_format($halaman->jumlah, 0, ',', '.') }} berkas</small>
  </h2>

  <form class="saring" method="get" action="{{ route('workspace.dokumen') }}" data-otomatis>
    <input type="hidden" name="tab" value="{{ $tab }}">
    <label class="cari">
      <svg class="ikon-cari" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
      <input id="q" name="q" value="{{ $q }}" placeholder="Cari judul dokumen…" aria-label="Cari dokumen">
    </label>
    <label class="pilih">
      <span class="sr">Jenis</span>
      <select name="jenis" aria-label="Saring jenis berkas">
        <option value="">Semua jenis</option>
        @foreach (App\Models\Dokumen::LABEL_JENIS as $k => $l)
          <option value="{{ $k }}" @selected($jenis === $k)>{{ $l }}</option>
        @endforeach
      </select>
    </label>
    <button class="tombol samar" type="submit">Filter</button>
    @if ($q !== '' || $jenis !== '')
      <a class="tombol samar" href="{{ route('workspace.dokumen', ['tab' => $tab]) }}">Bersihkan</a>
    @endif
  </form>

  {{-- Tab kelompok. `<a>` biasa (bukan JS) supaya tanpa JavaScript tetap jalan,
       dan hub.js sudah menangani tautan ber-pathname sama. --}}
  <nav class="dok-tab" aria-label="Kelompok dokumen">
    <a class="{{ $tab === 'semua' ? 'ini' : '' }}" href="{{ $tautanTab('semua') }}">
      Semua <span class="jml">{{ $ringkas['semua'] }}</span></a>
    <a class="{{ $tab === 'bersama' ? 'ini' : '' }}" href="{{ $tautanTab('bersama') }}">
      Bersama <span class="jml">{{ $ringkas['bersama'] }}</span></a>
    <a class="{{ $tab === 'pribadi' ? 'ini' : '' }}" href="{{ $tautanTab('pribadi') }}">
      Pribadi <span class="jml">{{ $ringkas['pribadi'] }}</span></a>
    <a class="{{ $tab === 'publik' ? 'ini' : '' }}" href="{{ $tautanTab('publik') }}">
      Publik <span class="jml">{{ $ringkas['publik'] }}</span></a>
  </nav>

  @if ($tab === 'publik')
    <p class="bantuan">Dokumen publik <b>bisa dilihat semua pengguna</b>. Untuk menyuntingnya,
      minta izin ke pemiliknya — tombolnya ada di tiap baris.</p>
  @endif

  <div class="tabel-bungkus">
  <table>
    <thead><tr>
      <th>Judul</th><th>Jenis</th><th>Pemilik</th><th>Diperbarui</th><th class="kolom-aksi">Aksi</th>
    </tr></thead>
    <tbody>
    @forelse ($baris as $d)
      @php
        $punyaSaya = (int) $d->dibuat_oleh === (int) auth()->id() || (int) $d->pemilik_id === (int) auth()->id();
        $aksesSaya = $aksesSaya[$d->id] ?? null;
        $menunggu = $aksesSaya && $aksesSaya->status === 'diminta';
        $diberi = $aksesSaya && $aksesSaya->status === 'diberi';
        $permintaanBaris = $permintaan->where('dokumen_id', $d->id);
      @endphp
      <tr>
        <td data-l="Judul">
          <span class="judul-sel">{{ $d->judul }}</span>
          <small class="pudar">
            @if ($d->terbuka())
              <span class="lencana kecil">{{ $d->akses === 'tamu' ? 'publik + sunting' : 'publik' }}</span>
            @endif
            @if ($d->akses === 'pribadi')<span class="lencana kecil">pribadi</span>@endif
            {{ $d->koperasi?->nama ?? 'Umum' }}
          </small>
        </td>
        <td data-l="Jenis">
          <span class="dok-ikon kecil {{ $warna[$d->jenis] ?? 'doc' }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $ikon[$d->jenis] ?? $ikon['docx'] !!}</svg></span>
          {{ $d->label_jenis() }}
        </td>
        <td data-l="Pemilik">
          {{ $d->pembuat?->nama ?? $d->pemilik?->nama ?? '—' }}
          @if ($punyaSaya)<small class="pudar">Anda</small>@endif
        </td>
        <td data-l="Diperbarui">{{ waktu_id($d->diperbarui ?: $d->dibuat) }}</td>
        <td class="kolom-aksi">
          {{-- Lihat selalu tersedia untuk dokumen yang tampil di daftar ini. --}}
          <a class="tombol samar mini" href="{{ route('workspace.dokumen.editor', $d->id) }}">
            {{ $punyaSaya || $diberi || $d->akses === 'tamu' || $d->akses === 'koperasi' ? 'Buka' : 'Lihat' }}
          </a>
          <a class="tombol samar mini" href="{{ tautan_berkas_dokumen($d) }}">Unduh</a>

          @if ($permintaanBaris->isNotEmpty())
            {{-- Menunggu keputusan Anda: setujui / tolak langsung dari daftar,
                 tanpa harus membuka dokumennya. --}}
            @foreach ($permintaanBaris as $p)
              <form method="post" action="{{ route('workspace.dokumen.putus', $d->id) }}" class="sebaris">
                @csrf
                <input type="hidden" name="pengguna_id" value="{{ $p->pengguna_id }}">
                <input type="hidden" name="putusan" value="diberi">
                <button class="tombol utama mini" type="submit" title="Izinkan {{ $p->pengguna?->nama ?? ('#'.$p->pengguna_id) }} menyunting">Izinkan</button>
              </form>
              <form method="post" action="{{ route('workspace.dokumen.putus', $d->id) }}" class="sebaris">
                @csrf
                <input type="hidden" name="pengguna_id" value="{{ $p->pengguna_id }}">
                <input type="hidden" name="putusan" value="ditolak">
                <button class="tombol samar mini" type="submit">Tolak</button>
              </form>
            @endforeach
          @endif

          @if (! $punyaSaya && ! $diberi && $d->akses === 'publik')
            @if ($menunggu)
              <span class="samar-kecil" title="Menunggu keputusan pemilik dokumen.">menunggu izin</span>
            @elseif (! $aksesSaya || $aksesSaya->status === 'ditolak')
              <form method="post" action="{{ route('workspace.dokumen.minta', $d->id) }}" class="sebaris">
                @csrf
                <button class="tombol utama mini" type="submit"
                        title="Kirim permintaan menyunting ke pemilik dokumen">Minta akses</button>
              </form>
            @endif
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="5" class="kosong">
        @if ($q !== '' || $jenis !== '')
          Tidak ada dokumen yang cocok dengan saringan ini.
        @else
          Belum ada dokumen di kelompok <b>{{ $tab }}</b>.
        @endif
      </td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>

{{-- Dialog "New Document": Pribadi atau Publik. Nama tetap (bukan field teks) —
     nama bisa diganti kapan saja dari editor, jadi formulir ini tidak perlu
     menahannya. --}}
<dialog class="modal" id="dok-baru" aria-labelledby="dok-baru-judul">
  <form method="post" action="{{ route('workspace.dokumen.baru') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="dok-baru-judul">New Document</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Nama dokumen
        <input name="judul" required maxlength="160" placeholder="Laporan Bulanan Agustus" autocomplete="off">
      </label>
      <label>Jenis berkas
        <select name="jenis">
          @foreach (App\Models\Dokumen::JENIS as $k => $label)
            <option value="{{ $k }}">{{ $label }}</option>
          @endforeach
        </select>
      </label>

      <fieldset class="dok-pilih-akses">
        <legend>Akses</legend>
        <label class="pilihan">
          <input type="radio" name="akses" value="pribadi">
          <span><b>Pribadi</b><small>Hanya Anda yang bisa membuka dan menyunting.</small></span>
        </label>
        <label class="pilihan">
          <input type="radio" name="akses" value="publik" checked>
          <span><b>Publik</b><small>Semua pengguna bisa <b>melihat</b>; menyunting perlu izin Anda.</small></span>
        </label>
        <label class="pilihan">
          <input type="radio" name="akses" value="tamu">
          <span><b>Publik + izin sunting</b><small>Semua pengguna langsung bisa menyunting.</small></span>
        </label>
        <label class="pilihan">
          <input type="radio" name="akses" value="koperasi">
          <span><b>Koperasi</b><small>Terbatas pada pembuat &amp; pengelola koperasi.</small></span>
        </label>
      </fieldset>

      <label>Koperasi <span class="bantuan">(hanya dipakai bila akses = Koperasi)</span>
        <select name="koperasi_id">
          <option value="">Umum</option>
          @foreach ($koperasi as $k)
            <option value="{{ $k->id }}">{{ $k->kode }} — {{ $k->nama }}</option>
          @endforeach
        </select>
      </label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Buat &amp; Buka</button>
    </footer>
  </form>
</dialog>
@endsection

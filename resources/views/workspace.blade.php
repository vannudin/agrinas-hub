@extends('layouts.utama')

@section('judul', 'Ruang Kerja')
@section('isi')
@php $label = App\Models\Domain::LABEL; @endphp
<header class="kepala">
  <div>
    <h1>Ruang Kerja</h1>
    <p class="tenang">
      @if ($mode === 'agrinas')
        Pusat kerja admin: tindak lanjut, daftar manajer, dan catatan pribadi.
      @else
        {{ $koperasi ? $koperasi->kode.' · '.$koperasi->nama : 'Akun Anda belum ditautkan ke koperasi.' }}
      @endif
    </p>
  </div>
</header>


{{-- Pintasan ke modul di luar Ruang Kerja: Dashboard pribadi, dan Toko.
     Bagi manajer koperasi Toko = katalog belanja; bagi admin/PIC = daftar
     pesanan koperasi binaannya (mereka tidak berbelanja), jadi labelnya ikut
     berubah — bukan cuma tautannya. --}}
<section class="blok">
  <h2>Pintasan</h2>
  <div class="kisi-fitur">
    <a class="kartu-fitur" href="{{ $ruteDasbor ?? route(auth()->user()->manajerKoperasi() ? 'kerja' : 'admin') }}" title="Dashboard pribadi">
      <span class="kartu-fitur-ikon">{!! ikon('dasbor', 'ik-fitur') !!}</span>
      <span class="kartu-fitur-label">Dashboard</span>
    </a>
    <a class="kartu-fitur" href="{{ route('toko') }}"
       title="{{ auth()->user()->manajerKoperasi() ? 'Katalog, keranjang, dan invoice pembelian barang' : 'Pesanan barang koperasi binaan' }}">
      <span class="kartu-fitur-ikon">{!! ikon('toko', 'ik-fitur') !!}</span>
      <span class="kartu-fitur-label">{{ auth()->user()->manajerKoperasi() ? 'Produk' : 'Pesanan Barang' }}</span>
    </a>
    {{-- Koperasi: data + status operasional, sarana prasarana, dan POS penjualan
         dalam satu halaman. Ditaruh di Pintasan (bukan di deret Modul Ruang
         Kerja) karena isinya modul tersendiri di luar Ruang Kerja — sama
         perlakuannya dengan Dashboard dan Toko. Manajer mendarat langsung di
         koperasinya; pusat/PIC memilih dulu di halaman itu. --}}
    <a class="kartu-fitur" href="{{ route('kop') }}"
       title="Data koperasi, status operasional, sarana prasarana, dan kasir POS penjualan">
      <span class="kartu-fitur-ikon">{!! ikon('koperasi', 'ik-fitur') !!}</span>
      <span class="kartu-fitur-label">Koperasi</span>
    </a>
  </div>
</section>

{{-- Tujuh modul Ruang Kerja dalam bentuk ikon, seragam dengan menu lain.
     Messenger tidak ikut di sini — lihat Koperasi/MODUL_TERSEMBUNYI.
     Kelas `kisi-rk` jadi penanda tetap untuk kisi ini di CSS: jumlah kartunya
     berubah-ubah (pernah 8, kini 7), jadi memakai `:has(:nth-child(8))` membuat
     aturan 4 kolomnya hilang begitu satu modul keluar dari daftar. --}}
<section class="blok">
  <h2>Modul Ruang Kerja</h2>
  <div class="kisi-fitur kisi-rk">
    @foreach ($modul as [$kunci, $rute, $label, $keterangan])
      <a class="kartu-fitur" href="{{ route($rute) }}" title="{{ $keterangan }}">
        <span class="kartu-fitur-ikon">{!! ikon($kunci, 'ik-fitur') !!}</span>
        <span class="kartu-fitur-label">{{ $label }}</span>
        @if (! empty($ringkas[$kunci]))
          <small class="pudar">{{ $ringkas[$kunci] }} item</small>
        @endif
      </a>
    @endforeach
  </div>
</section>

@if ($mode === 'agrinas')
  <div class="petak">
    <div class="kartu angka aksi-kuning"><small>Menunggu Verifikasi</small><b class="t-kuning">{{ $status['DIAJUKAN'] ?? 0 }}</b></div>
    <div class="kartu angka aksi-merah"><small>Perlu Revisi</small><b class="t-merah">{{ $status['REVISI'] ?? 0 }}</b></div>
    <div class="kartu angka aksi-kuning"><small>Terlambat</small><b class="t-kuning">{{ $terlambat }}</b></div>
    <div class="kartu angka"><small>Manajer</small><b>{{ $manajer['halaman']->jumlah }}</b></div>
    <div class="kartu angka aksi-hijau"><small>Selesai</small><b class="t-hijau">{{ $status['SELESAI'] ?? 0 }}</b></div>
  </div>

  {{-- Setiap tabel berhalaman sendiri (10 baris) dengan kunci halaman berbeda,
       dan saringannya <form class="saring" method="get"> — hub.js menangkap
       submit-nya lalu menukar isi [data-isi] tanpa muat ulang. Tanpa JavaScript
       formulirnya tetap GET biasa. Kunci halaman berbeda itu WAJIB: satu kunci
       untuk tiga tabel akan menggeser tabel lain saat satu tabel dipindah. --}}
  <section class="blok" id="ws-menunggu">
    <h2>Menunggu Tindakan Anda
      <small class="pudar">{{ $menunggu['halaman']->jumlah }} tugas</small>
    </h2>
    <form class="saring" method="get" action="{{ route('workspace') }}">
      <label class="cari">
        <span class="sr">Cari tugas menunggu</span>
        <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
        </svg>
        <input type="search" name="q_menunggu" value="{{ $cariMenunggu }}" autocomplete="off"
               placeholder="Cari judul tugas atau koperasi…">
      </label>
      <a class="tombol samar bersih-saring" href="{{ route('workspace') }}"
         @if ($cariMenunggu === '') hidden @endif>Bersihkan</a>
      <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
    </form>
    <div class="tabel-bungkus">
    <table>
      <thead><tr><th>Tugas</th><th>Koperasi</th><th>Status</th><th>Tenggat</th></tr></thead>
      <tbody>
      @forelse ($menunggu['baris'] as $t)
        <tr>
          <td data-l="Tugas"><span class="judul-sel">{{ $t->judul }}</span>
            @if ($t->deskripsi)<small class="pudar">{{ \Illuminate\Support\Str::limit($t->deskripsi, 120) }}</small>@endif
            @if (($t->sasaran ?? 'KOPERASI') !== 'KOPERASI')
              <small class="pudar">Dikirim ke: {{ \App\Models\Tugas::SASARAN[$t->sasaran] ?? $t->sasaran }}{{ $t->sasaran_wilayah ? ' — '.$t->sasaran_wilayah : '' }}</small>
            @endif</td>
          <td data-l="Koperasi">{{ $t->koperasi }}</td>
          <td data-l="Status"><span class="lencana s-{{ $t->status }}">{{ $label[$t->status] ?? $t->status }}</span></td>
          <td data-l="Tenggat">{{ tanggal_id($t->deadline) }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">
          @if ($cariMenunggu !== '')
            Tidak ada tugas menunggu yang cocok. <a href="{{ route('workspace') }}">Tampilkan semua</a>.
          @else
            Tidak ada tugas menunggu — semua sudah tertangani.
          @endif
        </td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
    @include('partials.halaman', ['halaman' => $menunggu['halaman'], 'ikon' => 'app', 'kunci' => $menunggu['kunci']])
  </section>

  <section class="blok" id="ws-manajer">
    <h2>Daftar Manajer &amp; Koperasinya
      <small class="pudar">{{ $manajer['halaman']->jumlah }} akun</small>
    </h2>
    <form class="saring" method="get" action="{{ route('workspace') }}">
      <label class="cari">
        <span class="sr">Cari manajer</span>
        <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
        </svg>
        <input type="search" name="q_manajer" value="{{ $cariManajer }}" autocomplete="off"
               placeholder="Cari nama atau email manajer…">
      </label>
      <a class="tombol samar bersih-saring" href="{{ route('workspace') }}"
         @if ($cariManajer === '') hidden @endif>Bersihkan</a>
      <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
    </form>
    <div class="tabel-bungkus">
    <table>
      <thead><tr><th>Nama</th><th>Email</th><th>Koperasi</th><th>Status</th></tr></thead>
      <tbody>
      @forelse ($manajer['baris'] as $m)
        <tr>
          <td data-l="Nama"><span class="judul-sel">{{ $m->nama }}</span></td>
          <td data-l="Email">{{ $m->email }}</td>
          <td data-l="Koperasi">{{ $m->koperasi?->nama ?? '—' }}@if ($m->koperasi)<small class="pudar">{{ $m->koperasi->kode }}</small>@endif</td>
          <td data-l="Status"><span class="lencana {{ $m->aktif ? 's-SELESAI' : 's-REVISI' }}">{{ $m->aktif ? 'Aktif' : 'Nonaktif' }}</span></td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">
          @if ($cariManajer !== '')
            Tidak ada manajer yang cocok. <a href="{{ route('workspace') }}">Tampilkan semua</a>.
          @else
            Belum ada akun manajer.
          @endif
        </td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
    @include('partials.halaman', ['halaman' => $manajer['halaman'], 'ikon' => 'app', 'kunci' => $manajer['kunci']])
  </section>

  <section class="blok">
    <h2>Tugas Cepat</h2>
    <form method="post" action="{{ route('workspace.tugas-cepat') }}" class="form-cepat" id="ws-cepat"
          data-url="{{ route('workspace.koperasi') }}">
      @csrf
      {{-- Pemilih koperasi yang BISA DICARI. Daftar lengkap tidak pernah
           dirender sebagai <option>: pada puluhan ribu koperasi itu berarti
           puluhan ribu baris di setiap pemuatan halaman, dan tetap tak bisa
           dicari. Yang dikirim hanya hasil pencarian (20 baris).

           Tanpa JavaScript pemilih ini tidak bisa dipakai untuk mencari, jadi
           disediakan <noscript> berisi <select> biasa + keterangan. --}}
      {{-- Sasaran pengiriman: KHUSUS pengelola (admin/PIC). Manajer tidak
           boleh mengirim massal (dijaga di controller), jadi pemilihnya tidak
           ditampilkan — formnya tetap sederhana untuk mereka. --}}
      @if (! empty($sasaranPilihan ?? []))
      <div class="penuh">
        <label for="sasaran_ws">Kirim ke</label>
        <select name="sasaran" id="sasaran_ws">
          @foreach (\App\Models\Tugas::SASARAN as $nilai => $teks)
            <option value="{{ $nilai }}">{{ $teks }}</option>
          @endforeach
        </select>
      </div>

      {{-- Wilayah sasaran: pilihannya DITURUNKAN dari data koperasi (bukan
           daftar 514 kabupaten yang harus dipelihara), dan hanya ditampilkan
           untuk sasaran yang membutuhkannya. --}}
      <div class="penuh" id="sasaran-wilayah-baris" hidden>
        <label for="wilayah_ws" id="wilayah_ws_label">Wilayah</label>
        <select name="wilayah" id="wilayah_ws"></select>
      </div>

      @endif
      <div class="penuh" id="koperasi-baris">
        <label for="cari_kop_ws">Koperasi</label>
        <input type="hidden" name="koperasi_id" id="ws-kop-id" data-kop-id value="">
        <div class="cari-kop">
          <input id="cari_kop_ws" data-cari-kop name="cari_kop" type="search" autocomplete="off"
                 placeholder="Cari KDMP — kode, nama, atau wilayah…"
                 role="combobox" aria-expanded="false" aria-controls="ws-kop-hasil"
                 aria-describedby="ws-kop-terpilih">
          @include('workspace._koperasi-kotak', ['hasil' => $ringkasKop, 'q' => '',
            'pilihanId' => $ringkasKop->first()->id ?? 0])
        </div>
        <p class="bantuan" id="ws-kop-terpilih" data-kop-terpilih>Terpilih: <b>belum dipilih</b>
          — ketik minimal 2 huruf untuk mencari.</p>
        {{-- Tanpa JavaScript pemilih ini tidak bisa mencari, dan merender
             SELURUH koperasi sebagai <option> justru masalah aslinya (puluhan
             ribu baris dikirim setiap pemuatan halaman). Jadi jalur tanpa-JS
             cukup mengetik KODE koperasinya — satuan yang memang dihafal admin,
             dan satu nilai saja yang dikirim. --}}
        <noscript>
          <label>Kode koperasi <span class="bantuan">(isi salah satu kode di atas)</span>
            <input name="kode_koperasi" required maxlength="30" placeholder="mis. KDKMP-001">
          </label>
        </noscript>
      </div>
      <label>Judul Tugas
        <input name="judul" required maxlength="160" placeholder="Tindak lanjut laporan"></label>
      <label>Tenggat <span class="bantuan">(opsional)</span>
        <input name="deadline" type="date"></label>
      {{-- Keterangan tugas: APA yang harus dikerjakan. Kolom `tugas.deskripsi`
           sudah ada sejak awal tetapi selalu diisi teks tetap; sekarang diisi
           pengguna dan tampil di halaman tugas koperasi. --}}
      <label class="penuh">Keterangan Tugas <span class="bantuan">(apa yang harus dikerjakan)</span>
        <textarea name="deskripsi" rows="3" maxlength="2000"
          placeholder="Contoh: Rekap penjualan bulan ini dan unggah bukti setor ke aplikasi."></textarea></label>
      {{-- Tombol rata KANAN dalam satu baris sendiri: sebelumnya ia 110px
           sendirian di kiri, tampak menggantung tanpa kaitan dengan isian di
           atasnya. --}}
      <div class="aksi penuh">
        <button class="tombol utama" type="submit">Buat Tugas</button>
      </div>
    </form>
  </section>
@else
  <div class="petak">
    <div class="kartu angka aksi-merah"><small>Perlu Revisi</small><b class="t-merah">{{ $status['REVISI'] ?? 0 }}</b></div>
    <div class="kartu angka"><small>Belum Mulai</small><b>{{ $status['BARU'] ?? 0 }}</b></div>
    <div class="kartu angka aksi-kuning"><small>Dikerjakan</small><b class="t-kuning">{{ $status['DIKERJAKAN'] ?? 0 }}</b></div>
    <div class="kartu angka aksi-hijau"><small>Selesai</small><b class="t-hijau">{{ $status['SELESAI'] ?? 0 }}</b></div>
  </div>

  <section class="blok" id="ws-tugas">
    <h2>Tugas Terbaru
      <small class="pudar">{{ $halamanTugas->dari() }}–{{ $halamanTugas->sampai() }} dari {{ $halamanTugas->jumlah }}</small>
    </h2>
    <form class="saring" method="get" action="{{ route('workspace') }}">
      <label class="cari">
        <span class="sr">Cari tugas</span>
        <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
        </svg>
        <input type="search" name="q_tugas" value="{{ $cariTugas }}" autocomplete="off"
               placeholder="Cari judul tugas…">
      </label>
      <label class="pilih">
        <select name="status_tugas" aria-label="Saring berdasarkan status">
          <option value="">Semua status</option>
          @foreach (\App\Models\Domain::STATUS as $s)
            @if (($status[$s] ?? 0) > 0)
              <option value="{{ $s }}" @selected($saringTugas === $s)>{{ \App\Models\Domain::LABEL[$s] ?? $s }} ({{ $status[$s] }})</option>
            @endif
          @endforeach
        </select>
      </label>
      <a class="tombol samar bersih-saring" href="{{ route('workspace') }}"
         @if ($cariTugas === '' && $saringTugas === '') hidden @endif>Bersihkan</a>
      <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
    </form>
    <div class="tabel-bungkus">
    <table>
      <thead><tr><th>Tugas</th><th>Jenis</th><th>Status</th><th>Tenggat</th></tr></thead>
      <tbody>
      @forelse ($tugas as $t)
        <tr>
          <td data-l="Tugas"><span class="judul-sel">{{ $t->judul }}</span>
            @if ($t->deskripsi)<small class="pudar">{{ \Illuminate\Support\Str::limit($t->deskripsi, 120) }}</small>@endif</td>
          {{-- "Jenis" kini berarti SASARAN pengiriman (kolom `jenis` dipakai
               ulang): Koperasi / Semua koperasi / Provinsi tertentu / dst.
               Tidak ada pembaca lain yang memakai `jenis` sebagai kategori
               dokumen — sudah diperiksa — jadi maknanya aman diganti. --}}
          <td data-l="Jenis">{{ \App\Models\Tugas::SASARAN[$t->jenis] ?? ucfirst(mb_strtolower($t->jenis)) }}
            @if ($t->sasaran_wilayah)<small class="pudar">{{ $t->sasaran_wilayah }}</small>@endif</td>
          <td data-l="Status"><span class="lencana s-{{ $t->status }}">{{ $label[$t->status] ?? $t->status }}</span></td>
          <td data-l="Tenggat">{{ tanggal_id($t->deadline) }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">
          @if ($cariTugas !== '' || $saringTugas !== '')
            Tidak ada tugas yang cocok. <a href="{{ route('workspace') }}">Tampilkan semua</a>.
          @else
            Belum ada tugas untuk koperasi Anda.
          @endif
        </td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
    @include('partials.halaman', ['halaman' => $halamanTugas, 'ikon' => 'app', 'kunci' => 'halaman'])
  </section>
@endif

<section class="blok">
  <h2>Catatan Kerja</h2>
  <form method="post" action="{{ route('workspace.catatan') }}">
    @csrf
    <label class="sr" for="catatan">Catatan kerja</label>
    <textarea id="catatan" name="catatan" rows="6" maxlength="20000"
      placeholder="Agenda hari ini, nomor kontak penting, hal yang harus ditindaklanjuti…">{{ $catatan }}</textarea>
    <div class="tombol-baris" style="margin-top:.7rem">
      <button class="tombol utama" type="submit">Simpan Catatan</button>
    </div>
  </form>
  <p class="bantuan">Catatan bersifat pribadi — hanya terlihat oleh akun Anda.</p>
</section>

{{-- Pilihan wilayah sasaran, DITURUNKAN dari data koperasi (lihat
     WorkspaceController::sasaranPilihan) — bukan daftar statis yang bisa basi. --}}
<script>window.WS_SASARAN = @json($sasaranPilihan ?? []);</script>
{{-- Pemilih KDMP yang bisa dicari (berkas bersama dengan /keuangan). --}}
<script src="/js/hub-pemilih-kop.js?v={{ filemtime(public_path('js/hub-pemilih-kop.js')) }}" defer></script>
@endsection

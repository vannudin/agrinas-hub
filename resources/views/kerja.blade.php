@extends('layouts.utama')

@section('judul', 'Dashboard')
@section('isi')
@php
  $label = App\Models\Domain::LABEL;
  // Kelompok kartu tugas & barang: satu sumber, dipakai bersamaan dengan
  // perulangan tabel di bawahnya.
  $kartuTugas = ['DIKERJAKAN', 'REVISI', 'DITOLAK', 'TERLAMBAT'];
  $kartuBarang = [['DIAJUKAN', 'Menunggu Diproses'], ['DIPROSES', 'Di Proses'],
                  ['DIKIRIM', 'Dalam Pengiriman'], ['DITERIMA', 'Diterima'],
                  ['DITOLAK', 'Ditolak']];
@endphp

<header class="kepala">
  <div>
    <h1>Dashboard Koperasi</h1>
    @if ($koperasi)
      <p class="tenang"><code>{{ $koperasi->kode }}</code> · {{ $koperasi->nama }}
        @if ($koperasi->wilayah) — {{ $koperasi->wilayah }} @endif</p>
    @else
      <p class="tenang">Akun Anda belum ditautkan ke koperasi.
        @if (auth()->user()->manajerKoperasi())
          <a href="{{ route('kop') }}">Buat data koperasi Anda</a> dengan memilih desa dari pemetaan
          lahan — setelah itu Kas, Produk, dan Stok bisa dipakai.
        @else
          Hubungi admin Agrinas.
        @endif
      </p>
    @endif
  </div>
  <div class="tombol-baris">
    <a class="tombol utama" href="{{ route('distribusi.pengajuan') }}">Ajukan Barang</a>
    <a class="tombol samar" href="{{ route('distribusi.daftar') }}">Permintaan Saya</a>
  </div>
</header>

@if ($koperasi)
  {{-- Urutan kartu: tugas dulu (yang perlu DIKERJAKAN / direvisi / ditolak),
       baru perjalanan barang. "Proses Tugas"/"Proses Barang" adalah JUDUL
       KELOMPOK, bukan kartu hitungan.

       Angka barang = JUMLAH JENIS barang (bukan jumlah surat jalan): nama
       kelompoknya "Proses Barang", jadi yang dihitung barangnya. Satu surat
       jalan berisi banyak barang, dan menampilkan "1" untuk tiga barang terbaca
       seperti salah.

       Dulu blok kartu barang muncul DUA KALI — sekali di sini, sekali lagi
       sebagai `.kartu-baris` di bagian bawah dengan label berbeda (Barang
       Dikirim/Barang Diterima). Sekarang satu saja. --}}
  <h2 class="petak-judul">Proses Tugas</h2>
  <div class="petak">
    @foreach ($kartuTugas as $k)
      @php $warna = in_array($k, ['REVISI', 'DITOLAK'], true) ? 'merah' : 'kuning'; @endphp
      <div class="kartu angka aksi-{{ $warna }}">
        <small>{{ $k === 'TERLAMBAT' ? 'Tugas Terlambat' : ($k === 'DIKERJAKAN' ? 'Di Kerjakan' : ($label[$k] ?? $k)) }}</small>
        <b class="t-{{ $warna }}">{{ $stat[$k] ?? 0 }}</b>
      </div>
    @endforeach
  </div>

  <h2 class="petak-judul">Proses Barang</h2>
  <div class="petak">
    @foreach ($kartuBarang as [$s, $nama])
      @php $warna = $s === 'DITOLAK' ? 'merah' : ($s === 'DITERIMA' ? 'hijau' : 'biru'); @endphp
      <div class="kartu angka aksi-{{ $warna }}">
        <small>{{ $nama }}</small>
        <b class="t-{{ $warna }}">{{ $statKirim[$s]['jenis'] ?? 0 }}</b>
        <small class="pudar">{{ $statKirim[$s]['n'] ?? 0 }} surat · {{ $statKirim[$s]['jumlah'] ?? 0 }} unit</small>
      </div>
    @endforeach
  </div>

  {{-- Wadah AJAX: dua daftar, satu wadah. Satu permintaan (saring / pindah
       halaman) mengganti KEDUANYA, tapi parameternya terpisah — hanya daftar
       yang parameternya berubah yang isinya bergeser. Tanpa JS tetap jalan:
       tautan pager & form adalah <a>/<form> biasa. --}}
  <div id="daftar" data-isi="{{ route('kerja') }}" data-params="q,status,halaman,bq,bstatus,halaman_barang">

    <section class="blok" id="tugas">
      <div class="kepala-bagian">
        <h2>Daftar Tugas
          <small class="pudar">{{ $halamanTugas->dari() }}–{{ $halamanTugas->sampai() }} dari {{ $halamanTugas->jumlah }} tugas</small>
        </h2>
        <div class="tombol-baris">
          {{-- Tugas tetap manajer (jobdesc PKWT) + tempat mengisi progres. --}}
          <a class="tombol samar mini" href="{{ route('workspace.jobdesc') }}">Jobdesc Manajer</a>
          <a class="tombol samar mini" href="{{ route('workspace.tugas') }}">Kelola &amp; Progres</a>
        </div>
      </div>
      <form class="saring" method="get" action="{{ route('kerja') }}">
        <label class="sr" for="q">Cari tugas</label>
        <input id="q" name="q" value="{{ $q }}" placeholder="Cari judul tugas…">
        <select name="status" aria-label="Saring status tugas">
          <option value="">Semua status</option>
          @foreach ($label as $k => $v)
            <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
          @endforeach
          <option value="TERLAMBAT" @selected($status === 'TERLAMBAT')>Terlambat</option>
        </select>
        <button class="tombol samar" type="submit">Filter</button>
        @if ($q !== '' || $status !== '')
          <a class="tombol samar" href="{{ route('kerja') }}">Bersihkan</a>
        @endif
      </form>
      <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Tugas</th><th>Jenis</th><th>Status</th><th>Tenggat</th><th class="kolom-aksi">Aksi</th></tr></thead>
        <tbody>
        @forelse ($tugas as $t)
          <tr>
            <td data-l="Tugas">
              <span class="judul-sel">{{ $t->judul }}</span>
              @if ($t->deskripsi)<small class="pudar">{{ Str::limit($t->deskripsi, 110) }}</small>@endif
            </td>
            <td data-l="Jenis">{{ ucfirst(mb_strtolower($t->jenis)) }}</td>
            <td data-l="Status"><span class="lencana s-{{ $t->status }}">{{ $label[$t->status] ?? $t->status }}</span></td>
            <td data-l="Tenggat">
              {{ tanggal_id($t->deadline) }}
              @if (\App\Http\Controllers\KerjaController::terlambat($t))
                <span class="lencana s-REVISI">Terlambat</span>
              @endif
            </td>
            <td class="kolom-aksi">
              {{-- Manajer = PELAKSANA: jalur cepat mengisi progres & melihat riwayat. --}}
              <a class="tombol utama mini" href="{{ route('workspace.tugas', ['q' => $t->judul]) }}">Isi Progres</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="kosong">Belum ada tugas untuk koperasi Anda.</td></tr>
        @endforelse
        </tbody>
      </table>
      </div>
      @include('partials.halaman', ['halaman' => $halamanTugas, 'ikon' => 'app', 'kunci' => 'halaman'])
    </section>

    <section class="blok" id="barang">
      <h2>Daftar Barang
        <small class="pudar">{{ $halamanKirim->dari() }}–{{ $halamanKirim->sampai() }} dari {{ $halamanKirim->jumlah }} baris</small>
      </h2>
      <form class="saring" method="get" action="{{ route('kerja') }}">
        <label class="sr" for="bq">Cari barang</label>
        <input id="bq" name="bq" value="{{ $bq }}" placeholder="Cari kode surat jalan atau nama barang…">
        <select name="bstatus" aria-label="Saring status barang">
          <option value="">Semua status</option>
          @foreach (App\Models\Distribusi::LABEL as $k => $v)
            <option value="{{ $k }}" @selected($bstatus === $k)>{{ $v }}</option>
          @endforeach
        </select>
        <button class="tombol samar" type="submit">Filter</button>
        @if ($bq !== '' || $bstatus !== '')
          <a class="tombol samar" href="{{ route('kerja') }}">Bersihkan</a>
        @endif
      </form>
      <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Surat Jalan</th><th>Barang</th><th>Jumlah</th><th>Status</th><th>Diperbarui</th><th class="kolom-aksi">Aksi</th></tr></thead>
        <tbody>
        @forelse ($barang as $d)
          <tr>
            <td data-l="Surat Jalan"><code>{{ $d->kode }}</code>
              @if ($d->jumlah_jenis > 1)<small class="pudar">{{ $d->jumlah_jenis }} jenis</small>@endif
            </td>
            <td data-l="Barang"><span class="judul-sel">{{ $d->barang }}</span>
              <small class="pudar">{{ $d->kode ? '' : '' }}</small>
            </td>
            <td data-l="Jumlah">{{ $d->jumlah }} {{ $d->satuan }}
              @if ($d->jumlah_diterima !== null && $d->jumlah_diterima !== $d->jumlah)
                <small class="pudar">diterima {{ $d->jumlah_diterima }}</small>
              @endif
            </td>
            <td data-l="Status"><span class="lencana s-{{ $d->status }}">{{ App\Models\Distribusi::label($d->status) }}</span></td>
            <td data-l="Diperbarui">{{ waktu_id($d->diperbarui) }}</td>
            <td class="kolom-aksi">
              {{-- Modal & skripnya milik halaman ini juga (kerja.blade.php adalah
                   wadah [data-isi], jadi tautan ke /distribusi tak ditukar
                   hub.js). Tanpa JavaScript tautannya jalan seperti semula. --}}
              @if ($d->status === 'DIKIRIM')
                <a class="tombol utama mini" href="{{ route('distribusi.rincian', $d->id) }}"
                   data-rincian-buka="{{ $d->id }}"
                   data-rincian-url="{{ route('distribusi.panel', $d->id) }}">Terima Barang</a>
              @else
                <a class="tombol samar mini" href="{{ route('distribusi.rincian', $d->id) }}"
                   data-rincian-buka="{{ $d->id }}"
                   data-rincian-url="{{ route('distribusi.panel', $d->id) }}">Rincian</a>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="kosong">Belum ada barang. Mulai dengan <b>Ajukan Barang</b>.</td></tr>
        @endforelse
        </tbody>
      </table>
      </div>
      @include('partials.halaman', ['halaman' => $halamanKirim, 'ikon' => 'app', 'kunci' => 'halaman_barang'])
    </section>
  </div>
@endif

<dialog id="rincian-modal" aria-labelledby="rincian-modal-judul">
  <div id="rincian-modal-isi"><p class="bantuan sedang-muat">Memuat rincian…</p></div>
  <form method="dialog" class="rincian-modal-tutup">
    <button class="tombol samar mini ikon-tutup" aria-label="Tutup rincian">&times;</button>
  </form>
</dialog>

@if ($voteBelum)
  {{-- Modal "Manager Memilih Sistem": sekali per manajer, hanya selama ia belum
       bersuara ($voteBelum dari KerjaController). Tombol "Nanti" & klik latar
       menunda 24 jam lewat localStorage — tanpa kolom baru di `users`. --}}
  <dialog class="modal" id="vote-modal" aria-labelledby="vote-modal-judul">
    <div class="modal-kepala">
      <h2 id="vote-modal-judul">Manager Memilih Sistem</h2>
      <button class="tutup" type="button" data-tutup data-vote-nanti aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-badan">
      <p>Saat ini manajer memakai tiga sistem sekaligus: <b>SIMKOPDES</b>, <b>Lark</b>, dan
        <b>AgriConnect</b>. Tiga sistem, tetapi banyak kendala terjadi di lapangan.</p>
      <p><b>Agrinas Hub</b> hadir sebagai sistem terpadu yang menyatukan dan mengoptimalkan
        ketiganya — persiapan, penempatan, operasional, pelaporan, dan kepegawaian.</p>
      <p>Suara Anda menentukan sistem yang digunakan ke depan. Yuk, bantu vote sistem mana yang
        ingin Anda gunakan.</p>
    </div>
    <div class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup data-vote-nanti>Nanti</button>
      <a class="tombol utama" href="{{ route('vote') }}">Lihat dan Vote</a>
    </div>
  </dialog>

  <script>
    (function () {
      var d = document.getElementById('vote-modal');
      if (!d || typeof d.showModal !== 'function') return;
      var KUNCI = 'vote_nanti';
      try { if (Date.now() < (+localStorage.getItem(KUNCI) || 0)) return; } catch (e) {}
      d.addEventListener('click', function (e) {
        if (e.target === d || e.target.closest('[data-vote-nanti]')) {
          try { localStorage.setItem(KUNCI, Date.now() + 864e5); } catch (e) {}
        }
      });
      d.showModal();
    })();
  </script>
@endif

<script src="/js/hub-distribusi.js?v={{ filemtime(public_path('js/hub-distribusi.js')) }}" defer></script>
@endsection

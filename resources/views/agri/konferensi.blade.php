@extends('layouts.utama')

@section('judul', 'Konferensi')
@section('isi')
@php
  // SATU rute sejak 2026-10-14: halaman ini hanya dilayani /rapat. Butir
  // Konferensi di kisi modul AgriConnect DIHAPUS (permintaan Bos), jadi tidak
  // ada lagi jalur kedua yang harus dibedakan lewat `$aktif`.
  $sedangPegawai = false;
@endphp
<header class="kepala">
  <div>
    <h1>Konferensi</h1>
    <p class="tenang">Video meeting dan webinar yang terjadwal — masuk ruangnya langsung dari sini, tanpa pemasangan aplikasi.</p>
  </div>
</header>
{{-- Tidak ada kisi modul AgriConnect di sini: halaman ini halaman mandiri,
     bukan bagian dari modul /agri. --}}

{{-- Ringkasan: yang sedang berlangsung ditaruh paling atas supaya rapat yang
     benar-benar bisa diikuti tidak perlu dicari di dalam tabel. --}}
@php
  // Status dari model supaya sama dengan halaman lain (lihat KonferensiRapat::status()).
  // TANPA argumen waktu: model mengambil jam WIB sendiri. Dulu di sini dikirim
  // `$sekarang` dari controller yang berisi jam UTC, sehingga rapat yang SEDANG
  // berlangsung terbaca "akan datang" (rapat 22:00 WIB baru dianggap mulai 05:00 WIB).
  $berlangsung = $daftar->filter(fn ($r) => $r->status() === 'KINI')->take(3);
@endphp
@if ($berlangsung->isNotEmpty())
  <section class="blok">
    <h2>Sedang Berlangsung</h2>
    <div class="kartu-baris">
      @foreach ($berlangsung as $r)
        <div class="kartu">
          <small class="pudar">{{ $r->label_jenis() }} · mulai {{ $r->mulai_manusia() }}</small>
          <b>{{ $r->judul }}</b>
          @if ($r->catatan)<p class="bantuan">{{ $r->catatan }}</p>@endif
          <div class="tombol-baris">
            @if ($r->bisaMasuk())
              <a class="tombol utama" href="{{ route('rapat.ruang', $r->id) }}">Masuk Ruang</a>
            @else
              <span class="tombol samar terkunci" aria-disabled="true"
                    title="{{ $r->alasanKunci() }}">{{ $r->alasanKunci() }}</span>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </section>
@endif

@unless ($sfu)
  <section class="blok">
    <div class="pesan" role="alert">
      Cloudflare Realtime belum dikonfigurasi — tombol <b>Masuk Ruang</b> akan menampilkan
      pesan yang sama di dalam rapat. Hubungi admin Agrinas.
    </div>
  </section>
@endunless

<section class="blok" id="kf" data-isi="{{ $sedangPegawai ? route('agri.konferensi') : route('rapat') }}" data-params="q,jenis,halaman">
  <h2>Jadwal
    <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari
      {{ number_format($halaman->jumlah, 0, ',', '.') }}</small>
  </h2>
  <form class="saring" method="get" action="{{ $sedangPegawai ? route('agri.konferensi') : route('rapat') }}"
        data-otomatis>
    <label class="cari">
      <span class="sr">Cari judul konferensi</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari judul…">
    </label>
    <label>
      <span class="sr">Jenis</span>
      <select name="jenis">
        <option value="">Semua jenis</option>
        @foreach ($jenisPilihan as $k => $l)
          <option value="{{ $k }}" @selected($jenis === $k)>{{ $l }}</option>
        @endforeach
      </select>
    </label>
    <button class="tombol" type="submit">Terapkan</button>
    <a class="tombol samar bersih-saring" href="{{ $sedangPegawai ? route('agri.konferensi') : route('rapat') }}"
       @if ($cari === '' && $jenis === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="tabel-bungkus">
  <table>
    {{-- URUTAN KOLOM HARUS SAMA DENGAN `data-l` DI BAWAHNYA.
         Sebelumnya header: Judul · Jenis · Mulai · Status · Ruang, sedangkan
         selnya: Judul · Jenis · Jadwal · Peserta · aksi — jadi kolom "Mulai"
         berisi jadwal+status, kolom "Status" berisi jumlah peserta, dan kolom
         "Ruang" berisi tombol. Empat sel saja (pagar tata letak ponsel). --}}
    <thead><tr>
      <th>Judul</th><th>Jenis</th><th>Jadwal &amp; Status</th><th class="kolom-aksi"></th>
    </tr></thead>
    <tbody>
      @forelse ($daftar as $r)
        @php
          // Dihitung di sini, bukan disimpan: "sedang berlangsung" berubah sendiri
          // seiring waktu. Aturannya satu tempat di KonferensiRapat::status().
          $st = $r->status();
          $dekat = $st === 'KINI';
          // Pintu ruang hanya terbuka 30 menit sebelum mulai sampai selesai.
          // Di luar itu tombolnya TERKUNCI dengan alasannya, bukan tautan yang
          // membawa pengguna ke ruang kosong.
          $buka = $r->bisaMasuk();
        @endphp
        <tr>
          <td data-l="Judul"><span class="judul-sel">{{ $r->judul }}</span>
            {{-- Undangan pembicara harus TERLIHAT oleh yang diundang di daftar
                 rapat; kalau tidak, undangan hanya tersimpan di basis data. --}}
            @if ($r->pembicaraUndang((int) auth()->id()))
              <span class="lencana s-SELESAI">Anda pembicara</span>
            @endif
            <small class="pudar">{{ $r->catatan !== '' ? $r->catatan : $r->ruang }}</small></td>
          <td data-l="Jenis">{{ $r->label_jenis() }}</td>
          {{-- Mulai & status digabung: "kapan" dan "sekarang bisa diikuti atau
               tidak" satu cerita, dan aturan tata letak Hub membatasi 4 sel. --}}
          <td data-l="Jadwal">{{ $r->mulai_manusia() }}
            @if ($st === 'KINI')<span class="lencana s-SELESAI">Berlangsung</span>
            @elseif ($st === 'SELESAI')<span class="lencana s-BARU">Selesai</span>
            @else<span class="lencana s-DIAJUKAN">Akan datang</span>@endif
            {{-- Jumlah peserta digabung ke sini: hanya bermakna saat berlangsung,
                 dan aturan tata letak ponsel membatasi empat sel per baris. --}}
            @if ($r->peserta_aktif() > 0)
              <small class="pudar">{{ $r->peserta_aktif() }} peserta</small>
            @endif</td>
          <td class="kolom-aksi">
            @if ($buka)
              <a class="tombol {{ $dekat ? 'utama' : 'samar' }} mini"
                 href="{{ route('rapat.ruang', $r->id) }}">{{ $dekat ? 'Masuk Ruang' : 'Ruang' }}</a>
            @else
              <span class="tombol samar mini terkunci" aria-disabled="true"
                    title="{{ $r->alasanKunci() }}">{{ $st === 'AKAN' ? 'Belum dibuka' : 'Sudah selesai' }}</span>
            @endif</td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">
          {{ $cari !== '' || $jenis !== '' ? 'Tidak ada konferensi yang cocok dengan saringan ini.' : 'Belum ada konferensi terjadwal.' }}
          @if ($pusat) <a href="{{ route('admin.rapat') }}">Jadwalkan sekarang</a>. @endif
        </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>

<section class="blok sempit">
  <h2>Cara kerja</h2>
  <ul class="bantuan">
    <li>Kamera &amp; mikrofon hanya bekerja pada alamat <b>https</b> (atau localhost) —
      itu aturan peramban, bukan setelan aplikasi.</li>
    <li>Gaya <b>Video Meeting</b>: semua peserta bisa bicara dua arah dan saling melihat.</li>
    <li>Gaya <b>Video Webinar</b>: hanya pembicara tampil; penonton menyimak dan bisa
      meminta bicara kepada pengelola.</li>
    <li>Ada obrolan, reaksi, daftar peserta, dan tombol mengangkat tangan di dalam ruang.</li>
  </ul>
</section>
@endsection

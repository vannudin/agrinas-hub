@extends('layouts.utama')
@section('judul', 'Kalender')
@section('isi')
@php
  // Kisi bulan mulai hari Senin: hari pertama ditempatkan sesuai isoWeekday,
  // jumlah hari diambil dari Carbon — tanpa pustaka kalender tambahan.
  $sisip = (int) $awal->isoWeekday() - 1;
  $jumlah = (int) $awal->daysInMonth;
  $baris = (int) ceil(($sisip + $jumlah) / 7);
  $bulanLalu = (clone $awal)->subMonth()->format('Y-m');
  $bulanDepan = (clone $awal)->addMonth()->format('Y-m');
  $tautanBulan = fn ($b) => route('workspace.kalender', array_filter(['bulan' => $b, 'q' => $cari, 'jenis' => $jenisSaring],
      fn ($v) => $v !== null && $v !== ''));
@endphp

<header class="kepala">
  <div>
    <h1>Kalender</h1>
    <p class="tenang">Jadwal rapat &amp; tenggat tugas, agenda satu bulan dalam satu daftar terurut waktu.</p>
  </div>
  @if ($boleh_jadwal)
    <div class="tombol-baris">
      <button class="tombol utama" type="button" data-buka="kal-rapat"
              data-awal="{{ json_encode(['mulai' => $awal->copy()->addDay()->format('Y-m-d').'T09:00']) }}">
        <svg class="ik" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        Jadwalkan Rapat
      </button>
    </div>
  @endif
</header>
@include('workspace._nav', ['mod' => 'kalender'])

{{-- ============================================================== Kisi bulan --}}
<section class="blok" id="kal-bulan">
  <div class="kal-kepala">
    <h2>{{ $awal->translatedFormat('F Y') }}
      <small class="pudar">{{ $ringkas['rapat'] }} rapat · {{ $ringkas['tenggat'] }} tenggat</small>
    </h2>
    <nav class="kal-navigasi" aria-label="Pindah bulan">
      <a class="tombol samar mini" href="{{ $tautanBulan($bulanLalu) }}" rel="prev">&larr; Sebelumnya</a>
      <a class="tombol samar mini" href="{{ $tautanBulan(now()->format('Y-m')) }}">Bulan ini</a>
      <a class="tombol samar mini" href="{{ $tautanBulan($bulanDepan) }}" rel="next">Berikutnya &rarr;</a>
    </nav>
  </div>

  {{-- Nama hari ringkas di layar lebar; di ponsel cukup inisial (CSS). --}}
  <div class="kal-grid kal-judul">
    @foreach (['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $h)<div>{{ $h }}</div>@endforeach
  </div>
  <div class="kal-grid">
    @for ($i = 0; $i < $baris * 7; $i++)
      @php
        $hari = $i - $sisip + 1;
        $ada = $hari >= 1 && $hari <= $jumlah;
        $tgl = $ada ? $awal->format('Y-m').'-'.str_pad((string) $hari, 2, '0', STR_PAD_LEFT) : '';
        $acara = $ada ? ($peta[$tgl] ?? []) : [];
        $akhirPekan = $ada && $i % 7 >= 5;
      @endphp
      @if (! $ada)
        <div class="kal-sel kal-luar"></div>
      @else
        <div class="kal-sel{{ $tgl === $hari_ini ? ' kal-kini' : '' }}{{ $akhirPekan ? ' kal-pekan' : '' }}">
          <div class="kal-tgl">
            <span class="kal-angka">{{ $hari }}</span>
            @if ($acara)<span class="kal-titik">{{ count($acara) }}</span>@endif
          </div>
          {{-- Di ponsel sel berubah jadi daftar ringkas (lihat hub.css): teksnya
               TETAP terbaca, tidak lagi menjadi titik `font-size:0`. --}}
          <div class="kal-acara-baris">
            @foreach ($acara as $a)
              @if ($a['jenis'] === 'rapat')
                @php $jam = substr((string) $a['data']->mulai, 11, 5); @endphp
                <a class="kal-acara kal-rapat" href="{{ route('rapat.ruang', $a['data']->id) }}"
                   title="{{ $jam }} {{ $a['data']->judul }}"
                   aria-label="Rapat {{ $jam }}: {{ $a['data']->judul }}">
                  <span class="kal-jam">{{ $jam }}</span>
                  <span class="kal-nama">{{ \Illuminate\Support\Str::limit($a['data']->judul, 26) }}</span>
                </a>
              @else
                <span class="kal-acara kal-tenggat"
                      title="Tenggat: {{ $a['data']->judul }} ({{ $a['data']->koperasi }})"
                      aria-label="Tenggat tugas: {{ $a['data']->judul }} ({{ $a['data']->koperasi }})">
                  <span class="kal-jam">⚑</span>
                  <span class="kal-nama">{{ \Illuminate\Support\Str::limit($a['data']->judul, 26) }}</span>
                </span>
              @endif
            @endforeach
          </div>
        </div>
      @endif
    @endfor
  </div>

  {{-- Daftar acara per tanggal. Ini yang TERBACA di ponsel; di layar lebar
       disembunyikan CSS karena acara sudah tampil di dalam sel kisi (tidak ada
       duplikasi). Sengaja dirender server: tanpa JavaScript pun terbaca. --}}
  @php $hariAda = collect($peta)->sortKeys(); @endphp
  @if ($hariAda->isNotEmpty())
    <div class="kal-daftar">
      @foreach ($hariAda as $tglAcara => $daftarAcara)
        @php $tglObj = \Carbon\Carbon::parse($tglAcara); @endphp
        <article class="kal-hari{{ $tglAcara === $hari_ini ? ' kal-kini' : '' }}">
          <h3>
            <span class="kal-angka">{{ $tglObj->day }}</span>
            <span class="kal-bulan">{{ $tglObj->translatedFormat('l, F Y') }}</span>
            <span class="lencana kecil">{{ count($daftarAcara) }} acara</span>
          </h3>
          @foreach ($daftarAcara as $a)
            @if ($a['jenis'] === 'rapat')
              @php $jam = substr((string) $a['data']->mulai, 11, 5); @endphp
              <a class="kal-acara kal-rapat" href="{{ route('rapat.ruang', $a['data']->id) }}">
                <span class="kal-jam">{{ $jam }}</span>
                <span class="kal-nama">{{ $a['data']->judul }}</span>
              </a>
            @else
              <span class="kal-acara kal-tenggat">
                <span class="kal-jam">⚑ Tenggat</span>
                <span class="kal-nama">{{ $a['data']->judul }} <span class="pudar">· {{ $a['data']->koperasi }}</span></span>
              </span>
            @endif
          @endforeach
        </article>
      @endforeach
    </div>
  @endif

  <p class="bantuan">
    Rapat dijadwalkan pengelola wilayah &amp; admin Agrinas; tiap acara rapat bisa langsung dibuka
    untuk memulai panggilan video. Di ponsel, kisi menjadi peta bulan (tanggal + jumlah acara)
    dan judul acaranya tampil di daftar per tanggal di bawahnya.
  </p>
</section>

{{-- ================================================================ Agenda --}}
<section class="blok" id="kal-agenda">
  <h2>Agenda Bulan Ini
    <small class="pudar">{{ number_format($halaman->jumlah, 0, ',', '.') }} acara</small>
  </h2>

  <form class="saring" method="get" action="{{ route('workspace.kalender') }}" data-otomatis>
    <input type="hidden" name="bulan" value="{{ $bulan }}">
    <label class="cari">
      <svg class="ikon-cari" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
      <input id="q" name="q" value="{{ $cari }}" placeholder="Cari acara, koperasi, atau agenda…" aria-label="Cari agenda">
    </label>
    <label class="pilih">
      <span class="sr">Jenis</span>
      <select name="jenis" aria-label="Saring jenis acara">
        <option value="">Semua jenis ({{ $ringkas['semua'] }})</option>
        <option value="rapat" @selected($jenisSaring === 'rapat')>Rapat ({{ $ringkas['rapat'] }})</option>
        <option value="tenggat" @selected($jenisSaring === 'tenggat')>Tenggat tugas ({{ $ringkas['tenggat'] }})</option>
      </select>
    </label>
    <button class="tombol samar" type="submit">Filter</button>
    @if ($cari !== '' || $jenisSaring !== '')
      <a class="tombol samar" href="{{ route('workspace.kalender', ['bulan' => $bulan]) }}">Bersihkan</a>
    @endif
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Acara</th><th>Jenis</th><th>Waktu</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    @forelse ($agenda as $a)
      {{-- `data-waktu` = waktu mentah ISO. Dipakai gate untuk membuktikan urutan
           KRONOLOGIS tanpa menebak-nebak format tampilan tanggal. --}}
      <tr data-waktu="{{ $a['waktu'] }}" data-jenis="{{ $a['jenis'] }}">
        <td data-l="Acara">
          <span class="judul-sel">{{ $a['judul'] }}</span>
          @if ($a['keterangan'] !== '')<small class="pudar">{{ $a['keterangan'] }}</small>@endif
        </td>
        <td data-l="Jenis">
          <span class="lencana">{{ $a['jenis'] === 'rapat' ? 'Rapat' : 'Tenggat Tugas' }}</span>
        </td>
        <td data-l="Waktu">
          {{ $a['jenis'] === 'rapat' ? $a['data']->mulai_manusia() : tanggal_id($a['waktu']) }}
        </td>
        <td class="kolom-aksi">
          @if ($a['jenis'] === 'rapat')
            <a class="tombol samar mini" href="{{ route('rapat.ruang', $a['data']->id) }}">Mulai Video</a>
          @else
            <a class="tombol samar mini" href="{{ route('workspace.tugas') }}">Lihat Tugas</a>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="4" class="kosong">
        @if ($cari !== '' || $jenisSaring !== '')
          Tidak ada acara yang cocok dengan saringan ini.
        @else
          Belum ada acara pada bulan ini.
        @endif
      </td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>

@if ($boleh_jadwal)
<dialog class="modal" id="kal-rapat" aria-labelledby="kal-rapat-judul">
  <form method="post" action="{{ route('workspace.kalender.rapat') }}">
    @csrf
    <input type="hidden" name="bulan" value="{{ $bulan }}">
    <header class="modal-kepala">
      <h2 id="kal-rapat-judul">Jadwalkan Rapat</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Judul Rapat
        <input name="judul" required maxlength="160" placeholder="Rapat koordinasi bulanan" autocomplete="off">
      </label>
      <div class="kal-dua">
        <label>Mulai
          <input name="mulai" type="datetime-local" required>
        </label>
        <label>Bentuk
          <select name="jenis">
            <option value="meeting">Video Meeting (semua bicara)</option>
            <option value="webinar">Video Webinar (pembicara → penonton)</option>
          </select>
        </label>
      </div>
      <label>Catatan <span class="bantuan">(opsional)</span>
        <input name="catatan" maxlength="1000" placeholder="Agenda singkat">
      </label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Jadwalkan</button>
    </footer>
  </form>
</dialog>
@endif
@endsection

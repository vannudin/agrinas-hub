@extends('layouts.utama')

@section('judul', 'Aspirasi Manajer')
@section('isi')
<header class="kepala">
  <div>
    <h1>Aspirasi Manajer</h1>
    <p class="tenang">Masukan &amp; keluhan dari manajer koperasi.</p>
  </div>
  @if ($jumlahBaru > 0)
    <div class="tombol-baris">
      <span class="lencana kuning">{{ number_format($jumlahBaru, 0, ',', '.') }} belum ditanggapi</span>
    </div>
  @endif
</header>

<section class="blok">
  <h2>Daftar Aspirasi <small class="pudar">{{ number_format($halaman->jumlah, 0, ',', '.') }}</small></h2>

  <form class="saring" method="get" action="{{ route('admin.aspirasi') }}" data-otomatis>
    <label class="cari">
      <span class="sr">Cari aspirasi</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari perihal, uraian, atau koperasi…">
    </label>
    <label class="pilih">
      <span class="sr">Status</span>
      <select name="status">
        <option value="">Semua status</option>
        @foreach ($statusPilihan as $kode => $label)
          <option value="{{ $kode }}" @selected($status === $kode)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <a class="tombol samar bersih-saring" href="{{ route('admin.aspirasi') }}"
       @if ($cari === '' && $status === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="kabar-daftar">
    @forelse ($daftar as $a)
      @php $tutup = 'asp-'.$a->id; @endphp
      <article class="kabar">
        <header>
          <h3>{{ $a->perihal }}
            <span class="lencana {{ $a->labelJenis() === 'Keluhan' ? 'merah' : '' }}">{{ $a->labelJenis() }}</span>
            <span class="lencana {{ $a->warnaStatus() }}">{{ $a->labelStatus() }}</span>
          </h3>
          <small class="pudar">
            {{ $a->pengirim?->nama ?? 'pengirim tidak dikenal' }}
            @if ($a->koperasi) · {{ $a->koperasi }} @endif
            @if ($a->dibuat) · {{ waktu_id($a->dibuat) }} @endif
          </small>
        </header>
        <p>{{ $a->pesan }}</p>

        @if ($a->tanggapan)
          <div class="tanggapan">
            <small class="pudar">Sudah ditanggapi
              @if ($a->penanggap) · {{ $a->penanggap }} @endif
              @if ($a->ditanggapi) · {{ waktu_id($a->ditanggapi) }} @endif</small>
            <p>{{ $a->tanggapan }}</p>
          </div>
        @endif

        @if (! auth()->user()->pantau())
        <div class="tombol-baris">
          <button class="tombol samar mini" type="button" data-buka="{{ $tutup }}">
            {{ $a->tanggapan ? 'Ubah tanggapan' : 'Tanggapi' }}</button>
        </div>
        @endif

        {{-- Satu dialog melayani "tanggapi" & "ubah tanggapan" — bentuk CRUD
             rumah: modal yang sama, dibuka dari barisnya. Peran pengawasan
             hanya membaca, jadi dialog tulisnya tidak dirender. --}}
        @if (! auth()->user()->pantau())
        <dialog class="modal" id="{{ $tutup }}">
          <div class="modal-kepala">
            <h2>Tanggapan — {{ $a->perihal }}</h2>
            <button class="tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
          </div>
          <form method="post" action="{{ route('admin.aspirasi.tanggapi', $a->id) }}">
            @csrf
            <div class="modal-badan">
              <p class="kotak">{{ $a->pesan }}</p>
              <label class="lebar">
                <span>Status</span>
                <select name="status" required>
                  @foreach ($statusPilihan as $kode => $label)
                    <option value="{{ $kode }}" @selected($a->status === $kode)>{{ $label }}</option>
                  @endforeach
                </select>
              </label>
              <label class="lebar">
                <span>Tanggapan</span>
                <textarea name="tanggapan" rows="4" maxlength="4000"
                          placeholder="Jawaban/tindak lanjut untuk manajer…">{{ $a->tanggapan }}</textarea>
              </label>
              <p class="bantuan">Kosongkan bila belum ada jawaban — teks tanggapan yang sudah terkirim tidak terhapus.</p>
            </div>
            <div class="modal-kaki">
              <button class="tombol samar" type="button" data-tutup>Batal</button>
              <button class="tombol" type="submit">Simpan</button>
            </div>
          </form>
        </dialog>
        @endif
      </article>
    @empty
      <p class="kosong">
        {{ $cari !== '' || $status !== '' ? 'Tidak ada aspirasi yang cocok dengan saringan ini.' : 'Belum ada aspirasi dari manajer.' }}
      </p>
    @endforelse
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>
@endsection

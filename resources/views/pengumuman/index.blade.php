@extends('layouts.utama')

@section('judul', 'Pengumuman')
@section('isi')
<header class="kepala">
  <div>
    <h1>Pengumuman</h1>
    <p class="tenang">
      {{ $pengelola ? 'Terbitkan pengumuman resmi untuk manajer koperasi.' : 'Pengumuman resmi dari pengelola.' }}
    </p>
  </div>
  @if ($belumBaca > 0)
    <div class="tombol-baris">
      <form method="post" action="{{ route('pengumuman.baca') }}" class="sebaris">
        @csrf
        <button class="tombol samar" type="submit">Tandai {{ $belumBaca }} sudah dibaca</button>
      </form>
    </div>
  @endif
</header>

{{-- Kop surat: penanda resmi perusahaan. Ditaruh di atas sekali, bukan diulang
     di tiap kartu — supaya terbaca sebagai satu surat edaran, bukan tempelan. --}}
<div class="kop">
  <img src="/img/logo-agrinas.png" alt="Logo Agrinas Pangan Nusantara" width="452" height="215">
  <div>
    <b>PT Agrinas Pangan Nusantara</b>
    <small>Pengumuman resmi — dikeluarkan pengelola (Agrinas, HR, atau PIC wilayah)</small>
  </div>
</div>

@if ($pengelola)
<section class="blok">
  <h2>Terbitkan Pengumuman</h2>
  <p class="bantuan">
    Muncul sebagai penanda baru (lencana loncana di kepala) bagi penerima yang dipilih.
    Butuh kirim email/WhatsApp juga? Pakai <a href="{{ route('admin.broadcast') }}">Broadcast</a> —
    pengumuman di sini tidak mengirim surat, hanya memasang tanda.
  </p>
  <form method="post" action="{{ route('pengumuman.terbit') }}" class="petak-form">
    @csrf
    <label>
      <span>Perihal</span>
      <input type="text" name="perihal" maxlength="160" required value="{{ old('perihal') }}"
             placeholder="mis. Jadwal verval aset gelombang 2">
    </label>
    <label>
      <span>Sasaran</span>
      <select name="sasaran" required>
        @foreach ($sasaran as $kode => $label)
          <option value="{{ $kode }}" @selected(old('sasaran') === $kode)>{{ $label }}</option>
        @endforeach
        @if ($koperasiPilihan->isNotEmpty())
          <optgroup label="Satu koperasi (manajernya)">
            @foreach ($koperasiPilihan as $k)
              <option value="kop:{{ $k->kode }}" @selected(old('sasaran') === 'kop:'.$k->kode)>
                {{ $k->kode }} — {{ $k->nama }}</option>
            @endforeach
          </optgroup>
        @endif
      </select>
    </label>
    <label class="lebar">
      <span>Isi pengumuman</span>
      <textarea name="pesan" rows="4" maxlength="4000" required
                placeholder="Tulis pengumuman selengkapnya di sini…">{{ old('pesan') }}</textarea>
    </label>
    <div class="tombol-baris">
      <button class="tombol" type="submit">Terbitkan</button>
    </div>
  </form>
</section>
@endif

<section class="blok">
  <h2>Daftar Pengumuman <small class="pudar">{{ number_format($halaman->jumlah, 0, ',', '.') }}</small></h2>
  <form class="saring" method="get" action="{{ route('pengumuman') }}" data-otomatis>
    <label class="cari">
      <span class="sr">Cari pengumuman</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari perihal atau isi…">
    </label>
    <a class="tombol samar bersih-saring" href="{{ route('pengumuman') }}" @if ($cari === '') hidden @endif>Bersihkan</a>
  </form>

  {{-- Satu KARTU per pengumuman, dan judulnya TAUTAN ke halaman detail
       (/pengumuman/{id}). Dulu isinya mengembang di tempat (<details>), tetapi
       Bos meminta pengumuman dibuka sebagai halaman tersendiri supaya isi
       lengkapnya lapang dibaca — dan halaman itulah yang menandai "sudah dibaca".

       Di daftar hanya ditampilkan RINGKASAN, jadi tidak ada paragraf yang
       tertulis dua kali dan tidak ada dinding teks. --}}
  <div class="kabar-daftar">
    @forelse ($daftar as $k)
      @php $baruBelum = ! in_array($k->id, $baca, true); @endphp
      <article class="kabar {{ $baruBelum ? 'kabar-baru' : '' }}">
        <h3>
          <a class="kabar-judul" href="{{ route('pengumuman.detail', $k->id) }}">{{ $k->label_perihal() }}</a>
          @if ($baruBelum)<span class="lencana kuning">Baru</span>@endif
        </h3>
        <small class="pudar">
          {{ $k->pengirim ?: 'pengelola' }}
          @if ($k->dibuat) · {{ waktu_id($k->dibuat) }} @endif
          · sasaran: {{ \App\Models\Siaran::label_sasaran((string) $k->sasaran) }}
          @if ((int) $k->jumlah > 0) · {{ number_format((int) $k->jumlah, 0, ',', '.') }} penerima @endif
        </small>
        <p class="kabar-ringkas">{{ $k->ringkas(180) }}</p>
        <a class="kabar-buka" href="{{ route('pengumuman.detail', $k->id) }}">Baca lengkap →</a>
      </article>
    @empty
      <p class="kosong">
        {{ $cari !== '' ? 'Tidak ada pengumuman yang cocok dengan pencarian ini.' : 'Belum ada pengumuman.' }}
      </p>
    @endforelse
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>
@endsection

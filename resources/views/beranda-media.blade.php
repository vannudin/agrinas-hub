<?php /* Kartu artikel disiapkan oleh BerandaController (3 di beranda, 6 per halaman di /artikel). */ ?>

<section class="pub-bagian pub-latar-lembut" id="media">
  <div class="pub-kepala-bagian">
    <p class="pub-eyebrow">Pusat informasi &middot; ruang media</p>
    <h2>Kabar terbaru tentang Agrinas Pangan</h2>
    <p>Kumpulan pemberitaan pilihan mengenai penugasan KDKMP, penyaluran sarana pangan,
      dan kinerja koperasi desa.</p>
  </div>

  @if (count($artikel))
    <div class="pub-media">
      @foreach ($artikel as $a)
        <a class="pub-artikel{{ $a['gambar'] ? '' : ' tanpa-gambar' }}"
           href="{{ $a['url'] }}" target="_blank" rel="noopener noreferrer">
          @if ($a['gambar'])
            <div class="gambar">
              <img src="{{ $a['gambar'] }}" alt="" loading="lazy">
              {{-- Penanda pemilik konten: logo Agrinas Pangan + Danantara --}}
              <span class="badge"><img src="/img/logo-lockup-kecil.png" alt="Agrinas Pangan &middot; Danantara"></span>
            </div>
          @endif
          <div class="isi-artikel">
            <p class="meta">
              @if ($a['media'])<span class="media">{{ $a['media'] }}</span>@endif
              @if ($a['tanggal'])<span>{{ $a['tanggal'] }}</span>@endif
            </p>
            <h3>{{ $a['judul'] }}</h3>
            @if ($a['ringkasan'])<p>{{ $a['ringkasan'] }}</p>@endif
            <span class="baca">Baca di {{ $a['media'] ?: 'sumber asli' }} &rarr;</span>
          </div>
        </a>
      @endforeach
    </div>

    <p class="pub-media-aksi">
      <a class="tombol utama" href="{{ route('artikel') }}">Lihat semua artikel</a>
    </p>
    <p class="pub-media-catatan">Setiap artikel tertaut ke penerbit aslinya. Hak cipta berita
      dan foto tetap milik media bersangkutan; dimuat di sini sebagai rujukan informasi publik.</p>
  @else
    <p class="pub-media-catatan">Ruang media sedang diisi dengan pemberitaan terverifikasi.
      Seksi ini akan memuat tautan, gambar, dan ringkasan dari media yang dapat diverifikasi.</p>
  @endif
</section>

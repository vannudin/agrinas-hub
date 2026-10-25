@extends('layouts.utama')

@section('judul', $rapat->judul)
@section('isi')
<header class="kepala">
  <div>
    <h1>{{ $rapat->judul }}</h1>
    <p class="tenang">
      {{ $rapat->label_jenis() }} · {{ $rapat->mulai_manusia() }}
      · ruang <code>{{ $rapat->ruang }}</code>
      <span id="sfu-jumlah">{{ $jumlah > 0 ? '· '.$jumlah.' peserta' : '' }}</span>
    </p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('rapat') }}">Kembali</a>
  </div>
</header>

@if ($rapat->catatan)
  <div class="pesan ok" role="status">{{ $rapat->catatan }}</div>
@endif

@if (! $sfu)
  <div class="pesan" role="alert">
    Cloudflare Realtime belum dikonfigurasi — ruang video tidak dapat dibuka.
    Hubungi admin Agrinas.
  </div>
@else
  <div id="sfu"
       data-jenis="{{ $rapat->jenis }}"
       data-nama="{{ $nama }}"
       data-aku-pembicara="{{ $penyiar ? 1 : 0 }}"
       data-peran="{{ route('rapat.sfu.peran', $rapat->id) }}"
       data-ikut="{{ route('rapat.sfu.ikut', $rapat->id) }}"
       data-siarkan="{{ route('rapat.sfu.siarkan', $rapat->id) }}"
       data-tonton="{{ route('rapat.sfu.tonton', $rapat->id) }}"
       data-jawab="{{ route('rapat.sfu.jawab', $rapat->id) }}"
       data-aksi="{{ route('rapat.sfu.aksi', $rapat->id) }}"
       data-keluar="{{ route('rapat.sfu.keluar', $rapat->id) }}">

    <div class="rapat-tata">
      <div class="rapat-utama">
        <div class="sfu-panggung" id="sfu-panggung">
          <div class="sfu-kotak" id="sfu-saya-kotak">
            <video class="sfu-video" id="sfu-saya" playsinline autoplay muted></video>
            <span class="sfu-nama">Anda</span>
          </div>
        </div>

        {{-- Navigasi panggung: 30 peserta tidak mungkin ditumpuk dalam satu
             gulungan (terukur 2.911 px di desktop, 6.215 px di ponsel). Halaman
             diisi sesuai jumlah kolom × 3 baris; JS menghitungnya dari lebar
             nyata, jadi tidak ada angka yang dipatok di sini. --}}
        <div class="sfu-hal" id="sfu-hal" hidden>
          <button type="button" class="tombol samar mini" data-hal="-1" aria-label="Peserta sebelumnya">‹</button>
          <span id="sfu-hal-info" class="pudar" role="status"></span>
          <button type="button" class="tombol samar mini" data-hal="1" aria-label="Peserta berikutnya">›</button>
        </div>

        {{-- Lobi (gaya Zoom): pilih kamera & mikrofon, uji suara, baru masuk.
             SENGAJA TANPA elemen <video> pratinjau: panggung `#sfu-saya-kotak`
             sudah menampilkan gambar Anda, dan pratinjau kedua di lobi membuat
             DUA frame kamera tampil bersamaan (keduanya aliran yang sama).
             Pemilihan perangkat tetap berjalan tanpa gambar — daftar kamera &
             mikrofon bekerja dari enumerateDevices, dan "Uji mikrofon" tetap ada.
             Tetap di dalam #sfu supaya data-* JS ruang tidak perlu diduplikasi. --}}
        <div class="rapat-lobi" id="sfu-lobi" hidden>
          <div class="rapat-lobi-isi">
            <p class="rapat-lobi-nama">{{ $nama }}</p>
            <label>Kamera
              <select id="sfu-lobi-kamera"><option value="">Memuat…</option></select></label>
            <label>Mikrofon
              <select id="sfu-lobi-mik"><option value="">Memuat…</option></select></label>
            <p class="pesan" id="sfu-lobi-status" role="status">
              Menyiapkan perangkat… kamera hanya perlu izin sekali.
            </p>
            <div class="tombol-baris">
              <button type="button" class="tombol samar" id="sfu-lobi-uji">Uji mikrofon</button>
              <button type="button" class="tombol utama" id="sfu-lobi-masuk">Masuk Rapat</button>
            </div>
            <p class="bantuan">Gambar Anda belum terkirim ke siapa pun sampai Anda menekan
              <b>Masuk Rapat</b> — setelah itu tampil di kotak panggung.</p>
          </div>
        </div>

        <div class="rapat-kendali">
          {{-- Jalur cadangan. `hidden` sejak awal: lobi yang jadi jalan utama, dan
               tombol ini hanya muncul bila peramban tanpa dukungan kamera atau
               penyiapan perangkat gagal. Tanpa `hidden`, tombolnya sempat tampil
               sebelum JS siap sehingga klik pertama tidak bereaksi apa pun. --}}
          <button type="button" class="tombol" id="sfu-mulai" hidden>Masuk Rapat</button>
          <button type="button" class="tombol samar" id="sfu-mik" hidden>Matikan mikrofon</button>
          <button type="button" class="tombol samar" id="sfu-kamera" hidden>Matikan kamera</button>
          {{-- Presentasi: menukar jalur KAMERA dengan gambar/video dari berkas,
               jadi tidak perlu layar-bagi dan tidak ada perubahan di server.
               Muncul hanya bila pengguna memang boleh menerbitkan (sama seperti
               tombol kamera), supaya penonton webinar tidak melihat tombol mati. --}}
          <button type="button" class="tombol samar" id="sfu-presentasi" hidden>Bagikan presentasi</button>
          <input type="file" id="sfu-berkas" accept="image/*,video/*" hidden>
          <button type="button" class="tombol samar" id="sfu-angkat" hidden>Angkat tangan</button>
          <button type="button" class="tombol samar" id="sfu-berbicara" hidden>Minta berbicara</button>
          <button type="button" class="tombol peringatan" id="sfu-henti" hidden>Keluar</button>
        </div>

        <p class="pesan" id="sfu-status" role="status">Siap. Tekan <b>Masuk Rapat</b> untuk menyalakan kamera &amp; mikrofon.</p>
      </div>

      <aside class="rapat-sisi">
        <div class="rapat-tab">
          <button type="button" class="tab aktif" data-panel="obrolan">Obrolan</button>
          <button type="button" class="tab" data-panel="peserta">Peserta <span id="sfu-jumlah2">0</span></button>
        </div>

        <div class="rapat-panel" id="panel-obrolan">
          <div class="rapat-log" id="sfu-obrolan" role="log" aria-live="polite"></div>
          <form class="rapat-kirim" id="sfu-form-chat">
            <input id="sfu-teks" maxlength="500" placeholder="Tulis pesan…" autocomplete="off">
            <button class="tombol utama mini" type="submit">Kirim</button>
          </form>
          <div class="rapat-reaksi" id="sfu-reaksi">
            <button type="button" data-emoji="👍">👍</button>
            <button type="button" data-emoji="👏">👏</button>
            <button type="button" data-emoji="❤️">❤️</button>
            <button type="button" data-emoji="😂">😂</button>
            <button type="button" data-emoji="🎉">🎉</button>
          </div>
        </div>

        <div class="rapat-panel" id="panel-peserta" hidden>
          <div class="rapat-log" id="sfu-daftar-peserta"></div>
        </div>
      </aside>
    </div>
  </div>
  <script src="/js/hub-rapat.js?v={{ filemtime(public_path('js/hub-rapat.js')) }}" defer></script>
@endif

<noscript>
  <div class="pesan" role="alert">
    Ruang rapat memerlukan JavaScript untuk menyalakan kamera &amp; mikrofon.
    Aktifkan JavaScript, lalu muat ulang halaman ini.
  </div>
</noscript>

<p class="bantuan" style="margin-top:1rem">
  Semua media mengalir lewat Cloudflare Realtime (WebRTC) — tidak ada layanan pihak ketiga
  lain yang dipakai. Untuk memakai kamera &amp; mikrofon, peramban memerlukan
  <b>https</b> atau <code>localhost</code>; pada koneksi tidak aman tombol kamera tidak akan berfungsi.
</p>
@endsection

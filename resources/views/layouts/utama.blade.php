<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('judul', 'Beranda') · Agrinas Pangan Nusantara</title>
<meta name="description" content="@yield('deskripsi', 'PT Agrinas Pangan Nusantara (Persero) — perusahaan bidang pangan dan konsultansi konstruksi, serta ruang kerja digital Agrinas Hub.')">
<meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large')">
<link rel="canonical" href="@yield('kanonik', url()->current())">
{{-- ---------------------------------------------------------------- IKON
     Google menampilkan favicon di hasil pencarian bila ukurannya KELIPATAN 48px.
     Sebelumnya hanya ada favicon.png 512x512 — 512 bukan kelipatan 48, jadi Google
     MENGABAIKANNYA dan menampilkan ikon generik. Sekarang disediakan 48/96/144/192
     (semua kelipatan 48) plus 512 untuk PWA, dan .ico multi-ukuran (16/32/48) untuk
     peramban lama. `mask-icon` tidak dipakai: butuh SVG monokrom satu warna yang
     belum ada. --}}
<link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="48x48" href="/img/favicon-48.png">
<link rel="icon" type="image/png" sizes="96x96" href="/img/favicon-96.png">
<link rel="icon" type="image/png" sizes="144x144" href="/img/favicon-144.png">
<link rel="icon" type="image/png" sizes="192x192" href="/img/favicon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/img/favicon-512.png">
<link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#15402f">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Agrinas Pangan Nusantara">
<meta property="og:title" content="@yield('judul', 'Agrinas Pangan Nusantara')">
<meta property="og:description" content="@yield('deskripsi', 'Perusahaan bidang pangan dan konsultansi konstruksi.')">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ url('/img/og-cover.png') }}">
<meta property="og:image:secure_url" content="{{ url('/img/og-cover.png') }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Logo Agrinas Pangan Nusantara di atas latar hijau">
<meta property="og:locale" content="id_ID">
{{-- twitter:image WAJIB ada: banyak pengurai (WhatsApp, Telegram, LinkedIn)
     mengambil gambar dari sini, bukan dari og:image. Tanpa ini kartu pratinjau
     tautan tampil tanpa gambar. --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="@yield('judul', 'Agrinas Pangan Nusantara')">
<meta name="twitter:description" content="@yield('deskripsi', 'Perusahaan bidang pangan dan konsultansi konstruksi.')">
<meta name="twitter:image" content="{{ url('/img/og-cover.png') }}">
<meta name="twitter:image:alt" content="Logo Agrinas Pangan Nusantara di atas latar hijau">

{{-- Data terstruktur schema.org: dipakai Google untuk panel pengetahuan dan
     dipakai mesin pencari AI untuk mengenali pemilik situs. Ditulis sebagai
     array PHP lalu di-json_encode, BUKAN sebagai JSON mentah: kunci "@context"
     di dalam <script> dibaca Blade sebagai direktif dan merusak hasil kompilasi
     (terbukti: "unexpected end of file" -> seluruh situs 500). --}}
@php
  $ld = [
    '@context' => 'https://schema.org',
    '@graph' => [
      [
        '@type' => 'Organization',
        '@id' => 'https://agrinashub.com/#organisasi',
        'name' => 'PT Agrinas Pangan Nusantara (Persero)',
        'alternateName' => 'Agrinas',
        'url' => 'https://agrinashub.com/',
        'logo' => ['@type' => 'ImageObject', 'url' => 'https://agrinashub.com/img/favicon-512.png',
                   'width' => 512, 'height' => 512],
        'image' => 'https://agrinashub.com/img/og-cover.png',
        'description' => 'Perusahaan bidang pangan dan konsultansi konstruksi Indonesia, pengelola Koperasi Desa/Kelurahan Merah Putih (KDKMP).',
        'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'ID'],
      ],
      [
        '@type' => 'WebSite',
        '@id' => 'https://agrinashub.com/#situs',
        'url' => 'https://agrinashub.com/',
        'name' => 'Agrinas Pangan Nusantara',
        'inLanguage' => 'id-ID',
        'publisher' => ['@id' => 'https://agrinashub.com/#organisasi'],
      ],
    ],
  ];
@endphp
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<link rel="stylesheet" href="/css/hub.css?v={{ filemtime(public_path('css/hub.css')) }}">
@stack('gaya')
<script src="/js/hub.js?v={{ filemtime(public_path('js/hub.js')) }}" defer></script>
{{-- jQuery TIDAK dimuat di sini. Ia hanya dibutuhkan Messenger (Select2), dan
     halaman itu memuatnya sendiri lewat @push('skrip') di _pesan-sisi.
     Memuatnya di SETIAP halaman berarti ±30 KB terbuang untuk fitur yang tidak
     dipakai. Salinan tetap dilayani dari /vendor (bukan CDN) supaya perilaku
     form bisa direproduksi di jaringan lapangan. --}}
</head>
@php
  $u = auth()->user();
  $menu = daftar_menu($u);
  $aktif = $aktif ?? '';
  // Beranda per peran: pusat/HR/PIC -> /admin, manajer -> /kerja, keuangan ->
  // /keuangan (satu sumber: rute_dasbor(), sama dengan yang dipakai Masuk &
  // Beranda saat mengalihkan pengguna sesudah masuk).
  $ruteDasbor = rute_dasbor($u);

  /**
   * Bilah bawah ponsel: 4 menu TETAP — Dasbor, Asisten AI, Message, Semua
   * Menu (/fitur) — sama persis untuk admin maupun manajer.
   *
   * Sengaja TIDAK diturunkan dari daftar_menu(): dulu isinya "3 menu teratas +
   * menu yang sedang dibuka", sehingga (a) susunannya bergeser tiap halaman yang
   * dibuka dan (b) admin & manajer melihat susunan berbeda. Daftar tetap menutup
   * keduanya. "Dasbor" tetap ikut walau logo di kepala juga menuju ke sana —
   * di bilah bawah ia menu yang diharapkan ada.
   *
   * "Message" menggantikan "Distribusi" DI SINI SAJA (permintaan Bos): di ponsel
   * percakapan jauh lebih sering dibuka, dan Distribusi tetap terjangkau lewat
   * "Semua Menu". Di sidebar desktop butir Message DITAMBAH, tidak menggantikan.
   */
  $bawah = collect([
      ['dasbor', $ruteDasbor, 'Dashboard'],
      ['asisten', route('asisten'), 'Asisten AI'],
      ['pesan', route('workspace.pesan'), 'Message'],
      // Empat butir TETAP (permintaan Bos): "Semua Menu" tinggal di bilah bawah.
      // Menu pojok kanan atas HANYA akun (nama + Keluar), tanpa butir menu —
      // jadi tidak ada pintu ganda. Susunannya wajib sama untuk admin & manajer.
      ['fitur', route('fitur'), 'Semua Menu'],
  // Peran pengawasan tidak membuka Asisten AI, Message, maupun Ruang Kerja
  // (semuanya perlu tulis untuk berguna). Menawarkan butir yang menuju 403
  // adalah cacat navigasi, jadi dibuang di sini.
  ])->reject(fn ($b) => $u?->pantau() && in_array($b[0], ['asisten', 'pesan'], true))->values();
  // Halaman Messenger memakai $aktif='workspace' (ia salah satu modul Ruang
  // Kerja), padahal sidebar & bilah bawah punya butir tersendiri ("Message").
  // Menyorot "Ruang Kerja" saat pengguna sedang di /workspace/pesan membuat
  // butir yang baru saja dikliknya tampak tidak bekerja — jadi satu nilai
  // turunan ini dipakai KEDUA navigasi sekaligus supaya keduanya sepakat.
  // (Halaman /workspace sendiri tidak tersentuh: rutenya bernama 'workspace'.)
  $aktifNav = str_starts_with((string) optional(request()->route())->getName(), 'workspace.pesan')
      ? 'pesan' : $aktif;
  // Kunci `$aktif` ditulis tangan di controller, jadi ia bisa TIDAK ADA di daftar
  // menu peran yang membuka halaman itu: Presensi Manajer tampil di menu
  // pengawasan dengan kunci 'presensi', sedangkan halamannya menandai diri
  // 'agri' (kunci AgriConnect) — butir menu tak pernah menyala. Bila kuncinya
  // tak ditemukan, cari butir menu yang RUTE-nya sama dengan rute sekarang:
  // nama rute adalah kebenaran yang tidak bisa salah tulis.
  if ($menu->doesntContain(fn ($m) => $m[0] === $aktifNav)) {
      $namaRute = (string) optional(request()->route())->getName();
      $cocok = $menu->first(fn ($m) => $m[1] === $namaRute);
      if ($cocok !== null) {
          $aktifNav = $cocok[0];
      }
  }
  // Kunci menu Dasbor manajer = 'kerja' (rute bernama sama); disamakan supaya
  // butir Dasbor tetap tersorot di /kerja.
  // Keuangan tidak punya halaman /admin: berandanya buku kas (/keuangan), jadi
  // butir "Dashboard" di bilah bawah harus menyala di sana.
  $aktifBawah = match (true) {
      $aktifNav === 'kerja' => 'dasbor',
      $u && $u->keuangan() && $aktifNav === 'keuangan' => 'dasbor',
      default => $aktifNav,
  };

  /**
   * Jumlah pengumuman yang belum dibaca pengguna ini — angka di lencana lonceng.
   *
   * Dihitung SEKALI di layout, bukan di tiap halaman: satu tempat, jadi tidak
   * mungkin berbeda antar halaman. Tahan galat karena layout dipakai juga oleh
   * halaman publik/instalasi awal — lonceng hilang lebih baik daripada 500.
   */
  try {
      $belumBaca = $u ? \App\Models\PengumumanBaca::belumDibaca($u) : 0;
  } catch (\Throwable) {
      $belumBaca = 0;
  }
  // Jumlah pesan Messenger belum dibaca — SATU angka untuk lencana di bilah bawah
  // ponsel dan di butir menu "Message" sidebar. Perhitungannya milik model
  // (RuangObrolan::belumDibaca) supaya batas lingkupnya tidak pernah berbeda
  // dengan daftar ruang di halaman Messenger. Try sendiri: kalau tabel obrolan
  // bermasalah, lencana lonceng jangan ikut hilang.
  try {
      $belumPesan = $u ? \App\Models\RuangObrolan::belumDibaca($u) : 0;
  } catch (\Throwable) {
      $belumPesan = 0;
  }
  /**
   * Pengumuman PRIBADI yang harus terbaca di layar pertama setelah masuk.
   *
   * Bos 2026-10-22: akun manajer SIMKOPDES harus langsung menerima kredensial
   * AgriConnect-nya saat login pertama — bukan tersembunyi di balik lonceng.
   * `pribadi_baru` dipasang MasukController hanya pada permintaan sesudah login,
   * jadi layar ini muncul sekali, bukan tiap halaman.
   */
  $pribadi = collect();
  if ($u && session('pribadi_baru')) {
      try {
          $pribadi = \App\Models\PengumumanBaca::pribadiBelumDibaca($u);
      } catch (\Throwable) {
          $pribadi = collect();
      }
  }
  // Lencana per butir menu: kunci menu => jumlah. Tak ada kuncinya = tak berlencana.
  $lencana = ['pesan' => $belumPesan];
  // Angka lencana dipotong di 99+ — satu tempat, dipakai semua lencana.
  $angka = fn (int $n): string => $n > 99 ? '99+' : (string) $n;
@endphp
<body class="@yield('kelas_tubuh', $u ? '' : 'latar-publik')">
@if ($u)
<a class="lompat" href="#isi">Lompat ke isi</a>
{{-- Indikator proses: dinyalakan hub.js tiap permintaan AJAX. Tanpa JS ia tetap
     tersembunyi, jadi tidak ada bilah yang nyangkut di halaman biasa. --}}
<div id="hub-proses" hidden role="progressbar" aria-label="Memuat data"></div>
<div class="shell">
  <aside class="sisi">
    {{-- Baris pertama kepala: merek + lonceng. Dibungkus supaya `.sisi` tetap
         KOLOM seperti semula — mengubah .sisi jadi baris-wrap membuat nav-nya
         kehilangan sifat gulir dan seluruh isi sidebar meluber 314px keluar
         layar (terukur). Dengan pembungkus ini tidak ada satu pun aturan .sisi
         yang perlu diubah. --}}
    <div class="sisi-kepala">
    <a class="merek" href="{{ $ruteDasbor }}"
       aria-label="Agrinas Hub — dasbor" title="Ke dasbor">
      <img src="/img/logo-agrinas.png" alt="Agrinas Pangan Nusantara" width="452" height="215">
      <div class="nama"><b>Agrinas Hub</b></div>
    </a>
    {{-- Lonceng notifikasi pengumuman — DESKTOP saja, sebaris dengan logo &
         tulisan "Agrinas Hub". Ikonnya lonceng (memang soal notifikasi) dan
         angkanya disebut lewat aria-label. Di PONSEL ia tidak ditampilkan:
         tugasnya pindah ke butir "Notifikasi" di panel menu pojok kanan atas. --}}
    <a class="lonceng" href="{{ route('pengumuman') }}"
       title="Pengumuman{{ $belumBaca > 0 ? ' — '.$belumBaca.' belum dibaca' : '' }}"
       aria-label="Pengumuman{{ $belumBaca > 0 ? ', '.$belumBaca.' belum dibaca' : '' }}">
      {!! ikon('notif') !!}
      @if ($belumBaca > 0)
        <span class="lencana-notif" aria-hidden="true">{{ $angka($belumBaca) }}</span>
      @endif
    </a>
    {{-- Tombol pojok kanan atas (PONSEL saja — di desktop tidak ditampilkan,
         sebab navigasi sudah ada di sisi kiri). Fungsinya menu AKUN, jadi
         ikonnya tiga garis, bukan lonceng: lonceng di sini dulu menjanjikan
         "ada notifikasi" padahal isinya nama pengguna & Keluar.
         Titik kecil tetap dipasang bila ada pengumuman belum dibaca — penanda,
         bukan lonceng, dan angkanya disebut lewat aria-label. --}}
    <button class="menu-kanan" type="button"
            aria-label="Menu akun{{ $belumBaca > 0 ? ', '.$belumBaca.' pengumuman belum dibaca' : '' }}"
            @if ($belumBaca > 0) data-ada-baru="1" @endif
            aria-haspopup="true" aria-expanded="false" aria-controls="menu-kanan">
      {!! ikon('menu') !!}
      {{-- Lencana merah berisi JUMLAH yang belum dibaca — sama seperti lonceng di
           desktop. Sebelumnya di ponsel cuma titik kecil tanpa angka, jadi tidak
           terbaca "ada berapa" (dan warnanya pun tak kelihatan merah). --}}
      @if ($belumBaca > 0)
        <span class="lencana-notif" aria-hidden="true">{{ $angka($belumBaca) }}</span>
      @endif
    </button>
    <dialog class="menu-kanan-panel" id="menu-kanan" aria-label="Menu akun">
      <div class="menu-pengguna">
        <b>{{ $u->nama }}</b>
        <small>{{ $u->email }}</small>
        <span class="tanda">{{ \App\Models\Domain::LABEL_PERAN[$u->peran] ?? $u->peran }}@if ($u->pic() && $u->wilayah) — {{ \App\Support\Wilayah::label($u->wilayah) }}@endif</span>
      </div>
      <a class="menu-butir" href="{{ route('pengumuman') }}">
        {!! ikon('notif', 'ik kecil') !!}
        <span>Notifikasi</span>
        @if ($belumBaca > 0)<span class="menu-lencana">{{ $angka($belumBaca) }}</span>@endif
      </a>
      <a class="menu-butir keluar" href="{{ route('keluar') }}">{!! ikon('keluar', 'ik kecil') !!}<span>Keluar</span></a>
    </dialog>
    </div>{{-- /.sisi-kepala --}}
    <nav class="nav-sisi" aria-label="Menu utama">
      @foreach ($menu as [$kunci, $rute, $label])
        {{-- Lencana "belum dibaca" ditaruh DI DALAM butir menunya (CSS
             margin-left:auto) sehingga ia mengikuti panjang label apa pun,
             bukan dipaku ke koordinat tertentu.
             SATU BARIS, tanpa jeda/indentasi di antara tag: teks apa pun yang
             terselip di dalam <a> ikut terbaca `textContent`, dan uji lain
             membandingkannya persis dengan label ("Konferensi" != "Konferensi\n "). --}}
        <a href="{{ route($rute) }}" class="{{ $aktifNav === $kunci ? 'ini' : '' }}"{!! $aktifNav === $kunci ? ' aria-current=page' : '' !!}>{!! ikon($kunci) !!}<span>{{ $label }}</span>@if (($lencana[$kunci] ?? 0) > 0)<span class="menu-lencana">{{ $angka($lencana[$kunci]) }}</span>@endif</a>
      @endforeach
    </nav>
    <div class="kaki">
      <div class="kotak-pengguna">
        <b title="{{ $u->nama }}">{{ $u->nama }}</b>
        <small>{{ $u->email }}</small>
        <span class="tanda">{{ \App\Models\Domain::LABEL_PERAN[$u->peran] ?? $u->peran }}@if ($u->pic() && $u->wilayah) — {{ \App\Support\Wilayah::label($u->wilayah) }}@endif</span>
      </div>
      <a class="tombol samar lebar" href="{{ route('keluar') }}" title="Keluar" aria-label="Keluar">{!! ikon('keluar', 'ik kecil') !!}<span class="label-keluar">Keluar</span></a>
    </div>
  </aside>
  <main class="isi" id="isi">
@else
<main class="isi polos">
@endif
    {{-- Pesan dari redirect: Laravel menyimpannya di session (flash), bukan di
         query string — tanpa dua baris ini semua ->with('pesan'/'galat')
         hilang tanpa jejak dan pengguna tak tahu aksinya berhasil atau gagal. --}}
    @if (session('pesan'))<div class="pesan ok" role="status">{{ session('pesan') }}</div>@endif
    @if (session('galat'))<div class="pesan rusak" role="alert">{{ session('galat') }}</div>@endif
    @if (request('pesan'))<div class="pesan ok" role="status">{{ request('pesan') }}</div>@endif
    @if (session('rahasia_baru'))
      <div class="pesan kunci" role="status"><b>Sandi sekali tampil</b><code>{{ session('rahasia_baru') }}</code>
        <small>Catat sekarang — halaman ini tidak menampilkannya lagi setelah dimuat ulang.</small></div>
    @endif
    @if (request('galat'))<div class="pesan rusak" role="alert">{{ request('galat') }}</div>@endif
    @if ($errors->any())
      <div class="pesan rusak" role="alert">{{ $errors->first() }}</div>
    @endif
    @if ($u && $u->wajib_ganti && $aktif !== 'profil')
      <div class="pesan ingat">Sandi awal masih dipakai. <a href="{{ route('profil') }}">Ganti sekarang</a>.</div>
    @endif
    {{-- Layar sambutan pengumuman PRIBADI (mis. kredensial AgriConnect). Dirender
         di sini, bukan di halaman dasbor, supaya tetap muncul apa pun halaman
         pendaratan peran itu. "Mengerti" menandai sudah dibaca (rute yang sama
         dengan halaman Pengumuman), jadi tidak muncul lagi di login berikutnya. --}}
    @if ($pribadi->isNotEmpty())
      <dialog class="modal" id="pribadi-modal" aria-labelledby="pribadi-judul">
        <form method="post" action="{{ route('pengumuman.baca') }}">
          @csrf
          <header class="modal-kepala">
            <h2 id="pribadi-judul">Pemberitahuan untuk Anda</h2>
            <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">Tutup</button>
          </header>
          <div class="modal-badan">
            @foreach ($pribadi as $s)
              <article class="pribadi-isi">
                <h3>{{ $s->label_perihal() }}</h3>
                <div class="pribadi-teks">{!! nl2br(e($s->pesan)) !!}</div>
              </article>
            @endforeach
            <p class="bantuan">Simpan informasi ini di tempat aman. Pengumuman ini hanya
              bisa dilihat oleh akun Anda.</p>
          </div>
          <footer class="modal-kaki">
            <a class="tombol samar" href="{{ route('pengumuman') }}">Buka halaman Pengumuman</a>
            <button class="tombol utama" type="submit">Mengerti</button>
          </footer>
        </form>
      </dialog>
      <script>
        (function () {
          var d = document.getElementById('pribadi-modal');
          if (d && typeof d.showModal === 'function') d.showModal();
        })();
      </script>
    @endif
    @auth
    {{-- Modal konfirmasi BERSAMA: dipakai semua tombol merusak (hapus, turunkan,
         batalkan, lepas tautan). Ditaruh di layout supaya setiap halaman punya
         satu gaya konfirmasi — `window.hubKonfirmasi()` di hub.js yang mengisi
         teksnya. Tombol "Ya" mengirim returnValue 'ya'; Esc = batal.
         HANYA untuk pengguna yang sudah masuk: halaman /masuk memakai layout
         ini juga, dan dialog ini menambah tombol submit tersembunyi yang
         membingungkan pemilih `button[type=submit]` (termasuk skrip uji). --}}
    <dialog class="modal" id="konfirmasi-modal" aria-labelledby="konfirmasi-judul">
      <form method="dialog">
        <header class="modal-kepala">
          <h2 id="konfirmasi-judul">Konfirmasi</h2>
          <button class="tombol samar mini ikon-tutup" type="submit" value="batal"
                  aria-label="Tutup">&times;</button>
        </header>
        <div class="modal-badan">
          <p data-konfirmasi-teks>Lanjutkan?</p>
        </div>
        <footer class="modal-kaki">
          <button class="tombol samar" type="submit" value="batal">Batal</button>
          <button class="tombol utama" type="submit" value="ya">Ya, Lanjutkan</button>
        </footer>
      </form>
    </dialog>
    @endauth
    @yield('isi')
    {{-- Skrip khusus satu halaman (mis. ONLYOFFICE di editor dokumen). Ditaruh
         setelah isi supaya elemen wadahnya sudah ada saat skrip dijalankan. --}}
    @stack('skrip')
  </main>
@if ($u)</div>
  {{-- Bilah bawah ponsel: 4 menu tetap (Dasbor, Asisten AI, Distribusi, Semua
       Menu) — identik untuk admin & manajer. Tidak ada menu "sedang dibuka"
       yang menyelipkan diri, jadi urutannya tak berubah antar halaman. --}}
  <nav class="nav-bawah" aria-label="Menu utama (mobile)">
    @foreach ($bawah as [$kunci, $tujuan, $label])
      {{-- Lencana memakai `.lencana-notif` yang SAMA dengan lonceng, jadi
           bentuk & warnanya pasti ikut aturan lencana yang sudah ada.
           SATU BARIS: teks apa pun yang terselip di dalam <a> ikut terbaca
           `textContent` dan merusak uji yang membandingkan label menu persis. --}}
      <a href="{{ $tujuan }}" class="{{ $aktifBawah === $kunci ? 'ini' : '' }}"{!! $aktifBawah === $kunci ? ' aria-current=page' : '' !!}>{!! ikon($kunci, 'ik-bawah') !!}<span>{{ $label }}</span>@if (($lencana[$kunci] ?? 0) > 0)<span class="lencana-notif" aria-hidden="true">{{ $angka($lencana[$kunci]) }}</span>@endif</a>
    @endforeach
  </nav>
@endif
</body>
</html>

@extends('layouts.utama')

@section('judul', 'Presensi')
@section('isi')
@php
  $peta = fn ($lat, $lng) => 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng.'#map=17/'.$lat.'/'.$lng;
  // Satu formulir melayani kedua aksi; yang membedakan hanya label & nama aksinya.
  // Check In / Check Out TIDAK ditulis dua kali — foto, lokasi, keterangan, dan
  // penjagaan waktunya sama, jadi dua salinan markup hanya akan berbeda
  // diam-diam kelak (dan itulah yang dulu membuat Check Out memakai kamera
  // belakang).
  // Presensi hari ini bisa datang dari Hub maupun SIMKOPDES (`$asli`), jadi
  // yang menentukan tombolnya bukan `$hariIni` saja: orang yang sudah Check In
  // di SIMKOPDES harus melihat tombol Check Out, bukan Check In lagi.
  $perluIsi = ! $asli || (string) $asli->keluar === '';
  $aksi = $asli && (string) $asli->masuk !== '' ? 'keluar' : 'masuk';
  $lbl = $aksi === 'keluar' ? 'Check Out' : 'Check In';
  $manajer = (bool) ($kdkmp['manajer'] ?? false);
  $terhubung = (bool) ($kdkmp['terhubung'] ?? false);
  $dariSimp = $asli !== null && str_contains((string) $asli->dari, 'SIMP');
  $statusHari = ! $asli || (string) $asli->masuk === ''
    ? ['Belum Check In', 's-BARU']
    : ((string) $asli->keluar === ''
      ? ['Sudah Check In — sisa Check Out', 's-DIAJUKAN']
      : ['Presensi lengkap', 's-SELESAI']);
@endphp

<header class="kepala">
  <div>
    <h1>Presensi</h1>
    <p class="tenang">Absensi mandiri &amp; daftar hadir KDKMP — foto langsung dari kamera, titik lokasi, dan jam perangkat.</p>
  </div>
  {{-- Halaman ini SENGAJA tidak memasang kisi menu AgriConnect: yang datang ke
       sini sedang bekerja (Check In/Check Out & daftar hadir), dan dua puluh
       kartu menu di atasnya hanya menggeser bagian yang dicari ke bawah layar.
       Jalan kembali ada di menu samping. Jangan pasang lagi `agri._nav` di sini
       tanpa alasan baru — lihat permintaan Bos 2026-10-16. --}}
</header>

{{-- Ringkasan: angka yang orang cari saat membuka halaman ini, bukan arsipnya.
     Nilainya dihitung bulan berjalan (zona Asia/Jakarta) dari presensi sendiri. --}}
<div class="petak">
  <div class="kartu angka"><small>Hari ini</small><b>{{ $statusHari[0] }}</b>
    <p class="bantuan">
      @if (! $asli || (string) $asli->masuk === '') Belum ada presensi hari ini.
      @else Masuk {{ $asli->masuk }}@if ((string) $asli->keluar !== '') · Keluar {{ $asli->keluar }}@endif
        @if ($dariSimp)
          <br>dari SIMKOPDES
        @endif
      @endif
    </p>
  </div>
  <div class="kartu angka"><small>Hadir {{ $rekap['mulaiBulan'] }}</small>
    <b>{{ number_format($rekap['hariBulanIni'], 0, ',', '.') }}</b>
    <p class="bantuan">hari presensi tercatat (Hub &amp; SIMKOPDES)</p>
  </div>
  <div class="kartu angka"><small>Terlambat &gt;{{ $rekap['jamMasuk'] }}</small>
    <b>{{ number_format($rekap['terlambat'], 0, ',', '.') }}</b>
    <p class="bantuan">dari {{ number_format($rekap['hariBulanIni'], 0, ',', '.') }} hari</p>
  </div>
  <div class="kartu angka"><small>Rata-rata masuk</small>
    <b>{{ $rekap['jamMasukRata'] !== '' ? $rekap['jamMasukRata'] : '—' }}</b>
    <p class="bantuan">jam absen tercatat</p>
  </div>
</div>

{{-- Absensi hari ini: formulir kamera TIDAK di dalam modal lagi. Di SIMKOPDES
     presensi itu halaman kerja — form, foto, dan tombol kirim terlihat sekaligus
     — dan modal menyembunyikan justru bagian yang paling perlu dipandang (foto
     sendiri). Kamera tetap hanya hidup selagi formulir ini ada di halaman dan
     dimatikan begitu foto diambil atau hari kerja lengkap. --}}
<section class="blok">
  <div class="kepala-blok">
    <h2>Absensi Hari Ini</h2>
    <span class="lencana {{ $statusHari[1] }}">{{ $statusHari[0] }}</span>
  </div>

  <div class="dua-kolom">
    <div>
      @if ($perluIsi)
        {{-- Foto diambil dari KAMERA LANGSUNG, bukan berkas yang dipilih: tidak
             ada `<input type=file>` di halaman ini, dan servernya pun menolak
             jalur berkas. Lihat /js/hub-agri.js — potret dipotong 3:4 di kanvas
             lalu dikirim sebagai data URL, jadi foto lama dari galeri tidak bisa
             dipakai. Waktu & zona perangkat ikut dikirim supaya jam yang tercatat
             adalah jam yang dilihat pengguna di ponselnya, dan server menolaknya
             bila jam itu melenceng terlalu jauh. --}}
        <form method="post" action="{{ route('agri.presensi.simpan') }}"
              data-presensi data-url-unggah="{{ route('agri.presensi.url') }}" class="form-cepat">
          @csrf
          <input type="hidden" name="aksi" value="{{ $aksi }}">
          {{-- Dua jalur foto, dan yang dipakai lebih dulu adalah yang pertama:
               peramban menaruh berkasnya langsung di R2 lalu mengisi `foto_kunci`
               dengan NAMANYA. `foto_data` hanya cadangan bila unggah langsung
               gagal (mis. CORS/jaringan), supaya presensi tidak pernah batal. --}}
          <input type="hidden" name="foto_kunci" value="">
          <input type="hidden" name="foto_data" value="">
          <input type="hidden" name="waktu" value="">
          <input type="hidden" name="zona" value="">
          <input type="hidden" name="lat" value="">
          <input type="hidden" name="lng" value="">
          <input type="hidden" name="akurasi" value="">
          <input type="hidden" name="lokasi" value="">

          {{-- Judul kegiatan WAJIB — sama seperti SIMKOPDES (``Judul kegiatan
               wajib diisi``). Keterangan inilah isi kolom Kegiatan di daftar
               hadir; baris tanpa keterangan tidak bisa diaudit. --}}
          <label>Judul kegiatan
            <input name="catatan" required minlength="3" maxlength="200"
                   value="{{ old('catatan') }}" placeholder="Mis. kunjungan koperasi binaan, dinas luar, izin">
          </label>

          <span class="label-kamera">Foto {{ $lbl }}
            <small class="bantuan">Wajib, diambil dari kamera saat ini juga — potret 3:4, dikecilkan otomatis di ponsel sebelum dikirim (hemat kuota &amp; cepat di sinyal lemah).</small>
          </span>
          <div class="kamera-bingkai">
            {{-- autoplay + playsinline + muted wajib: tanpa ketiganya pratinjau
                 tidak jalan di peramban ponsel. Bingkai 3:4 karena potretnya
                 memang potret — kotak lanskap membuat wajah terlihat kecil dan
                 hasilnya harus dipotong lagi setelahnya. --}}
            <video data-pratinjau autoplay playsinline muted aria-label="Pratinjau kamera"></video>
            <img data-potret class="potret" alt="Foto yang baru diambil" hidden>
            <p class="bantuan kamera-pesan" data-kamera-pesan role="status">Menyiapkan kamera…</p>
          </div>
          <div class="tombol-baris">
            <button class="tombol utama" type="button" data-jepret hidden>Ambil Foto</button>
            <button class="tombol samar" type="button" data-ulang hidden>Ulangi</button>
            <button class="tombol samar" type="button" data-nyalakan hidden>Nyalakan kamera</button>
            <button class="tombol samar" type="button" data-lokasi>Ambil titik lokasi</button>
          </div>
          <p class="bantuan" data-lokasi-status role="status">Titik lokasi wajib untuk Check In maupun Check Out, diambil saat ini juga.</p>

          <footer class="modal-kaki">
            {{-- Tombol kirim TERKUNCI sampai foto & titik lokasi lengkap (diurus
                 hub-agri.js). Alasan terkuncinya ditulis di sebelahnya supaya
                 tombol kelabu tidak tampak rusak — tanpa keterangan, pengguna
                 hanya menekannya berulang lalu mengira aplikasinya menggantung. --}}
            <p class="bantuan" data-siap-status role="status">Belum bisa dikirim: ambil foto dari kamera.</p>
            <button class="tombol utama" type="submit">{{ $lbl }}</button>
          </footer>
        </form>
      @else
        <p class="pesan ok" role="status">
          Presensi hari ini <b>sudah lengkap</b> — masuk {{ $asli->masuk }}, keluar {{ $asli->keluar }}.
          @if ($dariSimp) Catatannya berasal dari <b>SIMKOPDES</b>, jadi tidak ada yang perlu diisi lagi di sini.@endif
          Besok bisa Check In lagi.
        </p>
        <p class="bantuan">
          Check In memberi {{ \App\Http\Controllers\AgriController::POIN_PRESENSI }} poin sekali per hari;
          menekan tombolnya lagi tidak menambah poin maupun baris.
        </p>
      @endif
    </div>

    <div>
      <h3>Jam &amp; Sinkronisasi</h3>
      <p class="bantuan">
        Jam yang dicatat adalah jam perangkat Anda
        (<b data-jam-perangkat>—</b>), bukan jam server — presensi pukul 00:30 WIB
        tetap tercatat di tanggal itu, bukan tanggal kemarin.
      </p>

      @if ($manajer)
        @if ($terhubung)
          <p class="pesan ok" role="status">
            Terhubung sebagai pendamping SIMKOPDES.
            @if ($namaKoperasi !== '') Koperasi binaan: <b>{{ $namaKoperasi }}</b>. @endif
          </p>
          <p class="bantuan">
            {{ $kdkmp['total'] }} baris daftar hadir ({{ $kdkmp['tertarik'] }} dari SIMKOPDES{{ $kdkmp['tertunda'] > 0 ? ', '.$kdkmp['tertunda'].' menunggu dikirim' : '' }}).
            @if ($kdkmp['terakhir']) Terakhir: {{ tanggal_id($kdkmp['terakhir'], 'd M Y') }}. @endif
            Check In diteruskan ke SIMKOPDES otomatis sampai pukul {{ $cutoff }} WIB.
            Sesudah itu barisnya menunggu di daftar hadir di bawah, dan bisa dikirim
            sengaja lewat tombolnya — absensi SIMKOPDES permanen, jadi jam masuk yang
            keliru tidak bisa dibatalkan.
          </p>
        @else
          <p class="pesan" role="status">
            Check In tersimpan di Hub, <b>belum diteruskan</b> ke SIMKOPDES.
          </p>
          <p class="bantuan">
            Absensi SIMKOPDES tidak punya OAuth/SSO, jadi penerusan menuntut sandi
            akun Anda sendiri — diuji langsung ke sana dan disimpan terenkripsi.
          </p>
          <div class="tombol-baris">
            <button class="tombol utama" type="button"
                    onclick="document.getElementById('kdkmp-hubung')?.showModal()">Hubungkan akun SIMKOPDES</button>
          </div>
        @endif
      @else
        <p class="bantuan">
          Akun Anda bukan akun SIMKOPDES (manajer koperasi), jadi presensi tidak
          diteruskan ke sana dan daftar hadir KDKMP tidak tersedia. Presensi Anda
          tetap tercatat penuh di Hub.
        </p>
      @endif
    </div>
  </div>

</section>


{{-- Wadah daftar hadir KDKMP — cerminan daftar hadir resmi SIMKOPDES sekaligus
     tempat baris Hub yang belum terkirim. Disusun DULU sebelum penjelasannya:
     yang dicari orang adalah daftarnya, bukan keterangan sambungannya. --}}
<section class="blok" id="absen" data-isi="{{ route('agri.presensi') }}" data-params="halaman">
  <h2 id="daftar-hadir">Daftar Hadir KDKMP</h2>

  @if (! $manajer)
    <p class="bantuan">
      Daftar hadir KDKMP hanya dimiliki pendamping yang punya akun SIMKOPDES
      (manajer koperasi). Akun Anda bukan akun SIMKOPDES, jadi tidak ada daftar
      hadir yang bisa ditampilkan — dan sebaliknya, Check In Anda tidak
      diteruskan ke sana.
    </p>
  @else
    @if ($terhubung)
      <p class="bantuan">
        Isinya <b>daftar hadir resmi</b> dari SIMKOPDES, ditambah baris Hub yang belum
        diteruskan ke sana. Menarik daftar tidak menulis apa pun ke SIMKOPDES; yang
        menulis ke sana hanya Check In (otomatis sampai pukul {{ $cutoff }} WIB) dan
        tombol “Kirim” pada baris yang masih menunggu.
      </p>
    @endif

    <form class="saring" method="get" action="{{ route('agri.presensi') }}">
      <label class="cari">
        <span class="sr">Cari kegiatan, tanggal, atau jam absensi</span>
        <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
        </svg>
        <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari kegiatan atau tanggal…">
      </label>
      <a class="tombol samar bersih-saring" href="{{ route('agri.presensi') }}#daftar-hadir"
         @if ($cari === '') hidden @endif>Bersihkan</a>
    </form>

    <div class="tabel-bungkus">
    <table>
      <thead><tr><th>Sumber</th><th>Tanggal</th><th>Masuk</th><th>Keluar</th><th>Bukti</th><th>Kegiatan</th><th>Ke SIMKOPDES</th></tr></thead>
      <tbody>
        @forelse ($baris as $b)
          @php
            $manual = $b->sumber === 'HUB';
            $fotoMasuk = trim((string) $b->masuk_foto) !== ''
              ? route($b->masuk_dari === 'HUB' ? 'agri.presensi.foto' : 'agri.absen.foto', [$b->masuk_id]) : '';
            $fotoKeluar = trim((string) $b->keluar_foto) !== ''
              ? route($b->keluar_dari === 'HUB' ? 'agri.presensi.foto' : 'agri.absen.foto',
                  [$b->keluar_id, 'sisi' => 'keluar']) : '';
            $tertunda = $b->sumber === 'HUB' && trim((string) $b->kirim_pada) === '';
          @endphp
          <tr>
            <td data-l="Sumber">@if ($manual) <span class="pudar">Hub</span> @else <span class="pudar">SIMKOPDES</span> @endif</td>
            <td data-l="Tanggal"><span class="judul-sel">{{ tanggal_id($b->tanggal, 'd M Y') }}</span>
              <small class="pudar">{{ tanggal_id($b->tanggal, 'l') }}</small></td>
            <td data-l="Masuk" @if ($b->masuk_pada !== '') title="{{ $manual ? 'Waktu perangkat: ' : 'SIMKOPDES: ' }}{{ $b->masuk_pada }}" @endif>{{ $b->masuk !== '' ? $b->masuk : '—' }}</td>
            <td data-l="Keluar">{{ $b->keluar !== '' ? $b->keluar : '—' }}</td>
            <td data-l="Bukti">
              <div class="presensi-bukti">
                @if ($fotoMasuk !== '')
                  <a href="{{ $fotoMasuk }}" target="_blank" rel="noopener" title="Foto absen masuk">
                    <img class="foto-presensi" src="{{ $fotoMasuk }}" alt="Foto absen masuk {{ tanggal_id($b->tanggal, 'd M Y') }}" loading="lazy">
                  </a>
                @endif
                @if ($fotoKeluar !== '')
                  <a href="{{ $fotoKeluar }}" target="_blank" rel="noopener" title="Foto absen keluar">
                    <img class="foto-presensi" src="{{ $fotoKeluar }}" alt="Foto absen keluar {{ tanggal_id($b->tanggal, 'd M Y') }}" loading="lazy">
                  </a>
                @endif
                @if ($fotoMasuk === '' && $fotoKeluar === '') <span class="pudar">—</span> @endif
                @if ($b->lat !== '' && $b->lng !== '')
                  <a class="peta-tautan" href="{{ $peta($b->lat, $b->lng) }}" target="_blank" rel="noopener"
                     title="Titik lokasi masuk">Peta{{ $b->keluar !== '' ? ' Masuk' : '' }}</a>
                @endif
                @php
                  $latK = trim((string) ($b->lat_keluar ?? ''));
                  $lngK = trim((string) ($b->lng_keluar ?? ''));
                @endphp
                @if ($latK !== '' && $lngK !== '')
                  <a class="peta-tautan" href="{{ $peta($latK, $lngK) }}" target="_blank" rel="noopener"
                     title="Titik lokasi keluar">Peta Keluar</a>
                @endif
              </div>
            </td>
            <td data-l="Kegiatan">{{ $b->aktivitas !== '' ? $b->aktivitas : '—' }}
              @if ($b->catatan !== '') <small class="pudar">{{ $b->catatan }}</small> @endif</td>
            <td data-l="Ke SIMKOPDES">
              @if (! $manajer)
                <span class="pudar">akun Hub</span>
              @elseif ($b->sumber !== 'HUB')
                <span class="pudar">sudah di SIMKOPDES</span>
              @elseif (trim((string) $b->kirim_pada) !== '')
                <span class="lencana s-SELESAI">terkirim</span>
              @else
                <form method="post" action="{{ route('agri.absen.kirim') }}"
                      data-konfirmasi="Kirim absensi {{ tanggal_id($b->tanggal, 'd M Y') }} ke SIMKOPDES? Absensi SIMKOPDES TIDAK BISA diubah maupun dihapus setelah terkirim.">
                  @csrf
                  <input type="hidden" name="id" value="{{ $b->id }}">
                  @if ($tertunda) <input type="hidden" name="paksa" value="1"> @endif
                  <button class="tombol samar mini" type="submit">Kirim</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="kosong">
            {{ $cari !== ''
                ? 'Tidak ada absensi yang cocok dengan pencarian ini.'
                : ($terhubung ? 'Belum ada absensi. Tekan “Perbarui dari SIMKOPDES”.' : 'Hubungkan akun SIMKOPDES untuk melihat daftar hadir.') }}
          </td></tr>
        @endforelse
      </tbody>
    </table>
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => $kunciHalaman])

    @if ($terhubung)
      <div class="tombol-baris">
        {{-- `data-otomatis`: daftar hadir ditarik SEKALI begitu halaman ini
             dibuka, jadi yang terlihat selalu terbaru tanpa menekan apa pun.
             Sekali per sesi peramban (dijaga di hub-agri.js) — tombolnya tetap
             ada untuk menarik ulang dengan sengaja. --}}
        <form method="post" action="{{ route('agri.absen.tarik') }}" data-otomatis="tarik">
          @csrf
          <button class="tombol samar mini" type="submit">Perbarui dari SIMKOPDES</button>
        </form>
        <form method="post" action="{{ route('agri.absen.lepas') }}" data-konfirmasi="Lepas akun SIMKOPDES? Sandi yang tersimpan akan dibuang (baris absensi tidak dihapus).">
          @csrf
          <button class="tombol samar mini" type="submit">Lepas akun</button>
        </form>
      </div>
    @endif
  @endif
</section>
@if ($manajer && ! $terhubung)
  {{-- Modal menyambung akun, di AKHIR halaman: penjelasan sambungan yang panjang
       tidak boleh mendahului daftar hadir yang justru dicari orang (terukur
       lewat gate: posisi "Daftar Hadir KDKMP" harus sebelum penjelasan ini).
       Sebagai <dialog> ia tidak memakan ruang tata letak sampai dibuka. --}}
  <dialog class="modal" id="kdkmp-hubung" aria-labelledby="kdkmp-hubung-judul">
    <form method="post" action="{{ route('agri.absen.hubungkan') }}">
      @csrf
      <header class="modal-kepala">
        <h2 id="kdkmp-hubung-judul">Hubungkan akun SIMKOPDES</h2>
        <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
      </header>
      <div class="modal-badan">
        <p class="bantuan">Masuk sebagai <b>{{ auth()->user()?->email }}</b> — emailnya harus sama dengan akun SIMKOPDES Anda.</p>

        {{-- Login SIMKOPDES WAJIB menyertakan `recaptcha_token` (v3) atau
             `recaptcha_v2_token` — lihat masuk-*.js (`executeRecaptcha("login")`).
             Kunci reCAPTCHA terikat domain simkopdes.go.id, jadi login tidak bisa
             dijalankan dari server maupun dari halaman Hub. Satu-satunya jalan
             otomatis: pengguna sudah masuk di tab SIMKOPDES sendiri, lalu satu
             klik tautan simpan (bookmarklet) mengirim token ke halaman ini. --}}
        <p class="bantuan"><b>Cara otomatis (sekali simpan):</b> seret tombol ini ke bilah markah peramban —
          <a class="tombol mini" href="javascript:(function(){var%20t=localStorage.getItem('TOKEN_MERAHPUTIH');if(!t){alert('Belum masuk SIMKOPDES di tab ini.');return}location.href='{{ url('/agri/presensi') }}\x23simp='+encodeURIComponent(t)})()">Sambungkan ke Hub</a>
          <br>Lalu: masuk di <a href="https://simkopdes.go.id" target="_blank" rel="noopener">simkopdes.go.id</a> → klik markah itu → token terkirim sendiri.
        </p>

        <details>
          <summary class="bantuan">Tanpa markah: tempel token sendiri</summary>
          <ol class="bantuan" style="padding-left:1.2em">
            <li>Buka <a href="https://simkopdes.go.id" target="_blank" rel="noopener">simkopdes.go.id</a> di tab baru, lalu masuk seperti biasa (captcha dijawab di sana).</li>
            <li>Tekan <b>F12</b> → tab <b>Console</b>, tempel baris ini, tekan Enter:
              <br><code style="user-select:all">copy(localStorage.TOKEN_MERAHPUTIH)</code></li>
            <li>Kembali ke sini, tempel (Ctrl+V) di kolom Token, lalu tekan Hubungkan.</li>
          </ol>
        </details>

        <label>Token SIMKOPDES dari browser
          <input type="text" name="token" maxlength="300" autocomplete="off" spellcheck="false"
                 placeholder="terisi sendiri bila memakai markah di atas">
        </label>

        <details>
          <summary class="bantuan">Atau pakai sandi akun</summary>
          <label>Sandi SIMKOPDES
            <input type="password" name="sandi" maxlength="120" autocomplete="current-password">
          </label>
        </details>

        <p class="bantuan">Token diuji dulu ke SIMKOPDES; yang tidak sah tidak menyimpan apa pun.
          Yang disimpan hanya token sesi, <b>terenkripsi</b> — bukan sandi.
          SIMKOPDES hanya mengizinkan satu sesi per akun, jadi daftar hadir milik siapa pun tidak pernah ditulis dari akun bersama.</p>
      </div>
      <footer class="modal-kaki">
        <button class="tombol samar" type="button" data-tutup>Batal</button>
        <button class="tombol utama" type="submit">Hubungkan</button>
      </footer>
    </form>
  </dialog>
@endif

<script src="/js/hub-agri.js?v={{ filemtime(public_path('js/hub-agri.js')) }}" defer></script>
@endsection

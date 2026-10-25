@extends('layouts.utama')

@section('judul', 'Masuk')
@section('robots', 'noindex, follow')
@section('isi')
<div class="masuk">
  <div class="panel-masuk">
    <section class="panel-kiri">
      {{-- Dua logo berwarna, alas putih supaya warna aslinya tetap terbaca di
           panel hijau tua — sama persis seperti pola di kaki halaman. --}}
      <span class="merek-lockup">
        <img src="/img/logo-danantara-kecil.png" alt="Danantara Indonesia" width="420" height="105">
        <span class="merek-pemisah" aria-hidden="true"></span>
        <img src="/img/logo-agrinas-kecil.png" alt="Agrinas Pangan Nusantara" width="160" height="76">
      </span>
      <h1>Ruang kerja digital PT Agrinas Pangan Nusantara (Persero)</h1>
      <p>Agrinas Hub &mdash; satu tempat untuk menerbitkan tugas, mengirim berkas, dan
        memverifikasi hasil kerja mitra binaan perusahaan.</p>
      <ul class="ciri">
        <li>Alur tugas jelas: baru, dikerjakan, diajukan, selesai.</li>
        <li>Setiap berkas dicatat sidik SHA-256 untuk verifikasi.</li>
        <li>Jejak audit tiap tindakan, tidak bisa dihapus dari antarmuka.</li>
      </ul>
    </section>
    <section class="panel-kanan">
      <h2>Masuk ke akun Anda</h2>
      {{-- Satu pintu sejak 2026-10-24: Hub berdiri sendiri, SIMKOPDES tidak
           lagi dipanggil saat masuk. --}}
      <p class="pudar kecil">Masuk dengan akun Agrinas Hub Anda.</p>
      @if (session('aktivasi'))
        {{-- Akun hasil impor belum punya sandi. Tombolnya DI SINI, bukan
             lompatan otomatis ke /aktivasi: perpindahan halaman yang tak diminta
             itulah yang terbaca sebagai "loading" tak habis di ponsel. --}}
        <a class="tombol utama lebar" href="{{ route('aktivasi', ['email' => session('aktivasi')]) }}">Aktifkan akun saya</a>
        <p class="pudar kecil">Sekali saja: cocokkan tanggal lahir SIMKOPDES, lalu buat sandi Hub Anda sendiri.</p>
      @endif
      <form method="post" action="{{ route('masuk') }}" autocomplete="on">
        @csrf
        <label for="email">Email
          <input id="email" name="email" type="email" required autofocus autocomplete="username"
                 value="{{ old('email') }}" placeholder="nama@agrinas.co.id / email SIMKOPDES">
          <small class="pudar">Contoh email SIMKOPDES: emailanda+sppi@gmail.com</small>
        </label>
        <label for="sandi">Sandi
          <input id="sandi" name="sandi" type="password" required autocomplete="current-password"
                 placeholder="••••••••••">
        </label>
        {{-- Cloudflare Turnstile: widget menyuntikkan input tersembunyi
             `cf-turnstile-response` ke form ini; server memeriksanya di
             MasukController::lolosTurnstile(). Ditaruh SEBELUM tombol supaya
             pengguna melihat kotak verifikasi sebelum menekan Masuk. --}}
        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site') }}"
             data-language="id" data-theme="light" data-size="flexible"></div>
        <button class="tombol utama lebar" type="submit">Masuk</button>
      </form>

      <p class="bantuan">Belum punya akses? Hubungi admin Agrinas untuk dibuatkan akun.
        Lupa sandi? Admin dapat mengatur ulang sandi akun Anda.</p>
      <p class="bantuan">Sudah terdaftar dengan email SIMKOPDES tapi belum pernah membuat sandi?
        <a href="{{ route('aktivasi') }}">Aktifkan akun Anda di sini</a>.</p>
      <p class="bantuan"><a href="{{ route('beranda') }}">&larr; Kembali ke beranda</a></p>
    </section>
  </div>
</div>
@push('skrip')
{{-- Skrip Turnstile: hanya dimuat kalau kuncinya terpasang. --}}
@if (config('services.turnstile.site'))
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
{{-- Tanpa kolom di layar: helper admin boleh membuka /masuk#simp=<token> dan
     token itu langsung dikirim sendiri (fragmen tidak masuk log server). --}}
<script>
  (function () {
    var cocok = location.hash.match(/[#&]simp=([^&]+)/);
    if (!cocok) return;
    history.replaceState(null, '', location.pathname);
    var form = document.createElement('form');
    form.method = 'post';
    form.action = '{{ route('masuk.simkopdes') }}';
    [['_token', '{{ csrf_token() }}'], ['token', decodeURIComponent(cocok[1])]]
      .forEach(function (pasang) {
        var isian = document.createElement('input');
        isian.type = 'hidden';
        isian.name = pasang[0];
        isian.value = pasang[1];
        form.appendChild(isian);
      });
    document.body.appendChild(form);
    form.submit();
  })();
</script>
@endpush
@endsection

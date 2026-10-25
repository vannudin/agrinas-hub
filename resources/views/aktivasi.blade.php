@extends('layouts.utama')

@section('judul', 'Aktivasi akun')
@section('robots', 'noindex, nofollow')
@section('isi')
<div class="masuk">
  <div class="panel-masuk panel-aktivasi">
    <section class="panel-kanan">
      <h2>Aktifkan akun Anda</h2>
      <p class="pudar kecil">Sekali saja: cocokkan tanggal lahir SIMKOPDES, lalu buat sandi
        Agrinas Hub Anda sendiri. Sesudah aktif, masuk lewat halaman Masuk seperti biasa.</p>
      <form method="post" action="{{ route('aktivasi') }}" autocomplete="on">
        @csrf
        <label for="email">Email SIMKOPDES
          <input id="email" name="email" type="email" required autocomplete="username"
                 value="{{ old('email', $email) }}" placeholder="nama+sppi@gmail.com">
          <small class="pudar">Harus email yang berakhiran <b>+sppi</b> (bukan +ba).</small>
        </label>
        <label for="tanggal_lahir">Tanggal lahir
          <input id="tanggal_lahir" name="tanggal_lahir" type="date" required
                 value="{{ old('tanggal_lahir') }}" min="1940-01-01" max="{{ date('Y-m-d') }}">
          <small class="pudar">Format: <b>hari/bulan/tahun</b> — misal lahir 11 April 2002,
            isi tanggal <b>11</b>, bulan <b>04</b>, tahun <b>2002</b>.</small>
        </label>
        <label for="sandi">Sandi baru
          <input id="sandi" name="sandi" type="password" required minlength="10"
                 autocomplete="new-password" placeholder="Minimal 10 karakter">
        </label>
        <label for="ulang">Ulangi sandi baru
          <input id="ulang" name="ulang" type="password" required minlength="10"
                 autocomplete="new-password" placeholder="••••••••••">
        </label>
        {{-- Captcha Cloudflare Turnstile — sama seperti /masuk. Widget menyuntikkan
             `cf-turnstile-response` ke form ini; server memeriksanya di
             MasukController::lolosTurnstile(). --}}
        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site') }}"
             data-language="id" data-theme="light" data-size="flexible"></div>
        <button class="tombol utama lebar" type="submit">Aktifkan &amp; masuk</button>
      </form>

      <p class="bantuan">Tanggal lahir tidak cocok atau tidak tercatat? Hubungi admin Agrinas
        lewat menu Aspirasi setelah masuk, atau <a href="{{ route('masuk') }}">kembali ke halaman masuk</a>.</p>
    </section>
  </div>
</div>
@endsection

@push('skrip')
@if (config('services.turnstile.site'))
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
@endpush

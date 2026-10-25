@extends('layouts.utama')

@section('judul', 'Hasil Siaran')
@section('isi')
<header class="kepala">
  <div>
    <h1>Hasil Siaran</h1>
    <p class="tenang">
      {{ $kanal === 'EMAIL' ? 'Email' : 'WhatsApp' }} ·
      {{ App\Models\Siaran::label_sasaran($siaran->sasaran) }} ·
      {{ $penerima->count() }} penerima
    </p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('admin.broadcast.tujuan', $siaran->id) }}">Unduh CSV Tujuan</a>
    <a class="tombol samar" href="{{ route('admin.broadcast') }}">Kembali</a>
  </div>
</header>

@if ($kanal === 'EMAIL')
  <div class="pesan {{ $terkirim > 0 ? 'ok' : 'ingat' }}" role="status">
    <b>{{ $terkirim }} dari {{ $penerima->count() }} email berhasil dikirim.</b>
    @if ($mailer !== 'smtp' && $mailer !== 'sendmail')
      <small>
        Pengirim surat saat ini diatur ke <code>{{ $mailer }}</code>, artinya pesan
        <b>tidak benar-benar dikirim ke internet</b> — hanya dicatat di
        <code>storage/logs/laravel.log</code>. Untuk pengiriman sungguhan, isi setelan
        SMTP pada berkas <code>.env</code> lalu bersihkan konfigurasi.
      </small>
    @endif
  </div>

  @if ($gagal)
    <div class="pesan rusak" role="alert">
      <b>{{ count($gagal) }} gagal</b>
      <small>{{ implode(' · ', array_slice($gagal, 0, 6)) }}</small>
    </div>
  @endif

  @foreach ($contoh as $ke => $isi)
    <section class="blok">
      <h2>Contoh isi — {{ $ke }}</h2>
      <pre class="teks-polos">{{ $isi }}</pre>
    </section>
  @endforeach
@else
  <div class="pesan ingat" role="status">
    <b>WhatsApp belum terhubung.</b>
    <small>
      Penyedia WhatsApp resmi belum dipasang di server ini, jadi pesan tidak
      terkirim otomatis. Unduh CSV tujuan, salin isi pesan dari tabel di bawah,
      lalu kirim lewat aplikasi WhatsApp Anda. Setiap baris pesan sudah
      dipersonalisasi (nama &amp; koperasi penerima).
    </small>
  </div>
@endif

<section class="blok">
  <h2>Daftar Penerima &amp; Pesan Terpersonalisasi</h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Penerima</th><th>Email</th><th>Koperasi</th><th>Pesan</th></tr></thead>
    <tbody>
    @foreach ($penerima as $p)
      <tr>
        <td data-l="Penerima"><span class="judul-sel">{{ $p->nama }}</span></td>
        <td data-l="Email">{{ $p->email }}</td>
        <td data-l="Koperasi">{{ $p->koperasi ?: '—' }}</td>
        <td data-l="Pesan"><span class="teks-pesan">{{ App\Models\Siaran::isi_pesan($siaran->pesan, $p, $perihal) }}</span></td>
      </tr>
    @endforeach
    </tbody>
  </table>
  </div>
</section>
@endsection

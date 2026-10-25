@extends('layouts.utama')

@section('judul', $s->label_perihal())
@section('isi')
{{-- Halaman ini dulu "berantakan" karena memakai kelas milik komponen LAIN:
     `.jejak` ternyata bilah progres (`height: 8px` + latar bergaris), jadi tautan
     "← Semua pengumuman" tergencet jadi batang 8px; dan `.kepala .tenang`
     memotong meta dengan ellipsis. Sekarang tiap bagian memakai kelasnya sendiri
     (`.balik`, `.surat-meta`) supaya tidak menabrak arti kelas lain. --}}
<p class="balik"><a href="{{ route('pengumuman') }}">← Semua pengumuman</a></p>

<header class="kepala">
  <div>
    <h1>{{ $s->label_perihal() }}</h1>
  </div>
</header>

{{-- Kop surat: sama dengan daftar, supaya dokumennya tetap terbaca sebagai
     pengumuman resmi perusahaan walau dibuka langsung dari tautan. --}}
<div class="kop">
  <img src="/img/logo-agrinas.png" alt="Logo Agrinas Pangan Nusantara" width="452" height="215">
  <div>
    <b>PT Agrinas Pangan Nusantara</b>
    <small>Pengumuman resmi — dikeluarkan pengelola (Agrinas, HR, atau PIC wilayah)</small>
  </div>
</div>

{{-- SATU bingkai saja: dulu `.blok` (bergaris) memuat `.surat` (bergaris lagi),
     jadi kotak di dalam kotak. Sekarang `.blok` yang jadi bingkainya. --}}
<section class="blok">
  <p class="surat-meta">
    Dari <b>{{ $s->pengirim ?: 'pengelola' }}</b>
    @if ($s->dibuat) · {{ waktu_id($s->dibuat) }} @endif
    · sasaran: {{ \App\Models\Siaran::label_sasaran((string) $s->sasaran) }}
  </p>
  {{-- Isi penuh, apa adanya. `white-space: pre-wrap` (lihat .surat-isi) menjaga
       baris & paragraf yang ditulis pengirim. --}}
  <div class="surat-isi"><p>{{ $s->pesan }}</p></div>

  @if ($pengelola)
    <p class="bantuan">
      Diterbitkan sebagai pengelola. Butuh mengirim ulang lewat email/WhatsApp?
      Pakai <a href="{{ route('admin.broadcast') }}">Broadcast</a>.
    </p>
  @else
    <p class="bantuan">
      {{ $sudahBaca ? 'Pengumuman ini sudah ditandai dibaca.' : 'Pengumuman ini baru saja ditandai dibaca.' }}
    </p>
  @endif
</section>
@endsection

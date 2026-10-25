@extends('layouts.utama')
@section('judul', $dok->judul)
@section('isi')
<header class="kepala">
  <div>
    <h1>{{ $dok->judul }}</h1>
    <p class="tenang">
      {{ $dok->label_jenis() }} · {{ $dok->koperasi?->nama ?? 'Umum' }} ·
      {{ $dok->label_akses() }}
      @if ($dok->pembuat) · dibuat oleh {{ $dok->pembuat->nama }}@endif
    </p>
  </div>
  <div class="tombol-baris">
    @if ($dok->bisaDisunting() && $siap)
      {{-- Tab baru = layar penuh. Di dalam layout Hub editor hanya dapat 70vh
           dan masih menyisakan bilah sisi + nav; untuk menulis dokumen panjang
           itu sempit. `rel="noopener"` wajib: tanpa itu tab baru bisa
           mengakses window.opener dan mengubah halaman asal. --}}
      <a class="tombol utama" href="{{ route('workspace.dokumen.penuh', $dok->id) }}"
         target="_blank" rel="noopener">Buka di tab baru</a>
    @endif
    <a class="tombol samar" href="{{ tautan_berkas_dokumen($dok) }}">Unduh</a>
    <a class="tombol samar" href="{{ route('workspace.dokumen') }}">Daftar Dokumen</a>
  </div>
</header>
@include('workspace._nav', ['mod' => 'dokumen'])

@if (! $siap && $dok->bisaDisunting())
  <div class="pesan rusak" role="alert">
    Editor ONLYOFFICE belum tersambung — <b>ONLYOFFICE_URL</b> atau <b>ONLYOFFICE_JWT_SECRET</b> kosong.
  </div>
@endif

<section class="blok">
  @if ($dok->bisaDisunting())
    @if ($bolehSunting)
      <div class="editor-bingkai"><div id="editor-dokumen" data-editor-onlyoffice></div></div>
    @else
      {{-- Dokumen publik untuk pengguna yang belum diberi izin: DILIHAT sebagai
           pratinjau, bukan editor yang bisa diubah. --}}
      <div class="pesan ingat" id="dok-ajakan">
        <b>Anda melihat dokumen ini sebagai pratinjau.</b>
        Menyuntingnya perlu izin pemiliknya.
        @if (! $permintaanSaya || $permintaanSaya->status === 'ditolak')
          <form method="post" action="{{ route('workspace.dokumen.minta', $dok->id) }}" class="sebaris" style="margin-top:.6rem">
            @csrf
            <input type="text" name="pesan" maxlength="300" placeholder="Keterangan (opsional) — mis. saya perlu memperbaiki angka"
                   aria-label="Keterangan permintaan">
            <button class="tombol utama mini" type="submit">Minta akses sunting</button>
          </form>
        @else
          <div><b>Permintaan Anda sedang menunggu keputusan pemilik.</b>
            Dikirim {{ waktu_id($permintaanSaya->diminta) }}.</div>
        @endif
      </div>
      <div class="editor-bingkai"><div id="editor-dokumen" data-editor-onlyoffice></div></div>
    @endif
  @else
    {{-- PDF: tidak ada editor di sini, jadi yang penting adalah cara MENAIKKAN
         versinya (unggah berkas baru) — bukan editor kosong. --}}
    <div class="dok-pdf">
      <span class="dok-ikon pdf"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/><path d="M9 15h1.5a1.5 1.5 0 010 3H9v-3zm0 0v-4m0 4v3M12.5 11H14a3 3 0 010 6h-1.5z"/></svg></span>
      <div>
        <b>Berkas PDF</b>
        <p class="pudar">PDF tidak disunting di aplikasi ini. Buka atau unduh berkasnya, lalu
          naikkan versinya dengan mengunggah PDF baru.</p>
        <p class="bantuan">Ukuran berkas:
          {{ number_format(($ukuran ?? 0) / 1024, 0, ',', '.') }} KB</p>
      </div>
    </div>

    @if ($bolehSunting)
      <form method="post" action="{{ route('workspace.dokumen.unggah', $dok->id) }}"
            enctype="multipart/form-data" class="grid-form" style="margin-top:.9rem">
        @csrf
        <label class="lebar-penuh">Unggah PDF pengganti
          <input type="file" name="berkas" accept="application/pdf" required>
        </label>
        <div class="lebar-penuh"><button class="tombol utama" type="submit">Naikkan Versi</button></div>
      </form>
    @else
      <div class="pesan ingat">
        <b>Mengunggah versi baru perlu izin.</b>
        @if (! $permintaanSaya || $permintaanSaya->status === 'ditolak')
          <form method="post" action="{{ route('workspace.dokumen.minta', $dok->id) }}" class="sebaris" style="margin-top:.6rem">
            @csrf
            <button class="tombol utama mini" type="submit">Minta akses</button>
          </form>
        @else
          <div>Permintaan Anda sedang menunggu keputusan pemilik.</div>
        @endif
      </div>
    @endif
  @endif

  <p class="bantuan">
    Dibuka dari {{ $dok->pembuat?->nama ?: 'pengguna' }}.
    @if ($dok->akses === 'pribadi')
      <b>Dokumen pribadi</b> — hanya Anda ({{ auth()->user()->nama }}) yang bisa membuka dan membahasnya.
    @elseif ($bolehSunting)
      Bertipe <b>{{ $dok->label_akses() }}</b> — perubahan tersimpan otomatis oleh ONLYOFFICE.
    @else
      Anda <b>tidak sedang menyunting</b>: berkas ini dibuka sebagai pratinjau.
    @endif
    Pembahasannya ada di
    <a href="{{ route('workspace.pesan.ruang', $ruang->id) }}">ruang obrolan dokumen ini</a>
    <span class="pudar">({{ $ruang->jumlah_pesan() }} pesan)</span>.
  </p>
</section>

{{-- Skrip editor HANYA bila dokumen ini memang disunting lewat ONLYOFFICE.
     PDF tidak punya editor di sini, dan memuat api.js untuknya berarti
     mengunduh editor yang tak akan pernah dipakai. --}}
@if ($dok->bisaDisunting())
@push('skrip')
<script src="{{ $dasar }}/web-apps/apps/api/documents/api.js"></script>
<script id="config-dokumen" type="application/json">{!! json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script src="/js/hub-dokumen.js?v={{ filemtime(public_path('js/hub-dokumen.js')) }}" defer></script>
@endpush
@endif
@endsection

@extends('layouts.utama')

@section('judul', 'Kolaborasi')
@section('isi')
<header class="kepala">
  <div>
    <h1>Kolaborasi</h1>
    <p class="tenang">Pintu ke modul Ruang Kerja — sengaja tidak disalin, supaya tidak ada dua tempat untuk satu pekerjaan.</p>
  </div>
</header>
@include('agri._nav', ['mod' => 'ruang'])

<section class="blok">
  <div class="kartu-baris">
    <a class="kartu" href="{{ route('workspace.pesan') }}">
      <b>{!! ikon('pesan', 'ik kecil') !!} Messenger</b>
      <small class="pudar">{{ number_format($pesan, 0, ',', '.') }} pesan tersimpan di ruang obrolan tim.</small>
    </a>
    <a class="kartu" href="{{ route('workspace.dokumen') }}">
      <b>{!! ikon('dokumen', 'ik kecil') !!} Docs &amp; Sheets</b>
      <small class="pudar">Dokumen &amp; lembar sebar kolaboratif (ONLYOFFICE).</small>
    </a>
    <a class="kartu" href="{{ route('workspace.kalender') }}">
      <b>{!! ikon('kalender', 'ik kecil') !!} Kalender</b>
      <small class="pudar">Jadwal rapat dan tenggat tugas; mulai panggilan video dari acara.</small>
    </a>
    <a class="kartu" href="{{ route('workspace.tugas') }}">
      <b>{!! ikon('tugas', 'ik kecil') !!} Tasks</b>
      <small class="pudar">Pekerjaan lintas peran — beda dari Tugas Saya yang khusus milik Anda.</small>
    </a>
    <a class="kartu" href="{{ route('workspace.wiki') }}">
      <b>{!! ikon('wiki', 'ik kecil') !!} Wiki</b>
      <small class="pudar">Tutorial &amp; pengetahuan mengoperasikan Hub: fungsi, fitur, SOP.</small>
    </a>
    <a class="kartu" href="{{ route('workspace.approval') }}">
      <b>{!! ikon('setuju', 'ik kecil') !!} Approval</b>
      <small class="pudar">Pengajuan biaya operasional gerai ke admin/keuangan.</small>
    </a>
    <a class="kartu" href="{{ route('workspace.catatan-harian') }}">
      <b>{!! ikon('catatan-harian', 'ik kecil') !!} Catatan Harian</b>
      <small class="pudar">Catatan kerja harian; admin pusat &amp; PIC memantau.</small>
    </a>
  </div>
  <p class="bantuan">
    Beda dengan <a href="{{ route('agri.tugas') }}">Tugas Saya</a>: Tugas Saya hanya terlihat oleh
    Anda, sedangkan Tasks di Ruang Kerja dibagi ke tim dan terhubung ke obrolan &amp; dokumen.
  </p>
</section>

<section class="blok">
  <h2>Aktivitas terakhir</h2>
  <div class="kartu-baris">
    <div class="kartu">
      <b>Pesan Terbaru</b>
      <ul class="daftar-ringkas">
        @forelse ($terakhir as $p)
          <li><b>{{ $p->nama !== '' ? $p->nama : $p->oleh }}</b> — {{ \Illuminate\Support\Str::limit($p->isi, 70) }}</li>
        @empty
          <li class="pudar">Belum ada pesan.</li>
        @endforelse
      </ul>
      <a class="tombol samar mini" href="{{ route('workspace.pesan') }}">Buka Messenger</a>
    </div>
    <div class="kartu">
      <b>Dokumen Terbaru</b>
      <ul class="daftar-ringkas">
        @forelse ($dokumen as $d)
          <li>{{ $d->judul }} <small class="pudar">{{ strtoupper((string) $d->jenis) }}</small></li>
        @empty
          <li class="pudar">Belum ada dokumen.</li>
        @endforelse
      </ul>
      <a class="tombol samar mini" href="{{ route('workspace.dokumen') }}">Buka Docs</a>
    </div>
    <div class="kartu">
      <b>Rapat Mendatang</b>
      <ul class="daftar-ringkas">
        @forelse ($rapat as $r)
          <li>{{ $r->judul ?? $r->nama ?? 'Rapat' }} <small class="pudar">{{ tanggal_id($r->mulai ?? null, 'd M Y') }}</small></li>
        @empty
          <li class="pudar">Belum ada rapat terjadwal.</li>
        @endforelse
      </ul>
      <a class="tombol samar mini" href="{{ route('rapat') }}">Buka Konferensi</a>
    </div>
  </div>
</section>
@endsection

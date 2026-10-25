@extends('layouts.utama')
@section('judul', 'Informasi (AgriConnect)')
@section('deskripsi', 'Notifikasi, pengumuman, pegawai baru, poin, ruang kerja, dan regulasi dari AgriConnect HRIS.')

@section('isi')
<div class="kepala">
  <h1>Informasi</h1>
  <p class="tenang">Kabar, poin, dan fasilitas pegawai — dibaca langsung dari AgriConnect.</p>
</div>

@include('agri.hris._nav', ['mod' => 'hris-info'])

@php
  // notificationTime bisa milidetik (angka) atau teks tanggal — dua-duanya
  // diterima supaya jam yang tampil tetap jam sungguhan.
  $waktu = function ($v) {
      if ($v === null || $v === '') return '—';
      if (is_numeric($v)) {
          return \Illuminate\Support\Carbon::createFromTimestampMs((int) $v, 'UTC')
              ->setTimezone('Asia/Jakarta')->isoFormat('D MMM YYYY HH:mm');
      }
      try { return \Illuminate\Support\Carbon::parse((string) $v, 'Asia/Jakarta')->isoFormat('D MMM YYYY HH:mm'); }
      catch (\Throwable) { return (string) $v; }
  };
  $belumDibaca = count(array_filter($notif, fn ($n) => ($n['read'] ?? true) === false));
  $baruUrut = $baru;
  usort($baruUrut, fn ($a, $b) => strcmp((string) ($b['hireDate'] ?? ''), (string) ($a['hireDate'] ?? '')));
@endphp

<div class="kisi-kartu">
  <div class="kartu angka"><span class="pudar">Notifikasi belum dibaca</span><strong>{{ $belumDibaca }}</strong>
    <p class="bantuan">dari {{ count($notif) }} terbaru</p></div>
  <div class="kartu angka"><span class="pudar">Poin AgriConnect</span><strong>{{ $poin['currentPoints'] ?? 0 }}</strong>
    <p class="bantuan">terkumpul {{ $poin['collectedPoints'] ?? 0 }} · level {{ $poin['currentLevel'] ?? '—' }}</p></div>
  <div class="kartu angka"><span class="pudar">Pegawai baru</span><strong>{{ count($baru) }}</strong>
    <p class="bantuan">tercatat di perusahaan</p></div>
  <div class="kartu angka"><span class="pudar">Pengumuman</span><strong>{{ count($feed) }}</strong>
    <p class="bantuan">{{ count($kategori) }} kategori Starfeed</p></div>
</div>

<div class="blok">
  <div class="blok-kepala">
    <h2>Notifikasi</h2>
    <span class="pudar">{{ min(12, count($notif)) }} dari {{ count($notif) }}</span>
  </div>
  @if ($notif === [])
    <p class="tenang">AgriConnect belum mengirim notifikasi apa pun ke akun ini.</p>
  @else
    <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Judul</th><th>Isi</th><th>Waktu</th><th>Status</th></tr></thead>
        <tbody>
          @foreach (array_slice($notif, 0, 12) as $n)
            <tr>
              <td data-l="Judul">{{ $n['notificationTitle'] ?? '—' }}</td>
              <td data-l="Isi">{{ \Illuminate\Support\Str::limit((string) ($n['notificationBody'] ?? '—'), 110) }}</td>
              <td data-l="Waktu">{{ $waktu($n['notificationTime'] ?? null) }}</td>
              <td data-l="Status">
                @if (($n['needAction'] ?? false) === true)<span class="lencana s-DIAJUKAN">perlu tindakan</span>
                @elseif (($n['read'] ?? true) === false)<span class="lencana s-BARU">belum dibaca</span>
                @else<span class="pudar">terbaca</span>@endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <p class="bantuan">Daftar lengkap ada di aplikasi AgriConnect; Hub menampilkan yang terbaru.</p>
  @endif
</div>

<div class="blok">
  <div class="blok-kepala"><h2>Pengumuman (Starfeed)</h2>
    <span class="pudar">{{ implode(' · ', array_slice(array_map(fn ($k) => (string) ($k['categoryName'] ?? ''), $kategori), 0, 5)) }}</span>
  </div>
  @if ($feed === [])
    <p class="tenang">Belum ada pengumuman yang terbit untuk akun ini — kategorinya sudah ada ({{ count($kategori) }}), isinya belum.</p>
  @else
    @include('agri.hris._daftar', ['judul' => 'Pengumuman', 'baris' => $feed,
      'kolom' => ['title' => 'Judul', 'categoryName' => 'Kategori', 'publishDate' => 'Terbit']])
  @endif
</div>

<div class="blok">
  <div class="blok-kepala"><h2>Pegawai baru</h2>
    <span class="pudar">{{ min(12, count($baru)) }} dari {{ count($baru) }}</span>
  </div>
  @if ($baru === [])
    <p class="tenang">Tidak ada pegawai baru yang terbaca.</p>
  @else
    <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Nama</th><th>Jabatan</th><th>Perusahaan</th><th>Masuk</th></tr></thead>
        <tbody>
          @foreach (array_slice($baruUrut, 0, 12) as $b)
            <tr>
              <td data-l="Nama">{{ $b['fullName'] ?? '—' }}</td>
              <td data-l="Jabatan">{{ $b['jobName'] ?? '—' }}</td>
              <td data-l="Perusahaan">{{ $b['companyName'] ?? '—' }}</td>
              <td data-l="Masuk">{{ $b['hireDate'] ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

@include('agri.hris._daftar', ['judul' => 'Riwayat poin', 'baris' => $poinRiwayat,
  'kolom' => ['point' => 'Poin', 'description' => 'Keterangan', 'date' => 'Tanggal']])
@include('agri.hris._daftar', ['judul' => 'Ruang kerja & pemesanan', 'baris' => $ruang, 'kolom' => []])
@include('agri.hris._daftar', ['judul' => 'Relawan & lelang', 'baris' => array_merge($event, $lelang), 'kolom' => []])
@include('agri.hris._daftar', ['judul' => 'Regulasi perusahaan', 'baris' => $regulasi,
  'kolom' => ['title' => 'Judul', 'fileName' => 'Berkas', 'createdDate' => 'Diunggah']])
@endsection

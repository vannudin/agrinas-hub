{{-- RINGKASAN SEMUA KOPERASI — hanya untuk peran non-manajer (pusat, PIC
     wilayah, HR). Dirender oleh `_kepala` DI ATAS tabel daftar koperasi, supaya
     angka agregat terbaca lebih dulu daripada daftarnya (permintaan Bos
     2026-10-25). Manajer mengurus satu koperasi, jadi angka agregat tidak
     dikirim ke dia (lihat KoperasiController::ringkasSemua). --}}
@php
  $rs = $ringkasSemua;
  $rp2 = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp

<div class="petak kop-petak">
  <div class="kop-stat">
    <span class="kop-stat-ikon">{!! ikon('koperasi', 'ik') !!}</span>
    <span class="kop-stat-teks">
      <small>Jumlah Koperasi</small>
      <b>{{ $rp2($rs['koperasi']) }}</b>
      <i>dalam lingkup Anda</i>
    </span>
  </div>
  <div class="kop-stat">
    <span class="kop-stat-ikon">{!! ikon('setuju', 'ik') !!}</span>
    <span class="kop-stat-teks">
      <small>Operasional</small>
      <b>{{ $rp2($rs['operasional']['SUDAH']) }}</b>
      <i>sudah berjalan</i>
    </span>
  </div>
  <div class="kop-stat">
    <span class="kop-stat-ikon">{!! ikon('ringkasan', 'ik') !!}</span>
    <span class="kop-stat-teks">
      <small>Sebagian Operasional</small>
      <b>{{ $rp2($rs['operasional']['SEBAGIAN']) }}</b>
      <i>berjalan sebagian</i>
    </span>
  </div>
  <div class="kop-stat">
    <span class="kop-stat-ikon">{!! ikon('keluar', 'ik') !!}</span>
    <span class="kop-stat-teks">
      <small>Belum Operasional</small>
      <b>{{ $rp2($rs['operasional']['BELUM']) }}</b>
      <i>belum berjalan / belum diisi</i>
    </span>
  </div>
  <div class="kop-stat">
    <span class="kop-stat-ikon">{!! ikon('stok-koperasi', 'ik') !!}</span>
    <span class="kop-stat-teks">
      <small>Nilai Aset Produk</small>
      <b class="uang">Rp {{ $rp2($rs['nilai_stok']) }}</b>
      <i>{{ $rp2($rs['barang']) }} jenis barang di stok semua koperasi</i>
    </span>
  </div>
  <div class="kop-stat">
    <span class="kop-stat-ikon">{!! ikon('distribusi', 'ik') !!}</span>
    <span class="kop-stat-teks">
      <small>Sarana Prasarana</small>
      <b class="teks">{{ $rp2($rs['sarana']['BAIK']) }} lengkap · {{ $rp2($rs['sarana']['RUSAK']) }} rusak</b>
      <i>dari {{ $rp2($rs['indikator']) }} butir sarana · {{ $rp2($rs['gerai']) }} gerai · {{ $rp2($rs['karyawan']) }} karyawan</i>
    </span>
  </div>
</div>

@extends('layouts.utama')
@section('judul', 'Absen Agriconnect')

@php
  // Keadaan absen hari ini. Sumbernya baris CICO AgriConnect (bukan tabel Hub),
  // jadi yang terbaca di sini persis yang tercatat di server HRIS.
  // Jam dari API datang sebagai ISO ("2026-10-15T08:01:45"). Yang dibaca orang
  // di tabel absensi adalah jamnya, bukan tanggal+jam penuh.
  $jamIso = function ($v) {
    $v = (string) $v;
    if ($v === '') return '—';
    try {
      return \Illuminate\Support\Carbon::parse($v, 'Asia/Jakarta')->format('H:i');
    } catch (\Throwable) {
      return $v;
    }
  };
  $jam = fn ($b) => $b === null ? null
    : \Illuminate\Support\Carbon::createFromTimestampMs((int) ($b['actualTimeMilliSecond'] ?? 0), 'UTC')
        ->setTimezone('Asia/Jakarta')->format('H:i');
  $jamMasuk = $jam($cico['masuk'] ?? null);
  $jamPulang = $jam($cico['pulang'] ?? null);
  $berikut = $jamMasuk === null ? 'CHECKIN' : ($jamPulang === null ? 'CHECKOUT' : null);
  $judulTombol = $berikut === 'CHECKIN' ? 'Absen Masuk' : 'Absen Pulang';
@endphp

@section('isi')
<div class="kepala">
  <div>
    <h1>Absen Agriconnect</h1>
    <p class="tenang">Kehadiran dari AgriConnect HRIS, {{ $awal }} s.d. {{ $akhir }} — plus Absen Masuk &amp; Absen Pulang langsung dari sini.</p>
  </div>
</div>

@include('agri.hris._nav', ['mod' => 'hris-absensi'])

<div class="petak">
  <div class="kartu angka"><small>Hari ini</small>
    <b class="{{ $jamMasuk === null ? 'panjang' : '' }}">{{ $jamMasuk === null ? 'Belum absen' : $jamMasuk }}</b>
    <p class="bantuan">
      @if ($jamMasuk === null) Belum ada Absen Masuk AgriConnect hari ini.
      @elseif ($jamPulang === null) Masuk {{ $jamMasuk }} · belum Absen Pulang
      @else Masuk {{ $jamMasuk }} · Pulang {{ $jamPulang }}
      @endif
    </p>
  </div>
  <div class="kartu angka"><small>Hadir periode</small><b>{{ $ringkas['hadir'] }}</b>
    <p class="bantuan">dari {{ $ringkas['total'] }} baris</p></div>
  <div class="kartu angka"><small>Terlambat</small><b>{{ $ringkas['terlambat'] }}</b>
    <p class="bantuan">{{ $ringkas['alpha'] }} alpha · {{ $ringkas['libur'] }} libur</p></div>
  <div class="kartu angka"><small>Total jam kerja</small><b>{{ $ringkas['jam'] }} jam</b>
    <p class="bantuan">{{ $ringkas['lembur'] }} hari lembur</p></div>
</div>

{{-- Absen AgriConnect. SATU formulir untuk dua aksi; yang membedakan hanya
     `tipe` dan label tombolnya, karena foto + keterangan + titik sama.
     Foto memakai <input type="file" capture="user"> — di ponsel langsung
     membuka kamera depan, di desktop memilih berkas. Tidak ada getUserMedia di
     sini: tombol absen harus tetap bekerja di peramban yang memblokir kamera. --}}
<section class="blok agri-absen" id="absen">
  <div class="agri-absen-kepala">
    <h2>Absen AgriConnect</h2>
    {{-- Dua keadaan ditampilkan bersamaan: Absen Masuk dan Absen Pulang adalah
         dua hal yang dicari orang di halaman ini, dan menyembunyikan yang sudah
         selesai membuat status hari ini harus ditebak. --}}
    <div class="lencana-baris">
      <span class="lencana {{ $jamMasuk === null ? 's-BARU' : 's-SELESAI' }}">Masuk · {{ $jamMasuk ?? 'belum' }}</span>
      <span class="lencana {{ $jamPulang === null ? 's-BARU' : 's-SELESAI' }}">Pulang · {{ $jamPulang ?? 'belum' }}</span>
    </div>
  </div>

  @if ($berikut === null)
    <p class="tenang">Absen masuk <b>{{ $jamMasuk }}</b> dan absen pulang <b>{{ $jamPulang }}</b> hari ini sudah tercatat di AgriConnect. Besok bisa absen lagi dari sini.</p>
  @else
    <form method="post" action="{{ route('agri.hris.absensi.simpan') }}" enctype="multipart/form-data" class="agri-absen-form">
      @csrf
      <input type="hidden" name="tipe" value="{{ $berikut }}">

      <label class="agri-berkas">
        <span class="agri-berkas-label">Foto {{ strtolower($judulTombol) }}</span>
        <input type="file" name="foto" accept="image/jpeg,image/png" capture="user" required>
        <span class="bantuan">JPG/PNG, maks 4 MB — foto besar otomatis diperkecil sebelum dikirim. Di ponsel tombol ini membuka kamera depan.</span>
        @error('foto')<span class="pesan rusak">{{ $message }}</span>@enderror
      </label>

      <label class="agri-ket">
        <span>Keterangan</span>
        <input type="text" name="ket" value="{{ old('ket') }}"
               maxlength="190" required placeholder="Kegiatan hari ini…">
        @error('ket')<span class="pesan rusak">{{ $message }}</span>@enderror
      </label>

      <input type="hidden" name="lat" value="">
      <input type="hidden" name="lng" value="">

      <div class="agri-absen-kaki">
        <button class="tombol" type="submit">{{ $judulTombol }}</button>
        <p class="bantuan" id="agri-geo-status">
          Titik: <b data-titik>{{ $titik[0] }}, {{ $titik[1] }}</b> · meminta GPS…
        </p>
      </div>
      <p class="bantuan" id="agri-kirim-status"></p>
    </form>
  @endif
</section>

{{-- Report CheckInOut: kartu SENDIRI, bukan tombol di kepala kartu Absen — ini
     jalur lain (absen susulan yang menunggu persetujuan SDM), bukan lanjutan
     absen hari ini. Tombolnya kuning supaya terbaca sebagai aksi. --}}
<section class="blok" id="lapor">
  <div class="agri-absen-kepala">
    <h2>Report Check In / Check Out</h2>
    <span class="pudar">Absen susulan · menunggu persetujuan SDM</span>
  </div>
  <p class="tenang">Untuk hari yang tidak bisa diabsen dari aplikasi AgriConnect — belum ada akses atau belum bisa login. Laporan dikirim ke server AgriConnect, bukan dicatat di Hub.</p>
  <p class="tombol-baris">
    <button type="button" class="tombol kuning" data-buka="modal-lapor">Report Check In/Check Out</button>
  </p>
</section>

{{-- Formulirnya pop-up (pola `data-buka`/`data-tutup` yang sudah dipakai
     seluruh Hub — hub.js).
     Gambar TIDAK disimpan Hub: berkas yang dipilih diteruskan apa adanya sebagai
     multipart `file` ke server AgriConnect (lihat Agriconnect::lapor()). --}}
<dialog class="modal" id="modal-lapor" aria-labelledby="modal-lapor-judul">
  <form method="post" action="{{ route('agri.hris.absensi.lapor') }}" enctype="multipart/form-data">
    @csrf
    <header class="modal-kepala">
      <h2 id="modal-lapor-judul">Report Check In / Check Out</h2>
      <button type="button" class="tombol samar mini ikon-tutup" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="tenang">Laporan absen susulan untuk hari yang tidak bisa diabsen dari aplikasi. Setelah dikirim, laporan masuk ke AgriConnect dan menunggu persetujuan SDM.</p>
      <div class="grid-form">
        <label for="lapor-jenis">Jenis laporan</label>
        <select id="lapor-jenis" name="jenis" required>
          <option value="CHECKIN" @selected(old('jenis', 'CHECKIN') === 'CHECKIN')>Check In Report — absen masuk</option>
          <option value="CHECKOUT" @selected(old('jenis') === 'CHECKOUT')>Check Out Report — absen pulang</option>
        </select>

        <label for="lapor-tgl">Tanggal absen</label>
        <input type="datetime-local" id="lapor-tgl" name="tgl" required
               value="{{ old('tgl', now('Asia/Jakarta')->format('Y-m-d\TH:i')) }}"
               max="{{ now('Asia/Jakarta')->format('Y-m-d\TH:i') }}">
        @error('tgl')<span class="pesan rusak lebar-penuh">{{ $message }}</span>@enderror
        @error('jenis')<span class="pesan rusak lebar-penuh">{{ $message }}</span>@enderror

        <label for="lapor-tujuan">Tujuan</label>
        <select id="lapor-tujuan" name="tujuan" required>
          @foreach ($tujuan as $kode => $label)
            <option value="{{ $kode }}" @selected(old('tujuan', 'DR') === $kode)>{{ $label }}</option>
          @endforeach
        </select>
        @error('tujuan')<span class="pesan rusak lebar-penuh">{{ $message }}</span>@enderror

        <label for="lapor-alasan" class="lebar-penuh">Alasan / keterangan</label>
        <textarea id="lapor-alasan" name="alasan" rows="3" maxlength="190" required
                  class="lebar-penuh" placeholder="Belum Ada Akses">{{ old('alasan') }}</textarea>
        @error('alasan')<span class="pesan rusak lebar-penuh">{{ $message }}</span>@enderror
        <p class="bantuan lebar-penuh">Contoh: <b>Belum Ada Akses</b> · <b>Belum Bisa Login Di Agriconnect</b>. Tulis apa adanya — ini yang dibaca penyetuju di AgriConnect.</p>

        <label for="lapor-lampiran" class="lebar-penuh">Lampiran gambar (maks 5)</label>
        <input type="file" id="lapor-lampiran" name="lampiran[]" class="lebar-penuh"
               accept="image/jpeg,image/png" multiple>
        @error('lampiran')<span class="pesan rusak lebar-penuh">{{ $message }}</span>@enderror
        @error('lampiran.*')<span class="pesan rusak lebar-penuh">{{ $message }}</span>@enderror
        <p class="bantuan lebar-penuh">JPG/PNG, maks 4 MB per gambar. Gambar langsung dikirim ke server AgriConnect — Hub tidak menyimpannya. Laporan tidak bisa diubah atau dibatalkan setelah terkirim.</p>
      </div>
    </div>
    <footer class="modal-kaki">
      <button type="button" class="tombol samar" data-tutup>Batal</button>
      <button type="submit" class="tombol utama">Kirim laporan</button>
    </footer>
  </form>
</dialog>

@if ($errors->any())
<script>document.addEventListener('DOMContentLoaded', function () {
  var d = document.getElementById('modal-lapor');
  if (d && d.showModal && !d.open) d.showModal();
});</script>
@endif

@if ($berikut !== null)
<script>
// Titik absen = GPS perangkat. Kalau izin ditolak / tak tersedia, kolom lat/lng
// dibiarkan kosong dan server memakai titik absen sah terakhir (Agriconnect::titik).
(function () {
  var form = document.querySelector('.agri-absen-form');
  if (!form) return;
  var lat = form.querySelector('[name="lat"]'),
      lng = form.querySelector('[name="lng"]'),
      kotak = document.getElementById('agri-geo-status');

  function tulis(html) { kotak.innerHTML = html; }

  if (!navigator.geolocation) {
    tulis('Tanpa GPS di peramban ini. Titik dipakai: <b>{{ $titik[0] }}, {{ $titik[1] }}</b>.');

    return;
  }

  navigator.geolocation.getCurrentPosition(function (p) {
    lat.value = p.coords.latitude.toFixed(6);
    lng.value = p.coords.longitude.toFixed(6);
    tulis('Titik GPS: <b data-titik>' + lat.value + ', ' + lng.value + '</b> (±'
      + Math.round(p.coords.accuracy) + ' m)');
  }, function (e) {
    tulis('GPS tidak aktif — izinkan lokasi lalu muat ulang. Titik dipakai: <b>{{ $titik[0] }}, {{ $titik[1] }}</b>.');
  }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
})();

// Foto kamera ponsel bisa 3–4 MB. Di jam sibuk (07.00–08.00) mengunggah segitu
// bisa 30–60 detik, padahal AgriConnect hanya butuh foto wajah. Perkecil dulu ke
// sisi terpanjang 1280 px / JPEG 0,72 — biasanya jadi ±200 KB. Kalau peramban
// tidak mendukung (atau hasilnya tidak lebih kecil), berkas asli dikirim apa
// adanya: absen tidak boleh gagal karena soal penghematan.
(function () {
  var form = document.querySelector('.agri-absen-form');
  if (!form) return;
  var berkas = form.querySelector('[name="foto"]');
  var tombol = form.querySelector('button[type="submit"]');
  var status = document.getElementById('agri-kirim-status');
  var lanjut = false;

  function kirim() {
    lanjut = true;
    if (form.requestSubmit) form.requestSubmit(); else form.submit();
  }

  form.addEventListener('submit', function (ev) {
    var f = berkas && berkas.files && berkas.files[0];
    if (lanjut || !f || f.type.indexOf('image/') !== 0 || f.size <= 400 * 1024) return;
    ev.preventDefault();

    if (tombol) tombol.textContent = 'Memperkecil foto…';
    if (status) status.textContent = 'Foto diperkecil dulu supaya cepat terkirim…';

    var gambar = new Image();
    var url = URL.createObjectURL(f);
    gambar.onload = function () {
      var skala = Math.min(1, 1280 / Math.max(gambar.width, gambar.height));
      var kanvas = document.createElement('canvas');
      kanvas.width = Math.round(gambar.width * skala);
      kanvas.height = Math.round(gambar.height * skala);
      kanvas.getContext('2d').drawImage(gambar, 0, 0, kanvas.width, kanvas.height);
      URL.revokeObjectURL(url);

      kanvas.toBlob(function (b) {
        try {
          if (b && b.size < f.size && window.DataTransfer) {
            var dt = new DataTransfer();
            dt.items.add(new File([b], 'absen.jpg', { type: 'image/jpeg' }));
            berkas.files = dt.files;
          }
        } catch (e) { /* biarkan berkas asli */ }
        kirim();
      }, 'image/jpeg', 0.72);
    };
    gambar.onerror = function () { URL.revokeObjectURL(url); kirim(); };
    gambar.src = url;
  });
})();
</script>
@endif

<div class="blok">
  <div class="blok-kepala">
    <h2>Rincian harian</h2>
    <span class="pudar">{{ $daftar['jumlah'] }} baris</span>
  </div>

  @include('agri._saring', [
    'q' => $daftar['q'], 'nilai' => $daftar['nilai'], 'namaFilter' => 'keadaan',
    'pilihan' => $keadaan, 'placeholder' => 'Cari tanggal, jam, atau keterangan…',
  ])

  @if ($baris === [])
    <p class="kosong">
      @if ($daftar['q'] !== '' || $daftar['nilai'] !== '')
        Tidak ada baris yang cocok dengan saringan ini.
      @else
        AgriConnect belum mengembalikan baris kehadiran untuk periode ini.
      @endif
    </p>
  @else
    <div class="tabel-bungkus">
      <table>
        <thead>
          <tr>
            <th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Jadwal</th>
            <th>Jam kerja</th><th>Lembur</th><th>Keadaan</th><th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($baris as $b)
            @php
              $tanda = [];
              if (($b['isDayOff'] ?? false) === true) $tanda[] = ['Libur', 's-SELESAI'];
              if (($b['isAlpha'] ?? false) === true) $tanda[] = ['Alpha', 's-DITOLAK'];
              if (($b['isLateIn'] ?? false) === true) $tanda[] = ['Terlambat', 's-DIAJUKAN'];
              if (($b['isEarlyOut'] ?? false) === true) $tanda[] = ['Pulang awal', 's-DIAJUKAN'];
              if (filled($b['absenceCategory'] ?? null)) $tanda[] = [$b['absenceCategory'], 's-DIAJUKAN'];
              if ($tanda === []) $tanda[] = ['Normal', 's-SELESAI'];
              $lembur = (string) ($b['workingHoursOvtReal'] ?? '');
              $lembur = ($lembur !== '' && $lembur !== '00:00:00') ? $lembur
                : ((string) ($b['workingHoursOvtPlan'] ?? '') !== '' ? $b['workingHoursOvtPlan'].' (rencana)' : '—');
              $tgl = (string) ($b['startDate'] ?? '');
            @endphp
            <tr>
              <td data-l="Tanggal"><b>{{ $tgl !== '' ? \Illuminate\Support\Carbon::parse($tgl, 'Asia/Jakarta')->isoFormat('ddd, D MMM') : '—' }}</b></td>
              <td data-l="Masuk">{{ $jamIso($b['actualInTime'] ?? '') }}</td>
              <td data-l="Pulang">{{ $jamIso($b['actualOutTime'] ?? '') }}</td>
              <td data-l="Jadwal">{{ ($b['startTime'] ?? '—').'–'.($b['endTime'] ?? '—') }}</td>
              <td data-l="Jam kerja">{{ $b['workingHours'] ?? '—' }}</td>
              <td data-l="Lembur">{{ $lembur }}</td>
              <td data-l="Keadaan">
                @foreach ($tanda as [$teks, $kelas])<span class="lencana {{ $kelas }}">{{ $teks }}</span> @endforeach
              </td>
              <td data-l="Keterangan">{{ $b['remark'] ?? ($b['holiday'] ?? '—') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @include('partials.halaman', ['halaman' => $daftar['halaman'], 'ikon' => 'app'])
  @endif
</div>
@endsection

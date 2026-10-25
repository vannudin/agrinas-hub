@extends('layouts.utama')
@section('judul', 'Pemantauan AgriConnect')
@section('isi')
{{--
  Halaman AKUN AgriConnect untuk peran pemantau (admin, HR, PIC, keuangan).
  Mereka tidak punya akun pegawai, jadi yang ditampilkan bukan sesi pribadi
  melainkan daftar akun AgriConnect yang ditautkan para manajer.

  Yang bisa dipantau dari sini memang hanya keadaan SAMBUNGAN + jejak Hub
  (presensi, tugas): AgriConnect menerbitkan satu token per akun dan login baru
  mematikan token lama, jadi data HRIS orang lain tidak bisa ditarik dengan akun
  pemantau. Itu dinyatakan terbuka di halaman, bukan disembunyikan.
--}}
<div class="kepala">
  <div>
    <h1>Pemantauan AgriConnect</h1>
    <p class="tenang">
      Akun AgriConnect seluruh manajer: siapa sudah menautkan akunnya, dan jejak kepegawaiannya di Hub.
    </p>
  </div>
</div>

@include('agri.hris._nav', ['mod' => 'hris-akun'])

<section class="blok">
  <div class="kepala-bagian">
    <h2>Akun tertaut <small class="pudar">{{ number_format($jumlah, 0, ',', '.') }} akun</small></h2>
  </div>

  @include('agri._saring', [
    'aksi' => route('agri.hris.akun'),
    'q' => $q,
    'nilai' => $peran,
    'namaFilter' => 'peran',
    'pilihan' => ['semua' => 'Semua peran'],
    'placeholder' => 'nama pengguna atau akun AgriConnect…',
    'petunjuk' => 'Bawaan: hanya manajer. Pilih “Semua peran” untuk melihat akun pengelola yang juga tertaut.',
  ])

  <div class="tabel-bungkus">
    <table>
      <thead><tr><th>Pengguna</th><th>Peran</th><th>Presensi Hub</th><th>Sambungan AgriConnect</th></tr></thead>
      <tbody>
      @forelse ($baris as $b)
        <tr>
          <td data-l="Pengguna"><span class="judul-sel">{{ $b->nama }}</span>
            <small class="pudar">{{ $b->agri_username }}</small></td>
          <td data-l="Peran"><span class="lencana">{{ \App\Models\Domain::LABEL_PERAN[$b->peran] ?? $b->peran }}</span></td>
          <td data-l="Presensi Hub">{{ (int) ($hadir[$b->id] ?? 0) }} hari</td>
          <td data-l="Sambungan AgriConnect">
            @if ($tersambung[$b->id] ?? false)
              <span class="lencana s-DISETUJUI">Tersambung</span>
              <small class="pudar">{{ tanggal_id($b->agri_tersambung_pada, 'd M Y H:i') }}</small>
            @else
              <span class="lencana">Belum tersambung</span>
              <small class="pudar">manajer perlu masuk sekali lewat aplikasi AgriConnect</small>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="kosong">Belum ada manajer yang menautkan akun AgriConnect.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])

  <p class="bantuan">
    Data HRIS (profil, absensi, cuti, performa) hanya bisa dibaca pemilik akun: AgriConnect memberi
    <b>satu token per akun</b> dan login baru mematikan token lama. Karena itu halaman ini memantau
    <b>keadaan sambungan dan jejak Hub</b>; rincian HRIS tetap dibuka masing-masing manajer dari akunnya.
    Manajer yang belum tertaut dapat diminta masuk ke Hub → AgriConnect → Akun.
  </p>
</section>
@endsection

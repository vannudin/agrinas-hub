@extends('layouts.utama')

@section('judul', 'Perjalanan Dinas')
@section('isi')
@php
  $labelJenis = \App\Models\AgriPengajuan::JENIS;
  $labelStatus = \App\Models\AgriPengajuan::STATUS;
@endphp
<header class="kepala">
  <div>
    <h1>Perjalanan Dinas &amp; Pengajuan</h1>
    <p class="tenang">Ajukan perjalanan dinas, klaim imbalan, atau administrasi umum — lalu putuskan bila Anda berwenang.</p>
  </div>
  <div class="tombol-baris">
    <button class="tombol utama" type="button" data-buka="ajuan-baru">Pengajuan Baru</button>
  </div>
</header>
@include('agri._nav', ['mod' => 'perjalanan'])

<section class="blok" id="pj" data-isi="{{ route('agri.perjalanan') }}" data-params="q,jenis,halaman,halaman_antre">
  <h2>Pengajuan Saya
    <small class="pudar">{{ number_format($halamanSaya->jumlah, 0, ',', '.') }} pengajuan</small>
  </h2>

  <p class="setuju-ringkas">
    <span class="lencana s-DIAJUKAN">Menunggu {{ $ringkas['DIAJUKAN'] ?? 0 }}</span>
    <span class="lencana s-SELESAI">Disetujui {{ $ringkas['SETUJU'] ?? 0 }}</span>
    <span class="lencana s-REVISI">Ditolak {{ $ringkas['TOLAK'] ?? 0 }}</span>
  </p>

  <div class="kartu-baris kartu-status">
    @foreach ($labelJenis as $k => $nama)
      <a class="kartu angka {{ $jenis === $k ? 'aksi-hijau' : '' }}"
         href="{{ route('agri.perjalanan', array_filter(['jenis' => $k, 'q' => $cari])) }}">
        <small>{{ $nama }}</small><b>{{ $ringkasJenis[$k] ?? 0 }}</b>
      </a>
    @endforeach
    <a class="kartu angka {{ $jenis === '' ? 'aksi-hijau' : '' }}"
       href="{{ route('agri.perjalanan', array_filter(['q' => $cari])) }}">
      <small>Semua jenis</small><b>{{ number_format($halamanSaya->jumlah, 0, ',', '.') }}</b>
    </a>
  </div>

  <form class="saring" method="get" action="{{ route('agri.perjalanan') }}">
    <label class="cari">
      <span class="sr">Cari pengajuan</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari judul pengajuan…">
    </label>
    @if ($jenis !== '')<input type="hidden" name="jenis" value="{{ $jenis }}">@endif
    <a class="tombol samar bersih-saring" href="{{ route('agri.perjalanan') }}"
       @if ($cari === '' && $jenis === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Pengajuan</th><th>Jenis</th><th>Nilai</th><th>Tanggal</th><th>Status</th><th>Catatan Keputusan</th></tr></thead>
    <tbody>
      @forelse ($saya as $a)
        <tr>
          <td data-l="Pengajuan"><span class="judul-sel">{{ $a->judul }}</span>
            @if (trim((string) $a->keterangan) !== '')
              <small class="pudar">{{ \Illuminate\Support\Str::limit($a->keterangan, 110) }}</small>
            @endif
          </td>
          <td data-l="Jenis"><span class="lencana">{{ $a->label_jenis() }}</span></td>
          <td data-l="Nilai">{{ $a->nilai_teks() }}</td>
          <td data-l="Tanggal">{{ tanggal_id($a->tanggal, 'd M Y') }}
            @if ($a->tanggal_akhir !== '')<small class="pudar">s.d. {{ tanggal_id($a->tanggal_akhir, 'd M Y') }}</small>@endif</td>
          <td data-l="Status"><span class="lencana s-{{ $a->lencana() }}">{{ $a->label_status() }}</span></td>
          <td data-l="Catatan Keputusan">{{ trim((string) $a->catatan) !== '' ? \Illuminate\Support\Str::limit($a->catatan, 120) : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">
          {{ $cari !== '' || $jenis !== '' ? 'Tidak ada pengajuan yang cocok dengan saringan ini.' : 'Belum ada pengajuan. Mulai lewat tombol Pengajuan Baru.' }}
        </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halamanSaya, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>

@if ($bolehPutus)
<section class="blok">
  <h2>Menunggu Keputusan Anda
    <small class="pudar">{{ number_format($halamanAntre->jumlah, 0, ',', '.') }} pengajuan</small>
  </h2>
  <p class="bantuan">
    @if (auth()->user()->agrinas()) Seluruh pengajuan dari semua koperasi.
    @else Pengajuan dari koperasi di wilayah Anda.@endif
    Penolakan wajib disertai alasan supaya pemohon tahu apa yang harus diperbaiki.
  </p>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Pengajuan</th><th>Pemohon</th><th>Nilai</th><th>Tanggal</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
      @forelse ($antre as $a)
        <tr>
          <td data-l="Pengajuan"><span class="judul-sel">{{ $a->judul }}</span>
            <small class="pudar">{{ $a->label_jenis() }} · {{ $a->koperasi?->nama ?? 'Umum' }}</small></td>
          <td data-l="Pemohon">{{ $a->pemohon?->nama ?? '—' }}</td>
          <td data-l="Nilai">{{ $a->nilai_teks() }}</td>
          <td data-l="Tanggal">{{ tanggal_id($a->tanggal, 'd M Y') }}</td>
          <td class="kolom-aksi">
            <button class="tombol utama mini" type="button"
              data-buka="ajuan-putus"
              data-aksi="{{ route('agri.perjalanan.putus', 0) }}"
              data-awal="{{ json_encode(['id' => $a->id, 'judul' => $a->judul, 'ringkas' => $a->label_jenis().' · '.$a->nilai_teks()]) }}"
              data-judul-dialog="Putuskan: {{ $a->judul }}">Putuskan</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">Tidak ada pengajuan yang menunggu keputusan.</td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halamanAntre, 'ikon' => 'app', 'kunci' => 'halaman_antre'])
</section>
@endif

<dialog class="modal" id="ajuan-baru" aria-labelledby="ajuan-baru-judul">
  <form method="post" action="{{ route('agri.perjalanan.simpan') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="ajuan-baru-judul">Pengajuan Baru</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Jenis
        <select name="jenis" required>
          @foreach ($labelJenis as $k => $nama)<option value="{{ $k }}">{{ $nama }}</option>@endforeach
        </select></label>
      <label>Judul <input name="judul" maxlength="160" required placeholder="Contoh: Kunjungan koperasi Megulung"></label>
      <label>Keterangan <span class="bantuan">(opsional)</span>
        <textarea name="keterangan" rows="3" maxlength="1000" placeholder="Maksud, tujuan, rincian biaya"></textarea></label>
      <label>Nilai <span class="bantuan">(rupiah untuk perjalanan/biasa, poin untuk klaim imbalan)</span>
        <input type="number" name="nilai" min="0" max="999999999" step="1" value="0" required></label>
      <label>Tanggal berangkat <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required></label>
      <label>Tanggal kembali <span class="bantuan">(wajib untuk perjalanan dinas)</span>
        <input type="date" name="tanggal_akhir"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Kirim Pengajuan</button>
    </footer>
  </form>
</dialog>

@if ($bolehPutus)
<dialog class="modal" id="ajuan-putus" aria-labelledby="ajuan-putus-judul">
  <form method="post" id="ajuan-putus-form" action="{{ route('agri.perjalanan.putus', 0) }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="ajuan-putus-judul">Putuskan Pengajuan</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan" data-teks="ringkas" hidden></p>
      <label>Keputusan
        <select name="status" required>
          <option value="SETUJU">Disetujui</option>
          <option value="TOLAK">Ditolak</option>
        </select></label>
      <label>Catatan / alasan <span class="bantuan">(wajib bila ditolak)</span>
        <textarea name="catatan" rows="3" maxlength="300" placeholder="Alasan penolakan atau catatan persetujuan"></textarea></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan Keputusan</button>
    </footer>
  </form>
</dialog>
@endif

<script>
/* Dialog "Putuskan": satu <dialog> melayani seluruh baris antrean. Alamat aksi
   diambil dari data-aksi (templat berpenanda `0`) supaya view tidak perlu tahu
   bentuk URL-nya, dan satu tempat saja yang berubah bila rutenya berubah. */
(function () {
  var d = document.getElementById('ajuan-putus');
  if (!d) return;
  var form = d.querySelector('form');

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-buka="ajuan-putus"]') : null;
    if (!b) return;
    var a = b.getAttribute('data-awal');
    if (!a) return;
    try {
      var v = JSON.parse(a);
      var templat = b.getAttribute('data-aksi');
      if (templat && v.id) form.action = templat.replace(/0(?=\/putus$)/, v.id);
    } catch (e) { /* data-awal rusak: dialog terbuka tanpa aksi yang tepat */ }
  });
})();
</script>
<script src="/js/hub-agri.js?v={{ filemtime(public_path('js/hub-agri.js')) }}" defer></script>
@endsection

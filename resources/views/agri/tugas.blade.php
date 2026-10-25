@extends('layouts.utama')

@section('judul', 'Tugas Saya')
@section('isi')
@php
  $labelStatus = \App\Models\AgriTugas::STATUS;
  $labelJenis = \App\Models\AgriTugas::JENIS;
  $labelPrioritas = \App\Models\AgriTugas::PRIORITAS;
@endphp
<header class="kepala">
  <div>
    <h1>Tugas Saya</h1>
    <p class="tenang">Tugas, lembur, dan pengingat pribadi — di aplikasi aslinya tiga menu, di sini satu daftar.</p>
  </div>
  <div class="tombol-baris">
    <button class="tombol utama" type="button" data-buka="tugas-baru">Tugas Baru</button>
  </div>
</header>
@include('agri._nav', ['mod' => 'tugas'])

<section class="blok" id="tg" data-isi="{{ route('agri.tugas') }}" data-params="q,jenis,halaman">
  <div class="kartu-baris kartu-status">
    @foreach ($labelJenis as $k => $nama)
      <a class="kartu angka {{ $jenis === $k ? 'aksi-hijau' : '' }}"
         href="{{ route('agri.tugas', array_filter(['jenis' => $k, 'q' => $cari])) }}">
        <small>{{ $nama }}</small>
        <b>{{ $ringkasAktif[$k] ?? 0 }}</b>
      </a>
    @endforeach
    <a class="kartu angka {{ $jenis === '' ? 'aksi-hijau' : '' }}"
       href="{{ route('agri.tugas', array_filter(['q' => $cari])) }}">
      <small>Semua</small><b>{{ array_sum($ringkasAktif) }}</b>
    </a>
  </div>

  <form class="saring" method="get" action="{{ route('agri.tugas') }}">
    <label class="cari">
      <span class="sr">Cari tugas</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off" placeholder="Cari judul tugas…">
    </label>
    @if ($jenis !== '')<input type="hidden" name="jenis" value="{{ $jenis }}">@endif
    <a class="tombol samar bersih-saring" href="{{ route('agri.tugas') }}"
       @if ($cari === '' && $jenis === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Judul</th><th>Jenis</th><th>Prioritas</th><th>Jadwal</th><th>Status</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
      @forelse ($daftar as $t)
        <tr>
          <td data-l="Judul"><span class="judul-sel">{{ $t->judul }}</span>
            @if (trim((string) $t->keterangan) !== '')
              <small class="pudar">{{ \Illuminate\Support\Str::limit($t->keterangan, 110) }}</small>
            @endif
          </td>
          <td data-l="Jenis"><span class="lencana">{{ $t->label_jenis() }}</span></td>
          <td data-l="Prioritas">{{ $labelPrioritas[$t->prioritas] ?? $t->prioritas }}</td>
          <td data-l="Jadwal">{{ tanggal_id($t->tanggal, 'd M Y') }}@if ($t->jam !== '') <small class="pudar">{{ $t->jam }}</small>@endif</td>
          <td data-l="Status">
            <span class="lencana {{ $t->status === 'SELESAI' ? 's-SELESAI' : ($t->status === 'PROSES' ? 's-DIAJUKAN' : '') }}">
              {{ $labelStatus[$t->status] ?? $t->status }}
            </span>
          </td>
          <td class="kolom-aksi">
            <button class="tombol samar mini" type="button"
              data-buka="tugas-status"
              data-aksi="{{ route('agri.tugas.status', 0) }}"
              data-awal="{{ json_encode(['id' => $t->id, 'status' => $t->status]) }}"
              data-judul-dialog="{{ $t->judul }}">Status</button>
            <form method="post" action="{{ route('agri.tugas.hapus', $t->id) }}" class="sebaris"
                  data-konfirmasi="Hapus tugas &quot;{{ $t->judul }}&quot;?">
              @csrf @method('DELETE')
              <button class="tombol peringatan mini" type="submit">Hapus</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">
          {{ $cari !== '' || $jenis !== '' ? 'Tidak ada tugas yang cocok dengan saringan ini.' : 'Belum ada tugas. Tambahkan lewat tombol Tugas Baru.' }}
        </td></tr>
      @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>

<dialog class="modal" id="tugas-baru" aria-labelledby="tugas-baru-judul">
  <form method="post" action="{{ route('agri.tugas.simpan') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="tugas-baru-judul">Tugas Baru</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Judul <input name="judul" maxlength="160" required placeholder="Contoh: Susun laporan bulanan"></label>
      <label>Keterangan <span class="bantuan">(opsional)</span>
        <textarea name="keterangan" rows="3" maxlength="1000" placeholder="Rincian singkat"></textarea></label>
      <label>Jenis
        <select name="jenis" required>
          @foreach ($labelJenis as $k => $nama)<option value="{{ $k }}">{{ $nama }}</option>@endforeach
        </select></label>
      <label>Prioritas
        <select name="prioritas" required>
          @foreach ($labelPrioritas as $k => $nama)<option value="{{ $k }}" @selected($k === 'NORMAL')>{{ $nama }}</option>@endforeach
        </select></label>
      <label>Tanggal <input type="date" name="tanggal" value="{{ now()->toDateString() }}" required></label>
      <label>Jam <span class="bantuan">(opsional — untuk pengingat &amp; lembur)</span>
        <input type="time" name="jam"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan Tugas</button>
    </footer>
  </form>
</dialog>

<dialog class="modal" id="tugas-status" aria-labelledby="tugas-status-judul">
  <form method="post" id="tugas-status-form" action="{{ route('agri.tugas.status', 0) }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="tugas-status-judul">Ubah Status</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Status
        <select name="status" required>
          @foreach ($labelStatus as $k => $nama)<option value="{{ $k }}">{{ $nama }}</option>@endforeach
        </select></label>
      <p class="bantuan">Menandai tugas Selesai memberi
        {{ \App\Http\Controllers\AgriController::POIN_TUGAS }} poin, sekali saja per tugas.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan</button>
    </footer>
  </form>
</dialog>

<script>
/* Dialog "Ubah Status": SATU <dialog> melayani seluruh baris tabel, jadi tombol
   per baris hanya membawa id-nya. Alamat aksi diambil dari data-aksi (satu
   templat dengan penanda `0`), bukan disusun di JS — begitutambah rute, view
   ini tidak perlu ikut diubah. */
(function () {
  var d = document.getElementById('tugas-status');
  if (!d) return;
  var form = d.querySelector('form');

  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-buka="tugas-status"]') : null;
    if (!b) return;
    var a = b.getAttribute('data-awal');
    if (!a) return;
    try {
      var v = JSON.parse(a);
      var templat = b.getAttribute('data-aksi');
      if (templat && v.id) form.action = templat.replace(/0(?=\/status$)/, v.id);
      if (v.status) form.querySelector('[name="status"]').value = v.status;
    } catch (e) { /* data-awal rusak: dialog tetap terbuka, status tidak berubah */ }
  });
})();
</script>
<script src="/js/hub-agri.js?v={{ filemtime(public_path('js/hub-agri.js')) }}" defer></script>
@endsection

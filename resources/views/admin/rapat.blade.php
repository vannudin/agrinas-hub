@extends('layouts.utama')

@section('judul', 'Konferensi')
@section('isi')
<header class="kepala">
  <div><h1>Jadwal Konferensi</h1>
    <p class="tenang">Buat ruang Video Meeting (semua bicara) atau Video Webinar (pembicara &amp; penonton).</p></div>
</header>

{{-- Meja penjadwalan: satu formulir untuk MENJADWALKAN dan MENGUBAH. Tombol "Ubah"
     di tabel mengisi formulir ini (lihat @push('skrip') di bawah) — kalau tidak,
     jadwal yang salah waktu hanya bisa dihapus lalu dibuat ulang. --}}
<section class="blok">
  <h2 id="judul-form">Jadwalkan Rapat</h2>
  <form method="post" action="{{ route('admin.rapat.simpan') }}" class="grid-form" id="form-jadwal">
    @csrf
    {{-- `id` menentukan tambah atau ubah. Kosong = jadwalkan baru. --}}
    <input type="hidden" name="id" value="{{ old('id') }}" id="jadwal-id">
    <label class="lebar-penuh">Judul Rapat
      <input name="judul" id="jadwal-judul" required maxlength="160" value="{{ old('judul') }}" placeholder="Rapat Koordinasi Triwulan IV">
    </label>
    <label>Bentuk
      <select name="jenis" id="jadwal-jenis">
        {{-- Nilai rumpun 'meeting'/'webinar' diberi label tegas: ini yang menentukan
             apakah peserta bisa bicara dua arah atau hanya menonton. --}}
        <option value="meeting" @selected(old('jenis', 'meeting') === 'meeting')>Video Meeting — semua peserta bicara</option>
        <option value="webinar" @selected(old('jenis') === 'webinar')>Video Webinar — pembicara &amp; penonton</option>
      </select>
    </label>
    <label>Mulai
      <input name="mulai" id="jadwal-mulai" type="datetime-local" required value="{{ old('mulai') }}">
    </label>
    <label>Selesai (opsional)
      <input name="selesai" id="jadwal-selesai" type="datetime-local" value="{{ old('selesai') }}">
    </label>
    <label class="lebar-penuh">Catatan
      <input name="catatan" id="jadwal-catatan" maxlength="1000" value="{{ old('catatan') }}" placeholder="Agenda: laporan triwulan, kendala lapangan.">
    </label>
    <div class="lebar-penuh tombol-baris">
      <button class="tombol utama" type="submit" id="tombol-jadwal">Jadwalkan</button>
      <button class="tombol samar" type="button" id="batal-jadwal" hidden>Batalkan ubah</button>
    </div>
  </form>
  <p class="bantuan">
    Ruang memakai <b>Cloudflare Realtime</b> (WebRTC) — tanpa aplikasi tambahan dan tanpa layanan
    pihak ketiga lain. <b>Video Meeting</b>: semua peserta saling melihat &amp; mendengar.
    <b>Video Webinar</b>: hanya pembicara yang tampil; penonton menonton, menulis obrolan, dan
    bisa mengangkat tangan untuk minta diangkat jadi pembicara.
  </p>
</section>

<section class="blok">
  <h2>Jadwal Tersimpan
    <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }}</small>
  </h2>

  {{-- Pencarian + saringan: aturan semua tabel data di Hub, dan `data-otomatis`
       dipasang pada FORM (bukan tiap input) — itulah yang dibaca hub.js. --}}
  <form class="saring" method="get" action="{{ route('admin.rapat') }}" data-otomatis>
    <label class="cari">
      <span class="sr">Cari judul rapat</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" placeholder="Cari judul rapat…">
    </label>
    <label>
      <span class="sr">Saring bentuk</span>
      <select name="jenis">
        <option value="">Semua bentuk</option>
        <option value="meeting" @selected($jenis === 'meeting')>Video Meeting</option>
        <option value="webinar" @selected($jenis === 'webinar')>Video Webinar</option>
      </select>
    </label>
    <label>
      <span class="sr">Saring status</span>
      <select name="status">
        <option value="">Semua status</option>
        <option value="AKAN" @selected($status === 'AKAN')>Akan datang</option>
        <option value="KINI" @selected($status === 'KINI')>Berlangsung</option>
        <option value="SELESAI" @selected($status === 'SELESAI')>Selesai</option>
      </select>
    </label>
    <button class="tombol" type="submit">Terapkan</button>
    <a class="tombol samar bersih-saring" href="{{ route('admin.rapat') }}"
       @if ($cari === '' && $jenis === '' && $status === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="tabel-bungkus">
  <table>
    {{-- URUTAN KOLOM HARUS SAMA DENGAN `data-l` DI BAWAHNYA.
         Sebelumnya header kelima ini: Judul · Bentuk · Mulai · Status · (aksi),
         sedangkan selisinya: Judul · Bentuk · Jadwal · Ruang · aksi — jadi isi
         kolom "Status" adalah nama ruang, dan kolom "Mulai" berisi jadwal+status.
         Empat sel (bukan lima): yang sekeluarga digabung supaya di ponsel tetap
         terbaca sebagai satu baris per rapat (maks 4 sel ber-label). --}}
    <thead><tr>
      <th>Judul</th><th>Bentuk</th><th>Jadwal &amp; Status</th><th class="kolom-aksi"></th>
    </tr></thead>
    <tbody>
    @forelse ($daftar as $r)
      @php $st = $r->status(); @endphp
      <tr>
        <td data-l="Judul"><span class="judul-sel">{{ $r->judul }}</span>
          {{-- Ruang pindah ke sini: satu keluarga dengan judulnya, dan tidak lagi
               menempati kolom yang header-nya tidak ada. --}}
          <small class="pudar"><code>{{ $r->ruang }}</code></small></td>
        <td data-l="Bentuk"><span class="lencana">{{ $r->label_jenis() }}</span>
          @php $undangan = $r->daftarPembicara(); @endphp
          @if ($undangan)
            <small class="pudar">{{ count($undangan) }} pembicara diundang</small>
          @endif</td>
        {{-- "Kapan" dan "sudah lewat atau belum" satu cerita — digabung. --}}
        <td data-l="Jadwal">{{ $r->mulai_manusia() }}
          @if ($r->selesai) <small class="pudar">&rarr; {{ tanggal_id($r->selesai, 'd M H:i') }}</small>@endif
          @if ($st === 'KINI')<span class="lencana s-SELESAI">Berlangsung</span>
          @elseif ($st === 'SELESAI')<span class="lencana s-BARU">Selesai</span>
          @else<span class="lencana s-DIAJUKAN">Akan datang</span>@endif</td>
        <td class="kolom-aksi">
          {{-- Hanya terbuka dalam jendela waktu; di luar itu tombolnya terkunci
               dengan alasannya supaya tidak membawa orang ke ruang kosong. --}}
          @if ($r->bisaMasuk())
            <a class="tombol {{ $st === 'KINI' ? 'utama' : 'samar' }} mini"
               href="{{ route('rapat.ruang', $r->id) }}">{{ $st === 'KINI' ? 'Masuk Ruang' : 'Ruang' }}</a>
          @else
            <span class="tombol samar mini terkunci" aria-disabled="true"
                  title="{{ $r->alasanKunci() }}">{{ $st === 'AKAN' ? 'Belum dibuka' : 'Sudah selesai' }}</span>
          @endif
          <button class="tombol samar mini" type="button" data-ubah
            data-id="{{ $r->id }}" data-judul="{{ $r->judul }}" data-jenis="{{ $r->jenis }}"
            data-mulai="{{ str_replace(' ', 'T', substr($r->mulai, 0, 16)) }}"
            data-selesai="{{ $r->selesai ? str_replace(' ', 'T', substr($r->selesai, 0, 16)) : '' }}"
            data-catatan="{{ $r->catatan }}">Ubah</button>
          {{-- Undang pembicara: hanya berarti di webinar (meeting = semua bicara),
               tetapi tetap ditampilkan supaya admin bisa menyiapkan daftar lebih
               dulu lalu mengganti bentuk rapat tanpa mengulang pekerjaan. --}}
          <button class="tombol samar mini" type="button" data-undang
            data-aksi="{{ route('admin.rapat.undang', $r->id) }}"
            data-pilih="{{ implode(',', $undangan) }}"
            data-rapt="{{ $r->judul }}">Undang</button>
          <form method="post" action="{{ route('admin.rapat.hapus', $r->id) }}" class="sebaris"
                data-konfirmasi="Hapus jadwal rapat ini?">
            @csrf<button class="tombol peringatan mini" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="4" class="kosong">
        @if ($cari !== '' || $jenis !== '' || $status !== '')
          Tidak ada rapat yang cocok dengan saringan.
        @else
          Belum ada jadwal rapat.
        @endif
      </td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>

{{-- Undang pembicara: satu <dialog> untuk semua baris. Daftar akun aktif sudah
     ada di halaman (puluhan orang), jadi penyaringan nama dilakukan di peramban
     dan tidak perlu permintaan jaringan tambahan. --}}
<dialog class="modal" id="undang-modal" aria-labelledby="undang-judul">
  <form method="post" id="undang-form" action="{{ route('admin.rapat.undang', 0) }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="undang-judul">Undang Pembicara</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">Tutup</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan" id="undang-rapt"></p>
      <label class="cari">
        <span class="sr">Cari nama pembicara</span>
        <input type="search" id="undang-cari" placeholder="Cari nama atau email…" autocomplete="off">
      </label>
      <div class="undang-daftar" id="undang-daftar">
        @foreach ($pengguna as $p)
          <label class="undang-orang" data-cari="{{ mb_strtolower($p->nama.' '.$p->email) }}">
            <input type="checkbox" name="undang[]" value="{{ $p->id }}">
            <span>{{ $p->nama }}</span>
            <small class="pudar">{{ $p->email }}</small>
          </label>
        @endforeach
      </div>
      <p class="bantuan">Yang dicentang boleh bicara &amp; tampil sejak masuk ruang — tidak
        perlu diangkat lagi oleh pengelola. Di <b>Video Meeting</b> semua peserta memang
        sudah bisa bicara; daftar ini yang menentukan di <b>Video Webinar</b>.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Simpan Pembicara</button>
    </footer>
  </form>
</dialog>
@push('skrip')
<script>
/* Isi formulir untuk MENGUBAH jadwal. Tanpa ini, jadwal yang salah waktu hanya
   bisa dihapus lalu dibuat ulang — dan ruangnya ikut berganti, sehingga tautan
   yang sudah dibagikan ke peserta jadi salah. */
(function () {
  var f = document.getElementById('form-jadwal');
  if (!f) return;
  var el = function (id) { return document.getElementById(id); };
  var batal = el('batal-jadwal');

  function modeUbah(d) {
    el('jadwal-id').value = d.id;
    el('jadwal-judul').value = d.judul;
    el('jadwal-jenis').value = d.jenis;
    el('jadwal-mulai').value = d.mulai;
    el('jadwal-selesai').value = d.selesai;
    el('jadwal-catatan').value = d.catatan;
    el('tombol-jadwal').textContent = 'Simpan Perubahan';
    el('judul-form').textContent = 'Ubah Jadwal Rapat';
    batal.hidden = false;
    f.scrollIntoView({ behavior: 'smooth', block: 'start' });
    el('jadwal-judul').focus();
  }

  function modeTambah() {
    el('jadwal-id').value = '';
    batal.hidden = true;
    el('tombol-jadwal').textContent = 'Jadwalkan';
    el('judul-form').textContent = 'Jadwalkan Rapat';
  }

  document.addEventListener('click', function (ev) {
    var u = ev.target.closest ? ev.target.closest('[data-undang]') : null;
    if (u) {
      var d = document.getElementById('undang-modal');
      var f = document.getElementById('undang-form');
      // `action` berasal dari tombol (sudah memuat id rapat), jadi satu dialog
      // melayani semua baris tanpa perlu merakit URL di sini.
      f.action = u.dataset.aksi;
      document.getElementById('undang-rapt').textContent = 'Rapat: ' + u.dataset.rapt;
      var pilih = (u.dataset.pilih || '').split(',').filter(Boolean);
      document.querySelectorAll('#undang-daftar input[type=checkbox]').forEach(function (c) {
        c.checked = pilih.indexOf(c.value) >= 0;
      });
      document.getElementById('undang-cari').value = '';
      document.querySelectorAll('#undang-daftar .undang-orang').forEach(function (l) {
        l.hidden = false;
      });
      d.showModal();
      return;
    }

    var b = ev.target.closest ? ev.target.closest('[data-ubah]') : null;
    if (!b) return;
    modeUbah(b.dataset);
  });

  // Saringan nama di dalam dialog undangan: cocokkan nama ATAU email.
  document.getElementById('undang-cari')?.addEventListener('input', function () {
    var q = this.value.toLowerCase().trim();
    document.querySelectorAll('#undang-daftar .undang-orang').forEach(function (l) {
      l.hidden = q !== '' && (l.dataset.cari || '').indexOf(q) < 0;
    });
  });
  batal?.addEventListener('click', function () {
    f.reset();
    modeTambah();
  });
})();
</script>
@endpush
@endsection

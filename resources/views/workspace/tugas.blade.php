@extends('layouts.utama')
@section('judul', 'Tugas')
@section('isi')
@php
  $warnaStatus = ['BARU' => 'biru', 'DIKERJAKAN' => 'kuning', 'DIAJUKAN' => 'ungu',
                  'REVISI' => 'oranye', 'DITOLAK' => 'merah', 'SELESAI' => 'hijau'];
  $kartu = ['BARU', 'DIKERJAKAN', 'DIAJUKAN', 'REVISI', 'TERLAMBAT', 'SELESAI'];
@endphp

<header class="kepala">
  <div>
    <h1>Tugas</h1>
    <p class="tenang">
      @if ($boleh_menugaskan)
        Beri tugas ke PIC maupun manajer, pantau status, dan baca progres beserta lampirannya.
      @else
        Tugas untuk koperasi Anda — perbarui progres dan lampirkan bukti pekerjaan.
      @endif
    </p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('workspace.jobdesc') }}">
      <svg class="ik" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/><path d="M9 12h6M9 16h6"/></svg>
      Jobdesc Manajer
    </a>
    <button class="tombol utama" type="button" data-buka="tg-baru">
      <svg class="ik" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Tugas Baru
    </button>
  </div>
</header>
@include('workspace._nav', ['mod' => 'tugas'])

{{-- Kartu ringkasan: satu klik = saring status. Angka dihitung dari seluruh
     lingkup pembaca (TANPA saringan), jadi labelnya jujur sebagai gambaran. --}}
<section class="blok">
  <h2>Ringkasan <small class="pudar">{{ $ringkas['semua'] }} tugas</small></h2>
  <div class="tg-kartu">
    <a class="kartu angka aksi-{{ $statusSaring === '' ? 'netral' : 'netral' }}" href="{{ route('workspace.tugas', array_filter(['q' => $cari, 'prioritas' => $prioritasSaring], fn ($v) => $v !== '')) }}">
      <b>{{ $ringkas['semua'] }}</b><small>Semua</small>
    </a>
    @foreach ($kartu as $k)
      <a class="kartu angka aksi-{{ $warnaStatus[$k] ?? 'netral' }}{{ $statusSaring === $k ? ' ini' : '' }}"
         href="{{ route('workspace.tugas', array_filter(['status' => $k, 'q' => $cari, 'prioritas' => $prioritasSaring], fn ($v) => $v !== '')) }}">
        <b>{{ $ringkas[$k] ?? 0 }}</b>
        <small>{{ $k === 'TERLAMBAT' ? 'Terlambat' : ($label[$k] ?? $k) }}</small>
      </a>
    @endforeach
  </div>
</section>

<section class="blok" id="tg-daftar">
  <h2>Daftar Tugas
    <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }}</small>
  </h2>

  <form class="saring" method="get" action="{{ route('workspace.tugas') }}" data-otomatis>
    <label class="cari">
      <svg class="ikon-cari" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
      <input id="q" name="q" value="{{ $cari }}" placeholder="Cari judul tugas atau koperasi…" aria-label="Cari tugas">
    </label>
    <label class="pilih">
      <span class="sr">Status</span>
      <select name="status" aria-label="Saring status">
        <option value="">Semua status</option>
        @foreach ($kartu as $k)
          <option value="{{ $k }}" @selected($statusSaring === $k)>{{ $k === 'TERLAMBAT' ? 'Terlambat' : ($label[$k] ?? $k) }}</option>
        @endforeach
      </select>
    </label>
    <label class="pilih">
      <span class="sr">Prioritas</span>
      <select name="prioritas" aria-label="Saring prioritas">
        <option value="">Semua prioritas</option>
        @foreach (\App\Models\Domain::PRIORITAS as $p)
          <option value="{{ $p }}" @selected($prioritasSaring === $p)>{{ ucfirst(strtolower($p)) }}</option>
        @endforeach
      </select>
    </label>
    <button class="tombol samar" type="submit">Filter</button>
    @if ($cari !== '' || $statusSaring !== '' || $prioritasSaring !== '')
      <a class="tombol samar" href="{{ route('workspace.tugas') }}">Bersihkan</a>
    @endif
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr>
      <th>Tugas</th><th>Penerima</th><th>Status</th><th>Prioritas</th><th>Tenggat</th><th>Progres</th><th class="kolom-aksi">Aksi</th>
    </tr></thead>
    <tbody>
    @forelse ($tugas as $t)
      @php
        $telat = $t->deadline && $t->status !== 'SELESAI' && substr((string) $t->deadline, 0, 10) < now()->toDateString();
        $p = $penerima[$t->id] ?? ['label' => '—', 'catatan' => ''];
        $pr = $progres[$t->id] ?? collect();
      @endphp
      <tr class="{{ $t->induk_id ? 'baris-langkah' : '' }}" data-status="{{ $t->status }}">
        <td data-l="Tugas">
          <span class="judul-sel">{{ $t->induk_id ? '↳ ' : '' }}{{ $t->judul }}</span>
          @if ($t->induk_id)<small class="pudar">langkah dari #{{ $t->induk_id }}</small>@endif
          @if ($t->koperasi ?? null)<small class="pudar">{{ $t->koperasi }}</small>@endif
          @if ($t->deskripsi)<small class="pudar tg-desk">{{ \Illuminate\Support\Str::limit($t->deskripsi, 120) }}</small>@endif
          @if ($t->catatan_agrinas)
            <small class="tg-catatan"><b>Catatan pengelola:</b> {{ $t->catatan_agrinas }}</small>
          @endif
        </td>
        <td data-l="Penerima">
          <span class="judul-sel">{{ $p['label'] }}</span>
          @if ($p['catatan'] !== '')<small class="pudar">{{ $p['catatan'] }}</small>@endif
        </td>
        <td data-l="Status">
          <form method="post" action="{{ route('workspace.tugas.status', $t->id) }}" class="sebaris">
            @csrf
            <label class="sr" for="st{{ $t->id }}">Status {{ $t->judul }}</label>
            <select id="st{{ $t->id }}" name="status" onchange="this.form.submit()" class="pil-status {{ $warnaStatus[$t->status] ?? '' }}">
              @foreach (\App\Models\Domain::STATUS as $s)
                <option value="{{ $s }}" {{ $t->status === $s ? 'selected' : '' }}
                        {{ in_array($s, $t->langkahBerikut(), true) || $s === $t->status ? '' : 'disabled' }}>
                  {{ $label[$s] ?? $s }}
                </option>
              @endforeach
            </select>
          </form>
        </td>
        <td data-l="Prioritas">
          <span class="lencana kecil prio-{{ strtolower($t->prioritas) }}">{{ ucfirst(strtolower($t->prioritas)) }}</span>
        </td>
        <td data-l="Tenggat">
          <span class="{{ $telat ? 'tg-telat' : '' }}">{{ tanggal_id($t->deadline) }}</span>
          @if ($telat)<small class="tg-telat">terlambat</small>@endif
        </td>
        <td data-l="Progres">
          @if ($pr->isNotEmpty())
            <b>{{ $pr->count() }}</b> <small class="pudar">laporan</small>
            <small class="pudar">{{ waktu_id($pr->last()->dibuat) }}</small>
          @else
            <small class="pudar">belum ada</small>
          @endif
        </td>
        <td class="kolom-aksi">
          <button class="tombol samar mini" type="button" data-buka="tg-progres"
                  data-judul-dialog="Progres: {{ $t->judul }}"
                  data-awal="{{ json_encode(['tugas_id' => $t->id, 'judul' => $t->judul]) }}">Progres</button>
          <button class="tombol samar mini" type="button" data-buka="tg-keterangan"
                  data-judul-dialog="Catatan: {{ $t->judul }}"
                  data-awal="{{ json_encode(['tugas_id' => $t->id, 'judul' => $t->judul, 'catatan' => (string) $t->catatan_agrinas]) }}">Catatan</button>
          {{-- Langkah ditampilkan server-side (bukan dialog ber-fetch): tanpa
               JavaScript pun terbaca, dan tidak ada endpoint JSON tambahan. --}}
          @if (! $t->induk_id && ($langkah[$t->id] ?? collect())->isNotEmpty())
            <span class="lencana kecil">{{ ($langkah[$t->id] ?? collect())->count() }} langkah</span>
          @endif
          @if (isset($ruang[$t->id]))
            <a class="tombol samar mini" href="{{ route('workspace.pesan.ruang', $ruang[$t->id]->id) }}">Obrolan</a>
          @endif
        </td>
      </tr>
      @if (! $t->induk_id)
        <tr class="tg-langkah-baris">
          <td colspan="7">
            <details class="baris-detail">
              <summary class="tombol samar mini">
                Langkah ({{ ($langkah[$t->id] ?? collect())->count() }})
              </summary>
              <ul class="daftar-langkah">
                @foreach (($langkah[$t->id] ?? collect()) as $l)
                  <li>{{ $l->judul }} <small class="pudar">{{ $label[$l->status] ?? $l->status }}</small></li>
                @endforeach
              </ul>
              <form method="post" action="{{ route('workspace.tugas.langkah', $t->id) }}" class="sebaris">
                @csrf
                <label class="sr" for="l{{ $t->id }}">Langkah baru</label>
                <input id="l{{ $t->id }}" name="judul" required maxlength="160" placeholder="Langkah kecil…">
                <button class="tombol samar" type="submit">Tambah</button>
              </form>
            </details>
          </td>
        </tr>
      @endif
      @if ($pr->isNotEmpty())
        <tr class="tg-progres-baris">
          <td colspan="7">
            <details class="baris-detail">
              <summary class="tombol samar mini">Riwayat progres ({{ $pr->count() }})</summary>
              <ol class="tg-riwayat">
                @foreach ($pr as $k)
                  <li>
                    <b>{{ $k->pengirim?->nama ?? 'Pengguna' }}</b>
                    <small class="pudar">{{ waktu_id($k->dibuat) }}</small>
                    <div>{{ $k->catatan }}</div>
                    @if ($k->path)
                      <a class="tombol samar mini" href="{{ route('workspace.tugas.progres.berkas', [$t->id, $k->id]) }}">
                        Lampiran: {{ $k->nama_asli }} <small>({{ number_format(($k->ukuran ?? 0) / 1024, 0, ',', '.') }} KB)</small>
                      </a>
                    @endif
                  </li>
                @endforeach
              </ol>
            </details>
          </td>
        </tr>
      @endif
    @empty
      <tr><td colspan="7" class="kosong">
        @if ($cari !== '' || $statusSaring !== '' || $prioritasSaring !== '')
          Tidak ada tugas yang cocok dengan saringan ini.
        @else
          Belum ada tugas.
        @endif
      </td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>

{{-- ===================== Dialog Tugas Baru ===================== --}}
@if ($koperasi->isNotEmpty())
<dialog class="modal" id="tg-baru" aria-labelledby="tg-baru-judul">
  <form method="post" action="{{ route('workspace.tugas.baru') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="tg-baru-judul">Tugas Baru</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Koperasi
        <select name="koperasi_id" required>
          @foreach ($koperasi as $k)<option value="{{ $k->id }}">{{ $k->kode }} — {{ $k->nama }}</option>@endforeach
        </select>
      </label>
      <label>Judul Tugas
        <input name="judul" required maxlength="160" placeholder="Susun laporan triwulan" autocomplete="off">
      </label>
      <div class="tg-dua">
        <label>Prioritas
          <select name="prioritas">
            <option value="NORMAL">Normal</option><option value="TINGGI">Tinggi</option><option value="RENDAH">Rendah</option>
          </select>
        </label>
        <label>Tenggat<input name="deadline" type="date"></label>
      </div>
      @if ($petugas->isNotEmpty())
        <label>Pelaksana
          <select name="petugas_id">
            <option value="">Manajer koperasi ini</option>
            @foreach ($petugas as $pt)
              <option value="{{ $pt->id }}">{{ $pt->nama }} — {{ \App\Models\Domain::LABEL_PERAN[$pt->peran] ?? $pt->peran }}{{ $pt->wilayah ? ' · '.$pt->wilayah : '' }}</option>
            @endforeach
          </select>
        </label>
      @endif
      <label>Keterangan <span class="bantuan">(apa yang harus dikerjakan)</span>
        <textarea name="deskripsi" rows="3" maxlength="4000"></textarea>
      </label>
      <label class="centang"><input type="checkbox" name="ruang" value="1" checked> Buatkan ruang obrolan untuk tugas ini</label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Buat Tugas</button>
    </footer>
  </form>
</dialog>
@endif

{{-- ======================= Dialog Progres ====================== --}}
<dialog class="modal" id="tg-progres" aria-labelledby="tg-progres-judul">
  <form method="post" enctype="multipart/form-data" id="tg-progres-form">
    @csrf
    <header class="modal-kepala">
      <h2 id="tg-progres-judul" data-teks="judul">Progres</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan">Tulis perkembangan pekerjaannya, lengkapi lampiran bila ada, dan
        (opsional) sekalian ubah status.</p>
      <label>Catatan Progres
        <textarea name="catatan" rows="4" maxlength="2000" required
                  placeholder="Mis. barang sudah diterima 80%, sisa menunggu surat jalan."></textarea>
      </label>
      <label>Lampiran <span class="bantuan">(opsional, maks 20 MB: PDF, gambar, Word, Excel)</span>
        <input type="file" name="berkas" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
      </label>
      <label>Ubah status <span class="bantuan">(boleh dikosongkan)</span>
        <select name="status">
          <option value="">— biarkan seperti sekarang —</option>
          @foreach (\App\Models\Domain::STATUS as $s)
            <option value="{{ $s }}">{{ $label[$s] ?? $s }}</option>
          @endforeach
        </select>
      </label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Simpan Progres</button>
    </footer>
  </form>
</dialog>

{{-- ====================== Dialog Keterangan ==================== --}}
<dialog class="modal" id="tg-keterangan" aria-labelledby="tg-ket-judul">
  <form method="post" id="tg-ket-form">
    @csrf
    <header class="modal-kepala">
      <h2 id="tg-ket-judul" data-teks="judul">Catatan</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Keterangan pengelola
        <textarea name="catatan" rows="4" maxlength="2000"
                  placeholder="Mis. perbaiki lampiran anggaran lalu ajukan ulang."></textarea>
      </label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Simpan Keterangan</button>
    </footer>
  </form>
</dialog>

@endsection

@push('skrip')
<script>
/* Dialog memakai mekanisme umum hub.js (data-buka/data-awal/data-teks). Yang
   khusus di halaman ini hanya MENGARAHKAN FORM ke tugas yang diklik: action
   form bergantung id baris, dan id itu datang dari data-awal (tugas_id). */
document.querySelectorAll('[data-buka="tg-progres"]').forEach(function (b) {
  b.addEventListener('click', function () {
    var id = JSON.parse(b.getAttribute('data-awal')).tugas_id;
    document.getElementById('tg-progres-form').action = '/workspace/tugas/' + id + '/progres';
  });
});
document.querySelectorAll('[data-buka="tg-keterangan"]').forEach(function (b) {
  b.addEventListener('click', function () {
    var d = JSON.parse(b.getAttribute('data-awal'));
    document.getElementById('tg-ket-form').action = '/workspace/tugas/' + d.tugas_id + '/keterangan';
  });
});
</script>
@endpush

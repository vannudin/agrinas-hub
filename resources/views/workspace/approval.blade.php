@extends('layouts.utama')
@section('judul', 'Approval')
@section('isi')
@php
  $totalSetuju = array_sum($ringkasSetuju);
  $rp = fn ($n) => App\Models\Keuangan::rupiah($n);
@endphp

<header class="kepala">
  <div>
    <h1>Approval Biaya Operasional</h1>
    <p class="tenang">Pengajuan biaya operasional gerai koperasi — listrik, air, service AC/kendaraan, perjalanan dinas, rembes pembelian — ke admin pusat / petugas keuangan.</p>
  </div>
  <div class="tombol-baris">
    <button class="tombol utama" type="button" data-buka="setuju-baru">
      <svg class="ik" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Ajukan Biaya
    </button>
  </div>
</header>
@include('workspace._nav', ['mod' => 'setuju'])

<section class="blok" id="setuju">
  {{-- Ringkasan status. Angkanya dari SELURUH pengajuan (bukan 10 baris halaman
       ini), jadi tetap benar saat pengajuan sudah lebih dari satu halaman. --}}
  <p class="setuju-ringkas">
    <span class="lencana s-DIAJUKAN">Menunggu {{ $ringkasSetuju['DIAJUKAN'] ?? 0 }}</span>
    <span class="lencana s-SELESAI">Disetujui {{ $ringkasSetuju['SETUJU'] ?? 0 }}</span>
    <span class="lencana s-REVISI">Ditolak {{ $ringkasSetuju['TOLAK'] ?? 0 }}</span>
    <span class="lencana">Disetujui: {{ $rp($totalBiaya) }}</span>
  </p>

  <form class="saring" method="get" action="{{ route('workspace.approval') }}" data-otomatis>
    <label class="cari">
      <svg class="ikon-cari" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
      <input id="q" name="q" value="{{ $q }}" placeholder="Cari keperluan atau uraian…" aria-label="Cari pengajuan biaya">
    </label>
    <label class="pilih">
      <span class="sr">Status</span>
      <select name="status" aria-label="Saring status">
        <option value="">Semua status</option>
        @foreach (App\Models\Persetujuan::LABEL as $k => $label)
          <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label class="pilih">
      <span class="sr">Koperasi</span>
      <select name="koperasi" aria-label="Saring koperasi">
        <option value="">Semua koperasi</option>
        @foreach ($koperasi as $k)
          <option value="{{ $k->id }}" @selected($kopId === (int) $k->id)>{{ $k->kode }} — {{ $k->nama }}</option>
        @endforeach
      </select>
    </label>
    <button class="tombol samar" type="submit">Filter</button>
    @if ($q !== '' || $status !== '' || $kopId > 0)
      <a class="tombol samar" href="{{ route('workspace.approval') }}">Bersihkan</a>
    @endif
  </form>

  <div class="tabel-bungkus">
  <table>
    <thead><tr>
      <th>Keperluan</th><th>Kategori</th><th>Nominal</th><th>Dibutuhkan</th>
      <th>Status</th><th>Catatan Keputusan</th><th class="kolom-aksi">Aksi</th>
    </tr></thead>
    <tbody>
    @forelse ($persetujuan['baris'] as $s)
      <tr>
        <td data-l="Keperluan"><span class="judul-sel">{{ $s->judul }}</span>
          <small class="pudar">{{ $s->pemohon?->nama ?? '—' }} · {{ $s->koperasi?->nama ?? 'Umum' }} · diajukan {{ waktu_id($s->dibuat) }}</small>
          @if (trim((string) $s->isi) !== '')
            <small class="pudar">{{ \Illuminate\Support\Str::limit(trim((string) $s->isi), 120) }}</small>
          @endif
        </td>
        <td data-l="Kategori">{{ $s->jenis }}</td>
        <td data-l="Nominal">{{ $s->rupiah() }}</td>
        <td data-l="Dibutuhkan">{{ $s->tanggal ? tanggal_id($s->tanggal) : '—' }}</td>
        <td data-l="Status"><span class="lencana s-{{ $s->status === 'SETUJU' ? 'SELESAI' : ($s->status === 'TOLAK' ? 'REVISI' : 'DIAJUKAN') }}">{{ $s->label_status() }}</span></td>
        <td data-l="Catatan Keputusan">
          {{ \Illuminate\Support\Str::limit((string) $s->catatan, 120) ?: '—' }}
          @if ($s->diputus)<small class="pudar">{{ $s->pemutus?->nama ?? '—' }} · {{ waktu_id($s->diputus) }}</small>@endif
        </td>
        <td class="kolom-aksi">
          @if ($boleh_putus && $s->status === 'DIAJUKAN')
            <button class="tombol samar mini" type="button" data-buka="setuju-putus" data-judul-dialog="Putuskan Pengajuan"
                    data-awal="{{ json_encode(['id' => $s->id, 'ringkas' => $s->judul, 'pemohon' => $s->pemohon?->nama ?? '—']) }}">Putuskan</button>
          @else
            <span class="pudar">{{ $s->diputus ? waktu_id($s->diputus) : '—' }}</span>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="7" class="kosong">Belum ada pengajuan biaya.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $persetujuan['halaman'], 'ikon' => 'app', 'kunci' => $persetujuan['kunci']])
</section>

{{-- =========================================== Dialog: ajukan biaya baru --}}
<dialog class="modal" id="setuju-baru" aria-labelledby="setuju-baru-judul">
  <form method="post" action="{{ route('workspace.setuju.ajukan') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="setuju-baru-judul">Ajukan Biaya Operasional</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Keperluan
        <input name="judul" required maxlength="160" placeholder="Bayar tagihan listrik gerai Oktober" autocomplete="off">
      </label>
      <div class="cat-dua">
        <label>Kategori biaya
          <select name="jenis" required>
            @foreach (App\Models\Persetujuan::BIAYA as $b)
              <option value="{{ $b }}">{{ $b }}</option>
            @endforeach
          </select>
        </label>
        <label>Nominal (Rp)
          <input name="nominal" type="number" min="1" step="1" required placeholder="750000" inputmode="numeric">
        </label>
      </div>
      <div class="cat-dua">
        <label>Tanggal dibutuhkan <span class="bantuan">(opsional)</span>
          <input name="tanggal" type="date">
        </label>
        <label>Koperasi
          <select name="koperasi_id">
            <option value="">Umum</option>
            @foreach ($koperasi as $k)<option value="{{ $k->id }}">{{ $k->kode }} — {{ $k->nama }}</option>@endforeach
          </select>
        </label>
      </div>
      <label>Uraian &amp; alasan
        <textarea name="isi" rows="5" maxlength="8000" placeholder="Rincian kebutuhan, nominal tagihan, dan alasan pengajuan…"></textarea>
      </label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Kirim Pengajuan</button>
    </footer>
  </form>
</dialog>

{{-- ================================================= Dialog: putuskan pengajuan --}}
<dialog class="modal" id="setuju-putus" aria-labelledby="setuju-putus-judul"
        data-aksi="{{ route('workspace.setuju.putus', 0) }}">
  <form method="post" action="{{ route('workspace.setuju.putus', 0) }}" id="setuju-putus-form">
    @csrf
    <header class="modal-kepala">
      <h2 id="setuju-putus-judul">Putuskan Pengajuan</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="cat-meta"><b data-teks="ringkas">—</b> · diajukan <span data-teks="pemohon">—</span></p>
      <label>Catatan keputusan <span class="bantuan">(opsional)</span>
        <input name="catatan" maxlength="2000" placeholder="Alasan / arahan lanjutan…">
      </label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol samar" type="submit" name="status" value="TOLAK">Tolak</button>
      <button class="tombol utama" type="submit" name="status" value="SETUJU">Setujui</button>
    </footer>
  </form>
</dialog>

<script>
/* `action` modal putuskan: route() butuh slug {id}, sedangkan id-nya baru ada
   saat barisnya dibuka. Karena itu `action` memakai angka 0 yang ditimpa di sini
   — bukan dipilih dari `data-*` per tombol, supaya cuma ada SATU tempat yang
   menyusun URL-nya. */
document.addEventListener('click', function (ev) {
  var b = ev.target.closest && ev.target.closest('[data-buka="setuju-putus"]');
  if (!b) return;
  var d = document.getElementById('setuju-putus');
  if (!d) return;
  try {
    var a = JSON.parse(b.getAttribute('data-awal') || '{}');
    var dasar = d.getAttribute('data-aksi') || '';
    d.querySelector('#setuju-putus-form').setAttribute('action', dasar.replace(/\/0$/, '/' + a.id));
  } catch (e) { /* data-awal rusak: form tetap terbuka, action tidak berubah */ }
});
</script>
@endsection

@extends('layouts.utama')
@section('judul', 'Catatan Saya')
@section('isi')
@php
  $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $warna = ['kegiatan' => 'cat-kegiatan', 'kendala' => 'cat-kendala', 'rencana' => 'cat-rencana',
            'ide' => 'cat-ide', 'penting' => 'cat-penting'];
@endphp

<header class="kepala">
  <div>
    <h1>Catatan Saya</h1>
    <p class="tenang">
      <b>Pribadi</b> — hanya Anda yang bisa membaca dan mengubahnya. {{ number_format($ringkas['semua'], 0, ',', '.') }} catatan tersimpan.
    </p>
  </div>
  <div class="tombol-baris">
    <button class="tombol utama" type="button" data-buka="cat-baru"
            data-awal="{{ json_encode(['tanggal' => $tanggal]) }}">
      <svg class="ik" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      Tambah Catatan
    </button>
  </div>
</header>
@include('workspace._nav', ['mod' => 'catatan-harian'])

{{-- =================================================== Catatan tanggal ini --}}
<section class="blok">
  <div class="cat-kepala">
    <h2>Catatan {{ tanggal_id($tanggal, 'd F Y') }}
      <small class="pudar">{{ $hariIni->count() }} catatan</small>
    </h2>
    <form method="get" action="{{ route('workspace.catatan-harian') }}" class="cat-tanggal" data-otomatis>
      <label class="sr" for="tanggal">Tanggal</label>
      <input id="tanggal" name="tanggal" type="date" value="{{ $tanggal }}" required>
      @if ($cari !== '')<input type="hidden" name="q" value="{{ $cari }}">@endif
      @if ($jenis !== '')<input type="hidden" name="jenis" value="{{ $jenis }}">@endif
      <button class="tombol samar" type="submit">Buka</button>
    </form>
  </div>

  @forelse ($hariIni as $c)
    <article class="cat-kartu {{ $warna[$c->jenis] ?? '' }}">
      <div class="cat-isi">
        <h3>{{ $c->judul }}</h3>
        <p class="cat-meta">
          <span class="lencana kecil">{{ $c->label_jenis() }}</span>
          diperbarui {{ waktu_id($c->diperbarui ?: $c->dibuat) }}
        </p>
        @if (trim((string) $c->isi) !== '')
          <div class="cat-teks">{!! nl2br(e($c->isi)) !!}</div>
        @endif
        @if (trim((string) $c->kendala) !== '')
          <div class="cat-kendala"><b>Kendala / tindak lanjut</b>{!! nl2br(e($c->kendala)) !!}</div>
        @endif
      </div>
      <div class="cat-aksi">
        <button class="tombol samar mini" type="button" data-buka="cat-baru" data-judul-dialog="Ubah Catatan"
                data-awal="{{ json_encode(['id' => $c->id, 'tanggal' => $c->tanggal, 'judul' => $c->judul, 'jenis' => $c->jenis, 'isi' => $c->isi, 'kendala' => $c->kendala]) }}">Ubah</button>
        <form method="post" action="{{ route('workspace.catatan-harian.hapus', $c->id) }}"
              onsubmit="return confirm('Hapus catatan “{{ $c->judul }}”?')">
          @csrf @method('DELETE')
          <button class="tombol samar mini" type="submit">Hapus</button>
        </form>
      </div>
    </article>
  @empty
    <div class="cat-kosong">
      <p><b>Belum ada catatan pada tanggal ini.</b></p>
      <p class="pudar">Tulis apa yang Anda kerjakan, kendala yang dihadapi, atau rencana berikutnya.</p>
      <button class="tombol utama" type="button" data-buka="cat-baru"
              data-awal="{{ json_encode(['tanggal' => $tanggal]) }}">+ Tambah Catatan</button>
    </div>
  @endforelse
  @include('partials.halaman', ['halaman' => $halamanHari, 'ikon' => 'app', 'kunci' => 'halaman'])
</section>

{{-- ========================================================== Arsip & cari --}}
<section class="blok" id="cat-arsip">
  <h2>Arsip Catatan <small class="pudar">seluruh catatan Anda</small></h2>

  <form class="saring" method="get" action="{{ route('workspace.catatan-harian') }}" data-otomatis>
    <input type="hidden" name="tanggal" value="{{ $tanggal }}">
    <label class="cari">
      <svg class="ikon-cari" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
      <input id="q" name="q" value="{{ $cari }}" placeholder="Cari judul atau isi catatan…" aria-label="Cari catatan">
    </label>
    <label class="pilih">
      <span class="sr">Jenis</span>
      <select name="jenis" aria-label="Saring jenis catatan">
        <option value="">Semua jenis ({{ $ringkas['semua'] }})</option>
        @foreach (App\Models\CatatanHarian::JENIS as $k => $label)
          <option value="{{ $k }}" @selected($jenis === $k)>{{ $label }} ({{ $ringkas[$k] }})</option>
        @endforeach
      </select>
    </label>
    <button class="tombol samar" type="submit">Filter</button>
    @if ($cari !== '' || $jenis !== '')
      <a class="tombol samar" href="{{ route('workspace.catatan-harian', ['tanggal' => $tanggal]) }}">Bersihkan</a>
    @endif
  </form>

  @if ($arsip->isEmpty())
    <p class="kosong">
      @if ($cari !== '' || $jenis !== '')
        Tidak ada catatan yang cocok dengan saringan ini.
      @else
        Belum ada catatan tersimpan.
      @endif
    </p>
  @else
    <div class="cat-arsip-baris">
      @foreach ($arsip as $c)
        <article class="cat-kartu kecil {{ $warna[$c->jenis] ?? '' }}">
          <div class="cat-isi">
            <h3>{{ $c->judul }}</h3>
            <p class="cat-meta">
              <span class="lencana kecil">{{ $c->label_jenis() }}</span>
              {{ tanggal_id($c->tanggal, 'd M Y') }} · {{ waktu_id($c->diperbarui ?: $c->dibuat) }}
            </p>
            <div class="cat-teks">{{ \Illuminate\Support\Str::limit(trim((string) $c->isi) ?: (string) $c->kendala, 220) ?: '—' }}</div>
          </div>
          <div class="cat-aksi">
            <button class="tombol utama mini" type="button" data-buka="cat-lihat"
                    data-awal="{{ json_encode(['judul' => $c->judul, 'jenisLabel' => $c->label_jenis(), 'tanggal' => tanggal_id($c->tanggal, 'd F Y'), 'isi' => trim((string) $c->isi) ?: '—', 'kendala' => trim((string) $c->kendala)]) }}">Detail</button>
            <button class="tombol samar mini" type="button" data-buka="cat-baru" data-judul-dialog="Ubah Catatan"
                    data-awal="{{ json_encode(['id' => $c->id, 'tanggal' => $c->tanggal, 'judul' => $c->judul, 'jenis' => $c->jenis, 'isi' => $c->isi, 'kendala' => $c->kendala]) }}">Ubah</button>
          </div>
        </article>
      @endforeach
    </div>
    @include('partials.halaman', ['halaman' => $halamanArsip, 'ikon' => 'app', 'kunci' => 'halaman_arsip'])
  @endif
</section>

{{-- Dialog Tambah/Ubah. Satu <dialog> melayani dua mode: tanpa `id` = baru,
     dengan `id` = ubah. Isinya diisi `data-awal` tombolnya (mekanisme hub.js),
     jadi tidak ada markup kedua yang bisa basi. --}}
<dialog class="modal" id="cat-baru" aria-labelledby="cat-baru-judul">
  <form method="post" action="{{ route('workspace.catatan-harian.simpan') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="cat-baru-judul">Tambah Catatan</h2>
      <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <input type="hidden" name="id" value="">
      <label>Nama Catatan
        <input name="judul" required maxlength="160" placeholder="mis. Kunjungan lapangan ke Muara Bakti" autocomplete="off">
      </label>
      <div class="cat-dua">
        <label>Jenis
          <select name="jenis">
            @foreach (App\Models\CatatanHarian::JENIS as $k => $label)
              <option value="{{ $k }}">{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>Tanggal
          <input name="tanggal" type="date" value="{{ $tanggal }}" required>
        </label>
      </div>
      <p class="bantuan cat-saran">Cepat:
        @foreach ($saranJudul as $s)
          <button class="pil" type="button" data-pil-judul="{{ $s }}">{{ $s }}</button>
        @endforeach
      </p>
      <label>Keterangan
        <textarea name="isi" rows="6" maxlength="8000" placeholder="Uraikan kegiatan, hasil, atau hal penting lainnya…"></textarea>
      </label>
      <label>Kendala / tindak lanjut <span class="bantuan">(opsional)</span>
        <textarea name="kendala" rows="3" maxlength="4000" placeholder="Kendala, kebutuhan bantuan, langkah berikutnya…"></textarea>
      </label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Batal</button>
      <button class="tombol utama" type="submit">Simpan Catatan</button>
    </footer>
  </form>
</dialog>

{{-- Detail catatan: BACA-SAJA, memakai mekanisme dialog yang sama. Isinya
     diisikan ke elemen ber-`data-teks` (bukan <input>), jadi tidak ada form
     kedua yang bisa tersimpan keliru. --}}
<dialog class="modal" id="cat-lihat" aria-labelledby="cat-lihat-judul">
  <header class="modal-kepala">
    <h2 id="cat-lihat-judul" data-teks="judul">Detail Catatan</h2>
    <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
  </header>
  <div class="modal-badan">
    <p class="cat-meta"><span class="lencana kecil" data-teks="jenisLabel">—</span>
      <span data-teks="tanggal">—</span></p>
    <div class="cat-detail" data-teks="isi">—</div>
    <div class="cat-kendala" data-teks="kendala" hidden></div>
  </div>
  <footer class="modal-kaki">
    <button class="tombol utama" type="button" data-tutup>Tutup</button>
  </footer>
</dialog>
@endsection

@extends('layouts.utama')
@section('judul', $ruang->nama_tampil ?? $ruang->nama)
@section('kelas_tubuh', 'wa-penuh')
@section('isi')
@php
  // Nama untuk pewarnaan sebutan (satu daftar kecil, bukan regex raksasa).
  $namaSebut = $anggota->pluck('user_id')->map(fn ($id) => $namaAnggota[$id] ?? null)->filter()->values();
  $muatan = $muatan ?? ['pesan' => [], 'lebih_lama' => false];
  $pesan = collect($muatan['pesan']);
  $sebelum = null;
  // Papan pesan memuat id SEKATAN terakhir yang dikirim server: denyut hanya
  // menanyakan yang lebih baru dari ini.
  $idTerakhir = (int) ($pesan->last()['id'] ?? 0);
  $idTerawal = (int) ($pesan->first()['id'] ?? 0);
@endphp

{{-- Percakapan gaya WhatsApp: sisi kiri daftar percakapan, sisi kanan percakapan.
     Halaman ini memakai seluruh tinggi layar (kelas tubuh `wa-penuh`) supaya
     tidak ada ruang kosong di bawah — itu yang membuatnya terasa "nanggung".
     Di ponsel, daftar jadi laci yang dibuka lewat tombol kembali (lihat CSS+JS). --}}
<section class="wa wa-percakapan"
         data-src="{{ route('workspace.pesan.ruang', $ruang->id) }}"
         data-csrf="{{ csrf_token() }}">
  <div class="wa-laci" id="wa-laci" hidden></div>
  @include('workspace._pesan-sisi', ['aktif' => $ruang->id, 'daftar' => $daftar,
    'rekan' => $rekan, 'koperasi' => $koperasi, 'kategoriDaftar' => $kategoriDaftar ?? []])
  <div class="wa-ruang">
    <header class="wa-kepala">
      <button class="wa-kembali" type="button" id="wa-kembali" aria-label="Kembali ke daftar percakapan">‹</button>
      <span class="wa-avatar besar" aria-hidden="true">{{ mb_strtoupper(mb_substr($ruang->nama_tampil ?? $ruang->nama, 0, 1)) }}</span>
      <div class="wa-kepala-teks">
        <b>{{ $ruang->nama_tampil ?? $ruang->nama }}</b>
        <small id="wa-status">
          @if ($tautan)
            menempel pada
            @if ($ruang->acuan === 'dokumen' && \Route::has('workspace.dokumen.editor'))
              <a href="{{ route('workspace.dokumen.editor', $ruang->acuan_id) }}">{{ $tautan[1] }}</a>
            @else
              {{ $tautan[1] }}
            @endif
          @elseif ($ruang->pribadi())
            Percakapan pribadi — hanya Anda berdua
          @elseif ($ruang->kunci === \App\Models\RuangObrolan::KUNCI['anggota'])
            Grup tertutup · {{ $anggota->count() }} peserta
          @else
            {{ \App\Models\RuangObrolan::JENIS[$ruang->jenis] ?? $ruang->jenis }}
          @endif
        </small>
      </div>

      <form method="post" action="{{ route('workspace.pesan.semat', $ruang->id) }}" class="wa-semat-form">
        @csrf
        <button class="tombol samar mini" type="submit"
                title="{{ $ruang->tersemat ? 'Lepas sematan' : 'Sematkan percakapan' }}"
                aria-label="{{ $ruang->tersemat ? 'Lepas sematan' : 'Sematkan percakapan' }}">📌</button>
      </form>

      @if ($anggota->isNotEmpty())
        <details class="wa-info">
          <summary class="tombol samar mini" title="Peserta ruang">Peserta</summary>
          <ul class="wa-peserta">
            @foreach ($anggota as $a)
              <li>{{ $namaAnggota[$a->user_id] ?? 'Pengguna #'.$a->user_id }}</li>
            @endforeach
          </ul>
        </details>
      @endif
    </header>

    {{-- Topik forum: pertanyaan/pembuka yang membuat ruang ini sebuah forum.
         Tanpa ini, forum di dalam ruangnya terlihat persis seperti grup biasa —
         penandanya cuma di daftar percakapan. --}}
    @if (trim((string) $ruang->topik) !== '')
      <div class="wa-topik-bilah">
        <b>Topik</b>
        <span>{{ $ruang->topik }}</span>
      </div>
    @endif

    {{-- Pesan yang disematkan tampil sebagai pita di atas riwayat (Lark). --}}
    @php $semat = $pesan->firstWhere('disematkan', true); @endphp
    <div class="wa-semat-pita" id="wa-semat-pita" @if (! $semat) hidden @endif>
      <span aria-hidden="true">📌</span>
      <span class="wa-semat-isi">{{ $semat['isi'] ?? '' }}</span>
    </div>

    <div class="wa-pesan" id="wa-pesan" role="log" aria-live="polite" aria-relevant="additions"
         data-terakhir="{{ $idTerakhir }}" data-terawal="{{ $idTerawal }}"
         data-lebih="{{ ! empty($muatan['lebih_lama']) ? 1 : 0 }}">
      <button class="tombol samar mini wa-lebih" type="button" id="wa-lebih"
              @if (empty($muatan['lebih_lama'])) hidden @endif>Muat pesan lebih lama</button>

      @forelse ($pesan as $i => $p)
        @php
          $lanjut = $i > 0 && ($pesan[$i - 1]['nama'] ?? null) === ($p['nama'] ?? null);
        @endphp
        @include('workspace._pesan-satu', ['p' => $p, 'lanjut' => $lanjut, 'namaSebut' => $namaSebut,
          'teruskanId' => 'wa-teruskan-form'])
      @empty
        <p class="wa-kosong" id="wa-mulai">Belum ada pesan. Mulai percakapan di bawah.</p>
      @endforelse
    </div>

    {{-- Formulir "Teruskan" BERSAMA: tombolnya ada di dalam setiap menu pesan
         dan menunjuk ke sini lewat `form="..."`. Satu kolom pilih percakapan
         untuk seluruh halaman, bukan satu per pesan (30 pilihan × N pesan). --}}
    <form method="post" action="{{ route('workspace.pesan.aksi', $ruang->id) }}" id="wa-teruskan-form" hidden>
      @csrf
      <input type="hidden" name="aksi" value="teruskan">
      <select name="ke[]" multiple size="5" required>
        @foreach ($daftar as $rp)
          @if ((int) $rp->id !== (int) $ruang->id)
            <option value="{{ $rp->id }}">{{ $rp->nama_tampil ?? $rp->nama }}</option>
          @endif
        @endforeach
      </select>
      <button class="tombol utama mini" type="submit">Teruskan</button>
    </form>

    {{-- Balasan berkutip: bilah kecil di atas kotak tulis. --}}
    <div class="wa-balas-bilah" id="wa-balas-bilah" hidden>
      <span class="wa-balas-teks"><b id="wa-balas-nama"></b><small id="wa-balas-isi"></small></span>
      <button class="tombol samar mini" type="button" id="wa-balas-batal" aria-label="Batalkan balasan">✕</button>
    </div>

    <form class="wa-kirim" method="post" action="{{ route('workspace.pesan.kirim', $ruang->id) }}" data-mulai
          data-lampiran="{{ route('workspace.pesan.lampiran', $ruang->id) }}"
          data-suara="{{ route('workspace.pesan.suara', $ruang->id) }}">
      @csrf
      <input type="hidden" name="balas_id" id="wa-balas-id" value="">
      <textarea id="isi" name="isi" rows="1" maxlength="4000" placeholder="Tulis pesan…"
        @if ($dokumen->isNotEmpty()) data-dokumen="{{ $dokumen->map(fn ($d) => $d->id.'|'.$d->judul)->implode(' ;; ') }}" @endif></textarea>
      <button class="tombol samar mini wa-ikon" type="button" id="wa-lampir-buka"
              title="Lampirkan foto, berkas, atau suara" aria-label="Lampirkan berkas">＋</button>
      <button class="tombol utama" type="submit" aria-label="Kirim pesan">Kirim</button>
    </form>

    {{-- Panel lampiran: foto, berkas, lokasi-rasa-WhatsApp diganti "pesan suara". --}}
    <div class="wa-lampir" id="wa-lampir" hidden>
      <form method="post" action="{{ route('workspace.pesan.lampiran', $ruang->id) }}"
            enctype="multipart/form-data" class="wa-lampir-form">
        @csrf
        <label class="tombol samar mini">📷 Foto &amp; berkas
          <input type="file" name="berkas" accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip" hidden
                 id="wa-berkas">
        </label>
        <button class="tombol utama mini" type="submit">Kirim lampiran</button>
      </form>
      <div class="wa-suara-rekam">
        <button class="tombol samar mini" type="button" id="wa-rekam-mulai">🎤 Rekam suara</button>
        <button class="tombol samar mini" type="button" id="wa-rekam-stop" hidden>■ Selesai</button>
        <small id="wa-rekam-info" class="pudar"></small>
      </div>
      <p class="bantuan">Foto dikompresi otomatis sebelum dikirim (maks 6 MB). Batas berkas 10 MB.</p>
    </div>
  </div>
</section>

{{-- Gambar penuh: ketuk foto di percakapan = lihat besar (ala WhatsApp). --}}
<dialog class="wa-lihat-foto" id="wa-lihat-foto">
  <img alt="Foto lampiran" id="wa-lihat-img">
  <button class="tombol samar" type="button" onclick="document.getElementById('wa-lihat-foto').close()">Tutup</button>
</dialog>

@push('skrip')
<script src="/js/hub-obrolan.js?v={{ filemtime(public_path('js/hub-obrolan.js')) }}" defer></script>
@endpush
@endsection

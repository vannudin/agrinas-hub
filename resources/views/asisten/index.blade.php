@extends('layouts.utama')

@section('judul', 'Asisten AI')
@section('isi')
@php
  // Peran ditentukan SEKALI di model (User::kunciAi()), lalu dipakai untuk
  // semua yang berbeda per peran: saran, judul panel, dan deskripsi. Sebelumnya
  // pemeriksaannya `hrd()` yang juga true untuk ADMIN pusat — jadi admin melihat
  // saran & persona HR. Menambah peran cukup di satu tempat itu.
  $kunci = auth()->user()->kunciAi();
  $contoh = match ($kunci) {
    // Kalimatnya SENGAJA pendek: ini "perintah cepat", bukan deskripsi. Versi
    // panjang (mis. "…dan apa yang harus saya kerjakan lebih dulu?") memaksa
    // kartu membungkus 5-6 baris di ponsel dan kalah terbaca.
    'hr' => ['Siapa yang belum siap transfer gajinya?',
             'Susun langkah menyiapkan payroll bulan ini.',
             'Pegawai mana yang belum dinilai kinerjanya?',
             'Susun pengumuman cuti bersama.'],
    'agrinas' => ['Ringkas kondisi seluruh koperasi hari ini.',
                  'Koperasi mana yang perlu perhatian?',
                  'Stok apa yang di bawah minimum?',
                  'Susun instruksi rapat koordinasi manajer.'],
    'pic' => ['Ringkas kondisi koperasi di wilayah saya.',
              'Koperasi mana di wilayah saya yang perlu perhatian?',
              'Koperasi mana yang belum punya manajer?',
              'Susun rekap bulanan wilayah saya.'],
    'manajer' => ['Bagaimana keuangan koperasi saya bulan ini?',
                  'Susun laporan mingguan dari catatan harian.',
                  'Barang apa yang stoknya perlu diajukan?',
                  'Apa yang perlu saya kerjakan hari ini?'],
    default => ['Apa saja yang bisa Anda bantu di Hub?',
                'Ringkas data yang boleh saya lihat hari ini.'],
  };
  $judulAi = match ($kunci) {
    'hr' => 'HR Manager Agrinas',
    'agrinas' => 'Asisten Operasi Pusat',
    'pic' => 'Pendamping PIC Wilayah',
    'manajer' => 'Pendamping Manajer KDKMP',
    default => 'Asisten Agrinas',
  };
  $deskripsiAi = match ($kunci) {
    'hr' => 'HR Manager senior (25 tahun pengalaman) — menjawab dari data kepegawaian Hub: rekening & BPJS, kontrak, penilaian, rekrutmen. Nominal gaji tetap dari HRIS.',
    'agrinas' => 'Membaca angka seluruh koperasi binaan: stok, distribusi, pasokan, keuangan, tugas.',
    'pic' => 'Fokus koperasi di wilayah Anda: yang perlu perhatian, distribusi/pasokan tertahan, rekap wilayah.',
    'manajer' => 'Fokus koperasi Anda: keuangan, stok, pasokan UMKM, tugas, dan laporan.',
    default => 'Menjawab dari angka yang boleh Anda lihat.',
  };
@endphp

<header class="kepala">
  <div>
    <h1>Asisten AI</h1>
    <p class="tenang">{{ $deskripsiAi }}</p>
  </div>
  <div class="tombol-baris">
    {{-- Tombol ini dibuat/ditampilkan oleh hub-asisten.js setelah jawaban pertama
         bila percakapan baru dimulai dari halaman kosong. --}}
    @if ($riwayat->isNotEmpty())
      <form method="post" action="{{ route('asisten.bersih') }}" data-ai-bersih>
        @csrf
        <button class="tombol samar" type="submit">Bersihkan Percakapan</button>
      </form>
    @endif
  </div>
</header>

@unless ($siap)
  <div class="pesan ingat" role="status">
    Kunci API asisten belum diatur. Isi <code>AI_API_KEY</code> (dan bila perlu
    <code>AI_BASE_URL</code>, <code>AI_MODEL</code>) di <code>.env</code> lalu muat ulang halaman ini.
  </div>
@endunless

<section class="blok" id="ai" data-ajuan="{{ route('asisten.tanya') }}"
         data-hapus="{{ route('asisten.bersih') }}" data-token="{{ csrf_token() }}">

  {{-- Kepala panel: siapa yang menjawab & apakah ia siap. Statusnya data
       sungguhan, bukan hiasan — penanda "Belum dikonfigurasi" sama dengan
       peringatan AI_API_KEY di bawah, hanya lebih cepat terlihat. --}}
  <div class="ai-kepala">
    <span class="ai-avatar" aria-hidden="true">AI</span>
    <div>
      <b>{{ $judulAi }}</b>
      <small class="pudar">{{ $deskripsiAi }}</small>
    </div>
    <span class="lencana {{ $siap ? 's-SELESAI' : 's-REVISI' }}">{{ $siap ? 'Siap' : 'Belum dikonfigurasi' }}</span>
  </div>

  {{-- Balon percakapan. Terisi dari sesi saat halaman dimuat; sesudah itu
       hub-asisten.js menambah/mengganti balon tanpa muat ulang halaman.
       `data-kosong` menandai percakapan belum ada: panel kosong disembunyikan
       supaya tidak jadi kotak menganga. `aria-live` menutup celah balon yang
       dibuat JS (role="status" hanya ada pada balon dari server). --}}
  <div id="ai-balon" role="status" @if ($riwayat->isEmpty()) data-kosong @endif>
    @foreach ($riwayat as $r)
      <div class="aibalon {{ $r['role'] === 'user' ? 'ingat' : 'ok' }}">
        <b>{{ $r['role'] === 'user' ? 'Anda' : 'Asisten' }}</b>
        {{-- Keluaran Markdown yang SUDAH disanitasi (html_input=strip,
             allow_unsafe_links=false) — bukan teks mentah model. --}}
        <div class="aiteks">{!! $r['html'] !!}</div>
      </div>
    @endforeach
  </div>

  <div id="ai-mulai" @if ($riwayat->isNotEmpty()) hidden @endif>
    <h2>Mulai dari sini</h2>
    <p class="bantuan">Pilih salah satu, atau tulis pertanyaan Anda sendiri.</p>
    <div class="kartu-baris" id="ai-contoh">
      @foreach ($contoh as $c)
        <form method="post" action="{{ route('asisten.tanya') }}">
          @csrf
          <input type="hidden" name="tanya" value="{{ $c }}">
          <button class="kartu kartu-tanya" type="submit"><b>{{ $c }}</b></button>
        </form>
      @endforeach
    </div>
  </div>

  {{-- Kotak tulis: label visual dibuang (diganti placeholder) karena judulnya
       sudah jelas dari tombol kirimnya; `sr` tetap ada supaya pembaca layar
       tidak kehilangan namanya. --}}
  <form method="post" action="{{ route('asisten.tanya') }}" data-ai-kirim class="ai-tulis">
    @csrf
    <label class="sr" for="tanya">Pertanyaan atau permintaan untuk asisten</label>
    <textarea id="tanya" name="tanya" rows="3" maxlength="2000" required
      placeholder="Tulis pertanyaan atau permintaan… (Enter mengirim, Shift+Enter baris baru)"></textarea>
    <div class="tombol-baris">
      <button class="tombol utama" type="submit" data-ai-tombol>Kirim ke Asisten</button>
    </div>
  </form>
  <p class="bantuan">Percakapan disimpan di sesi dan ikut hilang saat Anda keluar — tidak disimpan permanen.</p>
</section>

{{-- Pembeda versi WAJIB: tanpa ?v= berkas JS tersaji dari cache Cloudflare/
     peramban, sehingga perbaikan tidak pernah sampai ke pengguna. --}}
<script src="/js/hub-asisten.js?v={{ filemtime(public_path('js/hub-asisten.js')) }}" defer></script>
@endsection

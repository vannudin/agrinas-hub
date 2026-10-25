@extends('layouts.utama')
@section('judul', 'Messenger')
@section('kelas_tubuh', 'wa-penuh')
@section('isi')
@php $forum = $forum ?? collect(); @endphp
{{-- Halaman indeks Messenger = daftar percakapan + kategori (Lark) dengan
     lencana belum-dibaca (WhatsApp). Sengaja TIDAK membuat percakapan otomatis
     ke pengguna pertama: ruang kosong tanpa pesan itu sampah. --}}
<section class="wa wa-sendiri">
  @include('workspace._pesan-sisi', ['aktif' => 0, 'daftar' => $daftar,
    'rekan' => $rekan, 'koperasi' => $koperasi, 'kategoriDaftar' => $kategoriDaftar,
    'lingkupDaftar' => $lingkupDaftar ?? []])

  <div class="wa-ruang wa-ajak">
    <div class="wa-ajak-dalam">
      <h1>Messenger</h1>
      <p class="tenang">Pilih percakapan di kiri, atau buat yang baru.</p>
      <ul class="wa-cara">
        <li><b>+ Baru</b> → <b>Mulai Pribadi</b>: percakapan dua orang, hanya terlihat Anda berdua.</li>
        <li><b>+ Baru</b> → <b>Grup</b>: tim, koperasi, atau sesama manajer &amp; PIC.</li>
        <li><b>+ Baru</b> → <b>Lingkup peserta</b>: pilih wilayah/kabupaten/kecamatan/peran, pesertanya ditarik otomatis.</li>
        <li><b>+ Baru</b> → <b>Forum</b>: buka topik diskusi untuk semua pengelola di lingkup itu.</li>
      </ul>

      {{-- Forum: ruang yang punya TOPIK. Ditampilkan di halaman indeks supaya
           pembahasan tidak tenggelam di antara percakapan sehari-hari. --}}
      @if ($forum->isNotEmpty())
        <section class="wa-forum" aria-label="Forum diskusi">
          <b>Forum</b>
          <ul class="pilih-daftar">
            @foreach ($forum as $f)
              <li><a class="pilih-baris" href="{{ route('workspace.pesan.ruang', $f->id) }}">
                <span class="pilih-nama">{{ $f->nama }}</span>
                <small>{{ \Illuminate\Support\Str::limit($f->topik, 90) }}</small>
              </a></li>
            @endforeach
          </ul>
        </section>
      @endif

      <p class="bantuan">Percakapan juga muncul otomatis dari dokumen, tugas, dan rapat — supaya konteksnya tidak hilang.</p>
    </div>
  </div>
</section>
@endsection

@push('skrip')
{{-- Skrip Messenger wajib ada di HALAMAN DAFTAR juga: formulir "+ Baru"
     (pemilih peserta & Mulai Pribadi) hidup di sini, bukan di halaman ruang.
     Dulu skrip ini hanya dipush oleh halaman ruang, sehingga pemilih peserta
     tampak ada tapi tak pernah berfungsi. Select2 & jQuery dimuat oleh
     `_pesan-sisi` (partial yang sama dipakai halaman ruang). --}}
<script src="/js/hub-obrolan.js?v={{ filemtime(public_path('js/hub-obrolan.js')) }}" defer></script>
@endpush

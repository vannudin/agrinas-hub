@extends('layouts.utama')
@section('judul', 'OpenCut')
@section('isi')
<header class="kepala">
  <div>
    <h1>OpenCut</h1>
    <p class="tenang">Editor video: susun <b>klip</b>, potong, beri efek &amp; teks, lalu ekspor.
      Semuanya berjalan di peramban Anda — tidak ada berkas video yang diunggah ke server.</p>
  </div>
  <div class="tombol-baris">
    {{-- Ponsel: bingkai 70dvh terlalu sempit untuk panel + garis waktu, jadi
         sediakan jalan layar penuh di tab sendiri. --}}
    <a class="tombol samar" href="/video/projects" target="_blank" rel="noopener">Layar penuh</a>
    <a class="tombol samar" href="{{ route('workspace.gambar') }}">PhotoCraft</a>
    <a class="tombol samar" href="{{ route('workspace') }}">Ruang Kerja</a>
  </div>
</header>
{{-- `mod` = 'video' → tautan OpenCut di bilah modul tersorot, sama seperti
     halaman modul lain. Salah kunci di sini = halaman lain yang tersorot. --}}
@include('workspace._nav', ['mod' => 'video'])
{{-- Muat ulang SEKALI setelah cache peramban dibersihkan (lihat
     WorkspaceController::sekali_segarkan). Tanpa ini, halaman yang sedang
     dibuka masih memakai aset 404 lama dan editor tampak tanpa CSS / macet
     di "Loading". --}}
@if (! request()->cookie('segarkan_editor'))
<script>addEventListener('load',function(){setTimeout(function(){location.reload();},300);});</script>
@endif

<section class="blok">
  {{-- Editor dibingkai apa adanya. Bingkai `.editor-bingkai` menerima iframe
       polos (aturannya `> *`), jadi tidak perlu CSS baru.

       `src` menunjuk /video/ di asal yang SAMA dengan Hub (bukan subdomain):
       gerbangnya nginx `auth_request` ke sesi Hub, jadi hanya pengguna yang
       sudah masuk yang bisa memuatnya, dan cookie sesi tetap pihak pertama.

       Sengaja TANPA `sandbox`: editor perlu mengunduh hasil ekspor dan menyimpan
       proyek di penyimpanan peramban (OPFS); `sandbox` tanpa `allow-downloads`
       memblokir keduanya. --}}
  <div class="editor-bingkai tinggi">
    <iframe id="opencut" src="/video/projects" title="OpenCut — editor video"
            allow="fullscreen; clipboard-read; clipboard-write"
            allowfullscreen></iframe>
  </div>

  <p class="bantuan">
    <b>Cara pakai:</b> buat proyek baru, tarik berkas video ke jendela editor,
    susun klip di garis waktu, lalu ekspor. Hasil ekspor menjadi <b>unduhan</b> ke
    perangkat Anda. Kalau layar terasa sempit, buka editor di
    <a href="/video/projects" target="_blank" rel="noopener">tab tersendiri</a>.
  </p>
</section>
@endsection

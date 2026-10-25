@extends('layouts.utama')
@section('judul', 'PhotoCraft')
@section('isi')
<header class="kepala">
  <div>
    <h1>PhotoCraft</h1>
    <p class="tenang">Editor gambar: buka <b>PSD/PSB</b>, sunting lapisan, ekspor PNG/JPG.
      Semuanya berjalan di peramban Anda — tidak ada gambar yang diunggah ke server.</p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ $editor }}" target="_blank" rel="noopener">Layar penuh</a>
    <a class="tombol samar" href="{{ route('workspace.dokumen') }}">Docs &amp; Sheets</a>
    <a class="tombol samar" href="{{ route('workspace') }}">Ruang Kerja</a>
  </div>
</header>
{{-- `mod` = 'gambar' → tautan PhotoCraft di bilah modul tersorot, sama seperti
     halaman modul lain. Sebelumnya halaman ini memakai 'dokumen', jadi yang
     tersorot justru Docs & Sheets. --}}
@include('workspace._nav', ['mod' => 'gambar'])
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

       Sengaja TANPA `sandbox`: editor ini perlu mengunduh hasil simpan/ekspor,
       dan `sandbox` tanpa `allow-downloads` justru memblokirnya (tanpa
       `allow-same-origin` ia juga kehilangan penyimpanan preferensinya). --}}
  <div class="editor-bingkai">
    <iframe id="photocraft" src="{{ $editor }}" title="PhotoCraft — editor gambar"
            allow="fullscreen; clipboard-read; clipboard-write"
            allowfullscreen></iframe>
  </div>

  <p class="bantuan">
    <b>Cara pakai:</b> buka berkas lewat menu di editor, atau tarik berkasnya ke
    jendela editor. Menyimpan/mengekspor menghasilkan <b>unduhan</b> ke perangkat Anda.
    Kalau layar terasa sempit, buka editor di
    <a href="{{ $editor }}" target="_blank" rel="noopener">tab tersendiri</a>.
    Pintasan papan tik baru aktif setelah Anda mengklik ke dalam editor.
  </p>
</section>
@endsection

{{--
  Editor ONLYOFFICE LAYAR PENUH — dibuka di tab tersendiri.

  Sengaja TIDAK @extends('layouts.utama'): justru kerangka itulah (bilah sisi,
  kepala, nav modul) yang membuat area editor sempit. Halaman ini hanya memuat
  satu dokumen selebar jendela.

  Konsekuensinya ia harus memuat sendiri semua yang dibutuhkan: CSS dasar,
  api.js ONLYOFFICE, config, dan skrip penyala editor. Tidak ada ketergantungan
  pada hub.css supaya halaman ini tetap ringan — hanya 30 baris gaya di bawah.

  Keamanan TIDAK berubah: `$config` disusun server lewat siapkanEditor() yang
  memeriksa izin yang sama persis dengan halaman biasa. Tab baru bukan jalan
  pintas; ia halaman yang sama dengan kerangka yang dilepas.
--}}
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
{{-- Halaman ini punya <head> sendiri (tidak memakai layout), jadi ikon harus
     ditulis ulang di sini — kalau tidak, tab editor tampil tanpa favicon. --}}
<link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="48x48" href="/img/favicon-48.png">
<link rel="icon" type="image/png" sizes="192x192" href="/img/favicon-192.png">
<link rel="apple-touch-icon" sizes="180x180" href="/img/apple-touch-icon.png">
<title>{{ $dok->judul }} — editor penuh</title>
<style>
  :root { --tinta:#132018; --garis:#dfe5e0; --hijau:#15402f; --pudar:#6b7c72; }
  * { box-sizing: border-box; }
  html, body { height:100%; margin:0; }
  body { font:14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
         color:var(--tinta); background:#f4f6f5; display:flex; flex-direction:column; }
  /* Bilah tipis di atas: judul + tombol kembali. Setinggi mungkin ditekan
     (44px) supaya editor dapat sisa layar sebanyak-banyaknya, tapi tetap
     menyisakan tempat untuk keluar — tab baru tanpa jalan pulang itu jebakan. */
  .bar { flex:0 0 auto; height:44px; display:flex; align-items:center; gap:.6rem;
         padding:0 .7rem; background:var(--hijau); color:#eaf3ee; }
  .bar b { font-size:.86rem; font-weight:600; white-space:nowrap; overflow:hidden;
           text-overflow:ellipsis; }
  .bar .kanan { margin-left:auto; display:flex; gap:.4rem; }
  .bar a { color:#eaf3ee; text-decoration:none; font-size:.82rem; padding:.3rem .55rem;
           border:1px solid rgba(255,255,255,.28); border-radius:.35rem; }
  .bar a:hover { background:rgba(255,255,255,.14); }
  /* Wadah editor. `min-height:0` WAJIB di dalam flex kolom: tanpa itu anak
     flex menolak menyusut di bawah tinggi isinya, dan iframe ONLYOFFICE (yang
     mengukur diri dari wadah) bisa berakhir 0px. `position:relative` memberi
     iframe acuan tata letak yang pasti. */
  #editor-penuh { flex:1 1 auto; min-height:0; position:relative; background:#fff; }
  /* ONLYOFFICE menyuntik iframe; pastikan ia mengisi wadah apa pun kondisinya. */
  #editor-penuh > iframe { display:block; width:100%; height:100%; border:0; }
  #galat { margin:1rem; padding:.8rem 1rem; border:1px solid #e5b4b4; border-radius:.4rem;
           background:#fdf2f2; color:#8a1c1c; }
</style>
</head>
<body>

<div class="bar">
  <b>{{ $dok->judul }}</b>
  <span class="kanan">
    <a href="{{ route('workspace.dokumen.editor', $dok->id) }}">Tampilan biasa</a>
    <a href="{{ route('workspace.dokumen') }}">Daftar dokumen</a>
  </span>
</div>

@if ($siap && $dok->bisaDisunting())
  <div id="editor-penuh" data-editor-onlyoffice></div>
  <script src="{{ $dasar }}/web-apps/apps/api/documents/api.js"></script>
  <script id="config-dokumen" type="application/json">{!! json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
  <script src="/js/hub-dokumen.js?v={{ filemtime(public_path('js/hub-dokumen.js')) }}" defer></script>
@else
  <div id="galat">
    <b>Editor tidak bisa dibuka di sini.</b>
    @if (! $siap)
      ONLYOFFICE belum tersambung (URL atau JWT kosong).
    @else
      Berkas ini tidak punya editor (mis. PDF).
    @endif
    <a href="{{ route('workspace.dokumen.editor', $dok->id) }}">Buka halaman biasa</a>.
  </div>
@endif

</body>
</html>

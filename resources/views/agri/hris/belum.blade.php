@extends('layouts.utama')
@section('judul', 'Belum tersambung')

@section('isi')
<div class="kepala">
  <h1>Akun AgriConnect belum tersambung</h1>
  <p class="tenang">Halaman ini membaca data langsung dari AgriConnect HRIS, jadi akun Anda perlu dihubungkan lebih dulu.</p>
</div>

@include('agri.hris._nav', ['mod' => ''])

<div class="blok">
  <p>Hubungkan akun sekali, dan seluruh halaman ini berisi data Anda — bukan halaman kosong.</p>
  <a class="tombol utama" href="{{ route('agri.hris.akun') }}">Masuk dengan akun AgriConnect</a>
</div>
@endsection

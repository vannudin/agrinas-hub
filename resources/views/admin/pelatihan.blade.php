@extends('layouts.utama')

@section('judul', 'Pelatihan')
@section('isi')
<header class="kepala">
  <div><h1>Pelatihan</h1>
    <p class="tenang">Materi pelatihan dari LMS Agrinas — tarik ulang, sunting, atau hapus.</p></div>
  <div class="tombol-baris">
    <form method="post" action="{{ route('admin.pelatihan.tarik') }}">
      @csrf<button class="tombol utama" type="submit">Tarik dari LMS</button>
    </form>
    <a class="tombol samar" href="{{ route('pelatihan') }}">Lihat sebagai manajer</a>
  </div>
</header>

<div class="petak">
  <div class="kartu angka"><small>Modul Tersalin</small><b>{{ $daftar->count() }}</b></div>
  <div class="kartu angka"><small>Jalur Belajar</small><b>{{ $jumlahPath }}</b></div>
  <div class="kartu angka"><small>Terakhir Ditarik</small><b style="font-size:1rem">{{ $terakhir ? \waktu_id($terakhir) : '—' }}</b></div>
</div>

<section class="blok">
  <h2>Modul Pelatihan
    <small class="pudar">{{ $daftar->count() }} modul · {{ $jumlahPath }} jalur</small>
  </h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Jalur / Modul</th><th>Durasi</th><th>Konten</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    @forelse ($daftar as $m)
      <tr>
        <td data-l="Jalur / Modul"><small class="pudar">{{ $m->path_judul }}</small>
          <span class="judul-sel">{{ $m->judul }}</span></td>
        <td data-l="Durasi">{{ $m->menit ? $m->menit.' menit' : '—' }}</td>
        <td data-l="Konten">{{ $m->konten !== '' ? mb_strlen($m->konten).' karakter' : 'kosong' }}</td>
        <td class="kolom-aksi">
          <a class="tombol samar mini" href="{{ route('pelatihan.baca', $m->lms_id) }}">Lihat</a>
          <details class="ubah">
            <summary class="tombol samar mini">Sunting</summary>
            <form method="post" action="{{ route('admin.pelatihan.simpan', $m->id) }}">
              @csrf
              <label>Judul <input name="judul" required maxlength="180" value="{{ $m->judul }}"></label>
              <label>Durasi (menit) <input type="number" name="menit" min="0" max="600" value="{{ $m->menit }}"></label>
              <label>Ringkasan <input name="ringkas" maxlength="1000" value="{{ $m->ringkas }}"></label>
              <label>Konten (HTML dari LMS) <textarea name="konten" rows="6">{{ $m->konten }}</textarea></label>
              <button class="tombol utama mini" type="submit">Simpan</button>
            </form>
          </details>
          <form method="post" action="{{ route('admin.pelatihan.hapus', $m->id) }}" class="sebaris"
                onsubmit="return confirm('Hapus salinan lokal modul ini? Modul tetap ada di LMS.')">
            @csrf<button class="tombol peringatan mini" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="4" class="kosong">Belum ada materi. Klik “Tarik dari LMS”.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</section>
@endsection

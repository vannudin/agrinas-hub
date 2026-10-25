@extends('layouts.utama')

@section('judul', 'Dashboard')
@section('isi')
@php $label = App\Models\Domain::LABEL; @endphp
<header class="kepala">
  <div><h1>Dashboard Koordinasi</h1><p class="tenang">Pantauan seluruh koperasi binaan.</p></div>
  <div class="tombol-baris">
    @if (Route::has('admin.ekspor'))
      <a class="tombol samar" href="{{ route('admin.ekspor') }}">Unduh Rekap CSV</a>
    @endif
    {{-- Pengawasan tidak punya Ruang Kerja (tidak bisa menulis berkas). --}}
    @if (Route::has('workspace') && ! auth()->user()?->pantau())
      <a class="tombol utama" href="{{ route('workspace') }}">Ruang Kerja</a>
    @endif
  </div>
</header>

{{-- Kartu ringkasan TIDAK diberi data-isi. hub.js hanya mendukung SATU wadah
     bertanda per halaman (`pencariWadah` memilih [data-isi] pertama), jadi
     menandai blok ini membuat penukar isi memilihnya dan daftar tabel tertimpa
     kartu. Angkanya pun dihitung dari SELURUH lingkup, bukan dari hasil saring,
     sehingga memang tidak perlu diperbarui. --}}
<div class="petak">
  <div class="kartu angka aksi-kuning"><small>Di Kerjakan</small><b class="t-kuning">{{ $stat['DIKERJAKAN'] ?? 0 }}</b></div>
  <div class="kartu angka aksi-merah"><small>Perlu Revisi</small><b class="t-merah">{{ $stat['REVISI'] ?? 0 }}</b></div>
  <div class="kartu angka aksi-merah"><small>Di Tolak</small><b class="t-merah">{{ $stat['DITOLAK'] ?? 0 }}</b></div>
  <div class="kartu angka aksi-kuning"><small>Terlambat</small><b class="t-kuning">{{ $stat['TERLAMBAT'] ?? 0 }}</b></div>
  <div class="kartu angka aksi-kuning"><small>Menunggu Verifikasi</small><b class="t-kuning">{{ $stat['DIAJUKAN'] ?? 0 }}</b></div>
  <div class="kartu angka aksi-hijau"><small>Selesai</small><b class="t-hijau">{{ $stat['SELESAI'] ?? 0 }}</b></div>
  <div class="kartu angka"><small>Koperasi</small><b>{{ $jumlahKoperasi }}</b></div>
</div>

@php
  // Label kartu barang diambil dari Distribusi::LABEL — dulu ditulis ulang di
  // sini ("Barang Dikirim"/"Barang Diterima") sehingga satu status punya DUA
  // nama di aplikasi yang sama, dan dasbor ini berbeda dari dasbor manajer.
  $kartuBarang = [['DIKIRIM', 'kuning'], ['DIAJUKAN', 'biru'], ['DIPROSES', 'biru'],
                  ['DITERIMA', 'hijau'], ['DITOLAK', 'merah']];
@endphp
<h2 class="petak-judul">Proses Barang</h2>
<div class="petak">
  @foreach ($kartuBarang as [$s, $warna])
    <div class="kartu angka aksi-{{ $warna }}">
      <small>{{ App\Models\Distribusi::label($s) }}</small>
      <b class="t-{{ $warna }}">{{ $statKirim[$s] ?? 0 }}</b>
    </div>
  @endforeach
</div>

{{-- Wadah AJAX. SATU wadah untuk dua daftar: satu permintaan mengganti keduanya,
     tetapi parameternya terpisah — hanya daftar yang parameternya berubah yang
     isinya bergeser. Kunci halamannya pun dipisah (`halaman` untuk tugas,
     `halaman_koperasi` untuk koperasi) supaya memindahkan satu daftar tidak
     menggeser daftar lain. Tanpa JS tetap jalan penuh: tautan & form biasa. --}}
<div id="daftar" data-isi="{{ route('admin') }}"
     data-params="q,status,halaman,qk,sk,halaman_koperasi">

  <section class="blok" id="koperasi-binaan">
    <h2>Koperasi Binaan
      <small class="pudar">{{ $halamanKoperasi->dari() }}–{{ $halamanKoperasi->sampai() }} dari {{ $halamanKoperasi->jumlah }} koperasi</small>
    </h2>
    <form class="saring" method="get" action="{{ route('admin') }}" data-saring>
      <label class="sr" for="qk">Cari koperasi</label>
      <input id="qk" name="qk" value="{{ $qk }}" placeholder="Cari nama, kode, wilayah, atau manajer…">
      <select name="sk" aria-label="Saring status koperasi">
        <option value="">Semua status</option>
        @foreach (App\Models\Koperasi::LABEL_STATUS as $k => $v)
          <option value="{{ $k }}" @selected($sk === $k)>{{ $v }}</option>
        @endforeach
      </select>
      <button class="tombol samar" type="submit">Filter</button>
      @if ($qk !== '' || $sk !== '')
        <a class="tombol samar" href="{{ route('admin') }}">Bersihkan</a>
      @endif
    </form>
    <div class="tabel-bungkus">
    <table class="tabel-koperasi" data-tabel="koperasi">
      <thead><tr><th>Koperasi</th><th>Wilayah</th><th>Manajer</th><th>Status</th><th class="kolom-aksi">Tugas</th></tr></thead>
      <tbody>
      @forelse ($koperasi as $k)
        <tr>
          {{-- Kode digabung ke kolom Koperasi: kode 5 karakter yang berdiri
               sendiri hanya menghabiskan 71px lebar tabel. --}}
          <td data-l="Koperasi"><span class="judul-sel">{{ $k->nama }}</span>
            <small class="pudar"><code>{{ $k->kode }}</code></small>
          </td>
          <td data-l="Wilayah">{{ $k->wilayah ?: '—' }}</td>
          <td data-l="Manajer">{{ $k->manajer ?: '—' }}
            @if ($k->manajer_email)<small class="pudar pecah">{{ $k->manajer_email }}</small>@endif
          </td>
          <td data-l="Status">
            <span class="lencana {{ $k->status === 'AKTIF' ? 's-SELESAI' : 's-REVISI' }}">{{ App\Models\Koperasi::LABEL_STATUS[$k->status] ?? $k->status }}</span>
          </td>
          <td data-l="Tugas" class="kolom-aksi">
            <span class="angka">{{ $k->jumlah_tugas }}</span>
            <small class="pudar">{{ $k->tugas_aktif }} aktif</small>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">
          @if ($qk !== '' || $sk !== '')
            Tidak ada koperasi yang cocok dengan saringan ini.
          @else
            Belum ada koperasi terdaftar.
          @endif
        </td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
    @include('partials.halaman', ['halaman' => $halamanKoperasi, 'ikon' => 'app', 'kunci' => 'halaman_koperasi'])
  </section>

  <section class="blok" id="perlu-tindakan">
    {{-- Peran pengawasan hanya membaca: menyebut "tindakan Anda" menjanjikan
         wewenang yang tidak ada (semua POST-nya 403). --}}
    <h2>{{ auth()->user()?->pantau() ? 'Tugas Dalam Pengawasan' : 'Perlu Tindakan Anda' }}
      <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }} tugas</small>
    </h2>
    <form class="saring" method="get" action="{{ route('admin') }}" data-saring>
      <label class="sr" for="q">Cari tugas</label>
      <input id="q" name="q" value="{{ $q }}" placeholder="Cari judul tugas atau nama koperasi…">
      <select name="status" aria-label="Saring status tugas">
        <option value="">Semua status</option>
        @foreach ($label as $k => $v)
          <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
        @endforeach
        <option value="TERLAMBAT" @selected($status === 'TERLAMBAT')>Terlambat</option>
      </select>
      <button class="tombol samar" type="submit">Filter</button>
      @if ($q !== '' || $status !== '')
        <a class="tombol samar" href="{{ route('admin') }}">Bersihkan</a>
      @endif
    </form>
    <div class="tabel-bungkus">
    <table class="tabel-tugas" data-tabel="tugas">
      <thead><tr><th>Tugas</th><th>Koperasi</th><th>Status</th><th>Tenggat</th><th class="kolom-aksi"></th></tr></thead>
      <tbody>
      @forelse ($tugas as $t)
        <tr>
          <td data-l="Tugas"><span class="judul-sel">{{ $t->judul }}</span>
            <small class="pudar">{{ ucfirst(mb_strtolower($t->jenis)) }} · {{ ucfirst(mb_strtolower($t->prioritas)) }}</small>
          </td>
          <td data-l="Koperasi">{{ $t->koperasi }}</td>
          <td data-l="Status"><span class="lencana s-{{ $t->status }}">{{ $label[$t->status] ?? $t->status }}</span></td>
          <td data-l="Tenggat">{{ tanggal_id($t->deadline) }}
            @if (\App\Http\Controllers\DasborController::terlambat($t))
              <span class="lencana s-REVISI">Terlambat</span>
            @endif
          </td>
          <td class="kolom-aksi">
            @if (Route::has('admin.tugas.detail'))
              <a class="tombol samar mini" href="{{ route('admin.tugas.detail', $t->id) }}">Buka</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="kosong">
          @if ($q !== '' || $status !== '')
            Tidak ada tugas yang cocok dengan saringan ini.
          @else
            Belum ada tugas.
          @endif
        </td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])
  </section>
</div>
@endsection

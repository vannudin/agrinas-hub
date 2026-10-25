@extends('layouts.utama')

@section('judul', 'Pemetaan Lahan')
@section('isi')
<header class="kepala">
  <div>
    <h1>Pemetaan Lahan KDKMP</h1>
    <p class="tenang">Titik lahan, status pembangunan, dan Babinsa penanggung jawab.
      @if ($lingkup !== '') <b>Lingkup pengawasan: {{ $lingkup }}</b> @endif
      @if (! empty($manajer)) Cari nama desa atau kecamatan koperasi Anda pada kotak
        pencarian untuk melihat proses pembangunan KDKMP di wilayah Anda. @endif</p>
  </div>
  @if (! empty($bolehTarik) && Route::has('admin.pemetaan.tarik'))
    <div class="tombol-baris">
      <form method="post" action="{{ route('admin.pemetaan.tarik') }}" class="sebaris">
        @csrf
        <button class="tombol samar" type="submit" title="Sinkronkan ulang dari portalkdkmp">Sinkronkan</button>
      </form>
    </div>
  @endif
</header>

{{-- Kartu ringkasan MENGIKUTI saringan: kalau pengguna menyaring Jawa Barat,
     pertanyaan yang sedang ditanyakan adalah "Jawa Barat", bukan Indonesia. --}}
<div class="petak">
  <div class="kartu angka"><small>Titik Lahan</small><b>{{ number_format($ringkas['total'], 0, ',', '.') }}</b>
    <small class="pudar">sesuai saringan</small></div>
  <div class="kartu angka aksi-hijau"><small>Selesai 100%</small><b class="t-hijau">{{ number_format($ringkas['selesai'], 0, ',', '.') }}</b></div>
  <div class="kartu angka aksi-kuning"><small>Sedang Dibangun</small><b class="t-kuning">{{ number_format($ringkas['jalan'], 0, ',', '.') }}</b></div>
  <div class="kartu angka"><small>Belum Mulai</small><b>{{ number_format($ringkas['belum'], 0, ',', '.') }}</b></div>
  <div class="kartu angka aksi-hijau"><small>Punya Nomor Babinsa</small><b class="t-hijau">{{ number_format($ringkas['telepon'], 0, ',', '.') }}</b>
    <small class="pudar">dari {{ number_format($ringkas['total'], 0, ',', '.') }} titik</small></div>
</div>

<section class="blok">
  <h2>Titik Pemetaan
    <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ number_format($halaman->jumlah, 0, ',', '.') }}</small>
  </h2>

  {{-- Satu form saring; `data-otomatis` mengirim sendiri (ketik ditunda 400 ms,
       pilih langsung). Saringan wilayah BERTINGKAT: pilihan kabupaten disaring
       oleh provinsi terpilih, kecamatan oleh kabupaten, dst — semuanya dirender
       server, jadi tidak perlu JavaScript sendiri. --}}
  <form class="saring" method="get" action="{{ route($rute) }}" data-otomatis>
    <label class="cari">
      <span class="sr">Cari</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $q }}" autocomplete="off"
             placeholder="Cari nama Babinsa, nomor, desa, atau potensi…">
    </label>
    {{-- Saringan wilayah BERTINGKAT hanya untuk pengguna tanpa lingkup tetap.
         Peran pengawasan sudah dikunci ke PUSAT/provinsi/kabupatennya sendiri,
         jadi menampilkan pilihan provinsi lain hanya menghasilkan nol baris. --}}
    @if ($lingkup === '')
    <label class="pilih">
      <span class="sr">Provinsi</span>
      <select name="provinsi">
        <option value="">Semua provinsi</option>
        @foreach ($pilihan['provinsi'] as $p)
          <option value="{{ $p }}" @selected($provinsi === $p)>{{ $p }}</option>
        @endforeach
      </select>
    </label>
    <label class="pilih">
      <span class="sr">Kabupaten/Kota</span>
      <select name="kabupaten" @disabled($kabupaten === '' && $provinsi === '')>
        <option value="">{{ $provinsi === '' ? 'Pilih provinsi dulu' : 'Semua kabupaten' }}</option>
        @foreach ($pilihan['kabupaten'] as $p)
          <option value="{{ $p }}" @selected($kabupaten === $p)>{{ $p }}</option>
        @endforeach
      </select>
    </label>
    <label class="pilih">
      <span class="sr">Kecamatan</span>
      <select name="kecamatan" @disabled($kecamatan === '' && $kabupaten === '')>
        <option value="">{{ $kabupaten === '' ? 'Pilih kabupaten dulu' : 'Semua kecamatan' }}</option>
        @foreach ($pilihan['kecamatan'] as $p)
          <option value="{{ $p }}" @selected($kecamatan === $p)>{{ $p }}</option>
        @endforeach
      </select>
    </label>
    <label class="pilih">
      <span class="sr">Desa/Kelurahan</span>
      <select name="desa" @disabled($desa === '' && $kecamatan === '')>
        <option value="">{{ $kecamatan === '' ? 'Pilih kecamatan dulu' : 'Semua desa' }}</option>
        @foreach ($pilihan['desa'] as $p)
          <option value="{{ $p }}" @selected($desa === $p)>{{ $p }}</option>
        @endforeach
      </select>
    </label>
    @endif
    <label class="pilih">
      <span class="sr">Status pembangunan</span>
      <select name="pembangunan">
        <option value="">Semua status</option>
        <option value="selesai" @selected($pembangunan === 'selesai')>Selesai 100%</option>
        <option value="jalan" @selected($pembangunan === 'jalan')>Sedang dibangun</option>
        <option value="belum" @selected($pembangunan === 'belum')>Belum mulai</option>
      </select>
    </label>
    <label class="pilih">
      <span class="sr">Urutkan</span>
      <select name="urut">
        <option value="terbaru" @selected($urut === 'terbaru')>Terbaru dulu</option>
        <option value="luas" @selected($urut === 'luas')>Luas terbesar</option>
        <option value="nama" @selected($urut === 'nama')>Nama Babinsa</option>
      </select>
    </label>
    <a class="tombol samar bersih-saring" href="{{ route($rute) }}"
       @if ($q === '' && $provinsi === '' && $kabupaten === '' && $kecamatan === '' && $desa === '' && $pembangunan === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="tabel-bungkus">
  <table class="tabel-pemetaan">
    <thead><tr>
      <th>Lokasi</th><th>Babinsa Penanggung Jawab</th><th>Lahan &amp; Potensi</th>
      <th>Pembangunan</th>
    </tr></thead>
    <tbody>
    @forelse ($baris as $b)
      {{-- EMPAT sel, tanpa kolom aksi tersendiri: di ponsel `.tabel-bungkus`
           mengubah tiap sel jadi label+nilai, jadi jumlah sel = tinggi baris.
           Enam sel → baris 404–423px (satu titik menelan satu layar); sel aksi
           sendirian menyumbang ~81px hanya untuk satu tombol. Tautan peta
           dipindah ke dalam sel Lokasi — informasi sama, satu sel lebih sedikit
           (terukur turun ke ± 320px). --}}
      <tr>
        <td data-l="Lokasi">
          <span class="judul-sel">{{ $b->desa ?: '—' }}</span>
          <small class="pudar">{{ collect([$b->kecamatan, $b->kabupaten, $b->provinsi])->filter()->implode(' · ') }}</small>
          @if ($b->adaTitik())
            <small><a href="{{ $b->tautanPeta() }}" target="_blank" rel="noopener"
                      title="Buka {{ koordinat_mentah((float) $b->latitude, (float) $b->longitude) }} di Google Maps"
               >Buka di Google Maps</a></small>
          @endif
        </td>
        <td data-l="Babinsa Penanggung Jawab">
          <span class="judul-sel">{{ $b->nama ?: '—' }}</span>
          @if ($b->teleponLokal() !== '')
            <small class="pudar">
              <a href="tel:{{ $b->teleponLokal() }}">{{ $b->teleponLokal() }}</a>
              · <a href="https://wa.me/{{ preg_replace('/\D/', '', $b->telepon) }}" target="_blank" rel="noopener">WhatsApp</a>
            </small>
          @else
            <small class="pudar">nomor belum ada di sumber</small>
          @endif
        </td>
        <td data-l="Lahan &amp; Potensi">
          <span class="judul-sel">{{ $b->luasTeks() }}</span>
          <small class="pudar">{{ $b->potensi ?: 'potensi belum diisi' }}</small>
        </td>
        <td data-l="Pembangunan">
          <span class="lencana s-{{ $b->statusKunci() }}">{{ $b->statusPembangunan() }}</span>
        </td>
      </tr>
    @empty
      <tr><td colspan="4" class="kosong">
        @if ($halaman->jumlah === 0 && $q === '' && $provinsi === '' && $pembangunan === '')
          Belum ada data pemetaan. Jalankan <code>php artisan pemetaan:tarik</code> untuk menariknya.
        @else
          Tidak ada titik yang cocok dengan saringan ini.
        @endif
      </td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>

<p class="bantuan">
  Sumber: pemetaan lahan KDKMP (portalkdkmp) — data publik, disalin agar bisa
  dicari &amp; disaring seketika. Status pembangunan diturunkan dari dua penanda
  sumber (selesai 100% / sedang dibangun); sisanya berarti belum mulai.
</p>
@endsection

@extends('layouts.utama')

@section('judul', 'Pesan Barang')
@section('isi')
<header class="kepala">
  <div>
    <h1>Pesan Barang</h1>
    <p class="tenang">
      Pilih barang dan jumlahnya, lalu kirim pesanan ke Agrinas saat sudah lengkap.
      Selama belum dikirim, daftar ini hanya tersimpan sebagai <b>draf</b> — belum
      menjadi permintaan resmi.
    </p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('distribusi.stok-koperasi') }}">Pilih Barang Lain</a>
    <a class="tombol samar" href="{{ route('distribusi.daftar') }}">Kembali</a>
  </div>
</header>

@if ($draf['jumlah'] > 0)
  <section class="blok">
    <h2>Draf Pesanan
      <small class="pudar">{{ $draf['jumlah'] }} barang — belum dikirim</small>
    </h2>
    {{-- Satu formulir untuk seluruh draf: "ganti=1" membuat isinya menggantikan
         draf seutuhnya, jadi baris yang dijadikan 0 ikut terhapus. --}}
    <form method="post" action="{{ route('distribusi.draf') }}">
      @csrf
      <input type="hidden" name="ganti" value="1">
      <div class="tabel-bungkus">
        <table>
          <thead><tr><th>Barang</th><th>Satuan</th><th>Stok Gudang</th><th class="kolom-aksi">Jumlah Pesan</th></tr></thead>
          <tbody>
          @foreach ($draf['baris'] as $b)
            <tr>
              <td data-l="Barang"><span class="judul-sel">{{ $b['nama'] }}</span><small class="pudar">{{ $b['kode'] }} · {{ $b['kategori'] }}</small></td>
              <td data-l="Satuan">{{ $b['satuan'] }}</td>
              <td data-l="Stok Gudang">{{ number_format($b['stok'], 0, ',', '.') }}</td>
              <td class="kolom-aksi" data-l="Jumlah Pesan">
                <input type="number" name="item[{{ $b['id'] }}]" value="{{ $b['jumlah'] }}"
                       min="0" max="1000000" step="1" inputmode="numeric"
                       aria-label="Jumlah {{ $b['nama'] }} dalam {{ $b['satuan'] }}">
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <p class="bantuan">Isi 0 untuk membuang satu barang dari draf.</p>
      <div class="tombol-baris">
        <button class="tombol samar" type="submit">Simpan Draf</button>
      </div>
    </form>

    <form method="post" action="{{ route('distribusi.ajukan') }}" class="tumpuk">
      @csrf
      {{-- Tanpa `item`: server mengambil isi DRAF dari sesi, jadi angka yang
           dikirim selalu sama dengan yang tampak di tabel draf. --}}
      <label>Catatan untuk Agrinas <span class="bantuan">(opsional — mis. kebutuhan waktu tanam)</span>
        <textarea name="catatan" rows="2" maxlength="500" placeholder="Contoh: dibutuhkan sebelum masa tanam."></textarea>
      </label>
      <div class="tombol-baris">
        <button class="tombol utama" type="submit">Kirim Pesanan ke Agrinas</button>
      </div>
      <p class="bantuan">Setelah dikirim, pesanan masuk sebagai permintaan resmi dan draf dikosongkan.</p>
    </form>

    <form method="post" action="{{ route('distribusi.draf.hapus') }}">
      @csrf
      <input type="hidden" name="id" value="0">
      <button class="tombol samar" type="submit"
              onclick="return confirm('Kosongkan seluruh draf pesanan?')">Kosongkan Draf</button>
    </form>
  </section>
@endif

<section class="blok">
  <h2>Katalog Barang
    @if ($pilih->isNotEmpty())<small class="pudar">{{ $pilih->count() }} barang dipilih</small>@endif
  </h2>
  <p class="bantuan">Isi jumlah, lalu simpan ke draf. Barang yang diisi 0 tidak disimpan.</p>
  <form method="post" action="{{ route('distribusi.draf') }}">
    @csrf
    <div class="tabel-bungkus">
      <table>
        <thead><tr><th>Barang</th><th>Kategori</th><th>Satuan</th><th>Stok Gudang</th><th class="kolom-aksi">Jumlah</th></tr></thead>
        <tbody>
        @forelse ($barang as $b)
          <tr>
            <td data-l="Barang"><span class="judul-sel">{{ $b->nama }}</span><small class="pudar">{{ $b->kode }}</small></td>
            <td data-l="Kategori"><span class="lencana s-DIAJUKAN">{{ \App\Models\Barang::KATEGORI[$b->kategori] ?? $b->kategori }}</span></td>
            <td data-l="Satuan">{{ $b->satuan }}</td>
            <td data-l="Stok Gudang">{{ number_format($b->stok, 0, ',', '.') }}</td>
            <td class="kolom-aksi" data-l="Jumlah">
              <input type="number" name="item[{{ $b->id }}]" min="0" max="1000000" value="0"
                     step="1" inputmode="numeric" aria-label="Jumlah {{ $b->nama }} dalam {{ $b->satuan }}">
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="kosong">Belum ada jenis barang di gudang Agrinas. Hubungi admin.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
    <button class="tombol utama" type="submit" @disabled($barang->isEmpty())>Simpan ke Draf</button>
  </form>
</section>
@endsection

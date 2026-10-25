{{--
  Isian satu gerai/outlet. Dipakai dua keadaan (TAMBAH & UBAH) supaya tidak ada
  dua formulir yang bisa menyimpang — pola yang sama dengan `_form-data`.

  Jenis & status memakai <select> bawaan, bukan <input list> + <datalist>:
  datalist dirender sekali per formulir, jadi begitu koperasi punya beberapa
  gerai ada BEBERAPA <datalist id="…"> kembar dalam satu halaman dan pilihannya
  tidak mau terbuka. <select> tidak punya masalah itu dan tetap bekerja tanpa
  JavaScript. Nilai lama yang tidak ada di daftar tetap ditampilkan (opsi
  tambahan di bawah) supaya menyunting gerai lama tidak menghapus isinya.

  Param: $aksi, $gerai (?KoperasiGerai), $tombol, $kembali ('data'|'sarana')
--}}
@php
  $pilihanJenis = ['Toko', 'Kios', 'Gudang', 'Unit Usaha', 'Pos Layanan', 'Lainnya'];
  $pilihanStatus = ['Aktif', 'Tutup', 'Rencana'];
  $nilaiJenis = old('jenis', $gerai->jenis ?? '');
  $nilaiStatus = old('status', $gerai->status ?? '');
  // Nilai lama di luar daftar tetap bisa dipilih ulang.
  if ($nilaiJenis !== '' && ! in_array($nilaiJenis, $pilihanJenis, true)) {
      $pilihanJenis[] = $nilaiJenis;
  }
  if ($nilaiStatus !== '' && ! in_array($nilaiStatus, $pilihanStatus, true)) {
      $pilihanStatus[] = $nilaiStatus;
  }
@endphp

<form method="post" action="{{ $aksi }}" class="grid-form">
  @csrf
  <input type="hidden" name="kembali" value="{{ $kembali }}">

  <label>Nama Gerai
    <input name="nama" required minlength="2" maxlength="120"
           value="{{ old('nama', $gerai->nama ?? '') }}" placeholder="Gerai Desa Sukamaju">
  </label>

  <label>Jenis
    <select name="jenis">
      <option value="">— pilih jenis —</option>
      @foreach ($pilihanJenis as $j)
        <option value="{{ $j }}" @selected($nilaiJenis === $j)>{{ $j }}</option>
      @endforeach
    </select>
  </label>

  <label>Telepon
    <input name="telepon" maxlength="40" value="{{ old('telepon', $gerai->telepon ?? '') }}"
           placeholder="08…">
  </label>

  <label>Status
    <select name="status">
      <option value="">— pilih status —</option>
      @foreach ($pilihanStatus as $s)
        <option value="{{ $s }}" @selected($nilaiStatus === $s)>{{ $s }}</option>
      @endforeach
    </select>
  </label>

  <label class="lebar-penuh">Alamat
    <input name="alamat" maxlength="255" value="{{ old('alamat', $gerai->alamat ?? '') }}">
  </label>

  <div class="lebar-penuh tombol-baris">
    <button class="tombol utama" type="submit">{{ $tombol }}</button>
    <button class="tombol samar" type="button" data-tutup>Batal</button>
  </div>
</form>

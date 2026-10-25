{{--
  Isian satu karyawan. Satu naskah untuk TAMBAH & UBAH — pola yang sama dengan
  `_form-gerai`.

  Jabatan memakai <select> + opsi "lainnya" berupa isian bebas? Tidak: <select>
  biasa saja, karena daftar jabatan koperasi pendek dan nilai lama di luar daftar
  tetap ditampilkan (ditambahkan ke pilihan) supaya menyunting tidak menghapus isi.

  Param: $aksi, $karyawan (?KoperasiKaryawan), $tombol, $jabatan (array)
--}}
@php
  $pilihan = $jabatan;
  $nilaiJabatan = old('jabatan', $karyawan->jabatan ?? '');
  if ($nilaiJabatan !== '' && ! in_array($nilaiJabatan, $pilihan, true)) {
      $pilihan[] = $nilaiJabatan;
  }
@endphp

<form method="post" action="{{ $aksi }}" class="grid-form">
  @csrf
  <label>Nama Karyawan
    <input name="nama" required minlength="2" maxlength="120"
           value="{{ old('nama', $karyawan->nama ?? '') }}" placeholder="Nama lengkap">
  </label>

  <label>Jabatan
    <select name="jabatan">
      <option value="">— pilih jabatan —</option>
      @foreach ($pilihan as $j)
        <option value="{{ $j }}" @selected($nilaiJabatan === $j)>{{ $j }}</option>
      @endforeach
    </select>
  </label>

  <label>Telepon
    <input name="telepon" maxlength="40" value="{{ old('telepon', $karyawan->telepon ?? '') }}"
           placeholder="08…">
  </label>

  <label>Tanggal Masuk
    <input type="date" name="tanggal_masuk"
           value="{{ old('tanggal_masuk', $karyawan?->tanggal_masuk?->format('Y-m-d') ?? '') }}">
  </label>

  <label>Status
    <select name="aktif">
      <option value="1" @selected((int) old('aktif', $karyawan->aktif ?? 1) === 1)>Aktif</option>
      <option value="0" @selected((int) old('aktif', $karyawan->aktif ?? 1) === 0)>Tidak aktif</option>
    </select>
  </label>

  <label class="lebar-penuh">Catatan
    <input name="catatan" maxlength="200" value="{{ old('catatan', $karyawan->catatan ?? '') }}"
           placeholder="mis. shift pagi, merangkap kasir">
  </label>

  <div class="lebar-penuh tombol-baris">
    <button class="tombol utama" type="submit">{{ $tombol }}</button>
    <button class="tombol samar" type="button" data-tutup>Batal</button>
  </div>
</form>

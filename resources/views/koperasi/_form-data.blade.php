{{--
  Formulir identitas koperasi — SATU naskah untuk dua keadaan:
    · TAMBAH (manajer belum punya koperasi) → `kop.simpan`, tampil sebagai blok
    · UBAH   (dialog di /kop)               → `kop.ubah`, di dalam <dialog>
  Kolomnya sama persis, jadi tidak ada dua formulir yang bisa menyimpang.

  Desa WAJIB dipilih dari daftar pemetaan lahan (Select2 AJAX atas 38.861
  titik): nama desa/kecamatan tidak diketik ulang, dan pilihannya DISIMPAN di
  `koperasi.pemetaan_lahan_id` sehingga tidak perlu ditebak dari kemiripan nama.

  Param: $aksi, $idSelect, $nilai[nama,wilayah,provinsi,alamat,no_rekening],
         $lahanAwal (?array id/text/nama/peta/koordinat), $denganPkwt (bool),
         $wajibLahan (bool), $namaTerkunci (bool), $tombol, $batal (bool)
--}}
@php
  // Nama & titik peta diturunkan dari desa yang dipilih, bukan diketik: satu
  // sumber (pemetaan lahan), jadi tidak ada dua ejaan untuk desa yang sama.
  $peta = $lahanAwal['peta'] ?? '';
  $koordinat = $lahanAwal['koordinat'] ?? '';
@endphp
<form method="post" action="{{ $aksi }}" class="grid-form">
  @csrf

  <label class="lebar-penuh">Desa pada Pemetaan Lahan
    {{-- `required` hanya saat MENAMBAH: koperasi lama (dibuat sebelum tautan
         pemetaan lahan ada) tetap bisa dibetulkan nama/alamatnya tanpa dipaksa
         memilih desa lebih dulu. --}}
    <select class="pilih2" name="pemetaan_lahan_id" id="{{ $idSelect }}"
            @if ($wajibLahan) required @endif>
      @if ($lahanAwal)
        <option value="{{ $lahanAwal['id'] }}" selected>{{ $lahanAwal['text'] }}</option>
      @endif
    </select>
    <small class="pudar">Ketik minimal 3 huruf: nama desa, kecamatan, atau kabupaten.</small>
  </label>

  <label>Nama Koperasi
    <input name="nama" required minlength="4" maxlength="160"
           @if (! empty($namaTerkunci)) readonly @endif
           value="{{ $nilai['nama'] }}" placeholder="Terisi otomatis dari desa">
  </label>

  <label>Titik Lokasi Maps
    <input readonly data-peta value="{{ $koordinat }}" placeholder="Terisi otomatis dari desa">
    <small class="pudar">
      <a href="{{ $peta }}" target="_blank" rel="noopener"
         data-peta-tautan @if (! $peta) hidden @endif>Buka di Google Maps</a>
    </small>
  </label>

  <label>Wilayah
    <input name="wilayah" maxlength="120" value="{{ $nilai['wilayah'] }}"
           placeholder="Desa …, Kec. …, Kab. …">
  </label>

  <label>Provinsi
    <input name="provinsi" maxlength="80" value="{{ $nilai['provinsi'] }}">
  </label>

  <label>No. Rekening (opsional)
    <input name="no_rekening" maxlength="60" value="{{ $nilai['no_rekening'] }}">
  </label>

  <label class="lebar-penuh">Alamat (opsional)
    <input name="alamat" maxlength="255" value="{{ $nilai['alamat'] }}">
  </label>

  @if (! empty($denganPkwt))
    <label class="lebar-penuh">
      <input type="checkbox" name="pkwt" value="1" required>
      Data ini sesuai PKWT yang sudah saya tanda tangani.
    </label>
  @endif

  <div class="lebar-penuh tombol-baris">
    <button class="tombol utama" type="submit">{{ $tombol }}</button>
    @if (! empty($batal))
      <button class="tombol samar" type="button" data-tutup>Batal</button>
    @endif
  </div>
</form>

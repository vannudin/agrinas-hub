{{--
  Alat scan barcode — SATU definisi untuk dua halaman (Stok Barang & POS).

  Dipisahkan ke partial karena markup-nya identik panjang sementara dua halaman
  memakainya: menyalinnya berarti dua tempat yang harus diubah bersama setiap
  kali alatnya diperbaiki, dan cepat atau lambat keduanya menyimpang.

  Kontrak `data-*` (dibaca public/js/hub-stok-koperasi.js):
   · data-cari      : endpoint pencarian AJAX (opsional). Halaman yang hanya
                      ingin MENYARING (POS) tidak mengirimnya.
   · data-scan-*    : diisi oleh tombol PEMBUKA, bukan di sini. `data-scan-untuk`
                      menunjuk id elemen tujuan dan `data-scan-isi` nama kolomnya;
                      bila tujuannya <form>, tambahkan `data-scan-kirim` supaya
                      formulirnya langsung dikirim setelah terisi.

  Barcode itu pelengkap, BUKAN syarat: ada jalan manual di dalamnya sehingga
  tetap berfungsi di perangkat yang tidak bisa memberi izin kamera (mis. dibuka
  lewat http:// alamat LAN — kamera memang mustahil di sana).
--}}
<dialog class="modal" id="scan-modal" aria-labelledby="scan-modal-judul"
        @if (! empty($scanCari)) data-cari="{{ $scanCari }}" @endif>
  <header class="modal-kepala">
    <h2 id="scan-modal-judul">Scan Barcode</h2>
    <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
  </header>
  <div class="modal-badan">
    <div class="scan-bingkai" data-scan-bingkai hidden>
      <video data-scan-video playsinline muted></video>
      <span class="scan-garis" aria-hidden="true"></span>
    </div>
    <p class="bantuan" data-scan-pesan>{{ $scanPesan ?? 'Arahkan kamera ke barcode produk.' }}</p>
    <label>Atau ketik kode barcode
      <input data-scan-manual inputmode="numeric" autocomplete="off" placeholder="Ketik angka barcode lalu Enter"></label>
    <div data-scan-hasil></div>
  </div>
  <footer class="modal-kaki">
    <button class="tombol samar" type="button" data-scan-ulang>Scan ulang</button>
    <button class="tombol samar" type="button" data-tutup>Tutup</button>
  </footer>
</dialog>

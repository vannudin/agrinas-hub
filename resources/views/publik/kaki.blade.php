<?php /* Kaki situs — dipakai beranda dan semua halaman publik lain
   supaya logo & tata letaknya tidak pernah berbeda antar halaman. */ ?>
<footer class="pub-kaki" id="kontak">
  <div class="pub-kaki-grid">
    <div>
      {{-- Di kaki gelap lockup putih terbaca jelas, tapi logo berwarna hilang —
           jadi tiap logo diberi alas putih supaya warna aslinya dipertahankan. --}}
      <span class="kaki-logo">
        <img src="/img/logo-danantara-kecil.png" alt="Danantara Indonesia" width="420" height="105">
        <span class="kaki-pemisah" aria-hidden="true"></span>
        <img src="/img/logo-agrinas-kecil.png" alt="Agrinas Pangan Nusantara" width="160" height="76">
      </span>
      <p class="pub-slogan">Agricultural sector &amp; engineering consultant</p>
    </div>
    <div>
      <h3>Kantor pusat</h3>
      <p>Jl. D. I. Panjaitan Kav. 8, Cawang<br>Jakarta Timur 13340, Indonesia</p>
      <p>Telepon (021) 8193951, 8194038<br>Faksimili (021) 8193519</p>
    </div>
    <div>
      <h3>Navigasi</h3>
      <ul>
        <li><a href="/#tentang">Tentang perusahaan</a></li>
        <li><a href="/#lini-bisnis">Lini bisnis</a></li>
        <li><a href="/#mandat">Mandat KDKMP</a></li>
        <li><a href="/#direksi">Jajaran direksi</a></li>
        <li><a href="{{ route('artikel') }}">Pusat informasi</a></li>
        <li><a href="/#layanan">Agrinas Hub</a></li>
      </ul>
    </div>
    <div>
      <h3>Sertifikasi</h3>
      <ul class="pub-tanpa-tanda">
        <li>ISO 9001:2015</li>
        <li>ISO 14001:2015</li>
        <li>ISO 37001:2016</li>
        <li>ISO 45001:2018</li>
      </ul>
    </div>
  </div>
  <div class="pub-kaki-bawah">
    <span>&copy; 2026 PT Agrinas Pangan Nusantara (Persero). Seluruh hak cipta dilindungi.</span>
    <span class="pub-kaki-hub">Petani Sejahtera, Negara Berdaulat.</span>
  </div>
</footer>

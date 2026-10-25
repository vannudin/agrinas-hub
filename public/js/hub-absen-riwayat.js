/*
 * Riwayat absen: ganti bulan/tahun dan pindah halaman TANPA memuat ulang.
 *
 * Server yang merender HTML-nya (lihat AgriAbsenHubController::riwayat), jadi
 * format tanggal, nama hari, dan hitungan durasi tetap satu sumber. JS di sini
 * hanya: kirim permintaan, tukar isi, perbarui URL.
 *
 * Tanpa JavaScript formulir tetap bekerja sebagai GET biasa dan paging memakai
 * tautan sungguhan — karena itu setiap tombol halaman tetap `<a href>`.
 */
(function () {
  'use strict';

  var akar = document.querySelector('[data-riwayat]');
  if (!akar || !window.fetch) return;

  var url = akar.getAttribute('data-url');
  var isi = akar.querySelector('[data-riwayat-isi]');
  var form = akar.querySelector('[data-riwayat-saring]');
  if (!isi || !url) return;

  var sedang = false;

  function bulanNilai() {
    if (!form) return '';
    var th = form.querySelector('[name="tahun"]');
    var bl = form.querySelector('[name="bln"]');
    if (!th || !bl || !th.value || !bl.value) return '';
    return th.value + '-' + (bl.value.length === 1 ? '0' + bl.value : bl.value);
  }

  function muat(halaman, perbaruiUrl) {
    if (sedang) return;
    sedang = true;
    isi.setAttribute('aria-busy', 'true');

    var u = new URL(url, window.location.origin);
    var b = bulanNilai();
    if (b) u.searchParams.set('bulan', b);
    u.searchParams.set('halaman', String(halaman || 1));

    fetch(u.toString(), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (r) {
      return r.ok ? r.json() : null;
    }).then(function (j) {
      if (!j || !j.ok) return;
      isi.innerHTML = j.html;
      if (perbaruiUrl && window.history && history.replaceState) {
        history.replaceState(null, '', j.url);
      }
      // Gulir ke awal daftar: setelah pindah halaman, pengguna harus mulai dari
      // baris pertama, bukan dari tengah daftar yang barusan berganti isi.
      if (akar.scrollIntoView) {
        akar.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }).catch(function () {
      /* Jaringan gagal: biarkan isi lama. Tautan paging tetap bisa diklik
         sebagai permintaan GET biasa, jadi datanya tidak pernah hilang. */
    }).finally(function () {
      sedang = false;
      isi.setAttribute('aria-busy', 'false');
    });
  }

  if (form) {
    form.addEventListener('change', function (ev) {
      if (!ev.target.closest('[name="tahun"], [name="bln"]')) return;
      muat(1, true);
    });
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      muat(1, true);
    });
  }

  akar.addEventListener('click', function (ev) {
    var a = ev.target.closest('[data-halaman]');
    if (!a) return;
    if (a.getAttribute('aria-hidden') === 'true') return;
    ev.preventDefault();
    muat(parseInt(a.getAttribute('data-halaman'), 10) || 1, true);
  });
})();


/* ---------------------------------------------------------------- modal foto
 * Tombol "Foto" pada riwayat membuka SATU modal berisi dua bukti: absen masuk
 * dan absen pulang. Event delegation dipakai (bukan addEventListener per
 * tombol) karena isi riwayat diganti lewat AJAX saat pindah halaman — tombol
 * yang baru dibuat tidak akan punya penangan bila dipasang satu per satu.
 */
(function () {
  'use strict';

  var dlg = document.getElementById('modal-foto-absen');
  if (!dlg || typeof dlg.showModal !== 'function') return;

  var elTanggal = dlg.querySelector('[data-foto-tanggal]');
  var imgMasuk = dlg.querySelector('[data-foto-img-masuk]');
  var imgKeluar = dlg.querySelector('[data-foto-img-keluar]');
  var kosongMasuk = dlg.querySelector('[data-foto-kosong-masuk]');
  var kosongKeluar = dlg.querySelector('[data-foto-kosong-keluar]');
  var jamMasuk = dlg.querySelector('[data-foto-jam-masuk]');
  var jamKeluar = dlg.querySelector('[data-foto-jam-keluar]');

  function isi(fotoEl, kosongEl, jamEl, url, jam, label) {
    if (jamEl) jamEl.textContent = jam ? 'pukul ' + jam : '';
    if (url) {
      fotoEl.hidden = false;
      fotoEl.alt = 'Foto absen ' + label;
      fotoEl.src = url;
      if (kosongEl) kosongEl.hidden = true;
    } else {
      fotoEl.hidden = true;
      fotoEl.removeAttribute('src');
      if (kosongEl) kosongEl.hidden = false;
    }
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-foto-absen]') : null;
    if (!t) return;
    ev.preventDefault();

    if (elTanggal) elTanggal.textContent = t.getAttribute('data-tanggal') || '';
    isi(imgMasuk, kosongMasuk, jamMasuk,
        t.getAttribute('data-masuk'), t.getAttribute('data-masuk-jam'), 'masuk');
    isi(imgKeluar, kosongKeluar, jamKeluar,
        t.getAttribute('data-keluar'), t.getAttribute('data-keluar-jam'), 'pulang');

    if (!dlg.open) dlg.showModal();
  });

  // Klik di area gelap (backdrop) menutup modal. `ev.target` untuk klik backdrop
  // ADALAH dialog itu sendiri, jadi yang diperiksa bukan "di luar kotak".
  dlg.addEventListener('click', function (ev) {
    if (ev.target === dlg) dlg.close();
  });
})();

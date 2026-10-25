/* Katalog toko: gulir-tak-berujung.
 *
 * Etalase menambah barang saat digulir; tidak ada klik nomor halaman yang
 * memutus alur menelusuri. Penanda "muat lagi" di bawah daftar diamati: begitu
 * mendekati layar, halaman berikutnya diambil — dan potongan HTML-nya
 * DISISIPKAN ke bawah kisi, bukan menukar isi (itu yang membuat gulir tetap
 * di tempatnya).
 *
 * Halaman /toko punya DUA kisi berpenanda: barang gudang Agrinas dan Produk
 * Lokal & UMKM. Karena itu seluruh berkas ini bekerja atas SETIAP penanda
 * (bukan hanya yang pertama ditemukan): penanda gudang menggulir kisi gudang,
 * penanda lokal menggulir kisi lokal. Kalau hanya satu yang diamati, menggulir
 * kisi kedua tidak melakukan apa pun dan tombol "Muat lagi"-nya menarik
 * halaman kisi yang salah.
 *
 * Tanpa JS tetap hidup: penanda itu <a href> halaman berikutnya yang benar.
 *
 * Kenapa tidak memakai [data-isi] yang sudah ada di hub.js: mekanisme itu
 * MENUKAR isi wadah (benar untuk tabel ber-paging seperti Kelola Katalog).
 * Untuk gulir kita perlu MENAMBAH, dan setelah menambah, penanda di dalam
 * potongan lama digantikan milik potongan baru.
 */
(function () {
  function pasang(awal) {
    if (!awal || awal.dataset.gulirSiap) return;
    awal.dataset.gulirSiap = '1';

    // Kisi yang digulir = kisi TERDEKAT SEBELUM penanda ini. Dengan begitu
    // penanda lokal tidak pernah menyisipkan kartu ke kisi gudang.
    var kisi = null;
    for (var el = awal.previousElementSibling; el; el = el.previousElementSibling) {
      if (el.classList && el.classList.contains('katalog-kisi')) { kisi = el; break; }
    }
    if (!kisi) return;

    var penanda = awal;
    var sedang = false, habis = false;

    /**
     * Kunci anti-ganda: satu produk hanya boleh muncul SEKALI. Kuncinya
     * `[data-kunci]` (asal:id) — produk lokal tidak punya baris `barang`,
     * sehingga memakai `barang_id` membuat SEMUA produk lokal dianggap barang
     * yang sama dan hanya yang pertama tersisip.
     */
    function kunci(k) {
      var u = k.getAttribute('data-kunci');
      if (u) return u;
      var i = k.querySelector('form input[name=barang_id], form input[name=pasokan_id]');
      return i ? i.name + ':' + i.value : '';
    }

    function ambil() {
      if (sedang || habis || !penanda) return;
      var a = penanda.querySelector('a[data-url]');
      if (!a) return;
      sedang = true;
      penanda.classList.add('memuat');

      fetch(a.getAttribute('data-url'), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
        .then(function (r) {
          if (r.redirected || r.status === 401 || r.status === 403) {
            // Sesi habis / tidak berhak: jatuh ke navigasi biasa, jangan diam.
            window.location.href = a.getAttribute('data-url');
            return '';
          }
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.text();
        })
        .then(function (html) {
          if (!html) return;
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var kartuBaru = doc.querySelectorAll('.kat-kartu');

          var ada = {};
          kisi.querySelectorAll('.kat-kartu').forEach(function (k) {
            var s = kunci(k);
            if (s) ada[s] = 1;
          });
          var ditambah = 0;
          kartuBaru.forEach(function (k) {
            var s = kunci(k);
            if (s && ada[s]) return;
            if (s) ada[s] = 1;
            kisi.appendChild(k);
            ditambah++;
          });

          // Ganti penanda dengan milik potongan baru (tautan halaman
          // berikutnya), atau tandai kalau sudah habis.
          var baru = doc.querySelector('[data-katalog-lagi]');
          if (baru) {
            penanda.replaceWith(baru);
            penanda = baru;
            penanda.dataset.gulirSiap = '1';
          } else {
            habis = true;
            var akhir = doc.querySelector('[data-katalog-habis]');
            penanda.replaceWith(akhir || document.createTextNode(''));
            penanda = null;
          }

          // Halaman yang tidak menambah apa pun = penanda berhenti, supaya tidak
          // ada permintaan berulang tanpa akhir.
          if (ditambah === 0 && !habis) habis = true;
        })
        .catch(function () { /* diamkan: penanda tetap ada, pengguna bisa klik */ })
        .finally(function () {
          sedang = false;
          if (penanda) penanda.classList.remove('memuat');
        });
    }

    // Amati penanda: satu layar sebelum terlihat, halaman berikutnya sudah
    // diminta (jadi gulir terasa tidak pernah menunggu).
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entri) {
        entri.forEach(function (e) { if (e.isIntersecting) ambil(); });
      }, { rootMargin: '600px 0px' }).observe(penanda);
    } else {
      // Peramban lama: periksa saat menggulir.
      window.addEventListener('scroll', function () {
        if (!penanda) return;
        if (penanda.getBoundingClientRect().top < window.innerHeight + 600) ambil();
      }, { passive: true });
    }

    // Klik penanda = ambil lewat AJAX (kalau JS hidup), bukan pindah halaman.
    penanda.addEventListener('click', function (ev) {
      var a = ev.target.closest ? ev.target.closest('a[data-url]') : null;
      if (!a) return;
      ev.preventDefault();
      ambil();
    });
  }

  document.querySelectorAll('[data-katalog-lagi]').forEach(pasang);
})();

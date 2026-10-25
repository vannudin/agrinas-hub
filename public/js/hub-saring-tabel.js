/*
 * Cari + filter + paging untuk tabel daftar di halaman AgriConnect.
 *
 * KENAPA DI PERAMBAN: baris di halaman /agri/* berasal dari jawaban API
 * AgriConnect, jadi seluruhnya memang sudah ada di dokumen. Menyaring di server
 * berarti satu perjalanan HTTP lagi untuk data yang sudah di tangan — dan
 * halaman HRIS dipanggil ulang tiap kali pengguna mengetik.
 *
 * Dipasang otomatis pada setiap `.tabel-bungkus` yang TIDAK didahului formulir
 * `.saring` (halaman Absensi menyaring di server karena datanya 6 bulan).
 * Kolom filter ditebak dari judul kolom status/keadaan/jenis — kalau tidak ada,
 * bar filter tetap muncul dengan pencarian saja, bukan select kosong.
 */
(function () {
  'use strict';

  var PER = 10;
  var RE_FILTER = /^(status|keadaan|jenis|kategori|tipe)$/i;

  function muat(bungkus) {
    var tabel = bungkus.querySelector('table');
    if (!tabel || !tabel.tBodies[0]) return null;

    var baris = Array.prototype.filter.call(tabel.tBodies[0].rows, function (tr) {
      return !tr.querySelector('td[colspan]');
    });
    // Tabel 1 baris tidak perlu disaring; tabel pasangan label→nilai (tanpa
    // <thead>, mis. Identitas/Kepegawaian di Profil) TIDAK punya kolom status,
    // tapi tetap diberi pencarian karena 12–15 baris itu juga dicari mata.
    if (baris.length < 2) return null;
    if (!tabel.tHead && baris.length < 8) return null;

    var kepala = tabel.tHead
      ? Array.prototype.map.call(tabel.tHead.rows[0].cells, function (th) {
          return (th.textContent || '').trim();
        })
      : [];
    var iFilter = kepala.findIndex(function (t) { return RE_FILTER.test(t); });

    var bar = document.createElement('div');
    bar.className = 'saring saring-tabel';

    var cari = document.createElement('label');
    cari.className = 'cari';
    cari.innerHTML = '<span class="sr">Cari di tabel</span>'
      + '<svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">'
      + '<circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path></svg>';
    var input = document.createElement('input');
    input.type = 'search';
    input.placeholder = 'Cari di tabel ini…';
    input.autocomplete = 'off';
    cari.appendChild(input);
    bar.appendChild(cari);

    var pilih = null;
    if (iFilter >= 0) {
      var nilai = [];
      baris.forEach(function (tr) {
        var sel = tr.cells[iFilter];
        var t = sel ? (sel.textContent || '').trim() : '';
        // Sel badge bisa berisi dua keadaan ("Libur · Terlambat"): pecah per "·".
        t.split('·').forEach(function (s) {
          s = s.trim();
          if (s && nilai.indexOf(s) === -1) nilai.push(s);
        });
      });
      if (nilai.length > 1 && nilai.length <= 12) {
        pilih = document.createElement('label');
        pilih.className = 'pilih';
        var judul = document.createElement('span');
        judul.className = 'sr';
        judul.textContent = kepala[iFilter];
        var sel = document.createElement('select');
        sel.innerHTML = '<option value="">Semua ' + kepala[iFilter].toLowerCase() + '</option>'
          + nilai.map(function (v) { return '<option value="' + v + '">' + v + '</option>'; }).join('');
        pilih.appendChild(judul);
        pilih.appendChild(sel);
        bar.appendChild(pilih);
      }
    }

    var hitung = document.createElement('span');
    hitung.className = 'saring-hitung pudar';
    bar.appendChild(hitung);

    var pager = document.createElement('div');
    pager.className = 'saring-pager';
    var sebelum = document.createElement('button');
    sebelum.type = 'button';
    sebelum.className = 'tombol samar';
    sebelum.textContent = '← Sebelumnya';
    var posisi = document.createElement('span');
    posisi.className = 'pudar';
    var sesudah = document.createElement('button');
    sesudah.type = 'button';
    sesudah.className = 'tombol samar';
    sesudah.textContent = 'Berikutnya →';
    pager.appendChild(sebelum);
    pager.appendChild(posisi);
    pager.appendChild(sesudah);

    bungkus.parentNode.insertBefore(bar, bungkus);
    bungkus.parentNode.insertBefore(pager, bungkus.nextSibling);

    var halaman = 1;
    var cocok = baris.slice();

    function saring() {
      var q = input.value.toLowerCase().trim();
      var f = pilih ? pilih.querySelector('select').value : '';
      cocok = baris.filter(function (tr) {
        if (f && (tr.cells[iFilter] ? tr.cells[iFilter].textContent : '').indexOf(f) === -1) return false;
        if (!q) return true;
        return (tr.textContent || '').toLowerCase().indexOf(q) !== -1;
      });
      halaman = 1;
      gambar();
    }

    function gambar() {
      var total = Math.max(1, Math.ceil(cocok.length / PER));
      if (halaman > total) halaman = total;
      var awal = (halaman - 1) * PER;
      baris.forEach(function (tr) { tr.hidden = true; });
      cocok.slice(awal, awal + PER).forEach(function (tr) { tr.hidden = false; });
      hitung.textContent = cocok.length === baris.length
        ? baris.length + ' baris'
        : cocok.length + ' dari ' + baris.length + ' baris';
      posisi.textContent = 'Halaman ' + halaman + ' / ' + total;
      sebelum.disabled = halaman <= 1;
      sesudah.disabled = halaman >= total;
      pager.hidden = cocok.length === 0;
      if (!cocok.length) hitung.textContent = 'Tidak ada baris yang cocok';
    }

    input.addEventListener('input', saring);
    if (pilih) pilih.querySelector('select').addEventListener('change', saring);
    sebelum.addEventListener('click', function () { halaman--; gambar(); });
    sesudah.addEventListener('click', function () { halaman++; gambar(); });

    gambar();
    return true;
  }

  /*
   * Kisi menu AgriConnect (/agri): 21 kartu dalam 5 kelompok. Mencari satu
   * butir di antara 21 kartu lebih lambat daripada mengetik namanya, jadi
   * kisi diberi kolom cari + penyaring kelompok. Kelompok yang habis
   * disembunyikan (judulnya ikut) supaya tidak ada judul menggantung.
   */
  function kisi() {
    var nav = document.querySelector('nav.kisi-modul');
    if (!nav || nav.dataset.saringSiap) return;
    var kartu = Array.prototype.slice.call(nav.querySelectorAll('.kartu-fitur'));
    if (kartu.length < 8) return;

    var bar = document.createElement('div');
    bar.className = 'saring saring-kisi';
    var label = document.createElement('label');
    label.className = 'cari';
    label.innerHTML = '<span class="sr">Cari menu</span>'
      + '<svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">'
      + '<circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path></svg>';
    var input = document.createElement('input');
    input.type = 'search';
    input.placeholder = 'Cari menu AgriConnect…';
    input.autocomplete = 'off';
    label.appendChild(input);
    bar.appendChild(label);
    var hitung = document.createElement('span');
    hitung.className = 'saring-hitung pudar';
    bar.appendChild(hitung);
    nav.parentNode.insertBefore(bar, nav);

    function saring() {
      var q = input.value.toLowerCase().trim();
      var tampil = 0;
      kartu.forEach(function (k) {
        var cocok = !q || (k.textContent || '').toLowerCase().indexOf(q) !== -1;
        k.hidden = !cocok;
        if (cocok) tampil++;
      });
      Array.prototype.forEach.call(nav.querySelectorAll('.kisi-seksi'), function (seksi) {
        seksi.hidden = !seksi.querySelector('.kartu-fitur:not([hidden])');
      });
      hitung.textContent = q ? tampil + ' dari ' + kartu.length + ' menu' : kartu.length + ' menu';
    }

    input.addEventListener('input', saring);
    saring();
    nav.dataset.saringSiap = '1';
  }

  function jalan() {
    kisi();
    Array.prototype.forEach.call(document.querySelectorAll('.tabel-bungkus'), function (b) {
      if (b.dataset.saringSiap) return;
      var blok = b.closest('.blok') || b.parentNode;
      if (blok && blok.querySelector(':scope > form.saring')) return; // sudah disaring server
      if (muat(b)) b.dataset.saringSiap = '1';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', jalan);
  } else {
    jalan();
  }
})();

/*
 * Meja SDM & Kepegawaian (/admin/hr) — dua hal yang tidak bisa dilakukan hub.js:
 *
 *   1. Mengisi TUJUAN formulir (action) dari tombol baris.
 *      hub.js menangani data-awal (nilai field) dan data-teks (dialog
 *      baca-saja). Tapi satu <dialog> melayani BANYAK baris, dan tiap baris
 *      punya rute simpan sendiri (/admin/hr/{id}/sdm, /nilai, /slip,
 *      /keputusan). Karena itu tombol pembuka membawa `data-lengkapi` =
 *      JSON {aksi, ...field}; penangan di bawah menulis form.action lalu
 *      menyalin tiap field lewat [name=n]. Satu atribut melayani keempat
 *      dialog sekaligus — menambah jenis aksi cukup menambah tombol.
 *
 *   2. Menyaring daftar pelamar saat mengetik.
 *      Tabel pelamar TIDAK berhalaman (beda dari tabel pegawai), jadi hub.js
 *      tidak bisa mengambil alih form `.saring` (itu menukar SELURUH isi
 *      wadah dan membuang kotak cari → fokus hilang tiap huruf). Di sini hanya
 *      <tbody> yang ditukar, lewat potongan server `?pelamar=`.
 */
document.addEventListener('click', function (ev) {
  var buka = ev.target.closest ? ev.target.closest('[data-buka][data-lengkapi]') : null;
  if (!buka) return;

  var d = document.getElementById((buka.getAttribute('data-buka') || '').replace(/^#/, ''));
  if (!d) return;
  var form = d.querySelector('form');
  if (!form) return;

  var isi;
  try { isi = JSON.parse(buka.getAttribute('data-lengkapi')); } catch (e) { return; }

  if (isi.aksi) form.setAttribute('action', isi.aksi);

  // Judul yang menyebut nama orang: "Kepegawaian — Budi" -> dipakai apa adanya.
  var h2 = d.querySelector('.modal-kepala h2');
  var judul = buka.getAttribute('data-judul-dialog');
  if (judul && h2) h2.textContent = judul;

  Object.keys(isi).forEach(function (nama) {
    if (nama === 'aksi' || nama === 'orang') return;
    var el = form.querySelector('[name="' + nama + '"]');
    if (!el) return;
    // <select> yang nilainya tak punya <option> (mis. atasan_id '0' vs opsi
    // kosong) akan DIAM-DIAM tampil sebagai opsi pertama dan menyesatkan.
    // Untuk select, pilih opsi yang cocok; bila tak ada, jatuhkan ke ''.
    if (el.tagName === 'SELECT') {
      var cocok = false;
      for (var i = 0; i < el.options.length; i++) {
        if (el.options[i].value === String(isi[nama])) { cocok = true; break; }
      }
      el.value = cocok ? String(isi[nama]) : '';
    } else {
      el.value = isi[nama];
    }
  });
});

/* Saring pelamar sambil mengetik: tukar <tbody> saja. */
(function () {
  var form = document.querySelector('[data-pelamar]');
  if (!form) return;
  var kotak = form.querySelector('[data-pelamar-cari]');
  var badan = document.getElementById('hr-pelamar-baris');
  if (!kotak || !badan) return;

  var url = form.getAttribute('data-pelamar');
  var pilih = form.querySelector('[data-pelamar-status]');
  var jam = null;
  var no = 0;

  function tarik() {
    var milik = ++no;
    var s = url + '&q=' + encodeURIComponent(kotak.value);
    // Status pelamar ikut dikirim — tanpa ini memilih status lalu mengetik
    // membuang saringan statusnya diam-diam.
    if (pilih && pilih.value) s += '&pstatus=' + encodeURIComponent(pilih.value);
    fetch(s, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }).then(function (r) { return r.ok ? r.text() : null; }).then(function (html) {
      // Jawaban yang datang terlambat (ketikan lebih baru sudah dikirim) dibuang,
      // supaya hasil ketikan lama tidak menimpa yang baru.
      if (html === null || milik !== no) return;
      // Respons = POTONGAN berisi <tr> saja (partial _hr-pelamar), BUKAN tabel
      // utuh. Jadi jangan mencari '#hr-pelamar-baris' di dalamnya — elemen itu
      // tidak pernah ada di respons, dan pencarian jadi diam-diam gagal.
      var p = new DOMParser().parseFromString(html, 'text/html');
      if (p.body) badan.innerHTML = p.body.innerHTML;
    }).catch(function () { /* biarkan isi lama; Enter tetap menyaring lewat form */ });
  }

  kotak.addEventListener('input', function () {
    clearTimeout(jam);
    jam = setTimeout(tarik, 250);
  });

  // Tombol submit form ini ada di dalam <noscript> (tanpa JS form GET biasa),
  // jadi saat JS hidup menekan Enter tidak mengirim apa pun — hasilnya halaman
  // dimuat ulang dan kotak cari tampak tidak bekerja. Tangani di sini.
  kotak.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') return;
    ev.preventDefault();
    clearTimeout(jam);
    tarik();
  });

  // Ganti status = langsung muat ulang daftar (tanpa menunggu ketikan).
  if (pilih) {
    pilih.addEventListener('change', function () { clearTimeout(jam); tarik(); });
  }
})();

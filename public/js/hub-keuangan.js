/* Keuangan — pemilih KDMP yang bisa dicari.
 *
 * Kenapa ada: dulu pemilih koperasi berupa <select> berisi SELURUH koperasi.
 * Dengan 18 baris itu nyaman; dengan puluhan ribu baris halaman mengirim puluhan
 * ribu <option> setiap kali dibuka — dan <select> tetap tak bisa dicari, jadi
 * admin menggulir satu per satu. Sekarang server mengirim hanya yang sedang
 * dicari (potongan 20 baris) dan skrip ini yang memintanya saat pengguna mengetik.
 *
 * Kenapa penanganannya di `document`, bukan per-elemen: halaman ini punya wadah
 * [data-isi] (#daftar) yang isinya DITUKAR hub.js setiap kali periode, saringan,
 * atau halaman berpindah — tanpa muat ulang. Penanganan yang diikat ke elemen
 * akan mati pada pertukaran pertama: kotak pencarian tampak hidup tapi diam.
 * Dengan delegasi, elemennya selalu dibaca ulang dari DOM saat kejadian terjadi.
 *
 * Yang TIDAK dilakukan skrip ini:
 *  - membuka hasil. Barisnya <a> biasa, dan hub.js sudah menukar isi [data-isi]
 *    untuk tautan ber-pathname sama — klik hasil tetap tanpa muat ulang.
 *  - mengirim formulir. Enter = <form method="get"> biasa; server merender
 *    daftar hasilnya di dalam halaman. Tanpa JavaScript semuanya tetap bekerja.
 */
/* Pencarian KDMP DIHAPUS dari berkas ini.
 *
 * Dulu ia punya sendiri penangan input+fetch untuk #cari_kop, padahal
 * /keuangan juga memuat /js/hub-pemilih-kop.js (berkas bersama dengan
 * /workspace). Dua penangan mengerjakan hal yang sama = dua tempat yang bisa
 * menyimpang — dan memang menyimpang: yang ini menulis `innerHTML`, yang
 * menyisipkan <ul> di dalam <ul> sehingga kotak hasil tampil 8px kosong.
 * Sekarang hanya skrip bersama yang menangani pencarian; berkas ini fokus pada
 * modal "Ubah catatan" (lihat di bawah). */

/* ---------------------------------------------------------------------------
 * Modal "Ubah catatan" (/keuangan).
 *
 * Kenapa modal: rincian ubah dulu <details> yang membuka form 420px TERTAMBAT di
 * dalam sel aksi (sel hanya ~137px di desktop), sehingga tampak terpotong dan
 * mendorong tinggi baris. Modal <dialog> mengambil ruangnya sendiri.
 *
 * Dialog dicari ULANG setiap dipakai: hub.js menukar ISI wadah [data-isi]
 * (#daftar) setiap periode/saringan/paging, dan elemen yang disimpan lebih dulu
 * akan menunjuk node yang sudah dibuang (tombol tampak "tidak bereaksi").
 * Modal juga diletakkan DI LUAR wadah itu (lihat blade).
 *
 * Tanpa JavaScript formnya tidak muncul, jadi tombolnya diberi `hidden` oleh
 * skrip ini sendiri saat skrip berjalan — tidak ada tombol mati.
 * ------------------------------------------------------------------------- */
(function () {
  const elModal = () => document.getElementById('keu-modal');
  const $ = (id) => document.getElementById(id);

  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;

    // Tutup
    if (ev.target.closest('[data-tutup]')) {
      const m = elModal();
      if (m) m.close();
      return;
    }

    const b = ev.target.closest('[data-keuangan]');
    if (!b) return;
    const m = elModal();
    if (!m || typeof m.showModal !== 'function') return;
    ev.preventDefault();

    // Isi dari data-* tombol — satu sumber, tak ada markup kedua yang bisa basi.
    const d = b.dataset;
    $('keu-modal-tanggal').textContent = '· ' + (d.tanggal || '');
    $('keu-modal-input-tanggal').value = d.tanggal || '';
    $('keu-modal-koperasi').value = d.koperasiId || '';
    $('keu-modal-pemasukan').value = d.pemasukan || 0;
    $('keu-modal-pengeluaran').value = d.pengeluaran || 0;
    $('keu-modal-masuk').value = d.masuk || '';
    $('keu-modal-keluar').value = d.keluar || '';
    $('keu-modal-catatan').value = d.catatan || '';
    $('keu-modal-kat-masuk').value = d.katMasuk || '';
    $('keu-modal-kat-keluar').value = d.katKeluar || '';

    // Baris otomatis: angka dikunci + alasannya dijelaskan. `readonly` (bukan
    // `disabled`) supaya nilainya tetap ikut terkirim.
    const otomatis = (d.ref || '') !== '';
    $('keu-modal-otomatis').hidden = !otomatis;
    if (otomatis) $('keu-modal-ref').textContent = d.ref;
    ['keu-modal-pemasukan', 'keu-modal-pengeluaran'].forEach(function (id) {
      const i = $(id);
      if (!i) return;
      i.readOnly = otomatis;
      i.classList.toggle('terkunci', otomatis);
    });

    if (!m.open) m.showModal(); else m.focus();
  });

  // Tombol Ubah disembunyikan sampai skrip ini hidup; kalau gagal, server tetap
  // menyediakan jalur tanpa-JS (form "Catat Keuangan Harian" + tombol di tabel
  // tidak dirender sebagai <details> lagi, maka tanpa JS tabel hanya informatif).
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-keuangan]').forEach(function (b) { b.hidden = false; });
  });
})();

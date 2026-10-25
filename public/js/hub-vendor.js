/* Vendor — satu <dialog> melayani Tambah & Ubah, dan tabel disaring tanpa
 * memuat ulang halaman.
 *
 * Kenapa: dulu tiap baris membawa <details> berisi formulir Ubah sendiri, dan
 * formulir Tambah duduk di blok terpisah di bawah tabel. Sekarang satu tombol
 * "Tambah Vendor" di pojok kanan atas dan satu modal yang sama untuk Tambah &
 * Ubah — pengguna tidak perlu menggulir ke bawah tabel untuk menambah.
 *
 * Dua hal yang sengaja TIDAK dilakukan:
 *   - mengirim formulir. Formulirnya <form method="post"> biasa, jadi CSRF,
 *     validasi, dan kewenangan tetap jalur server yang sama. Gagal validasi
 *     memuat ulang halaman, modal dibuka lagi oleh skrip inline di blade, dan
 *     flash session-nya ditampilkan hub.js sebagai notifikasi.
 *   - menukar SELURUH isi wadah [data-isi] saat menyaring. Yang ditukar hanya
 *     <tbody>: kotak cari berada di luar itu, jadi fokus mengetik tidak hilang
 *     setiap huruf (cacat yang sama pernah terjadi di /distribusi).
 */
(function () {
  if (typeof HTMLDialogElement === 'undefined') return;   // <a>/<form> tetap bekerja

  const elModal = () => document.getElementById('vendor-modal');
  const elBaris = () => document.getElementById('vd-baris');
  const elJumlah = () => document.getElementById('vd-jumlah');
  const elWadah = () => document.getElementById('vd');

  // ------------------------------------------------------------- modal
  // Judul & isian field sudah diisi hub.js dari `data-awal`/`data-judul-dialog`.
  // Yang tersisa: alamat tujuan formulir (satu form melayani Tambah & Ubah) dan
  // menyembunyikan kolom Kode saat mengubah — server memang tidak menerimanya
  // saat ubah, jadi menampilkannya akan menjanjikan sesuatu yang tak tersimpan.
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;
    const t = ev.target.closest('[data-buka="vendor-modal"]');
    if (!t) return;
    const modal = elModal();
    if (!modal) return;
    const form = modal.querySelector('form');
    // `data-mode` eksplisit, BUKAN tebakan dari isi URL: rute simpan beralamat
    // /admin/vendor (tanpa kata "simpan"), jadi menebak dari URL membuat mode
    // Tambah terbaca sebagai Ubah dan kolom Kode ikut tersembunyi.
    const ubah = t.dataset.mode !== 'tambah';

    if (form && t.dataset.aksi) form.action = t.dataset.aksi;

    const lab = document.getElementById('vendor-lab-kode');
    // Input Kode berada DI DALAM label ini, jadi menyembunyikan labelnya sekaligus
    // menyembunyikan kolomnya (server memang mengabaikan kode saat mengubah).
    if (lab) lab.hidden = ubah;
    const kode = modal.querySelector('[name="kode"]');
    // Saat menambah, kode kosong = dibuat otomatis server (VND-0006).
    if (kode && !ubah) kode.value = '';
  });

  // ------------------------------------------------- saring tanpa muat ulang
  function segarkanBersih() {
    const f = document.querySelector('#vd form.saring');
    const b = document.querySelector('#vd .bersih-saring');
    if (!f || !b) return;
    b.hidden = ![...new FormData(f)].some(function (p) { return String(p[1]).trim() !== ''; });
  }

  async function muatDaftar() {
    const isi = elBaris();
    const wadah = elWadah();
    const formSaring = document.querySelector('#vd form.saring');
    if (!isi || !wadah || !formSaring) return;
    const u = new URL(wadah.dataset.daftar, window.location.origin);
    new URLSearchParams(new FormData(formSaring)).forEach(function (v, k) {
      if (v !== '') u.searchParams.set(k, v);
    });
    try {
      const res = await fetch(u.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin',
      });
      if (res.redirected) { window.location.href = res.url; return; }
      if (!res.ok) return;
      isi.innerHTML = await res.text();
      const jm = elJumlah();
      if (jm) {
        jm.textContent = Number(res.headers.get('X-Jumlah') || 0).toLocaleString('id-ID') + ' vendor';
      }
    } catch (e) { /* tabel menampilkan data lama; halaman tetap bisa dimuat ulang */ }
  }

  let jeda = null;
  document.addEventListener('input', function (ev) {
    const f = ev.target.closest ? ev.target.closest('#vd form.saring') : null;
    if (!f || ev.target.tagName === 'SELECT') return;
    segarkanBersih();
    window.clearTimeout(jeda);
    jeda = window.setTimeout(muatDaftar, 320);
  });
  document.addEventListener('change', function (ev) {
    if (!ev.target.closest) return;
    if (ev.target.closest('#vd form.saring select')) { segarkanBersih(); muatDaftar(); }
  });
})();

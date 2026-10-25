/* Kontrak — saring tabel tanpa memuat ulang halaman.
 *
 * Kenapa tidak mengandalkan hub.js saja: penangan `.saring` di sana menukar
 * SELURUH isi wadah [data-isi], sehingga kotak cari ikut dibuang dan fokus
 * hilang tiap huruf. Di sini yang ditukar hanya <tbody> — pola yang sama
 * dipakai hub-vendor.js. Paging tetap ditangani hub.js (wadah ber-`data-isi`).
 *
 * Tanpa JavaScript semuanya tetap jalan: form GET biasa + tombol Saring di
 * <noscript>.
 */
(function () {
  const elBaris = () => document.getElementById('kt-baris');
  const elJumlah = () => document.getElementById('kt-jumlah');
  const elWadah = () => document.getElementById('kt');
  const elForm = () => document.querySelector('#kt form.saring');

  function segarkanBersih() {
    const f = elForm();
    const b = document.querySelector('#kt .bersih-saring');
    if (!f || !b) return;
    b.hidden = ![...new FormData(f)].some(function (p) { return String(p[1]).trim() !== ''; });
  }

  async function muatDaftar() {
    const isi = elBaris();
    const wadah = elWadah();
    const form = elForm();
    if (!isi || !wadah || !form) return;

    const u = new URL(wadah.dataset.daftar, window.location.origin);
    new URLSearchParams(new FormData(form)).forEach(function (v, k) {
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
        jm.textContent = Number(res.headers.get('X-Jumlah') || 0).toLocaleString('id-ID') + ' baris';
      }
    } catch (e) { /* tabel menampilkan data lama; halaman tetap bisa dimuat ulang */ }
  }

  let jeda = null;
  document.addEventListener('input', function (ev) {
    const f = ev.target.closest ? ev.target.closest('#kt form.saring') : null;
    if (!f || ev.target.tagName === 'SELECT') return;
    segarkanBersih();
    window.clearTimeout(jeda);
    jeda = window.setTimeout(muatDaftar, 320);
  });
  document.addEventListener('change', function (ev) {
    if (!ev.target.closest) return;
    if (ev.target.closest('#kt form.saring select')) { segarkanBersih(); muatDaftar(); }
  });
})();

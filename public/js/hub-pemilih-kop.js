/* Pemilih KDMP yang bisa dicari — dipakai /keuangan DAN /workspace.
 *
 * Kenapa ada: dulu pemilih koperasi berupa <select> berisi SELURUH koperasi.
 * Dengan 18 baris itu nyaman; dengan puluhan ribu baris halaman mengirim puluhan
 * ribu <option> setiap kali dibuka — dan <select> tetap tak bisa dicari, jadi
 * admin menggulir satu per satu. Sekarang server mengirim hanya yang sedang
 * dicari (potongan 20 baris) dan skrip ini yang memintanya saat pengguna mengetik.
 *
 * Kontraknya lewat data-* supaya satu berkas melayani dua halaman:
 *   - <form data-url="…/koperasi">        endpoint potongan hasil
 *   - <input data-cari-kop>               kotak pencarian
 *   - <ul data-hasil-kop>                 wadah hasil
 *   - <input type="hidden" data-kop-id>   nilai terpilih (untuk formulir)
 *   - <[data-pilih-kop]>                  tombol hasil (halaman tanpa navigasi)
 *   - <a data-baris-kop>                  tautan hasil (halaman yang berpindah)
 *
 * Penanganannya di `document`, bukan per-elemen: halaman-halaman ini punya wadah
 * [data-isi] yang isinya DITUKAR hub.js tanpa muat ulang. Penanganan yang diikat
 * ke elemen akan mati pada pertukaran pertama — kotak tampak hidup tapi diam.
 *
 * Tanpa JavaScript tetap bekerja: formulirnya <form method="get"> biasa (hasil
 * dirender server), dan halaman yang butuh memilih menyediakan <noscript> berisi
 * <select> biasa.
 */
(function () {
  // Ambang 2 huruf disamakan dengan server supaya tak ada permintaan yang
  // dijawab kosong karena terlalu pendek.
  const AMBANG = 2;
  let tunda = null;
  let urut = 0;

  const el = {
    kotak: h => document.querySelector('[' + h + ']'),
    hasil: h => document.querySelector('[' + h + ']'),
  };

  function isiKotak(ev) {
    const k = ev.target.closest ? ev.target.closest('[data-cari-kop]') : null;
    return k ? { k: k, f: k.closest('form'), d: document.querySelector('[data-hasil-kop]') } : null;
  }

  function buka(terbuka) {
    const d = document.querySelector('[data-hasil-kop]');
    const k = document.querySelector('[data-cari-kop]');
    if (d) d.hidden = !terbuka;
    if (k) k.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
  }

  async function cari(q, formEl, wadah) {
    const url = formEl && formEl.dataset.url;
    if (!url || !wadah) return;
    const saya = ++urut;                       // jawaban lama tidak boleh menimpa
    const u = new URL(url, window.location.origin);
    u.searchParams.set('cari_kop', q);
    const idTerpilih = formEl.querySelector('[data-kop-id]');
    if (idTerpilih && idTerpilih.value) u.searchParams.set('koperasi_id', idTerpilih.value);

    try {
      const res = await fetch(u.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin',
      });
      if (!res.ok || saya !== urut) return;
      // 🔴 Ganti NODE-nya, JANGAN `wadah.innerHTML = …`. Akar potongan dari
      // server adalah <ul class="pilih-daftar"> itu SENDIRI, jadi menulis
      // innerHTML-nya menyisipkan <ul> di dalam <ul>: anaknya position:absolute
      // sehingga tidak menyumbang tinggi, dan kotak hasil tampil sebagai
      // sliver 8px KOSONG walau berisi 15 baris ("muncul blank, tidak tampil").
      const p = new DOMParser().parseFromString(await res.text(), 'text/html');
      const baru = p.querySelector('[data-hasil-kop]');
      if (baru && wadah.parentNode) wadah.parentNode.replaceChild(baru, wadah);
      buka(true);
    } catch (e) { /* biarkan hasil lama; kotak tetap bisa dipakai mengetik lagi */ }
  }

  document.addEventListener('input', function (ev) {
    const x = isiKotak(ev);
    if (!x) return;
    window.clearTimeout(tunda);
    tunda = window.setTimeout(function () {
      const q = x.k.value.trim();
      if (q.length < AMBANG) { buka(false); return; }
      cari(q, x.f, x.d);
    }, 250);
  });

  // Memilih hasil: kalau ada input tersembunyi (formulir yang tidak berpindah),
  // isi nilainya; kalau barisnya <a>, biarkan peramban menanganinya.
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;
    const b = ev.target.closest('[data-pilih-kop]');
    if (b) {
      const formEl = b.closest('form') || document.body;
      const idEl = formEl.querySelector('[data-kop-id]') || document.querySelector('[data-kop-id]');
      if (idEl) idEl.value = b.dataset.pilihKop;
      const k = document.querySelector('[data-cari-kop]');
      if (k) k.value = b.dataset.label || '';
      const ket = document.querySelector('[data-kop-terpilih]');
      if (ket) ket.innerHTML = 'Terpilih: <b>' + (b.dataset.label || '') + '</b>';
      // Input tersembunyi bernama sama di dalam <noscript> bisa ikut terkirim;
      // hanya yang terlihat yang dipakai (noscript tidak punya kotak terlihat).
      buka(false);
      return;
    }
    // Klik di luar kotak menutup daftar.
    if (!ev.target.closest('.cari-kop')) buka(false);
  });

  // ------------------------------------------------ sasaran pengiriman tugas
  // Kirim ke: "Koperasi tertentu" menampilkan pemilih koperasi; sasaran wilayah
  // menampilkan pemilih wilayah (isinya dari server, per jenis sasaran).
  const SASARAN_WILAYAH = {
    PROVINSI:  { label: 'Provinsi', dari: 'provinsi' },
    KABUPATEN: { label: 'Kabupaten/kota', dari: 'kabupaten' },
    KECAMATAN: { label: 'Kecamatan', dari: 'kecamatan' },
  };

  function segarkanSasaran() {
    const sel = document.getElementById('sasaran_ws');
    if (!sel) return;
    const barisKop = document.getElementById('koperasi-baris');
    const barisWil = document.getElementById('sasaran-wilayah-baris');
    const pilihWil = document.getElementById('wilayah_ws');
    const labelWil = document.getElementById('wilayah_ws_label');
    const s = sel.value;

    if (barisKop) barisKop.hidden = s !== 'KOPERASI';
    if (barisWil) barisWil.hidden = !SASARAN_WILAYAH[s];

    if (SASARAN_WILAYAH[s] && pilihWil) {
      const kunci = SASARAN_WILAYAH[s].dari;
      const sumber = (window.WS_SASARAN || {})[kunci];
      // Bentuknya sengaja ditangani dua-duanya: provinsi datang sebagai PETA
      // (kunci tersimpan → label terbaca) karena nilai yang dikirim ke server
      // harus kuncinya, sedangkan kabupaten/kecamatan berupa larik nama.
      // Menganggap keduanya larik membuat `map` meledak di provinsi.
      let daftar;
      if (Array.isArray(sumber)) {
        daftar = sumber.map(function (n) { return [n, n]; });
      } else {
        daftar = Object.keys(sumber || {}).map(function (k) { return [k, sumber[k]]; });
      }
      if (labelWil) labelWil.textContent = SASARAN_WILAYAH[s].label;
      pilihWil.innerHTML = daftar.map(function (p) {
        return '<option value="' + p[0] + '">' + p[1] + '</option>';
      }).join('');
    }
  }

  document.addEventListener('change', function (ev) {
    if (ev.target && ev.target.id === 'sasaran_ws') segarkanSasaran();
  });
  document.addEventListener('DOMContentLoaded', segarkanSasaran);
  segarkanSasaran();

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && ev.target && ev.target.hasAttribute && ev.target.hasAttribute('data-cari-kop')) {
      buka(false); ev.target.blur();
    }
  });
})();

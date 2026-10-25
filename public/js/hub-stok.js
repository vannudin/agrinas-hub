/* Stok Gudang — Tambah/Ubah barang lewat modal, tanpa muat ulang.
 *
 * Prinsip yang sama dengan hub-katalog.js: JS TIDAK menyusun markup. Isi kolom
 * modal diambil dari atribut `data-*` yang dikirim server di tombol Ubah, dan
 * daftar diperbarui dengan POTONGAN HTML dari server (distribusi._stok-baris).
 *
 * Kenapa ada dua dialog yang tidak muncul bersamaan: Tambah dan Ubah memakai
 * SATU <dialog> dan SATU formulir — kolomnya identik, jadi dua formulir
 * terpisah hanya akan menyimpang satu sama lain saat ada kolom baru.
 *
 * Tanpa JavaScript: tombol "Tambah Barang" adalah <a href="#tambah"> menuju
 * formulir biasa di dalam <noscript>, dan tiap baris masih dapat disunting
 * lewat formulir POST biasa. Jadi tidak ada aksi yang hilang.
 */
(function () {
  const modal = document.getElementById('barang-modal');
  const form = document.getElementById('barang-form');
  if (!modal || !form || !document.getElementById('stok-baris') || typeof modal.showModal !== 'function') return;

  // Dibaca ULANG setiap kali, bukan disimpan di variabel: hub.js menukar isi
  // wadah #stok saat saring/paging berpindah, sehingga pegangan ke elemen lama
  // menunjuk node yang sudah dibuang — tabel tampak tidak pernah menyegar.
  const elBaris = () => document.getElementById('stok-baris');
  const elJumlah = () => document.getElementById('stok-jumlah');

  const judul = document.getElementById('barang-modal-judul');
  const simpan = document.getElementById('barang-modal-simpan');
  const kabar = document.getElementById('barang-modal-kabar');
  const galat = document.getElementById('barang-modal-galat');
  const token = document.querySelector('meta[name="csrf-token"]').content;
  const urlTambah = form.action;
  // `data-isi` milik hub.js (wadah yang ditukar untuk saring/paging), sedangkan
  // potongan BARIS tabel punya endpoint sendiri (`barang.daftar`). Keduanya
  // sempat tertukar: fetch ke /admin/barang mengembalikan halaman penuh, bukan
  // potongan, sehingga tabel tidak pernah ikut menyegar setelah simpan.
  const urlDaftar = (document.getElementById('stok') || {}).dataset?.daftar || '';

  // `data-pola`-nya tidak dipakai: alamat Ubah sudah dikirim server per baris,
  // jadi JS tak perlu menyusun URL dari id — itu sumber salah ketik rute.
  function bersih() {
    if (kabar) { kabar.hidden = true; kabar.textContent = ''; }
    if (galat) { galat.hidden = true; galat.textContent = ''; }
  }

  function pesan(teks, rusak) {
    bersih();
    const el = rusak ? galat : kabar;
    if (!el) return;
    el.textContent = teks;
    el.hidden = false;
    el.className = rusak ? 'pesan rusak' : 'pesan ok';
  }

  function bukaTambah() {
    bersih();
    form.reset();
    form.action = urlTambah;
    judul.textContent = 'Tambah Barang';
    simpan.textContent = 'Tambah Barang';
    modal.showModal();
  }

  function bukaUbah(tombol) {
    bersih();
    form.reset();
    // Alamat dari server (route()), bukan disusun di sini.
    form.action = tombol.dataset.ubah;
    const isi = {
      kode: tombol.dataset.kode, nama: tombol.dataset.nama,
      kategori: tombol.dataset.kategori, satuan: tombol.dataset.satuan,
      stok: tombol.dataset.stok, stok_min: tombol.dataset.stokMin,
      harga: tombol.dataset.harga, catatan: tombol.dataset.catatan,
    };
    Object.keys(isi).forEach(function (k) {
      const el = form.elements[k];
      if (el && isi[k] !== undefined && isi[k] !== null) el.value = isi[k];
    });
    // Vendor boleh kosong (barang dari gudang sendiri / pengadaan koperasi).
    if (form.elements.vendor_id) form.elements.vendor_id.value = tombol.dataset.vendor || '';
    judul.textContent = 'Ubah: ' + (tombol.dataset.nama || 'barang');
    simpan.textContent = 'Simpan Perubahan';
    modal.showModal();
  }

  /**
   * Ambil potongan baris terbaru dari server; saringan yang aktif ikut dibawa.
   *
   * Hanya BARIS yang ditukar (bukan seluruh blok): fokus di kotak pencarian tidak
   * hilang, jadi pengguna bisa terus mengetik. Menukar seluruh blok akan
   * membuang kotak yang sedang diisi.
   */
  async function muatDaftar() {
    if (!urlDaftar) return;
    const isi = elBaris();
    if (!isi) return;
    const saring = document.querySelector('#stok form.saring');
    const u = new URL(urlDaftar, window.location.origin);
    if (saring) new URLSearchParams(new FormData(saring)).forEach(function (v, k) {
      if (v !== '') u.searchParams.set(k, v);
    });
    try {
      const res = await fetch(u.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin',
      });
      if (!res.ok) return;
      isi.innerHTML = await res.text();
      const jm = elJumlah();
      if (jm) jm.textContent = (res.headers.get('X-Jumlah') || 0) + ' barang';
    } catch (e) {
      /* Biarkan: tabel tetap menampilkan data lama, dan pesan galat sudah tampil. */
    }
  }

  // -------------------------------------------------------------- membuka
  // Pendengar di DOKUMEN, bukan di elemen: wadah #stok isinya ditukar hub.js
  // saat saring/paging, sehingga penanganan yang diikat langsung akan mati
  // pada pertukaran pertama — tombol tampak hidup tapi diam.
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;

    if (ev.target.closest('[data-buka-tambah]')) {
      ev.preventDefault();
      bukaTambah();
      return;
    }
    const ubah = ev.target.closest('[data-ubah]');
    if (ubah) {
      ev.preventDefault();
      bukaUbah(ubah);
      return;
    }
    if (ev.target.closest('#barang-modal [data-tutup]')) {
      ev.preventDefault();
      modal.close();
    }
  });

  // Escape sudah ditangani <dialog> bawaan; cukup bersihkan sisa pesan.
  modal.addEventListener('close', bersih);

  // ---------------------------------------------- saring sambil mengetik
  // hub.js hanya menangani SUBMIT formulir .saring — artinya kata kunci baru
  // berlaku setelah Enter. Untuk daftar barang, menunggu Enter terasa seperti
  // pencarian yang tidak jalan. Di sini ketikan disaring setelah 320 ms tenang,
  // dan yang ditukar hanya baris tabelnya (fokus tidak hilang).
  // Enter tetap bekerja lewat hub.js, dan tanpa JavaScript tetap form GET biasa.
  let jeda = null;

  /** Tombol "Bersihkan" hanya berguna saat ada saringan yang bisa dibersihkan. */
  function segarkanBersih() {
    const f = document.querySelector('#stok form.saring');
    const b = document.querySelector('#stok .bersih-saring');
    if (!f || !b) return;
    const ada = [...new FormData(f)].some(function (p) { return String(p[1]).trim() !== ''; });
    b.hidden = !ada;
  }

  document.addEventListener('input', function (ev) {
    const f = ev.target.closest ? ev.target.closest('#stok form.saring') : null;
    if (!f || ev.target.tagName === 'SELECT') return;
    segarkanBersih();
    window.clearTimeout(jeda);
    jeda = window.setTimeout(muatDaftar, 320);
  });
  document.addEventListener('change', function (ev) {
    if (!ev.target.closest) return;
    if (ev.target.closest('#stok form.saring select')) { segarkanBersih(); muatDaftar(); }
  });

  // -------------------------------------------------------------- menyimpan
  // Kirim lewat fetch supaya modal tetap terbuka saat galat validasi, dan
  // tabel ikut disegarkan tanpa memuat ulang halaman. Kegagalan jaringan jatuh
  // kembali ke pengiriman formulir biasa supaya isian tidak hilang.
  form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    simpan.disabled = true;
    bersih();
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: new FormData(form),
        credentials: 'same-origin',
      });
      if (res.redirected) { window.location.href = res.url; return; }

      let j = {};
      try { j = await res.json(); } catch (e) { j = {}; }
      if (res.status === 422) {
        pesan(Object.values(j.errors || {}).flat().join(' ') || 'Isian belum lengkap.', true);
        return;
      }
      if (!res.ok || j.ok === false) {
        pesan(j.galat || ('Gagal menyimpan (HTTP ' + res.status + ').'), true);
        return;
      }
      modal.close();
      await muatDaftar();
      const k = elJumlah();
      if (k) k.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (e) {
      form.submit();      // jaringan putus: kirim sebagai formulir biasa
    } finally {
      simpan.disabled = false;
    }
  });
})();

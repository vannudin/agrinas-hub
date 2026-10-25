/* Kelola Katalog tanpa muat ulang.
 *
 * Prinsipnya: JS tidak menyusun markup. Semua perubahan daftar dikerjakan
 * server, yang dikirim balik adalah POTONGAN HTML dari partial yang sama
 * dengan yang dipakai muat-penuh (toko/_katalog-baris.blade.php). Jadi
 * tampilan hasil fetch mustahil berbeda dari tampilan hasil refresh.
 *
 * Tanpa JS semuanya tetap jalan: setiap formulir punya action POST biasa dan
 * halaman disegarkan seperti sebelumnya.
 */
(function () {
  const kat = document.getElementById('kat');
  if (!kat || !window.fetch) return;

  // Dibaca ULANG tiap kali dipakai, bukan dipegang sekali di awal: hub.js menukar
  // ISI #kat setiap paging ([data-isi]), jadi referensi yang disimpan menunjuk
  // node yang sudah dibuang — tabel tampak tidak menyaring dan notifikasi hilang
  // tanpa galat apa pun.
  const baris = () => document.getElementById('kat-baris');
  const saring = () => document.getElementById('kat-saring');
  const jumlah = () => document.getElementById('kat-jumlah');
  const kabar = () => document.getElementById('kat-kabar');
  const token = document.querySelector('meta[name="csrf-token"]').content;

  function pesan(teks, rusak) {
    const k = kabar();
    if (!k) return;
    k.hidden = false;
    k.textContent = teks;
    k.className = 'pesan ' + (rusak ? 'rusak' : 'ok');
    window.clearTimeout(pesan._t);
    pesan._t = window.setTimeout(() => { k.hidden = true; }, 4000);
  }

  async function minta(url, opsi) {
    const res = await fetch(url, Object.assign({
      headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    }, opsi || {}));
    const tipe = res.headers.get('content-type') || '';
    const isi = tipe.includes('json') ? await res.json() : null;
    if (!res.ok) throw new Error((isi && (isi.galat || isi.message)) || 'HTTP ' + res.status);
    return isi;
  }

  // ------------------------------------------------------- saring & muat daftar
  // Yang ditukar hanya <tbody>: kotak cari ada di luarnya, jadi fokus mengetik
  // tidak hilang. Paging ditangani hub.js lewat [data-isi] pada #kat (di sana
  // tidak ada isian yang sedang difokuskan, jadi menukar seluruh bagian aman).

  /** Pager dirender server untuk query SAAT HALAMAN DIMUAT. Setelah saring lewat
   *  AJAX ia harus ikut membawa kata kunci — kalau tidak, mengklik "halaman 2"
   *  membuang saringan dan menampilkan hasil yang salah. */
  function segarkanPager() {
    const s = saring();
    if (!s) return;
    const kini = new URLSearchParams(new FormData(s));
    kat.querySelectorAll('nav.paging a[href]').forEach(function (a) {
      const u = new URL(a.href, window.location.origin);
      ['q', 'kategori', 'sumber', 'aktif'].forEach(function (k) {
        const v = kini.get(k);
        if (v) u.searchParams.set(k, v); else u.searchParams.delete(k);
      });
      a.href = u.toString();
    });
  }

  async function muat() {
    const s = saring(), b = baris();
    if (!s || !b) return;
    const kueri = new URLSearchParams(new FormData(s));
    try {
      const res = await fetch(kat.dataset.daftar + '?' + kueri.toString(),
        { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      b.innerHTML = await res.text();              // potongan HTML dari server
      const j = jumlah();
      if (j) j.textContent = Number(res.headers.get('X-Jumlah') || b.querySelectorAll('tr[data-id]').length).toLocaleString('id-ID') + ' barang';
      segarkanPager();
      // Alamat ikut berubah supaya tombol Back & tautan yang dibagikan benar —
      // tetapi tanpa parameter halaman: saringan baru selalu mulai dari halaman 1.
      window.history.replaceState(null, '', kat.dataset.isi + (kueri.toString() ? '?' + kueri : ''));
    } catch (e) {
      pesan('Gagal memuat daftar: ' + e.message, true);
    }
  }

  // Penangan ditaruh di DOKUMEN dan elemennya dicari lewat closest(): baik
  // hub.js (paging) maupun muat() mengganti isi #kat, sehingga pendengar yang
  // diikat ke satu elemen di dalamnya akan mati pada pertukaran pertama.
  let jeda = null;
  document.addEventListener('input', function (ev) {
    const t = ev.target;
    if (!t.closest || !t.closest('#kat-saring') || t.tagName === 'SELECT') return;
    window.clearTimeout(jeda);
    jeda = window.setTimeout(muat, 300);
  });
  document.addEventListener('change', function (ev) {
    const t = ev.target;
    if (!t.closest || !t.closest('#kat-saring')) return;
    if (t.tagName === 'SELECT') muat();
  });
  document.addEventListener('submit', function (ev) {
    if (!ev.target.closest || !ev.target.closest('#kat-saring')) return;
    ev.preventDefault();
    muat();
  });

  // ------------------------------------------------------------- kirim formulir
  // Semua formulir bertanda data-aksi dikirim lewat fetch; bila gagal, pesannya
  // ditampilkan dan halaman tidak ditinggalkan (isi yang diketik tidak hilang).
  // Pendengar di DOKUMEN, bukan di satu bagian: formulir yang harus dikirim
  // lewat fetch bukan cuma milik #kat (modal produk, Pantau, Lepas, Terima).
  async function kirimForm(f) {
    const tombol = f.querySelector('button[type=submit]');
    if (tombol) tombol.disabled = true;
    try {
      const j = await minta(f.action, { method: 'POST', body: new FormData(f) });
      pesan((j && j.pesan) || 'Tersimpan.');
      if (f.id === 'pesanan-form') {
        // Tindakan pesanan mengubah STATUS baris (dilepas/dibayar/diterima), dan
        // tabel pesanan dirender server — jadi muat ulang halaman setelah pesan
        // tampil. Tanpa ini pengguna melihat baris yang sudah basi.
        if (modalPesanan && modalPesanan.open) modalPesanan.close();
        pesan((j && j.pesan) || 'Tersimpan.');
        window.setTimeout(() => window.location.reload(), 600);
        return;
      }
      if (f.id === 'kat-form') {
        if (modal && modal.open) modal.close();
        f.reset();                  // bawaan HTML: stok/harga kembali 0
      }
      await muat();
    } catch (e) {
      // Modal dibiarkan TERBUKA saat galat: pengguna bisa memperbaiki isiannya,
      // bukan mengetik ulang dari nol.
      pesan(e.message, true);
    } finally {
      if (tombol) tombol.disabled = false;
    }
  }

  document.addEventListener('submit', function (ev) {
    const f = ev.target.closest('form[data-aksi]');
    if (!f) return;
    ev.preventDefault();
    kirimForm(f);
  });

  // --------------------------------------------- sunting / tambah satu barang
  // Isi modal dibaca ULANG dari tombol yang diklik tiap kali. Itu bukan
  // kehati-hatian berlebih: daftar diganti lewat AJAX setiap saring/paging,
  // sehingga salinan data di variabel akan basi — dan yang paling berbahaya,
  // `action` yang basi membuat "Sunting baris B" MENIMPA baris A tanpa galat.
  const modal = document.getElementById('kat-modal');
  const form = document.getElementById('kat-form');

  function isiModal(t) {
    if (!modal || !form) return;
    const sunting = t.dataset.mode === 'sunting';
    form.action = sunting ? t.dataset.aksi : form.dataset.simpan;

    // Mode tambah = formulir bersih dengan nilai awal; biarkan nilai bawaan HTML
    // yang berlaku (stok/harga 0), jangan diisi "" yang bikin validasi gagal.
    if (sunting) {
      ['nama', 'kode', 'stok', 'stok_min', 'harga', 'gambar', 'grosir'].forEach(function (k) {
        const el = form.querySelector('[name=' + k + ']');
        if (el) el.value = t.dataset[k.replace(/_/g, '-')] ?? t.dataset[k] ?? '';
      });
      ['satuan', 'kategori', 'sumber'].forEach(function (k) {
        const el = form.querySelector('[name=' + k + ']');
        if (el) el.value = t.dataset[k] || '';
      });
      // Vendor: id-nya boleh kosong (barang dari gudang sendiri / koperasi).
      const vd = form.querySelector('[name=vendor_id]');
      if (vd) vd.value = t.dataset.vendor || '';
      // Subsidi: checkbox dibaca dari atribut, bukan dari nilai isian —
      // checkbox tidak punya `value` yang berubah, hanya `checked`.
      const sb = form.querySelector('input[type=checkbox][name=subsidi]');
      if (sb) sb.checked = t.dataset.subsidi === '1';
    }
  }

  // Penangan klik modal ditaruh di DOKUMEN, bukan di #kat: paging menukar isi
  // #kat (termasuk tombol Sunting di dalamnya), jadi pendengar yang menempel
  // pada elemen #kat tetap hidup, tetapi pendengar per tombol akan mati.
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;
    const t = ev.target.closest('#kat-baris [data-buka="kat-modal"], #kat .kepala-bagian [data-buka="kat-modal"]');
    if (!t) return;
    isiModal(t);
  });

  // ------------------------------------------------- tindakan pesanan (modal)
  // Satu jendela untuk semua tombol: yang diklik hanya membawa action, judul, dan
  // mode isian. Sebelumnya tombol Lepas/Bayar/Terima mengirim formulir sebaris dan
  // "Pantau" membuka <details> — jadi tidak ada modal sama sekali.
  const modalPesanan = document.getElementById('pesanan-modal');
  const formPesanan = document.getElementById('pesanan-form');

  document.addEventListener('click', function (ev) {
    const t = ev.target.closest && ev.target.closest('[data-pesanan]');
    if (!t || !modalPesanan || !formPesanan) return;
    const mode = t.dataset.mode || 'pantau';
    formPesanan.action = t.dataset.aksi;
    formPesanan.reset();               // isian tindakan/pesan selalu bersih
    document.getElementById('pesanan-judul').textContent = t.dataset.judul || 'Tindakan Pesanan';
    document.getElementById('pesanan-ringkas').textContent = t.dataset.ringkas || '';
    const antar = formPesanan.querySelector('[name=antar]');
    if (antar && t.dataset.antar) antar.value = t.dataset.antar;
    formPesanan.querySelectorAll('[data-untuk]').forEach(function (el) {
      el.hidden = el.dataset.untuk !== mode;
    });
    // Pesan hanya wajib pada mode pantau; mode lain tidak punya isian itu.
    const pesanEl = formPesanan.querySelector('[name=pesan]');
    if (pesanEl) pesanEl.required = mode === 'pantau';
    modalPesanan.showModal();
  });

  // TIDAK ada penangan submit khusus di sini: form modal bertanda `data-aksi`,
  // jadi penangan DOKUMEN di atas sudah menangkapnya. Menambah penangan kedua
  // membuat satu klik mengirim DUA kali (barang kembar).

  // ------------------------------------------------------- turunkan / tayangkan
  // Turunkan / tayangkan: satu penanganan untuk dua tombol — action-nya sudah
  // dititipkan server di data-url, JS tidak menyusun URL rute sendiri.
  document.addEventListener('click', async function (ev) {
    const b = ev.target.closest('[data-turun], [data-pulih]');
    if (!b) return;
    ev.preventDefault();
    const turun = b.hasAttribute('data-turun');
    if (turun) {
      // Janji di konfirmasi harus sama dengan yang dikerjakan server.
      const tetap = b.dataset.pakai === '1';
      const tanya = tetap
        ? 'Turunkan "' + b.dataset.nama + '" dari katalog? Barang ini pernah dipesan, jadi riwayatnya tetap utuh.'
        : 'Hapus "' + b.dataset.nama + '" dari katalog? Barang ini belum pernah dipesan, jadi dihapus permanen.';
      // Modal aplikasi, bukan window.confirm: satu gaya dengan dialog lain dan
      // bisa menyebut nama barang dengan rapi (lihat hubKonfirmasi di hub.js).
      const ya = await (window.hubKonfirmasi
        ? window.hubKonfirmasi(tanya, tetap ? 'Turunkan Produk' : 'Hapus Produk')
        : Promise.resolve(window.confirm(tanya)));
      if (!ya) return;
    }
    b.disabled = true;
    try {
      const j = await minta(b.dataset.url, { method: 'POST', body: new URLSearchParams({ _token: token }) });
      pesan((j && j.pesan) || (turun ? 'Barang diturunkan.' : 'Barang tayang lagi.'));
      await muat();
    } catch (e) {
      pesan(e.message, true);
    } finally {
      b.disabled = false;
    }
  });
})();

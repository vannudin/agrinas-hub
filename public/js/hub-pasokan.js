/* Pasokan UMKM — kolom Aksi cukup satu tombol "Update", selebihnya di modal.
 *
 * Kenapa: dulu satu baris memuat sampai DUA formulir (Setujui + Tolak) yang
 * masing-masing membawa kotak teksnya sendiri. Sel aksinya jadi 72px tinggi
 * dengan dua kotak tulis, dan untuk menolak pengguna harus menebak kotak mana
 * yang harus diisi. Sekarang: satu tombol, satu modal, satu pemilih status.
 *
 * Prinsip yang sama dengan hub-stok.js: JS tidak menyusun nilai, ia hanya
 * memindahkan apa yang dikirim server (data-*) ke dalam formulir modal.
 */
/* ---------------------------------------------------------------------------
 * Pengajuan & pembayaran (tiga modal: #bayar-ajukan, #bayar-putus, #bayar-bukti).
 *
 * Yang diurus di sini cuma dua hal yang hub.js belum lakukan:
 *   1. `data-aksi` -> action formulir. RUTE BERBEDA PER BARIS (id pengajuan), jadi
 *      tidak bisa ditulis di blade-nya formulir seperti modal biasa.
 *   2. `data-awal.ringkas` -> teks ringkasan di dalam dialog.
 * Pengisian field lain sengaja TIDAK dilakukan: dialog-dialog ini kosong saat
 * dibuka (rekening & invoice selalu diisi baru), bukan disunting dari data lama —
 * kalau diisi otomatis, nilai invoice lama bisa ikut terkirim tanpa disadari.
 * ------------------------------------------------------------------------- */
(function () {
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;
    const t = ev.target.closest('[data-buka][data-aksi]');
    if (!t) return;

    const d = document.getElementById(t.getAttribute('data-buka'));
    if (!d) return;
    const f = d.querySelector('form');
    if (f) f.action = t.getAttribute('data-aksi');

    let ringkas = '';
    try { ringkas = (JSON.parse(t.getAttribute('data-awal') || '{}').ringkas) || ''; } catch (e) { /* tetap kosong */ }
    d.querySelectorAll('[data-ringkas]').forEach(function (e) { e.textContent = ringkas || '—'; });
  });
})();

(function () {
  const modal = document.getElementById('pasokan-modal');
  const form = document.getElementById('pasokan-form');
  if (!modal || !form || typeof modal.showModal !== 'function') return;

  const pilih = document.getElementById('pasokan-status');
  const catatan = document.getElementById('pasokan-catatan');
  const ringkas = document.getElementById('pasokan-ringkas');
  const petunjuk = document.getElementById('pasokan-petunjuk');
  const kabar = document.getElementById('pasokan-modal-kabar');
  const galat = document.getElementById('pasokan-modal-galat');
  const simpan = document.getElementById('pasokan-simpan');

  const LABEL = { DIAJUKAN: 'Diajukan', DISETUJUI: 'Disetujui', DITOLAK: 'Ditolak' };

  /**
   * Titipkan pesan sebelum halaman dimuat ulang.
   *
   * Jawaban aksi ini JSON, jadi tidak ada flash session — pesannya akan hilang
   * tanpa jejak saat reload. hub.js membacanya lalu menampilkan toast 5 detik.
   * Dipakai juga untuk jalur GAGAL: tanpa ini halaman yang gagal memuat ulang
   * hanya terlihat "diam".
   */
  function pesanLepas(teks, rusak) {
    try {
      window.sessionStorage.setItem('hub_toast', JSON.stringify({ teks: teks, rusak: !!rusak }));
    } catch (e) { /* sessionStorage diblokir: notifikasi saja yang hilang */ }
  }

  function bersih() {
    [kabar, galat].forEach(function (e) { if (e) { e.hidden = true; e.textContent = ''; } });
  }

  function pesan(teks, rusak) {
    bersih();
    const el = rusak ? galat : kabar;
    if (!el) return;
    el.textContent = teks;
    el.hidden = false;
  }

  /** Petunjuk berubah mengikuti status: menolak WAJIB beralasan (dijaga server juga). */
  function segarkanPetunjuk() {
    const tolak = pilih.value === 'DITOLAK';
    catatan.placeholder = tolak
      ? 'Wajib — mis. komoditas tidak sesuai pesanan'
      : 'Opsional — mis. dibayar tunai, kirim minggu depan';
    petunjuk.textContent = tolak
      ? 'Menolak pasokan wajib disertai keterangan.'
      : (pilih.value === 'DIAJUKAN'
        ? 'Mengembalikan pasokan ke antrean supaya bisa diputus ulang.'
        : 'Disetujui berarti pasokan dari UMKM ini diterima.');
  }

  function buka(tombol) {
    // Opsi kosong = tak ada perpindahan sah dari status sekarang. Jangan buka
    // modal yang cuma bisa ditutup; arahkan pengguna dengan jelas.
    if (!tombol.dataset.opsi) {
      window.alert('Status ' + tombol.dataset.kode + ' sudah ' + tombol.dataset.status
        + ' — tidak ada perubahan yang bisa dilakukan dari sini.');
      return;
    }
    bersih();
    // Alamat dari server (route()), bukan disusun di sini.
    form.action = tombol.dataset.pasokan;
    ringkas.textContent = tombol.dataset.kode + ' · ' + tombol.dataset.umkm
      + ' · ' + tombol.dataset.komoditas + ' · ' + tombol.dataset.nilai;

    // Opsi HANYA yang sah dari status sekarang — dikirim server, jadi aturan
    // perpindahan tidak digandakan di JavaScript.
    pilih.innerHTML = '';
    tombol.dataset.opsi.split(',').forEach(function (s) {
      const o = document.createElement('option');
      o.value = s;
      o.textContent = LABEL[s] || s;
      pilih.appendChild(o);
    });
    // Bawaan = OPSI PERTAMA yang dikirim server. Jangan memaksa satu nilai
    // tertentu (dulu `pilih.value = 'DISETUJUI'`): pada baris yang statusnya
    // sudah Disetujui, opsi itu memang tidak ditawarkan, sehingga nilai tak
    // ketemu -> selectedIndex -1 -> <select> tampak KOSONG saat modal dibuka.
    pilih.selectedIndex = 0;
    catatan.value = tombol.dataset.catatan || '';
    simpan.textContent = 'Simpan';
    segarkanPetunjuk();
    modal.showModal();
  }

  // ------------------------------------------------ saring tanpa muat ulang
  // hub.js hanya menangani SUBMIT formulir .saring (kata kunci baru berlaku
  // setelah Enter). Untuk daftar pasokan itu terasa seperti pencarian yang tidak
  // jalan, jadi ketikan disaring setelah 320 ms tenang. Yang ditukar hanya baris
  // tabelnya, supaya fokus di kotak cari tidak hilang.
  // Elemen dibaca ULANG tiap kali: hub.js menukar isi wadah #ps saat paging,
  // sehingga pegangan ke elemen lama menunjuk node yang sudah dibuang.
  const wadahBaris = () => document.getElementById('ps-baris');
  const wadahJumlah = () => document.getElementById('ps-jumlah');

  function segarkanBersih() {
    const f = document.querySelector('#ps form.saring');
    const b = document.querySelector('#ps .bersih-saring');
    if (!f || !b) return;
    b.hidden = ![...new FormData(f)].some(function (p) { return String(p[1]).trim() !== ''; });
  }

  async function muatDaftar() {
    const isi = wadahBaris();
    const formSaring = document.querySelector('#ps form.saring');
    if (!isi || !formSaring) return;
    const u = new URL(document.getElementById('ps').dataset.daftar, window.location.origin);
    new URLSearchParams(new FormData(formSaring)).forEach(function (v, k) {
      if (v !== '') u.searchParams.set(k, v);
    });
    try {
      const res = await fetch(u.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin',
      });
      if (!res.ok) return;
      isi.innerHTML = await res.text();
      const jm = wadahJumlah();
      if (jm) jm.textContent = (res.headers.get('X-Jumlah') || 0) + ' pasokan';
    } catch (e) { /* tabel menampilkan data lama; pesan galat sudah tampil */ }
  }

  let jeda = null;
  document.addEventListener('input', function (ev) {
    const f = ev.target.closest ? ev.target.closest('#ps form.saring') : null;
    if (!f || ev.target.tagName === 'SELECT') return;
    segarkanBersih();
    window.clearTimeout(jeda);
    jeda = window.setTimeout(muatDaftar, 320);
  });
  document.addEventListener('change', function (ev) {
    if (!ev.target.closest) return;
    if (ev.target.closest('#ps form.saring select')) { segarkanBersih(); muatDaftar(); }
  });

  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;
    const t = ev.target.closest('[data-pasokan]');
    if (t) { ev.preventDefault(); buka(t); return; }
    if (ev.target.closest('#pasokan-modal [data-tutup]')) { ev.preventDefault(); modal.close(); }
  });

  pilih.addEventListener('change', segarkanPetunjuk);
  modal.addEventListener('close', bersih);

  // Kirim lewat fetch: modal tetap terbuka saat galat, dan pesan sukses muncul
  // di dalamnya lalu halaman dimuat ulang supaya daftar & hitungan kartu akurat
  // (status pasokan memengaruhi dua kartu sekaligus, jadi menukar satu baris
  // saja akan membuat kartunya berbohong).
  form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    simpan.disabled = true;
    bersih();
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value,
                   'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: new FormData(form),
        credentials: 'same-origin',
      });
      if (res.redirected) { window.location.href = res.url; return; }
      let j = {};
      try { j = await res.json(); } catch (e) { j = {}; }
      if (res.status === 422) {
        pesan(Object.values(j.errors || {}).flat().join(' ') || j.galat || 'Isian belum lengkap.', true);
        return;
      }
      if (!res.ok || j.ok === false) {
        // Ditolak server dengan status yang JELAS (mis. perpindahan tak sah):
        // tutup modal, titipkan alasannya, lalu muat ulang supaya tabel & kartu
        // memperlihatkan keadaan sebenarnya.
        if (res.status === 422 && (j.galat || '').length > 0) {
          modal.close();
          pesanLepas(j.galat, true);
          window.location.reload();
          return;
        }
        pesan(j.galat || ('Gagal menyimpan (HTTP ' + res.status + ').'), true);
        return;
      }
      // Sukses: TITIPKAN pesan lalu segarkan halaman. Kartu status & hitungannya
      // ikut berubah, dan jawaban AJAX berbentuk JSON sehingga tak ada flash
      // session — tanpa titipan ini pengguna tidak melihat notifikasi apa pun.
      modal.close();
      pesanLepas(j.pesan || 'Status pasokan diperbarui.');
      window.location.reload();
    } catch (e) {
      form.submit();      // jaringan putus: kirim sebagai formulir biasa
    } finally {
      simpan.disabled = false;
    }
  });
})();

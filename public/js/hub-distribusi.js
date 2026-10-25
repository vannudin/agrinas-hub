/* Distribusi — rincian Surat Jalan sebagai modal.
 *
 * Kenapa modal: halaman rincian memaksa perpindahan halaman hanya untuk melihat
 * tiga baris barang dan menekan "Kirim Barang"; setelah kirim, pengguna dibuang
 * ke daftar dan kehilangan halaman/filter yang sedang dikerjakan.
 *
 * Modal memakai <dialog> bawaan peramban: fokus terkunci, Esc menutup, latar
 * inert, dan lapisan atas — semuanya sudah ada, tak perlu ditulis.
 *
 * Dua hal yang sengaja TIDAK dilakukan skrip ini:
 *   - mengirim formulir. Tombolnya `type="submit" form="f-kirim"` (atribut HTML
 *     biasa), jadi CSRF, validasi, dan aturan kewenangan tetap jalur server yang
 *     sama seperti sebelumnya. Skrip hanya MEMANTAU pengiriman itu lewat fetch
 *     supaya modal tidak berpindah halaman.
 *   - menyimpan markup panel. Panelnya diambil dari server setiap kali dibuka —
 *     satu sumber kebenaran, jadi tak ada salinan yang bisa basi.
 */
(function () {
  if (typeof HTMLDialogElement === 'undefined') return;   // <a> tetap bekerja

  /**
   * Modal & isinya dicari ULANG setiap kali dipakai — JANGAN disimpan di
   * variabel sekali di awal.
   *
   * Sebabnya: hub.js menukar ISI wadah [data-isi] saat paging/saring. Elemen
   * yang disimpan lebih dulu menunjuk node yang sudah dibuang dari dokumen,
   * sehingga `modal.open` selalu false, `showModal()` menulis ke dialog mati,
   * dan tombol Rincian tampak "tidak bereaksi" — persis setelah ganti halaman.
   * Modal kini juga diletakkan DI LUAR wadah itu (lihat daftar.blade.php),
   * tetapi membacanya ulang membuat skrip ini tetap benar walau nanti dipindah.
   */
  const elModal = () => document.getElementById('rincian-modal');
  const elIsi = () => document.getElementById('rincian-modal-isi');

  let sibuk = false;

  function pesanKesalahan(html) {
    const isi = elIsi();
    if (isi) isi.insertAdjacentHTML('afterbegin', '<div class="pesan rusak" role="alert">' + html + '</div>');
  }

  async function buka(url) {
    const modal = elModal(), isi = elIsi();
    if (!modal || !isi) return;
    // Dialog bisa sudah terbuka (memuat ulang setelah aksi): isinya ditukar
    // tanpa memanggil showModal() dua kali.
    if (!modal.open) {
      isi.innerHTML = '<p class="bantuan sedang-muat">Memuat rincian…</p>';
      modal.showModal();
    }
    isi.classList.add('memuat');
    try {
      const res = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin',
      });
      // Sesi habis: serahkan ke navigasi biasa, jangan tampilkan panel kosong.
      if (res.redirected) { window.location.href = res.url; return; }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      isi.innerHTML = await res.text();
    } catch (e) {
      isi.innerHTML = '';
      pesanKesalahan('Rincian tidak bisa dimuat. Muat ulang halaman, atau buka '
        +'<a href="'+url.replace('/panel', '')+'">halamannya langsung</a>.');
    } finally {
      isi.classList.remove('memuat');
    }
  }

  // ------------------------------------------------ saring tanpa muat ulang
  // hub.js hanya menangani SUBMIT formulir .saring (kata kunci baru berlaku
  // setelah Enter), jadi ketikan disaring sendiri setelah 320 ms tenang.
  // Baris dibaca ULANG tiap kali; hub.js menukar isi wadah [data-isi].
  const elBaris = () => document.getElementById('ds-baris');
  const elJumlah = () => document.getElementById('ds-jumlah');

  function segarkanBersih() {
    const f = document.querySelector('#ds form.saring');
    const b = document.querySelector('#ds .bersih-saring');
    if (!f || !b) return;
    b.hidden = ![...new FormData(f)].some(function (p) { return String(p[1]).trim() !== ''; });
  }

  async function muatDaftar() {
    const isi = elBaris();
    const wadah = document.getElementById('ds');
    const formSaring = document.querySelector('#ds form.saring');
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
      if (jm) jm.textContent = (res.headers.get('X-Jumlah') || 0) + ' surat jalan';
    } catch (e) { /* tabel menampilkan data lama; pesan galat sudah tampil */ }
  }

  let jeda = null;
  document.addEventListener('input', function (ev) {
    const f = ev.target.closest ? ev.target.closest('#ds form.saring') : null;
    if (!f || ev.target.tagName === 'SELECT') return;
    segarkanBersih();
    window.clearTimeout(jeda);
    jeda = window.setTimeout(muatDaftar, 320);
  });
  document.addEventListener('change', function (ev) {
    if (!ev.target.closest) return;
    if (ev.target.closest('#ds form.saring select')) { segarkanBersih(); muatDaftar(); }
  });

  // -------------------------------------------------------------- membuka
  // Delegasi di document, bukan ikat elemen: halaman ini punya wadah [data-isi]
  // yang isinya ditukar hub.js saat paging/saring, sehingga penanganan yang
  // diikat langsung akan mati pada pertukaran pertama.
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest) return;

    const a = ev.target.closest('[data-rincian-buka]');
    if (a) {
      ev.preventDefault();
      buka(a.dataset.rincianUrl);
      return;
    }

    if (ev.target.closest('[data-kembali]')) {         // tombol Tutup/Kembali
      ev.preventDefault();
      const modal = elModal();
      if (modal && modal.open) modal.close();
      return;
    }

    // Unduhan surat jalan: biarkan peramban menanganinya. Modal ditutup lebih
    // dulu supaya berkasnya tidak diunduh di balik lapisan yang masih terbuka.
    const unduh = ev.target.closest('a[href$="surat-jalan.pdf"]');
    const modal = elModal();
    if (unduh && modal && modal.open) modal.close();
  });

  // -------------------------------------------------------------- menyimpan
  // Kirim/proses/tolak/terima dijalankan lewat fetch supaya modal TETAP terbuka:
  // server membalas potongan panel yang sudah diperbarui beserta pesannya.
  // Tanpa JavaScript, formulirnya tetap <form method="post"> biasa yang
  // mengalihkan halaman — perilaku lama tidak hilang.
  // Didaftarkan di DOKUMEN, bukan di modal: modalnya bisa diganti oleh
  // pertukaran isi wadah, dan penangan yang menempel di elemen lama akan mati.
  document.addEventListener('submit', async function (ev) {
    const f = ev.target;
    if (!(f instanceof HTMLFormElement) || !f.closest('#rincian-modal') || sibuk) return;
    const isi = elIsi();
    if (!isi) return;
    ev.preventDefault();
    sibuk = true;
    isi.classList.add('memuat');

    try {
      const res = await fetch(f.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        body: new FormData(f),
        credentials: 'same-origin',
      });
      if (res.redirected) { window.location.href = res.url; return; }

      if (res.status === 422) {                       // galat validasi
        const j = await res.json().catch(function () { return {}; });
        // DOM tidak disentuh: server menolak SEBELUM menyimpan, jadi isian di
        // layar masih yang benar — cukup tampilkan alasannya di atasnya.
        pesanKesalahan(Object.values(j.errors || {}).flat().join(' ') || 'Isian belum lengkap.');
        return;
      }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      isi.innerHTML = await res.text();               // panel terbaru dari server
    } catch (e) {
      // Gagal jaringan: kirim sebagai formulir biasa supaya aksi yang sudah
      // diketik pengguna tidak hilang begitu saja.
      f.submit();
      return;
    } finally {
      sibuk = false;
      isi.classList.remove('memuat');
    }
  });

  // Escape / klik latar: bersihkan isi supaya tidak ada sisa panel lama.
  // Dipasang di dokumen juga (modal bisa berganti node setelah pertukaran isi).
  document.addEventListener('close', function (ev) {
    if (ev.target && ev.target.id === 'rincian-modal') {
      const isi = elIsi();
      if (isi) isi.innerHTML = '';
    }
  }, true);
})();

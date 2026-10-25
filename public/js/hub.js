/* Hub — pemuatan data lewat AJAX + animasi proses.
 *
 * Dua bagian yang SALING LEPAS:
 *
 * 1. Animasi proses — berlaku di SETIAP halaman. Tombol yang dikirim menampilkan
 *    spinner, dan bilah tipis di atas layar menyala selama permintaan AJAX.
 * 2. Pemuatan AJAX — hanya bila halaman menandai wadahnya:
 *
 *      <div class="blok" id="daftar" data-isi="{{ route('admin.daftar') }}"
 *           data-params="status,q,halaman"> …tabel + .paging… </div>
 *
 *    `data-params` = nama parameter yang dikirim; nilainya diambil dari elemen
 *    `[data-param=...]` atau `[name=...]` di halaman.
 *
 * TANPA JS halaman tetap jalan penuh: tautan paging & tombol saring adalah
 * <a>/<form> biasa, server menjawab HTML utuh.
 */
(function () {
  // ---------------------------------------------- 0. notifikasi melayang (toast)
  //
  // Satu tempat untuk seluruh aplikasi. Dua sumber:
  //   a. pesan yang DITITIPKAN sebelum muat ulang (sessionStorage) — dipakai aksi
  //      AJAX yang menyegarkan halaman, karena jawabannya JSON sehingga tak ada
  //      flash session;
  //   b. pesan flash yang sudah dirender server di dalam halaman (redirect biasa).
  //
  // Yang TIDAK diangkat jadi toast: `.pesan.kunci` (sandi sekali tampil) dan
  // `.pesan.ingat` — keduanya harus tetap terbaca sampai pengguna sengaja
  // menutupnya, bukan hilang sendiri setelah 5 detik.
  const DURASI = 5000;

  function tampilkanToast(teks, rusak) {
    if (!teks) return;
    let wadah = document.getElementById('hub-toast');
    if (!wadah) {
      wadah = document.createElement('div');
      wadah.id = 'hub-toast';
      wadah.setAttribute('aria-live', 'polite');
      document.body.appendChild(wadah);
    }
    const k = document.createElement('div');
    k.className = 'toast ' + (rusak ? 'rusak' : 'ok');
    k.setAttribute('role', rusak ? 'alert' : 'status');
    k.textContent = teks;
    wadah.appendChild(k);
    // Dibuang setelah animasi keluar selesai; durasinya dari CSS.
    window.setTimeout(function () {
      k.classList.add('keluar');
      window.setTimeout(function () { k.remove(); }, 400);
    }, DURASI);
  }
  window.hubToast = tampilkanToast;   // dipakai skrip halaman (mis. hub-pasokan.js)

  // (a) titipan sebelum muat ulang
  try {
    const t = window.sessionStorage.getItem('hub_toast');
    if (t) {
      window.sessionStorage.removeItem('hub_toast');
      const p = JSON.parse(t);
      tampilkanToast(p.teks, !!p.rusak);
    }
  } catch (e) { /* sessionStorage diblokir: lewati, bukan alasan menghentikan skrip */ }

  // (b) flash server yang sudah ada di halaman
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pesan.ok, .pesan.rusak').forEach(function (el) {
      if (el.classList.contains('kunci') || el.classList.contains('ingat')) return;
      if (el.closest('.modal-badan') || el.closest('dialog')) return;  // pesan dalam modal: biarkan
      tampilkanToast(el.textContent.trim(), el.classList.contains('rusak'));
      el.hidden = true;    // aslinya sudah tampil sebentar; jangan dobel
    });
  });

  const indeks = document.getElementById('hub-proses');

  function mulaiProses() { if (indeks) indeks.hidden = false; }
  function selesaiProses() { if (indeks) indeks.hidden = true; }

  // ---------------------------------------------- 1. animasi proses (semua halaman)
  document.addEventListener('submit', function (ev) {
    const f = ev.target;
    if (!(f instanceof HTMLFormElement)) return;
    if (f.matches('form[data-ai-kirim]') || f.closest('#ai-contoh')) return;  // diurus hub-asisten.js
    if (f.matches('form[data-mulai]')) return;                               // diurus hub-obrolan.js
    if (f.matches('form[data-saring]')) return;                              // diurus AJAX di bawah

    const t = f.querySelector('[type="submit"]:not([disabled])');
    if (!t || t.querySelector('.spinner')) return;
    t.classList.add('sedang');
    t.insertAdjacentHTML('afterbegin', '<span class="spinner"></span>');
    mulaiProses();
    // Tombol di-disable SETELAH form benar-benar terkirim: kalau di-disable di
    // sini, nama/nilai tombolnya ikut hilang dari data yang dikirim.
    window.setTimeout(function () { t.disabled = true; }, 0);
  });

  // ---------------------------------------------- 2. pemuatan AJAX (per wadah)
  //
  // Wadah isi ditentukan otomatis: halaman ber-login SELALU punya <main id="isi">
  // (layout utama), jadi tak satu pun blade perlu ditandai. Wadah bertanda
  // [data-isi] tetap didukung dan dipakai lebih dulu — di dalamnya bisa ada
  // wadah yang lebih sempit lagi (halaman admin memberi #daftar).
  //
  // Halaman publik & /masuk memakai <main class="isi polos"> TANPA id, jadi
  // keduanya otomatis tidak di-AJAX-ken.
  const isiUtama = document.getElementById('isi');
  if (!window.fetch || !isiUtama) return;

  function wadahDari(el) {
    return el.closest('[data-isi]') || (isiUtama.contains(el) ? isiUtama : null);
  }

  function pencariWadah(w) {
    if (w.id === 'isi' && !w.hasAttribute('data-isi')) return '#isi';
    // Id dipakai bila ada, bukan `[data-isi]`: satu halaman boleh memuat DUA
    // wadah ber-`data-isi` (riwayat presensi `#pr` + daftar hadir KDKMP
    // `#absen`), dan `querySelector('[data-isi]')` SELALU memulangkan yang
    // PERTAMA — sehingga memindah halaman daftar hadir justru menukar isinya
    // dengan riwayat presensi: tabel salah, tanpa galat, tanpa sebab terlihat.
    return w.id ? '#' + (window.CSS && CSS.escape ? CSS.escape(w.id) : w.id) : '[data-isi]';
  }

  let sedang = false;

  async function muat(url, wadah) {
    if (sedang) return;
    sedang = true;
    mulaiProses();
    wadah.classList.add('memuat');

    try {
      const res = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin',
      });
      // Sesi habis / dialihkan (mis. ke /masuk): AJAX tak bisa memindahkan
      // halaman, jadi serahkan ke navigasi biasa. Tanpa ini pengguna hanya
      // melihat spinner lalu tidak terjadi apa-apa.
      if (res.redirected) {
        window.location.href = res.url;
        return;
      }
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const p = new DOMParser().parseFromString(await res.text(), 'text/html');
      const baru = p.querySelector(pencariWadah(wadah));
      if (!baru) throw new Error('isi tidak ditemukan');

      // Fokus & kata kunci yang sedang diketik DIPULIHKAN setelah penukaran.
      // Bilah saring boleh berada DI DALAM wadah yang ditukar (mis.
      // /distribusi/stok-koperasi, agar ikut tersegarkan saat menyaring), dan
      // tanpa ini kotak cari yang sedang diisi ikut dibuang: ketikan berikutnya
      // jatuh ke <body> — terbaca sebagai "pencarian yang tidak jalan".
      // Dipulihkan lewat NAMA kolom, bukan simpul: simpulnya memang sudah baru.
      const aktif = document.activeElement;
      const pulih = aktif && wadah.contains(aktif) && aktif.name
        ? { nama: aktif.name, ekor: aktif.selectionEnd === null ? null : aktif.selectionEnd }
        : null;

      // Tukar isi SAJA: menu samping, bilah bawah, dan aset tak tersentuh,
      // jadi tak ada kilatan layout dan fokus pengguna tidak hilang.
      wadah.innerHTML = baru.innerHTML;

      if (pulih) {
        var lagi = wadah.querySelector('[name="' + (window.CSS && CSS.escape ? CSS.escape(pulih.nama) : pulih.nama) + '"]');
        if (lagi) {
          if (pulih.ekor !== null) { try { lagi.setSelectionRange(pulih.ekor, pulih.ekor); } catch (e) {} }
          lagi.focus();
        }
      }

      // Ikut menyegarkan baris status DI LUAR wadah (mis. "Gabungan seluruh
      // koperasi" vs "<kode> · <nama>" di header /keuangan). Tanpa ini, memilih
      // satu koperasi menukar isi tabel tetapi header tetap mengaku agregat —
      // pengguna melihat dua kebenaran yang berbeda di satu halaman.
      // Umum: elemen ber-`data-kop-status` di kedua dokumen ditukar isinya.
      const statusLama = document.querySelector('[data-kop-status]');
      const statusBaru = p.querySelector('[data-kop-status]');
      if (statusLama && statusBaru) statusLama.innerHTML = statusBaru.innerHTML;

      // Alamat ikut berubah supaya tombol Back & tautan yang dibagikan benar.
      window.history.replaceState(null, '', url);
    } catch (e) {
      // Gagal AJAX = jatuh kembali ke muat ulang penuh. Halaman tidak boleh
      // diam-diam kehilangan data hanya karena satu permintaan gagal.
      window.location.href = url;
      return;
    } finally {
      sedang = false;
      wadah.classList.remove('memuat');
      selesaiProses();
    }
  }

  // Klik tautan diambil alih HANYA bila tujuannya HALAMAN YANG SAMA dengan query
  // berbeda (`?halaman=2`, `?status=DIKIRIM`) — itu selalu berarti daftar/filter
  // di halaman ini, aman ditukar. Tautan ke halaman lain (mis. "Buka" rincian
  // tugas) punya pathname berbeda → tetap muat ulang penuh, karena halaman
  // tujuannya memang harus muncul. Lebih ringkas & lebih benar daripada
  // mengecek kelas `.paging` satu per satu: kartu status yang berupa tautan
  // filter ikut tertangkap tanpa perlu ditandai.
  document.addEventListener('click', function (ev) {
    const a = ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a || a.origin !== window.location.origin) return;

    // Kisi menu = NAVIGASI, bukan saringan. Karena butir kisi bisa punya
    // pathname YANG SAMA dengan halaman yang sedang dibuka, aturan "pathname
    // sama -> tukar isi saja" di bawah salah menangkapnya: menekan "Ringkasan"
    // di /agri atau "Pelatihan" di /agri/pelatihan menukar isi <main>, sehingga
    // halaman tujuan muncul DI BAWAH kisi menunya, di alamat yang sama.
    if (a.closest('.kisi-modul, .kisi-fitur')) return;

    const href = a.getAttribute('href') || '';
    if (href === '' || href.startsWith('#')) return;     // jangkar / tombol JS

    // Tautan yang SENGAJA membuka tab/jendela baru (target="_blank" dll) tidak
    // boleh diambil alih: peramban sudah punya tujuan sendiri, dan menukar isi
    // di tempat justru membatalkan maksud tautannya.
    if (a.target && a.target !== '_self') return;

    const tujuan = new URL(a.href, window.location.origin);
    if (tujuan.pathname !== window.location.pathname) return;   // halaman lain
    if (tujuan.href === window.location.href) return;           // tak ada perubahan

    const wadah = wadahDari(a);
    if (!wadah) return;

    ev.preventDefault();
    muat(tujuan.toString(), wadah);
  });

  // ---------------------------------------------- 3. saring tanpa muat ulang
  //
  // Form `.saring` (GET) memuat ulang seluruh halaman hanya untuk mengganti
  // satu tabel. Selama wadah isinya bisa ditemukan, ambil alih: nilai form
  // diserahkan sebagai parameter query. Tanpa JS form tetap form GET biasa.
  document.addEventListener('submit', function (ev) {
    const f = ev.target.closest ? ev.target.closest('form.saring[method="get"]') : null;
    if (!f) return;

    const wadah = wadahDari(f);
    if (!wadah) return;

    ev.preventDefault();
    const u = new URL(f.action, window.location.origin);
    new FormData(f).forEach(function (v, k) {
      if (v !== '') u.searchParams.set(k, v);
    });
    // Saring baru = kembali ke halaman 1. SEMUA kunci halaman dibuang, bukan
    // hanya 'halaman': satu halaman boleh memuat dua daftar berhalaman sekaligus
    // (mis. riwayat presensi `halaman` + daftar hadir KDKMP `ahalaman`), dan
    // menyaring satu daftar sambil menyisakan nomor halaman daftar lain membuat
    // daftar itu tampak kosong tanpa sebab.
    [...u.searchParams.keys()].forEach(function (k) {
      if (/^halaman(_|$)/.test(k)) u.searchParams.delete(k);
    });
    muat(u.toString(), wadah);
  });

  // Memilih dari <select> di dalam .saring harus langsung menyaring. Banyak
  // halaman menaruh tombol submit-nya di dalam <noscript> (tanpa JS form tetap
  // form GET biasa), jadi tanpa handler ini mengubah pilihan tidak melakukan
  // apa pun sampai Enter ditekan — terasa seperti saringan rusak.
  // Kotak teks TIDAK diambil alih: Enter sudah bekerja, dan menyaring tiap
  // ketikan akan membuang fokus pengguna.
  document.addEventListener('change', function (ev) {
    const s = ev.target;
    if (!s || s.tagName !== 'SELECT' || s.multiple) return;
    const f = s.form;
    if (!f || !f.matches('form.saring[method="get"]')) return;
    if (wadahDari(f)) {
      if (f.requestSubmit) f.requestSubmit();
      else f.submit();
    }
  });

/*
 * Konfirmasi tindakan merusak: `data-konfirmasi="teks"` pada <form>.
 *
 * Dulu penangan ini hanya ada di hub-agri.js, padahal halaman pengelola
 * (/admin/rapat, /distribusi/stok-koperasi) TIDAK memuat berkas itu —
 * akibatnya atributnya mati dan tombol "Hapus" di sana langsung menghapus.
 * Ditaruh di hub.js yang dimuat layout pada SETIAP halaman.
 *
 * Sekarang konfirmasinya memakai JENDELA MODAL (`#konfirmasi-modal`, ada di
 * layout), bukan `window.confirm()`: kotak bawaan peramban tidak bisa
 * menyebut nama barang/akun dengan rapi, tidak bergaya aplikasi, dan di
 * ponsel muncul sebagai dialog sistem. Satu modal melayani SEMUA tombol hapus/
 * turunkan di seluruh Hub — hub-katalog.js memakai `window.hubKonfirmasi`.
 *
 * Idempoten lewat penanda di elemen: di halaman yang memuat hub.js DAN
 * hub-agri.js, tanpa penanda ini pengguna ditanya dua kali.
 */
window.hubKonfirmasi = function (pesan, judul) {
  var d = document.getElementById('konfirmasi-modal');
  if (!d || typeof d.showModal !== 'function') {
    return Promise.resolve(window.confirm(pesan));   // tanpa modal: tetap aman
  }
  if (d.open) d.close();
  d.querySelector('[data-konfirmasi-teks]').textContent = pesan || 'Lanjutkan?';
  var h = d.querySelector('.modal-kepala h2');
  if (h) h.textContent = judul || 'Konfirmasi';
  d.showModal();
  return new Promise(function (selesai) {
    function tutup(jawab) {
      d.removeEventListener('close', onClose);
      selesai(jawab);
    }
    function onClose() {
      tutup(d.returnValue === 'ya');
    }
    d.addEventListener('close', onClose);
  });
};

document.addEventListener('submit', function (ev) {
  var f = ev.target;
  if (!f || !f.getAttribute || !f.getAttribute('data-konfirmasi')) return;
  if (f.dataset.konfirmasiSiap === '1') {
    f.dataset.konfirmasiSiap = '';   // sekali pakai: kiriman berikutnya ditanya lagi
    return;
  }
  ev.preventDefault();
  window.hubKonfirmasi(f.getAttribute('data-konfirmasi'),
    f.getAttribute('data-konfirmasi-judul')).then(function (ya) {
      if (!ya) return;
      f.dataset.konfirmasiSiap = '1';
      if (f.requestSubmit) f.requestSubmit();
      else f.submit();
    });
}, true);

/* -------------------------------------------------------------------- dialog
 * Mekanisme serbaguna untuk <dialog class="modal">:
 *   [data-buka="#id-dialog"]  membuka  (mis. tombol "New Document")
 *   [data-tutup]              menutup dialog terdekat
 *
 * Ditaruh di sini karena SETIAP halaman memuat hub.js: satu penangan melayani
 * semua dialog, jadi halaman baru tidak perlu menulis JS-nya sendiri (dan tidak
 * bisa lupa, sehingga tombolnya diam tanpa galat).
 */
document.addEventListener('click', function (ev) {
  if (!ev.target.closest) return;

  var buka = ev.target.closest('[data-buka]');
  if (buka) {
    var id = (buka.getAttribute('data-buka') || '').replace(/^#/, '');
    var d = id ? document.getElementById(id) : null;
    if (d && typeof d.showModal === 'function') {
      ev.preventDefault();
      // `data-awal` = JSON {namaField: nilai}: pengisi awal untuk dialog. Dipakai
      // kartu jenis dokumen — satu klik pada kartu Word/Excel/PPT/PDF membuka
      // dialog yang jenisnya sudah terpilih, tanpa JS khusus per halaman.
      var awal = buka.getAttribute('data-awal');
      if (awal) {
        try {
          var nilai = JSON.parse(awal);
          Object.keys(nilai).forEach(function (n) {
            var el = d.querySelector('[name="' + n + '"]');
            if (!el) return;
            if (el.type === 'radio') {
              var r = d.querySelector('[name="' + n + '"][value="' + nilai[n] + '"]');
              if (r) r.checked = true;
            } else {
              el.value = nilai[n];
            }
          });
          // Sebagian dialog bersifat BACA-SAJA (mis. "Detail catatan"): di sana
          // tidak ada <input>, hanya elemen ber-`data-teks`. Nilai yang sama
          // diisikan sebagai TEXT — bukan innerHTML — supaya isi catatan tidak
          // pernah bisa menjadi markup.
          Object.keys(nilai).forEach(function (n) {
            d.querySelectorAll('[data-teks="' + n + '"]').forEach(function (t) {
              t.textContent = nilai[n];
              // Elemen yang isinya kosong disembunyikan; yang berisi dimunculkan.
              // Tanpa ini bagian opsional (mis. kendala) tetap tersembunyi walau
              // datanya ADA — jadi terbaca seolah catatannya tidak punya kendala.
              t.hidden = (nilai[n] === '' || nilai[n] === null || nilai[n] === undefined);
            });
          });
        } catch (e) { /* data-awal rusak: dialog tetap terbuka apa adanya */ }
      }
      // Judul dialog: HANYA bila pembukanya memintanya (deklaratif).
      var judul = buka.getAttribute('data-judul-dialog');
      var h2 = d.querySelector('.modal-kepala h2');
      if (judul && h2) h2.textContent = judul;

      if (!d.open) d.showModal();
      var fokus = d.querySelector('input[name="judul"], input:not([type=hidden]), select, textarea');
      if (fokus) fokus.focus();
    }
    return;
  }

  var tutup = ev.target.closest('[data-tutup]');
  if (tutup) {
    var dlg = tutup.closest('dialog');
    if (dlg) dlg.close();
  }
});

/* --------------------------------------------------------------------------
 * Tombol menu akun di pojok kanan atas (ponsel): dibuka & ditutup dari SATU
 * tombol. Satu penangan di sini, bukan skrip per halaman — hub.js dimuat semua.
 *
 * Dua jebakan yang sudah terbukti menahan penutupan panel ini:
 *  1. `showModal()` memunculkan lapisan backdrop dan membuat kotak dialog melebar
 *     menutupi seluruh layar (sisa `inset: 0` bawaan), sehingga klik kedua jatuh
 *     ke DIALOG, bukan ke tombolnya — persis keluhan "diklik lagi tidak menutup".
 *  2. Memeriksa "di luar panel" dengan `closest('.nama-panel')` selalu salah,
 *     sebab ev.target untuk klik di backdrop ADALAH dialog itu sendiri.
 * Karena panel ini cuma popover akun (bukan dialog yang harus mengunci layar),
 * yang tepat justru dialog NON-MODAL: `show()`. Tanpa backdrop, tombolnya tetap
 * bisa diklik dan klik di luar benar-benar mengenai halaman.
 * Esc tetap ditangani sendiri — perilaku yang biasa diharapkan dari popover.
 */
document.addEventListener('click', function (ev) {
  var panel = document.getElementById('menu-kanan');
  var tombol = ev.target.closest ? ev.target.closest('.menu-kanan') : null;

  if (tombol) {
    if (panel.open) {
      panel.close();
      tombol.setAttribute('aria-expanded', 'false');
    } else {
      panel.show();
      tombol.setAttribute('aria-expanded', 'true');
      // Fokus dipindahkan ke butir pertama: tanpa modal, peramban tidak
      // memindahkannya sendiri — dan tanpa itu panel sulit dipakai keyboard.
      var butir = panel.querySelector('a, button');
      if (butir) butir.focus();
    }
    return;
  }

  // Klik di luar panel = tutup. Dikecualikan bila panelnya memang belum terbuka,
  // dan bila ada dialog lain yang terbuka (jangan menutup yang bukan milik kita).
  if (panel.open && !panel.contains(ev.target)) {
    panel.close();
    var t = document.querySelector('.menu-kanan');
    if (t) t.setAttribute('aria-expanded', 'false');
  }
});

/* Esc menutup panel, dan fokus dikembalikan ke tombolnya. Dialog non-modal tidak
   mendapat perilaku ini dari peramban (itu hanya milik mode modal). */
document.addEventListener('keydown', function (ev) {
  if (ev.key !== 'Escape') return;
  var panel = document.getElementById('menu-kanan');
  if (!panel || !panel.open) return;
  panel.close();
  var t = document.querySelector('.menu-kanan');
  if (t) { t.setAttribute('aria-expanded', 'false'); t.focus(); }
});

/* Size berubah (mis. ponsel diputar jadi lebar): tutup panel supaya tidak
   tertinggal terbuka dalam bentuk yang tak seharusnya (panelnya display:none
   di desktop, dan <dialog> yang terbuka sementara induknya disembunyikan bisa
   menyandera fokus). */
window.addEventListener('resize', function () {
  var panel = document.getElementById('menu-kanan');
  if (panel && panel.open && window.matchMedia('(min-width: 901px)').matches) {
    panel.close();
  }
});

/* Pil saran: satu klik mengisi satu field (mis. nama catatan yang sering dipakai).
   Generik, jadi ditaruh di sini, bukan skrip per halaman:
   data-pil-judul="teks" mengisi field bernama `judul` di dialog terdekat. */
document.addEventListener('click', function (ev) {
  if (!ev.target.closest) return;
  var p = ev.target.closest('[data-pil-judul]');
  if (!p) return;
  var dlg = p.closest('dialog');
  if (!dlg) return;
  var isian = dlg.querySelector('[name="judul"]');
  if (isian) {
    isian.value = p.getAttribute('data-pil-judul');
    isian.focus();
  }
});

/* Judul dialog TIDAK pernah ditebak dari isi form.
 *
 * Percobaan pertama menebak: kalau `[name="id"]` terisi, judul berawalan
 * "Tambah"/"New" diganti "Ubah". Niatnya baik, tetapi ia MENYIMPAN judul asli di
 * `data-dasar` lalu memakainya ulang pada pembukaan berikutnya — sehingga halaman
 * yang memang mengubah judulnya sendiri (mis. /admin/barang menampilkan nama
 * barang) dikembalikan ke judul lama. Satu handler generik merusak halaman lain.
 *
 * Sekarang deklaratif: pembukanya boleh menyebut judul lewat `data-judul-dialog`.
 * Tanpa atribut itu, judul dibiarkan apa adanya — mustahil mengganggu halaman
 * yang tidak memintanya. */

/* --------------------------------------------------------------------------
 * Bilah saring ber-`data-otomatis`: kirim sendiri.
 *
 * Atribut ini sudah dipakai di banyak halaman (dokumen, catatan, kalender, …)
 * tetapi SELAMA INI TIDAK ADA yang menanganinya — jadi mengetik di kotak cari
 * atau mengubah pilihan saring tidak melakukan apa pun sampai tombol Filter
 * ditekan. Satu handler di sini membuat seluruh halaman itu benar sekaligus,
 * alih-alih menambah skrip per halaman.
 *
 * Diketik = ditunda 400 ms supaya tidak ada satu permintaan per huruf; memilih
 * dari <select> langsung dikirim.
 */
document.addEventListener('input', function (ev) {
  var el = ev.target;
  if (!el.form || !el.form.hasAttribute('data-otomatis') || el.type === 'file') return;
  if (el.tagName === 'SELECT') return;
  clearTimeout(el._tundaSaring);
  el._tundaSaring = setTimeout(function () { el.form.requestSubmit ? el.form.requestSubmit() : el.form.submit(); }, 400);
});
document.addEventListener('change', function (ev) {
  var el = ev.target;
  if (el.tagName !== 'SELECT' || !el.form || !el.form.hasAttribute('data-otomatis')) return;
  el.form.requestSubmit ? el.form.requestSubmit() : el.form.submit();
});

/* --------------------------------------------------------------------------
 * Segarkan angka lonceng pengumuman sekali saat halaman siap.
 *
 * Angkanya dirender server saat halaman dimuat. Halaman ber-`data-isi`
 * memutakhirkan BAGIAN isi lewat AJAX, jadi kepala/sisi (tempat lonceng) tidak
 * ikut dirender ulang: pengumuman yang baru terbit setelah halaman terbuka tidak
 * pernah muncul di lonceng selama tab dibiarkan atau sampai muat ulang penuh.
 * Satu permintaan ke daftar pengumuman menutup celah itu — server mengirim
 * markup yang sudah benar, kita hanya memindahkan angkanya.
 *
 * ponytail: interval 60 detik + saat tab kembali dilihat. Selama tab tidak
 * dilihat tidak ada permintaan sama sekali; turunkan jadi push/SSE kalau ternyata
 * lonceng harus berubah dalam hitungan detik.
 */
(function () {
  if (document.querySelectorAll('a[href*="/pengumuman"]').length === 0) return;
  var muat = function () {
    // Tab tersembunyi tidak perlu disegarkan: tidak ada yang melihatnya.
    if (document.hidden) return;
    fetch('/pengumuman', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (html) {
        if (!html) return;
        var doc = new DOMParser().parseFromString(html, 'text/html');
        ['lonceng', 'menu-kanan'].forEach(function (sel) {
          var baru = doc.querySelector('.' + sel);
          var lama = document.querySelector('.' + sel);
          if (!baru || !lama) return;
          // Angkanya disebut lewat aria-label; salin itu + lencana visualnya.
          var aBaru = baru.getAttribute('aria-label');
          if (aBaru) lama.setAttribute('aria-label', aBaru);
          if (baru.hasAttribute('data-ada-baru')) lama.setAttribute('data-ada-baru', '1');
          else lama.removeAttribute('data-ada-baru');
          var lcBaru = baru.querySelector('.lencana-notif');
          var lcLama = lama.querySelector('.lencana-notif');
          if (lcBaru && !lcLama) lama.appendChild(lcBaru.cloneNode(true));
          else if (!lcBaru && lcLama) lcLama.remove();
          else if (lcBaru && lcLama) lcLama.textContent = lcBaru.textContent;
        });
        var mlBaru = doc.querySelector('.menu-lencana');
        var mlLama = document.querySelector('.menu-lencana');
        if (mlBaru && mlLama) mlLama.textContent = mlBaru.textContent;
      })
      .catch(function () { /* lencana basi tidak boleh mengganggu halaman */ });
  };
  if (document.readyState === 'complete') muat();
  else window.addEventListener('load', muat);
  // Kembali ke tab = saat paling mungkin ada pengumuman baru; periksa segera
  // ketimbang menunggu interval.
  document.addEventListener('visibilitychange', function () { if (!document.hidden) muat(); });
  setInterval(muat, 60000);
})();
})();


/* --------------------------------------------------------------- TRANSISI HALAMAN
 * Bilah proses untuk NAVIGASI BIASA (bukan AJAX).
 *
 * Kenapa perlu: pindah menu memuat halaman penuh — itu memang benar (halaman
 * tujuan berbeda, dan AJAX hanya dipakai untuk menyaring daftar di halaman yang
 * sama). Tapi tanpa umpan balik apa pun, pengguna melihat layar diam lalu
 * halaman putih: terasa seperti "loading standar peramban" dan seperti aplikasi
 * rusak. Bilah di bawah ini muncul SEGERA saat tautan ditekan, jadi ada tanda
 * bahwa kliknya diterima — tanpa mengubah cara halaman dimuat.
 *
 * Tidak menghalangi apa pun: kalau JavaScript gagal, tautan tetap bekerja biasa.
 */
(function () {
  var bilah = document.getElementById('hub-proses');
  if (!bilah) {
    // Halaman tanpa bilah: buat sendiri, gaya minimal, tidak bergantung CSS.
    bilah = document.createElement('div');
    bilah.id = 'hub-proses';
    bilah.setAttribute('role', 'status');
    bilah.setAttribute('aria-live', 'polite');
    bilah.style.cssText = 'position:fixed;left:0;right:0;top:0;height:3px;z-index:60;'
      + 'pointer-events:none;opacity:0;transition:opacity .15s';
    var isi = document.createElement('div');
    isi.style.cssText = 'height:100%;width:0;background:#1f8739;'
      + 'transition:width .4s ease-out';
    isi.dataset.bilahIsi = '1';
    bilah.appendChild(isi);
    document.body.appendChild(bilah);
  }

  var isi = bilah.querySelector('[data-bilah-isi]') || bilah.firstElementChild;
  var jalan = false;

  function mulai() {
    if (jalan || !isi) return;
    jalan = true;
    bilah.hidden = false;
    bilah.style.opacity = '1';
    isi.style.width = '0';
    // Dua langkah: 30% segera (terasa responsif), lalu merayap ke 80% selama
    // halaman tujuan dimuat. Tidak pernah 100% — 100% hanya saat halaman BENAR
    // berganti, dan itu tidak bisa diketahui dari sini.
    requestAnimationFrame(function () { isi.style.width = '30%'; });
    setTimeout(function () { if (jalan) isi.style.width = '80%'; }, 350);
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a) return;
    if (a.target && a.target !== '_self') return;      // tab/jendela baru
    if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button !== 0) return;
    if (a.hasAttribute('download') || a.hasAttribute('data-tanpa-transisi')) return;
    var href = a.getAttribute('href') || '';
    if (href === '' || href.charAt(0) === '#') return;
    if (a.origin !== window.location.origin) return;   // keluar situs
    if (a.pathname === window.location.pathname && a.search === window.location.search
        && a.hash === window.location.hash) return;    // tautan ke diri sendiri

    mulai();
  }, true);

  // Tombol submit formulir juga memicu muat ulang: beri tanda yang sama.
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f || !f.matches) return;
    if (f.matches('form[data-ai-kirim]') || f.matches('form[data-mulai]')) return;
    mulai();
  }, true);

  // Tombol Back/Forward: peramban bisa memulihkan halaman dari cache (bfcache)
  // dengan bilah masih menyala. Padamkan.
  window.addEventListener('pageshow', function () {
    jalan = false;
    if (isi) isi.style.width = '0';
    bilah.style.opacity = '0';
    setTimeout(function () { if (!jalan) bilah.hidden = true; }, 200);
  });
})();


/* ============================================================ pindah halaman
 * Menukar isi <main id="isi"> saat menu/tautan mengarah ke HALAMAN LAIN,
 * tanpa muat ulang penuh. Sebelumnya aturan "pathname sama" di atas hanya
 * menangani paging & saringan; klik menu samping selalu memuat ulang seluruh
 * dokumen sehingga layar sempat putih.
 *
 * Yang SENGAJA tidak diambil alih:
 *
 *  - Halaman ber-@push('skrip'). Skrip halaman (Select2, ONLYOFFICE, papan
 *    rapat) dijalankan sekali saat dokumen dimuat; menukar DOM tanpa
 *    menjalankannya meninggalkan halaman yang tampak benar tapi diam. Begitu
 *    dokumen hasil menyebut ada <script> di dalam #isi, kita jatuh ke muat
 *    penuh — lambat sedikit, tapi benar.
 *  - Keluar/logout, unduhan, target selain _self, dan klik dengan tombol
 *    pengubah (Ctrl/Cmd/Shift) — pengguna minta perilaku peramban asli.
 *  - Tautan ber-`data-muat-penuh` untuk halaman yang nanti butuh muat penuh.
 *
 * Tanpa JavaScript semuanya tetap <a> biasa: tidak ada yang hilang.
 */
(function () {
  'use strict';

  var isi = document.getElementById('isi');
  if (!isi || !window.fetch || !window.history || !history.pushState) return;

  var bilah = document.getElementById('hub-proses');
  var sedang = null;

  function tampilBilah() { if (bilah) bilah.hidden = false; }
  function sembunyiBilah() { if (bilah) bilah.hidden = true; }

  function lewati(a, ev) {
    if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return true;
    if (a.target && a.target !== '_self') return true;
    if (a.hasAttribute('download') || a.hasAttribute('data-muat-penuh')) return true;
    if (a.origin !== window.location.origin) return true;
    var h = a.getAttribute('href') || '';
    if (h === '' || h.charAt(0) === '#') return true;
    var u = new URL(a.href, window.location.origin);
    if (u.pathname === window.location.pathname) return true;   // diurus aturan [data-isi]
    if (/\/(keluar|logout)(\/|$)/.test(u.pathname)) return true;
    return false;
  }

  // Menu samping & bilah bawah: tandai ulang butir yang aktif memakai keadaan
  // dari dokumen TUJUAN, bukan menebak dari URL. Rute beralias (mis. /kerja
  // dan /admin sama-sama menandai "dasbor") jadi tetap benar.
  function tandaiMenu(doc) {
    var aSisi = doc.querySelector('.sisi a.ini[href]');
    var aBawah = doc.querySelector('.nav-bawah a.ini[href]');
    var pSisi = aSisi ? new URL(aSisi.href, window.location.origin).pathname : null;
    var pBawah = aBawah ? new URL(aBawah.href, window.location.origin).pathname : null;

    function pasang(sel, target) {
      document.querySelectorAll(sel).forEach(function (x) {
        var p = new URL(x.href, window.location.origin).pathname;
        var on = target !== null && p === target;
        x.classList.toggle('ini', on);
        if (on) { x.setAttribute('aria-current', 'page'); }
        else { x.removeAttribute('aria-current'); }
      });
    }
    pasang('.sisi a[href]', pSisi);
    pasang('.nav-bawah a[href]', pBawah);
  }

  function penuh(url) {
    window.location.href = url;
  }

  async function pindah(url, dorong) {
    if (sedang === url) return;
    sedang = url;
    tampilBilah();
    try {
      var res = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      });
      // 4xx/5xx: serahkan ke peramban supaya halaman galat asli yang tampil.
      if (!res.ok) { sedang = null; sembunyiBilah(); penuh(url); return; }

      var teks = await res.text();
      var doc = new DOMParser().parseFromString(teks, 'text/html');
      var baru = doc.getElementById('isi');

      // Halaman ini punya skrip sendiri -> butuh muat penuh agar skripnya jalan.
      if (!baru || baru.querySelector('script')) {
        sedang = null; sembunyiBilah(); penuh(url); return;
      }

      isi.innerHTML = baru.innerHTML;
      if (doc.title) document.title = doc.title;
      if (doc.body && doc.body.className !== undefined) document.body.className = doc.body.className;
      tandaiMenu(doc);

      // Animasi masuk: lepas dulu agar bisa diputar ulang pada perpindahan
      // berikutnya (kelas yang sama tidak memicu animasi dua kali).
      isi.classList.remove('masuk-nav');
      void isi.offsetWidth;
      isi.classList.add('masuk-nav');

      if (dorong) history.pushState({ hubIsi: 1 }, '', url);
      window.scrollTo(0, 0);
    } catch (e) {
      penuh(url);              // jaringan putus / offline: jangan tinggalkan layar kosong
    } finally {
      sedang = null;
      sembunyiBilah();
    }
  }

  document.addEventListener('click', function (ev) {
    if (ev.defaultPrevented) return;
    var a = ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a) return;
    if (a.closest('.kisi-modul, .kisi-fitur')) {
      // kisi modul tetap dinavigasi; tapi butir yang pathname-nya SAMA dengan
      // halaman ini memang bukan perpindahan halaman -> biarkan aturan lama.
    }
    if (lewati(a, ev)) return;
    ev.preventDefault();
    pindah(a.href, true);
  });

  window.addEventListener('popstate', function () {
    // Kembali/Maju: ambil ulang isi, JANGAN pushState lagi.
    pindah(window.location.href, false);
  });
})();

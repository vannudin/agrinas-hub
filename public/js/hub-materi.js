/* /admin/materi — unggah berprogres + saring/serah berkas.
 *
 * Dua hal yang ditangani di sisi klien, keduanya TIDAK memindahkan kewenangan
 * dari server:
 *
 * 1. UNGGAH. Formulirnya tetap <form method=post enctype=multipart> biasa
 *    (CSRF, validasi, kewenangan, jurnal audit = jalur server yang sama).
 *    Yang ditambahkan hanya umpan baliknya: bilah kemajuan dari
 *    `xhr.upload.onprogress`, nama+ukuran berkas terpilih, dan seret-lepas.
 *    Bila browser tidak punya XMLHttpRequest.upload/FormData (atau JS mati),
 *    formulir dikirim seperti biasa dan hub.js yang menyalakan spinner tombol.
 *
 *    Jawaban diminta JSON (`Accept: application/json`) supaya 422 tidak
 *    MENYAMAR jadi 302→200: tanpa itu `fetch`/XHR menelusuri pengalihan
 *    validasi dan mengira unggahan berhasil. Pola yang sama dipakai modal
 *    surat jalan & /pasokan.
 *
 * 2. SARING saat mengetik. Yang ditukar hanya <tbody> (#mm-baris), bukan
 *    seluruh wadah [data-isi] — kalau seluruh blok ditukar, kotak cari yang
 *    sedang diisi ikut dibuang dan fokus hilang tiap huruf (cacat nyata di
 *    /admin/vendor & /distribusi dulu).
 *
 *    Dua mode, dipilih dari keadaan awal halaman:
 *      - daftar MUAT SATU HALAMAN  -> disaring di klien, tanpa permintaan
 *        sama sekali (instan), karena semua barisnya memang sudah ada di DOM;
 *      - daftar BERHALAMAN         -> potongan diambil dari server (hanya
 *        barisnya yang diganti, X-Jumlah memperbarui hitungan).
 *    Mode ditentukan sekali saat muat: adanya tautan pager ke halaman 2.
 */
(function () {
  // ---------------------------------------------------------- 0. bantu
  const ukuran = function (b) {
    const satuan = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (b >= 1024 && i < 3) { b /= 1024; i++; }
    return (i === 0 ? b : b.toFixed(1)).toString().replace('.', ',') + ' ' + satuan[i];
  };

  // ===================================================== 1. UNGGAH BERPROGRES
  const form = document.getElementById('mm-unggah');
  if (form && form.querySelector('input[type=file]')) {
    const isian = form.querySelector('input[type=file]');
    const kartuJatuh = form.querySelector('[data-jatuh]');
    const kotakIsi = form.querySelector('.mm-jatuh-isi');
    const kotakTerpilih = form.querySelector('[data-terpilih]');
    const namaEl = form.querySelector('[data-terpilih-nama]');
    const ukurEl = form.querySelector('[data-terpilih-ukur]');
    const jalur = form.querySelector('[data-jalur]');
    const bilah = form.querySelector('[data-jalur-bilah]');
    const jalurTeks = form.querySelector('[data-jalur-teks]');
    const tombolKirim = form.querySelector('[data-kirim]');
    const statusSiap = form.querySelector('[data-siap-status]');
    const maksKb = parseInt(form.getAttribute('data-maks-kb') || '2048', 10);
    let sedangKirim = false;

    function bilang(teks, galat) {
      if (!statusSiap) return;
      statusSiap.textContent = teks;
      statusSiap.style.color = galat ? '#96201e' : '';
    }

    function periksa() {
      const f = isian.files && isian.files[0];
      if (tombolKirim) tombolKirim.disabled = !f || sedangKirim;
      if (namaEl) namaEl.textContent = f ? f.name : '—';
      if (ukurEl) ukurEl.textContent = f ? ukuran(f.size) + (f.type ? ' · ' + f.type : '') : '';
      if (kotakTerpilih) kotakTerpilih.hidden = !f;
      if (kotakIsi) kotakIsi.hidden = !!f;
      if (kartuJatuh) kartuJatuh.classList.toggle('ada-berkas', !!f);
      if (!f) { bilang('Seret atau pilih satu berkas dulu.'); return false; }
      if (f.size > maksKb * 1024) {
        bilang('Berkas ' + ukuran(f.size) + ' melebihi batas ' + ukuran(maksKb * 1024) + '. Perkecil dulu.', true);
        if (tombolKirim) tombolKirim.disabled = true;
        return false;
      }
      bilang('Siap diunggah: ' + f.name + ' (' + ukuran(f.size) + ').');
      return true;
    }

    isian.addEventListener('change', periksa);

    // Seret-lepas. Diperiksa `dataTransfer.items` supaya berkas non-file
    // (mis. teks yang diseret dari editor) tidak dianggap berkas.
    if (kartuJatuh) {
      ['dragenter', 'dragover'].forEach(function (ev) {
        kartuJatuh.addEventListener(ev, function (e) {
          e.preventDefault();
          if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0) {
            kartuJatuh.classList.add('mm-seret');
          }
        });
      });
      ['dragleave', 'dragend'].forEach(function (ev) {
        kartuJatuh.addEventListener(ev, function () { kartuJatuh.classList.remove('mm-seret'); });
      });
      kartuJatuh.addEventListener('drop', function (e) {
        e.preventDefault();
        kartuJatuh.classList.remove('mm-seret');
        const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (!f) return;
        // DataTransfer adalah satu-satunya cara mengisi <input type=file> dari
        // kode (setelah keamanan 2016). Tanpa dukungannya, seret-lepas
        // dilewatkan dan pengguna memakai tombol pilih — bukan jalan buntu.
        try {
          const dt = new DataTransfer();
          dt.items.add(f);
          isian.files = dt.files;
        } catch (err) {
          bilang('Peramban ini tidak mengizinkan seret berkas. Pakai tombol "pilih dari perangkat".', true);
          return;
        }
        periksa();
      });
    }

    // Tombol Ganti: kosongkan pilihan, kembali ke kotak seret-lepas.
    const tombolGanti = form.querySelector('[data-ganti]');
    if (tombolGanti) {
      tombolGanti.addEventListener('click', function () {
        isian.value = '';
        try { isian.files = new DataTransfer().files; } catch (e) { /* biarkan */ }
        periksa();
      });
    }

    const bisaXHR = !!(window.XMLHttpRequest && new XMLHttpRequest().upload && window.FormData);

    form.addEventListener('submit', function (ev) {
      if (!periksa()) {
        // Kiriman tanpa berkas sah dicegah; stopPropagation WAJIB supaya
        // penangan `submit` global hub.js tidak menyalakan spinner pada tombol
        // + bilah #hub-proses tanpa ujung (terbaca sebagai "cuma loading").
        ev.preventDefault();
        ev.stopPropagation();
        return;
      }
      if (!bisaXHR) return;   // jalur biasa: biarkan hub.js bekerja

      ev.preventDefault();
      sedangKirim = true;
      if (tombolKirim) tombolKirim.disabled = true;
      if (jalur) jalur.hidden = false;
      if (bilah) bilah.style.width = '0%';
      if (jalurTeks) jalurTeks.textContent = 'Mengunggah… 0%';

      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action, true);
      // JSON: jalur ini menerima {pesan, url} pada sukses, dan 422 pada galat
      // validasi. Tanpa Accept JSON, XHR menelusuri 302 dan GET tujuan itu
      // menghabiskan flash session — pesan suksesnya lalu hilang tanpa jejak.
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.addEventListener('progress', function (e) {
        if (!e.lengthComputable) return;
        const persen = Math.round(e.loaded / e.total * 100);
        if (bilah) bilah.style.width = persen + '%';
        if (jalurTeks) jalurTeks.textContent = 'Mengunggah… ' + persen + '% (' + ukuran(e.loaded) + ' dari ' + ukuran(e.total) + ')';
      });

      xhr.upload.addEventListener('load', function () {
        if (bilah) bilah.style.width = '100%';
        if (jalurTeks) jalurTeks.textContent = 'Berkas terkirim — server sedang memproses…';
      });

      function gagal(pesan) {
        sedangKirim = false;
        if (tombolKirim) tombolKirim.disabled = false;
        if (jalur) jalur.hidden = true;
        bilang(pesan, true);
      }

      function selesai() {
        const kode = xhr.status;
        if (kode === 422) {
          let pesan = 'Berkas ditolak.';
          try {
            const j = JSON.parse(xhr.responseText);
            pesan = j.message || (j.errors && Object.values(j.errors)[0] && Object.values(j.errors)[0][0]) || pesan;
          } catch (e) { /* jawaban bukan JSON: pakai pesan umum */ }
          gagal(pesan);
          return;
        }
        if (kode === 413) { gagal('Berkas melebihi batas unggah server. Perkecil dulu.'); return; }
        if (kode < 200 || kode >= 400) { gagal('Unggahan gagal (HTTP ' + kode + '). Coba lagi.'); return; }

        let tujuan = form.action;
        try {
          const j = JSON.parse(xhr.responseText);
          if (j.url) tujuan = j.url;
          // Dititipkan ke sessionStorage: flash session TIDAK bisa dipakai di
          // jalur ini (server tidak mengalihkan), dan tanpa titipan ini
          // pengguna berpindah halaman tanpa tahu unggahannya berhasil.
          if (j.pesan) {
            window.sessionStorage.setItem('hub_toast', JSON.stringify({ teks: j.pesan, rusak: false }));
          }
        } catch (e) { /* bukan JSON: halaman tujuan dirender apa adanya */ }

        if (jalurTeks) jalurTeks.textContent = 'Selesai. Memuat ulang…';
        window.location.href = tujuan;
      }

      xhr.addEventListener('load', selesai);
      xhr.addEventListener('error', function () { gagal('Koneksi terputus saat mengunggah. Coba lagi.'); });
      xhr.addEventListener('abort', function () { gagal('Unggahan dibatalkan.'); });
      xhr.send(new FormData(form));
    });

    periksa();
  }

  // ====================================== 2. SARING TANPA KEHILANGAN FOKUS
  const wadah = document.getElementById('mm');
  if (!wadah) return;

  const elBaris = function () { return document.getElementById('mm-baris'); };
  const elJumlah = function () { return document.getElementById('mm-jumlah'); };
  const elBersih = function () { return document.querySelector('#mm .bersih-saring'); };

  // Berhalaman? Pager dirender server saat muat; tautan ke halaman 2 hanya ada
  // kalau memang ada lebih dari satu halaman.
  const berhalaman = !!wadah.querySelector('nav.paging a[href*="halaman=2"]');
  const totalAwal = function () {
    const el = elBaris();
    return el ? el.querySelectorAll('tr').length : 0;
  }();
  const jumlahAwal = function () {
    const j = elJumlah();
    if (!j) return 0;
    const t = (j.textContent || '').replace(/[^\d]/g, '');
    return t ? parseInt(t, 10) : 0;
  }();

  function segarkanBersih() {
    const f = wadah.querySelector('form.saring');
    const b = elBersih();
    if (!f || !b) return;
    b.hidden = ![...new FormData(f)].some(function (p) { return String(p[1]).trim() !== ''; });
  }

  // --- mode daftar satu halaman: saring di klien, tanpa permintaan
  //
  // Kategori dibandingkan lewat `data-kat` (kunci aslinya: JUKNIS/DOKUMEN/…),
  // bukan dari teks lencana — teksnya nama panjang ("Juknis / Panduan") yang
  // tak akan pernah sama dengan nilai <option>.
  function saringKlien(teks, kategori) {
    const el = elBaris();
    if (!el) return 0;
    const cari = teks.trim().toLowerCase();
    let tampil = 0;
    el.querySelectorAll('tr').forEach(function (tr) {
      if (tr.classList.contains('klien-kosong')) return;
      const selKat = tr.querySelector('td[data-kat]');
      // Baris "belum ada materi" dari server ikut disembunyikan saat menyaring.
      if (!selKat || tr.querySelector('.kosong')) { tr.hidden = cari !== '' || kategori !== ''; return; }
      const cocokCari = cari === '' || (tr.textContent || '').toLowerCase().indexOf(cari) >= 0;
      const cocokKat = kategori === '' || selKat.getAttribute('data-kat') === kategori;
      tr.hidden = !(cocokCari && cocokKat);
      if (!tr.hidden) tampil++;
    });
    // Baris "tidak ada yang cocok" dibuat sendiri: server tidak ikut diminta.
    let kosong = el.querySelector('tr.klien-kosong');
    if (tampil === 0) {
      if (!kosong) {
        kosong = document.createElement('tr');
        kosong.className = 'klien-kosong';
        kosong.innerHTML = '<td colspan="6" class="kosong">Tidak ada materi yang cocok. ' +
          '<a href="#">Tampilkan semua</a>.</td>';
        kosong.querySelector('a').addEventListener('click', function (e) {
          e.preventDefault();
          const f = wadah.querySelector('form.saring');
          if (f) { f.reset(); segarkanBersih(); periksaKlien(); }
        });
        el.appendChild(kosong);
      }
      kosong.hidden = false;
    } else if (kosong) {
      kosong.hidden = true;
    }
    const j = elJumlah();
    if (j) {
      j.textContent = (cari === '' && kategori === '')
        ? jumlahAwal.toLocaleString('id-ID') + ' materi'
        : tampil + ' dari ' + jumlahAwal.toLocaleString('id-ID') + ' materi';
    }
    return tampil;
  }

  function nilaiSaring() {
    const f = wadah.querySelector('form.saring');
    if (!f) return { q: '', kategori: '' };
    return {
      q: (f.querySelector('[name=q]') || {}).value || '',
      kategori: (f.querySelector('[name=kategori]') || {}).value || '',
    };
  }

  function periksaKlien() {
    const v = nilaiSaring();
    return saringKlien(v.q, v.kategori);
  }

  // --- mode berhalaman: ambil potongan dari server (hanya <tbody> ditukar)
  function muatPotongan() {
    const isi = elBaris();
    const f = wadah.querySelector('form.saring');
    if (!isi || !f || !wadah.dataset.daftar) return;
    const u = new URL(wadah.dataset.daftar, window.location.origin);
    new URLSearchParams(new FormData(f)).forEach(function (v, k) {
      if (v !== '') u.searchParams.set(k, v);
    });
    fetch(u.toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
      credentials: 'same-origin',
    }).then(function (res) {
      if (res.redirected) { window.location.href = res.url; return null; }
      if (!res.ok) return null;
      const jumlah = parseInt(res.headers.get('X-Jumlah') || '0', 10);
      return res.text().then(function (html) {
        isi.innerHTML = html;
        const j = elJumlah();
        if (j) {
          const jml = jumlah.toLocaleString('id-ID');
          j.textContent = jumlah > 10
            ? jml + ' materi · tekan Enter untuk semua halaman'
            : jml + ' materi';
        }
      });
    }).catch(function () { /* biarkan data lama tampil; Enter tetap jalur penuh */ });
  }

  let jeda = null;
  document.addEventListener('input', function (ev) {
    const f = ev.target.closest ? ev.target.closest('#mm form.saring') : null;
    if (!f || ev.target.tagName === 'SELECT') return;
    segarkanBersih();
    window.clearTimeout(jeda);
    jeda = window.setTimeout(function () {
      // Satu halaman = semua baris sudah ada di DOM; menyaring di klien
      // membuatnya instan dan tanpa permintaan sama sekali.
      if (!berhalaman) { periksaKlien(); return; }
      muatPotongan();
    }, 260);
  });

  document.addEventListener('change', function (ev) {
    if (!ev.target.closest || !ev.target.closest('#mm form.saring select')) return;
    segarkanBersih();
    if (!berhalaman) { periksaKlien(); return; }
    muatPotongan();
  });
})();

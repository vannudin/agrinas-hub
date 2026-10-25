/* ---------------------------------------------------------------------------
 * Stok koperasi: alat scan barcode (OPSIONAL) + pengisian modal update stok.
 *
 * Kenapa berkas sendiri, bukan ditambahkan ke hub.js: hub.js dimuat SEMUA halaman,
 * sedangkan yang ini hanya dipakai satu halaman. Modal Tambah/Ubah barang cukup
 * memakai penangan `data-buka`/`data-awal` yang sudah ada di hub.js.
 *
 * Barcode itu pelengkap, BUKAN syarat: kalau peramban tidak punya BarcodeDetector
 * atau kamera tidak diizinkan (mis. dibuka lewat http:// alamat LAN — kamera
 * memang mustahil di sana), pengguna tetap bisa mengetik barcodenya manual dan
 * semua fitur lain tetap jalan.
 * ------------------------------------------------------------------------- */
(function () {
  'use strict';

  /* ---------- 1. Isi modal "Perbarui Stok" dari data-awal ----------
   * hub.js sudah mengisi field yang namanya cocok; yang tidak ada di sini adalah
   * rute tujuan (data-aksi) dan nama barang (tidak ada input-nya). Keduanya
   * diurus di sini supaya tidak menyentuh hub.js yang dipakai halaman lain. */
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-buka="stok-modal"]') : null;
    if (!b) return;

    var d = document.getElementById('stok-modal');
    if (!d) return;

    var form = d.querySelector('form');
    if (form && b.dataset.aksi) form.action = b.dataset.aksi;

    // Selisih selalu dikosongkan dan sebab kembali ke "hitung ulang": modal yang
    // menyimpan sisa isian sebelumnya membuat orang mengirim tanpa sadar.
    var tambah = d.querySelector('[name="tambah"]');
    if (tambah) tambah.value = '';
    var sebab = d.querySelector('[name="sebab"]');
    if (sebab) sebab.value = 'HITUNG';

    var judul = d.querySelector('[data-stok-nama]');
    if (judul) {
      try {
        var a = JSON.parse(b.getAttribute('data-awal') || '{}');
        judul.textContent = a.nama ? a.nama + ' — stok sekarang ' + a.stok + ' ' + (a.satuan || '') : '—';
      } catch (e) { judul.textContent = '—'; }
    }
  });

  /* ---------- 2. Alat scan barcode ---------- */
  var dlg = document.getElementById('scan-modal');
  if (!dlg) return;

  var video = dlg.querySelector('[data-scan-video]');
  var bingkai = dlg.querySelector('[data-scan-bingkai]');
  var pesan = dlg.querySelector('[data-scan-pesan]');
  var manual = dlg.querySelector('[data-scan-manual]');
  var hasil = dlg.querySelector('[data-scan-hasil]');
  var aliran = null;      // MediaStream
  var pemindai = null;    // BarcodeDetector
  var timer = null;
  /* Scan yang dibuka DARI formulir Tambah/Ubah barang: hasilnya mengisi kolom
   * barcode formulir itu, bukan cuma ditampilkan sebagai kartu. `null` = scan
   * dibuka dari bilah atas (untuk memeriksa barang), perilakunya seperti semula. */
  var tujuan = null;

  function bilang(t) { if (pesan) pesan.textContent = t; }

  function hentikan() {
    if (timer) { clearInterval(timer); timer = null; }
    if (aliran) { aliran.getTracks().forEach(function (t) { t.stop(); }); aliran = null; }
    if (bingkai) bingkai.hidden = true;
    if (video) video.srcObject = null;
  }

  function mulai() {
    if (hasil) hasil.innerHTML = '';
    if (manual) manual.value = '';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      bilang('Kamera tidak tersedia di peramban ini (kamera hanya jalan di HTTPS). Ketik kode barcode di bawah — hasilnya sama.');
      return;
    }
    if (!('BarcodeDetector' in window)) {
      bilang('Peramban ini belum bisa membaca barcode otomatis. Ketik kode barcode di bawah — hasilnya sama.');
      return;
    }

    pemindai = new window.BarcodeDetector();
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(function (s) {
        aliran = s;
        if (bingkai) bingkai.hidden = false;
        video.srcObject = s;
        return video.play();
      })
      .then(function () {
        bilang('Arahkan kamera ke barcode produk.');
        timer = setInterval(function () {
          pemindai.detect(video).then(function (kode) {
            if (kode && kode.length && kode[0].rawValue) {
              var v = kode[0].rawValue;
              hentikan();
              if (manual) manual.value = v;
              bilang('Barcode terbaca: ' + v);
              cari(v);
            }
          }).catch(function () { /* bingkai gagal dibaca: coba lagi di tick berikutnya */ });
        }, 400);
      })
      .catch(function () {
        bilang('Izin kamera ditolak atau kamera sedang dipakai. Ketik kode barcode di bawah, lalu Enter.');
      });
  }

  /* ---------- 3. Tujuan hasil scan: isi kolom, lalu lanjutkan ---------- */
  /* Isi kolom tujuan dengan barcode hasil scan, tutup alat scan, lalu:
   *  · tujuan <form> (halaman POS) -> formulirnya LANGSUNG DIKIRIM, karena di
   *    sana hasilnya adalah penyaringan daftar, bukan formulir yang menunggu
   *    diisi lebih lanjut;
   *  · tujuan <dialog> (Tambah/Ubah barang) -> dibuka kembali dan fokus pindah
   *    ke isian "nama", yaitu isian wajib berikutnya.
   * Dipakai baik saat barangnya sudah terdaftar maupun belum: barcode yang baru
   * dipindai selalu berguna di kolomnya. */
  function keTujuan(v) {
    var el = tujuan ? tujuan.d.querySelector('[name="' + tujuan.n + '"]') : null;
    if (!el) return false;

    el.value = v;
    dlg.close();

    if (tujuan.d.tagName === 'FORM') {
      if (tujuan.d.requestSubmit) tujuan.d.requestSubmit(); else tujuan.d.submit();
      return true;
    }

    if (!tujuan.d.open) tujuan.d.showModal();
    var nama = tujuan.d.querySelector('[name="nama"]');
    if (nama) nama.focus();

    return true;
  }

  function cari(kunci) {
    var url = dlg.getAttribute('data-cari');
    // Tanpa endpoint pencarian (kolom cari POS memakai penyaringan GET biasa):
    // barcode cukup MENGISI kolom tujuan lalu formulirnya dikirim. Tidak ada
    // permintaan jaringan yang perlu dijawab di sini.
    if (!url) { keTujuan(kunci); return; }
    if (!kunci) return;
    var u = new URL(url, window.location.origin);
    u.searchParams.set('kunci', kunci);
    if (hasil) hasil.innerHTML = '<p class="bantuan">Mencari…</p>';

    fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { if (hasil) hasil.innerHTML = '<p class="pesan rusak">' + (d.pesan || 'Gagal mencari.') + '</p>'; return; }
        tampilkan(kunci, d);
      })
      .catch(function () { if (hasil) hasil.innerHTML = '<p class="pesan rusak">Tidak bisa menghubungi server. Coba lagi.</p>'; });
  }

  function tampilkan(kunci, d) {
    // Dipanggil dari formulir Tambah/Ubah: yang dibutuhkan bukan kartu hasil,
    // melainkan barcodenya masuk ke kolom.
    //
    // TAPI hanya bila barcode itu belum dipakai barang LAIN di koperasi ini —
    // barcode unik per koperasi, jadi menempelkannya ke formulir barang lain akan
    // ditolak database. Kasus itu jatuh ke tampilan kartu di bawah: orangnya
    // melihat barangnya sudah ada, bukan menemui galat saat menyimpan.
    var me = tujuan ? (tujuan.d.querySelector('[name="id"]') || { value: '' }).value : '';
    var bolehIsi = tujuan && (! d.tepat || (me !== '' && String(d.tepat.id) === String(me)));
    if (bolehIsi && keTujuan(kunci)) return;

    if (!hasil) return;
    var b = d.tepat;
    if (!b) {
      var boleh = !!document.getElementById('barang-modal');
      hasil.innerHTML =
        '<p class="bantuan">Barang dengan barcode <b>' + esc(kunci) + '</b> belum ada di daftar koperasi ini.</p>' +
        (boleh
          ? '<button class="tombol utama" type="button" data-tambah-dari-scan="' + esc(kunci) + '">Tambah Barang dengan Barcode Ini</button>'
          : '<p class="bantuan">Hubungi manajer koperasi untuk menambahkannya.</p>');
      // Daftar hasil lain (kalau kuncinya kebetulan cocok nama) tetap ditampilkan
      if (d.hasil && d.hasil.length) hasil.insertAdjacentHTML('beforeend', daftar(d.hasil));
      return;
    }

    hasil.innerHTML =
      '<div class="kartu">' +
        '<b>' + esc(b.nama) + '</b>' +
        '<small class="pudar">' + esc(b.kategori_label) + (b.barcode ? ' · barcode ' + esc(b.barcode) : '') + '</small>' +
        '<p class="bantuan"><b>' + num(b.stok) + ' ' + esc(b.satuan) + '</b>' +
        (b.stok_min > 0 ? ' · ambang ' + num(b.stok_min) : '') +
        (b.menipis ? ' <span class="lencana s-REVISI">Menipis</span>' : '') +
        (b.harga > 0 ? ' · Rp ' + num(b.harga) : '') + '</p>' +
      '</div>';
  }

  function daftar(items) {
    var s = '<ul class="pilih-daftar" style="position:static;max-height:none;margin-top:.5rem">';
    items.forEach(function (x) {
      s += '<li><div class="pilih-baris"><span class="pilih-nama">' + esc(x.nama) + '</span>' +
           '<small>' + num(x.stok) + ' ' + esc(x.satuan) + '</small></div></li>';
    });
    return s + '</ul>';
  }

  function esc(t) {
    return String(t === null || t === undefined ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function num(n) { return Number(n || 0).toLocaleString('id-ID'); }

  /* Hasil scan -> isi formulir Tambah Barang, lalu buka modalnya. Ini yang membuat
   * "tambah barang dengan barcode" cukup sekali sentuh. */
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-tambah-dari-scan]') : null;
    if (!t) return;
    var barcode = t.getAttribute('data-tambah-dari-scan');
    var m = document.getElementById('barang-modal');
    if (!m) return;

    hentikan();
    dlg.close();
    tujuan = null;

    // Pakai penangan hub.js lewat elemen pembuka buatan: satu jalur pengisian
    // field, jadi tidak ada logika pengisian ganda yang bisa saling menyimpang.
    var j = document.createElement('button');
    j.type = 'button';
    j.setAttribute('data-buka', 'barang-modal');
    j.setAttribute('data-judul-dialog', 'Tambah Barang');
    j.setAttribute('data-awal', JSON.stringify({ id: '', kode: '', barcode: barcode, nama: '',
      satuan: 'pcs', kategori: 'SEMBAKO', stok: 0, stok_min: 0, harga: 0, catatan: '' }));
    j.hidden = true;
    document.body.appendChild(j);
    j.click();
    j.remove();

    var nama = m.querySelector('[name="nama"]');
    if (nama) nama.focus();
  });

  /* ---------- 4. Siklus buka/tutup dialog scan ---------- */
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-buka="scan-modal"]') : null;
    if (t) {
      // Pembuka boleh menunjuk formulir yang harus diisi hasilnya
      // (`data-scan-untuk` = id dialog, `data-scan-isi` = nama kolomnya). Tanpa
      // itu, scan tetap seperti semula: hasilnya ditampilkan sebagai kartu.
      tujuan = null;
      var d2 = t.getAttribute('data-scan-untuk');
      if (d2) {
        var f2 = document.getElementById(d2);
        if (f2) tujuan = { d: f2, n: t.getAttribute('data-scan-isi') || 'barcode' };
      }
      setTimeout(mulai, 60);
      return;
    }
    if (ev.target.closest && ev.target.closest('[data-scan-ulang]')) { mulai(); return; }
    // Ditutup lewat tombol Tutup / Esc / klik luar -> kamera WAJIB dimatikan.
    // HANYA penutup DI DALAM alat scan yang membuka kembali formulirnya: tanpa
    // syarat itu, menutup formulir barang ikut memicu buka-ulang (tombol Tutup
    // tidak bisa dipakai).
    if (ev.target.closest && ev.target.closest('[data-tutup]')) {
      if (dlg.contains(ev.target)) {
        // Hanya <dialog> yang boleh dibuka kembali; tujuan berupa <form> (kolom
        // cari POS) tidak punya showModal — memanggilnya melempar TypeError.
        if (tujuan && tujuan.d.tagName !== 'FORM' && !tujuan.d.open) tujuan.d.showModal();
        hentikan();
      }
      return;
    }
    if (ev.target === dlg) {
      if (tujuan && tujuan.d.tagName !== 'FORM' && !tujuan.d.open) tujuan.d.showModal();
      hentikan();
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') hentikan();
  });

  if (manual) {
    manual.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter') return;
      ev.preventDefault();
      hentikan();
      bilang('Mencari barcode ' + manual.value + '…');
      cari(manual.value.trim());
    });
  }

  window.addEventListener('pagehide', hentikan);
})();

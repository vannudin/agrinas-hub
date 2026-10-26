/* AgriConnect — notifikasi untuk aksi yang dikonfirmasi lebih dulu.
 *
 * Konfirmasi `data-konfirmasi` untuk tindakan merusak DIPINDAH ke hub.js.
 * Alasannya: berkas ini TIDAK dimuat di halaman pengelola (/admin/rapat,
 * /distribusi/stok-koperasi), sehingga atribut itu di sana mati — tombol "Hapus"
 * langsung menghapus tanpa bertanya. hub.js dimuat layout pada setiap halaman,
 * jadi satu tempat cukup. Jangan kembalikan blok konfirmasi ke sini.
 */

/* Modal: satu penangan untuk semua halaman modul (termasuk Absen KDKMP).
 * Tombol ber-`data-buka="id"` membuka <dialog> itu; `data-tutup` menutupnya.
 * Halaman Presensi menangani dialognya sendiri karena kamera harus menyala
 * tepat saat modal terbuka — lihat blok berikutnya. */
(function () {
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-buka]');
    if (b) {
      var d = document.getElementById(b.getAttribute('data-buka'));
      if (d && typeof d.showModal === 'function' && !d.open) {
        d.showModal();
      }
      return;
    }
    var t = ev.target.closest('[data-tutup]');
    if (t) {
      var m = t.closest('dialog');
      if (m) m.close();
    }
  });
})();

/* Absen KDKMP: tarik daftar hadir sendiri saat halaman dibuka.
 *
 * Daftar hadir di halaman itu cerminan SIMKOPDES, jadi membukanya hampir selalu
 * berarti "saya ingin yang terbaru". Menuntut satu klik dulu untuk itu membuat
 * daftar yang terlihat terasa basi padahal datanya sudah ada di sana.
 *
 * Jeda minimal 5 menit antar-tarikan, bukan tiap muat ulang: tiap tarikan
 * memanggil SIMKOPDES (batasnya 120 permintaan, dan satu tarikan bisa memakan
 * beberapa halaman), jadi menyegarkan ulang dalam waktu singkat tidak boleh
 * menyapu berulang kali. Tombolnya tetap ada untuk menarik ulang dengan sengaja.
 *
 * Dulu tarikan ini hanya jalan kalau URL memuat `#absen` atau `?view=absen` —
 * sisa dari masa halaman ini memuat dua daftar. Akibatnya membuka halaman dari
 * menu (jalan biasa orang) TIDAK PERNAH menyegarkan daftar. Sekarang halaman
 * menyatakan sendiri bahwa ia ingin disegarkan (`data-otomatis="tarik"` hanya
 * dipasang di sana), jadi penjaga `mauAbsen` tidak dipakai lagi.
 *
 * Tarikan yang gagal tidak dikunci: formulirnya dikirim dengan halaman dimuat
 * ulang, jadi penjagaan "sudah jalan" di sini punya masa hidup satu halaman.
 */
(function () {
  var f = document.querySelector('form[data-otomatis="tarik"]');
  if (!f) return;
  // Jarak minimal antar-tarikan otomatis. Halaman ini menyegarkan daftarnya
  // sendiri saat dibuka — itulah "sinkron saat halaman dibuka" yang diminta —
  // tetapi memuat ulang halaman berulang kali tidak boleh menyapu SIMKOPDES
  // berulang kali: batasnya 120 permintaan, dan satu tarikan bisa memakan
  // beberapa halaman. Lima menit cukup segar untuk daftar absensi.
  var JEDA = 5 * 60 * 1000;
  var KUNCI = 'hub.absen.tarik.pada';
  try {
    var lalu = parseInt(window.sessionStorage.getItem(KUNCI) || '0', 10);
    if (lalu && Date.now() - lalu < JEDA) return;
    window.sessionStorage.setItem(KUNCI, String(Date.now()));
  } catch (e) {
    // Penyimpanan sesi diblokir (mode privat): tarik sekali saja per muat.
  }
  f.submit();
})();

/* Presensi: kamera LANGSUNG + lokasi + waktu perangkat, di halaman.
 *
 * Formulirnya terhampar di halaman (bukan di dalam modal): di SIMKOPDES
 * presensi itu halaman kerja — judul kegiatan, foto, titik lokasi, dan tombol
 * kirim terlihat sekaligus — dan modal menyembunyikan justru bagian yang paling
 * perlu dipandang (foto diri sendiri). Tiga hal dikerjakan di sisi klien karena
 * server tidak sanggup / tidak punya:
 *
 * 1. KAMERA. Tidak ada `<input type="file">` sama sekali di halaman ini — potret
 *    diambil dari `getUserMedia` pada saat itu juga, jadi foto lama dari galeri
 *    tidak bisa dipakai. Servernya pun menolak jalur berkas. `getUserMedia`
 *    hanya ada di secure context (https atau localhost); di http://<ip-lan> ia
 *    tidak ada, dan pesannya menyebut sebabnya, bukan "coba lagi".
 *
 *    KAMERA DEPAN (`facingMode: 'user'`) untuk Check In MAUPUN Check Out.
 *    Dulu Check Out memakai kamera belakang, dan itu salah dua kali: yang
 *    difoto adalah dinding, dan di ponsel pengguna harus memutar badan untuk
 *    mencari tombolnya — sementara pratinjaunya dicerminkan (selfie), sehingga
 *    hasilnya justru terbalik dari yang dilihat. Presensi itu selfie; sisi
 *    kamera tidak lagi ditanyakan.
 *
 *    Hasilnya dikirim sebagai data URL di kolom teks, BUKAN unggahan berkas,
 *    karena `upload_max_filesize` PHP di kontainer ini cuma 2M dan GD/Imagick
 *    tidak terpasang — server tidak bisa mengecilkan foto sendiri. `post_max_size`
 *    8M memberi ruang, tapi tetap kita potret pada 960px / kualitas 0.7.
 *
 * 2. LOKASI. Peramban hanya memberi `navigator.geolocation` dari tindakan
 *    pengguna, jadi tombolnya yang menekan — bukan saat halaman dimuat.
 *    Titik lokasi wajib, sama untuk Check In dan Check Out. Titik yang
 *    akurasinya buruk (mis. 3 km — hasil jaringan seluler tanpa GPS) DITOLAK:
 *    itu bukan titik tempat orang berdiri, jadi tidak layak jadi bukti.
 *
 * 3. WAKTU. Jam perangkat (`new Date()`) + nama zona waktunya ikut dikirim.
 *    Server menolak bila jam itu melenceng lebih dari toleransinya, dan
 *    koordinat (0,0) ditolak karena itu titik kosong, bukan alamat.
 *
 * Kamera hidup HANYA di halaman yang masih punya formulir presensi, dan mati
 * begitu modal Penjelasan dibuka (menonton penjelasan sambil lampu kamera
 * menyala itu tidak sopan) atau sesudah foto diambil. Tanpa JavaScript,
 * presensi tidak bisa dipakai, dan itu memang disengaja: foto harus dari
 * kamera saat itu juga, bukan berkas pilihan.
 */
(function () {
  var form = document.querySelector('form[data-presensi]');
  if (!form) return;

  var MAKS_BYTE = 1.5 * 1024 * 1024; // cerminan AgriController::FOTO_MAKS_KB
  var POTRET_LEBAR = 960;            // cukup untuk wajah; 1280 pun masih aman

  // SASARAN ukuran berkas, bukan kualitas tetap. Satu setelan tetap menghasilkan
  // sebaran lebar: pada 960/q0.70 foto wajah terang jatuh di 45 KB, tapi foto
  // bertekstur (rambut, kerudung, latar berdaun) bisa 117 KB — dan yang besar
  // itulah yang memperlambat unggahan di sinyal lemah, bukan rata-ratanya.
  // Dengan tangga ini mutu TERTINGGI dipertahankan selama muat, dan hanya foto
  // yang berat yang diturunkan. Terukur pada 40 foto presensi nyata:
  // median 60 KB, p90 69 KB, maksimum 70 KB (dulu: median 63 KB, p90 99 KB,
  // maksimum 117 KB). Sepuluh ribu absensi: 0,57 GB, bukan 0,62 GB — dan yang
  // lebih penting, tidak ada satu pun berkas yang meledak jadi 117 KB.
  var TARGET_BYTE = 70 * 1024;       // batas atas per foto setelah kompresi

  // Setiap rung = lebar px + mutu JPEG. Urut dari mutu tertinggi ke terendah;
  // rung pertama yang muat di TARGET_BYTE itulah yang dipakai.
  var TANGGA = [
    { lebar: 960, q: 0.82 },
    { lebar: 960, q: 0.72 },
    { lebar: 960, q: 0.62 },
    { lebar: 840, q: 0.64 },
    { lebar: 720, q: 0.70 },
    { lebar: 720, q: 0.60 },
    { lebar: 640, q: 0.62 }
  ];

  var video = form.querySelector('[data-pratinjau]');
  var potret = form.querySelector('[data-potret]');
  var pesanKamera = form.querySelector('[data-kamera-pesan]');
  var tombolJepret = form.querySelector('[data-jepret]');
  var tombolUlang = form.querySelector('[data-ulang]');
  var tombolNyalakan = form.querySelector('[data-nyalakan]');
  var tombolLokasi = form.querySelector('[data-lokasi]');
  var statusLokasi = form.querySelector('[data-lokasi-status]');
  var tombolKirim = form.querySelector('[type="submit"]');
  var aliran = null;
  var adaPotret = false;

  function beri(pesan, galat) {
    if (!pesanKamera) return;
    pesanKamera.textContent = pesan;
    pesanKamera.style.color = galat ? '#96201e' : '';
  }

  /* Pratinjau ditampilkan sebagai cermin (selfie), tapi potret yang disimpan
     TIDAK dicerminkan — foto bukti harus sesuai kenyataan, bukan terbalik. */
  function tampilkanPratinjau(ya) {
    if (!video) return;
    video.hidden = !ya;
    video.classList.toggle('cermin', ya);
  }

  function hentikanKamera() {
    if (aliran) {
      aliran.getTracks().forEach(function (t) { t.stop(); });
      aliran = null;
    }
    // srcObject ikut dilepas: stop() mematikan track, tapi elemen <video> tetap
    // memegang stream-nya — dan penanda kamera di ponsel butuh keduanya.
    if (video) video.srcObject = null;
  }

  function nyalakanKamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      if (tombolNyalakan) tombolNyalakan.hidden = false;
      if (tombolJepret) tombolJepret.hidden = true;
      beri('Kamera hanya bisa dipakai lewat https://, bukan http://. Buka halaman ini lewat alamat https.', true);
      return;
    }
    hentikanKamera();
    beri('Menyiapkan kamera…');
    if (tombolNyalakan) tombolNyalakan.hidden = true;
    if (adaPotret) { resetPotret(); }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 } }, audio: false })
      .then(function (s) {
        aliran = s;
        video.srcObject = s;
        tampilkanPratinjau(true);
        if (tombolJepret) tombolJepret.hidden = false;
        beri('Arahkan wajah, lalu tekan Ambil Foto.');
      })
      .catch(function (e) {
        if (tombolNyalakan) tombolNyalakan.hidden = false;
        if (tombolJepret) tombolJepret.hidden = true;
        beri(e && e.name === 'NotAllowedError'
          ? 'Izin kamera ditolak. Aktifkan kamera untuk situs ini di pengaturan peramban, lalu tekan Nyalakan kamera.'
          : 'Kamera gagal dinyalakan. Pastikan tidak dipakai aplikasi lain, lalu coba lagi.', true);
      });
  }

  function resetPotret() {
    adaPotret = false;
    form.querySelector('[name="foto_data"]').value = '';
    if (potret) { potret.hidden = true; potret.removeAttribute('src'); }
    if (tombolUlang) tombolUlang.hidden = true;
    if (tombolJepret) tombolJepret.hidden = false;
    tampilkanPratinjau(true);
    if (aliran) beri('Arahkan wajah, lalu tekan Ambil Foto.');
    periksa();
  }

  /* ---------- kompresi adaptif (sebelum unggah) ---------- */
  // Kanvas sumber sudah berukuran 3:4 dan <= POTRET_LEBAR. Dari situ tiap rung
  // diuji: kalau hasilnya masih di atas TARGET_BYTE, turun ke rung berikutnya.
  // Rung pertama yang muat dipakai, jadi foto yang ringan TIDAK ikut diturunkan
  // mutunya hanya karena foto lain berat.
  //
  // Mengganti ukuran dilakukan dengan menggambar ulang ke kanvas baru, bukan
  // dengan mengecilkan data URL: JPEG yang sudah dikodekan tidak bisa diperkecil
  // tanpa dikodekan ulang, dan mengodekan ulang tanpa menggambar akan menyisakan
  // resolusi yang tidak perlu — buang pikselnya sekalian, itu yang menghemat.
  function kompresPotret(sumber) {
    var terakhir = null;
    for (var i = 0; i < TANGGA.length; i++) {
      var t = TANGGA[i];
      var lebar = Math.min(t.lebar, sumber.width);
      var kanvas = sumber;
      if (lebar < sumber.width) {
        kanvas = document.createElement('canvas');
        kanvas.width = lebar;
        kanvas.height = Math.max(1, Math.round(sumber.height * lebar / sumber.width));
        kanvas.getContext('2d').drawImage(sumber, 0, 0, kanvas.width, kanvas.height);
      }
      var d = kanvas.toDataURL('image/jpeg', t.q);
      var b = Math.round(d.length * 3 / 4);   // panjang data URL ~4/3 ukuran biner
      terakhir = { data: d, byte: b, lebar: kanvas.width, kualitas: t.q };
      if (b <= TARGET_BYTE) break;            // sudah cukup kecil: berhenti
    }
    return terakhir;
  }

  function jepret() {
    if (!video || !video.videoWidth) {
      beri('Kamera belum siap. Tunggu sebentar, lalu coba lagi.', true);
      return;
    }
    /* Potret 3:4 SEBENARNYA dipotong di sini — ambil bagian tengah frame yang
       paling besar dengan perbandingan 3:4, lalu gambar HANYA bagian itu ke
       kanvas yang juga 3:4. Tanpa potongan ini, frame webcam lanskap (4:3 atau
       16:9) tersimpan lanskap, dan daftar hadir jadi tidak seragam: sebagian
       baris tinggi, sebagian pendek — sementara "menyeragamkan" dengan
       menyunting header JPEG (yang dulu dilakukan server) hanya mengubah
       angkanya tanpa memotong piksel, sehingga wajahnya justru teregang. */
    var vw = video.videoWidth;
    var vh = video.videoHeight;
    var sw = Math.min(vw, Math.round(vh * 3 / 4));
    var sh = Math.round(sw * 4 / 3);
    var sx = Math.round((vw - sw) / 2);
    var sy = Math.round((vh - sh) / 2);
    var skala = Math.min(1, POTRET_LEBAR / sw);
    var c = document.createElement('canvas');
    c.width = Math.round(sw * skala);
    c.height = Math.round(sh * skala);
    c.getContext('2d').drawImage(video, sx, sy, sw, sh, 0, 0, c.width, c.height);

    // Kompresi terjadi DI SINI, di perangkat pengguna, sebelum satu byte pun
    // dikirim. Yang diunggah ke R2 adalah hasil rung terkecil yang masih
    // memenuhi sasaran — jadi jaringan ponsel tidak pernah dipakai untuk
    // mengangkut foto yang terlalu besar.
    var hasil = kompresPotret(c);
    var data = hasil.data;
    var kiraBiner = hasil.byte;
    if (kiraBiner > MAKS_BYTE) {
      beri('Foto masih ' + Math.round(kiraBiner / 1048576 * 10) / 10 + ' MB (batas 1,5 MB). Coba lagi dengan pencahayaan lebih terang.', true);
      return;
    }
    // Foto TIDAK dikirim lewat server. Peramban menaruhnya LANGSUNG di R2 dan
    // formulir hanya membawa NAMANYA (`foto_kunci`), jadi tidak ada worker PHP
    // yang tertahan selama byte berpindah. Selama unggahan berjalan tombol kirim
    // tetap terkunci; kalau gagal, jalur lama (data URL di kolom teks) dipakai
    // supaya presensi tidak pernah batal gara-gara jaringan.
    unggahLangsung(data, function (kunci) {
      var kolomKunci = form.querySelector('[name="foto_kunci"]');
      var kolomData = form.querySelector('[name="foto_data"]');
      if (kunci) {
        if (kolomKunci) kolomKunci.value = kunci;
        if (kolomData) kolomData.value = '';
        beri('Foto siap dikirim (' + Math.round(kiraBiner / 1024) + ' KB dari '
          + hasil.lebar + ' px, tersimpan langsung di R2). Kamera dimatikan.');
      } else {
        if (kolomKunci) kolomKunci.value = '';
        if (kolomData) kolomData.value = data;
        beri('Foto siap dikirim (' + Math.round(kiraBiner / 1024) + ' KB). Kamera dimatikan.');
      }
      adaPotret = true;
      if (potret) { potret.src = data; potret.hidden = false; }
      tampilkanPratinjau(false);
      if (tombolJepret) tombolJepret.hidden = true;
      if (tombolUlang) tombolUlang.hidden = false;
      hentikanKamera();
      periksa();
    });
  }

  /* ---------- unggah foto LANGSUNG ke R2 ---------- */
  // Server hanya menandatangani izin tulis (0,6 ms); peramban yang mengirim
  // byte-nya. Dengan begitu foto sepuluh ribu orang tidak berebut bandwidth
  // server ini dan tidak menahan satu pun worker PHP.
  function dataUrlKeBlob(d) {
    var i = d.indexOf(',');
    var tipe = d.substring(5, i).split(';')[0];
    var biner = atob(d.substring(i + 1));
    var buf = new Uint8Array(biner.length);
    for (var k = 0; k < biner.length; k++) { buf[k] = biner.charCodeAt(k); }
    return new Blob([buf], { type: tipe });
  }

  function unggahLangsung(data, selesai) {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var url = form.getAttribute('data-url-unggah');
    if (!meta || !url || !window.fetch || !window.Blob || !window.Uint8Array) {
      selesai(null);
      return;
    }
    var blob;
    try { blob = dataUrlKeBlob(data); } catch (e) { selesai(null); return; }

    hentikanKamera();
    beri('Menyimpan foto langsung ke penyimpanan…');
    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': meta.getAttribute('content'),
        'X-Requested-With': 'XMLHttpRequest'
      },
      // Ukuran dikirim supaya tanda tangannya MENGUNCI panjang persis: R2
      // menolak kiriman yang berbeda, jadi URL ini tidak bisa dipakai
      // menaruh berkas lain yang lebih besar.
      body: JSON.stringify({ ukuran: blob.size })
    }).then(function (r) {
      if (!r.ok) { return null; }
      return r.json();
    }).then(function (j) {
      if (!j || !j.url || !j.kunci) { selesai(null); return; }
      return fetch(j.url, {
        method: 'PUT',
        headers: { 'Content-Type': 'image/jpeg' },
        body: blob
      }).then(function (r2) {
        selesai(r2.ok ? j.kunci : null);
      });
    }).catch(function () {
      // Jaringan putus / CORS diblokir: jalur lama masih bisa dipakai.
      selesai(null);
    });
  }

  if (tombolJepret) tombolJepret.addEventListener('click', jepret);
  if (tombolUlang) tombolUlang.addEventListener('click', function () { nyalakanKamera(); });
  if (tombolNyalakan) tombolNyalakan.addEventListener('click', nyalakanKamera);

  /* Kamera hidup HANYA di halaman yang masih punya formulir presensi, dan mati
     saat modal Penjelasan dibuka. Tanpa JavaScript, presensi tidak bisa
     dipakai, dan itu memang disengaja: foto harus dari kamera saat itu juga. */
  function hidupkanKamera() {
    if (adaPotret || aliran) return;
    // Modal terbuka (mis. Penjelasan) menutupi halaman: jangan menyalakan kamera
    // di belakangnya — lampu kamera yang menyala tanpa terlihat itu tidak sopan.
    if (document.querySelector('dialog.modal[open]')) return;
    nyalakanKamera();
  }

  var pembuka = document.querySelector('[data-kamera-mulai]');
  if (pembuka) pembuka.addEventListener('click', hidupkanKamera);

  // Modal apa pun yang dibuka mematikan kamera; yang ditutup menyalakannya lagi
  // (kalau formulirnya masih ada dan fotonya belum diambil).
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t || !t.closest) return;
    if (t.closest('[data-buka-modal], [data-buka]') || (t.closest('a') && /#kdkmp-hubung/.test(t.getAttribute('href') || ''))) {
      hentikanKamera();
    }
  }, true);
  document.querySelectorAll('dialog.modal').forEach(function (d) {
    d.addEventListener('close', hidupkanKamera);
  });

  /* ---------- lokasi ---------- */
  // Akurasi terburuk yang masih dianggap bukti titik lokasi. Cerminan
  // AgriController::AKURASI_MAKS. Tanpa ini, ponsel tanpa GPS menjawab dengan
  // titik dari menara seluler (ribuan meter) dan "titik lokasi" itu lolos
  // seolah-olah orangnya memang di sana.
  var AKURASI_MAKS = 500;

  function tampilkanLokasi(p) {
    var c = p.coords;
    if (c.accuracy > AKURASI_MAKS) {
      // Jangan simpan titiknya: titik seburuk ini bukan tempat orang berdiri.
      form.querySelector('[name="lat"]').value = '';
      form.querySelector('[name="lng"]').value = '';
      form.querySelector('[name="akurasi"]').value = '';
      statusLokasiTeks('Titik lokasi terlalu kasar (±' + Math.round(c.accuracy)
        + ' m — batas ' + AKURASI_MAKS + ' m). Keluar ke area terbuka, tunggu sampai GPS terkunci, lalu coba lagi.', true);
      periksa();
      return;
    }
    form.querySelector('[name="lat"]').value = c.latitude.toFixed(6);
    form.querySelector('[name="lng"]').value = c.longitude.toFixed(6);
    form.querySelector('[name="akurasi"]').value = String(Math.round(c.accuracy));
    var bulat = function (v) { return Math.round(v * 1000) / 1000; };
    form.querySelector('[name="lokasi"]').value = bulat(c.latitude) + ', ' + bulat(c.longitude);
    if (statusLokasi) {
      statusLokasi.textContent = 'Titik lokasi tersimpan · ±' + Math.round(c.accuracy) + ' m';
      statusLokasi.style.color = '';
    }
    periksa();
  }

  function statusLokasiTeks(t, galat) {
    if (!statusLokasi) return;
    statusLokasi.textContent = t;
    statusLokasi.style.color = galat ? '#96201e' : '';
  }

  if (tombolLokasi) {
    if (!navigator.geolocation) {
      tombolLokasi.disabled = true;
      statusLokasiTeks('Peramban ini tidak menyediakan lokasi. Akses lewat https://, bukan http://', true);
    } else {
      tombolLokasi.addEventListener('click', function () {
        tombolLokasi.disabled = true;
        statusLokasiTeks('Mengambil titik lokasi…');
        navigator.geolocation.getCurrentPosition(
          function (p) { tombolLokasi.disabled = false; tampilkanLokasi(p); },
          function (e) {
            tombolLokasi.disabled = false;
            statusLokasiTeks(e.code === 1
              ? 'Izin lokasi ditolak. Aktifkan di pengaturan situs, lalu coba lagi.'
              : 'Titik lokasi gagal diambil. Coba lagi di area terbuka.', true);
          },
          // maximumAge 0 = jangan pakai titik lama yang di-cache; timeout 15 dtk
          // supaya tombolnya tidak menggantung di ruang tanpa sinyal.
          { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
      });
    }
  }

  /* ---------- waktu perangkat ---------- */
  var jamPerangkat = form.querySelector('[data-jam-perangkat]');
  function capWaktu() {
    var d = new Date();
    form.querySelector('[name="waktu"]').value = d.toISOString();
    var off = -d.getTimezoneOffset();
    var tanda = off >= 0 ? '+' : '-';
    var jam = String(Math.floor(Math.abs(off) / 60)).padStart(2, '0');
    var mnt = String(Math.abs(off) % 60).padStart(2, '0');
    var zona = 'UTC' + tanda + jam + ':' + mnt;
    try { zona = Intl.DateTimeFormat().resolvedOptions().timeZone || zona; } catch (e) {}
    form.querySelector('[name="zona"]').value = zona;
    if (jamPerangkat) {
      jamPerangkat.textContent = d.toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' });
    }
  }

  /* ---------- kelengkapan sebelum kirim ---------- */
  // Judul kegiatan, foto, & titik lokasi tersimpan di input TERSEMBUNYI atau
  // diisi bahasa JavaScript, dan keduanya TIDAK ikut validasi bawaan peramban —
  // jadi tanpa pemeriksaan ini formulir kosong tetap terkirim dan server yang
  // menolaknya. Dua pertahanan sekaligus, karena keduanya pernah gagal
  // sendiri-sendiri:
  //   (a) tombolnya TERKUNCI selagi belum lengkap, dengan alasannya tertulis di
  //       `[data-siap-status]` — tombol kelabu tanpa keterangan tidak memberi
  //       tahu apa yang kurang, jadi pengguna hanya menekannya berulang;
  //   (b) kiriman yang belum lengkap dicegat di `submit` — penguncian bisa
  //       dilewati (Enter di kolom judul, atau JS yang mengirim langsung).
  var statusSiap = form.querySelector('[data-siap-status]');
  var judul = form.querySelector('[name="catatan"]');

  function periksa() {
    var adaFoto = adaPotret;
    var adaLokasi = form.querySelector('[name="lat"]').value !== '';
    var adaJudul = !!judul && judul.value.trim().length >= 3;
    var siap = adaFoto && adaLokasi && adaJudul;
    if (tombolKirim) {
      tombolKirim.disabled = !siap;
      tombolKirim.title = siap ? '' : (!adaJudul ? 'Judul kegiatan belum diisi'
        : (adaFoto ? 'Titik lokasi belum diambil' : 'Foto belum diambil'));
    }
    if (statusSiap) {
      statusSiap.textContent = siap ? 'Siap dikirim — judul, foto, dan titik lokasi sudah lengkap.'
        : 'Belum bisa dikirim: ' + (!adaJudul ? 'isi judul kegiatan'
          : (!adaFoto ? 'ambil foto dari kamera' : 'ambil titik lokasi')) + '.';
      statusSiap.style.color = siap ? '' : '#96201e';
    }
    return siap;
  }

  // Judul kegiatan diisi mengetik, jadi kelengkapan diperiksa tiap ketikan —
  // tanpa ini tombolnya tetap kelabu sesudah kolomnya diisi.
  if (judul) judul.addEventListener('input', periksa);

  // Jam perangkat diambil ulang tepat sebelum dikirim: halaman boleh terbuka
  // lama, dan yang dicatat harus jam saat tombolnya ditekan.
  //
  // `stopPropagation()` WAJIB saat kiriman dibatalkan: hub.js memasang penangan
  // `submit` GLOBAL di document yang menyalakan spinner pada tombol + bilah
  // #hub-proses. Penangan itu berjalan SESUDAH yang ini, dan karena kita tidak
  // jadi berpindah halaman, spinnernya berputar selamanya — itulah keluhan
  // "cuma loading". Kiriman yang SAH sengaja dibiarkan menggelembung: di situ
  // spinner memang benar (halamannya sungguh dimuat ulang).
  form.addEventListener('submit', function (ev) {
    capWaktu();
    if (!periksa()) {
      ev.preventDefault();
      ev.stopPropagation();
      // Sebabnya disebut dengan nama yang sama seperti di `periksa()`; kolom
      // judul tidak punya penanda galat sendiri, jadi tempatnya di situ.
      if (!judul || judul.value.trim().length < 3) {
        if (judul) judul.focus();
      } else if (!adaPotret) {
        beri('Ambil foto lewat kamera dulu — kamera ada di bagian atas form ini.', true);
        hidupkanKamera();
      } else {
        statusLokasiTeks('Ambil titik lokasi dulu — tekan tombol "Ambil titik lokasi".', true);
      }
      return;
    }
    if (tombolKirim) {
      tombolKirim.disabled = true;
      tombolKirim.textContent = 'Mengirim…';
    }
    hentikanKamera();
  });

  // Keadaan awal: tombol terkunci dengan alasan yang terbaca, bukan diam.
  capWaktu();
  periksa();
  // Kamera dinyalakan sendiri saat halaman siap: ini halaman kerja presensi,
  // bukan pratinjau — pengguna tidak perlu mencari tombol untuk melihat
  // wajahnya. Kegagalan izin kamera tampil sebagai pesan, bukan kotak hitam.
  hidupkanKamera();
})();

/* Sambung SIMKOPDES tanpa F12: markah (bookmarklet) di halaman Absen KDKMP
   membawa token dari tab SIMKOPDES ke sini lewat fragmen URL
   (`#simp=...`) — fragmen tidak pernah dikirim ke server, jadi token tidak
   masuk log akses. Begitu halaman siap: token diisikan ke form sambung, fragmen
   dibersihkan dari riwayat, lalu form dikirim sendiri.
   ponytail: hanya jalan di halaman Absen KDKMP (satu-satunya tempat token
   berguna); pindahkan ke halaman lain bila kelak ada konsumen kedua. */
(function () {
  var cocok = /[#&]simp=([^&]+)/.exec(location.hash);
  if (!cocok) return;

  var form = document.querySelector('#kdkmp-hubung form');
  if (!form) return;
  var isi = form.querySelector('input[name="token"]');
  if (!isi) return;

  var token = decodeURIComponent(cocok[1]);
  // Fragmen dibuang lebih dulu supaya token tidak tertinggal di riwayat/markah.
  history.replaceState(null, '', location.pathname + location.search);
  if (!token) return;

  isi.value = token;
  form.submit();
})();

/*
 * Absen Agrinashub — kamera, kompresi, titik lokasi, dan unggah LANGSUNG ke R2.
 *
 * Tiga hal yang menentukan bentuk berkas ini:
 *
 * 1. FOTO TIDAK LEWAT SERVER. Peramban meminta izin tulis (presign ~0,6 ms),
 *    lalu mengirim byte fotonya langsung ke R2. Formulir hanya membawa NAMA
 *    berkas. Kalau server yang mengangkut foto, satu foto menahan satu worker
 *    PHP selama seluruh byte berpindah — dan itu yang membuat absensi massal
 *    membekukan situs.
 *
 * 2. FOTO DIKOMPRES DI PERANGKAT. Tangga kualitas di bawah menurunkan mutu
 *    hanya bila perlu, jadi foto wajah yang ringan tidak ikut dikorbankan.
 *    Yang penting bukan rata-ratanya, melainkan TIDAK ADA berkas yang meledak:
 *    satu foto 117 KB di sinyal 2G menahan orang 40 detik.
 *
 * 3. TOMBOL TERKUNCI SAMPAI LENGKAP, dengan ALASAN tertulis. Tombol kelabu tanpa
 *    keterangan membuat orang menekannya berulang dan menyimpulkan aplikasinya
 *    rusak.
 */
(function () {
  var form = document.querySelector('form[data-absen]');
  if (!form) return;

  var MAKS_BYTE = 1.5 * 1024 * 1024;
  var TARGET_BYTE = 70 * 1024;
  var POTRET_LEBAR = 960;

  // Rung dari mutu tertinggi ke terendah; rung pertama yang muat dipakai.
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
  var tombolLokasi = form.querySelector('[data-lokasi]');
  var statusLokasi = form.querySelector('[data-lokasi-status]');
  var rinciLokasi = form.querySelector('[data-lokasi-rinci]');
  var koordLokasi = form.querySelector('[data-lokasi-koord]');
  var akurasiLokasi = form.querySelector('[data-lokasi-akurasi]');
  var statusSiap = form.querySelector('[data-siap-status]');
  var tombolKirim = form.querySelector('[data-kirim]');
  var kolomJenis = form.querySelector('[name="jenis"]');
  var aliran = null;
  var adaFoto = false;
  var adaLokasi = false;

  // 500 m terlalu ketat untuk laptop/PC: geolocation di sana memakai WiFi/IP,
  // bukan GPS, dan akurasi 1000-3000 m itu NORMAL di perkotaan. Dengan batas
  // 500 tombol tidak akan pernah terbuka di komputer — persis keluhan
  // "tombol simpan tidak berfungsi". 2000 m masih menunjukkan lokasi yang
  // sama kotanya, cukup sebagai bukti; di ponsel dengan GPS angkanya jauh
  // lebih kecil (±5-50 m).
  var AKURASI_MAKS = 2000;

  function beri(pesan, galat) {
    if (!pesanKamera) return;
    pesanKamera.textContent = pesan;
    pesanKamera.style.color = galat ? '#a32218' : '';
  }

  /* ---------- kamera ---------- */
  function nyalakan() {
    if (!video || aliran) return;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      beri('Peramban ini tidak mendukung kamera. Pakai Chrome/Safari terbaru.', true);
      return;
    }
    navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 1280 } },
      audio: false
    }).then(function (s) {
      aliran = s;
      video.srcObject = s;
      video.play().catch(function () {});
      beri('Kamera siap. Ambil foto saat wajah terlihat jelas.');
    }).catch(function () {
      beri('Izin kamera ditolak. Absen butuh foto — izinkan kamera di pengaturan peramban.', true);
    });
  }

  function matikan() {
    if (!aliran) return;
    aliran.getTracks().forEach(function (t) { t.stop(); });
    aliran = null;
    if (video) video.srcObject = null;
  }

  /* ---------- kompresi adaptif ---------- */
  function kompres(sumber) {
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
      var b = Math.round(d.length * 3 / 4);
      terakhir = { data: d, byte: b, lebar: kanvas.width };
      if (b <= TARGET_BYTE) break;
    }
    return terakhir;
  }

  function jepret() {
    if (!video || !video.videoWidth) {
      beri('Kamera belum siap. Tunggu sebentar, lalu coba lagi.', true);
      return;
    }
    // Potong 3:4 dari tengah frame, supaya daftar hadir seragam dan wajah
    // tidak teregang.
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

    var hasil = kompres(c);
    if (hasil.byte > MAKS_BYTE) {
      beri('Foto masih ' + Math.round(hasil.byte / 1048576 * 10) / 10
        + ' MB (batas 1,5 MB). Coba dengan pencahayaan lebih terang.', true);
      return;
    }

    matikan();
    beri('Menyimpan foto ke penyimpanan…');
    unggah(hasil.data, function (kunci) {
      var kolomKunci = form.querySelector('[name="foto_kunci"]');
      var kolomData = form.querySelector('[name="foto_data"]');
      if (kunci) {
        if (kolomKunci) kolomKunci.value = kunci;
        if (kolomData) kolomData.value = '';
        beri('Foto siap (' + Math.round(hasil.byte / 1024) + ' KB dari ' + hasil.lebar + ' px).');
      } else {
        if (kolomKunci) kolomKunci.value = '';
        if (kolomData) kolomData.value = hasil.data;
        beri('Foto siap (' + Math.round(hasil.byte / 1024) + ' KB).');
      }
      adaFoto = true;
      if (potret) { potret.src = hasil.data; potret.hidden = false; }
      if (tombolJepret) tombolJepret.hidden = true;
      if (tombolUlang) tombolUlang.hidden = false;
      periksa();
    });
  }

  function dataUrlKeBlob(d) {
    var i = d.indexOf(',');
    var tipe = d.substring(5, i).split(';')[0];
    var biner = atob(d.substring(i + 1));
    var buf = new Uint8Array(biner.length);
    for (var k = 0; k < biner.length; k++) buf[k] = biner.charCodeAt(k);
    return new Blob([buf], { type: tipe });
  }

  function unggah(data, selesai) {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var url = form.getAttribute('data-url-unggah');
    if (!meta || !url || !window.fetch || !window.Blob) { selesai(null); return; }
    var blob;
    try { blob = dataUrlKeBlob(data); } catch (e) { selesai(null); return; }

    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': meta.getAttribute('content'),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ ukuran: blob.size })
    }).then(function (r) {
      return r.ok ? r.json() : null;
    }).then(function (j) {
      if (!j || !j.url || !j.kunci) { selesai(null); return; }
      return fetch(j.url, {
        method: 'PUT',
        headers: { 'Content-Type': 'image/jpeg' },
        body: blob
      }).then(function (r2) { selesai(r2.ok ? j.kunci : null); });
    }).catch(function () {
      // CORS/jaringan gagal: jalur lama (data URL) masih bisa dipakai, supaya
      // absen tidak pernah batal gara-gara jaringan.
      selesai(null);
    });
  }

  /* ---------- lokasi ---------- */
  function ambilLokasi() {
    if (!navigator.geolocation) {
      teksLokasi('Peramban ini tidak memberi titik lokasi.', true);
      return;
    }
    teksLokasi('Mengambil titik lokasi…');
    navigator.geolocation.getCurrentPosition(function (p) {
      var akurasi = Math.round(p.coords.accuracy);
      if (akurasi > AKURASI_MAKS) {
        teksLokasi('Akurasi titik terlalu kasar (±' + akurasi + ' m). Keluar ke area terbuka, lalu coba lagi.', true);
        adaLokasi = false;
        periksa();
        return;
      }
      form.querySelector('[name="lat"]').value = p.coords.latitude.toFixed(6);
      form.querySelector('[name="lng"]').value = p.coords.longitude.toFixed(6);
      form.querySelector('[name="akurasi"]').value = String(akurasi);
      adaLokasi = true;
      teksLokasi('Titik lokasi tersimpan.');
      if (rinciLokasi) rinciLokasi.hidden = false;
      if (koordLokasi) koordLokasi.textContent =
        p.coords.latitude.toFixed(6) + ', ' + p.coords.longitude.toFixed(6);
      if (akurasiLokasi) akurasiLokasi.textContent = '±' + akurasi + ' m';
      periksa();
    }, function (e) {
      teksLokasi(e.code === 1
        ? 'Izin lokasi ditolak. Absen butuh titik lokasi — izinkan akses lokasi.'
        : 'Titik lokasi tidak didapat. Coba di area terbuka.', true);
      adaLokasi = false;
      periksa();
    }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
  }

  function teksLokasi(t, galat) {
    if (!statusLokasi) return;
    statusLokasi.textContent = t;
    statusLokasi.style.color = galat ? '#a32218' : '';
  }

  /* ---------- kelengkapan ---------- */
  function jenis() {
    var r = form.querySelector('[name="pilih_jenis"]:checked');
    return r ? r.value : (kolomJenis ? kolomJenis.value : '');
  }

  // Apa yang masih kurang, dalam kalimat yang bisa ditindaklanjuti.
  function alasanBelum() {
    var j = jenis();
    var butuhBukti = (j === 'masuk' || j === 'keluar');
    if (!butuhBukti) return '';
    if (!adaFoto) return 'Foto belum diambil — tekan "Ambil Foto" dulu.';
    if (!adaLokasi) return 'Titik lokasi belum diambil — tekan "Ambil titik lokasi".';
    return '';
  }

  function periksa() {
    var j = jenis();
    var butuhBukti = (j === 'masuk' || j === 'keluar');
    var alasan = alasanBelum();

    // PENTING: tombol TIDAK PERNAH di-disable. Tombol mati tidak bisa menjelaskan
    // apa yang kurang; pengguna hanya menekannya berulang lalu menganggap
    // halamannya rusak. Yang berubah hanya rupa (.belum) dan teks alasannya.
    if (tombolKirim) {
      tombolKirim.classList.toggle('belum', !!alasan);
      tombolKirim.setAttribute('aria-disabled', alasan ? 'true' : 'false');
      tombolKirim.title = alasan;
    }
    if (statusSiap) {
      if (!butuhBukti) {
        statusSiap.textContent = 'Siap disimpan — istirahat tidak memerlukan foto.';
        statusSiap.style.color = '';
      } else if (!alasan) {
        statusSiap.textContent = 'Siap disimpan — foto dan titik lokasi sudah lengkap.';
        statusSiap.style.color = '';
      } else {
        statusSiap.textContent = 'Belum lengkap: ' + alasan;
        statusSiap.style.color = '#96201e';
      }
    }
    var bukti = form.querySelector('.bukti-baris');
    if (bukti) bukti.style.display = butuhBukti ? '' : 'none';

    return !alasan;
  }

  // Gulir ke bagian yang kurang, supaya alasannya tidak perlu dicari.
  function tunjukkanKekurangan() {
    var j = jenis();
    var butuhBukti = (j === 'masuk' || j === 'keluar');
    if (!butuhBukti) return;
    var sasaran = !adaFoto
      ? form.querySelector('.bukti-kotak')
      : form.querySelector('[data-lokasi]');
    if (sasaran && sasaran.scrollIntoView) {
      sasaran.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  function capWaktu() {
    var w = form.querySelector('[name="waktu"]');
    var z = form.querySelector('[name="zona"]');
    if (w) w.value = new Date().toISOString();
    if (z) {
      try { z.value = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; }
      catch (e) { z.value = ''; }
    }
  }

  /* ---------- pemasangan ---------- */
  if (tombolJepret) tombolJepret.addEventListener('click', jepret);
  if (tombolUlang) tombolUlang.addEventListener('click', function () {
    adaFoto = false;
    form.querySelector('[name="foto_kunci"]').value = '';
    form.querySelector('[name="foto_data"]').value = '';
    if (potret) potret.hidden = true;
    if (tombolJepret) tombolJepret.hidden = false;
    tombolUlang.hidden = true;
    nyalakan();
    periksa();
  });
  if (tombolLokasi) tombolLokasi.addEventListener('click', ambilLokasi);

  var radio = form.querySelectorAll('[name="pilih_jenis"]');
  for (var i = 0; i < radio.length; i++) {
    radio[i].addEventListener('change', function (ev) {
      if (kolomJenis) kolomJenis.value = ev.target.value;
      periksa();
    });
  }

  form.addEventListener('submit', function (ev) {
    capWaktu();
    var alasan = alasanBelum();
    if (alasan) {
      // Dibatalkan, TAPI dengan penjelasan — dan spinnernya dimatikan.
      // `stopPropagation()` wajib: hub.js memasang penangan submit global yang
      // menyalakan indikator proses, dan karena halaman tidak berpindah,
      // indikator itu akan berputar selamanya.
      ev.preventDefault();
      ev.stopPropagation();
      periksa();
      if (statusSiap) {
        statusSiap.textContent = 'Belum lengkap: ' + alasan;
        statusSiap.style.color = '#96201e';
      }
      tunjukkanKekurangan();
      return;
    }
    if (tombolKirim) {
      tombolKirim.textContent = 'Menyimpan…';
      tombolKirim.classList.add('belum');
    }
    matikan();
  });

  capWaktu();
  periksa();
  nyalakan();
})();

/* Percakapan gaya WhatsApp di atas cara kerja Lark Messenger.
 *
 * Yang membuatnya terasa "chat", bukan halaman formulir:
 *   1. Kirim tanpa muat ulang — pesan dikirim lewat fetch, gelembungnya muncul
 *      langsung, kotak tulis dikosongkan, halaman tidak berkedip.
 *   2. Denyut 4 detik — hanya pesan BARU yang ditanyakan (JSON kecil, bukan
 *      HTML halaman). Detak berhenti saat tab tidak terlihat.
 *   3. Muat pesan lebih lama — menggulir ke atas menarik gelombang berikutnya,
 *      posisi baca tidak melompat (tinggi yang ditambahkan dikompensasi).
 *
 * Gelembung TIDAK disusun ulang di JavaScript: server merender HTML-nya memakai
 * partial Blade yang sama dengan halaman pertama (`_pesan-satu`). JS hanya
 * menyisipkan HTML itu. Dulu ada penyusun gelembung sendiri di sini dan ia
 * perlahan berbeda dari versi server (tak ada reaksi, tak ada centang).
 *
 * Tanpa JS semuanya tetap jalan: formulirnya <form> biasa dan halaman disegarkan
 * seperti sebelumnya.
 */
(function () {
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  // ------------------------------------------- cari di dalam <select> panjang
  // Lingkup peserta (per provinsi: kabupaten/kecamatan/koperasi + manajer) dan
  // daftar rekan terlalu panjang untuk digulir satu per satu — pencariannya
  // dibawa **Select2** (`.pilih2`), bukan kotak cari terpisah di atas <select>.
  //
  // Lebar Select2 DIUKUR saat ia dipasang. Form "+ Baru" lahir dalam keadaan
  // `hidden` (lebar 0), jadi pemasangannya ditunda sampai form pertama kali
  // dibuka — kalau dipasang saat tersembunyi, kotaknya menciut jadi beberapa
  // piksel dan daftarnya tak bisa dibaca.
  var siapkanPilih2 = function (akar) {
    var jq = window.jQuery;
    if (!jq || !jq.fn || !jq.fn.select2) return 0;
    var n = 0;
    jq(akar).find('select.pilih2').each(function () {
      var $s = jq(this);
      if ($s.data('pilih2-siap')) return;
      // Di dalam wadah tersembunyi lebarnya 0 -> Select2 memasang dirinya
      // dengan lebar beberapa piksel. Lewati; dipasang saat wadahnya terlihat.
      if (!this.offsetParent) return;
      $s.data('pilih2-siap', true).select2({
        width: '100%',
        allowClear: true,                       // ada <option value=""> untuk membatalkan
        placeholder: $s.data('placeholder') || 'Pilih…',
        language: { noResults: function () { return 'Tidak ada yang cocok'; } },
      });
      n++;
    });
    return n;
  };
  siapkanPilih2(document);

  // -------------------------------------------------- pemilih peserta grup
  // Daftar peserta bisa panjang (semua rekan se-lingkup), jadi ia disaring dan
  // ditandai centang alih-alih memakai <select multiple> polos. "Selesai"
  // menutup daftar — pilihan tetap tersimpan di kotak centang tersembunyi,
  // jadi formulir tetap membawa `anggota[]`.
  var pilih = document.getElementById('wa-pilih-peserta');
  if (pilih) {
    var cariP = document.getElementById('wa-cari-peserta');
    var kotakP = document.getElementById('wa-daftar-peserta');
    var ringkas = document.getElementById('wa-terpilih');
    var kosongP = document.getElementById('wa-peserta-kosong');
    var selesai = document.getElementById('wa-selesai');
    var kotakCentang = function () { return pilih.querySelectorAll('input[name="anggota[]"]'); };
    var barisP = function () { return pilih.querySelectorAll('.pilih-baris'); };

    function perbarui() {
      var dipilih = [].slice.call(kotakCentang()).filter(function (c) { return c.checked; });
      if (ringkas) {
        ringkas.hidden = dipilih.length === 0;
        ringkas.textContent = dipilih.length + ' peserta dipilih: ' +
          dipilih.map(function (c) { return c.closest('label').querySelector('.pilih-nama').textContent.trim(); }).join(', ');
      }
      barisP().forEach(function (b) {
        b.classList.toggle('centang', b.querySelector('input').checked);
      });
    }

    function saring() {
      var q = (cariP.value || '').trim().toLowerCase();
      var tampil = 0;
      barisP().forEach(function (b) {
        var cocok = q === '' || (b.dataset.nama || '').includes(q);
        b.closest('li').hidden = !cocok;
        if (cocok) tampil++;
      });
      if (kosongP) kosongP.hidden = tampil !== 0;
    }

    cariP.addEventListener('input', saring);
    pilih.addEventListener('change', function (ev) {
      if (ev.target.matches('input[name="anggota[]"]')) perbarui();
    });
    if (selesai) {
      selesai.addEventListener('click', function () {
        kotakP.hidden = true;
        if (kosongP) kosongP.hidden = true;
        cariP.hidden = true;
        selesai.hidden = true;
        perbarui();
      });
    }
    // Membuka panel "+ Baru" menampilkan lagi daftarnya (pilihan tidak dihapus).
    // Delegasi di document, bukan pada tombolnya: blok ini hidup juga di halaman
    // DAFTAR percakapan, tempat tombolnya baru ada saat panel dibuka.
    document.addEventListener('click', function (ev) {
      if (!ev.target.closest('#wa-baru-buka') || kotakP.hidden === false) return;
      kotakP.hidden = false;
      cariP.hidden = false;
      if (selesai) selesai.hidden = false;
      saring();
    });
    perbarui();
  }

  var kotak = document.querySelector('.wa');
  var papan = document.getElementById('wa-pesan');
  var form = document.querySelector('.wa-kirim');

  // Kirim pesan, denyut, muat lebih lama, lampiran, rekam suara: bagian khusus
  // RUANG. Halaman DAFTAR tidak punya papan & kotak tulis, TAPI tetap memakai
  // bagian bawah skrip ini (cari percakapan, panel "+ Baru", laci ponsel).
  // Dulu di sini ada `return` lebih awal yang mematikan SELURUH bagian bawah di
  // halaman daftar — panel "+ Baru" tak pernah terbuka.
  if (kotak && papan && form) {
    var src = kotak.dataset.src;
    var csrfAman = kotak.dataset.csrf || csrf;
    var terakhir = Number(papan.dataset.terakhir || 0);
    var terawal = Number(papan.dataset.terawal || 0);
    var lebih = papan.dataset.lebih === '1';
    var sedang = false;

    var keBawah = function (mulus) {
      papan.scrollTo({ top: papan.scrollHeight, behavior: mulus ? 'smooth' : 'auto' });
    };
    var dekatBawah = function () {
      return papan.scrollHeight - papan.scrollTop - papan.clientHeight < 90;
    };
    var tombolLebih = document.getElementById('wa-lebih');

    keBawah(false);
    if (tombolLebih) tombolLebih.hidden = !lebih;

    // Sisa pesan lama yang paling tua di layar "dikelompokkan" ke pesan
    // berikutnya yang lebih baru: kalau namanya sama, nama pengirim ganda
    // dihapus. Dipakai setelah gelombang lama disisipkan di atas.
    function rapikanKelompok() {
      var semua = papan.querySelectorAll('.wa-b');
      for (var i = 1; i < semua.length; i++) {
        var a = semua[i - 1].querySelector('.wa-nama');
        var b = semua[i].querySelector('.wa-nama');
        if (a && b && a.textContent === b.textContent) b.remove();
      }
    }

    /** Sisipkan HTML gelembung (dari server) ke papan. */
    function sisip(html, diAtas) {
      if (!html) return;
      var wadah = document.createElement('div');
      wadah.innerHTML = html;
      var simpul = [].slice.call(wadah.children);
      if (diAtas) {
        var tinggiLama = papan.scrollHeight;
        var jangkar = papan.firstElementChild && papan.firstElementChild.id === 'wa-lebih'
          ? papan.firstElementChild.nextElementSibling : papan.firstElementChild;
        simpul.reverse().forEach(function (el) { papan.insertBefore(el, jangkar); });
        // Posisi baca tidak boleh melompat: tinggi yang bertambah dikompensasi.
        papan.scrollTop += papan.scrollHeight - tinggiLama;
      } else {
        simpul.forEach(function (el) { papan.appendChild(el); });
      }
    }

    // ------------------------------------------------------------- muat lama
    var memuatLama = false;
    async function muatLama() {
      if (memuatLama || !window.fetch || terawal <= 0) return;
      memuatLama = true;
      try {
        var res = await fetch(src + '?sebelum=' + terawal, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (res.ok) {
          var j = await res.json();
          var pesan = j.pesan || [];
          if (pesan.length) {
            terawal = Number(pesan[0].id);
            sisip(j.html, true);
            rapikanKelompok();
          }
          lebih = !!j.lebih_lama;
          if (tombolLebih) tombolLebih.hidden = !lebih;
        }
      } catch (e) { /* jaringan putus: tombolnya masih ada, coba lagi nanti */ }
      finally { memuatLama = false; }
    }
    if (tombolLebih) tombolLebih.addEventListener('click', muatLama);
    // Menggulir ke puncak juga memicu pemuatan — tanpa perlu mencari tombolnya.
    papan.addEventListener('scroll', function () {
      if (papan.scrollTop < 60) muatLama();
    });

    // ---------------------------------------------------------------- kirim
    // Formulirnya bertanda `data-mulai` di blade; hub.js melewatinya supaya tidak
    // memasang spinner & menonaktifkan tombol pada alur kirim tanpa muat ulang.
    form.addEventListener('submit', async function (ev) {
      var isi = form.querySelector('textarea');
      var nilai = (isi.value || '').trim();
      if (nilai === '') { ev.preventDefault(); return; }
      if (!window.fetch) return;      // tanpa fetch: biarkan formulir terkirim biasa

      ev.preventDefault();
      // FormData WAJIB dibangun SEBELUM kotak tulis dikosongkan di bawah: FormData
      // menyalin nilai formulir saat ia dibuat, jadi membangunnya setelah
      // `isi.value = ''` mengirim pesan KOSONG → server menolak 422 dan pengguna
      // melihat "Pesan gagal terkirim" untuk setiap pesan.
      var isian = new FormData(form);
      var mulai = document.getElementById('wa-mulai');
      if (mulai) mulai.remove();
      batalBalas();
      isi.value = '';
      isi.style.height = '';
      try {
        // Balasan atas pesan yang gagal: pesannya dikembalikan ke kotak tulis.
        var res = await fetch(form.action, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfAman, 'X-Requested-With': 'XMLHttpRequest' },
          body: isian,
          credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        await res.json();
      } catch (e) {
        isi.value = nilai;
        window.alert('Pesan gagal terkirim. Coba lagi.');
        return;
      }
      muatBaru();
    });

    // ---------------------------------------------------------------- denyut
    async function muatBaru() {
      if (sedang || document.hidden || !window.fetch) return;
      sedang = true;
      try {
        var res = await fetch(src + '?sejak=' + terakhir, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (!res.ok) return;
        var j = await res.json();
        var baru = (j.pesan || []).filter(function (p) { return Number(p.id) > terakhir; });
        if (!baru.length) return;

        var mulai = document.getElementById('wa-mulai');
        if (mulai) mulai.remove();
        var dekat = dekatBawah();
        sisip(j.html, false);
        terakhir = Number(baru[baru.length - 1].id);
        papan.dataset.terakhir = terakhir;
        rapikanKelompok();
        if (dekat) keBawah(true);
        // Sudah saya lihat = sudah saya baca (centang biru di sisi pengirim).
        tandaiBaca();
      } catch (e) {
        /* jaringan putus sesaat: coba lagi detak berikutnya, jangan ganggu pengguna */
      } finally {
        sedang = false;
      }
    }
    window.setInterval(muatBaru, 4000);

    /** Kabari server bahwa ruang ini sudah dilihat sampai pesan terakhir. */
    function tandaiBaca() {
      if (!window.fetch || document.hidden) return;
      fetch(src + '/baca', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfAman, 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      }).then(function (r) {
        if (!r.ok) return null;
        return r.json();
      }).catch(function () { /* diabaikan: sekadar penanda baca */ });
    }
    tandaiBaca();
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) { muatBaru(); tandaiBaca(); }
    });

    // ------------------------------------------------------- kotak tulis
    var area = form.querySelector('textarea');
    function tumbuh() {
      area.style.height = 'auto';
      area.style.height = Math.min(area.scrollHeight, 132) + 'px';
    }
    area.addEventListener('input', tumbuh);
    // Enter mengirim (seperti WhatsApp); Shift+Enter untuk baris baru.
    area.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); form.requestSubmit(); }
    });

    // ------------------------------------------------------------- balasan
    var bilahBalas = document.getElementById('wa-balas-bilah');
    var inputBalas = document.getElementById('wa-balas-id');
    function batalBalas() {
      if (bilahBalas) bilahBalas.hidden = true;
      if (inputBalas) inputBalas.value = '';
    }
    papan.addEventListener('click', function (ev) {
      var balas = ev.target.closest('.wa-balas');
      if (balas && bilahBalas) {
        inputBalas.value = balas.dataset.id;
        document.getElementById('wa-balas-nama').textContent = balas.dataset.nama || '';
        document.getElementById('wa-balas-isi').textContent = balas.dataset.cuplikan || '';
        bilahBalas.hidden = false;
        area.focus();
        return;
      }
      // Kutipan: ketuk = lompat ke pesan asalnya (ala WhatsApp).
      var kutip = ev.target.closest('.wa-kutip');
      if (kutip) {
        ev.preventDefault();
        var asal = document.getElementById(kutip.dataset.ke ? 'p' + kutip.dataset.ke : '');
        if (asal) {
          asal.scrollIntoView({ block: 'center', behavior: 'smooth' });
          asal.classList.add('wa-sorot');
          window.setTimeout(function () { asal.classList.remove('wa-sorot'); }, 1600);
        }
      }
    });
    var balasBatal = document.getElementById('wa-balas-batal');
    if (balasBatal) balasBatal.addEventListener('click', batalBalas);

    // -------------------------------------------- aksi pesan (reaksi & lainnya)
    // Reaksi & semua aksi lain dikirim ke rute yang SAMA, jadi satu penanganan:
    // jawabannya memuat HTML seluruh papan versi terbaru, dan papan ditimpa.
    async function kirimAksi(aksi, pesanId, emoji, isi) {
      var isian = new FormData();
      isian.append('aksi', aksi);
      isian.append('pesan_id', pesanId);
      if (emoji) isian.append('emoji', emoji);
      if (isi !== undefined && isi !== null) isian.append('isi', isi);
      var res = await fetch(kotak.dataset.src + '/aksi', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfAman, 'X-Requested-With': 'XMLHttpRequest' },
        body: isian,
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var j = await res.json();
      if (j.html) {
        var bukaTerakhir = papan.scrollHeight - papan.scrollTop - papan.clientHeight < 90;
        papan.innerHTML = '';
        var wadah = document.createElement('div');
        wadah.innerHTML = '<button class="tombol samar mini wa-lebih" type="button" id="wa-lebih"' +
          (j.lebih_lama ? '' : ' hidden') + '>Muat pesan lebih lama</button>' + j.html;
        var simpul = [].slice.call(wadah.children);
        simpul.forEach(function (el) { papan.appendChild(el); });
        tombolLebih = document.getElementById('wa-lebih');
        if (tombolLebih) tombolLebih.addEventListener('click', muatLama);
        var ps = j.pesan || [];
        if (ps.length) { terakhir = Number(ps[ps.length - 1].id); terawal = Number(ps[0].id); }
        if (bukaTerakhir) keBawah(false);
      }
    }

    papan.addEventListener('click', function (ev) {
      var rx = ev.target.closest('.wa-rx');
      if (!rx) return;
      var b = rx.closest('.wa-b');
      if (!b) return;
      kirimAksi('reaksi', b.dataset.id, rx.dataset.emoji).catch(function () {
        window.alert('Reaksi gagal dikirim.');
      });
    });

    // ------------------------------------------------------- lampiran & suara
    var panel = document.getElementById('wa-lampir');
    var bukaLampir = document.getElementById('wa-lampir-buka');
    if (bukaLampir && panel) {
      bukaLampir.addEventListener('click', function () { panel.hidden = !panel.hidden; });
    }

    /** Kirim satu berkas (foto/berkas/suara) ke rute yang sesuai. */
    async function kirimBerkas(jalur, blob, nama, jenis) {
      var isian = new FormData();
      isian.append('berkas', blob, nama);
      if (jenis) isian.append('jenis', jenis);
      if (inputBalas && inputBalas.value) isian.append('balas_id', inputBalas.value);
      var res = await fetch(jalur, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfAman, 'X-Requested-With': 'XMLHttpRequest' },
        body: isian,
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      batalBalas();
      muatBaru();
    }

    // Kompresi foto di peramban: foto ponsel 4–8 MB tidak perlu dikirim mentah.
    // Kanvas sudah ada di peramban — tidak perlu pustaka (rung 4 tangga).
    function kompres(foto, maksSisi, mutu) {
      // ponytail: kalau bukan gambar (mis. PDF) langsung dipakai apa adanya.
      if (!/^image\//.test(foto.type) || foto.type === 'image/gif') return Promise.resolve(foto);
      return new Promise(function (selesai) {
        var img = new Image();
        var url = URL.createObjectURL(foto);
        img.onload = function () {
          var skala = Math.min(1, maksSisi / Math.max(img.width, img.height));
          var c = document.createElement('canvas');
          c.width = Math.round(img.width * skala);
          c.height = Math.round(img.height * skala);
          c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
          URL.revokeObjectURL(url);
          c.toBlob(function (b) {
            // Kalau hasil kompresi malah lebih besar (foto sudah kecil), pakai aslinya.
            selesai(b && b.size < foto.size ? b : foto);
          }, 'image/jpeg', mutu);
        };
        img.onerror = function () { URL.revokeObjectURL(url); selesai(foto); };
        img.src = url;
      });
    }

    var berkas = document.getElementById('wa-berkas');
    if (berkas) {
      berkas.addEventListener('change', async function () {
        var f = berkas.files && berkas.files[0];
        if (!f) return;
        var info = document.getElementById('wa-rekam-info');
        if (info) info.textContent = 'Mengunggah…';
        try {
          var kirim = await kompres(f, 1600, 0.72);
          var nama = kirim.name || f.name || 'berkas.jpg';
          if (kirim !== f && !/\.jpe?g$/i.test(nama)) nama = nama.replace(/\.[^.]+$/, '') + '.jpg';
          await kirimBerkas(form.dataset.lampiran, kirim, nama, kirim !== f ? 'gambar' : '');
          if (info) info.textContent = 'Terkirim.';
        } catch (e) {
          if (info) info.textContent = 'Gagal mengirim lampiran.';
        }
        berkas.value = '';
      });
    }

    // Rekam suara: MediaRecorder sudah ada di peramban.
    var rekamMulai = document.getElementById('wa-rekam-mulai');
    var rekamStop = document.getElementById('wa-rekam-stop');
    var infoRekam = document.getElementById('wa-rekam-info');
    var perekam = null, potongan = [], mulaiRekam = 0, jamRekam = null;
    if (rekamMulai && navigator.mediaDevices && window.MediaRecorder) {
      rekamMulai.addEventListener('click', async function () {
        try {
          var aliran = await navigator.mediaDevices.getUserMedia({ audio: true });
          perekam = new MediaRecorder(aliran);
          potongan = [];
          perekam.ondataavailable = function (e) { if (e.data.size) potongan.push(e.data); };
          perekam.onstop = async function () {
            aliran.getTracks().forEach(function (t) { t.stop(); });
            if (infoRekam) infoRekam.textContent = 'Mengirim suara…';
            try {
              await kirimBerkas(form.dataset.suara, new Blob(potongan, { type: 'audio/webm' }), 'pesan-suara.webm');
              if (infoRekam) infoRekam.textContent = 'Pesan suara terkirim.';
            } catch (e) {
              if (infoRekam) infoRekam.textContent = 'Gagal mengirim pesan suara.';
            }
          };
          perekam.start();
          mulaiRekam = Date.now();
          rekamMulai.hidden = true;
          rekamStop.hidden = false;
          jamRekam = window.setInterval(function () {
            if (infoRekam) infoRekam.textContent = 'Merekam… ' +
              Math.round((Date.now() - mulaiRekam) / 1000) + ' dtk';
          }, 500);
        } catch (e) {
          if (infoRekam) infoRekam.textContent = 'Izin mikrofon ditolak.';
        }
      });
      rekamStop.addEventListener('click', function () {
        if (perekam && perekam.state !== 'inactive') perekam.stop();
        window.clearInterval(jamRekam);
        rekamStop.hidden = true;
        rekamMulai.hidden = false;
      });
    } else if (rekamMulai) {
      rekamMulai.hidden = true;
    }

    // Lihat foto besar (ala WhatsApp): ketuk foto = buka dialog.
    papan.addEventListener('click', function (ev) {
      var foto = ev.target.closest('.wa-foto');
      var dialog = document.getElementById('wa-lihat-foto');
      if (!foto || !dialog || !dialog.showModal) return;
      ev.preventDefault();
      document.getElementById('wa-lihat-img').src = foto.dataset.foto;
      dialog.showModal();
    });
  }

  // ------------------------------------------------------- cari percakapan
  var cari = document.getElementById('wa-cari');
  if (cari) {
    cari.addEventListener('input', function () {
      var q = cari.value.trim().toLowerCase();
      var tampil = 0;
      document.querySelectorAll('#wa-daftar .wa-baris').forEach(function (a) {
        var cocok = q === '' || (a.dataset.nama || '').includes(q);
        a.hidden = !cocok;
        if (cocok) tampil++;
      });
      var kosong = document.getElementById('wa-takada');
      if (kosong) kosong.hidden = tampil !== 0;
    });
  }

  // --------------------------------------------------- penyaring kategori
  // Pil kategori adalah TAUTAN (?kategori=) supaya tetap bekerja tanpa JS.
  // Dengan JS, penyaringan dilakukan seketika di peramban (datanya sudah ada)
  // dan URL-nya diperbarui — tidak ada muat ulang, tapi tautannya tetap sah
  // kalau dikirim/disematkan.
  var pil = document.querySelectorAll('.wa-pil');
  var daftarKotak = document.getElementById('wa-daftar');
  if (pil.length && daftarKotak) {
    pil.forEach(function (p) {
      p.addEventListener('click', function (ev) {
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey) return;   // buka di tab lain
        ev.preventDefault();
        var kat = p.dataset.saring;
        pil.forEach(function (x) {
          x.classList.toggle('ini', x === p);
          x.setAttribute('aria-pressed', x === p ? 'true' : 'false');
        });
        daftarKotak.dataset.kategori = kat;
        saringKategori(kat);
        history.replaceState(null, '', p.href);
      });
    });

    function saringKategori(kat) {
      var cocok = function (b) {
        switch (kat) {
          case 'belum': return b.dataset.belum === '1';
          case 'tersemat': return b.dataset.tersemat === '1';
          case 'pribadi': return b.dataset.pribadi === '1';
          case 'grup': return b.dataset.grup === '1';
          default: return true;
        }
      };
      var tampil = 0;
      document.querySelectorAll('#wa-daftar .wa-baris').forEach(function (b) {
        var ok = cocok(b);
        b.hidden = !ok;
        if (ok) tampil++;
      });
      var kosong = document.getElementById('wa-takada');
      if (kosong) {
        kosong.hidden = tampil !== 0;
        kosong.textContent = tampil === 0 ? 'Tidak ada percakapan di kategori ini.' : '';
      }
    }
  }

  // ------------------------------------------- sematkan percakapan (tanpa ulang)
  // Tanpa JS tombolnya tetap <form> yang bekerja; dengan JS, daftarnya ikut
  // dipindah tanpa memuat ulang halaman.
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f.matches('.wa-semat-form') || !window.fetch) return;
    ev.preventDefault();
    fetch(f.action, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(f),
      credentials: 'same-origin',
    }).then(function (r) {
      if (r.ok) window.location.reload();
    }).catch(function () { f.submit(); });
  });

  // -------------------------------------------------- percakapan / grup baru
  var buka = document.getElementById('wa-baru-buka');
  var bar = document.getElementById('wa-baru');
  if (buka && bar) {
    var siapSebelumnya = siapkanPilih2(bar) > 0;
    buka.addEventListener('click', function () {
      bar.hidden = !bar.hidden;
      buka.setAttribute('aria-expanded', bar.hidden ? 'false' : 'true');
      if (!bar.hidden) {
        // Selagi tersembunyi Select2 tak bisa mengukur lebarnya, jadi ia
        // dipasang/diukur ulang tepat setelah form terlihat.
        if (siapSebelumnya) window.jQuery(bar).find('select.pilih2').select2('close');
        else siapSebelumnya = siapkanPilih2(bar) > 0;
        var n = bar.querySelector('input[name=nama]'); if (n) n.focus();
      }
    });
  }
  // -------------------------------------------------- mode Grup / Forum
  // Satu formulir, dua mode: bedanya cuma apakah ruangnya punya TOPIK. Kalau
  // forum, kolomnya dibuka dan tombolnya berubah kata — tanpa JS ruangnya tetap
  // dibuat, hanya terbaca sebagai grup biasa.
  var modeR = document.querySelectorAll('#wa-baru input[name="mode"]');
  var kotakTopik = document.getElementById('wa-topik');
  var tombolBuat = document.getElementById('wa-buat');
  if (modeR.length && kotakTopik && tombolBuat) {
    var aturMode = function () {
      var terpilih = document.querySelector('#wa-baru input[name="mode"]:checked');
      var forum = !!terpilih && terpilih.value === 'forum';
      kotakTopik.hidden = !forum;
      tombolBuat.textContent = forum ? 'Buat Forum' : 'Buat Grup';
    };
    [].slice.call(modeR).forEach(function (r) { r.addEventListener('change', aturMode); });
    aturMode();
  }

  // "Mulai Pribadi" mengirim ke rute percakapan pribadi dengan rekan terpilih —
  // formulirnya sendiri tetap satu, jadi tidak perlu satu formulir per pengguna.
  var pribadi = document.getElementById('wa-pribadi');
  if (pribadi) {
    pribadi.addEventListener('click', function () {
      var rekan = document.getElementById('wa-rekan');
      if (!rekan || !rekan.value) { window.alert('Pilih dulu pengguna untuk percakapan pribadi.'); return; }
      var f = document.createElement('form');
      f.method = 'post';
      f.action = bar.dataset.pribadi;
      f.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">' +
        '<input type="hidden" name="user_id" value="' + rekan.value + '">';
      document.body.appendChild(f);
      f.submit();
    });
  }
  // ------------------------------------------------- ponsel: buka/tutup laci
  var laci = document.getElementById('wa-laci');
  var kembali = document.getElementById('wa-kembali');
  var sisiPonsel = document.querySelector('.wa-sisi');
  var bukaLaci = function (b) {
    if (!sisiPonsel) return;
    sisiPonsel.classList.toggle('buka', b);
    if (laci) { laci.classList.toggle('buka', b); laci.hidden = !b; }
  };
  if (kembali) kembali.addEventListener('click', function () { bukaLaci(true); });
  if (laci) laci.addEventListener('click', function () { bukaLaci(false); });
  if (sisiPonsel) {
    // Setelah memilih percakapan, laci menutup sendiri supaya isi langsung terlihat.
    sisiPonsel.addEventListener('click', function (ev) {
      if (ev.target.closest('.wa-baris-isi')) bukaLaci(false);
    });
  }
})();

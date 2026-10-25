/* Rapat video Cloudflare Realtime — dua bentuk:
 *
 *   meeting : setiap peserta menerbitkan kamera+mikrofonnya dan menerima milik
 *             orang lain (dua arah penuh).
 *   webinar : hanya pembicara menerbitkan; penonton menonton, menulis obrolan,
 *             dan mengangkat tangan untuk minta diangkat jadi pembicara.
 *
 * Ditambah kendali gaya Zoom: obrolan, angkat tangan, reaksi, kunci mikrofon,
 * angkat jadi pembicara, dan keluarkan peserta (oleh pengelola rapat).
 *
 * App Secret tidak pernah sampai ke browser: semua panggilan Cloudflare lewat
 * HTTPS ke /rapat/{id}/sfu/*.
 */
(function () {
  const kotak = document.getElementById('sfu');
  if (!kotak) return;

  const status = document.getElementById('sfu-status');

  // Kegagalan ini sebelumnya SENYAP (hanya `return`), jadi halaman tampak rusak
  // tanpa sebab. Sekarang sebabnya disebut apa adanya.
  if (!window.RTCPeerConnection || !navigator.mediaDevices) {
    if (status) {
      status.innerHTML = '<b>Peramban belum bisa rapat video di halaman ini.</b> '
        + 'WebRTC hanya aktif pada HTTPS atau localhost — bukan alamat http biasa — '
        + 'dan tidak aktif di mode penjelajahan privat sebagian peramban. '
        + 'Jika alamat sudah https, coba Chrome/Edge terbaru.';
      status.className = 'pesan galat';
    }
    const b = document.getElementById('sfu-mulai');
    if (b) b.disabled = true;
    return;
  }

  const JENIS = { kamera: 'cam', mikrofon: 'mic' };
  const EMOJI = { '👍': '👍', '👏': '👏', '❤️': '❤️', '😂': '😂', '🎉': '🎉' };
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const panggung = document.getElementById('sfu-panggung');
  // Navigasi halaman panggung (30+ peserta). Boleh tidak ada: ruang rapat lama
  // tanpa elemen ini tetap berjalan, hanya tanpa pager.
  const halBar = document.getElementById('sfu-hal');
  const halInfo = document.getElementById('sfu-hal-info');
  const obrolanLog = document.getElementById('sfu-obrolan');
  const pesertaLog = document.getElementById('sfu-daftar-peserta');
  const jumlahLabel = document.getElementById('sfu-jumlah');
  const jumlahLabel2 = document.getElementById('sfu-jumlah2');
  const bMulai = document.getElementById('sfu-mulai');
  const bMik = document.getElementById('sfu-mik');
  const bKamera = document.getElementById('sfu-kamera');
  const bPresentasi = document.getElementById('sfu-presentasi');
  const berkasPresentasi = document.getElementById('sfu-berkas');
  const bAngkat = document.getElementById('sfu-angkat');
  const bBicara = document.getElementById('sfu-berbicara');
  const bHenti = document.getElementById('sfu-henti');
  const formChat = document.getElementById('sfu-form-chat');
  const inputTeks = document.getElementById('sfu-teks');
  const saya = document.getElementById('sfu-saya');

  const R = {
    peran: kotak.dataset.peran, ikut: kotak.dataset.ikut, siarkan: kotak.dataset.siarkan,
    tonton: kotak.dataset.tonton, jawab: kotak.dataset.jawab, aksi: kotak.dataset.aksi,
    keluar: kotak.dataset.keluar,
  };
  const JENIS_RAPAT = kotak.dataset.jenis === 'webinar' ? 'webinar' : 'meeting';

  let pc = null, sesi = '', aliran = null, rapat = '';
  let namaku = kotak.dataset.nama || 'Peserta';
  let ice = [{ urls: 'stun:stun.cloudflare.com:3478' }];
  let midKeSlot = new Map(), slotKeSesi = new Map(), namaSesi = new Map();
  let sesiPeserta = new Set(), terlanggan = new Set(), gagalLanggan = new Map();
  let aliranPeserta = new Map();
  let bolehSiar = {}, daftarAngkat = [], tuan = '';
  let nomorKanal = 0, sudahTerbit = false, terangkat = false;
  let rantai = Promise.resolve(), denyut = null;

  const tulis = (t, galat) => {
    status.textContent = t;
    status.className = galat ? 'pesan galat' : 'pesan';
  };
  const antri = (fn) => (rantai = rantai.then(fn, fn));

  async function kirim(url, data) {
    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
      body: JSON.stringify(data),
    });
    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.ok) throw new Error(j.pesan || 'HTTP ' + r.status);
    return j;
  }

  function tawaranLengkap(p) {
    return new Promise((selesai) => {
      if (p.iceGatheringState === 'complete') return selesai();
      const batas = setTimeout(selesai, 2500);
      p.addEventListener('icegatheringstatechange', function cek() {
        if (p.iceGatheringState === 'complete') {
          clearTimeout(batas);
          p.removeEventListener('icegatheringstatechange', cek);
          selesai();
        }
      });
    });
  }

  /* ------------------------------- panggung ------------------------------- */

  function kotakPeserta(slot) {
    let el = document.getElementById('sfu-p' + slot);
    if (!el) {
      el = document.createElement('div');
      el.className = 'sfu-kotak';
      el.id = 'sfu-p' + slot;
      el.innerHTML = '<video class="sfu-video" playsinline autoplay></video>'
        + '<span class="sfu-nama"></span><span class="sfu-lencana"></span>';
      panggung.appendChild(el);
      const v = el.querySelector('video');
      // Klik kotak = perbesar (gaya Zoom "pin").
      v.addEventListener('click', () => el.classList.toggle('besar'));
      // Kotak baru bisa jatuh di halaman berikutnya — tanpa ini ia tetap
      // tersembunyi sampai pengguna menekan "›" tanpa tahu ada yang datang.
      aturPanggung();
    }
    return el;
  }

  /* Halaman panggung. 30 peserta dalam satu gulungan terukur 2.911 px (desktop)
     dan 6.215 px (ponsel) — nama peserta jadi tak terbaca dan obrolan terdorong
     jauh ke bawah. Isi per halaman = kolom nyata × 3 baris, jadi jumlahnya ikut
     lebar layar tanpa satu pun angka yang dipatok. */
  let hal = 0;

  function aturPanggung() {
    const kotak = [...panggung.querySelectorAll('.sfu-kotak')];
    const kolom = getComputedStyle(panggung).gridTemplateColumns.split(' ').filter(Boolean).length || 1;
    const per = Math.max(1, kolom * 3);
    const total = Math.max(1, Math.ceil(kotak.length / per));
    if (hal > total - 1) hal = total - 1;
    if (hal < 0) hal = 0;
    kotak.forEach((k, i) => { k.hidden = i < hal * per || i >= (hal + 1) * per; });
    if (!halBar) return;
    halBar.hidden = kotak.length <= per;
    if (halInfo) {
      halInfo.textContent = (hal * per + 1) + '–' + Math.min(kotak.length, (hal + 1) * per)
        + ' dari ' + kotak.length + ' · halaman ' + (hal + 1) + '/' + total;
    }
    halBar.querySelectorAll('[data-hal]').forEach((b) => {
      b.disabled = (+b.dataset.hal < 0 && hal === 0) || (+b.dataset.hal > 0 && hal >= total - 1);
    });
  }

  function pasangKotak(id, el, aktif, bicara) {
    if (!el) return;
    const nama = id === sesi ? 'Anda' : namaSesi.get(id) || 'Peserta';
    const label = el.querySelector('.sfu-nama');
    if (label) label.textContent = nama;
    const lencana = el.querySelector('.sfu-lencana');
    if (lencana) {
      lencana.textContent = bicara ? '🔊' : '';
    }
    el.classList.toggle('mati', !aktif);
  }

  function terimaTrek(ev) {
    const mid = ev.transceiver?.mid;
    const info = mid ? midKeSlot.get(String(mid)) : null;
    if (!info) return;

    let ms = aliranPeserta.get(info.slot);
    if (!ms) {
      ms = new MediaStream();
      aliranPeserta.set(info.slot, ms);
    }
    if (!ms.getTracks().some((t) => t.id === ev.track.id)) ms.addTrack(ev.track);

    const el = kotakPeserta(info.slot);
    const v = el.querySelector('video');
    if (v && v.srcObject !== ms) v.srcObject = ms;
    // Peramban boleh menahan pemutaran otomatis video BERSUARA. Kalau tidak
    // dipaksa, gambar peserta lain tidak pernah muncul walau jalurnya sudah
    // mengalir — dan pemeriksaan "video tampil" gagal tanpa sebab yang jelas.
    if (v && v.paused) v.play().catch(() => { /* perlu sentuhan pengguna; biarkan */ });
    // "Kamera mati" ditentukan dari ISI aliran, bukan dari jenis jalur yang baru
    // tiba. Dulu `mati` dipasang menurut jenis trek: jalur MIKROFON datang
    // sesudah kamera, jadi setiap peserta langsung berubah jadi "kamera mati" —
    // CSS menyembunyikan gambarnya padahal byte video mengalir (dan suara tetap
    // terdengar). Itu persis keluhan "video hanya tampil di perangkat sendiri".
    pasangKotak(info.sesi, el, ms.getVideoTracks().length > 0, ms.getAudioTracks().length > 0);
  }

  async function bukaKoneksi() {
    pc = new RTCPeerConnection({ iceServers: ice });
    window.__sfu_pc = pc; // untuk uji otomatis & pemeriksaan statistik
    pc.addEventListener('track', terimaTrek);
    pc.addEventListener('connectionstatechange', () => {
      if (pc && pc.connectionState === 'failed') tulis('Koneksi media terputus — coba Muat Ulang.', true);
      if (pc && pc.connectionState === 'connected' && !siarGagal) tulis(jumlahPesan());
    });
  }

  const jumlahPesan = () => 'Tersambung — ' + (terlanggan.size + 1) + ' peserta.';
  // Terbit gagal = keadaan yang HARUS bertahan sampai benar-benar berhasil.
  // Tanpa penanda ini, pesan galat langsung ditimpa "Tersambung — 1 peserta" saat
  // PeerConnection tersambung (terima-saja), sehingga pengguna mengira kameranya
  // menyiarkan padahal tidak ada satu byte pun yang keluar. Itu juga yang membuat
  // uji dua-arah kadang melihat "tersambung" tetapi 0 byte.
  let siarGagal = false;
  async function deteksi() {
    if (!pc) return;
    // `gagalLanggan` = jeda waktu sampai kapan peserta itu boleh dicoba lagi,
    // BUKAN penghitung yang berhenti setelah beberapa kali. Dengan penghitung,
    // peserta yang kebetulan terbit terlambat (jaringan lambat, penyiapan kamera
    // lama) langsung dilupakan SELAMANYA — dan orang di sisi lain tidak pernah
    // melihatnya ("A lihat B: []", byte masuk nol). Percobaan berikutnya datang
    // sendiri dari denyut `pantau()` tiap 5 detik, jadi tidak perlu ada batas.
    const sekarang = Date.now();
    const baru = [...sesiPeserta].filter(
      (s) => s !== sesi && !terlanggan.has(s) && (gagalLanggan.get(s) || 0) < sekarang
    );
    if (!baru.length) return;

    const daftar = [...new Set([rapat, sesi, ...sesiPeserta])].filter(Boolean).sort();
    const minta = [];
    for (const s of baru) {
      const slot = daftar.indexOf(s);
      if (slot < 0) continue;
      slotKeSesi.set(slot, s);
      for (const [jenis, trek] of Object.entries(JENIS)) {
        minta.push({ sesi: s, trek, slot, jenis });
      }
    }
    if (!minta.length) return;

    tulis('Menyambungkan peserta…');

    // PENTING: permintaan jalur TIDAK boleh mematikan seluruh rapat.
    //
    // Dulu galat di sini dilempar keluar `deteksi()`, naik ke `mulai()`, dan
    // seluruh join dibatalkan ("Gagal masuk rapat") — padahal hanya SATU peserta
    // yang gagal diambil. Penyebab paling sering: peserta yang perambannya sudah
    // tutup tetapi sesinya masih tercatat, sehingga Cloudflare menolak dengan
    // not_found_track_error dan server menjawab 502. Akibatnya rapat tidak bisa
    // dipakai siapa pun. Sekarang kegagalan dihitung per peserta (`gagalLanggan`)
    // dan join tetap berjalan dengan peserta yang benar-benar ada.
    let j;
    try {
      j = await kirim(R.tonton, { sesi, minta });
    } catch (e) {
      // Tunda 5 detik sebelum mencoba peserta ini lagi (denyut berikutnya).
      for (const s of baru) gagalLanggan.set(s, Date.now() + 5000);
      tulis('Sebagian peserta belum bisa disambungkan — mencoba lagi…');
      return;
    }
    for (const [slot, peta] of Object.entries(j.peta || {})) {
      for (const [jenis, mid] of Object.entries(peta)) {
        if (mid) midKeSlot.set(String(mid), { slot: +slot, sesi: slotKeSesi.get(+slot), jenis });
      }
    }
    // Tak ada satu pun jalur baru: tidak ada deskripsi untuk dinegosiasikan.
    // Jangan menimpa PeerConnection dengan deskripsi kosong — cukup tandai gagal.
    if (!j.description) {
      // Tunda 5 detik sebelum mencoba peserta ini lagi (denyut berikutnya).
      for (const s of baru) gagalLanggan.set(s, Date.now() + 5000);
      tulis(jumlahPesan());
      return;
    }
    try {
      // Batalkan tawaran yang belum terselesaikan lebih dulu. Tanpa ini,
      // `setRemoteDescription` berikutnya ditolak karena PeerConnection masih
      // berada di keadaan "punya tawaran jauh" — dan peserta itu tidak akan
      // pernah tersambung, berapa kali pun dicoba.
      if (pc.signalingState !== 'stable') {
        await pc.setLocalDescription({ type: 'rollback' });
      }
      await pc.setRemoteDescription(j.description);
      await pc.setLocalDescription(await pc.createAnswer());
      await tawaranLengkap(pc);
      await kirim(R.jawab, { sesi, sdp: pc.localDescription.sdp });
    } catch (e) {
      // Tunda 5 detik sebelum mencoba peserta ini lagi (denyut berikutnya).
      for (const s of baru) gagalLanggan.set(s, Date.now() + 5000);
      tulis('Negosiasi media tertunda — mencoba lagi…');
      return;
    }

    for (const s of baru) {
      if ([...midKeSlot.values()].some((v) => v.ses === s)) terlanggan.add(s);
      // Jeda WAKTU, bukan penghitung: peserta yang terbitnya terlambat harus
      // dicoba lagi, bukan dilupakan selamanya setelah beberapa kali gagal.
      else gagalLanggan.set(s, Date.now() + 5000);
    }
    tulis(jumlahPesan());
  }

  /* ------------------------------ daftar & panel --------------------------- */

  function serapDaftar(j) {
    if (j.rapat) rapat = j.rapat;
    if (j.tuan !== undefined) tuan = j.tuan;
    if (j.ice && j.ice.length) ice = j.ice;
    for (const [s, nama] of Object.entries(j.peta || {})) namaSesi.set(s, nama || 'Peserta');
    // JANGAN menyaring `rapat` di sini. `rapat` = sesi PESERTA PERTAMA, bukan
    // sesi ruang tersendiri — menyaringnya membuat orang kedua tidak pernah bisa
    // melihat orang pertama (terukur: byte masuk sisi B = 0 sementara A melihat B).
    // Server sudah mengirim `peserta` = orang LAIN saja (`lain($daftar, $sesi)`),
    // jadi tidak ada lagi yang perlu dibuang di klien.
    sesiPeserta = new Set([...(j.peserta || [])].filter((s) => s && s !== sesi));
    if (j.boleh && typeof j.boleh === 'object') bolehSiar = j.boleh;
    if (Array.isArray(j.angkat) !== undefined && Array.isArray(j.angkat)) daftarAngkat = j.angkat;
    gambarPeserta();
  }

  function gambarPeserta() {
    const aku = bolehSiar[sesi] !== false; // meeting: tidak ada di peta → anggap boleh
    const n = sesiPeserta.size + 1;
    if (jumlahLabel) jumlahLabel.textContent = '· ' + n + ' peserta';
    if (jumlahLabel2) jumlahLabel2.textContent = String(n);

    if (!pesertaLog) return;
    const orang = [[sesi, namaku + ' (Anda)'], ...[...sesiPeserta].map((s) => [s, namaSesi.get(s) || 'Peserta'])];
    // Pengelola: satu tombol untuk membisukan SEMUA peserta sekaligus. Tanpa ini
    // ia harus menekan "Matikan mikrofon" satu per satu di rapat yang ramai.
    const semua = sesi === tuan
      ? '<div class="rapat-orang"><button type="button" class="mini peringatan" data-bisukan-semua>Bisukan semua peserta</button></div>'
      : '';
    pesertaLog.innerHTML = semua + orang.map(([s, nama]) => {
      const tanda = [];
      if (s === tuan) tanda.push('👑 pengelola');
      if (s === sesi ? sudahTerbit || (JENIS_RAPAT === 'webinar' && terangkat) : bolehSiar[s]) tanda.push('🎤 pembicara');
      if (daftarAngkat.includes(s) && s !== sesi) tanda.push('✋ minta bicara');
      if (s === sesi && daftarAngkat.includes(s)) tanda.push('✋ tangan terangkat');

      // Kendali pengelola hanya untuk pengelola.
      let kendali = '';
      if (sesi === tuan && s !== sesi) {
        kendali = '<span class="rapat-kendali-peserta">'
          + '<button type="button" class="mini samar" data-kunci="' + s + '">Matikan mikrofon</button>'
          + '<button type="button" class="mini samar" data-bicara="' + s + '">Beri bicara</button>'
          + (bolehSiar[s] ? '' : '<button type="button" class="mini samar" data-pembicara="' + s + '">Jadikan pembicara</button>')
          + '<button type="button" class="mini peringatan" data-tendang="' + s + '">Keluarkan</button></span>';
      }

      return '<div class="rapat-orang"><span class="rapat-nama">' + esc(nama) + '</span>'
        + '<span class="pudar">' + tanda.join(' · ') + '</span>' + kendali + '</div>';
    }).join('');

    pesertaLog.querySelectorAll('[data-kunci]').forEach((b) => b.addEventListener('click', () => aksi('kunci', b.dataset.kunci)));
    pesertaLog.querySelectorAll('[data-bicara]').forEach((b) => b.addEventListener('click', () => aksi('bicara', b.dataset.bicara)));
    pesertaLog.querySelectorAll('[data-bisukan-semua]').forEach((b) => b.addEventListener('click', () => aksi('kunci', 'semua')));
    pesertaLog.querySelectorAll('[data-pembicara]').forEach((b) => b.addEventListener('click', () => aksi('pembicara', b.dataset.pembicara)));
    pesertaLog.querySelectorAll('[data-tendang]').forEach((b) => b.addEventListener('click', () => {
      if (confirm('Keluarkan peserta ini dari rapat?')) aksi('tendang', b.dataset.tendang);
    }));

    // Tombol "berbicara": meeting selalu bisa; webinar penonton minta diangkat.
    if (bBicara) {
      const penontonWebinar = JENIS_RAPAT === 'webinar' && !aku;
      bBicara.hidden = !penontonWebinar || !sesi;
      bBicara.textContent = daftarAngkat.includes(sesi) ? 'Batalkan permintaan bicara' : 'Minta berbicara';
    }
  }

  const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function tulisKanal(pesan) {
    if (!obrolanLog) return;
    const el = document.createElement('div');
    el.className = 'rapat-pesan rapat-' + pesan.jenis;

    if (pesan.jenis === 'obrolan') {
      el.innerHTML = '<b>' + esc(pesan.nama) + '</b> <span class="pudar">' + esc(pesan.waktu) + '</span><br>' + esc(pesan.teks);
    } else if (pesan.jenis === 'reaksi') {
      terbang(pesan.emoji, pesan.nama);
      return;
    } else {
      el.innerHTML = '<i>' + esc(pesan.teks) + '</i>';
    }
    obrolanLog.appendChild(el);
    obrolanLog.scrollTop = obrolanLog.scrollHeight;
  }

  function terbang(emoji, dari) {
    const el = document.createElement('div');
    el.className = 'rapat-terbang';
    el.textContent = emoji;
    el.title = dari || '';
    (panggung || document.body).appendChild(el);
    setTimeout(() => el.remove(), 2600);
  }

  function prosesKanal(daftarPesan) {
    for (const p of daftarPesan || []) {
      if (p.n > nomorKanal) nomorKanal = p.n;
      tulisKanal(p);
    }
  }

  async function prosesPerintah(perintah) {
    for (const p of perintah || []) {
      if (p.aksi === 'kunci') {
        if (aliran) aliran.getAudioTracks().forEach((t) => { t.enabled = false; });
        if (bMik) bMik.textContent = 'Nyalakan mikrofon';
        tulis('Pengelola meminta mikrofon Anda dimatikan.', true);
      } else if (p.aksi === 'tendang') {
        await hentikan();
        alert('Anda dikeluarkan dari rapat oleh pengelola.');
        return;
      } else if (p.aksi === 'bicara') {
        // Pengelola menunjuk peserta ini untuk berbicara: nyalakan lagi mikrofonnya.
        // Kebalikan dari `kunci` — tanpa jalur ini tombol "Beri bicara" hanya
        // mengubah tampilan di layar pengelola.
        if (aliran) aliran.getAudioTracks().forEach((t) => { t.enabled = true; });
        if (bMik) bMik.textContent = 'Matikan mikrofon';
        tulis('Pengelola memberi Anda izin bicara — mikrofon dinyalakan.');
      } else if (p.aksi === 'pembicara') {
        await terbitkan();
      } else if (p.aksi === 'pesan') {
        tulis('Pengelola mengirim pesan — lihat obrolan.');
      }
    }
  }

  /* --------------------------------- peran -------------------------------- */

  /* --------------------------------- lobi --------------------------------- */

  // Lobi = pilih perangkat + uji mikrofon. TIDAK ada pratinjau video di sini:
  // panggung `#sfu-saya-kotak` sudah menampilkan gambar Anda, dan pratinjau kedua
  // membuat DUA frame kamera tampil bersamaan (terbukti: dua elemen <video>
  // membawa `srcObject` yang sama persis). Perangkat disimpan agar bisa dipakai
  // saat benar-benar masuk — kalau pratinjau dan rapat memakai perangkat berbeda,
  // yang muncul bisa bukan yang tadi dilihat.
  let pilihanKamera = '', pilihanMik = '';

  const penggunaMedia = () =>
    !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

  function isiPilihan(sel, daftar, terpilih, labelKosong) {
    if (!sel) return;
    sel.innerHTML = '';
    if (!daftar.length) {
      const o = document.createElement('option');
      o.value = '';
      o.textContent = labelKosong;
      sel.appendChild(o);
      return;
    }
    daftar.forEach((d) => {
      const o = document.createElement('option');
      o.value = d.deviceId;
      o.textContent = d.label || 'Perangkat tanpa nama';
      sel.appendChild(o);
    });
    sel.value = terpilih || (daftar[0] ? daftar[0].deviceId : '');
  }

  async function siapkanLobi() {
    const kotakLobi = document.getElementById('sfu-lobi');
    if (!kotakLobi || !penggunaMedia()) {
      modeLangsung();
      return;
    }

    // Lobi BELUM ditampilkan di sini. Ia baru dibuka setelah tombol "Masuk Rapat"
    // punya penangan: kalau tidak, penyiapan perangkat (minta izin kamera) berlalu
    // beberapa detik dengan tombol yang sudah terlihat tapi BELUM BEREAKSI —
    // menekannya seolah tidak terjadi apa-apa.
    const statusLobi = document.getElementById('sfu-lobi-status');
    const selKamera = document.getElementById('sfu-lobi-kamera');
    const selMik = document.getElementById('sfu-lobi-mik');

    async function bukaAliran() {
      // JANGAN sentuh aliran bila pengguna SUDAH masuk ruang.
      //
      // Penyiapan lobi berjalan asinkron (minta izin kamera). Kalau pengguna menekan
      // "Masuk Rapat" sebelum selesai — hal biasa bagi yang tidak sabar, dan pasti
      // terjadi pada uji otomatis — `bukaAliran()` yang baru selesai akan MEMATIKAN
      // trek yang sedang diterbitkan ke Cloudflare, sehingga terbitkan gagal dan
      // tombol Keluar tidak pernah muncul. Sekali sudah masuk, urusan perangkat
      // pindah ke ruang (bisukanMikrofon/bisukanKamera), bukan lobi.
      if (sesi) return !!aliran;
      if (aliran) aliran.getTracks().forEach((t) => t.stop());
      // Tanpa pratinjau, tidak ada tempat menaruh gambar — jadi cukup MENDAPATKAN
      // aliran agar daftar perangkat punya label, dan `#sfu-saya` dipasangi
      // aliran yang sama supaya begitu pengguna masuk gambarnya langsung muncul.
      aliran = await navigator.mediaDevices.getUserMedia({
        video: pilihanKamera ? { deviceId: { exact: pilihanKamera } } : true,
        audio: pilihanMik ? { deviceId: { exact: pilihanMik } } : true,
      }).catch(async () => {
        // Kamera mungkin tidak ada/ditolak, tetapi mikrofon masih bisa dipakai.
        return navigator.mediaDevices.getUserMedia({
          video: false, audio: pilihanMik ? { deviceId: { exact: pilihanMik } } : true,
        }).catch(() => null);
      });
      if (saya && aliran) saya.srcObject = aliran;
      if (aliran) {
        const trekV = aliran.getVideoTracks();
        if (trekV[0]) pilihanKamera = trekV[0].getSettings().deviceId || pilihanKamera;
        const trekA = aliran.getAudioTracks();
        if (trekA[0]) pilihanMik = trekA[0].getSettings().deviceId || pilihanMik;
      }
      return !!aliran;
    }

    try {
      const ada = await bukaAliran();
      const perangkat = await navigator.mediaDevices.enumerateDevices();
      isiPilihan(selKamera, perangkat.filter((d) => d.kind === 'videoinput'), pilihanKamera,
        'Tidak ada kamera');
      isiPilihan(selMik, perangkat.filter((d) => d.kind === 'audioinput'), pilihanMik,
        'Tidak ada mikrofon');
      if (statusLobi) {
        statusLobi.textContent = ada
          ? 'Perangkat siap. Periksa pilihan Anda, lalu masuk.'
          : 'Kamera/mikrofon tidak tersedia — Anda tetap bisa masuk sebagai penonton.';
      }
    } catch (e) {
      if (statusLobi) statusLobi.textContent = 'Tidak bisa membuka perangkat: ' + e.message;
      // Jangan biarkan pengguna terkunci di lobi yang tidak bisa menyiapkan
      // perangkat: sediakan tombol masuk lama sebagai jalan keluar.
      bMulai.hidden = false;
    }

    selKamera?.addEventListener('change', () => { pilihanKamera = selKamera.value; bukaAliran(); });
    selMik?.addEventListener('change', () => { pilihanMik = selMik.value; bukaAliran(); });

    // Penangan tombol dipasang SEBELUM perangkat disiapkan, lalu lobi dibuka.
    document.getElementById('sfu-lobi-masuk')?.addEventListener('click', () => antri(masukRapat));
    // Tombol lama TETAP berfungsi sebagai jalan masuk cadangan — kalau penyiapan
    // perangkat menggantung (peramban menahan izin), pengguna masih bisa masuk.
    bMulai.addEventListener('click', () => antri(() => mulai(false)));
    bMulai.hidden = true;
    kotakLobi.hidden = false;

    // Uji mikrofon: bilah yang bergerak hanya saat ada suara — cara paling cepat
    // memastikan mikrofon yang dipilih benar-benar menangkap suara.
    document.getElementById('sfu-lobi-uji')?.addEventListener('click', async () => {
      if (!aliran || !aliran.getAudioTracks().length) {
        if (statusLobi) statusLobi.textContent = 'Tidak ada mikrofon untuk diuji.';
        return;
      }
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) { if (statusLobi) statusLobi.textContent = 'Peramban tidak mendukung uji mikrofon.'; return; }
      const ctx = new AC();
      const src = ctx.createMediaStreamSource(aliran);
      const an = ctx.createAnalyser();
      an.fftSize = 512;
      src.connect(an);
      const buf = new Uint8Array(an.frequencyBinCount);
      let puncak = 0;
      const mulaiUji = Date.now();
      if (statusLobi) statusLobi.textContent = 'Bicara sekarang — mengukur 3 detik…';
      const timer = setInterval(() => {
        an.getByteFrequencyData(buf);
        puncak = Math.max(puncak, ...buf);
        if (Date.now() - mulaiUji > 3000) {
          clearInterval(timer);
          try { src.disconnect(); ctx.close(); } catch (e) { /* sudah tutup */ }
          if (statusLobi) {
            statusLobi.textContent = puncak > 8
              ? 'Mikrofon terdengar (puncak ' + puncak + '). Siap masuk.'
              : 'Tidak ada suara terdeteksi — periksa mikrofon yang dipilih atau izin peramban.';
          }
        }
      }, 120);
    });

  }

  /** Tanpa dukungan kamera (mis. http:// LAN): langsung seperti dulu. */
  function modeLangsung() {
    const kotakLobi = document.getElementById('sfu-lobi');
    if (kotakLobi) kotakLobi.hidden = true;
    bMulai.hidden = false;
  }

  async function masukRapat() {
    const kotakLobi = document.getElementById('sfu-lobi');
    if (kotakLobi) kotakLobi.hidden = true;
    // Aliran dari lobi DIPAKAI ULANG (tidak diminta ulang): perangkatnya sudah
    // dipilih pengguna, meminta ulang bisa mendapat perangkat bawaan yang lain.
    await mulai(true);
  }

  async function mulai(dariLobi) {
    bMulai.disabled = true;
    tulis('Meminta izin kamera & mikrofon…');
    try {
      aliran = dariLobi && aliran
        ? aliran
        : await navigator.mediaDevices.getUserMedia({ video: true, audio: true }).catch(async () => {
            tulis('Kamera/mikrofon ditolak — ikut sebagai penonton.');
            return null;
          });
      if (saya && aliran) saya.srcObject = aliran;

      const j = await kirim(R.peran, {});
      sesi = j.sesi;
      serapDaftar(j);
      gambarPeserta();

      await bukaKoneksi();

      if (JENIS_RAPAT === 'webinar' && j.boleh && j.boleh[sesi] === false) {
        await masukPenonton();
      } else if (!(await terbitkan())) {
        // Gagal menerbitkan kamera/mikrofon: SEBUTKAN apa adanya. Dulu pesannya
        // tetap "Meminta izin kamera & mikrofon…"/berhasil, padahal tidak ada
        // gambar yang terkirim — pengguna menunggu gambar yang tak pernah datang.
        tulis('Kamera/mikrofon tidak bisa diterbitkan — Anda masuk tanpa media.', true);
      }

      await kirim(R.ikut, { sesi, kode: namaku });
      // Peserta yang gagal disambungkan TIDAK membatalkan keikutsertaan: kita sudah
      // terdaftar & sudah menerbitkan media. Cukup dilaporkan di status.
      try {
        await deteksi();
      } catch (e) {
        tulis('Terhubung, tetapi sebagian peserta belum bisa disambungkan.', true);
      }

      bHenti.hidden = false;
      bMulai.hidden = true;
      bMulai.disabled = true;
      if (bMik) bMik.hidden = !aliran;
      if (bKamera) bKamera.hidden = !aliran;
      if (bAngkat) bAngkat.hidden = false;
      pantau();
      tulis(jumlahPesan());
      gambarPeserta();
    } catch (e) {
      tulis('Gagal masuk rapat: ' + e.message, true);
      bersihkan();
      bMulai.disabled = false;
    }
  }

  /**
   * Terbitkan kamera+mikrofon di koneksi yang sudah ada.
   * @return bool true bila benar-benar terkirim ke Cloudflare.
   */
  async function terbitkan() {
    // Sudah terbit / belum ada koneksi. "Sudah terbit" dihitung BERHASIL supaya
    // pemanggil tidak melaporkan kegagalan yang tidak terjadi.
    if (sudahTerbit) return true;
    if (!pc) return false;
    if (!aliran) {
      aliran = await navigator.mediaDevices.getUserMedia({ video: true, audio: true }).catch(() => null);
      if (saya && aliran) saya.srcObject = aliran;
    }
    if (!aliran) {
      tulis('Tidak ada kamera/mikrofon untuk diterbitkan.', true);
      return false;
    }

    try {
      const trek = [];
      for (const t of aliran.getTracks()) {
        const jenis = t.kind === 'video' ? 'kamera' : 'mikrofon';
        const tx = pc.addTransceiver(t, { direction: 'sendonly' });
        // Disimpan supaya jalur KAMERA bisa ditukar lintas-jalur dengan berkas
        // presentasi tanpa negosiasi ulang. Yang dipakai adalah SENDER-nya:
        // `replaceTrack` milik RTCRtpSender, bukan RTCRtpTransceiver
        // (memanggilnya pada transceiver = TypeError, presentasi gagal senyap).
        if (jenis === 'kamera') { txKamera = tx.sender; trekKamera = t; }
        trek.push({ tx, jenis });
      }
      await pc.setLocalDescription(await pc.createOffer());
      await tawaranLengkap(pc);

      tulis('Menyambung ke Cloudflare…');
      const r = await kirim(R.siarkan, {
        sesi,
        trek: trek.map((t) => ({ mid: t.tx.mid, jenis: t.jenis })),
        tipe: 'offer',
        sdp: pc.localDescription.sdp,
      });
      await pc.setRemoteDescription(r.description);
    } catch (e) {
      // Kegagalan menerbitkan TIDAK melempar ke pemanggil: pengguna tetap masuk
      // (mungkin sebagai penonton), dan statusnya menyebutkan apa yang terjadi.
      siarGagal = true;
      tulis('Gagal menerbitkan kamera/mikrofon: ' + e.message, true);
      return false;
    }

    siarGagal = false;
    sudahTerbit = true;
    bolehSiar[sesi] = true;
    if (aliran) {
      bMik.hidden = false;
      bKamera.hidden = false;
      if (bPresentasi && aliran.getVideoTracks().length) bPresentasi.hidden = false;
    }
    tulis(jumlahPesan());
    gambarPeserta();
    return true;
  }

  /** Webinar: penonton tidak menerbitkan apa pun, hanya menerima jalur pembicara. */
  async function masukPenonton() {
    tulis('Menonton webinar…');
    await terimaSaja();
  }

  /** Terima jalur pembicara tanpa menerbitkan (dipakai penonton webinar). */
  async function terimaSaja() {
    if (!pc || terlanggan.size > 0) return;
    const sumber = rapat || [...sesiPeserta][0];
    if (!sumber) return;
    const minta = Object.entries(JENIS).map(([jenis, trek], i) => ({ sesi: sumber, trek, slot: 0, jenis }));
    slotKeSesi.set(0, sumber);
    try {
      const j = await kirim(R.tonton, { sesi, minta });
      for (const [slot, peta] of Object.entries(j.peta || {})) {
        for (const [jenis, mid] of Object.entries(peta)) {
          if (mid) midKeSlot.set(String(mid), { slot: +slot, sesi: slotKeSesi.get(+slot), jenis });
        }
      }
      await pc.setRemoteDescription(j.description);
      await pc.setLocalDescription(await pc.createAnswer());
      await tawaranLengkap(pc);
      await kirim(R.jawab, { sesi, sdp: pc.localDescription.sdp });
      terlanggan.add(sumber);
    } catch (e) {
      // Pembicara belum menerbitkan — denyut berikutnya akan mencoba lagi.
    }
  }

  /* --------------------------------- fitur -------------------------------- */

  async function aksi(aksiNama, sasaran, tambahan) {
    const badan = { sesi, aksi: aksiNama, ...(tambahan || {}) };
    if (sasaran) badan.sasaran = sasaran;
    try {
      const j = await kirim(R.aksi, badan);
      if (j.pesan) tulis(j.pesan, true);
      return j;
    } catch (e) {
      tulis(e.message, true);
      return null;
    }
  }

  function kirimChat(teks) {
    if (!teks.trim()) return;
    tulisKanal({ jenis: 'obrolan', nama: namaku, teks, waktu: jam(), n: ++nomorKanal });
    aksi('chat', '', { teks });
  }

  function angkatTangan() {
    const sudah = daftarAngkat.includes(sesi);
    daftarAngkat = sudah ? daftarAngkat.filter((s) => s !== sesi) : [...daftarAngkat, sesi];
    if (bAngkat) bAngkat.textContent = sudah ? (JENIS_RAPAT === 'webinar' ? 'Minta berbicara' : 'Angkat tangan')
      : (JENIS_RAPAT === 'webinar' ? 'Batalkan permintaan bicara' : 'Turunkan tangan');
    tulis(sudah ? 'Permintaan bicara dibatalkan.' : (JENIS_RAPAT === 'webinar'
      ? 'Permintaan bicara dikirim ke pengelola.' : 'Tangan terangkat — pengelola melihat tandanya.'));
    aksi('angkat');
    gambarPeserta();
  }

  const jam = () => new Date().toTimeString().slice(0, 5);

  function bisukanMikrofon() {
    if (!aliran || !aliran.getAudioTracks().length) return;
    const aktif = aliran.getAudioTracks().some((t) => t.enabled);
    aliran.getAudioTracks().forEach((t) => { t.enabled = !aktif; });
    bMik.textContent = aktif ? 'Nyalakan mikrofon' : 'Matikan mikrofon';
    tulis(aktif ? 'Mikrofon mati.' : 'Mikrofon hidup.');
  }

  function bisukanKamera() {
    if (!aliran || !aliran.getVideoTracks().length) return;
    const aktif = aliran.getVideoTracks().some((t) => t.enabled);
    aliran.getVideoTracks().forEach((t) => { t.enabled = !aktif; });
    if (bKamera) bKamera.textContent = aktif ? 'Nyalakan kamera' : 'Matikan kamera';
    document.getElementById('sfu-saya-kotak')?.classList.toggle('mati', aktif);
    tulis(aktif ? 'Kamera mati.' : 'Kamera hidup.');
  }

  /* ------------------------------ presentasi ------------------------------ */

  // Presentasi = jalur KAMERA ditukar dengan berkas (gambar/video). Cara ini tidak
  // butuh layar-bagi, tidak butuh perubahan server, dan langsung terlihat semua
  // peserta karena jalurnya memang jalur yang sudah mereka tonton.
  // ponytail: hanya gambar & video, satu berkas. Tambah PDF/PPTX kalau perlu
  // (konversi halaman ke kanvas = pekerjaan lain; sekarang belum ada pemakainya).
  let txKamera = null, trekKamera = null, presentasiAktif = false;  // txKamera = RTCRtpSender
  let aliranPresentasi = null, putarPresentasi = null;

  function hentikanPresentasi() {
    if (putarPresentasi) { clearInterval(putarPresentasi); putarPresentasi = null; }
    if (aliranPresentasi) {
      aliranPresentasi.getTracks().forEach((t) => t.stop());
      aliranPresentasi = null;
    }
    if (txKamera && trekKamera) txKamera.replaceTrack(trekKamera).catch(() => {});
    presentasiAktif = false;
    if (bPresentasi) bPresentasi.textContent = 'Bagikan presentasi';
    document.getElementById('sfu-saya-kotak')?.classList.remove('presentasi');
    kirimChat('📊 Berhenti membagikan presentasi — kembali ke kamera');
    tulis('Presentasi dihentikan — kamera kembali.');
  }

  async function bagikanPresentasi(berkas) {
    if (!txKamera) {
      tulis('Masuk rapat dulu sebelum membagikan presentasi.', true);
      return;
    }
    if (presentasiAktif) hentikanPresentasi();

    try {
      if (berkas.type.startsWith('video/')) {
        const v = document.createElement('video');
        v.src = URL.createObjectURL(berkas);
        v.loop = true;
        v.muted = true;
        v.playsInline = true;
        await v.play();
        aliranPresentasi = v.captureStream();
      } else if (berkas.type.startsWith('image/')) {
        const img = new Image();
        img.src = URL.createObjectURL(berkas);
        await img.decode();
        const c = document.createElement('canvas');
        c.width = 1280;
        c.height = 720;
        const ctx = c.getContext('2d');
        const gambar = () => {
          // Latar gelap + gambar dimuat UTUH (tidak dipotong) agar slide tidak
          // kehilangan tepinya di kotak 16:9.
          const r = Math.min(c.width / img.width, c.height / img.height);
          const w = img.width * r;
          const h = img.height * r;
          ctx.fillStyle = '#0a1710';
          ctx.fillRect(0, 0, c.width, c.height);
          ctx.drawImage(img, (c.width - w) / 2, (c.height - h) / 2, w, h);
        };
        gambar();
        // Digambar berkala: sebagian peramban berhenti mengirim frame kalau
        // kanvas tidak pernah berubah.
        putarPresentasi = setInterval(gambar, 500);
        aliranPresentasi = c.captureStream(2);
      } else {
        tulis('Berkas presentasi harus gambar atau video.', true);
        return;
      }

      const trek = aliranPresentasi.getVideoTracks()[0];
      if (!trek) {
        tulis('Berkas itu tidak menghasilkan gambar.', true);
        return;
      }
      await txKamera.replaceTrack(trek);
      presentasiAktif = true;
      if (bPresentasi) bPresentasi.textContent = 'Hentikan presentasi';
      document.getElementById('sfu-saya-kotak')?.classList.add('presentasi');
      // Diumumkan ke kanal: peserta lain perlu tahu kenapa gambar berubah, dan
      // hanya kanal obrolan yang bisa menyampaikan itu ke semua orang.
      kirimChat('📊 Mulai membagikan presentasi (' + berkas.name + ')');
      tulis('Presentasi dibagikan: ' + berkas.name);
    } catch (e) {
      tulis('Gagal membagikan presentasi: ' + e.message, true);
    }
  }

  function tab(panel) {
    document.querySelectorAll('.rapat-tab .tab').forEach((t) => t.classList.toggle('aktif', t.dataset.panel === panel));
    const a = document.getElementById('panel-obrolan');
    const b = document.getElementById('panel-peserta');
    if (a) a.hidden = panel !== 'obrolan';
    if (b) b.hidden = panel !== 'peserta';
  }

  /* -------------------------------- siklus -------------------------------- */

  function bersihkan() {
    if (presentasiAktif) hentikanPresentasi();
    if (denyut) { clearInterval(denyut); denyut = null; }
    if (aliran) { aliran.getTracks().forEach((t) => t.stop()); aliran = null; }
    if (pc) { pc.close(); pc = null; }
    if (saya) saya.srcObject = null;
    panggung?.querySelectorAll('.sfu-kotak:not(#sfu-saya-kotak)').forEach((e) => e.remove());
    midKeSlot.clear(); slotKeSesi.clear(); aliranPeserta.clear();
    terlanggan.clear(); sesiPeserta.clear(); gagalLanggan.clear();
    sudahTerbit = false; terangkat = false;
    sesi = ''; rapat = '';
  }

  async function hentikan() {
    const s = sesi;
    bersihkan();
    if (s) { try { await kirim(R.keluar, { sesi: s }); } catch (e) { /* sudah tidak ada */ } }
    bMulai.disabled = false;
    bMulai.hidden = false;
    [bMik, bKamera, bAngkat, bBicara, bHenti].forEach((b) => { if (b) b.hidden = true; });
    tulis('Anda sudah keluar dari rapat.');
  }

  function pantau() {
    denyut = setInterval(() => antri(async () => {
      if (!sesi || !pc) return;
      try {
        const j = await kirim(R.ikut, { sesi, kode: namaku, sejak: nomorKanal });
        serapDaftar(j);
        prosesKanal(j.kanal);
        if (j.perintah && j.perintah.length) await prosesPerintah(j.perintah);

        if (JENIS_RAPAT === 'webinar' && !sudahTerbit) {
          if (bolehSiar[sesi]) await terbitkan();
          else await terimaSaja();
          await deteksi();
        } else if (!sudahTerbit) {
          // Meeting yang terbitnya gagal (gangguan jaringan sesaat, balasan
          // Cloudflare tertunda) DULU TIDAK PERNAH dicoba lagi — pengguna
          // duduk dengan pesan galat sampai ia keluar dan masuk kembali.
          // Denyut 5 detik ini sudah berjalan, jadi percobaan ulangnya gratis.
          await terbitkan();
          await deteksi();
        } else {
          await deteksi();
        }
      } catch (e) { /* jaringan putus sesaat: coba lagi pada denyut berikutnya */ }
    }), 5000);
  }

  // Lobi disiapkan lebih dulu; kalau peramban tidak mendukung kamera (mis. alamat
  // http:// biasa), tombol "Masuk Rapat" lama yang dipakai — perilaku tidak berubah.
  // Penangan tombol masuk dipasang DI DALAM `siapkanLobi()`, sebelum lobinya
  // ditampilkan (lihat komentar di sana).
  siapkanLobi();
  bHenti.addEventListener('click', () => antri(hentikan));
  bMik?.addEventListener('click', bisukanMikrofon);
  bPresentasi?.addEventListener('click', () => {
    if (presentasiAktif) hentikanPresentasi();
    else berkasPresentasi?.click();
  });
  berkasPresentasi?.addEventListener('change', () => {
    const f = berkasPresentasi.files && berkasPresentasi.files[0];
    if (f) antri(() => bagikanPresentasi(f));
    berkasPresentasi.value = '';   // berkas yang sama boleh dipilih lagi
  });
  bKamera?.addEventListener('click', bisukanKamera);
  bAngkat?.addEventListener('click', angkatTangan);
  bBicara?.addEventListener('click', angkatTangan);
  formChat?.addEventListener('submit', (e) => {
    e.preventDefault();
    kirimChat(inputTeks.value);
    inputTeks.value = '';
  });
  document.getElementById('sfu-reaksi')?.querySelectorAll('button').forEach((b) => {
    b.addEventListener('click', () => {
      const emoji = EMOJI[b.dataset.emoji] || b.dataset.emoji;
      terbang(emoji, namaku);
      aksi('reaksi', '', { emoji });
    });
  });
  document.querySelectorAll('.rapat-tab .tab').forEach((t) => t.addEventListener('click', () => tab(t.dataset.panel)));
  // Pager panggung: geser satu halaman; jumlah kolom bisa berubah saat layar
  // diputar/diperkecil, jadi halaman dihitung ulang dari lebar nyata.
  halBar?.querySelectorAll('[data-hal]').forEach((b) => b.addEventListener('click', () => {
    hal += +b.dataset.hal;
    aturPanggung();
  }));
  window.addEventListener('resize', () => aturPanggung());
  aturPanggung();
  window.addEventListener('pagehide', () => {
    if (sesi) navigator.sendBeacon(R.keluar, new Blob([JSON.stringify({ sesi, _token: csrf })], { type: 'application/json' }));
  });
})();

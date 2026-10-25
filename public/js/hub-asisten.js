/* Asisten AI — kirim & tampilkan jawaban lewat AJAX.
 *
 * SEBELUMNYA: tiap kirim = POST form + muat ulang halaman penuh. Jawaban menunggu
 * model (2-6 detik) dan selama itu layar hanya diam — tombol contoh terasa
 * "tidak berfungsi" walau permintaannya sebenarnya terkirim.
 *
 * SEKARANG: kirim lewat fetch, tampilkan gelembung "sedang menulis" seketika,
 * lalu ganti dengan jawaban. Kotak pesan hanya yang berubah, bukan seluruh
 * halaman. TANPA JS halaman tetap jalan: form-nya form biasa, server tetap
 * menjawab dengan redirect + flash. Karena itu markup & fallback tidak diubah.
 */
(function () {
  const kotak = document.getElementById('ai');
  if (!kotak || !window.fetch) return;

  const balon = document.getElementById('ai-balon');
  const mulai = document.getElementById('ai-mulai');
  const form = kotak.querySelector('form[data-ai-kirim]');
  const isian = document.getElementById('tanya');
  const tombol = kotak.querySelector('[data-ai-tombol]');
  const ajuan = kotak.dataset.ajuan;
  const token = kotak.dataset.token;
  const indeks = document.getElementById('hub-proses');
  let sedang = false;

  function proses(nyala) { if (indeks) indeks.hidden = !nyala; }

  /** Balon baru. `peran` = 'user' | 'asisten' | 'galat'. */
  function tiup(peran, teks) {
    // `data-kosong` dirender server untuk menyembunyikan panel saat percakapan
    // belum ada. Penanda itu WAJIB dilepas di sini — tanpa ini balon baru
    // ditambahkan ke panel yang masih display:none, jadi pertanyaan pertama
    // pengguna tidak pernah terlihat.
    balon.removeAttribute('data-kosong');
    const kelas = peran === 'user' ? 'ingat' : (peran === 'galat' ? 'rusak' : 'ok');
    const el = document.createElement('div');
    // Tanpa `pesan`: balon percakapan bukan kotak peringatan. `role` tidak lagi
    // per-balon — #ai-balon sudah jadi kawasan live (role="status").
    el.className = 'aibalon ' + kelas;
    const b = document.createElement('b');
    b.textContent = peran === 'user' ? 'Anda' : 'Asisten';
    const isi = document.createElement('div');
    isi.className = 'aiteks';
    isi.textContent = teks;
    el.append(b, isi);
    balon.append(el);
    return el;
  }

  /** Gelembung "sedang menulis" dengan tiga titik yang berdenyut. */
  function tiupMenulis() {
    const el = tiup('asisten', '');
    const isi = el.querySelector('.aiteks');
    isi.classList.add('aiteks-tunggu');
    isi.innerHTML = '<span class="ai-titik"></span><span class="ai-titik"></span>'
      + '<span class="ai-titik"></span>';
    return el;
  }

  function gulirKeBawah() {
    // Ruang percakapan punya batas tinggi + gulir sendiri (jawaban panjang bisa
    // satu layar lebih), jadi gulirkan DIA supaya jawaban terbaru terlihat.
    if (balon.scrollHeight > balon.clientHeight + 4) {
      balon.scrollTo({ top: balon.scrollHeight, behavior: 'smooth' });
    }
    // Lalu geser jendela supaya KOTAK TULIS duduk di dasar layar. Tanpa ini, di
    // ponsel kotak tulis berada di bawah lipatan (terukur: 490px percakapan +
    // 184px kepala) sehingga pengguna harus menggulir untuk membalas.
    const kotakTulis = kotak.querySelector('.ai-tulis');
    if (!kotakTulis) return;
    const dok = document.documentElement;
    const tujuan = Math.max(0, Math.min(dok.scrollHeight - window.innerHeight,
      window.scrollY + kotakTulis.getBoundingClientRect().bottom - window.innerHeight + 12));
    window.scrollTo({ top: tujuan, behavior: 'smooth' });
  }

  async function kirim(teks) {
    if (sedang) return;
    sedang = true;
    proses(true);
    if (tombol) {
      tombol.disabled = true;
      tombol.classList.add('sedang');
      if (!tombol.querySelector('.spinner')) {
        tombol.insertAdjacentHTML('afterbegin', '<span class="spinner"></span>');
      }
    }

    // Teks asli yang muncul di server (tanpa JS) baru tampil SETELAH muat ulang.
    // Dengan JS ia muncul seketika, jadi pengguna melihat apa yang ia kirim.
    tiup('user', teks);
    if (mulai) mulai.hidden = true;
    const tunggu = tiupMenulis();
    gulirKeBawah();

    const badan = new FormData();
    badan.append('_token', token);
    badan.append('tanya', teks);

    try {
      const res = await fetch(ajuan, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: badan,
        credentials: 'same-origin',
      });
      const data = await res.json().catch(() => ({}));

      if (!res.ok || !data.jawab) {
        tunggu.remove();
        tiup('galat', data.galat || ('Asisten gagal menjawab (HTTP ' + res.status + ').'));
      } else {
        // Isi balon penunggu diganti jawaban sungguhan. `jawab_html` sudah
        // disanitasi di server (Markdown html_input=strip) — jangan konversi
        // markdown di sini, dan jangan pakai textContent (penanda ** tampil mentah).
        const isi = tunggu.querySelector('.aiteks');
        if (data.jawab_html) isi.innerHTML = data.jawab_html;
        else isi.textContent = data.jawab;
        isi.classList.remove('aiteks-tunggu');
        isian.value = '';
        tampilkanTombolBersih();
      }
    } catch (e) {
      tunggu.remove();
      tiup('galat', 'Tidak bisa menghubungi server: ' + e.message);
    } finally {
      sedang = false;
      proses(false);
      if (tombol) {
        tombol.disabled = false;
        tombol.classList.remove('sedang');
        const sp = tombol.querySelector('.spinner');
        if (sp) sp.remove();
      }
      gulirKeBawah();
    }
  }

  /** Setelah jawaban pertama, tombol "Bersihkan Percakapan" perlu muncul —
   *  pada muat pertama ia hanya ada bila sesi sudah berisi percakapan. */
  function tampilkanTombolBersih() {
    const baris = document.querySelector('.kepala .tombol-baris');
    if (!baris || baris.querySelector('[data-ai-bersih]')) return;
    const f = document.createElement('form');
    f.method = 'post';
    f.action = kotak.dataset.hapus;
    f.setAttribute('data-ai-bersih', '');
    const t = document.createElement('input');
    t.type = 'hidden'; t.name = '_token'; t.value = token;
    const b = document.createElement('button');
    b.type = 'submit'; b.className = 'tombol samar'; b.textContent = 'Bersihkan Percakapan';
    f.append(t, b);
    baris.append(f);
  }

  document.addEventListener('submit', function (ev) {
    const f = ev.target;

    // Tombol contoh: kirim teksnya tanpa mengisi kotak isian.
    if (f.closest('#ai-contoh')) {
      const teks = (f.querySelector('input[name="tanya"]') || {}).value;
      if (!teks) return;
      ev.preventDefault();
      kirim(teks);
      return;
    }

    if (f.matches('form[data-ai-kirim]')) {
      const teks = (isian.value || '').trim();
      if (!teks) return;                 // biarkan validasi bawaan browser jalan
      ev.preventDefault();
      kirim(teks);
    }
  });

  // Enter mengirim, Shift+Enter membuat baris baru — kebiasaan kotak obrolan.
  isian.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && !ev.shiftKey) {
      ev.preventDefault();
      if (isian.form.reportValidity()) kirim(isian.value.trim());
    }
  });
})();

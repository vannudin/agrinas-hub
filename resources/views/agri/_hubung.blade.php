{{--
  Dialog "Hubungkan AgriConnect" + notifikasi JS.

  Kenapa satu dialog di sini, bukan halaman /agri/hris/akun lagi: Bos meminta
  tombol Hubungkan ada di samping Ringkasan dan terbuka sebagai pop-up
  (2026-10-21). Halaman /agri/hris/akun TETAP hidup — ia kini status sesi dan
  tujuan tombol "Lepas", tetapi bukan lagi butir menu.

  Sandi tidak pernah disimpan: server hanya menyimpan token sesi terenkripsi di
  baris user (lihat App\Services\Agriconnect::hubungkan). Setelah berhasil,
  halaman dimuat ulang supaya data AgriConnect benar-benar ditarik — bukan
  sekadar tombolnya berubah warna.

  Jawaban server: JSON `{ok:true, pesan:…}` / HTTP 422 `{ok:false, pesan:…}` —
  lihat AgriHrisController::akun_masuk.
--}}
@php
  $acAgri = app(\App\Services\Agriconnect::class);
  $uAgri = auth()->user();
  // Peran pemantau (admin/HR/PIC/keuangan) tidak punya akun AgriConnect pribadi.
  // Dialog ini pernah menampilkan "Tersambung sebagai irpan-cslw" di akun admin —
  // sesi orang lain yang kebetulan tertaut. Bagi mereka: keadaan selalu "belum",
  // dan isinya diganti penjelasan + jalan ke daftar akun manajer.
  $pemantauAgri = $uAgri && ! $uAgri->manajerKoperasi();
  $sambungAgri = $uAgri && ! $pemantauAgri && $acAgri->tersambung($uAgri);
@endphp
<dialog class="modal" id="modal-agri" aria-labelledby="modal-agri-judul">
  <form method="post" action="{{ route('agri.hris.masuk') }}" id="form-agri">
    @csrf
    <header class="modal-kepala">
      <h2 id="modal-agri-judul">Hubungkan AgriConnect</h2>
      <button type="button" class="tombol samar mini ikon-tutup" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      @if ($pemantauAgri)
        <p class="tenang">Peran pemantau tidak menghubungkan akun AgriConnect pribadi.
          Yang dipantau adalah akun AgriConnect para manajer.</p>
        <p><a class="tombol" href="{{ route('agri.hris.akun') }}">Buka daftar akun manajer</a></p>
      @elseif ($sambungAgri)
        <p class="pesan ok">Tersambung sebagai <b>{{ $uAgri->agri_username }}</b>@if ($uAgri->agri_tersambung_pada) sejak {{ \Illuminate\Support\Carbon::parse($uAgri->agri_tersambung_pada)->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB @endif. Seluruh fitur AgriConnect di menu sudah terbuka.</p>
        <p class="tenang">Data pegawai dibaca langsung dari AgriConnect setiap halaman dibuka — tidak disalin ke Hub.</p>
      @else
        <p class="tenang">Pakai nama pengguna &amp; sandi yang sama seperti di aplikasi AgriConnect. Sandi tidak disimpan; yang disimpan hanya token sesi.</p>
        <div class="grid-form">
          <label for="agri-username">Nama pengguna</label>
          <input id="agri-username" name="username" required autocomplete="username"
                 value="{{ old('username', $uAgri->agri_username) }}" placeholder="mis. irpan-cslw">

          <label for="agri-sandi">Sandi</label>
          <input id="agri-sandi" name="sandi" type="password" required autocomplete="current-password">

        </div>
      @endif
    </div>
    <footer class="modal-kaki">
      @if ($sambungAgri)
        {{-- Lepas sesi ada di halaman status, bukan di sini: satu formulir satu
             tujuan, jadi skrip di bawah tak perlu menebak tombol mana yang
             ditekan (formaction di dalam form login pernah mengirim login kosong). --}}
        <a class="tombol" href="{{ route('agri.hris.akun') }}">Kelola sesi</a>
      @endif
      <button type="button" class="tombol samar" data-tutup>Tutup</button>
      @if (! $sambungAgri && ! $pemantauAgri)
        <button type="submit" class="tombol utama">Hubungkan</button>
      @endif
    </footer>
  </form>
</dialog>

<script>
/* Satu blok untuk tiga hal: buka/tutup dialog, kunci butir yang butuh akun,
   dan kirim login lewat fetch supaya hasilnya jadi notifikasi — bukan halaman
   galat. Tanpa JS: butir terkunci tetap tautan biasa dan formulirnya POST
   normal (halaman /agri/hris/akun yang menjawab), jadi tidak ada jalan buntu. */
(function () {
  function dialog() { return document.getElementById('modal-agri'); }
  function beritahu(teks, rusak) {
    if (window.hubToast) { window.hubToast(teks, rusak); return; }
    // hub.js memuat toast; kalau karena satu sebab ia absen, jangan diam saja.
    window.alert(teks);
  }

  document.addEventListener('click', function (ev) {
    var kunci = ev.target.closest('[data-perlu-agri]');
    if (kunci) {
      ev.preventDefault();
      beritahu('Hubungkan Akun AgriConnect untuk membuka fitur ini.', true);
      var d = dialog();
      if (d && typeof d.showModal === 'function' && !d.open) d.showModal();
      return;
    }
    var buka = ev.target.closest('[data-buka="modal-agri"]');
    if (buka) {
      var m = dialog();
      if (m && typeof m.showModal === 'function' && !m.open) m.showModal();
      return;
    }
    var tutup = ev.target.closest('[data-tutup]');
    if (tutup) {
      var mm = tutup.closest('dialog');
      if (mm) mm.close();
    }
  });

  var f = document.getElementById('form-agri');
  if (!f) return;
  f.addEventListener('submit', function (ev) {
    // Tombol "Tutup" bukan submit; satu-satunya submit di sini = Hubungkan.
    ev.preventDefault();
    var b = f.querySelector('.tombol.utama');
    if (b) { b.disabled = true; b.classList.add('sedang'); }
    fetch(f.action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(f)
    }).then(function (r) {
      return r.json().catch(function () { return { pesan: 'Jawaban server tidak terbaca.' }; })
        .then(function (d) { return { ok: r.ok, d: d || {} }; });
    }).then(function (h) {
      var d = h.d;
      var galat = d.errors ? Object.keys(d.errors).map(function (k) { return d.errors[k][0]; })[0] : null;
      var pesan = galat || d.pesan || d.message || 'Gagal menghubungkan akun AgriConnect.';
      var sukses = h.ok && d.ok === true;
      beritahu(pesan, !sukses);
      if (b) { b.disabled = false; b.classList.remove('sedang'); }
      // Muat ulang = data AgriConnect benar-benar ditarik dengan token baru.
      if (sukses) { window.setTimeout(function () { window.location.reload(); }, 1400); }
    }).catch(function () {
      beritahu('Tidak bisa menghubungi server. Periksa koneksi lalu coba lagi.', true);
      if (b) { b.disabled = false; b.classList.remove('sedang'); }
    });
  });
})();
</script>

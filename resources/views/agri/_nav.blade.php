{{--
  Menu modul AgriConnect: kisi ikon berKELOMPOK, bukan satu deret 20 kartu.

  KENAPA DIKELOMPOKKAN: daftar ini tumbuh dari 14 ke 20 butir begitu modul
  kepegawaian masuk, dan satu deret panjang tidak memberi tahu mana urusan
  kehadiran dan mana data pribadi — di ponsel ia jadi empat baris kartu yang
  tak terbaca polanya.

  KENAPA BUKAN `<details>` YANG DILIPAT: itu yang dipakai versi pertama dan
  SALAH — isi `<details>` tanpa `open` tidak dirender sama sekali, jadi di
  DESKTOP menu 4 kelompok HILANG dan tinggal 5 bar judul (terukur Chrome 145:
  `checkVisibility()` false, tinggi kelompok 26px), sementara di ponsel pemakai
  harus mengklik 5 kali untuk menemukan tujuan. Sekarang semua kelompok selalu
  terbuka; kependekan ruang dijawab dengan 2 kolom di ponsel (kartu 175px,
  label terbaca) alih-alih menyembunyikan tautan.

  Tiap butir tetap memuat label teks, bukan hanya ikon, agar di ponsel tidak
  ada informasi yang hilang.

  DUA BUTIR KHUSUS (permintaan Bos 2026-10-21):
  - `hris-hubungkan` = <button> pembuka dialog login, bukan tautan; halaman
    statusnya tetap ada di /agri/hris/akun tetapi tidak lagi jadi butir menu.
  - Seluruh butir ber-rute `agri.hris.*` DIKUNCI selama akun AgriConnect belum
    tersambung: tetap <a href> (tanpa JS halaman tujuan menjelaskan sendiri),
    tetapi ber-`data-perlu-agri` sehingga JS menahan kliknya dan menampilkan
    notifikasi "Hubungkan Akun AgriConnect untuk membuka fitur ini".
--}}
@php
  // SELALU dari AgriController::MODUL, bukan `$modul` yang dikirim halaman.
  // Dulu tiap halaman mengirim daftarnya sendiri (AgriController atau
  // AgriHrisController), sehingga menu yang terlihat BERGANTUNG halaman mana
  // yang sedang dibuka. Satu daftar = satu menu di mana pun.
  $daftar = \App\Http\Controllers\AgriController::MODUL;
  // Butir ber-seksi kosong (Ringkasan + Hubungkan) berdiri sendiri di atas.
  $kepala = array_values(array_filter($daftar, fn ($m) => ($m[4] ?? '') === ''));
  $kelompok = [];
  foreach ($daftar as $m) {
      if (($m[4] ?? '') !== '') $kelompok[$m[4]][] = $m;
  }
  $ini = $mod ?? '';
  // Murah: hanya membaca token tersimpan di baris user, tanpa panggilan API.
  $bolehAgri = auth()->check() && app(\App\Services\Agriconnect::class)->tersambung(auth()->user());
  $kartu = function (array $m) use ($ini, $bolehAgri) {
      [$kunci, $rute, $label, $keterangan] = $m;
      $kelas = 'kartu-fitur'.($ini === $kunci ? ' ini' : '');
      $ikon = '<span class="kartu-fitur-ikon">'.ikon($kunci, 'ik-fitur').'</span>'
        .'<span class="kartu-fitur-label">'.e($label).'</span>';
      if ($kunci === 'hris-hubungkan') {
          return '<button type="button" class="'.$kelas.'" data-buka="modal-agri"'
            .' title="'.e($label).' — '.e($keterangan).'">'.$ikon.'</button>';
      }
      $kunci_butir = str_starts_with($rute, 'agri.hris.') && ! $bolehAgri;

      return '<a class="'.$kelas.($kunci_butir ? ' terkunci' : '').'" href="'.route($rute).'"'
        .' title="'.e($label).' — '.e($keterangan).'"'
        .($kunci_butir ? ' data-perlu-agri' : '')
        .($ini === $kunci ? ' aria-current=page' : '').'>'.$ikon.'</a>';
  };
@endphp<nav class="kisi-modul" aria-label="Menu AgriConnect">
  <div class="kisi-baris">
    @foreach ($kepala as $m) {!! $kartu($m) !!} @endforeach
  </div>
  @foreach ($kelompok as $seksi => $butir)
    <section class="kisi-seksi">
      <h3 class="kisi-seksi-judul">
        {{ $seksi }}
        <span class="kisi-seksi-jumlah">{{ count($butir) }}</span>
      </h3>
      <div class="kisi-baris">
        @foreach ($butir as $m) {!! $kartu($m) !!} @endforeach
      </div>
    </section>
  @endforeach
</nav>
@include('agri._hubung')
@push('skrip')
  {{-- Cari + filter + paging untuk SEMUA tabel daftar AgriConnect. Dimuat dari
       kedua _nav (lihat agri/hris/_nav) supaya 21 halaman ikut tanpa menambah
       satu baris pun di masing-masing halaman. --}}
  <script src="/js/hub-saring-tabel.js?v={{ filemtime(public_path('js/hub-saring-tabel.js')) }}" defer></script>
@endpush

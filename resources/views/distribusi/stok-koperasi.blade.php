@extends('layouts.utama')

@section('judul', 'Stok Koperasi')
@section('isi')

{{-- Pemilih koperasi (admin & PIC saja). Manajer langsung ke koperasinya, jadi
     blok ini tidak dirender untuknya — bukan disembunyikan lewat CSS. --}}
@unless ($tulis)
  <section class="blok">
    <h2>Pilih Koperasi</h2>
    <p class="bantuan">
      @if ($koperasi)
        Sedang melihat <b>{{ $koperasi->nama }}</b> (<code>{{ $koperasi->kode }}</code>).
      @else
        Pilih koperasi yang ingin Anda periksa. Yang tampil hanya koperasi dalam lingkup Anda.
      @endif
    </p>
    <form class="saring cari-koperasi" method="get" action="{{ route('distribusi.stok-koperasi') }}"
          data-url="{{ route('distribusi.stok-koperasi.pilih') }}">
      <label class="sr" for="cari_kop">Cari koperasi</label>
      <div class="cari-kop">
        <input id="cari_kop" data-cari-kop name="cari_kop" type="search" value=""
               placeholder="Cari koperasi — kode atau nama…" autocomplete="off"
               role="combobox" aria-expanded="false" aria-controls="kop-hasil">
        @include('distribusi._pemilih-koperasi', ['hasil' => collect(), 'q' => '', 'pilihanId' => $koperasi?->id ?? 0])
      </div>
    </form>
  </section>
@endunless

@if (! $koperasi)
  <p class="kosong">Pilih satu koperasi di atas untuk melihat stok barangnya.</p>
@else

<header class="kepala">
  <div>
    <h1>Stok Koperasi</h1>
    {{-- Deskripsi kepala WAJIB pendek: `.kepala .tenang` adalah aturan proyek yang
         memotong teksnya dengan ellipsis pada lebar menengah, dan gate tata letak
         menandai teks terpotong sebagai cacat (informasi tidak boleh disembunyikan).
         Kalimat panjangnya dulu: "…daftar barang koperasi Anda. Tambah barang lewat
         tombol di kanan, perbarui stoknya setiap hari." — terukur terpotong di 1280px. --}}
    <p class="tenang">
      {{ $koperasi->nama }} <code>{{ $koperasi->kode }}</code>
      — {{ $tulis ? 'kelola daftar barang & stok harian.' : 'hanya lihat (dikelola manajer koperasi).' }}
    </p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('distribusi.daftar') }}">Permintaan Saya</a>
  </div>
</header>

{{-- Hasil impor CSV terakhir: berapa masuk, dan baris mana yang dilewati. --}}
@if (session('impor_galat') && count(session('impor_galat')))
  <section class="blok">
    <h2>Baris yang Dilewati</h2>
    <ul class="bantuan">
      @foreach (session('impor_galat') as $g)
        <li>{{ $g }}</li>
      @endforeach
    </ul>
  </section>
@endif

@if ($ringkas)
  <section class="blok">
    <div class="kartu-baris">
      <div class="kartu angka"><small>Jenis Barang</small><b>{{ number_format($ringkas['jenis'], 0, ',', '.') }}</b>
        <small class="pudar">terdaftar di koperasi ini</small></div>
      <div class="kartu angka"><small>Stok Menipis</small><b>{{ number_format($ringkas['menipis'], 0, ',', '.') }}</b>
        <small class="pudar">sudah di bawah/tiba di ambang</small></div>
      <div class="kartu angka"><small>Nilai Stok</small><b>{{ number_format($ringkas['nilai'], 0, ',', '.') }}</b>
        <small class="pudar">Rp — dihitung dari harga jual yang diisi</small></div>
    </div>
  </section>
@endif

<section class="blok" id="kb" data-isi="{{ route('distribusi.stok-koperasi', ['koperasi' => $koperasi->id]) }}"
         data-params="q,halaman,koperasi">
  <div class="cat-kepala">
    <h2>Daftar Barang Koperasi</h2>
    <div class="tombol-baris">
      {{-- Barcode: OPSIONAL, bukan syarat. Tombolnya (ikon saja) ada di bilah
           saring, tepat di sebelah kotak pencarian — pencarian dan scan itu satu
           pekerjaan yang sama (menemukan satu barang), jadi alatnya duduk di
           tempat orang mencarinya, bukan di baris tombol lain. --}}
      {{-- Impor/ekspor CSV: manajer mengisi & memeriksa daftarnya dari luar
           aplikasi (Excel). Tombolnya selalu tampil — yang menjaga kewenangan
           adalah controller, bukan tampilannya. --}}
      <a class="tombol samar" href="{{ route('distribusi.stok-koperasi.template') }}">Template CSV</a>
      <a class="tombol samar" href="{{ route('distribusi.stok-koperasi.ekspor', ['koperasi' => $koperasi->id]) }}">Ekspor CSV</a>
      @if ($tulis)
        <button class="tombol samar" type="button" data-buka="impor-modal">Impor CSV</button>
        <button class="tombol utama" type="button" data-buka="barang-modal"
                data-judul-dialog="Tambah Barang"
                data-awal="{{ json_encode(['id' => '', 'kode' => '', 'barcode' => '', 'nama' => '', 'satuan' => 'pcs', 'kategori' => 'SEMBAKO', 'stok' => 0, 'stok_min' => 0, 'harga' => 0, 'catatan' => '', 'promo_jenis' => '', 'promo_nilai' => 0, 'promo_min' => 0, 'promo_bonus_id' => 0]) }}">Tambah Barang</button>
      @endif
    </div>
  </div>

  {{-- `data-otomatis`: mengetik langsung menyaring tanpa memuat ulang halaman
       (hub.js, tunda 400 ms). Bilah ini berada DI DALAM wadah `#kb` yang isinya
       ditukar hub.js, jadi hub.js memulihkan fokus kotak cari setelah penukaran
       — tanpa itu ketikan kedua hilang ke <body> dan pencarian terasa mati.
       Tanpa JavaScript tetap form GET biasa. --}}
  <form class="saring" method="get" action="{{ route('distribusi.stok-koperasi') }}" data-otomatis>
    <input type="hidden" name="koperasi" value="{{ $koperasi->id }}">
    <label class="cari">
      <span class="sr">Cari barang</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off"
             placeholder="Cari nama, kode, atau barcode…">
    </label>
    {{-- Tombol scan: IKON SAJA, di sebelah kolom pencarian. `aria-label` =
         "Scan Barcode" (huruf besar) mengikuti teks tombol lamanya — itulah nama
         yang dibaca pembaca layar dan dicari gate. --}}
    <button class="tombol samar tombol-ikon" type="button" data-buka="scan-modal"
            title="Scan barcode" aria-label="Scan Barcode">
      <svg class="ik" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3h5v2H5v3H3zm13 0h5v5h-2V5h-3zM3 16v5h5v-2H5v-3zm18 0v5h-5v-2h3v-3zM7.5 8h1.6v8H7.5zm2.8 0h1.1v8h-1.1zm2.5 0h2.2v8h-2.2zm3.6 0h1.6v8h-1.6z"/></svg>
    </button>
    <a class="tombol samar bersih-saring" href="{{ route('distribusi.stok-koperasi', ['koperasi' => $koperasi->id]) }}"
       @if ($cari === '') hidden @endif>Bersihkan</a>
  </form>

  <div class="tabel-bungkus">
    <table>
      {{-- Empat sel ber-label saja (Barang, Kategori, Stok, Diperbarui).
           Di ponsel tiap sel ber-`data-l` menambah satu baris, jadi stok +
           ambang + harga digabung di SATU sel "Stok" — memisahkannya jadi tiga
           baris membuat satu kartu barang setinggi hampir satu layar. --}}
      <thead>
        <tr>
          <th>Barang</th><th>Kategori</th><th>Stok</th><th>Diperbarui</th>
          @if ($tulis)<th class="kolom-aksi">Aksi</th>@endif
        </tr>
      </thead>
      <tbody>
      @forelse ($barang as $b)
        <tr data-id="{{ $b->id }}" @if ($b->barcode !== '') data-barcode="{{ $b->barcode }}" @endif>
          <td data-l="Barang">
            <span class="judul-sel">{{ $b->nama }}</span>
            <small class="pudar">
              @if ($b->kode !== '')<code>{{ $b->kode }}</code> · @endif
              @if ($b->barcode !== '')barcode {{ $b->barcode }} · @endif
              {{ $b->catatan !== '' ? $b->catatan : '' }}
            </small>
            @if ($b->promo_jenis !== '')
              <span class="lencana s-DISETUJUI" data-promo>{{ $b->labelPromo() }}</span>
            @endif
          </td>
          <td data-l="Kategori">{{ \App\Models\KoperasiBarang::KATEGORI[$b->kategori] ?? $b->kategori }}</td>
          <td data-l="Stok">
            <b>{{ number_format($b->stok, 0, ',', '.') }}</b> {{ $b->satuan }}
            @if ($b->menipis())<span class="lencana s-REVISI">Menipis</span>@endif
            <small class="pudar">
              ambang {{ $b->stok_min > 0 ? number_format($b->stok_min, 0, ',', '.') : '—' }}
              @if ($b->harga > 0) · Rp {{ number_format($b->harga, 0, ',', '.') }}@endif
            </small>
          </td>
          <td data-l="Diperbarui">{{ $b->diperbarui ? waktu_id($b->diperbarui) : '—' }}</td>
          @if ($tulis)
            <td class="kolom-aksi">
              <button class="tombol samar mini" type="button" data-buka="stok-modal"
                      data-judul-dialog="Perbarui Stok"
                      data-aksi="{{ route('distribusi.stok-koperasi.stok', $b->id) }}"
                      data-awal="{{ json_encode(['stok' => (int) $b->stok, 'nama' => $b->nama, 'satuan' => $b->satuan]) }}">Stok</button>
              <button class="tombol samar mini" type="button" data-buka="barang-modal"
                      data-judul-dialog="Ubah Barang"
                      data-awal="{{ json_encode(['id' => $b->id, 'kode' => $b->kode, 'barcode' => $b->barcode, 'nama' => $b->nama, 'satuan' => $b->satuan, 'kategori' => $b->kategori, 'stok' => (int) $b->stok, 'stok_min' => (int) $b->stok_min, 'harga' => (int) $b->harga, 'catatan' => $b->catatan, 'promo_jenis' => $b->promo_jenis, 'promo_nilai' => (int) $b->promo_nilai, 'promo_min' => (int) $b->promo_min, 'promo_bonus_id' => (int) $b->promo_bonus_id]) }}">Ubah</button>
              <form method="post" action="{{ route('distribusi.stok-koperasi.hapus', $b->id) }}"
                    data-konfirmasi="Hapus &quot;{{ $b->nama }}&quot; dari daftar barang koperasi?">
                @csrf @method('DELETE')
                <button class="tombol samar mini" type="submit">Hapus</button>
              </form>
            </td>
          @endif
        </tr>
      @empty
        <tr><td colspan="{{ $tulis ? 5 : 4 }}" class="kosong">
          @if ($cari !== '')
            Tidak ada barang yang cocok dengan pencarian ini.
          @elseif ($tulis)
            Daftar masih kosong — tekan <b>Tambah Barang</b> di kanan atas tabel.
          @else
            Manajer koperasi ini belum mengisi daftar barangnya.
          @endif
        </td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>

@if ($riwayat->isNotEmpty())
<section class="blok">
  <h2>Riwayat Perubahan Stok</h2>
  <p class="bantuan">Sepuluh perubahan terakhir — supaya angka stok bisa ditelusuri, bukan berubah diam-diam.</p>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Waktu</th><th>Sebab</th><th>Perubahan</th><th>Catatan</th></tr></thead>
    <tbody>
      @foreach ($riwayat as $r)
        <tr>
          <td data-l="Waktu">{{ waktu_id($r->dibuat) }}</td>
          <td data-l="Sebab">{{ \App\Models\KoperasiBarang::SEBAB[$r->sebab] ?? $r->sebab }}</td>
          <td data-l="Perubahan">
            {{ number_format($r->sebelum, 0, ',', '.') }} →
            <b>{{ number_format($r->sesudah, 0, ',', '.') }}</b>
            <span class="lencana s-{{ $r->selisih >= 0 ? 'SELESAI' : 'REVISI' }}">
              {{ $r->selisih >= 0 ? '+' : '' }}{{ number_format($r->selisih, 0, ',', '.') }}
            </span>
          </td>
          <td data-l="Catatan">{{ $r->catatan !== '' ? $r->catatan : '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  </div>
</section>
@endif

@if ($diterima->isNotEmpty())
<section class="blok">
  <h2>Kiriman Diterima dari Agrinas</h2>
  <p class="bantuan">Sumber stok yang paling sering. Angka ini dari surat jalan yang sudah Anda terima.</p>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Kode</th><th>Barang</th><th>Diterima</th><th>Terakhir Masuk</th></tr></thead>
    <tbody>
      @foreach ($diterima as $s)
        <tr>
          <td data-l="Kode"><code>{{ $s->kode }}</code></td>
          <td data-l="Barang"><span class="judul-sel">{{ $s->nama }}</span></td>
          <td data-l="Diterima"><b>{{ number_format($s->jumlah, 0, ',', '.') }}</b> {{ $s->satuan }}</td>
          <td data-l="Terakhir Masuk">{{ tanggal_id($s->terakhir) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  </div>
</section>
@endif

@if ($tulis)
{{-- SATU <dialog> melayani Tambah & Ubah barang: tombol pembuka mengisi judul dan
     nilai field lewat data-awal (hub.js); `id` di body menentukan tambah/ubah. --}}
<dialog class="modal" id="barang-modal" aria-labelledby="barang-modal-judul">
  <form method="post" action="{{ route('distribusi.stok-koperasi.simpan') }}">
    @csrf
    <input type="hidden" name="id" value="">
    <header class="modal-kepala">
      <h2 id="barang-modal-judul">Tambah Barang</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Nama barang <input name="nama" maxlength="160" required placeholder="Contoh: Beras Premium 5 kg"></label>
      <div class="grid-form">
        <label>Kode <span class="bantuan">(opsional)</span>
          <input name="kode" maxlength="32" placeholder="BRG-001"></label>
        <div>
          <label>Barcode <span class="bantuan">(opsional — dari scan)</span>
            <input name="barcode" maxlength="64" placeholder="Kosongkan bila tidak ada"></label>
          {{-- Scan barcode LANGSUNG dari sini: dulu alat scan hanya bisa dibuka
               dari bilah atas, jadi menambah barang ber-barcode berarti keluar dari
               formulir ini dulu. Sekarang hasil scan jatuh ke kolom Barcode ini,
               dan kalau barcodenya belum terdaftar modal ini terbuka sendiri dalam
               mode tambah untuk melengkapinya. Barcode tetap OPSIONAL. --}}
          <div class="tombol-baris">
            <button class="tombol samar mini" type="button" data-buka="scan-modal"
                    data-scan-untuk="barang-modal" data-scan-isi="barcode">Scan barcode</button>
          </div>
        </div>
      </div>
      <div class="grid-form">
        <label>Satuan
          <select name="satuan" required>
            @foreach ($satuanOpsi as $s)
              <option value="{{ $s }}">{{ $s }}</option>
            @endforeach
          </select></label>
        <label>Kategori
          <select name="kategori" required>
            @foreach ($kategoriOpsi as $k => $l)
              <option value="{{ $k }}">{{ $l }}</option>
            @endforeach
          </select></label>
      </div>
      <div class="grid-form">
        <label>Stok <input type="number" name="stok" min="0" max="1000000" step="1" value="0" required></label>
        <label>Ambang <span class="bantuan">(0 = tanpa peringatan)</span>
          <input type="number" name="stok_min" min="0" max="1000000" step="1" value="0" required></label>
      </div>
      <label>Harga jual <span class="bantuan">(Rp, opsional)</span>
        <input type="number" name="harga" min="0" max="1000000000" step="1" value="0" required></label>
      <label>Catatan <span class="bantuan">(opsional)</span>
        <input name="catatan" maxlength="200" placeholder="Letak rak, pemasok, dll."></label>

      {{-- PROMO. Dua bentuk saja, sesuai yang benar-benar dipakai koperasi desa:
           potongan persen, atau bonus barang gratis setelah beli sekian.
           Keduanya dihitung ULANG di server saat nota disimpan. --}}
      <fieldset class="promo-isian">
        <legend>Promo <span class="bantuan">(opsional)</span></legend>
        <div class="grid-form">
          <label>Jenis promo
            <select name="promo_jenis" data-promo-jenis>
              @foreach ($promoOpsi as $kN => $lN)
                <option value="{{ $kN }}">{{ $lN }}</option>
              @endforeach
            </select></label>
          <label>Nilai promo
            <input type="number" name="promo_nilai" min="0" max="1000" step="1" value="0"
                   data-promo-nilai aria-describedby="promo-nilai-petunjuk">
            <span class="bantuan" id="promo-nilai-petunjuk" data-promo-petunjuk>—</span></label>
          <label data-promo-untuk="GRATIS">Barang bonus
            <select name="promo_bonus_id">
              <option value="0">— pilih barang —</option>
              @foreach ($bonusOpsi as $bo)
                <option value="{{ $bo->id }}">{{ $bo->nama }}</option>
              @endforeach
            </select></label>
          <label>Minimal beli <span class="bantuan">(0 = langsung berlaku)</span>
            <input type="number" name="promo_min" min="0" max="1000" step="1" value="0"></label>
        </div>
        <p class="bantuan">Diskon dihitung dari harga jual × jumlah, dibulatkan ke rupiah.
          Bonus diberikan sekali per baris nota selama jumlahnya mencapai minimal beli.</p>
      </fieldset>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan Barang</button>
    </footer>
  </form>
</dialog>

{{-- Modal impor CSV. Satu unggahan = satu laporan hasil: berapa ditambahkan,
     berapa diperbarui, dan baris mana yang dilewati beserta sebabnya. --}}
<dialog class="modal" id="impor-modal" aria-labelledby="impor-modal-judul">
  <form method="post" action="{{ route('distribusi.stok-koperasi.impor') }}" enctype="multipart/form-data">
    @csrf
    <header class="modal-kepala">
      <h2 id="impor-modal-judul">Impor Barang dari CSV</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan">Susun berkasnya di Excel memakai susunan kolom template, lalu pilih berkasnya di sini.
        Pemisah kolom titik koma (;) atau koma (,) — keduanya terbaca.</p>
      <label>Berkas CSV
        <input type="file" name="berkas" accept=".csv,text/csv" required></label>
      <p class="bantuan">
        <b>Baris dicocokkan lewat barcode atau kode:</b> barang yang sudah ada diperbarui,
        yang belum ada ditambahkan. Karena itu berkas yang sama boleh diunggah ulang
        (mis. setelah hitung ulang stok) tanpa membuat barang kembar.
      </p>
      <p class="bantuan">Satu baris yang salah tidak membatalkan baris lainnya — baris yang
        dilewati akan dilaporkan beserta nomornya.
        <a href="{{ route('distribusi.stok-koperasi.template') }}">Unduh template CSV</a>.</p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Impor Sekarang</button>
    </footer>
  </form>
</dialog>

{{-- Modal update stok harian. Dua cara: isi angka BARU (hasil hitung ulang) atau
     SELISIH (barang masuk/keluar). Sebabnya wajib supaya riwayatnya bermakna. --}}
<dialog class="modal" id="stok-modal" aria-labelledby="stok-modal-judul">
  <form method="post" action="">
    @csrf
    <header class="modal-kepala">
      <h2 id="stok-modal-judul">Perbarui Stok</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan" data-stok-nama>—</p>
      <label>Stok baru <span class="bantuan">(hasil hitung ulang; kosongkan bila memakai selisih)</span>
        <input type="number" name="stok" min="0" max="1000000" step="1" value="0"></label>
      <label>Selisih <span class="bantuan">(mis. 12 untuk barang masuk, -3 untuk keluar)</span>
        <input type="number" name="tambah" min="-1000000" max="1000000" step="1" placeholder="kosong = pakai stok baru"></label>
      <label>Sebab
        <select name="sebab" required>
          @foreach ($sebabOpsi as $k => $l)
            <option value="{{ $k }}">{{ $l }}</option>
          @endforeach
        </select></label>
      <label>Catatan <span class="bantuan">(opsional)</span>
        <input name="catatan" maxlength="200" placeholder="Mis. sisa setelah penjualan pasar"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan Stok</button>
    </footer>
  </form>
</dialog>
@endif

{{-- Alat scan barcode — OPSIONAL, tersedia untuk semua peran (manajer mencari
     barangnya, admin/PIC memeriksa). Markup-nya di partial supaya halaman POS
     memakai alat yang PERSIS sama, bukan salinan yang menyimpang. --}}
@include('partials.scan-barcode', [
    'scanCari' => route('distribusi.stok-koperasi.cari', ['koperasi' => $koperasi->id]),
])

@endif
{{-- Pemilih koperasi: satu berkas skrip melayani halaman ini DAN /keuangan
     (kontrak data-* yang sama), jadi tidak ada salinan logika pencarian. --}}
@unless ($tulis)
  <script src="/js/hub-pemilih-kop.js?v={{ filemtime(public_path('js/hub-pemilih-kop.js')) }}" defer></script>
@endunless
<script src="/js/hub-stok-koperasi.js?v={{ filemtime(public_path('js/hub-stok-koperasi.js')) }}" defer></script>
<script defer>
  // PROMO: satu kolom nilai dipakai dua jenis promo, jadi labelnya mengikuti
  // jenisnya. Diskon dibatasi 0–100 (persen); bonus 0–1000 (unit barang).
  (function () {
    var modal = document.getElementById('barang-modal');
    if (!modal) return;
    var jenis = modal.querySelector('[data-promo-jenis]');
    var nilai = modal.querySelector('[data-promo-nilai]');
    var petunjuk = modal.querySelector('[data-promo-petunjuk]');

    function segarkan() {
      var j = jenis.value;
      modal.querySelectorAll('[data-promo-untuk]').forEach(function (el) {
        el.hidden = el.getAttribute('data-promo-untuk') !== j;
      });
      nilai.max = j === 'DISKON' ? '100' : '1000';
      if (petunjuk) {
        petunjuk.textContent = j === 'DISKON' ? 'persen potongan dari harga × jumlah'
          : (j === 'GRATIS' ? 'jumlah unit barang bonus yang diberikan' : 'tanpa promo');
      }
      if (j === '') nilai.value = 0;
    }
    jenis.addEventListener('change', segarkan);
    document.addEventListener('click', function (ev) {
      if (ev.target.closest && ev.target.closest('[data-buka="barang-modal"]')) setTimeout(segarkan, 0);
    });
    segarkan();
  })();
</script>
@endsection

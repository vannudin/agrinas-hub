@extends('layouts.utama')

@section('judul', 'Produk Lokal & UMKM')
@section('isi')
<header class="kepala">
  <div>
    <h1>Produk Lokal &amp; UMKM</h1>
    <p class="tenang">
      @if ($agrinas)
        Produk lokal pertanian, peternakan, dan UMKM — di luar distribusi Agrinas. Semua koperasi.
      @else
        {{ $koperasi->nama ?? 'Koperasi' }} — catat hasil pertanian, peternakan, atau produk UMKM di sekitar.
      @endif
    </p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('distribusi.daftar') }}">Distribusi Agrinas</a>
  </div>
</header>

@unless ($agrinas)
  @if ($koperasi)
    <section class="blok">
      <h2>Catat Pasokan Baru</h2>
      {{-- enctype WAJIB: tanpa ini berkas invoice tidak terkirim sama sekali dan
           validasi server menolak dengan "berkas invoice wajib diunggah". --}}
      <form method="post" action="{{ route('pasokan.simpan') }}" class="grid-form"
            enctype="multipart/form-data">
        @csrf
        {{-- Jenis menentukan asal barang & daftar komoditas yang disodorkan.
             Komoditas tetap BEBAS diketik (datalist cuma usulan), karena hasil
             tani/peternakan di tiap daerah tidak bisa didaftar lengkap. --}}
        <label>Jenis produk
          <select name="jenis" required>
            @foreach (\App\Models\Pasokan::JENIS as $k => $l)
              <option value="{{ $k }}" @selected(old('jenis', 'UMKM') === $k)>{{ $l }}</option>
            @endforeach
          </select></label>
        <label>Nama UMKM / Poktan / Kelompok <input type="text" name="umkm_nama" maxlength="120" required
          placeholder="Contoh: UD Sumber Tani"></label>
        <label>Kontak <span class="bantuan">(opsional)</span>
          <input type="text" name="umkm_kontak" maxlength="60" placeholder="08xxxxxxxxxx"></label>
        <label>Alamat <span class="bantuan">(opsional)</span>
          <input type="text" name="umkm_alamat" maxlength="160" placeholder="Desa, kecamatan"></label>
        <label>Komoditas / Produk <input type="text" name="komoditas" maxlength="120" required
          list="komoditas-umum" placeholder="Contoh: gabah kering, telur ayam, keripik">
          {{-- Satu datalist memuat SEMUA jenis: memilih jenis lain tidak
               langsung mengubah isinya tanpa JS, tetapi usulan dari jenis lain
               tetap lebih menolong daripada kotak kosong. --}}
          <datalist id="komoditas-umum">
            @foreach (\App\Models\Pasokan::KOMODITAS as $senarai)
              @foreach ($senarai as $k)
                <option value="{{ $k }}"></option>
              @endforeach
            @endforeach
          </datalist>
        </label>
        <label>Jumlah <input type="number" name="jumlah" min="1" max="1000000" step="1" value="1" required></label>
        <label>Satuan
          <select name="satuan" required>
            @foreach (\App\Models\Barang::SATUAN as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
          </select>
        </label>
        <label>Harga beli dari UMKM (Rp) <input type="number" name="harga_satuan" min="0" max="999999999" step="1" value="0" required></label>
        <label>Tanggal <input type="date" name="tanggal" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></label>
        <label class="lebar-penuh">Catatan <span class="bantuan">(opsional)</span>
          <input type="text" name="catatan" maxlength="300" placeholder="Contoh: kirim minggu depan"></label>

        {{-- Opsi tayang: menampilkan produk ini di etalase /toko supaya bisa
             dipesan koperasi lain. Bukan tayang seketika — pasokan harus
             disetujui pengelola dulu, dan itu disebutkan apa adanya di bawah
             supaya tidak jadi janji yang tidak ditepati. --}}
        <h3 class="lebar-penuh">Jual ke Koperasi Lain</h3>
        <p class="bantuan lebar-penuh">
          Isi harga jual bila produk ini mau ditawarkan ke koperasi lain lewat
          <b>Toko</b>. Dikosongkan berarti harga jualnya sama dengan harga beli
          dari UMKM.
        </p>
        <label>Harga jual (Rp) <span class="bantuan">(opsional)</span>
          <input type="number" name="harga_jual" min="0" max="999999999" step="1" value="0"></label>
        {{-- tayang=0 sebagai pasangan checkbox: kotak centang yang TIDAK
             dicentang tidak mengirim apa pun, sehingga validasi 'in:0,1'
             jatuh ke null -> 0 karena nullable. Tetap aman apa adanya. --}}
        <label class="centang lebar-penuh">
          <input type="checkbox" name="tayang" value="1">
          <span>Tayangkan di Toko setelah disetujui pengelola — bisa dipesan koperasi lain.</span>
        </label>

        {{-- Pengajuan pembayaran. Tanpa rekening tujuan bendahara tidak punya
             arah transfer — karena itu rekening wajib. INVOICE tidak lagi
             diketik: nomornya DITERBITKAN SISTEM dari kode pasokan, dan berkas
             lampiran opsional. Sebelumnya nomor + berkas keduanya wajib, dan
             itu membuat satu pasokan tidak bisa dicatat sebelum UMKM mengirim
             gambar tagihannya — padahal yang dibayar Agrinas adalah pasokan
             yang sudah tercatat, bukan gambar kiriman UMKM. --}}
        <h3 class="lebar-penuh">Pengajuan Pembayaran ke Agrinas</h3>
        <p class="bantuan lebar-penuh">
          Isi rekening UMKM penerima. Invoice resmi diterbitkan otomatis oleh sistem
          (nomor di bawah), jadi tidak perlu diketik. Agrinas akan memeriksa,
          menyetujui, lalu mengunggah bukti pembayaran.
        </p>
        <label>Bank
          <select name="bank" required>
            @foreach (\App\Models\PasokanBayar::BANK as $b)
              <option value="{{ $b }}">{{ $b }}</option>
            @endforeach
          </select></label>
        <label>Nomor rekening <input type="text" name="no_rekening" maxlength="40" required
          inputmode="numeric" placeholder="Contoh: 003401011234567"></label>
        <label>Atas nama <input type="text" name="atas_nama" maxlength="120" required
          placeholder="Nama pemilik rekening (UMKM)"></label>
        <label>Nomor invoice <span class="bantuan">(terbit otomatis)</span>
          <input type="text" value="{{ \App\Models\PasokanBayar::invoiceBaru() }}" readonly
                 disabled class="terkunci" aria-describedby="invoice-auto"></label>
        <p class="bantuan" id="invoice-auto">
          Nomor ini dibuat sistem saat pasokan disimpan — tidak bisa diubah, supaya
          tagihan dan pasokan selalu bisa dilacak balik satu sama lain.
        </p>
        <label class="lebar-penuh">Lampiran invoice dari UMKM
          <span class="bantuan">(opsional, PDF/gambar, maks 10 MB)</span>
          <input type="file" name="invoice" accept=".pdf,image/*"></label>

        <div class="tombol-baris lebar-penuh">
          <button class="tombol utama" type="submit">Simpan &amp; Ajukan Pembayaran</button>
        </div>
      </form>
    </section>
  @endif
@endunless

{{-- Wadah ber-`data-isi`: hub.js menukar ISI-nya untuk saringan & paging yang
     tetap di halaman ini, sehingga tabel tidak memuat ulang halaman penuh.
     Tanpa JavaScript semuanya tetap form GET & tautan biasa. --}}
<section class="blok" id="ps" data-isi="{{ route('pasokan') }}" data-daftar="{{ route('pasokan.baris') }}">
  <h2>Daftar Pasokan
    <small class="pudar" id="ps-jumlah">{{ number_format($jumlah, 0, ',', '.') }} pasokan</small>
  </h2>

  {{-- Kartu JENIS produk = saringan daftar per Pertanian / Peternakan / UMKM.
       Ditaruh di atas kartu status: memilih jenis lebih dulu, lalu status. --}}
  <div class="kartu-baris kartu-status">
    @foreach (\App\Models\Pasokan::JENIS as $jk => $jl)
      <a class="kartu angka {{ $jenis === $jk ? 'aksi-hijau' : '' }}"
         href="{{ route('pasokan', array_filter(['jenis' => $jk, 'q' => $cari])) }}">
        <small>{{ $jl }}</small>
        <b>{{ $statJenis[$jk] ?? 0 }}</b>
      </a>
    @endforeach
    <a class="kartu angka {{ $jenis === '' ? 'aksi-hijau' : '' }}"
       href="{{ route('pasokan', array_filter(['q' => $cari])) }}">
      <small>Semua jenis</small>
      <b>{{ array_sum($statJenis) }}</b>
    </a>
  </div>

  {{-- Kartu status = ringkasan + saringan tabel. "Semua" selalu tampil supaya
       selalu ada jalan keluar; tanpa itu penyaring bisa buntu. Tanpa JavaScript
       kartunya tautan biasa (memuat ulang) — tetap jalan. --}}
  <div class="kartu-baris kartu-status">
    @foreach (\App\Models\Pasokan::STATUS as $s)
      <a class="kartu angka {{ $status === $s ? 'aksi-hijau' : '' }}"
         href="{{ route('pasokan', array_filter(['status' => $s, 'q' => $cari])) }}">
        <small>{{ \App\Models\Pasokan::label($s) }}</small>
        <b>{{ $agrinas ? ($stat[$s] ?? 0) : ($statKop[$s] ?? 0) }}</b>
      </a>
    @endforeach
    <a class="kartu angka {{ $status === '' ? 'aksi-hijau' : '' }}"
       href="{{ route('pasokan', array_filter(['q' => $cari])) }}">
      <small>Semua</small>
      <b>{{ $agrinas ? array_sum($stat) : array_sum($statKop) }}</b>
    </a>
  </div>

  {{-- Bilah saring: cari + filter status baris. Pilihan status hanya yang memang
       ada datanya (dihitung server), jadi pengguna tak disodori pilihan kosong.
       Penanda `status` dibawa sebagai field tersembunyi: saringan kartu jangan
       ikut hilang saat pengguna mengetik di kotak cari. --}}
  <form class="saring" method="get" action="{{ route('pasokan') }}">
    <label class="cari">
      <span class="sr">Cari pasokan</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="q" value="{{ $cari }}" autocomplete="off"
             placeholder="Cari kode, UMKM, komoditas, atau koperasi…">
    </label>
    <label class="pilih">
      <select name="jenis" aria-label="Saring berdasarkan jenis produk">
        <option value="">Semua jenis</option>
        @foreach (\App\Models\Pasokan::JENIS as $jk => $jl)
          <option value="{{ $jk }}" @selected($jenis === $jk)>{{ $jl }} ({{ $statJenis[$jk] ?? 0 }})</option>
        @endforeach
      </select>
    </label>
    <label class="pilih">
      <select name="status_kop" aria-label="Saring berdasarkan status">
        <option value="">Semua status</option>
        @foreach (\App\Models\Pasokan::STATUS as $s)
          @if (($stat[$s] ?? 0) > 0)
            <option value="{{ $s }}" @selected($saringStatus === $s)>{{ \App\Models\Pasokan::label($s) }} ({{ $stat[$s] }})</option>
          @endif
        @endforeach
      </select>
    </label>
    @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
    <a class="tombol samar bersih-saring" href="{{ route('pasokan') }}"
       @if ($cari === '' && $saringStatus === '') hidden @endif>Bersihkan</a>
    <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
  </form>

  <div class="tabel-bungkus">
  <table>
    {{-- ENAM kolom, berpasangan persis dengan enam sel badan: di ponsel setiap sel
         ber-`data-l` menambah satu baris, jadi memecah "Jumlah" dan "Nilai" jadi
         kolom sendiri membuat satu pasokan setinggi hampir satu layar. Isi yang
         sekeluarga digabung (Jumlah & Nilai, Status & Pembayaran); koperasi
         menempel di sel Kode untuk admin. --}}
    <thead><tr>
      <th>Kode</th><th>UMKM &amp; Komoditas</th>
      <th>Jumlah &amp; Nilai</th><th>Status &amp; Pembayaran</th>
      <th class="kolom-aksi">Aksi</th>
    </tr></thead>
    <tbody id="ps-baris">
      @include('pasokan._baris', ['daftar' => $daftar, 'agrinas' => $agrinas,
        'kosong' => ($cari !== '' || $saringStatus !== '')])
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>

{{-- Modal Update pasokan: satu pemilih status + satu keterangan. Satu <dialog>
     untuk seluruh tabel — isinya diisi dari data-* tombol yang ditekan. --}}
<dialog class="modal" id="pasokan-modal" aria-labelledby="pasokan-modal-judul">
  <form method="post" id="pasokan-form" action="{{ route('pasokan.putus', 0) }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="pasokan-modal-judul">Update Pasokan</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>

    <div class="modal-badan">
      <p class="bantuan" id="pasokan-ringkas"></p>
      <p class="pesan rusak" id="pasokan-modal-galat" role="alert" hidden></p>
      <p class="bantuan" id="pasokan-modal-kabar" role="status" hidden></p>

      <label>Status
        <select name="status" id="pasokan-status" required></select>
      </label>
      <label>Keterangan / catatan penolakan
        <textarea name="catatan_agrinas" id="pasokan-catatan" rows="3" maxlength="300"
                  placeholder="Wajib diisi bila menolak — mis. komoditas tidak sesuai"></textarea>
      </label>
      <p class="bantuan" id="pasokan-petunjuk"></p>
    </div>

    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit" id="pasokan-simpan">Simpan</button>
    </footer>
  </form>
</dialog>

{{-- ============ Pengajuan & pembayaran pasokan UMKM ============
     Tiga dialog terpisah untuk tiga tindakan yang berbeda, karena tiap tindakan
     punya berkas & penjaga kewenangan sendiri — menggabungkannya jadi satu
     formulir "serba bisa" akan membuat tombol yang salah mudah ditekan.

     Pola yang sudah ada dipakai apa adanya: `data-buka` + `data-judul-dialog` +
     `data-awal` ditangani hub.js, sedangkan rute tujuan diisi di sini karena
     `data-aksi` belum dikenali hub.js. Halaman ini punya RUTE BERBEDA PER BARIS
     (id pengajuan), jadi JS umum yang mengisi semua `[data-aksi]` cukup. --}}

{{-- 1. Pengaju (manajer koperasi): rekening + invoice. --}}
<dialog class="modal" id="bayar-ajukan" aria-labelledby="bayar-ajukan-judul">
  <form method="post" action="" enctype="multipart/form-data">
    @csrf
    <header class="modal-kepala">
      <h2 id="bayar-ajukan-judul">Ajukan Pembayaran</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan" data-ringkas>—</p>
      <p class="bantuan">
        Isi rekening UMKM penerima. Invoice resmi diterbitkan otomatis oleh sistem,
        jadi tidak ada nomor yang perlu diketik. Uang dibayarkan Agrinas setelah
        pengajuan ini disetujui.
      </p>
      <label>Bank
        <select name="bank" required>
          @foreach ($bankOpsi as $b)<option value="{{ $b }}">{{ $b }}</option>@endforeach
        </select></label>
      <div class="grid-form">
        <label>Nomor rekening <input type="text" name="no_rekening" maxlength="40" required
          inputmode="numeric" placeholder="003401011234567"></label>
        <label>Atas nama <input type="text" name="atas_nama" maxlength="120" required
          placeholder="Nama pemilik rekening"></label>
      </div>
      <div class="grid-form">
        <label>Nomor invoice <span class="bantuan">(terbit otomatis)</span>
          <input type="text" value="{{ \App\Models\PasokanBayar::invoiceBaru() }}" readonly
                 disabled class="terkunci"></label>
        <label>Tanggal <input type="date" value="{{ now()->toDateString() }}" readonly
          disabled class="terkunci"></label>
      </div>
      <label>Lampiran invoice dari UMKM <span class="bantuan">(opsional, PDF/gambar, maks 10 MB)</span>
        <input type="file" name="invoice" accept=".pdf,image/*"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Kirim Pengajuan</button>
    </footer>
  </form>
</dialog>

{{-- 2. Pengelola (admin Agrinas / PIC wilayah): setujui atau tolak. --}}
<dialog class="modal" id="bayar-putus" aria-labelledby="bayar-putus-judul">
  <form method="post" action="">
    @csrf
    <header class="modal-kepala">
      <h2 id="bayar-putus-judul">Keputusan Pengajuan</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan" data-ringkas>—</p>
      <label>Keputusan
        <select name="status" required>
          <option value="DISETUJUI">Setujui</option>
          <option value="DITOLAK">Tolak</option>
        </select></label>
      <label>Keterangan <span class="bantuan">(wajib bila menolak)</span>
        <textarea name="catatan" rows="2" maxlength="300"
          placeholder="Contoh: nominal invoice tidak sesuai jumlah pasokan"></textarea></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan Keputusan</button>
    </footer>
  </form>
</dialog>

{{-- 3. Pengelola: unggah bukti transfer setelah disetujui. --}}
<dialog class="modal" id="bayar-bukti" aria-labelledby="bayar-bukti-judul">
  <form method="post" action="" enctype="multipart/form-data">
    @csrf
    <header class="modal-kepala">
      <h2 id="bayar-bukti-judul">Bukti Pembayaran</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan" data-ringkas>—</p>
      <label>Berbukti transfer <span class="bantuan">(PDF/gambar, maks 10 MB)</span>
        <input type="file" name="bukti" accept=".pdf,image/*" required></label>
      <label>Catatan <span class="bantuan">(opsional)</span>
        <input type="text" name="bukti_catatan" maxlength="300"
          placeholder="Contoh: transfer via BRI, 12 Nov 2026"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan Bukti Bayar</button>
    </footer>
  </form>
</dialog>

{{-- 4. Manajer: tayangkan / turunkan produk lokal di Toko (order antar-koperasi). --}}
<dialog class="modal" id="pasokan-tayang" aria-labelledby="pasokan-tayang-judul">
  <form method="post" action="">
    @csrf
    <header class="modal-kepala">
      <h2 id="pasokan-tayang-judul">Tayangkan di Toko</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan" data-ringkas>—</p>
      <input type="hidden" name="tayang" value="1">
      <p class="bantuan">
        Produk yang tayang muncul di <b>Toko</b> dan bisa dipesan koperasi lain.
        Harganya per satuan; kosongkan (0) berarti memakai harga beli dari UMKM.
      </p>
      <label>Harga jual (Rp) per satuan
        <input type="number" name="harga_jual" min="0" max="999999999" step="1" value="0"></label>
      <p class="bantuan" data-teks></p>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit" id="pasokan-tayang-simpan">Simpan</button>
    </footer>
  </form>
</dialog>

<script src="/js/hub-pasokan.js?v={{ filemtime(public_path('js/hub-pasokan.js')) }}" defer></script>
@endsection

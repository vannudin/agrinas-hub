@extends('layouts.utama')

@section('judul', 'Akun')
@section('isi')
{{-- Wadah AJAX: pencarian, saringan, dan pindah halaman mengganti SELURUH isi di
     dalam sini. Tanpa JS tetap jalan penuh: tautan paging & tombol Filter adalah
     <a>/<form> biasa. --}}
<div id="daftar" data-isi="{{ route('admin.akun') }}" data-params="q,peran,status,halaman">
  <header class="kepala">
    <div><h1>Akun Pengguna</h1>
      <p class="tenang">Kelola akun, peran, dan status aktifnya. Satu formulir untuk
        semua peran — pilih perannya di daftar <b>Peran</b>.</p></div>
    <div class="tombol-baris">
      <a class="tombol utama" href="{{ route('admin.broadcast') }}">Kirim Siaran</a>
    </div>
  </header>

  @if ($q !== '' || $peran !== '' || $status !== '')
    <div class="pesan" role="status">
      Menampilkan hasil saringan — <b>{{ $halaman->jumlah }}</b> akun.
      <a href="{{ route('admin.akun') }}">Tampilkan semua</a>
    </div>
  @endif

  <section class="blok">
    <div class="kepala-bagian">
      <h2>Tambah / angkat akun</h2>
      {{-- Formulirnya di MODAL (sama seperti /admin/toko): formulir panjang yang
           menempel di halaman mendorong daftar akun jauh ke bawah, dan di ponsel
           ia memakan dua layar sebelum akun pertama terlihat. --}}
      <div class="tombol-baris">
        <button class="tombol utama" type="button" data-buka="akun-modal"
                data-judul-dialog="Tambah / angkat akun">Tambah / angkat akun</button>
      </div>
    </div>
    <p class="tenang">Satu akun satu peran. Email yang sudah terdaftar <b>diangkat</b> ke peran
      yang dipilih — bukan membuat akun kedua.</p>

    {{-- Wewenang tiap peran dulu satu paragraf panjang: pembaca harus menyusuri
         lima kalimat sebelum menemukan peran yang ia cari, dan di ponsel
         paragraf itu jadi 12 baris abu-abu. Sekarang satu kartu per peran. --}}
    <div class="kisi-peran">
      <div class="kartu kartu-peran">
        <b>PIC Wilayah</b>
        <small class="pudar">Koperasi, tugas, keuangan, distribusi, pasokan, audit —
          wilayahnya sendiri. Lingkup: satu provinsi.</small>
      </div>
      <div class="kartu kartu-peran">
        <b>Petugas HR</b>
        <small class="pudar">Meja SDM &amp; Kepegawaian: kontrak, gaji, penilaian,
          rekrutmen. Lingkup: semua wilayah.</small>
      </div>
      <div class="kartu kartu-peran">
        <b>Keuangan</b>
        <small class="pudar">Pembayaran pasokan, pelunasan order, buku kas. Lingkup:
          semua wilayah.</small>
      </div>
      <div class="kartu kartu-peran">
        <b>TNI Pengawasan</b>
        <small class="pudar">Hanya membaca 10 modul pengawasan + unggah materi.
          Lingkup: PUSAT, provinsi, atau kabupaten/kota.</small>
      </div>
      <div class="kartu kartu-peran">
        <b>Dinas Koperasi</b>
        <small class="pudar">Sama dengan TNI Pengawasan, termasuk lingkup dan
          pagar hanya-baca.</small>
      </div>
      <div class="kartu kartu-peran">
        <b>Vendor Penyalur</b>
        <small class="pudar">Hanya halaman mitra: kirim barang ke koperasi, terbitkan
          tagihan ke Agrinas. Tanpa dasbor, koperasi, gudang, atau kas.</small>
      </div>
    </div>

    <dialog class="modal" id="akun-modal" aria-labelledby="akun-modal-judul">
    <form method="post" action="{{ route('admin.akun.simpan') }}">
      @csrf
      <header class="modal-kepala">
        <h2 id="akun-modal-judul">Tambah / angkat akun</h2>
        <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
      </header>
      <div class="modal-badan">
      <div class="grid-form">
      <label>Nama
        <input name="nama" required maxlength="120" value="{{ old('nama') }}" placeholder="Nama lengkap">
      </label>
      <label>Email
        <input name="email" type="email" required maxlength="160" value="{{ old('email') }}" placeholder="nama@agrinas.co.id">
      </label>
      <label>Peran
        <select name="peran" required>
          @foreach (['PIC', 'HR', 'KEUANGAN', 'TNI', 'DINAS', 'VENDOR'] as $kodePeran)
            <option value="{{ $kodePeran }}" @selected(old('peran') === $kodePeran)>{{ \App\Models\Domain::LABEL_PERAN[$kodePeran] }}</option>
          @endforeach
        </select>
      </label>
      <label>Sandi awal <span class="bantuan">(opsional)</span>
        <input name="sandi" maxlength="200" placeholder="Kosongkan = dibuat otomatis">
      </label>
      {{-- Vendor hanya dipakai peran Vendor Penyalur. Ditampilkan selalu (bukan
           disembunyikan lewat JS) supaya formulir tetap terpakai tanpa JavaScript;
           akun VENDOR tanpa tautan ditolak server, bukan hanya di layar. --}}
      <label class="lebar-penuh">Vendor
        <select name="vendor_id">
          <option value="">— bukan akun vendor —</option>
          @foreach ($vendorList as $idVendor => $namaVendor)
            <option value="{{ $idVendor }}" @selected((string) old('vendor_id') === (string) $idVendor)>{{ $namaVendor }}</option>
          @endforeach
        </select>
        <span class="bantuan">Wajib untuk peran <b>Vendor Penyalur</b>: menentukan barang
          mana yang boleh ia kirim dan tagih. Peran lain: biarkan kosong.</span>
      </label>
      {{-- Wilayah: PIC wajib provinsi; pengawasan boleh PUSAT, provinsi, atau
           kabupaten/kota. Satu kolom, satu daftar — nilai kabupaten diambil dari
           data pemetaan lahan supaya tidak ada ketikan bebas yang salah. --}}
      <label class="lebar-penuh">Wilayah / Lingkup
        {{-- Satu kolom, satu kendali: 534 opsi (37 provinsi + 495 kabupaten/kota)
             tak bisa dicari di <select> bawaan, jadi dipasangi Select2 — berkasnya
             sudah ada di /vendor (dipakai Messenger), bukan CDN. Kotak cari
             Select2 MENGGANTIKAN kotak ini saat dibuka, jadi tak ada kolom kedua. --}}
        <select name="wilayah" id="pilih-wilayah" class="pilih2">
          <option value="">— tidak perlu —</option>
          <option value="{{ \App\Support\Wilayah::PUSAT }}" @selected(old('wilayah') === \App\Support\Wilayah::PUSAT)>PUSAT (nasional)</option>
          <optgroup label="Provinsi">
            @foreach ($wilayah as $kodeWilayah => $namaWilayah)
              <option value="{{ $kodeWilayah }}" @selected(old('wilayah') === $kodeWilayah)>{{ $namaWilayah }}</option>
            @endforeach
          </optgroup>
          <optgroup label="Kabupaten / Kota (pengawasan)">
            @foreach ($kabupaten as $kab)
              <option value="{{ $kab }}" @selected(old('wilayah') === $kab)>{{ str_starts_with($kab, 'Kota') ? $kab : 'Kab. '.$kab }}</option>
            @endforeach
          </optgroup>
        </select>
        <span class="bantuan">PIC: pilih provinsi · Pengawasan: PUSAT, provinsi, atau
          kabupaten/kota (menentukan data yang boleh dibaca) · HR &amp; Keuangan: tidak perlu.</span>
      </label>
      </div>
      <p class="bantuan">Sandi otomatis ditampilkan sekali di pesan setelah disimpan — kirim lewat
        kanal yang aman. Akun <b>Manajer Koperasi</b> tidak dibuat di sini: ia lahir dari
        login SIMKOPDES pendamping dan sudah tertaut ke koperasi binaannya.</p>
      </div>
      <footer class="modal-kaki">
        <button class="tombol samar" type="button" data-tutup>Tutup</button>
        <button class="tombol utama" type="submit">Simpan akun</button>
      </footer>
    </form>
    </dialog>
    @if ($errors->any())
      <div class="pesan rusak" role="alert">{{ $errors->first() }}</div>
      <script>document.addEventListener('DOMContentLoaded', function () { var d = document.getElementById('akun-modal'); if (d && d.showModal) d.showModal(); });</script>
    @endif
  </section>

  <section class="blok">
    <h2>Daftar Akun
      <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }} akun</small>
    </h2>

    <form class="saring" method="get" action="{{ route('admin.akun') }}">
      <label class="sr" for="q">Cari akun</label>
      <input id="q" name="q" value="{{ $q }}" placeholder="Cari nama, email, atau koperasi…">
      {{-- id unik: `peran` di formulir tambah juga bernama sama, dan label
           saring memakai sr-only. Tanpa id, uji peramban memilih dropdown
           yang salah (yang tanpa opsi kosong) dan menunggu selamanya. --}}
      <select id="saring-peran" name="peran" aria-label="Saring peran">
        <option value="">Semua peran</option>
        @foreach (\App\Models\Domain::LABEL_PERAN as $kode => $nama)
          <option value="{{ $kode }}" @selected($peran === $kode)>{{ $nama }}</option>
        @endforeach
      </select>
      <select name="status" aria-label="Saring status">
        <option value="">Semua status</option>
        <option value="aktif" @selected($status === 'aktif')>Aktif</option>
        <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
      </select>
      <button class="tombol samar" type="submit">Filter</button>
    </form>

    <div class="tabel-bungkus">
    <table class="tabel-akun">
      {{-- Nama & email digabung dalam SATU kolom "Akun": keduanya identitas
           orang yang sama, dan memisahkannya membuat dua kolom sempit saling
           berebut lebar — nama 32 karakter terpaksa jadi 4 baris. Pola baku
           panel admin: nama tebal, email kecil di bawahnya. --}}
      <thead><tr><th>Akun</th><th>Peran</th><th>Koperasi / Wilayah</th><th>Status</th><th>Masuk terakhir</th><th class="kolom-aksi">Aksi</th></tr></thead>
      <tbody>
      @forelse ($daftar as $a)
        <tr>
          <td data-l="Akun">
            <span class="judul-sel">{{ $a->nama }}</span>
            <small class="pudar pecah">{{ $a->email }}</small>
          </td>
          <td data-l="Peran">
            <span class="lencana {{ $a->peran === 'PIC' ? 's-DIAJUKAN' : '' }}">{{ \App\Models\Domain::LABEL_PERAN[$a->peran] ?? $a->peran }}</span>
          </td>
          <td data-l="Koperasi / Wilayah">
            @if ($a->peran === 'PIC')
              {{ \App\Support\Wilayah::pendek((string) $a->wilayah) }}
            @elseif (in_array($a->peran, \App\Models\Domain::PERAN_PANTAU, true))
              {{ $a->wilayah === \App\Support\Wilayah::PUSAT ? 'PUSAT (nasional)' : (\App\Support\Wilayah::sah((string) $a->wilayah) ? \App\Support\Wilayah::pendek((string) $a->wilayah) : (string) $a->wilayah) }}
              <small class="pudar">pengawasan</small>
            @else
              {{ $a->nama_koperasi ?: '—' }}@if ($a->kode_koperasi) <small class="pudar">{{ $a->kode_koperasi }}</small>@endif
            @endif
          </td>
          <td data-l="Status">
            <span class="lencana {{ $a->aktif ? 's-SELESAI' : 's-REVISI' }}">{{ $a->aktif ? 'Aktif' : 'Nonaktif' }}</span>
            @if ($a->wajib_ganti)<small class="pudar">wajib ganti sandi</small>@endif
          </td>
          <td data-l="Masuk terakhir">{{ $a->masuk_terakhir ? waktu_id($a->masuk_terakhir) : '—' }}</td>
          <td class="kolom-aksi">
            {{-- Dua aksi saja, sebaris. Dulu ada tiga anak (tautan Siaran, form
                 aktif/nonaktif, lencana "id N") yang ditumpuk vertikal sehingga
                 tiap baris setinggi 120px di desktop (300–400px di ponsel).
                 Lencana nomor akun dibuang seluruhnya: tak ada aksi admin yang
                 memakainya, dan di ponsel ia menambah satu baris per akun. --}}
            <a class="tombol samar mini" href="{{ route('admin.broadcast', ['sasaran' => 'MANAJER']) }}#form-siaran">Siaran</a>
            @if ($a->id !== auth()->id())
              <form method="post" action="{{ route('admin.akun.aktif', $a->id) }}" class="sebaris"
                    data-konfirmasi="{{ $a->aktif ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $a->nama }} ({{ $a->email }})?{{ $a->aktif ? ' Ia tidak bisa masuk sampai diaktifkan lagi.' : '' }}"
                    data-konfirmasi-judul="{{ $a->aktif ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}">
                @csrf
                <button class="tombol {{ $a->aktif ? 'peringatan' : 'samar' }} mini" type="submit">
                  {{ $a->aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
              </form>
            @else
              <span class="samar-kecil">akun Anda</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">
          @if ($q !== '' || $peran !== '' || $status !== '')
            Tidak ada akun yang cocok dengan saringan ini.
          @else
            Belum ada akun.
          @endif
        </td></tr>
      @endforelse
      </tbody>
    </table>
    </div>

    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app', 'kunci' => 'halaman'])

    <p class="bantuan">
      Sandi akun disimpan sebagai <b>hash scrypt</b> (satu arah) — tak ada yang bisa membacanya
      kembali, termasuk admin. Karena itu sandi tidak pernah ditampilkan di halaman ini.
      Untuk memulihkan akses sebuah akun, buat sandi baru lewat perintah
      <code>php artisan hub:sandi &lt;email&gt;</code> (opsional <code>--sandi="..."</code>).
      Sandi baru wajib diganti pemiliknya saat masuk.
    </p>
  </section>
</div>
{{-- Di luar #daftar: hub.js menukar isi wadah itu saat pindah halaman/filter, dan
     <script> yang disisipkan lewat innerHTML tidak dijalankan peramban. --}}
@endsection

@push('gaya')
{{-- Select2 (sudah ada di /vendor, dipakai Messenger) — bukan CDN: Hub dipakai di
     jaringan lapangan. .pilih2 sudah digayakan di hub.css. --}}
<link rel="stylesheet" href="/vendor/select2/select2.min.css">
@endpush

@push('skrip')
{{-- Urutan penting — ketiganya `defer`: jQuery -> Select2 -> pemasangan. --}}
<script src="/vendor/jquery/jquery.min.js" defer></script>
<script src="/vendor/select2/select2.min.js" defer></script>
<script defer>
  // Kolom Wilayah berisi 534 opsi dan <select> bawaan tak bisa dicari.
  // Dipasang ULANG setiap #daftar ditukar hub.js (paging & filter mengganti
  // seluruh isinya, jadi elemen <select>-nya baru dan Select2 lama ikut hilang).
  (function () {
    function pasang() {
      var jq = window.jQuery;
      if (!jq || !jq.fn.select2) return;
      // dropdownParent WAJIB: kolom ini hidup di dalam <dialog>. Tanpa itu
      // daftar pilihan Select2 dirender di dalam dialog dan terpotong (atau
      // menutupi isian lain) karena dialog punya overflow sendiri.
      var dlg = jq('#pilih-wilayah').closest('dialog');
      jq('#pilih-wilayah:not(.select2-hidden-accessible)').select2({
        width: '100%',
        dropdownParent: dlg.length ? dlg : jq(document.body),
        allowClear: true,
        placeholder: 'Ketik untuk mencari provinsi / kabupaten / kota…',
        language: { noResults: function () { return 'Tidak ada wilayah yang cocok'; } },
      });
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', pasang);
    } else {
      pasang();
    }
    var d = document.getElementById('daftar');
    if (d) new MutationObserver(pasang).observe(d, { childList: true, subtree: true });
  })();
</script>
@endpush

@extends('layouts.utama')

@section('judul', 'Keuangan')
@section('isi')
@php
  $rp = fn ($n) => App\Models\Keuangan::rupiah($n);
  // `$rekap` null hanya pada akun manajer yang belum ditautkan ke koperasi.
  $netoPeriode = $rekap ? $rekap['pemasukan'] - $rekap['pengeluaran'] : 0;
  $hari = max(1, $rekap['hari_terisi'] ?? 1);
  $u = auth()->user();
  // Siapa yang sedang melihat menentukan APA yang boleh ia lakukan di sini.
  // Dulu halaman ini sama untuk semua orang: tombol Dashboard menunjuk /kerja
  // (halaman manajer) sehingga admin pusat mendarat di halaman kosong, dan
  // tombol Unduh CSV hanya muncul di mode satu koperasi — padahal pengelola
  // justru yang menyusun laporan.
  $peran = App\Models\Domain::LABEL_PERAN[$u->peran] ?? $u->peran;
  $pengelola = ! $u->manajerKoperasi();
  $bolehCatat = $koperasi !== null;   // butuh satu koperasi yang jelas
  $tautanDasbor = rute_dasbor($u);
  $csv = route('keuangan.ekspor', ['periode' => $periode, 'acuan' => $acuan,
                                   'koperasi' => request('koperasi')]);
@endphp

{{-- Baris "sedang dilihat" diberi `data-kop-status` supaya bisa disegarkan
     hub.js saat pengguna memilih koperasi: `[data-isi]` menukar ISI wadah
     (#daftar) saja, sehingga <header> di LUAR-nya tetap menampilkan "Gabungan
     seluruh koperasi" walaupun isinya sudah pindah ke satu koperasi. --}}
<header class="kepala">
  <div>
    <h1>Keuangan Koperasi
      <span class="lencana">{{ $peran }}</span>
    </h1>
    <p class="tenang" data-kop-status>
      @if ($koperasi)
        <code>{{ $koperasi->kode }}</code> · {{ $koperasi->nama }} — rekap dihitung sendiri.
      @elseif (! empty($agregat))
        Gabungan {{ number_format($totalKoperasi, 0, ',', '.') }} koperasi — pilih satu untuk mencatat.
      @else
        Akun Anda belum ditautkan ke koperasi.
        <a href="{{ route('kop') }}">Buat data koperasi</a> lebih dulu.
      @endif
    </p>
  </div>
  <div class="tombol-baris">
    @if ($rekap)
      {{-- CSV tersedia untuk SEMUA mode yang punya data: satu koperasi maupun
           agregat. Berkasnya mengikuti apa yang sedang dilihat (periode, acuan,
           koperasi), jadi yang diunduh = yang di layar. --}}
      <a class="tombol samar" href="{{ $csv }}">Unduh CSV</a>
    @endif
    {{-- Tujuan dasbor mengikuti PERAN (rute_dasbor): admin pusat/HR/PIC → /admin,
         keuangan → /keuangan, manajer → /kerja. Dulu selalu /kerja, sehingga
         admin mendarat di halaman manajer yang kosong baginya. Untuk peran
         KEUANGAN tujuan itu halaman ini sendiri — tombol ke diri sendiri tidak
         dirender. --}}
    @if ($tautanDasbor !== url()->current())
      <a class="tombol samar" href="{{ $tautanDasbor }}">Dashboard</a>
    @endif
  </div>
</header>

@if ($koperasi || ! empty($agregat))
  {{-- Wadah AJAX: mengubah periode atau menyaring Buku Kas mengganti seluruh isi
       blok ini sekaligus — kartu, rekap, dan tabel tak mungkin menampilkan angka
       dari periode berbeda. Tanpa JS tetap jalan: tautan & form elemen biasa. --}}
  <div id="daftar" data-isi="{{ route('keuangan') }}"
       data-params="q,jenis,lingkup,periode,acuan,koperasi,halaman">

    @if ($agregat)
      {{-- Mode bawaan: gambaran SELURUH koperasi. Kotak ini menjawab "berapa
           totalnya", dan satu-satunya cara kembali ke sini setelah memilih satu
           koperasi. Diberi bingkai + jarak supaya tidak menempel ke kartu di
           bawahnya (terukur 0px sebelumnya). --}}
      <div class="lingkup-kop">
        <div>
          <b>Seluruh koperasi</b> <span class="lencana">agregat</span>
          <small class="pudar">{{ number_format($totalKoperasi, 0, ',', '.') }} koperasi dalam lingkup Anda</small>
        </div>
        {{-- Bukan `.saring`: kelas itu sudah punya bingkai + latar sendiri, dan
             menyarangkannya di dalam baris `.lingkup-kop` menghasilkan DUA bingkai
             bersarang (kotak pencarian 242px mengambang di dalam baris 1117px) —
             itulah yang terbaca "seperti bertumpuk, kaya ada 2 kolom". --}}
        <form class="cari-koperasi" method="get" action="{{ route('keuangan') }}"
              data-url="{{ route('keuangan.koperasi') }}">
          <input type="hidden" name="periode" value="{{ $periode }}">
          <input type="hidden" name="acuan" value="{{ $acuan }}">
          <label class="sr" for="cari_kop">Pilih koperasi untuk dilihat</label>
          <div class="cari-kop">
            <input id="cari_kop" data-cari-kop name="cari_kop" type="search" value="{{ $cariKop }}"
                   placeholder="Cari satu koperasi — kode, nama, atau wilayah…" autocomplete="off"
                   role="combobox" aria-expanded="false" aria-controls="kop-hasil">
            @include('keuangan._koperasi-kotak', ['hasil' => $hasilKoperasi, 'q' => $cariKop,
              'pilihanId' => 0, 'periode' => $periode, 'acuan' => $acuan])
          </div>
        </form>
      </div>
    @endif

    @if ($totalKoperasi > 1 && $koperasi)
      {{-- Admin Agrinas/PIC: pilih koperasi mana yang dilihat.
           Daftar lengkap TIDAK dirender sebagai <option>: pada puluhan ribu
           koperasi itu berarti puluhan ribu baris di setiap pemuatan halaman, dan
           tetap tak bisa dicari. Yang dirender hanya koperasi yang sedang dicari
           pengguna, sepotong 20 baris. --}}
      <form class="saring cari-koperasi" method="get" action="{{ route('keuangan') }}"
            data-url="{{ route('keuangan.koperasi') }}">
        {{-- Koperasi yang sedang dilihat ikut dibawa: tanpa ini, menekan Enter
             setelah mengetik kata kunci akan memulihkan koperasi PERTAMA dalam
             lingkup (urutan nama) — halaman tampak melompat ke koperasi lain
             hanya karena mencari. --}}
        <input type="hidden" name="koperasi" value="{{ $koperasi->id }}">
        <input type="hidden" name="periode" value="{{ $periode }}">
        <input type="hidden" name="acuan" value="{{ $acuan }}">
        <label class="sr" for="cari_kop">Cari koperasi</label>
        <div class="cari-kop">
          <input id="cari_kop" data-cari-kop name="cari_kop" type="search" value="{{ $cariKop }}"
                 placeholder="Cari KDMP — kode, nama, atau wilayah…" autocomplete="off"
                 role="combobox" aria-expanded="false" aria-controls="kop-hasil"
                 aria-describedby="kop-terpilih">
          @include('keuangan._koperasi-kotak', ['hasil' => $hasilKoperasi, 'q' => $cariKop,
            'pilihanId' => $koperasi->id, 'periode' => $periode, 'acuan' => $acuan])
        </div>
        <button class="tombol samar" type="submit">Cari</button>
        <noscript><span class="bantuan">Ketik lalu tekan Enter, kemudian klik hasilnya.</span></noscript>
      </form>
      <p class="bantuan kop-terpilih" id="kop-terpilih">Sedang dilihat:
        <b>{{ $koperasi->kode }}</b> — {{ $koperasi->nama }} ·
        {{ number_format($totalKoperasi, 0, ',', '.') }} koperasi dalam lingkup Anda.</p>
    @endif

    {{-- Ringkasan periode terpilih. Tiap kartu memuat satu angka besar + satu
         keterangan pendukung, supaya angkanya punya konteks tanpa harus membuka
         tabel di bawah. --}}
    <div class="petak">
      <div class="kartu angka aksi-hijau">
        <small>Pemasukan · {{ App\Models\Keuangan::labelPeriode($periode) }}</small>
        <b class="t-hijau">{{ $rp($rekap['pemasukan']) }}</b>
        <small class="pudar">rata-rata {{ $rp((int) round($rekap['pemasukan'] / $hari)) }} / hari tercatat</small>
      </div>
      <div class="kartu angka aksi-merah">
        <small>Pengeluaran · {{ App\Models\Keuangan::labelPeriode($periode) }}</small>
        <b class="t-merah">{{ $rp($rekap['pengeluaran']) }}</b>
        <small class="pudar">{{ $rekap['pemasukan'] > 0 ? number_format($rekap['pengeluaran'] / $rekap['pemasukan'] * 100, 1, ',', '.') : '0,0' }}% dari pemasukan</small>
      </div>
      <div class="kartu angka {{ $netoPeriode >= 0 ? 'aksi-hijau' : 'aksi-merah' }}">
        <small>Neto</small>
        <b class="{{ $netoPeriode >= 0 ? 't-hijau' : 't-merah' }}">{{ $rp($netoPeriode) }}</b>
        <small class="pudar">{{ $netoPeriode >= 0 ? 'surplus' : 'defisit' }} · {{ $netoPeriode >= 0 ? 'kas bertambah' : 'kas berkurang' }}</small>
      </div>
      <div class="kartu angka">
        <small>Hari Tercatat</small>
        <b>{{ $rekap['hari_terisi'] }}</b>
        <small class="pudar">{{ tanggal_id($rekap['awal']) }} – {{ tanggal_id($rekap['akhir']) }}</small>
      </div>
      <div class="kartu angka {{ $saldoAkhir >= 0 ? 'aksi-hijau' : 'aksi-merah' }}">
        <small>Saldo Kas Akhir Periode</small>
        <b class="{{ $saldoAkhir >= 0 ? 't-hijau' : 't-merah' }}">{{ $rp($saldoAkhir) }}</b>
        <small class="pudar">awal {{ $rp($saldoAwal) }} + neto {{ $rp($netoPeriode) }}</small>
      </div>
    </div>

    {{-- Catat harian diletakkan sebelum tabel: ini tindakan yang paling sering
         dipakai, dan di ponsel ia tak terkubur di bawah daftar yang panjang.

         Hanya dirender bila ada SATU koperasi yang jelas tujuannya. Di mode
         agregat formulir ini tidak punya koperasi tujuan (dulu tetap tampil dan
         menyimpan ke koperasi pertama — angka masuk ke koperasi yang salah tanpa
         ada yang menyadarinya). Pengelola mencatat lewat pemilih koperasi di
         atas; form Ubah per baris tetap tersedia di tabel. --}}
    @if ($bolehCatat)
    <section class="blok" id="catat">
      <h2>Catat Keuangan Harian
        <small class="pudar">{{ $koperasi->kode }}</small>
      </h2>
      <form method="post" action="{{ route('keuangan.simpan') }}" class="grid-form">
        @csrf
        <input type="hidden" name="koperasi" value="{{ request('koperasi') }}">
        <label>Tanggal
          <input type="date" name="tanggal" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
        </label>
        <label>Pemasukan (Rp)
          <input type="number" name="pemasukan" min="0" max="999999999999" step="1" inputmode="numeric" value="0" required>
        </label>
        <label>Pengeluaran (Rp)
          <input type="number" name="pengeluaran" min="0" max="999999999999" step="1" inputmode="numeric" value="0" required>
        </label>
        <label>Kategori pemasukan
          <select name="kategori_pemasukan">
            <option value="">— tanpa kategori —</option>
            @foreach (App\Models\Domain::KATEGORI_PEMASUKAN as $kode => $label)
              <option value="{{ $kode }}" @selected(old('kategori_pemasukan') === $kode)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>Kategori pengeluaran
          <select name="kategori_pengeluaran">
            <option value="">— tanpa kategori —</option>
            @foreach (App\Models\Domain::KATEGORI_PENGELUARAN as $kode => $label)
              <option value="{{ $kode }}" @selected(old('kategori_pengeluaran') === $kode)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>Rincian pemasukan <span class="bantuan">(opsional)</span>
          <input type="text" name="rincian_pemasukan" maxlength="200" placeholder="Penjualan pupuk 12 sak">
        </label>
        <label>Rincian pengeluaran <span class="bantuan">(opsional)</span>
          <input type="text" name="rincian_pengeluaran" maxlength="200" placeholder="Transport pengiriman">
        </label>
        <label>Catatan <span class="bantuan">(opsional)</span>
          <input type="text" name="catatan" maxlength="300" placeholder="Gudang tutup 3 hari">
        </label>
        {{-- `lebar-penuh`: baris tombol jangan berbagi baris grid dengan isian.
             Tanpa ini tingginya ikut isian tetangganya dan tombol "Simpan
             Catatan" melar 40px -> 72px di desktop (terukur). --}}
        <div class="tombol-baris lebar-penuh">
          <button class="tombol utama" type="submit">Simpan Catatan</button>
        </div>
      </form>
      {{-- Keterangan perilaku: satu baris utuh di bawah baris tombol. Dulu ia
           menyempil di sebelah tombol, dan .bantuan (margin-top .35rem) di
           dalam .tombol-baris (align-items: center) membuat baris tombol melar
           40px -> 59px tanpa alasan. --}}
      <p class="bantuan">Tanggal yang sudah tercatat akan <b>diperbarui</b> —
        tidak menambah baris baru. Angka hari itu langsung ikut dihitung di rekap bawah.</p>
    </section>
    @elseif ($pengelola && $rekap)
      <p class="pesan ingat">
        Mode <b>gabungan</b>: formulir catat tidak ditampilkan karena tidak jelas
        koperasi mana yang dicatat. Pilih satu koperasi di kotak pencarian atas
        untuk mencatat, atau perbaiki baris mana pun langsung dari tabel di bawah.
      </p>
    @endif

    {{-- Tabel kas memakai LEBAR PENUH. Sebelumnya ia berbagi baris dengan Rekap
         (1.7fr : 1fr), sehingga tabel berhenti di 648px dari 1117px — sisa lebar
         kosong dan kolom Aksi tercekik 22px. Rekap dipindah ke bawah tabel;
         `.dua-kolom` tidak dipakai lagi di halaman ini. --}}
    <div>
      {{-- ============================================================ Buku Kas --}}
      <div>
        <section class="blok" id="buku">
          <h2>Buku Kas
            <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }} catatan</small>
          </h2>

          <form class="saring" method="get" action="{{ route('keuangan') }}">
            <input type="hidden" name="periode" value="{{ $periode }}">
            <input type="hidden" name="acuan" value="{{ $acuan }}">
            @if (request('koperasi'))<input type="hidden" name="koperasi" value="{{ request('koperasi') }}">@endif
            <label class="sr" for="q">Cari catatan</label>
            <input id="q" name="q" value="{{ $q }}" placeholder="Cari rincian, catatan, atau nomor invoice…">
            <select name="jenis" aria-label="Saring asal catatan">
              <option value="">Semua asal</option>
              <option value="manual" @selected($jenis === 'manual')>Dicatat manual</option>
              <option value="otomatis" @selected($jenis === 'otomatis')>Otomatis dari pesanan</option>
            </select>
            <select name="kategori" aria-label="Saring kategori kas">
              <option value="">Semua kategori</option>
              <optgroup label="Pemasukan">
                @foreach (App\Models\Domain::KATEGORI_PEMASUKAN as $kode => $label)
                  <option value="{{ $kode }}" @selected($kategori === $kode)>{{ $label }}</option>
                @endforeach
              </optgroup>
              <optgroup label="Pengeluaran">
                @foreach (App\Models\Domain::KATEGORI_PENGELUARAN as $kode => $label)
                  <option value="{{ $kode }}" @selected($kategori === $kode)>{{ $label }}</option>
                @endforeach
              </optgroup>
            </select>
            <select name="lingkup" aria-label="Saring lingkup tanggal">
              <option value="periode" @selected($lingkup === 'periode')>Periode ini</option>
              <option value="semua" @selected($lingkup === 'semua')>Seluruh riwayat</option>
            </select>
            <button class="tombol samar" type="submit">Filter</button>
            @if ($q !== '' || $jenis !== '' || $kategori !== '' || $lingkup === 'semua')
              <a class="tombol samar" href="{{ route('keuangan', ['periode' => $periode, 'acuan' => $acuan, 'koperasi' => request('koperasi')]) }}">Bersihkan</a>
            @endif
          </form>

          <div class="tabel-bungkus">
          <table>
            <thead><tr>
              <th>Tanggal</th>
              {{-- Mode agregat memuat catatan BANYAK koperasi; tanpa kolom ini
                   barisnya tak bisa ditelusuri ke koperasi mana. --}}
              @if ($agregat ?? false)<th>Koperasi</th>@endif
              <th>Uraian</th>
              <th class="angka-kanan">Masuk</th><th class="angka-kanan">Keluar</th>
              <th class="angka-kanan">Selisih</th><th class="kolom-aksi">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse ($buku as $h)
              <tr>
                <td data-l="Tanggal">
                  <span class="judul-sel">{{ tanggal_id($h->tanggal) }}</span>
                  @if ($h->kanal !== '')
                    <small><span class="lencana">{{ App\Models\Saldo::LABEL_KANAL[$h->kanal] ?? $h->kanal }}</span></small>
                  @endif
                </td>
                @if ($agregat ?? false)
                  <td data-l="Koperasi"><span class="judul-sel">{{ $h->nama_koperasi ?? '—' }}</span>
                    @if ($h->kode_koperasi)<small class="pudar">{{ $h->kode_koperasi }}</small>@endif</td>
                @endif
                <td data-l="Uraian">
                  @php
                    $katM = App\Models\Domain::labelKategori('masuk', $h->kategori_pemasukan);
                    $katK = App\Models\Domain::labelKategori('keluar', $h->kategori_pengeluaran);
                  @endphp
                  @if ($katM || $katK)
                    <span class="kwitansi">
                      @if ($katM)<span class="lencana s-DISETUJUI">{{ $katM }}</span>@endif
                      @if ($katK)<span class="lencana s-DITOLAK">{{ $katK }}</span>@endif
                    </span>
                  @endif
                  @if ($h->rincian_pemasukan)<span class="kwitansi">masuk: {{ $h->rincian_pemasukan }}</span>@endif
                  @if ($h->rincian_pengeluaran)<span class="kwitansi">keluar: {{ $h->rincian_pengeluaran }}</span>@endif
                  @if ($h->catatan)<small class="pudar">{{ $h->catatan }}</small>@endif
                  @if ($h->ref)<small class="pudar">Rujukan: <code>{{ $h->ref }}</code></small>@endif
                  @if ($h->rincian_pemasukan === '' && $h->rincian_pengeluaran === '' && $h->catatan === '' && $h->ref === '' && ! $katM && ! $katK)
                    <small class="pudar">Tanpa rincian.</small>
                  @endif
                </td>
                <td data-l="Masuk" class="angka-kanan t-hijau">{{ $h->pemasukan ? $rp($h->pemasukan) : '—' }}</td>
                <td data-l="Keluar" class="angka-kanan t-merah">{{ $h->pengeluaran ? $rp($h->pengeluaran) : '—' }}</td>
                <td data-l="Selisih" class="angka-kanan"><b class="{{ $h->selisih() >= 0 ? 't-hijau' : 't-merah' }}">{{ $rp($h->selisih()) }}</b></td>
                {{-- SATU tombol "Ubah" per baris; rinciannya di modal pop up.
                     Sebelumnya <details> membuka form 420px TERTAMBAT di dalam
                     sel (sel aksi hanya 137px) sehingga tampak terpotong. Baris
                     otomatis (punya `ref`) tetap dapat tombol, tetapi formnya
                     menolak perubahan angka — alasannya ditampilkan di modal. --}}
                <td class="kolom-aksi">
                  <button class="tombol samar mini" type="button"
                          data-keuangan="{{ $h->id }}"
                          data-koperasi-id="{{ $h->koperasi_id }}"
                          data-tanggal="{{ $h->tanggal }}"
                          data-pemasukan="{{ $h->pemasukan }}"
                          data-pengeluaran="{{ $h->pengeluaran }}"
                          data-masuk="{{ $h->rincian_pemasukan }}"
                          data-keluar="{{ $h->rincian_pengeluaran }}"
                          data-catatan="{{ $h->catatan }}"
                          data-kat-masuk="{{ $h->kategori_pemasukan }}"
                          data-kat-keluar="{{ $h->kategori_pengeluaran }}"
                          data-ref="{{ $h->ref }}">Ubah</button>
                  {{-- Paritas tanpa JavaScript: modal butuh skrip, jadi jalur
                       lama (form sebaris) tetap disediakan — dan HANYA dirender
                       saat JS mati. Tanpa ini, mematikan JS membuat catatan
                       tidak bisa diubah sama sekali. --}}
                  <noscript>
                    <details class="ubah">
                      <summary class="tombol samar mini">Ubah</summary>
                      <form method="post" action="{{ route('keuangan.simpan') }}">
                        @csrf
                        <input type="hidden" name="tanggal" value="{{ $h->tanggal }}">
                        <input type="hidden" name="koperasi" value="{{ request('koperasi') }}">
                        <label>Pemasukan <input type="number" name="pemasukan" min="0" step="1" value="{{ $h->pemasukan }}"></label>
                        <label>Pengeluaran <input type="number" name="pengeluaran" min="0" step="1" value="{{ $h->pengeluaran }}"></label>
                        <label>Kategori masuk
                          <select name="kategori_pemasukan">
                            <option value="">— tanpa kategori —</option>
                            @foreach (App\Models\Domain::KATEGORI_PEMASUKAN as $kode => $label)
                              <option value="{{ $kode }}" @selected($h->kategori_pemasukan === $kode)>{{ $label }}</option>
                            @endforeach
                          </select>
                        </label>
                        <label>Kategori keluar
                          <select name="kategori_pengeluaran">
                            <option value="">— tanpa kategori —</option>
                            @foreach (App\Models\Domain::KATEGORI_PENGELUARAN as $kode => $label)
                              <option value="{{ $kode }}" @selected($h->kategori_pengeluaran === $kode)>{{ $label }}</option>
                            @endforeach
                          </select>
                        </label>
                        <label>Rincian masuk <input type="text" name="rincian_pemasukan" maxlength="200" value="{{ $h->rincian_pemasukan }}"></label>
                        <label>Rincian keluar <input type="text" name="rincian_pengeluaran" maxlength="200" value="{{ $h->rincian_pengeluaran }}"></label>
                        <label>Catatan <input type="text" name="catatan" maxlength="300" value="{{ $h->catatan }}"></label>
                        <button class="tombol utama mini" type="submit">Simpan</button>
                      </form>
                    </details>
                  </noscript>
                </td>
              </tr>
            @empty
              <tr><td colspan="{{ ($agregat ?? false) ? 7 : 6 }}" class="kosong">
                @if ($q !== '' || $jenis !== '')
                  Tidak ada catatan yang cocok dengan saringan ini.
                @else
                  Belum ada catatan pada {{ $lingkup === 'periode' ? 'periode ini' : 'buku kas' }}.
                @endif
              </td></tr>
            @endforelse
            </tbody>
          </table>
          </div>
          @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
        </section>
      </div>

      {{-- ============================================================== Rekap --}}
      <div>
        <section class="blok" id="rekap">
          {{-- (diisi di bawah) --}}
          <h2>Rekap
            <small class="pudar">{{ tanggal_id($rekap['awal']) }} – {{ tanggal_id($rekap['akhir']) }}</small>
          </h2>

          <div class="kartu-baris">
            @foreach (\App\Http\Controllers\KeuanganController::PERIODE as $k => $label)
              <a class="kartu angka {{ $periode === $k ? 'aksi-hijau ini' : '' }}"
                 href="{{ route('keuangan', ['periode' => $k, 'acuan' => $acuan, 'koperasi' => request('koperasi')]) }}">
                <small>{{ $label }}</small>
                <b class="{{ ($neto[$k] ?? 0) >= 0 ? 't-hijau' : 't-merah' }}">{{ $rp($neto[$k] ?? 0) }}</b>
              </a>
            @endforeach
          </div>

          <p class="bantuan">Selisih {{ strtolower(\App\Models\Keuangan::labelPeriode($periode)) }} dari
            {{ $rekap['hari_terisi'] }} hari tercatat. Pindah periode tidak menghitung ulang buku — angkanya selalu
            diturunkan dari catatan harian.</p>

          @if ($aliran->isNotEmpty())
            {{-- Arus kas: satu batang per hari (per bulan bila tahunan). Batang naik
                 = kas bertambah, turun = berkurang. Tanpa JavaScript. --}}
            <div class="arus" role="img"
                 aria-label="Arus kas {{ \App\Models\Keuangan::labelPeriode($periode) }}: {{ $rekap['hari_terisi'] }} hari tercatat">
              @foreach ($aliran as $a)
                <span class="arus-batang {{ $a['net'] >= 0 ? 'naik' : 'turun' }}"
                      title="{{ $a['label'] }} · {{ $rp($a['net']) }}"><i style="height:{{ max(2, (int) round(abs($a['net']) / $maks * 46)) }}%"></i></span>
              @endforeach
            </div>
            <div class="arus-kaki">
              <span>{{ $aliran->first()['label'] }}</span>
              <span>{{ $rp($maks) }} skala</span>
              <span>{{ $aliran->last()['label'] }}</span>
            </div>
          @endif

          @if ($periode === 'tahunan')
            <div class="tabel-bungkus">
            <table>
              <thead><tr><th>Bulan</th><th class="angka-kanan">Masuk</th><th class="angka-kanan">Keluar</th><th class="angka-kanan">Neto</th></tr></thead>
              <tbody>
              @forelse ($bulan as $bln => $v)
                <tr>
                  <td data-l="Bulan"><span class="judul-sel">{{ tanggal_id($bln.'-01', 'F Y') }}</span></td>
                  <td data-l="Masuk" class="angka-kanan t-hijau">{{ $rp($v['pemasukan']) }}</td>
                  <td data-l="Keluar" class="angka-kanan t-merah">{{ $rp($v['pengeluaran']) }}</td>
                  <td data-l="Neto" class="angka-kanan"><b class="{{ ($v['pemasukan'] - $v['pengeluaran']) >= 0 ? 't-hijau' : 't-merah' }}">{{ $rp($v['pemasukan'] - $v['pengeluaran']) }}</b></td>
                </tr>
              @empty
                <tr><td colspan="4" class="kosong">Belum ada catatan pada tahun ini.</td></tr>
              @endforelse
              </tbody>
            </table>
            </div>
          @endif

          {{-- ================================================ Rekap kategori
               "Uangnya masuk dari mana, keluar untuk apa" — pertanyaan pertama
               setiap laporan keuangan, dan satu-satunya bagian halaman ini yang
               tidak bisa dijawab total masuk/keluar saja. Sumbernya baris yang
               sama dengan tabel di atas, jadi jumlah per kategori selalu sama
               dengan total periode (gate memeriksa invarian itu). --}}
          @if (! empty($kategoriRekap['masuk']) || ! empty($kategoriRekap['keluar']))
            <h3 class="petak-judul">Asal &amp; penggunaan kas</h3>
            <div class="kategori-kas">
              @foreach (['masuk' => ['Pemasukan', 't-hijau', $rekap['pemasukan']],
                         'keluar' => ['Pengeluaran', 't-merah', $rekap['pengeluaran']]] as $sisi => $kepala)
                @php [$judul, $warna, $total] = $kepala; @endphp
                <div class="kategori-sisi">
                  <b class="{{ $warna }}">{{ $judul }} · {{ $rp($total) }}</b>
                  @forelse ($kategoriRekap[$sisi] as $r)
                    @php $persen = $total > 0 ? $r['nilai'] / $total * 100 : 0; @endphp
                    <div class="kategori-baris">
                      <span class="kategori-nama">{{ $r['label'] }}</span>
                      <span class="kategori-angka">{{ $rp($r['nilai']) }}</span>
                      <span class="kategori-persen pudar">{{ number_format($persen, 1, ',', '.') }}%</span>
                      <span class="kategori-batang"><i style="width:{{ max(1, round($persen)) }}%"></i></span>
                    </div>
                  @empty
                    <p class="bantuan">Tidak ada {{ strtolower($judul) }} pada periode ini.</p>
                  @endforelse
                </div>
              @endforeach
            </div>
          @endif

          {{-- Periode lain: menggeser tanggal acuan. `acuan` menentukan minggu/bulan/
               tahun mana yang ditampilkan, bukan tanggal catatannya. --}}
          <form class="saring" method="get" action="{{ route('keuangan') }}">
            <input type="hidden" name="periode" value="{{ $periode }}">
            @if (request('koperasi'))<input type="hidden" name="koperasi" value="{{ request('koperasi') }}">@endif
            <label class="sr" for="acuan">Tanggal acuan</label>
            <input id="acuan" type="date" name="acuan" value="{{ $acuan }}">
            <button class="tombol samar" type="submit">Lihat Periode Lain</button>
          </form>
        </section>
      </div>
    </div>
  </div>
@endif

{{-- Modal "Ubah catatan". WAJIB di luar wadah [data-isi] (#daftar): hub.js
     menukar ISI wadah itu setiap periode/saringan/paging, dan <dialog> di
     dalamnya akan terbuang — tombolnya lalu tampak "tidak bereaksi".
     Satu <dialog> untuk seluruh tabel; isinya diisi dari data-* tombol. --}}
{{-- Dirender untuk SEMUA peran. Dulu hanya pengelola: manajer mendapat tombol
     "Ubah" di tabel tetapi <dialog>-nya tidak pernah dirender, sehingga tombolnya
     mati tanpa pesan apa pun (hanya jalur <noscript> yang bekerja, dan itu tak
     terlihat saat JS hidup). Wewenang menyentuh koperasi tetap dijaga server
     lewat koperasi_id + koperasiQuery(). --}}
<dialog class="modal" id="keu-modal" aria-labelledby="keu-modal-judul">
  <form method="post" action="{{ route('keuangan.simpan') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="keu-modal-judul">Ubah Catatan <span id="keu-modal-tanggal" class="pudar"></span></h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="pesan ingat" id="keu-modal-otomatis" hidden>
        Baris ini terbentuk otomatis dari pesanan toko <b id="keu-modal-ref"></b>.
        Angka masuk/keluar milik pesanan itu dan tidak bisa diubah di sini —
        batalkan pesanannya bila memang keliru. Rincian & kategori tetap boleh
        diperbaiki.
      </p>
      <input type="hidden" name="tanggal" id="keu-modal-input-tanggal">
      {{-- Koperasi baris ini, bukan "koperasi yang sedang dilihat": di mode
           agregat keduanya berbeda. --}}
      <input type="hidden" name="koperasi_id" id="keu-modal-koperasi">
      <input type="hidden" name="koperasi" value="{{ request('koperasi') }}">
      <label>Pemasukan (Rp)
        <input type="number" name="pemasukan" id="keu-modal-pemasukan" min="0" max="999999999999" step="1" required>
      </label>
      <label>Pengeluaran (Rp)
        <input type="number" name="pengeluaran" id="keu-modal-pengeluaran" min="0" max="999999999999" step="1" required>
      </label>
      <label>Kategori pemasukan
        <select name="kategori_pemasukan" id="keu-modal-kat-masuk">
          <option value="">— tanpa kategori —</option>
          @foreach (App\Models\Domain::KATEGORI_PEMASUKAN as $kode => $label)
            <option value="{{ $kode }}">{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label>Kategori pengeluaran
        <select name="kategori_pengeluaran" id="keu-modal-kat-keluar">
          <option value="">— tanpa kategori —</option>
          @foreach (App\Models\Domain::KATEGORI_PENGELUARAN as $kode => $label)
            <option value="{{ $kode }}">{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label>Rincian pemasukan <span class="bantuan">(opsional)</span>
        <input type="text" name="rincian_pemasukan" id="keu-modal-masuk" maxlength="200">
      </label>
      <label>Rincian pengeluaran <span class="bantuan">(opsional)</span>
        <input type="text" name="rincian_pengeluaran" id="keu-modal-keluar" maxlength="200">
      </label>
      <label>Catatan <span class="bantuan">(opsional)</span>
        <input type="text" name="catatan" id="keu-modal-catatan" maxlength="300">
      </label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan</button>
    </footer>
  </form>
</dialog>

{{-- Pencarian KDMP: kotak hasil mengambang + pilihan baris tanpa muat ulang.
     Tanpa JS tetap jalan — ketik lalu Enter mengirim form biasa. --}}
{{-- Pemilih KDMP: berkas BERSAMA dengan /workspace (kontraknya lewat data-*),
     supaya dua halaman tidak memelihara dua penangan yang bisa menyimpang. --}}
<script src="/js/hub-pemilih-kop.js?v={{ filemtime(public_path('js/hub-pemilih-kop.js')) }}" defer></script>
<script src="/js/hub-keuangan.js?v={{ filemtime(public_path('js/hub-keuangan.js')) }}" defer></script>
@endsection

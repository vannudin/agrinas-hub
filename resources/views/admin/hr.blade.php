@extends('layouts.utama')

@section('judul', 'SDM & Kepegawaian')

@section('isi')
@php
  use App\Models\Domain;
  use App\Models\SdmPegawai;
  // Bulan berjalan untuk nilai awal periode penilaian.
  $bulanIni = now('Asia/Jakarta')->format('Y-m');
  // Kelas lencana dari satu peta (Domain::KELAS_SDM) supaya tabel, modal, dan
  // kartu ringkas memakai warna yang sama.
  $kelas = fn (string $s) => Domain::KELAS_SDM[$s] ?? 's-BARU';
@endphp

{{-- Wadah ber-`data-isi`: pencarian, saringan, dan pindah halaman menukar seluruh
     isi di dalamnya (hub.js). Tanpa JavaScript semuanya tetap form GET & tautan
     biasa — pola yang sama dengan /admin/akun. --}}
<div id="hr" data-isi="{{ route('hr') }}" data-params="q,peran,status,data,halaman">
  <header class="kepala">
    <div>
      <h1>SDM &amp; Kepegawaian</h1>
      <p class="tenang">
        Satu meja untuk identitas, penempatan, kontrak, kehadiran, siklus kerja,
        penilaian kinerja berperiode, rujukan slip gaji, dan rekrutmen
        <b>{{ number_format($total, 0, ',', '.') }} pegawai</b>. Datanya digabung
        dari akun Hub, profil SIMKOPDES, kontrak pendamping, dan lembar SDM.
      </p>
    </div>
    <div class="tombol-baris">
      <a class="tombol samar" href="{{ route('kontrak') }}">Kontrak Pendamping</a>
      <a class="tombol samar" href="{{ route('admin.akun') }}">Semua Akun</a>
    </div>
  </header>

  {{-- Tab meja. Tanpa JavaScript ini tautan biasa; dengan hub.js [?] tetap
       dimuat ulang karena tab menukar SELURUH isi wadah, bukan hanya tabel. --}}
  <nav class="pil-baris" aria-label="Bagian meja SDM">
    @foreach (\App\Http\Controllers\HrdController::TAB as $tabKunci => [$label, $ket])
      <a class="pil{{ $tab === $tabKunci ? ' ini' : '' }}"
         href="{{ route('hr', array_filter(['tab' => $tabKunci, 'q' => $q, 'peran' => $peran, 'status' => $status, 'data' => $data])) }}"
         title="{{ $ket }}"{!! $tab === $tabKunci ? ' aria-current=page' : '' !!}>{{ $label }}</a>
    @endforeach
  </nav>

  @if ($tab === 'pegawai')
  {{-- ======================= 1. PEGAWAI ======================= --}}
  <div class="kartu-baris kartu-status">
    <a class="kartu angka {{ $peran === Domain::PERAN_MANAJER ? 'aksi-hijau' : '' }}"
       href="{{ route('hr', ['tab' => 'pegawai', 'peran' => Domain::PERAN_MANAJER]) }}">
      <small>Manajer Koperasi</small><b>{{ $stat['manajer'] }}</b>
    </a>
    <a class="kartu angka {{ $peran === Domain::PERAN_PIC ? 'aksi-hijau' : '' }}"
       href="{{ route('hr', ['tab' => 'pegawai', 'peran' => Domain::PERAN_PIC]) }}">
      <small>PIC Wilayah</small><b>{{ $stat['pic'] }}</b>
    </a>
    <a class="kartu angka {{ $peran === Domain::PERAN_AGRINAS ? 'aksi-hijau' : '' }}"
       href="{{ route('hr', ['tab' => 'pegawai', 'peran' => Domain::PERAN_AGRINAS]) }}">
      <small>Admin Pusat</small><b>{{ $stat['admin'] }}</b>
    </a>
    {{-- Kartu ini menyorot KELENGKAPAN DATA, bukan peran: jumlah pegawai yang
         tanggal lahirnya belum ada — belum pernah tersalin dari SIMKOPDES. --}}
    <a class="kartu angka {{ $data === 'belum' ? 'aksi-hijau' : '' }}"
       href="{{ route('hr', ['tab' => 'pegawai', 'data' => 'belum']) }}">
      <small>Data belum lengkap</small><b>{{ $tanpaData }}</b>
    </a>
    <a class="kartu angka {{ $peran === '' && $data === '' && $status === '' && $q === '' ? 'aksi-hijau' : '' }}"
       href="{{ route('hr', ['tab' => 'pegawai']) }}">
      <small>Semua pegawai</small><b>{{ number_format(array_sum($stat), 0, ',', '.') }}</b>
    </a>
  </div>

  <section class="blok">
    <h2>Daftar Pegawai
      <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }}</small>
    </h2>

    <form class="saring" method="get" action="{{ route('hr') }}">
      {{-- TANPA <input name="tab">: hub.js menukar isi wadah dari respons yang
           bentuknya sama, jadi tab harus dipertahankan lewat jalur — kalau
           diikutkan, parameter tab menempel di URL dan pindah tab berikutnya
           mengabaikan tab yang diklik. --}}
      {{-- WAJIB: hub.js membangun URL dari action + FormData dan MEMBUANG
           kunci halaman, jadi `tab` harus ikut di form — tanpa ini menyaring
           dari tab Kehadiran/Penggajian melempar pengguna ke tab Pegawai. --}}
      <input type="hidden" name="tab" value="pegawai">
      <label class="cari">
        <span class="sr">Cari pegawai</span>
        <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
        </svg>
        <input type="search" name="q" value="{{ $q }}" autocomplete="off"
               placeholder="Cari nama, email, koperasi, atau telepon…">
      </label>
      <label class="pilih">
        <select name="peran" aria-label="Saring peran">
          <option value="">Semua peran</option>
          @foreach (Domain::LABEL_PERAN as $kode => $nama)
            <option value="{{ $kode }}" @selected($peran === $kode)>{{ $nama }}</option>
          @endforeach
        </select>
      </label>
      <label class="pilih">
        <select name="data" aria-label="Saring kelengkapan data">
          <option value="">Semua data</option>
          <option value="lengkap" @selected($data === 'lengkap')>Data lengkap</option>
          <option value="belum" @selected($data === 'belum')>Belum lengkap ({{ $tanpaData }})</option>
        </select>
      </label>
      <a class="tombol samar bersih-saring" href="{{ route('hr', ['tab' => 'pegawai']) }}"
         @if ($q === '' && $peran === '' && $data === '') hidden @endif>Bersihkan</a>
      <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
    </form>

    <div class="tabel-bungkus">
    <table>
      <thead><tr>
        <th>Pegawai</th><th>Peran &amp; Status</th><th>Koperasi / Wilayah</th><th>Kontak</th>
        <th>Kontrak</th><th class="kolom-aksi">Tindakan</th>
      </tr></thead>
      <tbody>
      @forelse ($pegawai as $p)
        @php
          $k = $kontrak[$p->id] ?? null;
          $statusKerja = $p->aktif ? (in_array($p->sdm_status, SdmPegawai::STATUS, true) ? $p->sdm_status : 'AKTIF') : 'NONAKTIF';
          $rincian = [
            'nama' => $p->nama, 'email' => $p->email,
            'peran' => Domain::LABEL_PERAN[$p->peran] ?? $p->peran,
            'wilayah' => $p->pic() ? \App\Support\Wilayah::label((string) $p->wilayah) : '',
            'koperasi' => (string) ($p->nama_koperasi ?? ''),
            'telepon' => (string) ($p->telepon ?? ''),
            'kabupaten' => (string) ($p->kabupaten ?? ''),
            'lahir' => tanggal_id($p->tgl_lahir ?? null),
            'gender' => (string) ($p->gender ?? ''),
            'sumber_peran' => (string) ($p->peran_simkopdes ?? ''),
            'dibuat' => waktu_id($p->dibuat),
            'masuk' => $p->masuk_terakhir ? waktu_id($p->masuk_terakhir) : '',
            'aktif' => $p->aktif ? 'Aktif' : 'Nonaktif',
            'periode' => $k ? ($k->periode_program ?: 'Periode '.$k->periode_id) : '',
            'penempatan' => $k ? $k->penempatan_nama : '',
            'kontrak' => $k ? $k->kontrak_nomor : '',
            'kontrak_status' => $k ? ($k->kontrak_label ?: $k->kontrak_status) : '',
            'kontrak_ttd' => $k && $k->kontrak_ttd ? tanggal_id($k->kontrak_ttd) : '',
            'kontrak_tautan' => $k ? (string) $k->kontrak_tautan : '',
            'presensi' => '', // diisi di tab Kehadiran
          ];
        @endphp
        <tr>
          <td data-l="Pegawai">
            <span class="judul-sel">{{ $p->nama }}</span>
            <small class="pudar pecah">{{ $p->email }}</small>
          </td>
          <td data-l="Peran &amp; Status">
            <span class="lencana {{ $p->peran === 'PIC' ? 's-DIAJUKAN' : ($p->peran === 'HR' ? 's-DIPROSES' : '') }}">
              {{ Domain::LABEL_PERAN[$p->peran] ?? $p->peran }}
            </span>
            {{-- Status kerja menyatu dengan peran: keduanya sifat ORANG yang sama,
                 dan kolom terpisah membuat tabel tak muat di tablet (terukur
                 880px untuk 731px ruang). --}}
            <span class="lencana {{ $kelas($statusKerja) }}">{{ SdmPegawai::STATUS_LABEL[$statusKerja] }}</span>
          </td>
          <td data-l="Koperasi / Wilayah">
            @if ($p->pic())
              {{ \App\Support\Wilayah::pendek((string) $p->wilayah) }}
            @else
              {{ $p->nama_koperasi ?: '—' }}@if ($p->kode_koperasi) <small class="pudar">{{ $p->kode_koperasi }}</small>@endif
            @endif
          </td>
          <td data-l="Kontak">
            {{ $p->telepon ?: '—' }}
            @if ($p->kabupaten)<small class="pudar pecah">{{ $p->kabupaten }}</small>@endif
          </td>
          <td data-l="Kontrak">
            @if ($k)
              <span class="lencana {{ $k->kontrak_status === 'SIGNED' ? 's-DISETUJUI' : 's-DIKERJAKAN' }}">
                {{ $k->kontrak_label ?: $k->kontrak_status }}</span>
              <small class="pudar pecah">{{ $k->kontrak_nomor ?: $k->kontrak_komitmen }}</small>
              <a class="peta-tautan" href="{{ $k->kontrak_tautan }}" target="_blank" rel="noopener">Buka tanda tangan</a>
            @elseif ($p->manajerKoperasi())
              <span class="pudar">Belum tersalin</span>
            @else
              <span class="pudar">—</span>
            @endif
          </td>
          <td class="kolom-aksi">
            <button class="tombol samar mini" type="button" data-buka="#hr-rincian"
                    data-judul-dialog="{{ $p->nama }}"
                    data-awal="{{ json_encode($rincian, JSON_UNESCAPED_UNICODE) }}">Lihat</button>
            <button class="tombol samar mini" type="button" data-buka="#hr-modal"
                    data-judul-dialog="Kepegawaian — {{ $p->nama }}"
                    data-lengkapi="{{ json_encode([
                      'aksi' => route('hr.sdm', $p->id),
                      'status' => $statusKerja, 'tanggal_awal' => (string) $p->tanggal_awal,
                      'tanggal_akhir' => (string) $p->tanggal_akhir,
                      'jabatan' => (string) $p->jabatan, 'unit' => (string) $p->unit,
                      'atasan_id' => (string) ($p->atasan_id ?? 0),
                      'catatan' => (string) ($p->sdm_catatan ?? ''),
                      'orang' => $p->nama,
                    ], JSON_UNESCAPED_UNICODE) }}">Kepegawaian</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">
          @if ($q !== '' || $peran !== '' || $data !== '' || $status !== '')
            Tidak ada pegawai yang cocok dengan saringan ini.
          @else
            Belum ada pegawai.
          @endif
        </td></tr>
      @endforelse
      </tbody>
    </table>
    </div>

    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
  </section>
  @endif

  @if ($tab === 'kehadiran')
  {{-- ============= 2. KEHADIRAN & KINERJA BERPERIODE ============= --}}
  <div class="kartu-baris kartu-status">
    <div class="kartu angka"><small>Pegawai Aktif</small><b>{{ $sdmStat['aktif'] }}</b></div>
    <div class="kartu angka"><small>Cuti</small><b>{{ $sdmStat['cuti'] }}</b></div>
    <div class="kartu angka"><small>Nonaktif</small><b>{{ $sdmStat['nonaktif'] }}</b></div>
    <a class="kartu angka {{ $status === 'AKTIF' && $data === '' ? 'aksi-hijau' : '' }}"
       href="{{ route('hr', ['tab' => 'kehadiran', 'status' => 'AKTIF']) }}">
      <small>Tanpa Lembar</small><b>{{ $tanpaLembar }}</b>
    </a>
    <a class="kartu angka aksi-kuning" href="{{ route('hr', ['tab' => 'kehadiran']) }}">
      <small>Belum Dinilai</small><b>{{ $belumDinilai }}</b>
    </a>
  </div>

  <section class="blok">
    <h2>Rekap Kehadiran &amp; Penilaian
      <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }}</small>
    </h2>
    <p class="bantuan">
      Kehadiran dibaca dari <b>absensi resmi SIMKOPDES</b> dan presensi Hub yang sudah
      menjadi catatan resmi. Penilaian diisi HR lewat tombol <b>Nilai</b> — angkanya
      tercatat bersama <b>periode</b>, <b>catatan</b>, dan <b>siapa penilainya</b>, jadi
      tidak ada nilai tanpa jejak. Hub tidak mengarang bobot/kuesioner; yang disimpan
      adalah hasil penilaian yang sudah diputuskan.
    </p>

    <form class="saring" method="get" action="{{ route('hr') }}">
      <label class="cari">
        <span class="sr">Cari pegawai</span>
        <input type="search" name="q" value="{{ $q }}" autocomplete="off" placeholder="Cari nama atau email…">
      </label>
      {{-- Pindah daftar = kembali ke halaman 1 (dibuang tiap saring oleh hub.js). --}}
      <input type="hidden" name="halaman" value="1">
      <input type="hidden" name="tab" value="kehadiran">
      <label class="pilih">
        <select name="status" aria-label="Saring status kerja">
          <option value="">Semua status</option>
          @foreach (SdmPegawai::STATUS_LABEL as $kode => $nama)
            <option value="{{ $kode }}" @selected($status === $kode)>{{ $nama }}</option>
          @endforeach
        </select>
      </label>
      <a class="tombol samar bersih-saring" href="{{ route('hr', ['tab' => 'kehadiran']) }}"
         @if ($q === '' && $status === '') hidden @endif>Bersihkan</a>
      <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
    </form>

    <div class="tabel-bungkus">
    <table>
      <thead><tr>
        <th>Pegawai</th><th>Status Kerja</th><th>Masa Kerja</th><th>Atasan</th>
        <th>Kehadiran</th><th>Nilai Terakhir</th><th class="kolom-aksi">Tindakan</th>
      </tr></thead>
      <tbody>
      @forelse ($pegawai as $p)
        @php
          $a = $absen[$p->id] ?? null;
          $statusKerja = $p->aktif ? (in_array($p->sdm_status, SdmPegawai::STATUS, true) ? $p->sdm_status : 'AKTIF') : 'NONAKTIF';
          $masaKerja = $p->tanggal_awal ? tanggal_id($p->tanggal_awal) . ($p->tanggal_akhir ? ' – '.tanggal_id($p->tanggal_akhir) : ' – sekarang') : '';
          $namaAtasan = $p->atasan_id ? ($atasan[$p->atasan_id] ?? '') : '';
          $nilaiUbah = [
            'aksi' => route('hr.nilai', $p->id), 'orang' => $p->nama,
            'nilai' => $p->nilai !== null ? (string) $p->nilai : '',
            'periode_nilai' => $p->periode_nilai ?: $bulanIni,
            'catatan_nilai' => (string) $p->catatan_nilai,
          ];
        @endphp
        <tr>
          <td data-l="Pegawai">
            <span class="judul-sel">{{ $p->nama }}</span>
            <small class="pudar pecah">{{ $p->email }}</small>
          </td>
          <td data-l="Status Kerja"><span class="lencana {{ $kelas($statusKerja) }}">{{ SdmPegawai::STATUS_LABEL[$statusKerja] }}</span></td>
          <td data-l="Masa Kerja">{{ $masaKerja ?: '—' }}</td>
          <td data-l="Atasan">{{ $namaAtasan ?: '—' }}</td>
          <td data-l="Kehadiran">
            @if ($a)
              {{ $a->jumlah }} hari <small class="pudar">terakhir {{ tanggal_id($a->terakhir) }}</small>
            @else
              <span class="pudar">Belum ada catatan</span>
            @endif
          </td>
          <td data-l="Nilai Terakhir">
            @if ($p->nilai !== null)
              <b>{{ $p->nilai }}</b> <small class="pudar">{{ $p->periode_nilai }} · {{ tanggal_id($p->nilai_pada) }}</small>
            @else
              <span class="pudar">Belum dinilai</span>
            @endif
          </td>
          <td class="kolom-aksi">
            <button class="tombol samar mini" type="button" data-buka="#hr-nilai"
                    data-judul-dialog="Penilaian — {{ $p->nama }}"
                    data-lengkapi="{{ json_encode($nilaiUbah, JSON_UNESCAPED_UNICODE) }}">Nilai</button>
            <button class="tombol samar mini" type="button" data-buka="#hr-modal"
                    data-judul-dialog="Kepegawaian — {{ $p->nama }}"
                    data-lengkapi="{{ json_encode([
                      'aksi' => route('hr.sdm', $p->id), 'status' => $statusKerja,
                      'tanggal_awal' => (string) $p->tanggal_awal, 'tanggal_akhir' => (string) $p->tanggal_akhir,
                      'jabatan' => (string) $p->jabatan, 'unit' => (string) $p->unit,
                      'atasan_id' => (string) ($p->atasan_id ?? 0),
                      'catatan' => (string) ($p->sdm_catatan ?? ''), 'orang' => $p->nama,
                    ], JSON_UNESCAPED_UNICODE) }}">Siklus</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">Tidak ada pegawai yang cocok dengan saringan ini.</td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
  </section>
  @endif

  @if ($tab === 'pembayaran')
  {{-- ============ 3. PAYROLL: rekening + BPJS, rujukan slip ============ --}}
  <section class="blok">
    <h2>Payroll — Rekening &amp; BPJS
      <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }}</small>
    </h2>
    <p class="bantuan">
      Rekening dan keanggotaan <b>BPJS</b> di bawah ini <b>ikut tersinkron saat pegawai
      masuk lewat SIMKOPDES</b> — jadi HR tidak perlu menanyakannya lagi. <b>Hub tetap
      tidak menyimpan angka gaji satu pun:</b> nominalnya dihitung dan dibayarkan di
      <b>Agrinas HRIS</b>; dari sini hanya nomor slip &amp; nama berkasnya yang dirujuk,
      supaya HR dan pegawai memeriksa slip yang <i>sama</i>.
    </p>
    <div class="kartu-baris kartu-status">
      <a class="kartu angka {{ $tanpaBank === 0 ? 'aksi-hijau' : 'aksi-kuning' }}"
         href="{{ route('hr', ['tab' => 'pembayaran', 'data' => 'payroll']) }}"
         title="Saring pegawai yang rekening atau BPJS-nya belum lengkap">
        <small>Belum Siap Ditransfer</small><b>{{ $tanpaBank + $tanpaBpjs }}</b>
      </a>
      <div class="kartu angka"><small>Siap Payroll</small><b>{{ $siapPayroll }}</b></div>
      <a class="kartu angka" href="{{ route('hr', ['tab' => 'pembayaran']) }}"
         title="Pegawai yang belum punya rujukan slip di Hub">
        <small>Belum Ada Rujukan Slip</small><b>{{ $tanpaSlip }}</b>
      </a>
    </div>

    <div class="tombol-baris">
      <a class="tombol samar" href="{{ route('hr.payroll.csv', array_filter(['q' => $q, 'data' => $data, 'status' => $status])) }}"
         title="Berkas inilah yang dipakai menyiapkan transfer gaji — tanpa satu pun nominal gaji">Ekspor CSV Payroll</a>
      <form method="post" action="{{ route('hr.payroll.tarik') }}">
        @csrf
        <button class="tombol samar" type="submit"
                title="Muat ulang rekening & BPJS dari profil SIMKOPDES yang sudah tersimpan (tanpa memanggil ulang SIMKOPDES)">
          Tarik Ulang dari Profil
        </button>
      </form>
    </div>

    <form class="saring" method="get" action="{{ route('hr') }}">
      <label class="cari">
        <span class="sr">Cari pegawai</span>
        <input type="search" name="q" value="{{ $q }}" autocomplete="off" placeholder="Cari nama atau email…">
      </label>
      <input type="hidden" name="halaman" value="1">
      <input type="hidden" name="tab" value="pembayaran">
      <label class="pilih">
        <select name="data" aria-label="Saring kesiapan payroll">
          <option value="">Semua pegawai</option>
          <option value="payroll" @selected($data === 'payroll')>Belum siap payroll</option>
        </select>
      </label>
      <a class="tombol samar bersih-saring" href="{{ route('hr', ['tab' => 'pembayaran']) }}"
         @if ($q === '' && $data === '') hidden @endif>Bersihkan</a>
      <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
    </form>

    <div class="tabel-bungkus">
    <table>
      <thead><tr>
        <th>Pegawai</th><th>Rekening (Bank)</th><th>BPJS</th><th>Rujukan Slip</th>
        <th>Data Ditarik</th><th class="kolom-aksi">Tindakan</th>
      </tr></thead>
      <tbody>
      @forelse ($pegawai as $p)
        @php
          $statusKerja = $p->aktif ? (in_array($p->sdm_status, SdmPegawai::STATUS, true) ? $p->sdm_status : 'AKTIF') : 'NONAKTIF';
          $slipUbah = [
            'aksi' => route('hr.slip', $p->id), 'orang' => $p->nama,
            'slip_nomor' => (string) $p->slip_nomor, 'slip_berkas' => (string) $p->slip_berkas,
          ];
        @endphp
        <tr>
          <td data-l="Pegawai">
            <span class="judul-sel">{{ $p->nama }}</span>
            <small class="pudar pecah">{{ $p->email }}</small>
            <span class="lencana {{ $kelas($statusKerja) }}">{{ SdmPegawai::STATUS_LABEL[$statusKerja] }}</span>
          </td>
          <td data-l="Rekening">
            @if ($p->bank_nomor)
              <span class="judul-sel">{{ $p->bank_nama ?: 'Bank' }} · {{ $p->bank_nomor }}</span>
              <small class="pudar pecah">{{ $p->bank_pemilik ?: $p->nama }}@if ($p->bank_cabang) · {{ $p->bank_cabang }}@endif</small>
            @else
              <span class="pudar">Belum ada rekening</span>
            @endif
          </td>
          <td data-l="BPJS">
            @if ($p->bpjs_kesehatan || $p->bpjs_bpjs_ketenagakerjaan)
              <small class="pudar">Kesehatan</small> {{ $p->bpjs_kesehatan ?: '—' }}<br>
              <small class="pudar">TK</small> {{ $p->bpjs_bpjs_ketenagakerjaan ?: '—' }}
              @if ($p->bpjs_program)<br><small class="pudar pecah">{{ $p->bpjs_program }}</small>@endif
            @else
              <span class="pudar">Belum ada BPJS</span>
            @endif
          </td>
          <td data-l="Rujukan Slip">{{ $p->slip_nomor ?: '—' }}
            @if ($p->slip_berkas)<br><small class="pudar pecah">{{ $p->slip_berkas }}</small>@endif
          </td>
          <td data-l="Data Ditarik">{{ $p->data_bank_pada ? tanggal_id($p->data_bank_pada) : '—' }}</td>
          <td class="kolom-aksi">
            <button class="tombol samar mini" type="button" data-buka="#hr-slip"
                    data-judul-dialog="Rujukan Slip — {{ $p->nama }}"
                    data-lengkapi="{{ json_encode($slipUbah, JSON_UNESCAPED_UNICODE) }}">Catat</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="kosong">Tidak ada pegawai yang cocok.</td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
    @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
  </section>
  @endif

  @if ($tab === 'masuk')
  {{-- ============ 4. REKRUTMEN, ONBOARDING, OFFBOARDING ============ --}}
  <div class="kartu-baris kartu-status">
    @foreach (\App\Models\RekrutmenPelamar::STATUS_LABEL as $kode => $nama)
      <a class="kartu angka {{ $kode === 'MENUNGGU' ? 'aksi-kuning' : '' }}"
         href="{{ route('hr', ['tab' => 'masuk', 'pstatus' => $kode]) }}"
         title="Saring pelamar berstatus {{ $nama }}">
        <small>{{ $nama }}</small><b>{{ $rekrutStat[$kode] ?? 0 }}</b>
      </a>
    @endforeach
    <div class="kartu angka" title="Offboarding: akun nonaktif atau status kerja NONAKTIF"><small>Nonaktif</small><b>{{ $sdmStat['nonaktif'] }}</b></div>
  </div>

  <section class="blok">
    <h2>Rekrutmen &amp; Onboarding
      <small class="pudar">{{ $pelamarHalaman->dari() }}–{{ $pelamarHalaman->sampai() }}
        dari {{ $pelamarHalaman->jumlah }}
        &middot; wawancara &amp; kontrak kerja di HRIS</small>
    </h2>
    <p class="bantuan">
      Alur Hub berhenti di <b>keputusan</b>: pelamar dicatat → diputuskan
      <b>Diterima</b>/<b>Ditolak</b>. <b>Diterima</b> langsung dibuatkan akun pegawai
      (onboarding) lewat tabel <code>users</code> yang sama dengan halaman Akun — jadi
      tidak ada dua kenyataan yang bisa berbeda. <b>Offboarding</b> dilakukan dengan
      mengubah status kerja di tab Kehadiran. Wawancara, penawaran, dan kontrak kerja
      tetap di HRIS.
    </p>

    {{-- Penyaringan daftar pelamar ditangani hub-hr.js yang HANYA menukar
         <tbody> lewat potongan AJAX (data-pelamar → hr.pelamar), sehingga kotak
         cari & fokus tidak hilang. Tanpa JS form ini GET biasa dan tetap benar.

         data-pelamar WAJIB menunjuk RUTE POTONGAN, bukan /admin/hr?pelamar=1:
         pernah salah, dan karena URL tanpa `tab` server merender tab Pegawai →
         #hr-pelamar-baris tidak ada di respons → pencarian diam-diam gagal. --}}
    <form class="saring" method="get" action="{{ route('hr') }}"
          data-pelamar="{{ route('hr.pelamar') }}?pelamar=1">
      <input type="hidden" name="tab" value="masuk">
      <label class="cari">
        <span class="sr">Cari pelamar</span>
        <input type="search" name="q" value="{{ $q }}" autocomplete="off" placeholder="Cari nama, surel, atau posisi…"
               data-pelamar-cari>
      </label>
      <label class="pilih">
        <select name="pstatus" aria-label="Saring status pelamar" data-pelamar-status>
          <option value="">Semua status</option>
          @foreach (\App\Models\RekrutmenPelamar::STATUS_LABEL as $kode => $nama)
            <option value="{{ $kode }}" @selected($pstatus === $kode)>{{ $nama }}</option>
          @endforeach
        </select>
      </label>
      <a class="tombol samar bersih-saring" href="{{ route('hr', ['tab' => 'masuk']) }}"
         @if ($q === '' && $pstatus === '') hidden @endif>Bersihkan</a>
      <noscript><button class="tombol samar" type="submit">Saring</button></noscript>
    </form>

    <div class="tabel-bungkus">
    <table>
      <thead><tr>
        <th>Pelamar</th><th>Dilamar</th><th>Posisi</th><th>Sumber</th>
        <th>Status</th><th class="kolom-aksi">Tindakan</th>
      </tr></thead>
      <tbody id="hr-pelamar-baris">
      @include('admin._hr-pelamar', ['pelamar' => $pelamar, 'q' => $q, 'kelas' => $kelas])
      </tbody>
    </table>
    </div>
    @include('partials.halaman', ['halaman' => $pelamarHalaman, 'ikon' => 'app', 'kunci' => 'phalaman'])
    <div class="tombol-baris" style="justify-content:flex-end;margin-top:.8rem">
      <button class="tombol utama" type="button" data-buka="#hr-pelamar"
              data-judul-dialog="Tambah Pelamar">Tambah Pelamar</button>
    </div>
  </section>
  @endif

  <section class="blok">
    <h2>Catatan lingkup</h2>
    <p class="bantuan">
      Hub memuat apa yang benar-benar ada datanya, dan menyebut apa adanya yang tidak
      dikerjakan di sini. Penggajian hanya berupa rujukan berkas (HRIS memegang angkanya);
      penilaian kinerja dicatat apa adanya beserta penilainya (tanpa bobot/kuesioner);
      rekrutmen berhenti di keputusan (wawancara &amp; kontrak kerja tetap di HRIS).
    </p>
  </section>
</div>

{{-- ======================= DIALOG-DIALOG ======================= --}}
{{-- Satu dialog per JENIS aksi. Isi field + tujuan formulirnya diambil dari
     `data-lengkapi` tombol barisnya (hub.js) — jadi menambah baris tidak menambah
     dialog, dan tidak ada formulir yang digandakan. Ditaruh DI LUAR wadah
     [data-isi] supaya tidak terbuang saat saring/paging. --}}

{{-- Rincian baca-saja (identitas + kontak + kontrak). --}}
<dialog class="modal" id="hr-rincian" aria-labelledby="hr-rincian-judul">
  <header class="modal-kepala">
    <h2 id="hr-rincian-judul">Rincian Pegawai</h2>
    <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
  </header>
  <div class="modal-badan">
    <div class="tabel-bungkus">
      <table>
        <tbody>
          <tr><td data-l="Nama"><span data-teks="nama"></span></td></tr>
          <tr><td data-l="Email"><span data-teks="email"></span></td></tr>
          <tr><td data-l="Peran"><span data-teks="peran"></span> <span data-teks="sumber_peran" class="pudar"></span></td></tr>
          <tr><td data-l="Wilayah"><span data-teks="wilayah"></span></td></tr>
          <tr><td data-l="Koperasi"><span data-teks="koperasi"></span></td></tr>
          <tr><td data-l="Telepon"><span data-teks="telepon"></span></td></tr>
          <tr><td data-l="Kabupaten"><span data-teks="kabupaten"></span></td></tr>
          <tr><td data-l="Tanggal lahir"><span data-teks="lahir"></span></td></tr>
          <tr><td data-l="Jenis kelamin"><span data-teks="gender"></span></td></tr>
          <tr><td data-l="Akun dibuat"><span data-teks="dibuat"></span></td></tr>
          <tr><td data-l="Masuk terakhir"><span data-teks="masuk"></span></td></tr>
          <tr><td data-l="Status akun"><span data-teks="aktif"></span></td></tr>
          <tr><td data-l="Periode"><span data-teks="periode"></span></td></tr>
          <tr><td data-l="Penempatan"><span data-teks="penempatan"></span></td></tr>
          <tr><td data-l="Nomor kontrak"><span data-teks="kontrak"></span></td></tr>
          <tr><td data-l="Status kontrak"><span data-teks="kontrak_status"></span></td></tr>
          <tr><td data-l="Tanggal tanda tangan"><span data-teks="kontrak_ttd"></span></td></tr>
        </tbody>
      </table>
    </div>
    <p class="bantuan">
      Kontak, tanggal lahir, dan kabupaten berasal dari profil SIMKOPDES yang tersalin
      saat pendamping masuk — kalau kosong, orang itu belum pernah menarik profilnya.
      Sandi tidak pernah ditampilkan di mana pun: yang tersimpan hanya hash scrypt.
    </p>
  </div>
  <footer class="modal-kaki">
    <button class="tombol samar" type="button" data-tutup>Tutup</button>
  </footer>
</dialog>

{{-- Siklus kerja / onboarding-offboarding + atasan. --}}
<dialog class="modal" id="hr-modal" aria-labelledby="hr-modal-judul">
  <form method="post" action="#" data-aksi-lengkapi>
    @csrf
    <header class="modal-kepala">
      <h2 id="hr-modal-judul">Lembar Kepegawaian</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Status kerja
        <select name="status" required>
          @foreach (SdmPegawai::STATUS_LABEL as $kode => $nama)
            <option value="{{ $kode }}">{{ $nama }}</option>
          @endforeach
        </select>
      </label>
      <div class="grid-form">
        <label>Mulai bertugas <span class="bantuan">(onboarding)</span>
          <input type="date" name="tanggal_awal"></label>
        <label>Akhir bertugas <span class="bantuan">(offboarding — kosongkan bila masih jalan)</span>
          <input type="date" name="tanggal_akhir"></label>
      </div>
      <div class="grid-form">
        <label>Jabatan
          <input type="text" name="jabatan" maxlength="120" placeholder="mis. Manajer Koperasi"></label>
        <label>Unit / wilayah kerja
          <input type="text" name="unit" maxlength="120" placeholder="mis. Wilayah Jawa Barat"></label>
      </div>
      <label>Atasan langsung
        <select name="atasan_id">
          <option value="0">— tidak ada —</option>
          @foreach ($atasanPilihan ?? [] as $aid => $anama)
            <option value="{{ $aid }}">{{ $anama }}</option>
          @endforeach
        </select>
      </label>
      <label>Catatan
        <input type="text" name="catatan" maxlength="500" placeholder="mis. perpanjangan kontrak, mutasi"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan</button>
    </footer>
  </form>
</dialog>

{{-- Penilaian kinerja berperiode. --}}
<dialog class="modal" id="hr-nilai" aria-labelledby="hr-nilai-judul">
  <form method="post" action="#" data-aksi-lengkapi>
    @csrf
    <header class="modal-kepala">
      <h2 id="hr-nilai-judul">Penilaian Kinerja</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan">Nilai dicatat bersama periode dan penilainya, jadi tidak ada angka tanpa jejak.</p>
      <div class="grid-form">
        <label>Periode <span class="bantuan">(tahun-bulan)</span>
          <input type="month" name="periode_nilai" value="{{ $bulanIni }}" required></label>
        <label>Nilai <span class="bantuan">(1–100)</span>
          <input type="number" name="nilai" min="1" max="100" required></label>
      </div>
      <label>Catatan penilaian
        <input type="text" name="catatan_nilai" maxlength="500" placeholder="mis. capaian target, hal yang perlu diperbaiki"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Catat nilai</button>
    </footer>
  </form>
</dialog>

{{-- Rujukan slip gaji (tanpa angka rupiah). --}}
<dialog class="modal" id="hr-slip" aria-labelledby="hr-slip-judul">
  <form method="post" action="#" data-aksi-lengkapi>
    @csrf
    <header class="modal-kepala">
      <h2 id="hr-slip-judul">Rujukan Slip Gaji</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan">Hub tidak menyimpan nominal. Cukup nomor slip + nama berkas di HRIS.</p>
      <label>Nomor slip
        <input type="text" name="slip_nomor" maxlength="80" required placeholder="mis. SLIP/2026/10/0123"></label>
      <label>Nama berkas di HRIS
        <input type="text" name="slip_berkas" maxlength="160" placeholder="mis. slip-2026-10.pdf"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan rujukan</button>
    </footer>
  </form>
</dialog>

{{-- Tambah pelamar. --}}
<dialog class="modal" id="hr-pelamar" aria-labelledby="hr-pelamar-judul">
  <form method="post" action="{{ route('hr.rekrut') }}">
    @csrf
    <header class="modal-kepala">
      <h2 id="hr-pelamar-judul">Tambah Pelamar</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <label>Nama lengkap
        <input type="text" name="nama" maxlength="120" required></label>
      <div class="grid-form">
        <label>Surel
          <input type="email" name="email" maxlength="160" placeholder="wajib bila akan diangkat jadi pegawai"></label>
        <label>Telepon
          <input type="text" name="telepon" maxlength="32"></label>
      </div>
      <div class="grid-form">
        <label>Posisi dilamar
          <input type="text" name="jabatan" maxlength="120" placeholder="mis. Manajer Koperasi"></label>
        <label>Unit / wilayah
          <input type="text" name="unit" maxlength="120"></label>
      </div>
      <div class="grid-form">
        <label>Sumber lamaran
          <input type="text" name="sumber" maxlength="80" placeholder="mis. walk-in, surel, koperasi"></label>
        <label>Tanggal melamar
          <input type="date" name="dilamar" value="{{ now('Asia/Jakarta')->toDateString() }}"></label>
      </div>
      <label>Catatan
        <input type="text" name="catatan" maxlength="500"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Tambah</button>
    </footer>
  </form>
</dialog>

{{-- Keputusan pelamar (terima/tolak/tarik diri). --}}
<dialog class="modal" id="hr-putus" aria-labelledby="hr-putus-judul">
  <form method="post" action="#" data-aksi-lengkapi>
    @csrf
    <header class="modal-kepala">
      <h2 id="hr-putus-judul">Keputusan Pelamar</h2>
      <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
    </header>
    <div class="modal-badan">
      <p class="bantuan"><b>Diterima</b> langsung membuatkan akun pegawai (onboarding); surel pelamar wajib sudah diisi.</p>
      <label>Keputusan
        <select name="status" required>
          @foreach (\App\Models\RekrutmenPelamar::STATUS_LABEL as $kode => $nama)
            <option value="{{ $kode }}">{{ $nama }}</option>
          @endforeach
        </select>
      </label>
      <label>Catatan
        <input type="text" name="catatan" maxlength="500"></label>
    </div>
    <footer class="modal-kaki">
      <button class="tombol samar" type="button" data-tutup>Tutup</button>
      <button class="tombol utama" type="submit">Simpan keputusan</button>
    </footer>
  </form>
</dialog>
@endsection

@push('skrip')
<script src="/js/hub-hr.js?v={{ filemtime(public_path('js/hub-hr.js')) }}" defer></script>
@endpush

@extends('layouts.utama')

@section('judul', 'Sarana Prasarana Koperasi')
@section('isi')
@include('koperasi._kepala', ['ruteBagian' => 'kop.sarana'])

@if ($koperasi)
  @php
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
    $total = $katalog->flatten(1)->count();
    $persen = $titik === null ? 0 : ($titik->pembangunan_100 ? 100 : ($titik->pembangunan_mulai ? 50 : 0));
  @endphp

  @include('koperasi._nav', ['mod' => 'sarana', 'koperasi' => $koperasi])

  {{-- PROSES PEMBANGUNAN — dibaca dari TITIK PEMETAAN LAHAN yang dipilih
       manajer. Sumbernya satu dengan halaman Data Koperasi, jadi angkanya tidak
       bisa berbeda antar-halaman. --}}
  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('setuju', 'ik') !!}Proses Pembangunan</span></h2>
    @if ($titik)
      <div class="petak kop-petak">
        <div class="kop-stat">
          <span class="kop-stat-ikon">{!! ikon('analisa', 'ik') !!}</span>
          <span class="kop-stat-teks">
            <small>Status Pembangunan</small>
            <b class="teks">{{ $titik->statusPembangunan() }}</b>
            <i>dari pemetaan lahan, bukan isian manual</i>
          </span>
        </div>
        <div class="kop-stat">
          <span class="kop-stat-ikon">{!! ikon('pemetaan', 'ik') !!}</span>
          <span class="kop-stat-teks">
            <small>Luas Titik</small>
            <b>{{ $titik->luasTeks() }}</b>
            <i>{{ $titik->potensi ?: 'potensi belum dicatat' }}</i>
          </span>
        </div>
        <div class="kop-stat">
          <span class="kop-stat-ikon">{!! ikon('lokasi', 'ik') !!}</span>
          <span class="kop-stat-teks">
            <small>Titik Maps</small>
            <b class="teks">
              @if ($titik->adaTitik())
                <a href="{{ $titik->tautanPeta() }}" target="_blank" rel="noopener">{{ koordinat_teks((float) $titik->latitude, (float) $titik->longitude) }}</a>
              @else
                —
              @endif
            </b>
            <i>{{ $titik->desa }}, Kec. {{ $titik->kecamatan }}</i>
          </span>
        </div>
      </div>

      <div class="kop-progres" role="img" aria-label="Proses pembangunan {{ $persen }} persen">
        <span style="width: {{ min(100, max(0, $persen)) }}%"></span>
      </div>
    @else
      <p class="kosong">
        Koperasi ini belum tertaut ke titik pemetaan lahan, jadi proses pembangunannya
        belum bisa ditampilkan. Pilih desanya lewat <b>Ubah Data</b> di halaman Data Koperasi.
      </p>
    @endif
  </section>

  {{-- GERAI / OUTLET: diisi sendiri manajer (CRUD penuh), naskah yang sama dipakai
       halaman Data Koperasi. --}}
  @include('koperasi._gerai', ['kembali' => 'sarana'])

  {{-- KATALOG PATOKAN + CENTANG. Ini menjawab "sarana apa yang SEHARUSNYA ada"
       (butir Laporan Harian Pengecekan Titik KDKMP) sekaligus "mana yang SUDAH
       ada di koperasi ini". Satu formulir untuk semua kelompok: mencentang lalu
       menyimpan. Tidak ada butir yang dicentang lebih dulu — isian manajer. --}}
  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('distribusi', 'ik') !!}Katalog Sarana Prasarana Program</span></h2>
    <p class="bantuan">
      Daftar butir laporan pengecekan titik KDKMP — {{ $rp($total) }} butir. Centang yang
      <b>sudah tersedia</b> di koperasi ini, lalu simpan. Butir yang belum ada cukup
      dibiarkan kosong. Keterangan diisi bila perlu (jumlah, ukuran, kondisi).
      @unless ($bolehTulis)
        Hanya manajer koperasi yang bisa mengubah centang ini.
      @endunless
    </p>

    @if ($bolehTulis)
      <form method="post" action="{{ route('kop.sarana.centang') }}">
        @csrf
        <input type="hidden" name="kembali" value="sarana">
    @endif

    {{-- SATU tabel untuk semua kelompok. Dulu tiap kelompok dapat tabel sendiri
         (`@forelse` + `<h3>` + `.tabel-bungkus`), sehingga lebar kolom antar
         tabel tidak sejajar (terukur 251/417/348px) dan kepala tabel diulang
         3x. Sekarang kelompok jadi baris pemisah di dalam tabel yang sama.
         Kelompok = jenis pengembangan + sub-kelompok laporan ("1. Konstruksi
         Bangunan · B. Kenyamanan dan Kebersihan"), jadi tidak perlu label
         pengulang di setiap baris. --}}
    @if ($total)
      <div class="tabel-bungkus">
        <table class="tabel-katalog">
          <thead>
            <tr>
              <th>Butir</th>
              <th class="kolom-pilih">Tersedia</th>
              @if ($bolehTulis) <th>Keterangan</th><th>Kondisi</th> @endif
            </tr>
          </thead>
          @foreach ($katalog as $jenis => $items)
            <tbody>
              <tr class="baris-kelompok">
                <td colspan="{{ $bolehTulis ? 4 : 2 }}">
                  {{ $jenis }} <small class="pudar">{{ $items->count() }} butir</small>
                </td>
              </tr>
              @foreach ($items as $it)
                @php $c = $centang[$it->id] ?? null; @endphp
                <tr>
                  <td data-l="Butir">{{ $it->indikator ?: '—' }}</td>
                  <td data-l="Tersedia" class="kolom-pilih">
                    @if ($bolehTulis)
                      <label class="centang">
                        <input type="checkbox" name="tersedia[]" value="{{ $it->id }}"
                               @checked($c !== null)>
                        <span class="sr">Tersedia</span>
                      </label>
                    @else
                      <span class="lencana {{ ! $c ? 's-DITOLAK' : (($c->kondisi === 'RUSAK') ? 's-REVISI' : 's-DISETUJUI') }}">{{ ! $c ? 'Belum' : (($c->kondisi === 'RUSAK') ? 'Rusak' : 'Lengkap') }}</span>
                    @endif
                  </td>
                  @if ($bolehTulis)
                    <td data-l="Keterangan">
                      <input name="keterangan[{{ $it->id }}]" maxlength="200"
                             value="{{ $c->keterangan ?? '' }}" placeholder="mis. 2 unit rusak ringan">
                    </td>
                    {{-- Kondisi: hanya bermakna bila sarana ada. Nilainya dipakai
                         ringkasan /kop (lengkap vs rusak), jadi pilihannya dua,
                         bukan teks bebas. --}}
                    <td data-l="Kondisi">
                      <select name="kondisi[{{ $it->id }}]" aria-label="Kondisi {{ $it->indikator }}">
                        <option value="BAIK" @selected(($c->kondisi ?? 'BAIK') !== 'RUSAK')>Lengkap / Baik</option>
                        <option value="RUSAK" @selected(($c->kondisi ?? '') === 'RUSAK')>Rusak</option>
                      </select>
                    </td>
                  @endif
                </tr>
              @endforeach
            </tbody>
          @endforeach
        </table>
      </div>
    @else
      <p class="kosong">Katalog butir sarana belum tersedia di Hub.</p>
    @endif

    @if ($bolehTulis)
        {{-- Bilah simpan menempel di bawah layar: 33 butir = gulir panjang,
             tanpa ini tombol simpan hanya bisa dijangkau dari dasar halaman
             (terukur 4.934px di desktop, 13.429px di ponsel). --}}
        <div class="bilah-simpan">
          <button class="tombol utama" type="submit">Simpan Centang Sarana</button>
          <small class="pudar">{{ $centang->count() }} dari {{ $rp($total) }} ditandai tersedia.</small>
        </div>
      </form>
    @endif
  </section>

  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('pemetaan', 'ik') !!}Aset / Lahan Terverifikasi</span></h2>
    @if ($lahan->isNotEmpty())
      <div class="tabel-bungkus">
        <table>
          <thead><tr><th>Nama Aset</th><th class="angka-kanan">Luas (m²)</th><th>Kepemilikan</th><th>Status Verval</th><th>Jenis</th><th>Surveyor</th></tr></thead>
          <tbody>
            @foreach ($lahan as $l)
              <tr>
                <td data-l="Nama Aset">{{ $l->nama ?: '—' }}</td>
                <td data-l="Luas" class="angka-kanan">{{ number_format((float) $l->luas, 0, ',', '.') }}</td>
                <td data-l="Kepemilikan">{{ $l->kepemilikan ?: '—' }}</td>
                <td data-l="Status Verval">{{ $l->status ?: '—' }}</td>
                <td data-l="Jenis">{{ $l->status_aset ?: '—' }}</td>
                <td data-l="Surveyor">{{ $l->surveyor ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="bantuan">
        Aset ini hasil verval lapangan (bukan isian Hub), jadi hanya bisa dibaca.
        Rinciannya ada di halaman <a href="{{ route('admin.koperasi') }}">Koperasi (daftar)</a>,
        tombol Lahan &amp; Pembangunan.
      </p>
    @else
      <p class="kosong">Belum ada aset/lahan terverifikasi untuk koperasi ini.</p>
    @endif
  </section>

  {{-- Padanan pemetaan lahan: titik desa yang wilayahnya sama dengan koperasi
       ini. Ditaruh SETELAH aset terverifikasi karena menjawab pertanyaan yang
       sama ("asetnya di mana & sudah sejauh apa") dari sumber yang berbeda. --}}
  @include('koperasi._padanan', ['koperasi' => $koperasi, 'padanan' => $padanan ?? []])
@endif
@endsection

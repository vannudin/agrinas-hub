@extends('layouts.utama')

@section('judul', 'Koperasi')
@section('isi')
{{-- Halaman DATA KOPERASI — menjawab "koperasi ini keadaannya bagaimana" dengan
     angka yang bisa ditelusuri, lalu menautkan ke dua halaman kerja (Sarana,
     POS). Susunannya: kepala (identitas + aksi) → petak angka → padanan
     pemetaan lahan → rincian identitas → dua pintu ke halaman bagian. --}}
@include('koperasi._kepala', ['ruteBagian' => 'kop', 'ringkasSemua' => $ringkasSemua ?? null])

@if ($koperasi)
  @php
    // Nomor gaya Indonesia: 1.234.567. Dipakai berkali-kali di halaman ini.
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
    $angka = fn ($n, $desimal = 2) => rtrim(rtrim(number_format((float) $n, $desimal, ',', '.'), '0'), ',');
    $mj = App\Models\User::find($koperasi->manajer_id);
    // Status operasional DINILAI MANAJER (lihat KoperasiController::operasional).
    // Kelasnya diambil dari model — dulu dicocokkan lewat teks label di sini dan
    // tidak pernah cocok ('Sebagian' vs 'Operasional Sebagian'), sehingga semua
    // koperasi tampak "belum operasional".
    $ops = $sarana?->labelOperasional();
    $kelasOps = $sarana?->kelasOperasional() ?? '';
  @endphp

  @include('koperasi._nav', ['mod' => 'data', 'koperasi' => $koperasi])

  {{-- PETAK ANGKA. Ini ringkasan yang dibaca pengelola lebih dulu, jadi angka
       besar ditaruh di kiri-atas dan yang paling penting (nilai stok, penjualan
       hari ini) mendapat tempat pertama di baris kedua. `.kop-petak` mengatur
       lebar kolomnya (4/3/2), jangan pakai `.petak` polos: 7 kartu dengan
       auto-fit jatuh 6+1 dan satu kartu menggantung sendirian. --}}
  <div class="petak kop-petak">
    <div class="kop-stat">
      <span class="kop-stat-ikon">{!! ikon('koperasi', 'ik') !!}</span>
      <span class="kop-stat-teks">
        <small>Status Operasional</small>
        <b class="teks">{{ $ops ?: 'Belum diisi' }}</b>
        <i>{{ $sarana?->alasan ?: 'diisi manajer koperasi' }}</i>
      </span>
    </div>
    <div class="kop-stat">
      <span class="kop-stat-ikon">{!! ikon('setuju', 'ik') !!}</span>
      <span class="kop-stat-teks">
        <small>Proses Pembangunan</small>
        <b class="teks">{{ $titik?->statusPembangunan() ?? '—' }}</b>
        <i>{{ $titik ? 'dari pemetaan lahan desa '.$titik->desa : 'belum tertaut titik lahan' }}</i>
      </span>
    </div>
    <div class="kop-stat">
      <span class="kop-stat-ikon">{!! ikon('stok-koperasi', 'ik') !!}</span>
      <span class="kop-stat-teks">
        <small>Jenis Barang</small>
        <b>{{ $rp($ringkas['barang']) }}</b>
        <i>{{ $rp($ringkas['menipis']) }} menipis · nilai Rp {{ $rp($ringkas['nilai_stok']) }}</i>
      </span>
    </div>
    <div class="kop-stat">
      <span class="kop-stat-ikon">{!! ikon('keuangan', 'ik') !!}</span>
      <span class="kop-stat-teks">
        <small>Penjualan Hari Ini</small>
        <b class="uang">Rp {{ $rp($ringkas['penjualan_hari']) }}</b>
        <i>{{ $rp($ringkas['nota_hari']) }} nota hari ini</i>
      </span>
    </div>
    <div class="kop-stat">
      <span class="kop-stat-ikon">{!! ikon('toko', 'ik') !!}</span>
      <span class="kop-stat-teks">
        <small>Gerai / Outlet</small>
        <b>{{ $rp($ringkas['gerai']) }}</b>
        <i>outlet tercatat</i>
      </span>
    </div>
    <div class="kop-stat">
      <span class="kop-stat-ikon">{!! ikon('pemetaan', 'ik') !!}</span>
      <span class="kop-stat-teks">
        <small>Aset / Lahan</small>
        <b>{{ $rp($ringkas['lahan']) }}</b>
        <i>{{ $angka($ringkas['luas']) }} m² · {{ $ringkas['persen'] }}% pembangunan</i>
      </span>
    </div>
    <div class="kop-stat">
      <span class="kop-stat-ikon">{!! ikon('distribusi', 'ik') !!}</span>
      <span class="kop-stat-teks">
        <small>Sarana Tersedia</small>
        <b>{{ $rp($ringkas['tersedia']) }}</b>
        <i>dari {{ $rp($katalog->flatten(1)->count()) }} butir · {{ $rp($ringkas['rusak']) }} rusak</i>
      </span>
    </div>
  </div>

  {{-- IDENTITAS. Satu tabel kunci–nilai; tidak ada kartu terpisah per baris. --}}
  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('koperasi', 'ik') !!}Data Koperasi</span></h2>

    @if ($bolehTulis)
      {{-- CRUD pemilik data: manajer mengurus koperasinya sendiri. Ubah lewat
           dialog (satu formulir, isian sudah terisi); hapus DITOLAK pengelola
           bila koperasi sudah punya data — lihat KoperasiController::hapus(). --}}
      <div class="tombol-baris aksi-kop">
        <button class="tombol samar" type="button" data-buka="#kop-ubah">Ubah Data</button>
        <form method="post" action="{{ route('kop.hapus', ['id' => $koperasi->id]) }}"
              data-konfirmasi="Hapus koperasi {{ $koperasi->kode }}? Bisa dihapus hanya bila belum punya data (stok, nota, aset).">
          @csrf @method('DELETE')
          <button class="tombol samar" type="submit">Hapus</button>
        </form>
        @if ($lahanAwal)
          {{-- Pindah lokasi: tautan dilepas, lalu desa baru dipilih lewat Ubah
               Data. Tanpa tombol ini titik lama terus dipegang koperasi ini. --}}
          <form method="post" action="{{ route('kop.lepas', ['id' => $koperasi->id]) }}"
                data-konfirmasi="Lepas tautan ke {{ $lahanAwal['text'] }}? Koperasi ini kembali tanpa titik lokasi dan titik itu bebas dipakai koperasi lain.">
            @csrf
            <button class="tombol samar" type="submit">Lepas Tautan</button>
          </form>
        @endif
      </div>
    @endif

    <dl class="rincian">
      <dt>Kode</dt><dd><code>{{ $koperasi->kode }}</code></dd>
      <dt>Nama</dt><dd>{{ $koperasi->nama }}</dd>
      {{-- Wilayah & provinsi dibaca dari TITIK PEMETAAN LAHAN bila ada: itulah
           sumbernya, dan salinan di `koperasi` hanya hasil saat menyimpan. --}}
      <dt>Wilayah</dt>
      <dd>{{ $titik ? "Desa {$titik->desa}, Kec. {$titik->kecamatan}, {$titik->kabupaten}" : ($koperasi->wilayah ?: '—') }}</dd>
      <dt>Provinsi</dt><dd>{{ $titik?->provinsi ?: ($koperasi->provinsi ?: '—') }}</dd>
      @if ($lahanAwal)
        {{-- Titik pemetaan lahan yang DIPILIH manajer — bukan hasil tebakan
             kemiripan nama desa. Kosong = koperasi lama sebelum kolom ini ada. --}}
        <dt>Desa Pemetaan Lahan</dt><dd>{{ $lahanAwal['text'] }}</dd>
        <dt>Titik Lokasi Maps</dt>
        <dd>
          @if ($lahanAwal['peta'])
            <a href="{{ $lahanAwal['peta'] }}" target="_blank" rel="noopener">{{ $lahanAwal['koordinat'] }}</a>
          @else
            —
          @endif
        </dd>
      @endif
      @if ($titik)
        <dt>Proses Pembangunan</dt><dd>{{ $titik->statusPembangunan() }}</dd>
        <dt>Luas Titik</dt><dd>{{ $titik->luasTeks() }}</dd>
      @endif
      <dt>Alamat</dt><dd>{{ $koperasi->alamat ?: '—' }}</dd>
      <dt>Status Operasional</dt>
      <dd>
        <span class="lencana {{ $kelasOps }}">{{ $ops ?: 'Belum diisi' }}</span>
        @if ($sarana?->alasan) <small class="pudar">{{ $sarana->alasan }}</small> @endif
      </dd>
      <dt>Status di Hub</dt><dd>{{ App\Models\Koperasi::LABEL_STATUS[$koperasi->status] ?? $koperasi->status }}</dd>
      <dt>Manajer</dt>
      <dd>
        {{ $mj?->nama ?: '—' }}
        @if ($mj) <small class="pudar">{{ $mj->email }}</small> @endif
      </dd>
      <dt>No. Rekening</dt><dd>{{ $koperasi->no_rekening ?: '—' }}</dd>
      <dt>Id SIMKOPDES</dt><dd>{{ $koperasi->koperasi_simkopdes_id ?: 'belum tertaut' }}</dd>
    </dl>

    @if ($bolehTulis)
      {{-- Status operasional DIISI MANAJER. Dulu angka ini hasil hitungan
           penarik SIMKOPDES; tarikan itu sudah diputus, jadi penilainya manusia
           dan alasannya ikut dicatat. --}}
      <form method="post" action="{{ route('kop.operasional') }}" class="grid-form tumpuk">
        @csrf
        <input type="hidden" name="kembali" value="data">
        <label>Status Operasional
          <select name="operasional" required>
            @foreach (App\Models\KoperasiSarana::LABEL as $kunci => $label)
              <option value="{{ $kunci }}" @selected($sarana?->operasional === $kunci)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>Catatan (opsional)
          <input name="catatan" maxlength="200" value="{{ $sarana?->alasan }}"
                 placeholder="Alasan status ini, mis. baru buka 1 gerai">
        </label>
        <div class="lebar-penuh tombol-baris">
          <button class="tombol" type="submit">Simpan Status Operasional</button>
        </div>
      </form>
    @endif
  </section>

  {{-- PROSES PEMBANGUNAN. Sumbernya TITIK PEMETAAN LAHAN yang dipilih manajer
       (bukan tarikan SIMKOPDES yang sudah diputus): status pembangunan, luas,
       potensi, dan titik petanya. --}}
  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('setuju', 'ik') !!}Proses Pembangunan</span></h2>
    @if ($titik)
      <p class="bantuan">
        <b>{{ $titik->statusPembangunan() }}</b> —
        desa {{ $titik->desa }}, Kec. {{ $titik->kecamatan }}, {{ $titik->kabupaten }}.
        Data ini mengikuti titik pemetaan lahan, jadi selalu sama dengan sumbernya.
      </p>
      <div class="kop-progres" role="img"
           aria-label="Proses pembangunan {{ $ringkas['persen'] }} persen">
        <span style="width: {{ min(100, max(0, $ringkas['persen'])) }}%"></span>
      </div>
      <dl class="rincian">
        <dt>Status</dt><dd>{{ $titik->statusPembangunan() }}</dd>
        <dt>Luas titik</dt><dd>{{ $titik->luasTeks() }}</dd>
        @if ($titik->potensi)
          <dt>Potensi</dt><dd>{{ $titik->potensi }}</dd>
        @endif
        <dt>Titik Maps</dt>
        <dd>
          @if ($titik->adaTitik())
            <a href="{{ $titik->tautanPeta() }}" target="_blank" rel="noopener">{{ koordinat_teks((float) $titik->latitude, (float) $titik->longitude) }}</a>
          @else
            —
          @endif
        </dd>
      </dl>
    @else
      <p class="kosong">
        Koperasi ini belum tertaut ke titik pemetaan lahan, jadi proses pembangunannya
        belum bisa ditampilkan.
        @if (($padanan['satu'] ?? null))
          Titik desanya sudah ditemukan dari pemetaan lahan — tautkan sekali tekan.
        @else
          Tekan <b>Ubah Data</b> lalu pilih desanya.
        @endif
      </p>
      @if (($padanan['satu'] ?? null) && $bolehTulis)
        <form method="post" action="{{ route('kop.taut', ['id' => $koperasi->id]) }}" class="tombol-baris">
          @csrf
          <input type="hidden" name="pemetaan_lahan_id" value="{{ $padanan['satu']->id }}">
          <button class="tombol utama ikon-kiri" type="submit">
            {!! ikon('pemetaan', 'ik kecil') !!}Tautkan ke titik {{ $padanan['satu']->desa }}
          </button>
          <small class="pudar">
            {{ $padanan['satu']->statusPembangunan() }} · {{ $padanan['satu']->luasTeks() }}
          </small>
        </form>
      @endif
    @endif
  </section>

  @include('koperasi._padanan', ['koperasi' => $koperasi, 'padanan' => $padanan])

  @include('koperasi._gerai', ['kembali' => 'data'])

  {{-- DUA PINTU ke halaman bagian. Ditulis sebagai kartu yang bisa diklik, bukan
       kalimat "rinciannya ada di tautan X" — satu sentuhan, bukan dua. --}}
  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('fitur', 'ik') !!}Bagian Koperasi</span></h2>
    <div class="kartu-baris">
      <a class="kartu" href="{{ route('kop.sarana', ['koperasi' => $koperasi->id]) }}">
        <b>{!! ikon('setuju', 'ik kecil') !!} Sarana Prasarana</b>
        <small class="pudar">
          Proses pembangunan, gerai, aset terverifikasi, dan butir sarana yang
          bisa dicentang.
          {{ $rp($ringkas['lahan']) }} aset · {{ $angka($ringkas['luas']) }} m².
        </small>
      </a>
      <a class="kartu" href="{{ route('kop.pos', ['koperasi' => $koperasi->id]) }}">
        <b>{!! ikon('keuangan', 'ik kecil') !!} POS Penjualan</b>
        <small class="pudar">
          Kasir, scan barcode, riwayat nota, dan rekap penjualan.
          Hari ini {{ $rp($ringkas['penjualan_hari']) }} Rp dari {{ $rp($ringkas['nota_hari']) }} nota.
        </small>
      </a>
    </div>
  </section>

  @if ($bolehTulis)
    {{-- Ubah identitas: satu dialog, isian sudah terisi nilai sekarang. Kode &
         status tidak ada di formulir — keduanya milik pengelola. --}}
    <dialog class="modal" id="kop-ubah" aria-labelledby="kop-ubah-judul">
      <header class="modal-kepala">
        <h2 id="kop-ubah-judul">Ubah Data Koperasi</h2>
        <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
      </header>
      <div class="modal-badan">
        @include('koperasi._form-data', [
          'aksi' => route('kop.ubah', ['id' => $koperasi->id]),
          'idSelect' => 'lahan-ubah',
          'lahanAwal' => $lahanAwal,
          'denganPkwt' => false,
          'wajibLahan' => false,
          'namaTerkunci' => $lahanAwal !== null,
          'batal' => true,
          'tombol' => 'Simpan Perubahan',
          'nilai' => [
            'nama' => $lahanAwal['nama'] ?? old('nama', $koperasi->nama),
            'wilayah' => old('wilayah', $koperasi->wilayah),
            'provinsi' => old('provinsi', $koperasi->provinsi),
            'alamat' => old('alamat', $koperasi->alamat),
            'no_rekening' => old('no_rekening', $koperasi->no_rekening),
          ],
        ])
      </div>
    </dialog>
  @endif
@endif

@if ($bolehTulis && ! $koperasi)
  {{-- TAMBAH: manajer yang akunnya belum tertaut koperasi. Yang dipilih adalah
       DESA dari pemetaan lahan, bukan mengetik nama desa — satu sumber, tidak
       ada salah tulis yang harus dicocokkan belakangan. --}}
  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('koperasi', 'ik') !!}Tambah Data Koperasi</span></h2>
    <p class="bantuan">
      Pilih desa tempat koperasi Anda berada dari <b>pemetaan lahan</b>. Nama koperasi,
      wilayah, provinsi, dan titik lokasi maps terisi otomatis dari titik itu.
      Data tersimpan berstatus <b>Baru</b> sampai pengelola memeriksanya terhadap PKWT.
    </p>
    @include('koperasi._form-data', [
      'aksi' => route('kop.simpan'),
      'idSelect' => 'lahan-baru',
      'lahanAwal' => $lahanAwal,
      'denganPkwt' => true,
      'wajibLahan' => true,
      'namaTerkunci' => true,
      'batal' => false,
      'tombol' => 'Simpan Data Koperasi',
      'nilai' => [
        'nama' => $lahanAwal['nama'] ?? old('nama', ''),
        'wilayah' => old('wilayah', ''),
        'provinsi' => old('provinsi', ''),
        'alamat' => old('alamat', ''),
        'no_rekening' => old('no_rekening', ''),
      ],
    ])
  </section>
@endif
@endsection

@push('gaya')
<link rel="stylesheet" href="/vendor/select2/select2.min.css">
@endpush

@push('skrip')
{{-- Select2 sudah ada di /vendor (dipakai form Akun): jaringan lapangan tidak
     boleh bergantung CDN. Dipakai karena daftar desa berisi 38.861 baris —
     <select> biasa akan menggantung peramban, dan pencarian harus ke server. --}}
<script src="/vendor/jquery/jquery.min.js" defer></script>
<script src="/vendor/select2/select2.min.js" defer></script>
<script defer>
  (function () {
    var LAHAN = @json(route('kop.lahan'));

    function pasang(sel) {
      var jq = window.jQuery;
      var el = document.querySelector(sel);
      if (!jq || !jq.fn.select2 || !el || jq(el).hasClass('select2-hidden-accessible')) return;

      // `dropdownParent` WAJIB untuk pemilih yang ada di dalam <dialog>:
      // Select2 menempelkan daftar hasil ke <body>, sedangkan <dialog> hidup di
      // TOP LAYER — akibatnya daftar hasil tertimbun dialog (terukur: klik
      // ketikan tidak masuk dan opsi tidak pernah terlihat). Menempelkannya ke
      // dialog menaruh daftar di lapisan yang sama, tanpa z-index tambahan.
      var dlg = el.closest('dialog');

      var opts = {
        width: '100%',
        placeholder: 'Ketik nama desa / kecamatan…',
        minimumInputLength: 3,
        ajax: {
          url: LAHAN,
          dataType: 'json',
          delay: 250,
          data: function (p) { return { q: p.term }; },
          processResults: function (d) { return d; },
          cache: true
        },
        language: {
          inputTooShort: function () { return 'Ketik minimal 3 huruf…'; },
          noResults: function () { return 'Desa tidak ditemukan'; },
          searching: function () { return 'Mencari…'; }
        }
      };
      if (dlg) opts.dropdownParent = jq(dlg);

      jq(el).select2(opts);

      // Nama koperasi & titik peta ikut terisi dari desa yang dipilih: dua kolom
      // read-only, jadi tidak ada ejaan desa kedua yang bisa menyimpang.
      jq(el).on('select2:select', function (e) {
        var d = e.params.data || {};
        var f = el.closest('form');
        if (!f) return;
        [['wilayah', d.wilayah], ['provinsi', d.provinsi], ['nama', d.nama]].forEach(function (p) {
          var i = f.querySelector('[name="' + p[0] + '"]');
          if (i && p[1]) i.value = p[1];
        });

        var koor = f.querySelector('[data-peta]');
        if (koor) koor.value = d.koordinat || '';

        var tautan = f.querySelector('[data-peta-tautan]');
        if (tautan) {
          tautan.href = d.peta || '#';
          tautan.hidden = !d.peta;
        }
      });
    }

    function mulai() { pasang('#lahan-baru'); pasang('#lahan-ubah'); }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', mulai);
    } else {
      mulai();
    }
  })();
</script>
@endpush

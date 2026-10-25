{{--
  Panel analisa lintas modul — dipakai Jurnal Audit (admin & PIC) dan
  Struktur & Tim (AgriConnect). Satu partial, bukan dua salinan: angka turunan
  gampang berbeda begitu dihitung di dua tempat.

  Variabel:
    $an        = keluaran App\Support\Analisa (cakupan, tren, aksi, sebaran,
                 wilayah, kesiapan)
    $anBagian  = urutan bagian yang ditampilkan; default seluruhnya. Halaman
                 menyusun sendiri supaya analisa yang sama tidak ditampilkan dua
                 kali dengan bentuk berbeda di dua halaman.
    $anRinci   = tampilkan tabel rincian per provinsi (default true)

  Semua grafik di sini CSS murni (tanpa pustaka, tanpa JavaScript) — sama seperti
  grafik arus kas di modul Keuangan.
--}}
@php
  $bagian = $anBagian ?? ['tren', 'cakupan', 'sebaran', 'aksi', 'kesiapan', 'wilayah'];
  $rinci = $anRinci ?? true;
  $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
  // Nama provinsi panjang ("NUSA TENGGARA TIMUR (NTT)") dimampatkan memakai tabel
  // wilayah rumah supaya kolom Provinsi tidak memaksa tabel melebar. Nilai yang
  // tidak dikenal dibiarkan apa adanya.
  $prov = fn (string $p) => \App\Support\Wilayah::label($p);
  // Ada isi selain tren? Halaman yang hanya meminta tren (mis. panel ringkas di
  // Jurnal Audit) tidak boleh ikut merender kotak kosong berbingkai.
  $adaLain = (bool) array_intersect($bagian, ['cakupan', 'sebaran', 'aksi', 'kesiapan'])
      || ($rinci && in_array('wilayah', $bagian));
@endphp

@if (in_array('tren', $bagian) && $an['tren']['seri'])
  @php $tr = $an['tren']; @endphp
  <section class="blok">
    <h2>Aktivitas {{ $tr['hari'] }} Hari Terakhir
      <small class="pudar">dari jurnal audit</small>
    </h2>
    <div class="petak">
      <div class="kartu angka"><small>Total Aktivitas</small><b>{{ $nf($tr['total']) }}</b>
        <small class="pudar">{{ $nf($tr['rata']) }} per hari</small></div>
      <div class="kartu angka aksi-hijau"><small>Hari Ini</small><b class="t-hijau">{{ $nf($tr['hari_ini']) }}</b>
        <small class="pudar">Waktu Indonesia Barat</small></div>
      <div class="kartu angka aksi-kuning"><small>Hari Teramai</small><b class="t-kuning">{{ $nf($tr['puncak']) }}</b>
        <small class="pudar">puncak tertinggi periode ini</small></div>
    </div>
    <div class="tren" role="img"
         aria-label="Aktivitas harian {{ $tr['hari'] }} hari terakhir, puncak {{ $nf($tr['puncak']) }}">
      @foreach ($tr['seri'] as $h)
        <span class="tren-kolom{{ $h['n'] === 0 ? ' kosong' : '' }}"
              title="{{ tanggal_id($h['tgl'], 'j M Y') }} · {{ $nf($h['n']) }} aktivitas"><i
              style="height:{{ $tr['puncak'] > 0 ? max(2, (int) round($h['n'] / $tr['puncak'] * 100)) : 0 }}%"></i></span>
      @endforeach
    </div>
    <div class="arus-kaki">
      <span>{{ tanggal_id($tr['seri'][0]['tgl'], 'j M') }}</span>
      <span>puncak {{ $nf($tr['puncak']) }} · skala penuh</span>
      <span>{{ tanggal_id($tr['seri'][count($tr['seri']) - 1]['tgl'], 'j M') }}</span>
    </div>
  </section>
@endif

@if ($adaLain)
<section class="blok">
  @if (in_array('cakupan', $bagian))
    @php $c = $an['cakupan']; @endphp
    <h2>Cakupan Kerja
      <small class="pudar">{{ $c['koperasi'] }} koperasi dalam lingkup Anda</small>
    </h2>
    <p class="bantuan">Jumlah koperasi saja tidak memberi tahu apakah pekerjaannya
      berjalan. Baris di bawah membandingkan berapa koperasi yang sudah punya jejak
      di tiap modul.</p>
    <div class="petak-kotak">
      @foreach ([['tugas', 'Tugas'], ['distribusi', 'Distribusi Barang'],
                 ['pasokan', 'Produk Lokal & UMKM'], ['saldo', 'Saldo Koperasi']] as [$kk, $lb])
        @php $n = $c[$kk]; $pct = $c['koperasi'] > 0 ? (int) round($n / $c['koperasi'] * 100) : 0; @endphp
        <div class="jejak-baris">
          <span class="nama">{{ $lb }}</span>
          <span class="jejak"><span style="width:{{ $pct }}%"></span></span>
          <b>{{ $n }}/{{ $c['koperasi'] }}</b>
        </div>
      @endforeach
      <div class="jejak-baris">
        <span class="nama">Stok gudang tipis</span>
        <span class="jejak"><span style="width:{{ $c['gudang'] > 0 ? 100 : 0 }}%"
          class="{{ $c['gudang'] > 0 ? 'peringatan' : '' }}"></span></span>
        <b>{{ $c['gudang'] > 0 ? $nf($c['gudang']).' item' : 'aman' }}</b>
      </div>
    </div>
  @endif

  @if (in_array('sebaran', $bagian))
    @php $sb = $an['sebaran']; @endphp
    <h2 class="petak-judul">Sebaran Aktivitas per Koperasi</h2>
    <div class="petak-kotak">
      @foreach ($sb['ember'] as $e)
        @php $pct = $sb['total'] > 0 ? (int) round($e['jml'] / $sb['total'] * 100) : 0; @endphp
        <div class="jejak-baris">
          <span class="nama">{{ $e['label'] }}</span>
          <span class="jejak"><span class="e-{{ $e['kunci'] }}" style="width:{{ $pct }}%"></span></span>
          <b>{{ $e['jml'] }} koperasi</b>
        </div>
      @endforeach
    </div>
    @if ($sb['dorman'])
      <p class="bantuan">
        <b>Belum ada catatan sama sekali:</b>
        @foreach (array_slice($sb['dorman'], 0, 12) as $nm) <span class="lencana samar">{{ $nm }}</span> @endforeach
        @if (count($sb['dorman']) > 12) <span class="pudar">dan {{ count($sb['dorman']) - 12 }} lainnya</span> @endif
      </p>
    @endif
  @endif

  @if (in_array('aksi', $bagian) && $an['aksi'])
    @php $mx = max(1, ...array_map(fn ($a) => $a['n'], $an['aksi'])); @endphp
    <h2 class="petak-judul">Aksi Paling Sering</h2>
    <div class="petak-kotak">
      @foreach ($an['aksi'] as $a)
        <div class="jejak-baris">
          <span class="nama">{{ \App\Support\Analisa::labelAksi($a['aksi']) }}</span>
          <span class="jejak"><span style="width:{{ (int) round($a['n'] / $mx * 100) }}%"></span></span>
          <b>{{ $nf($a['n']) }}</b>
        </div>
      @endforeach
    </div>
  @endif

  @if (in_array('kesiapan', $bagian))
    @php $k = $an['kesiapan']; @endphp
    <h2 class="petak-judul">Kesiapan yang Bisa Ditindaklanjuti</h2>
    <div class="petak-kotak">
      @foreach ([
          ['manajer', 'Koperasi punya manajer', 'Tunjuk manajer agar tugas bisa ditugaskan.'],
          ['rekening', 'Koperasi punya rekening', 'Lengkapi nomor rekening di pengelolaan koperasi.'],
          ['provinsi', 'Provinsi terisi', 'Lengkapi provinsi agar masuk peta wilayah.'],
      ] as [$kk, $lb, $saran])
        @php $n = $k[$kk]; $kurang = $k['total'] - $n; @endphp
        <div class="jejak-baris">
          <span class="nama">{{ $lb }}</span>
          <span class="jejak"><span style="width:{{ $k['total'] > 0 ? (int) round($n / $k['total'] * 100) : 0 }}%"></span></span>
          <b>{{ $n }}/{{ $k['total'] }}</b>
          {{-- Saran hanya muncul kalau memang ada yang kurang: baris "lengkap"
               yang tetap menyuruh bertindak membuat daftar ini tidak dipercaya. --}}
          @if ($kurang > 0) <small class="pudar">{{ $kurang }} koperasi belum — {{ $saran }}</small> @endif
        </div>
      @endforeach
      <div class="jejak-baris">
        <span class="nama">Akun aktif</span>
        <span class="jejak"><span style="width:{{ $k['akun_total'] > 0 ? (int) round($k['akun_aktif'] / $k['akun_total'] * 100) : 0 }}%"></span></span>
        <b>{{ $k['akun_aktif'] }}/{{ $k['akun_total'] }}</b>
      </div>
    </div>
  @endif

  @if ($rinci && in_array('wilayah', $bagian) && $an['wilayah']['baris'])
    @php $wl = $an['wilayah']; @endphp
    <h2 class="petak-judul">Aktivitas menurut Provinsi</h2>
    <div class="tabel-bungkus">
    <table>
      <thead><tr><th>Provinsi</th><th class="angka-kanan">Koperasi</th><th class="angka-kanan">Tugas</th>
        <th class="angka-kanan">Distribusi</th><th class="angka-kanan">Pasokan</th><th>Sebaran</th></tr></thead>
      <tbody>
      @foreach ($wl['baris'] as $x)
        <tr>
          <td data-l="Provinsi"><span class="judul-sel">{{ $prov($x->provinsi) }}</span></td>
          <td data-l="Koperasi" class="angka-kanan">{{ $x->koperasi }}</td>
          <td data-l="Tugas" class="angka-kanan">{{ $x->tugas }}</td>
          <td data-l="Distribusi" class="angka-kanan">{{ $x->distribusi }}</td>
          <td data-l="Pasokan" class="angka-kanan">{{ $x->pasokan }}</td>
          <td data-l="Sebaran">
            <span class="jejak"><span style="width:{{ (int) round($x->jumlah / $wl['maks'] * 100) }}%"></span></span>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
    </div>
  @endif
</section>
@endif

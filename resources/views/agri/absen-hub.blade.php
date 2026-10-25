@extends('layouts.utama')

@section('judul', 'Absen Agrinashub')
@section('isi')
@php
  $fmt = function (?int $detik): string {
      if ($detik === null) return '—';
      $j = intdiv($detik, 3600);
      $m = intdiv($detik % 3600, 60);
      return $j > 0 ? $j.'j '.$m.'m' : $m.'m';
  };
  $boleh = $status['boleh'];
  $aksiLabel = [
      'masuk' => ['Masuk', 'Mulai hari kerja — foto & titik lokasi wajib.'],
      'istirahat_mulai' => ['Mulai Istirahat', 'Istirahat dihitung otomatis dan dikurangkan dari jam kerja.'],
      'istirahat_selesai' => ['Selesai Istirahat', 'Kembali bekerja — jam istirahat berhenti dihitung.'],
      'keluar' => ['Keluar', 'Menutup hari kerja — foto & titik lokasi wajib.'],
  ];
  $labelJenis = \App\Models\AgriAbsenHub::JENIS;
@endphp

<header class="kepala">
  <div>
    <h1>Absen Agrinashub</h1>
    <p class="tenang">Absen milik sistem kita sendiri — tidak bergantung pada SIMKOPDES maupun AgriConnect.
      Istirahat tercatat, jam kerja bersih dihitung otomatis.</p>
  </div>
  <span class="lencana {{ $status['keluar'] ? 's-SELESAI' : ($status['masuk'] ? 's-DIAJUKAN' : 's-BARU') }}">
    {{ $status['keluar'] ? 'Hari kerja selesai' : ($status['masuk'] ? 'Sedang bekerja' : 'Belum masuk') }}
  </span>
</header>

{{-- Angka pokok: yang dicari orang saat membuka halaman ini. --}}
<div class="petak">
  <div class="kartu angka" data-kartu-kerja>
    <small>Jam kerja bersih</small>
    <b data-jam-kerja>{{ $fmt($status['kerjaDetik']) }}</b>
    <p class="bantuan">
      @if ($status['masuk'] && ! $status['keluar'])
        berjalan sejak {{ $status['masuk']->format('H:i') }}
      @elseif ($status['masuk'] && $status['keluar'])
        {{ $status['masuk']->format('H:i') }} – {{ $status['keluar']->format('H:i') }}
      @elseif ($status['keluar'])
        {{-- Data cacat: ada 'keluar' tanpa 'masuk'. Ditampilkan apa adanya, TIDAK
             dibiarkan melempar error — satu baris rusak tidak boleh mematikan
             seluruh halaman kehadiran. --}}
        keluar {{ $status['keluar']->format('H:i') }} (tanpa catatan masuk)
      @else
        belum dimulai
      @endif
    </p>
  </div>
  <div class="kartu angka">
    <small>Istirahat</small>
    <b data-jam-istirahat>{{ $fmt($status['istirahatDetik']) }}</b>
    <p class="bantuan">
      {{ $status['jumlahIstirahat'] }} kali
      @if ($status['istirahatBerjalan']) · <strong>sedang istirahat</strong> @endif
    </p>
  </div>
  <div class="kartu angka"><small>Hadir {{ $bulan }}</small>
    <b>{{ $rekap['hariHadir'] }}</b>
    <p class="bantuan">hari tercatat di absen ini</p>
  </div>
  <div class="kartu angka"><small>Rata-rata masuk</small>
    <b>{{ $rekap['rataMasuk'] }}</b>
    <p class="bantuan">{{ $rekap['terlambat'] }} kali lewat 08:00</p>
  </div>
</div>

<div class="dua-kolom">
  <div>
    {{-- ---------------- Tombol absen ---------------- --}}
    <section class="blok">
      <div class="kepala-blok">
        <h2>Absen hari ini</h2>
        <span class="pudar">{{ \Carbon\Carbon::parse($hari)->locale('id')->translatedFormat('l, d F Y') }}</span>
      </div>

      @if (session('pesan'))
        <p class="notis notis-ok">{{ session('pesan') }}</p>
      @endif
      @if (session('galat'))
        <p class="notis notis-galat">{{ session('galat') }}</p>
      @endif

      @if (empty($boleh))
        <p class="notis notis-ok">
          Absen hari ini sudah lengkap. Sampai jumpa besok.
        </p>
      @else
        <form method="post" action="{{ route('agri.absen-hub.simpan') }}"
              data-absen data-url-unggah="{{ route('agri.absen-hub.url') }}">
          @csrf
          <input type="hidden" name="jenis" value="{{ $boleh[0] }}">
          <input type="hidden" name="waktu" value="">
          <input type="hidden" name="zona" value="">
          <input type="hidden" name="lat" value="">
          <input type="hidden" name="lng" value="">
          <input type="hidden" name="akurasi" value="">
          <input type="hidden" name="lokasi" value="">
          <input type="hidden" name="foto_kunci" value="">
          <input type="hidden" name="foto_data" value="">

          {{-- Pilihan jenis: kalau ada dua jalan (istirahat atau keluar), orang
               harus MEMILIH — bukan ditebak oleh sistem. --}}
          @if (count($boleh) > 1)
            <div class="pilih-aksi" role="group" aria-label="Pilih jenis absen">
              @foreach ($boleh as $j)
                <label class="pilih-aksi-butir">
                  <input type="radio" name="pilih_jenis" value="{{ $j }}"
                         @checked($loop->first)>
                  <span>
                    <strong>{{ $aksiLabel[$j][0] }}</strong>
                    <small>{{ $aksiLabel[$j][1] }}</small>
                  </span>
                </label>
              @endforeach
            </div>
          @else
            <p class="bantuan">{{ $aksiLabel[$boleh[0]][1] }}</p>
          @endif

          <label>Catatan <span class="pudar">(opsional)</span>
            <input name="catatan" maxlength="200" placeholder="Mis. kunjungan koperasi binaan">
          </label>

          <div class="bukti-baris">
            <div class="bukti-kotak">
              <span class="label-kamera">Foto</span>
              <div class="kamera-bingkai">
                <video data-pratinjau autoplay playsinline muted aria-label="Pratinjau kamera"></video>
                <img data-potret class="potret" alt="Foto yang baru diambil" hidden>
                <p class="bantuan kamera-pesan" data-kamera-pesan role="status">Menyiapkan kamera…</p>
              </div>
              <div class="tombol-baris tombol-tengah">
                <button class="tombol utama" type="button" data-jepret>Ambil Foto</button>
                <button class="tombol samar" type="button" data-ulang hidden>Ulangi</button>
              </div>
            </div>

            <div class="bukti-kotak">
              <span class="label-kamera">Titik lokasi</span>
              <div class="lokasi-kotak">
                <p class="bantuan" data-lokasi-status>Belum diambil.</p>
                <dl class="lokasi-rinci" data-lokasi-rinci hidden>
                  <div><dt>Koordinat</dt><dd data-lokasi-koord>—</dd></div>
                  <div><dt>Akurasi</dt><dd data-lokasi-akurasi>—</dd></div>
                </dl>
              </div>
              <div class="tombol-baris tombol-tengah">
                <button class="tombol samar" type="button" data-lokasi>Ambil titik lokasi</button>
              </div>
            </div>
          </div>

          {{-- Tombol TIDAK ber-atribut `disabled`. Tombol mati yang tidak bisa ditekan
               tidak memberi tahu apa yang kurang: pengguna hanya melihat tombol kelabu,
               menekannya berulang, lalu menyimpulkan aplikasinya rusak. Sekarang klik
               SELALU bereaksi — kalau belum lengkap, yang muncul adalah alasannya dan
               halaman menggulir ke bagian yang kurang. --}}
          <p class="bantuan" data-siap-status aria-live="polite">Belum lengkap.</p>
          <button class="tombol utama lebar" type="submit" data-kirim>
            Simpan Absen
          </button>
        </form>
      @endif
    </section>

    {{-- ---------------- Riwayat: bulan + tahun, 10 hari per halaman ---------------- --}}
    {{--
      Saring memakai DUA dropdown (bulan & tahun), bukan satu pemilih bulan
      bawaan peramban: di ponsel pemilih itu membuka dialog sistem yang berbeda
      di tiap perangkat, sementara dua dropdown selalu sama dan lebih cepat
      dipakai. Formulirnya tetap bekerja tanpa JavaScript (GET biasa), dan
      dengan JavaScript hasilnya dimuat lewat AJAX tanpa memuat ulang halaman.
    --}}
    <section class="blok" id="riwayat" data-riwayat
             data-url="{{ route('agri.absen-hub.riwayat') }}">
      <div class="kepala-blok">
        <h2>Riwayat kehadiran</h2>
        <span class="pudar">{{ $riwayat['bulanTeks'] }}</span>
      </div>

      <form class="saring saring-riwayat" method="get" action="{{ route('agri.absen-hub') }}"
            data-riwayat-saring>
        <label>
          <span class="saring-label">Bulan</span>
          <select name="bln">
            @foreach ($bulanPilihan as $angka => $nama)
              <option value="{{ $angka }}" @selected((int) substr($bulan, 5, 2) === $angka)>{{ $nama }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span class="saring-label">Tahun</span>
          <select name="tahun">
            @foreach ($tahunPilihan as $th)
              <option value="{{ $th }}" @selected((int) substr($bulan, 0, 4) === $th)>{{ $th }}</option>
            @endforeach
          </select>
        </label>
        <button class="tombol mini" type="submit">Lihat</button>
      </form>

      <div class="riwayat-isi" data-riwayat-isi aria-live="polite" aria-busy="false">
        @include('agri._absen-riwayat', $riwayat)
      </div>
    </section>
  </div>

  {{-- ---------------- Linimasa hari ini ---------------- --}}
  <div>
    <section class="blok">
      <h2>Linimasa hari ini</h2>
      @if ($kejadian->isEmpty())
        <p class="bantuan">Belum ada kejadian. Tekan Masuk untuk memulai.</p>
      @else
        <ol class="linimasa">
          @foreach ($kejadian as $k)
            <li class="linimasa-butir linimasa-{{ $k->jenis }}">
              <span class="linimasa-jam">{{ $k->waktu }}</span>
              <span class="linimasa-isi">
                <strong>{{ $labelJenis[$k->jenis] ?? $k->jenis }}</strong>
                @if (trim((string) $k->catatan) !== '')
                  <small>{{ $k->catatan }}</small>
                @endif
                @if (trim((string) $k->lokasi) !== '')
                  <small>{{ $k->lokasi }}</small>
                @endif
                @if ($k->akurasi)
                  <small>akurasi ±{{ $k->akurasi }} m</small>
                @endif
              </span>
            </li>
          @endforeach
        </ol>
      @endif
    </section>

    <section class="blok">
      <h2>Cara kerja absen ini</h2>
      <ul class="daftar-ringkas">
        <li><strong>Kejadian, bukan dua kolom.</strong> Masuk, istirahat, dan keluar dicatat terpisah,
          jadi istirahat tidak hilang dan jam kerja bersih bisa dihitung.</li>
        <li><strong>Foto & lokasi wajib</strong> saat masuk dan keluar — bukti kehadiran, bukan sekadar jam.</li>
        <li><strong>Foto tidak lewat server.</strong> Peramban mengirimnya langsung ke penyimpanan,
          lalu server hanya menyimpan nama berkasnya.</li>
        <li><strong>Tidak bergantung layanan lain.</strong> Kalau SIMKOPDES atau AgriConnect bermasalah,
          absen ini tetap mencatat.</li>
      </ul>
    </section>
  </div>
</div>



{{-- ---------------- Modal bukti foto: masuk & pulang ---------------- --}}
{{--
  Satu modal untuk SEMUA baris riwayat, bukan satu modal per baris. Sepuluh baris
  x dua foto berarti dua puluh dialog di DOM bila dibuat per baris — dan isinya
  sama-sama kosong sampai dibuka. Di sini <img> diisi saat tombol ditekan, jadi
  tidak ada satu pun permintaan foto sebelum pengguna benar-benar memintanya.

  Tombol tutup ada DUA: "Tutup" di kaki (jelas & mudah dijangkau jempol) dan
  tombol X di kepala (kebiasaan umum). Esc juga menutup karena ini <dialog>
  bawaan peramban — bukan div yang meniru modal.
--}}
<dialog class="modal" id="modal-foto-absen" aria-labelledby="judul-foto-absen">
  <div class="modal-kepala">
    <h2 id="judul-foto-absen">Bukti foto absen</h2>
    <button type="button" class="tombol mini samar tombol-x" data-tutup aria-label="Tutup">&times;</button>
  </div>

  <div class="modal-badan">
    <p class="bantuan" data-foto-tanggal></p>

    <div class="foto-pasangan">
      <figure class="foto-kotak" data-kotak="masuk">
        <figcaption>
          <strong>Masuk</strong>
          <span data-foto-jam-masuk>—</span>
        </figcaption>
        <div class="foto-bingkai">
          <img data-foto-img-masuk alt="Foto absen masuk" hidden>
          <p class="foto-kosong" data-foto-kosong-masuk>Belum ada foto masuk.</p>
        </div>
      </figure>

      <figure class="foto-kotak" data-kotak="keluar">
        <figcaption>
          <strong>Pulang</strong>
          <span data-foto-jam-keluar>—</span>
        </figcaption>
        <div class="foto-bingkai">
          <img data-foto-img-keluar alt="Foto absen pulang" hidden>
          <p class="foto-kosong" data-foto-kosong-keluar>Belum ada foto pulang.</p>
        </div>
      </figure>
    </div>
  </div>

  <div class="modal-kaki">
    <button type="button" class="tombol samar" data-tutup>Tutup</button>
  </div>
</dialog>

@push('skrip')
<script src="/js/hub-absen.js?v={{ filemtime(public_path('js/hub-absen.js')) }}" defer></script>
<script src="/js/hub-absen-riwayat.js?v={{ filemtime(public_path('js/hub-absen-riwayat.js')) }}" defer></script>
@endpush
@endsection

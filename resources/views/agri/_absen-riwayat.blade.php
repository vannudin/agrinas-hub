{{--
  Isi riwayat kehadiran: tabel di layar lebar, kartu di ponsel.

  Satu markup untuk dua bentuk. Di ponsel setiap <tr> menjadi kartu dan setiap
  <td> menampilkan labelnya sendiri lewat `data-label` — tanpa tabel kedua yang
  harus dirawat terpisah, dan tanpa gulir horizontal yang membuat enam kolom
  tidak terbaca sebagai satu hari kerja.

  Berkas ini dipakai DUA cara: di-@include saat halaman pertama dirender server,
  dan dikirim sebagai HTML lewat JSON saat AJAX meminta halaman berikutnya.
  Keduanya karena itu menghasilkan markup yang sama persis.
--}}
@php
  $tautan = fn (int $n) => route('agri.absen-hub', ['bulan' => $bulan, 'halaman' => $n]);

  // Nomor halaman yang ditampilkan: jendela 5 angka di sekitar halaman aktif.
  $mulai = max(1, $halaman - 2);
  $akhir = min($totalHalaman, $mulai + 4);
  $mulai = max(1, $akhir - 4);
  $angka = $mulai <= $akhir ? range($mulai, $akhir) : [];
@endphp

@if ($total === 0)
  <p class="bantuan riwayat-kosong">
    Belum ada absen pada {{ $bulanTeks }}. Pilih bulan atau tahun lain di atas.
  </p>
@else
  <div class="riwayat-tabel">
    <table>
      <caption class="sr">Riwayat absen {{ $bulanTeks }}, halaman {{ $halaman }} dari {{ $totalHalaman }}</caption>
      <thead>
        <tr>
          <th scope="col">Tanggal</th>
          <th scope="col">Masuk</th>
          <th scope="col">Keluar</th>
          <th scope="col">Kerja bersih</th>
          <th scope="col">Istirahat</th>
          <th scope="col">Status</th>
          <th scope="col">Bukti</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($baris as $r)
          <tr>
            <th scope="row" class="riwayat-tanggal" data-label="Tanggal">
              <b>{{ $r['tanggalTeks'] }}</b>
              <small>{{ $r['hariTeks'] }}</small>
            </th>
            <td data-label="Masuk" class="riwayat-angka">{{ $r['masukTeks'] }}</td>
            <td data-label="Keluar" class="riwayat-angka">{{ $r['keluarTeks'] }}</td>
            <td data-label="Kerja bersih" class="riwayat-angka">{{ $r['kerjaTeks'] }}</td>
            <td data-label="Istirahat" class="riwayat-angka">
              {{ $r['istirahatTeks'] }}
              @if ($r['jumlahIstirahat'] > 0)
                <small>{{ $r['jumlahIstirahat'] }}×</small>
              @endif
            </td>
            <td data-label="Status">
              <span class="lencana {{ $r['statusKelas'] }}">{{ $r['statusTeks'] }}</span>
            </td>
            <td data-label="Bukti">
              @php
                $fm = $r['fotoMasukId'] ?? null;
                $fk = $r['fotoKeluarId'] ?? null;
              @endphp
              @if ($fm || $fk)
                {{-- <button>, bukan <a target="_blank">: foto absen adalah bukti
                     milik hari itu, dan membukanya di tab baru meninggalkan
                     pengguna dengan tab yatim yang harus ditutup sendiri. Modal
                     menjaga konteks: tanggal, jam, dan dua foto terlihat
                     bersamaan sehingga bisa dibandingkan. --}}
                <button type="button" class="tombol mini samar" data-foto-absen
                        data-tanggal="{{ $r['tanggalTeks'] }}"
                        @if ($fm) data-masuk="{{ route('agri.absen-hub.foto', ['id' => $fm]) }}"
                                  data-masuk-jam="{{ $r['fotoMasukJam'] }}" @endif
                        @if ($fk) data-keluar="{{ route('agri.absen-hub.foto', ['id' => $fk]) }}"
                                  data-keluar-jam="{{ $r['fotoKeluarJam'] }}" @endif>
                  Foto
                </button>
              @else
                <span class="pudar">—</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="riwayat-kaki">
    <p class="bantuan">
      Menampilkan {{ $dari }}–{{ $sampai }} dari {{ $total }} hari
      · halaman {{ $halaman }} dari {{ $totalHalaman }}
    </p>

    @if ($totalHalaman > 1)
      <nav class="paging" aria-label="Halaman riwayat">
        <a class="tombol mini samar {{ $halaman <= 1 ? 'tanpa-isi' : '' }}"
           data-halaman="{{ max(1, $halaman - 1) }}" href="{{ $tautan(max(1, $halaman - 1)) }}"
           @if ($halaman <= 1) aria-hidden="true" tabindex="-1" @endif>Sebelumnya</a>

        <ol class="paging-angka">
          @foreach ($angka as $n)
            <li>
              @if ($n === $halaman)
                <span aria-current="page">{{ $n }}</span>
              @else
                <a data-halaman="{{ $n }}" href="{{ $tautan($n) }}">{{ $n }}</a>
              @endif
            </li>
          @endforeach
        </ol>

        <a class="tombol mini samar {{ $halaman >= $totalHalaman ? 'tanpa-isi' : '' }}"
           data-halaman="{{ min($totalHalaman, $halaman + 1) }}"
           href="{{ $tautan(min($totalHalaman, $halaman + 1)) }}"
           @if ($halaman >= $totalHalaman) aria-hidden="true" tabindex="-1" @endif>Berikutnya</a>
      </nav>
    @endif
  </div>
@endif

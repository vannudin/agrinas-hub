{{--
  Padanan koordinat: data koperasi Hub ↔ pemetaan lahan KDKMP (pemetaan_lahan).

  KENAPA dicocokkan lewat teks, bukan kunci: `pemetaan_lahan` adalah cermin
  situs publik tingkat DESA (38.821 baris, kolom `desa`/`kecamatan`), sedangkan
  `koperasi` dikelola di Hub dengan kolom `wilayah` berbentuk "Desa X, Kec. Y,
  Kab. Z" — tidak ada id yang menautkan keduanya, dan menambah kolom relasi
  berarti mengubah dua sumber yang ditarik otomatis.

  Akurasi terukur (13 koperasi tertaut SIMKOPDES): nama desa+kecamatan cocok
  11/13. Yang gagal biasanya karena sumber menulis "Muarabakti (Muara Bakti)"
  atau hanya memuat nama desa tanpa kecamatan. Karena itu hasil di sini
  ditampilkan sebagai TEMUAN yang bisa diperiksa manusia — bukan dipakai untuk
  mengambil keputusan izin atau menghitung apa pun.

  Kalau `$padanan` kosong, blok ini TIDAK dirender sama sekali: lebih baik tidak
  ada daripada panel yang berbunyi "0 hasil" tanpa penjelasan.
--}}
@if (! empty($padanan))
  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('pemetaan', 'ik') !!}Padanan Pemetaan Lahan</span></h2>
    <p class="bantuan">
      Titik pemetaan lahan KDKMP yang wilayahnya sama dengan koperasi ini.
      Dicocokkan dari <b>nama desa + kecamatan</b> — koperasi
      <b>{{ $padanan['desa'] }}</b>{{ $padanan['kecamatan'] ? ', Kec. '.$padanan['kecamatan'] : '' }}.
      {{ $padanan['cocok'] }} titik ditemukan
      @if ($padanan['luas'] > 0) · total {{ number_format((float) $padanan['luas'], 0, ',', '.') }} m² @endif.
    </p>

    <div class="kop-peta">
      <div class="kop-peta-baris">
        <span>Desa dicari</span><b>{{ $padanan['desa'] ?: '—' }}</b>
      </div>
      <div class="kop-peta-baris">
        <span>Kecamatan</span><b>{{ $padanan['kecamatan'] ?: 'tidak disebut di nama koperasi' }}</b>
      </div>
      <div class="kop-peta-baris">
        <span>Titik selesai 100%</span>
        <b>{{ $padanan['selesai'] }} dari {{ $padanan['cocok'] }} titik
          @if ($padanan['mulai'] > 0) · {{ $padanan['mulai'] }} sedang dikerjakan @endif</b>
      </div>
      <div class="kop-peta-baris">
        <span>Total luas titik</span>
        <b>{{ number_format((float) $padanan['luas'], 0, ',', '.') }} m²</b>
      </div>
    </div>
    {{-- Penegasan yang mencegah panel ini dibaca sebagai progres koperasi:
         "Selesai 100%" di sini milik TITIK PEMETAAN DESA (situs publik), bukan
         progres bangunan koperasi — itu datang dari pekan SIMKOPDES dan bisa
         saja masih kosong untuk desa yang titiknya sudah ditandai selesai. --}}
    <p class="bantuan">
      Status di atas adalah status <b>titik pemetaan desa</b> dari portalkdkmp,
      bukan progres pembangunan koperasi ini. Progres bangunan koperasi dihitung
      dari pekan SIMKOPDES dan ditampilkan terpisah di halaman ini.
    </p>

    @if ($padanan['titik']->isNotEmpty())
      <div class="tabel-bungkus">
        <table>
          <thead><tr>
            <th>Babinsa</th><th>Desa</th><th>Kecamatan</th><th>Kabupaten</th>
            <th class="angka-kanan">Luas (m²)</th><th>Potensi</th><th>Pembangunan</th>
          </tr></thead>
          <tbody>
            @foreach ($padanan['titik'] as $t)
              <tr>
                <td data-l="Babinsa">{{ $t->nama ?: '—' }}</td>
                <td data-l="Desa">{{ $t->desa ?: '—' }}</td>
                <td data-l="Kecamatan">{{ $t->kecamatan ?: '—' }}</td>
                <td data-l="Kabupaten">{{ $t->kabupaten ?: '—' }}</td>
                <td data-l="Luas" class="angka-kanan">{{ number_format((float) $t->luas, 0, ',', '.') }}</td>
                <td data-l="Potensi">{{ $t->potensi ?: '—' }}</td>
                <td data-l="Pembangunan">
                  <span class="lencana s-{{ $t->statusKunci() }}">{{ $t->statusPembangunan() }}</span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="bantuan">
        Daftar penuh 38 ribu titik ada di <a href="{{ route('admin.pemetaan') }}">Pemetaan Lahan</a>.
      </p>
    @endif
  </section>
@endif

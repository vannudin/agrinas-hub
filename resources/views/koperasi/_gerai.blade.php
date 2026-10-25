{{--
  Bagian Gerai / Outlet — SATU naskah, dipakai halaman Data Koperasi (/kop) dan
  Sarana Prasarana (/kop/sarana) supaya CRUD-nya tidak bercabang dua.

  Gerai diisi SENDIRI oleh manajer: tarikan SIMKOPDES sudah diputus 2026-10-25,
  dan daftar gerai dulu hanya muncul bagi koperasi yang pernah ditarik. Sekarang
  manajer mencatat gerainya sendiri — tambah, ubah, hapus.

  Param: $koperasi, $gerai, $bolehTulis, $kembali ('data'|'sarana')
--}}
@php $kembali = $kembali ?? 'data'; @endphp

<section class="blok">
  <h2><span class="kop-judul">{!! ikon('toko', 'ik') !!}Gerai / Outlet</span></h2>
  <p class="bantuan">
    Gerai yang benar-benar ada di koperasi ini — diisi sendiri oleh manajer,
    bukan hasil tarikan data luar.
  </p>

  @if ($bolehTulis)
    <div class="tombol-baris">
      <button class="tombol utama ikon-kiri" type="button" data-buka="#gerai-baru">
        {!! ikon('toko', 'ik kecil') !!}<span>Tambah Gerai</span>
      </button>
      <span class="pudar kecil">Gerai yang sudah dibuka, bukan rencana.</span>
    </div>

    <dialog class="modal" id="gerai-baru" aria-labelledby="gerai-baru-judul">
      <header class="modal-kepala">
        <h2 id="gerai-baru-judul">Tambah Gerai / Outlet</h2>
        <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
      </header>
      <div class="modal-badan">
        @include('koperasi._form-gerai', [
          'aksi' => route('kop.gerai.simpan'),
          'gerai' => null,
          'tombol' => 'Simpan Gerai',
          'kembali' => $kembali,
        ])
      </div>
    </dialog>
  @endif

  @if ($gerai->isEmpty())
    <p class="kosong">
      Belum ada gerai yang dicatat.
      @if ($bolehTulis) Tekan <b>Tambah Gerai</b> untuk mencatat gerai pertama. @endif
    </p>
  @else
    <div class="tabel-bungkus">
      <table>
        <thead>
          <tr>
            <th>Nama</th><th>Jenis</th><th>Alamat</th><th>Telepon</th><th>Status</th>
            @if ($bolehTulis) <th class="kolom-aksi">Aksi</th> @endif
          </tr>
        </thead>
        <tbody>
          @foreach ($gerai as $g)
            <tr>
              <td data-l="Nama">{{ $g->nama ?: '—' }}</td>
              <td data-l="Jenis">{{ $g->jenis ?: '—' }}</td>
              <td data-l="Alamat">{{ $g->alamat ?: '—' }}</td>
              <td data-l="Telepon">{{ $g->telepon ?: '—' }}</td>
              <td data-l="Status">{{ $g->status ?: '—' }}</td>
              @if ($bolehTulis)
                {{-- Tanpa `data-l`: sel aksi bertombol memakai pola `.kolom-aksi`
                     yang sudah ada (sebaris di ponsel lewat aturan
                     `td.kolom-aksi:not([data-l])`), bukan grid label+nilai. --}}
                <td class="kolom-aksi">
                  <button class="tombol mini samar" type="button" data-buka="#gerai-{{ $g->id }}">Ubah</button>
                  <form method="post" action="{{ route('kop.gerai.hapus', ['id' => $g->id]) }}"
                        data-konfirmasi="Hapus gerai {{ $g->nama }}?">
                    @csrf @method('DELETE')
                    <input type="hidden" name="kembali" value="{{ $kembali }}">
                    <button class="tombol mini samar" type="submit">Hapus</button>
                  </form>
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @if ($bolehTulis)
      {{-- Satu dialog per gerai: pola yang sama dengan "Ubah Data Koperasi".
           Jumlah gerai per koperasi kecil (hitungan), jadi tidak perlu satu
           dialog bersama yang diisi JavaScript. --}}
      @foreach ($gerai as $g)
        <dialog class="modal" id="gerai-{{ $g->id }}" aria-labelledby="gerai-{{ $g->id }}-judul">
          <header class="modal-kepala">
            <h2 id="gerai-{{ $g->id }}-judul">Ubah Gerai</h2>
            <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
          </header>
          <div class="modal-badan">
            @include('koperasi._form-gerai', [
              'aksi' => route('kop.gerai.ubah', ['id' => $g->id]),
              'gerai' => $g,
              'tombol' => 'Simpan Perubahan',
              'kembali' => $kembali,
            ])
          </div>
        </dialog>
      @endforeach
    @endif
  @endif
</section>

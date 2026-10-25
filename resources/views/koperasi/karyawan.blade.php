@extends('layouts.utama')

@section('judul', 'Karyawan Koperasi')
@section('isi')
{{-- Halaman KARYAWAN — manajer mencatat orang yang benar-benar bekerja di
     koperasi ini (kasir, juru buku, operator gudang, dst). Terpisah dari Akun
     Hub: karyawan tidak harus punya akun untuk bisa dicatat. --}}
@include('koperasi._kepala', ['ruteBagian' => 'kop.karyawan'])

@if ($koperasi)
  @php
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
    $jmlAktif = $karyawan->where('aktif', 1)->count();
  @endphp

  @include('koperasi._nav', ['mod' => 'karyawan', 'koperasi' => $koperasi])

  <div class="petak">
    <div class="kop-stat">
      <span class="kop-stat-ikon">{!! ikon('hr', 'ik') !!}</span>
      <span class="kop-stat-teks">
        <small>Jumlah Karyawan</small>
        <b>{{ $rp($karyawan->count()) }}</b>
        <i>orang tercatat</i>
      </span>
    </div>
    <div class="kop-stat">
      <span class="kop-stat-ikon">{!! ikon('setuju', 'ik') !!}</span>
      <span class="kop-stat-teks">
        <small>Aktif Bekerja</small>
        <b>{{ $rp($jmlAktif) }}</b>
        <i>{{ $rp($karyawan->count() - $jmlAktif) }} tidak aktif</i>
      </span>
    </div>
  </div>

  <section class="blok">
    <h2><span class="kop-judul">{!! ikon('hr', 'ik') !!}Daftar Karyawan</span></h2>
    <p class="bantuan">
      Diisi sendiri oleh manajer koperasi. Karyawan di sini tidak otomatis punya
      akun Hub — akun dibuat terpisah oleh pengelola lewat menu Akun.
    </p>

    @if ($bolehTulis)
      <div class="tombol-baris">
        <button class="tombol utama ikon-kiri" type="button" data-buka="#karyawan-baru">
          {!! ikon('hr', 'ik kecil') !!}<span>Tambah Karyawan</span>
        </button>
      </div>

      <dialog class="modal" id="karyawan-baru" aria-labelledby="karyawan-baru-judul">
        <header class="modal-kepala">
          <h2 id="karyawan-baru-judul">Tambah Karyawan</h2>
          <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
        </header>
        <div class="modal-badan">
          @include('koperasi._form-karyawan', [
            'aksi' => route('kop.karyawan.simpan'),
            'karyawan' => null,
            'jabatan' => $jabatan,
            'tombol' => 'Simpan Karyawan',
          ])
        </div>
      </dialog>
    @endif

    @if ($karyawan->isEmpty())
      <p class="kosong">
        Belum ada karyawan yang dicatat.
        @if ($bolehTulis) Tekan <b>Tambah Karyawan</b> untuk mencatat yang pertama. @endif
      </p>
    @else
      <div class="tabel-bungkus">
        <table>
          <thead>
            <tr>
              <th>Nama</th><th>Jabatan</th><th>Telepon</th><th>Masuk</th><th>Status</th>
              @if ($bolehTulis) <th class="kolom-aksi">Aksi</th> @endif
            </tr>
          </thead>
          <tbody>
            @foreach ($karyawan as $k)
              <tr>
                <td data-l="Nama">{{ $k->nama }}
                  @if ($k->catatan !== '')<small class="pudar">{{ $k->catatan }}</small>@endif
                </td>
                <td data-l="Jabatan">{{ $k->jabatan ?: '—' }}</td>
                <td data-l="Telepon">{{ $k->telepon ?: '—' }}</td>
                <td data-l="Masuk">{{ $k->tanggal_masuk ? $k->tanggal_masuk->format('d M Y') : '—' }}</td>
                <td data-l="Status">
                  <span class="lencana {{ $k->aktif ? 's-DISETUJUI' : 's-DITOLAK' }}">{{ $k->aktif ? 'Aktif' : 'Tidak aktif' }}</span>
                </td>
                @if ($bolehTulis)
                  <td class="kolom-aksi">
                    <button class="tombol mini samar" type="button" data-buka="#karyawan-{{ $k->id }}">Ubah</button>
                    <form method="post" action="{{ route('kop.karyawan.hapus', ['id' => $k->id]) }}"
                          data-konfirmasi="Hapus karyawan {{ $k->nama }} dari daftar?">
                      @csrf @method('DELETE')
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
        @foreach ($karyawan as $k)
          <dialog class="modal" id="karyawan-{{ $k->id }}" aria-labelledby="karyawan-{{ $k->id }}-judul">
            <header class="modal-kepala">
              <h2 id="karyawan-{{ $k->id }}-judul">Ubah Karyawan</h2>
              <button class="tombol samar mini" type="button" data-tutup aria-label="Tutup">&times;</button>
            </header>
            <div class="modal-badan">
              @include('koperasi._form-karyawan', [
                'aksi' => route('kop.karyawan.ubah', ['id' => $k->id]),
                'karyawan' => $k,
                'jabatan' => $jabatan,
                'tombol' => 'Simpan Perubahan',
              ])
            </div>
          </dialog>
        @endforeach
      @endif
    @endif
  </section>
@endif
@endsection

{{--
  Baris tabel kontrak. Dipakai DUA jalur dengan markup SAMA: saat halaman
  dimuat penuh dan potongan AJAX saat cari/saring — satu berkas, jadi hasil
  keduanya mustahil berbeda.

  Kolomnya mengikuti tiga hal yang diminta: PERIODE (masa penugasan),
  PENEMPATAN (koperasi tempat bertugas), KONTRAK (nomor, status, tanggal).
--}}
@forelse ($kontrak as $k)
  <tr data-id="{{ $k->id }}">
    <td data-l="Pendamping">
      <span class="judul-sel">{{ $k->nama_pendamping }}</span>
      <small class="pudar">{{ $k->email_pendamping }}</small>
    </td>
    <td data-l="Periode">
      @if ($k->periode_mulai !== '')
        <span class="judul-sel">{{ tanggal_id($k->periode_mulai, 'd M Y') }}</span>
        <small class="pudar">s.d. {{ tanggal_id($k->periode_selesai, 'd M Y') }}</small>
      @else
        <span class="pudar">—</span>
      @endif
      @if ($k->periode_program !== '')
        <small class="pudar">{{ $k->periode_program }}</small>
      @endif
    </td>
    <td data-l="Penempatan">
      @if ($k->penempatan_nama !== '')
        <span class="judul-sel">{{ $k->penempatan_nama }}</span>
        <small class="pudar">{{ trim($k->penempatan_wilayah.($k->penempatan_provinsi !== '' ? ', '.$k->penempatan_provinsi : ''), ', ') }}</small>
        @if ($k->penempatan_mulai !== '')
          <small class="pudar">Tugas {{ tanggal_id($k->penempatan_mulai, 'd M Y') }} – {{ tanggal_id($k->penempatan_selesai, 'd M Y') }}</small>
        @endif
      @else
        <span class="pudar">Belum ditempatkan</span>
      @endif
    </td>
    <td data-l="Kontrak">
      @if ($k->kontrak_nomor !== '')
        <span class="judul-sel">{{ $k->kontrak_jenis }} · {{ $k->kontrak_nomor }}</span>
        @if ($k->kontrak_komitmen !== '')
          <small class="pudar">Pakta integritas: {{ $k->kontrak_komitmen }}</small>
        @endif
        {{-- Tautan tanda tangan duduk di kolom KONTRAK, bukan di kolom aksi:
             ia milik nomor kontraknya, dan menaruhnya di sana membuat kolom
             aksi cukup selebar satu tombol (dua tombol berdampingan memaksa
             tabel meluber di tablet). --}}
        @if ($k->kontrak_tautan !== '')
          <a class="peta-tautan" href="{{ $k->kontrak_tautan }}" target="_blank" rel="noopener">Buka tanda tangan</a>
        @endif
      @elseif ($k->kontrak_id !== null)
        <span class="pudar">Baris kontrak terbit, nomor belum ada</span>
      @else
        <span class="pudar">Belum diterbitkan</span>
      @endif
    </td>
    <td data-l="Status">
      @if ($k->kontrak_status !== '')
        <span class="lencana {{ \App\Models\Kontrak::statusKelas($k->kontrak_status) }}">{{ \App\Models\Kontrak::statusLabel($k->kontrak_status, (string) $k->kontrak_label) }}</span>
      @else
        <span class="lencana s-BARU">Belum ada kontrak</span>
      @endif
    </td>
    <td data-l="Tanggal">
      @if ($k->kontrak_mulai !== '')
        <span class="judul-sel">{{ tanggal_id($k->kontrak_mulai, 'd M Y') }}</span>
        <small class="pudar">s.d. {{ tanggal_id($k->kontrak_selesai, 'd M Y') }}</small>
      @else
        <span class="pudar">—</span>
      @endif
      @if ($k->kontrak_ttd !== '')
        <small class="pudar">Ditandatangani {{ tanggal_id($k->kontrak_ttd, 'd M Y') }}</small>
      @endif
    </td>
    <td data-l="Sumber">{{ $k->kontrak_sumber !== '' ? $k->kontrak_sumber : '—' }}</td>
    <td class="kolom-aksi">
      @if ((int) $k->user_id === (int) ($pemilik ?? 0) && $k->kontrak_id !== null)
        <a class="tombol utama mini" href="{{ route('kontrak.berkas', $k->id) }}">Unduh PDF</a>
      @else
        <span class="pudar">—</span>
      @endif
    </td>
  </tr>
@empty
  <tr><td colspan="8" class="kosong">
    @if ($kosong ?? false)
      Tidak ada kontrak yang cocok dengan saringan ini.
      <a href="{{ route('kontrak') }}">Tampilkan semua</a>.
    @else
      Belum ada data kontrak yang tersalin ke Hub. Kontrak ikut tersalin otomatis
      saat pendamping masuk lewat SIMKOPDES — atau tekan <b>Tarik Kontrak Saya</b>
      bila Anda akun pendamping.
    @endif
  </td></tr>
@endforelse

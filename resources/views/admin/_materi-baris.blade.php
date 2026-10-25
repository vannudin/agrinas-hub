{{--
  Baris tabel materi. Dipakai DUA jalur dengan markup SAMA: muat penuh dan
  potongan AJAX. Satu berkas, jadi tampilan hasil saring mustahil berbeda dari
  hasil muat ulang.

  Atribut kolom (`data-l`) tetap ada karena tabel berubah jadi kartu bertumpuk
  di ponsel — labelnya diambil dari situ.

  Sel aksi TIDAK memuat formulir sunting: satu tombol Unduh (tautan biasa) dan
  satu tombol Hapus. Formulir Hapus sengaja tetap sebaris di dalam sel supaya
  POST-nya tetap jalur server yang sama.
--}}
@forelse ($daftar as $m)
  <tr>
    <td data-l="Judul"><span class="judul-sel">{{ $m->judul }}</span>@if ($m->catatan)<small class="pudar">{{ $m->catatan }}</small>@endif</td>
    <td data-l="Kategori" data-kat="{{ $m->kategori }}"><span class="lencana s-DIAJUKAN">{{ $kategori[$m->kategori] ?? $m->kategori }}</span></td>
    <td data-l="Berkas"><span class="berkas-nama" title="{{ $m->nama_asli }}">{{ $m->nama_asli }}</span></td>
    <td data-l="Ukuran">{{ ukuran_id($m->ukuran) }}</td>
    <td data-l="Diunggah">{{ waktu_id($m->dibuat) }}</td>
    <td class="kolom-aksi">
      <a class="tombol samar mini" href="{{ route('materi.unduh', $m->id) }}">Unduh</a>
      <form method="post" action="{{ route('admin.materi.hapus', $m->id) }}" class="sebaris"
            onsubmit="return confirm('Hapus materi &quot;{{ $m->judul }}&quot;?')">
        @csrf<button class="tombol peringatan mini" type="submit">Hapus</button>
      </form>
    </td>
  </tr>
@empty
  <tr><td colspan="6" class="kosong">
    @if ($kosong ?? false)
      Tidak ada materi yang cocok dengan saringan ini.
      <a href="{{ route('admin.materi') }}">Tampilkan semua</a>.
    @else
      Belum ada materi — seret berkasnya ke kotak unggah di atas.
    @endif
  </td></tr>
@endforelse

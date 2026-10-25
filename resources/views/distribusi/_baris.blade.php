{{--
  Baris tabel distribusi. Dipakai DUA jalur dengan markup SAMA: tabel saat
  halaman dimuat penuh, dan potongan AJAX saat cari/filter/paging — satu berkas,
  jadi tampilan hasil AJAX mustahil berbeda dari hasil muat penuh.

  Tombol "Rincian" tetap <a href="…/distribusi/{id}"> biasa supaya tanpa
  JavaScript halamannya benar-benar terbuka (perilaku lama). Skrip distribusi
  yang mencegahnya lalu membuka modal.
--}}
@forelse ($daftar as $d)
  <tr data-id="{{ $d->id }}">
    <td data-l="Kode"><code>{{ $d->kode }}</code></td>
    <td data-l="Koperasi"><span class="judul-sel">{{ $d->koperasi }}</span>
      <small class="pudar">{{ $d->kode_koperasi }}</small></td>
    <td data-l="Item">{{ $d->jumlah_item }}</td>
    <td data-l="Status"><span class="lencana s-{{ $d->status }}">{{ \App\Models\Distribusi::label($d->status) }}</span></td>
    <td data-l="Diperbarui">{{ waktu_id($d->diperbarui) }}</td>
    <td class="kolom-aksi">
      <a class="tombol samar mini" href="{{ route('distribusi.rincian', $d->id) }}"
         data-rincian-buka="{{ $d->id }}"
         data-rincian-url="{{ route('distribusi.panel', $d->id) }}">Rincian</a>
    </td>
  </tr>
@empty
  <tr><td colspan="6" class="kosong">
    @if ($kosong ?? false)
      Tidak ada surat jalan yang cocok dengan saringan ini.
      <a href="{{ route('distribusi.daftar') }}">Tampilkan semua</a>.
    @else
      {{ ($agrinas ?? false) ? 'Belum ada pengajuan dari koperasi.' : 'Anda belum pernah mengajukan barang.' }}
    @endif
  </td></tr>
@endforelse

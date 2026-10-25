@extends('layouts.utama')

@section('judul', 'Konferensi')
@section('isi')
<header class="kepala">
  <div><h1>Konferensi Video</h1>
    <p class="tenang">Video Meeting &amp; Video Webinar langsung dari peramban — tanpa pemasangan aplikasi.</p></div>
</header>

<section class="blok">
  <h2>Jadwal Rapat
    <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }}</small>
  </h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Judul</th><th>Bentuk</th><th>Mulai</th><th>Selesai</th><th>Status</th><th>Catatan</th><th class="kolom-aksi"></th></tr></thead>
    <tbody>
    @forelse ($daftar as $r)
      @php
        // Aturan status satu tempat: KonferensiRapat::status().
        $st = $r->status();
        $dekat = $st === 'KINI';
      @endphp
      <tr>
        <td data-l="Judul"><span class="judul-sel">{{ $r->judul }}</span><small class="pudar">{{ $r->ruang }}</small></td>
        <td data-l="Bentuk"><span class="lencana">{{ $r->label_jenis() }}</span></td>
        <td data-l="Mulai">{{ $r->mulai_manusia() }}</td>
        <td data-l="Selesai">{{ $r->selesai ? tanggal_id($r->selesai, 'd M Y H:i') : '—' }}</td>
        <td data-l="Status">
          @if ($st === 'KINI')<span class="lencana s-SELESAI">Sedang berlangsung</span>
          @elseif ($st === 'SELESAI')<span class="lencana s-BARU">Selesai</span>
          @else<span class="lencana s-DIAJUKAN">Akan datang</span>@endif
        </td>
        <td data-l="Catatan">{{ $r->catatan ?: '—' }}</td>
        <td class="kolom-aksi"><a class="tombol utama mini" href="{{ route('rapat.ruang', $r->id) }}">Masuk Ruang</a></td>
      </tr>
    @empty
      <tr><td colspan="7" class="kosong">Belum ada jadwal rapat. Admin Agrinas belum menjadwalkan konferensi.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>
@endsection

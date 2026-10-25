@php
  // Baris tabel pelamar. Dipakai DUA jalur: halaman penuh (tab Rekrutmen) dan
  // potongan AJAX (HrdController::pelamar_semua) yang disaring hub-hr.js saat
  // mengetik. Satu partial = satu bentuk baris, tidak ada dua versi yang bisa
  // berbeda diam-diam.
  $kelas = $kelas ?? fn (string $s) => \App\Models\Domain::KELAS_SDM[$s] ?? 's-BARU';
  $q = $q ?? '';
@endphp
{{-- TIDAK disaring di sini: pemanggil sudah menyaring di SQL (RekrutmenPelamar::daftar).
     Menyaring di view membuat pelamar lama tak pernah ketemu (saringan jalan SETELAH limit). --}}
@forelse ($pelamar as $r)
  <tr>
    <td data-l="Pelamar">
      <span class="judul-sel">{{ $r->nama }}</span>
      <small class="pudar pecah">{{ $r->email ?: 'tanpa surel' }} @if ($r->telepon) · {{ $r->telepon }} @endif</small>
    </td>
    <td data-l="Dilamar">{{ $r->dilamar ? tanggal_id($r->dilamar) : '—' }}</td>
    <td data-l="Posisi">{{ $r->jabatan ?: '—' }}@if ($r->unit) <small class="pudar">{{ $r->unit }}</small>@endif</td>
    <td data-l="Sumber">{{ $r->sumber ?: '—' }}</td>
    <td data-l="Status">
      <span class="lencana {{ $kelas($r->status) }}">{{ $r->labelStatus() }}</span>
      @if ($r->user_id)<small class="pudar">akun #{{ $r->user_id }}</small>@endif
      @if ($r->diputus_oleh)<small class="pudar pecah">oleh {{ $r->diputus_oleh }}</small>@endif
    </td>
    <td class="kolom-aksi">
      <button class="tombol samar mini" type="button" data-buka="#hr-putus"
              data-judul-dialog="Keputusan — {{ $r->nama }}"
              data-lengkapi="{{ json_encode([
                'aksi' => route('hr.keputusan', $r->id), 'orang' => $r->nama,
                'status' => $r->status === 'MENUNGGU' ? 'DITERIMA' : $r->status,
                'catatan' => (string) $r->catatan,
              ], JSON_UNESCAPED_UNICODE) }}">Putuskan</button>
    </td>
  </tr>
@empty
  <tr><td colspan="6" class="kosong">
    @if ($q !== '') Tidak ada pelamar yang cocok.
    @else Belum ada pelamar tercatat. Tambahkan lewat tombol di kanan atas. @endif
  </td></tr>
@endforelse

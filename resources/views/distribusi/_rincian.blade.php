{{--
  Isi Surat Jalan: daftar barang, riwayat, dan aksi yang sedang berlaku.

  Dirender DUA kali dengan markup yang SAMA:

  * halaman penuh `/distribusi/{id}` — hasil kirim/ubah mengalihkan ke daftar;
  * potongan di modal `/distribusi` — `$di_modal = true`, aksi mengembalikan
    potongan yang sama sehingga modal memuat ulang isinya, bukan berpindah
    halaman. Karena satu partial dipakai dua-duanya, tombol dan aturan
    kewenangannya mustahil berbeda antara kedua jalur.

  Tombol aksi duduk di KAKI yang tetap terlihat (`.rincian-kaki`), sedangkan
  formulirnya di badan yang bisa digulir. `form="…"` pada tombol adalah cara
  bawaan HTML menghubungkan keduanya tanpa menyalin apa pun ke JavaScript.
--}}
@php
  $di_modal = $di_modal ?? false;
  $pemohon = $pemohon ?? null;
@endphp
<div class="rincian-panel" data-rincian="{{ $dis->id }}">

  <div class="rincian-badan">
    {{-- Pesan hasil aksi di dalam modal. Halaman penuh memakai flash session di
         layout, jadi blok ini hanya terisi di jalur potongan (lihat
         DistribusiController::balasRincian) — satu tempat, bukan dua. --}}
    @if (! empty($pesan ?? ''))
      <div class="pesan {{ ($kunci ?? 'pesan') === 'galat' ? 'rusak' : 'ok' }}"
           role="{{ ($kunci ?? 'pesan') === 'galat' ? 'alert' : 'status' }}">{{ $pesan }}</div>
    @endif

    <div class="rincian-judul">
      <div>
        <b>Surat Jalan <code>{{ $dis->kode }}</code></b>
        <small>{{ $dis->koperasi->nama ?? '—' }}</small>
      </div>
      <span class="lencana s-{{ $dis->status }}">{{ \App\Models\Distribusi::label($dis->status) }}</span>
    </div>

    <div class="tabel-bungkus">
    <table>
      <thead><tr><th>Barang</th><th>Diminta</th><th>Diterima</th><th>Satuan</th></tr></thead>
      <tbody>
      @foreach ($dis->item as $it)
        <tr>
          <td data-l="Barang"><span class="judul-sel">{{ $it->barang->nama ?? '—' }}</span><small class="pudar">{{ $it->barang->kode ?? '' }}</small></td>
          <td data-l="Diminta">{{ number_format($it->jumlah, 0, ',', '.') }}</td>
          <td data-l="Diterima">
            @if ($dis->status === 'DITERIMA')
              <b>{{ number_format($it->jumlah_diterima, 0, ',', '.') }}</b>
              @if ($it->jumlah_diterima < $it->jumlah)
                <span class="lencana s-REVISI">Kurang {{ number_format($it->jumlah - $it->jumlah_diterima, 0, ',', '.') }}</span>
              @endif
            @else
              —
            @endif
          </td>
          <td data-l="Satuan">{{ $it->barang->satuan ?? '' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
    </div>

    <h3 class="rincian-sub">Riwayat</h3>
    <ul class="linimasa">
      <li><b>Diajukan</b> — {{ waktu_id($dis->dibuat) }}
        @if ($pemohon)<small class="pudar">oleh {{ $pemohon->nama }}</small>@endif
        @if ($dis->catatan)<span class="bantuan">{{ $dis->catatan }}</span>@endif
      </li>
      @if ($dis->status === 'DIPROSES')
        <li><b>Mulai diproses Agrinas</b> — {{ waktu_id($dis->diperbarui) }}</li>
      @endif
      @if (in_array($dis->status, ['DIKIRIM', 'DITERIMA', 'DITOLAK'], true))
        <li><b>{{ $dis->status === 'DITOLAK' ? 'Ditolak Agrinas' : 'Dikirim Agrinas' }}</b> — {{ waktu_id($dis->diperbarui) }}
          @if ($dis->catatan_agrinas)<span class="bantuan">{{ $dis->catatan_agrinas }}</span>@endif
        </li>
      @endif
    </ul>

    @php
      // Opsi status: hanya tujuan yang SAH dari status sekarang (matriks
      // Distribusi::PINDAH) plus status yang sedang berlaku. Status mustahil
      // tak pernah muncul untuk dipilih — kesalahan dicegah sebelum dikirim,
      // bukan ditolak setelahnya.
      // DITERIMA sengaja TIDAK ditawarkan di select: ia butuh jumlah diterima
      // per barang. Jalurnya formulir penerimaan di bawah (koperasi pemohon).
      $bolehKe = array_values(array_diff(
          array_merge([$dis->status], \App\Models\Distribusi::PINDAH[$dis->status] ?? []), ['DITERIMA']));
      $pengawas = $agrinas;
      $bisaUbah = $pengawas && count($bolehKe) > 1;
    @endphp

    @if ($bisaUbah)
      <form id="f-status" method="post" action="{{ route('distribusi.status', $dis->id) }}" class="tumpuk">
        @csrf
        <label>Status surat jalan
          <select name="status" required>
            @foreach ($bolehKe as $st)
              <option value="{{ $st }}" @selected($st === $dis->status)>{{ \App\Models\Distribusi::label($st) }}</option>
            @endforeach
          </select>
        </label>
        <label>Keterangan
          <textarea name="keterangan" rows="2" maxlength="300"
                    placeholder="Opsional — wajib saat menolak (mis. stok kosong, dikirim sebagian)"></textarea>
        </label>
        <p class="bantuan">Menandai <b>Dalam Pengiriman</b> memotong stok gudang dan menerbitkan surat jalan yang bisa diunduh.</p>
      </form>
    @endif

    {{-- ---------------------------------------------------------- Manajer --}}
    @if (! $agrinas && $dis->boleh('DITERIMA'))
      <form id="f-terima" method="post" action="{{ route('distribusi.terima', $dis->id) }}" class="tumpuk">
        @csrf
        <p class="bantuan">Isi jumlah yang <b>benar-benar</b> diterima. Bila kurang, ubah angkanya — selisih tercatat di surat jalan.</p>
        <div class="tabel-bungkus">
          <table>
            <thead><tr><th>Barang</th><th>Dikirim</th><th class="kolom-aksi">Diterima</th></tr></thead>
            <tbody>
            @foreach ($dis->item as $it)
              <tr>
                <td data-l="Barang"><span class="judul-sel">{{ $it->barang->nama ?? '—' }}</span></td>
                <td data-l="Dikirim">{{ number_format($it->jumlah, 0, ',', '.') }} {{ $it->barang->satuan ?? '' }}</td>
                <td class="kolom-aksi" data-l="Diterima">
                  <input type="number" name="terima[{{ $it->id }}]" value="{{ $it->jumlah }}"
                         min="0" max="{{ $it->jumlah }}" step="1" inputmode="numeric"
                         aria-label="Jumlah diterima {{ $it->barang->nama ?? '' }}" required>
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
        <label>Catatan <span class="bantuan">(opsional — mis. 2 sak rusak)</span>
          <input type="text" name="catatan" maxlength="300"></label>
      </form>
    @endif
    @if ($pengawas && $dis->bolehUnduh())
      <p class="tombol-baris">
        <a class="tombol samar" href="{{ route('distribusi.pdf', $dis->id) }}">Unduh Surat Jalan (PDF)</a>
        <a class="tombol samar mini" href="{{ route('distribusi.surat-jalan', $dis->id) }}">Lihat &amp; Cetak</a>
      </p>
    @endif

  </div>

  {{-- Kaki aksi: satu tempat, tetap terlihat saat badan digulir. `data-kembali`
       menandai tombol pembatal — di halaman penuh ia tautan biasa; di modal
       skrip yang menutup dialognya. --}}
  <div class="rincian-kaki">
    @if ($bisaUbah ?? false)
      <button class="tombol utama" type="submit" form="f-status">Update</button>
    @endif
    @if (! $agrinas && $dis->boleh('DITERIMA'))
      <button class="tombol utama" type="submit" form="f-terima">Barang Sudah Diterima</button>
    @endif
    @if ($di_modal)
      <button class="tombol samar" type="button" data-kembali>Tutup</button>
    @else
      <a class="tombol samar" href="{{ route('distribusi.daftar') }}" data-kembali>Kembali</a>
    @endif
  </div>
</div>

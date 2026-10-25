{{--
  Baris tabel pasokan. Dipakai DUA jalur dengan markup SAMA: tabel saat halaman
  dimuat penuh, dan potongan AJAX saat cari/filter tanpa muat ulang. Satu berkas,
  jadi tampilan hasil AJAX mustahil berbeda dari hasil muat penuh.

  Sel digabung sekeluarga (Jumlah+Nilai, Status+Pembayaran): di ponsel setiap sel
  ber-`data-l` menambah satu baris, jadi 7 sel membuat satu pasokan setinggi hampir
  satu layar. Koperasi menempel di sel Kode (admin) — bukan kolom sendiri.
--}}
@forelse ($daftar as $p)
  @php
    $b = ($pengajuan ?? [])[$p->id] ?? null;
    $kali = ($pengajuanJumlah ?? [])[$p->id] ?? 0;
    // Dua wewenang terpisah, keduanya dihitung di controller (bukan di sini):
    //   $pengelola = memutus BARANGNYA (wilayah: pusat/HR/PIC)
    //   $pembayar  = memutus & membayar UANGNYA (pusat/PIC/keuangan)
    // Manajer tidak pernah keduanya — ia pihak yang mengajukan.
    $pengelola = $bolehPutus ?? ($agrinas ?? false);
    $pembayar = $bolehBayar ?? ($agrinas ?? false);
    $bolehKe = \App\Models\Pasokan::PINDAH[$p->status] ?? [];
  @endphp
  <tr data-id="{{ $p->id }}">
    <td data-l="Kode"><code>{{ $p->kode }}</code><small class="pudar">{{ tanggal_id($p->tanggal) }}</small>
      @if (($agrinas ?? false) && ($p->nama_koperasi ?? null))
        <small class="pudar">{{ $p->nama_koperasi }}@if ($p->kode_koperasi) · {{ $p->kode_koperasi }}@endif</small>
      @endif</td>
    {{-- UMKM & Komoditas digabung: keduanya satu cerita ("dari siapa, barang apa"),
         dan aturan tata letak Hub membatasi 4 sel ber-label per baris di ponsel —
         dengan 5 sel, satu pasokan jadi setinggi lebih dari satu layar. --}}
    <td data-l="UMKM &amp; Komoditas">
      <span class="judul-sel">{{ $p->umkm_nama }}</span>
      @if ($p->umkm_kontak)<small class="pudar">{{ $p->umkm_kontak }}</small>@endif
      @if ($p->umkm_alamat)<small class="pudar">{{ $p->umkm_alamat }}</small>@endif
      <div>{{ $p->komoditas }} <span class="tanda">{{ $p->labelJenis() }}</span></div>
      @if ($p->catatan)<small class="pudar">{{ $p->catatan }}</small>@endif
      @if ((int) $p->tayang === 1 && $p->status === 'DISETUJUI')
        <small class="pudar t-hijau">Tayang di Toko — {{ \App\Models\Keuangan::rupiah($p->hargaTawaran()) }} / {{ $p->satuan }} · sisa {{ number_format($p->sisaTayang(), 0, ',', '.') }} {{ $p->satuan }}</small>
      @endif
    </td>
    <td data-l="Jumlah &amp; Nilai">
      <b>{{ number_format($p->jumlah, 0, ',', '.') }} {{ $p->satuan }}</b>
      @if ($p->harga_satuan > 0)
        <small class="pudar">{{ \App\Models\Keuangan::rupiah($p->harga_satuan) }} / {{ $p->satuan }}</small>
      @endif
      <div>{{ \App\Models\Keuangan::rupiah($p->nilai()) }}</div>
    </td>
    <td data-l="Status &amp; Pembayaran">
      <span class="lencana s-{{ $p->status }}">{{ \App\Models\Pasokan::label($p->status) }}</span>
      @if ($b)
        <small class="pudar">
          {{ \App\Models\PasokanBayar::label($b->status) }}
          @if ($b->bank !== '') · {{ $b->bank }} {{ $b->no_rekening }}@endif
          @if ($kali > 1) · pengajuan ke-{{ $kali }}@endif
        </small>
        @if ($b->catatan !== '')<small class="pudar">{{ $b->catatan }}</small>@endif
      @else
        <small class="pudar">belum ada pengajuan bayar</small>
      @endif
      @if ($p->catatan_agrinas)<small class="pudar">{{ $p->catatan_agrinas }}</small>@endif
    </td>
    <td class="kolom-aksi">
      @php $adaAksi = false; @endphp

      {{-- Pengaju (manajer koperasi): ajukan pembayaran — rekening + invoice.
           Ditampilkan bila belum ada pengajuan berjalan. --}}
      @if (! $pengelola && ! $b && ($koperasi ?? null))
        <button class="tombol utama mini" type="button" data-buka="bayar-ajukan"
                data-judul-dialog="Ajukan Pembayaran {{ $p->kode }}"
                data-aksi="{{ route('pasokan.bayar.ajukan', $p->id) }}"
                data-awal="{{ json_encode(['ringkas' => $p->kode.' · '.$p->umkm_nama.' · '.\App\Models\Keuangan::rupiah($p->nilai())]) }}">Ajukan Bayar</button>
        @php $adaAksi = true; @endphp
      @endif

      {{-- Manajer pemilik pasokan: tayangkan produk lokalnya di /toko supaya
           bisa dipesan koperasi lain (order antar-koperasi).

           SATU tombol + satu <dialog>, bukan input langsung di kolom Aksi:
           kolom Aksi adalah sel ber-`data-l` terakhir, dan menaruh input angka
           di sana menambah satu baris per pasokan di ponsel — gate
           uji_pasokan_modal.py mengukurnya sebagai "kolom Aksi tidak bersih".
           Dialognya juga jadi tempat mengisi harga jual sekali jalan. --}}
      @if (! $pengelola && ($koperasi ?? null))
        @php $siap = $p->status === 'DISETUJUI'; $nyala = (int) $p->tayang === 1; @endphp
        <button class="tombol {{ $nyala ? 'samar' : 'utama' }} mini" type="button"
                data-buka="pasokan-tayang"
                data-judul-dialog="{{ $nyala ? 'Turunkan' : 'Tayangkan' }} {{ $p->kode }}"
                data-aksi="{{ route('pasokan.tayang', $p->id) }}"
                data-awal="{{ json_encode(['tayang' => $nyala ? '0' : '1', 'harga_jual' => (int) $p->harga_jual, 'ringkas' => $p->kode.' · '.$p->komoditas.' · '.number_format($p->jumlah, 0, ',', '.').' '.$p->satuan]) }}"
                @unless ($siap || $nyala) disabled title="Pasokan harus disetujui pengelola dulu" @endunless
                >{{ $nyala ? 'Turunkan' : 'Tayangkan' }}</button>
        @php $adaAksi = true; @endphp
      @endif

      {{-- Pengelola uang: setujui / tolak pengajuan yang masih berjalan. --}}
      @if ($pembayar && $b && $b->boleh('DISETUJUI'))
        <button class="tombol utama mini" type="button" data-buka="bayar-putus"
                data-judul-dialog="Keputusan Pengajuan {{ $p->kode }}"
                data-aksi="{{ route('pasokan.bayar.putus', $b->id) }}"
                data-awal="{{ json_encode(['ringkas' => $p->kode.' · '.$p->umkm_nama.' · '.\App\Models\Keuangan::rupiah($p->nilai()).' · '.$b->bank.' '.$b->no_rekening.' a.n. '.$b->atas_nama]) }}">Periksa</button>
        @php $adaAksi = true; @endphp
      @endif

      {{-- Pengelola uang: unggah bukti transfer setelah disetujui. --}}
      @if ($pembayar && $b && $b->boleh('DIBAYAR'))
        <button class="tombol utama mini" type="button" data-buka="bayar-bukti"
                data-judul-dialog="Bukti Pembayaran {{ $p->kode }}"
                data-aksi="{{ route('pasokan.bayar.bukti', $b->id) }}"
                data-awal="{{ json_encode(['ringkas' => 'Bayar '.\App\Models\Keuangan::rupiah($p->nilai()).' ke '.$b->bank.' '.$b->no_rekening.' a.n. '.$b->atas_nama]) }}">Bayar</button>
        @php $adaAksi = true; @endphp
      @endif

      {{-- Invoice RESMI pasokan: terbit otomatis dari kode pasokan, dan inilah
           dasar Agrinas membayar ke UMKM. Selalu ada (tidak bersyarat) — beda
           dengan lampiran dari UMKM di bawah, yang hanya muncul bila dikirim.
           Label sengaja pendek ("Invoice", bukan "Invoice PDF"): dengan label
           panjang kedua tombol tidak muat sebaris di kolom Aksi, selnya
           membungkus jadi dua baris dan tinggi baris naik 106px -> 143px
           (terukur). "Lampiran" di bawah sudah membedakannya dari berkas UMKM,
           dan `title` menjelaskan artinya untuk pembaca layar & tahan-sentuh. --}}
      <a class="tombol samar mini" target="_blank" rel="noopener"
         href="{{ route('pasokan.invoice.pdf', $p->id) }}"
         title="Invoice resmi {{ $p->nomorInvoice() }} — terbit otomatis, dasar pembayaran Agrinas ke UMKM"
         aria-label="Unduh invoice resmi {{ $p->nomorInvoice() }} (PDF)"
         >Invoice</a>
      @php $adaAksi = true; @endphp

      {{-- Lampiran yang dikirim UMKM. Diberi nama "Lampiran" supaya tidak
           tertukar dengan invoice terbitan Agrinas di atas. --}}
      @if ($b && $b->adaInvoice())
        <a class="tombol samar mini" href="{{ route('pasokan.bayar.berkas', [$b->id, 'invoice']) }}"
           title="Berkas tagihan dari UMKM (bukan invoice terbitan Agrinas)">Lampiran</a>
        @php $adaAksi = true; @endphp
      @endif
      @if ($b && $b->adaBukti())
        <a class="tombol samar mini" href="{{ route('pasokan.bayar.berkas', [$b->id, 'bukti']) }}">Bukti Bayar</a>
        @php $adaAksi = true; @endphp
      @endif

      {{-- Pengelola BARANG (wilayah): koreksi status pasokan. Bukan wewenang
           keuangan — ia membayar, bukan memutus barangnya. --}}
      @if ($pengelola && $bolehKe)
        <button class="tombol samar mini" type="button"
                data-pasokan="{{ route('pasokan.putus', $p->id) }}"
                data-kode="{{ $p->kode }}" data-umkm="{{ $p->umkm_nama }}"
                data-komoditas="{{ $p->komoditas }}"
                data-nilai="{{ \App\Models\Keuangan::rupiah($p->nilai()) }}"
                data-status="{{ $p->status }}"
                data-opsi="{{ implode(',', $bolehKe) }}"
                data-catatan="{{ $p->catatan_agrinas }}">Update</button>
        @php $adaAksi = true; @endphp
      @endif

      @unless ($adaAksi)
        <span class="pudar">—</span>
      @endunless
    </td>
  </tr>
@empty
  <tr><td colspan="5" class="kosong">
    @if ($kosong ?? false)
      Tidak ada pasokan yang cocok dengan saringan ini.
      <a href="{{ route('pasokan') }}">Tampilkan semua</a>.
    @else
      Belum ada catatan pasokan UMKM.
    @endif
  </td></tr>
@endforelse

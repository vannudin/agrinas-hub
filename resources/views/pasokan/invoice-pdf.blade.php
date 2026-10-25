{{--
  Isi invoice untuk PDF server (bukan HTML).

  Menerima `$p` = App\Support\Pdf (penulis dokumen) dan `$pas` = App\Models\Pasokan
  (datanya). DUA nama berbeda dengan sengaja: penulis PDF disuntik `Pdf::dariBlade`
  sebagai `$p`, jadi menamai modelnya `$p` juga akan membuat keduanya bertabrakan —
  gejalanya `Call to undefined method App\Models\Pasokan::teks()`. Surat jalan
  memakai pola yang sama (`$p` penulis, `$dis` modelnya).

  Nomor invoice TIDAK disimpan di dokumen ini sebagai teks bebas: ia diturunkan
  dari kode pasokan (`$pas->nomorInvoice()`), jadi nomor pada dokumen dan nomor
  pada baris tabel mustahil berbeda.
--}}
@php
  use App\Support\Pdf;
  use App\Models\Keuangan;
  $pas = $pas ?? null;
  $koperasi = $koperasi ?? null;
  $pemohon = $pemohon ?? null;
  // Pengajuan pembayaran terbaru untuk pasokan ini: dari sanalah rekening tujuan
  // dibaca. Bisa null — pasokan lama (sebelum alur pengajuan ada) tetap harus
  // bisa diterbitkan invoice-nya, dan rekeningnya ditandai belum diisi apa
  // adanya, bukan dikarang.
  $bayar = $bayar ?? null;
  $y = 46;
  $x = 40;
  $lebar = Pdf::LEBAR - 2 * $x;
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp

@php $p->teks($x, $y, 'PT AGRINAS PANGAN NUSANTARA (PERSERO)', 13, true); $y += 15; @endphp
@php $p->teks($x, $y, 'INVOICE - tagihan pembayaran pasokan UMKM', 9); $y += 18; @endphp
@php $p->garis($x, $y, $x + $lebar); $y += 20; @endphp

@php $p->teks($x, $y, 'Nomor invoice', 8.5); $p->teks($x + 150, $y, ': '.$pas->nomorInvoice(), 8.5, true); $y += 13; @endphp
@php $p->teks($x, $y, 'Tanggal', 8.5); $p->teks($x + 150, $y, ': '.tanggal_id((string) $pas->tanggal, 'd M Y'), 8.5); $y += 13; @endphp
@php $p->teks($x, $y, 'Status pasokan', 8.5); $p->teks($x + 150, $y, ': '.\App\Models\Pasokan::label($pas->status), 8.5); $y += 13; @endphp
@php $p->teks($x, $y, 'Kode pasokan', 8.5); $p->teks($x + 150, $y, ': '.$pas->kode, 8.5, true); $y += 13; @endphp
@php $p->teks($x, $y, 'Koperasi pembeli', 8.5); $p->teks($x + 150, $y, ': '.($koperasi->kode ?? '').' - '.($koperasi->nama ?? '-'), 8.5, true); $y += 13; @endphp
@php $p->teks($x, $y, 'Dicatat oleh', 8.5); $p->teks($x + 150, $y, ': '.(optional($pemohon)->nama ?? '-'), 8.5); $y += 22; @endphp

@php $p->teks($x, $y, 'DITERBITKAN UNTUK PEMBAYARAN KE:', 8.5, true); $y += 13; @endphp
@php $p->teks($x, $y, 'Nama UMKM', 8.5); $p->teks($x + 150, $y, ': '.$pas->umkm_nama, 8.5, true); $y += 12; @endphp
@if (trim((string) $pas->umkm_kontak) !== '')
  @php $p->teks($x, $y, 'Kontak', 8.5); $p->teks($x + 150, $y, ': '.$pas->umkm_kontak, 8.5); $y += 12; @endphp
@endif
@if (trim((string) $pas->umkm_alamat) !== '')
  @php $p->teks($x, $y, 'Alamat', 8.5); $p->teks($x + 150, $y, ': '.mb_substr((string) $pas->umkm_alamat, 0, 70), 8.5); $y += 12; @endphp
@endif
@php $rek = $bayar && (trim((string) $bayar->bank) !== '' || trim((string) $bayar->no_rekening) !== ''); @endphp
@if ($rek)
  @php $p->teks($x, $y, 'Rekening tujuan', 8.5); $p->teks($x + 150, $y, ': '.trim((string) $bayar->bank.' '.$bayar->no_rekening), 8.5, true); $y += 12; @endphp
  @if (trim((string) $bayar->atas_nama) !== '')
    @php $p->teks($x, $y, 'Atas nama', 8.5); $p->teks($x + 150, $y, ': '.$bayar->atas_nama, 8.5); $y += 12; @endphp
  @endif
@else
  @php $p->teks($x, $y, 'Rekening tujuan', 8.5); $p->teks($x + 150, $y, ': BELUM DIISI oleh manajer koperasi', 8.5); $y += 12; @endphp
@endif
@php $y += 10; @endphp

@php
  // Tabel rincian: satu baris untuk komoditas pasokan ini.
  $kolom = [$x + 6, $x + 250, $x + 330, $x + 410];
  $p->garis($x, $y, $x + $lebar);
  $p->teks($kolom[0], $y + 12, 'Komoditas', 9, true);
  $p->teks($kolom[1], $y + 12, 'Jumlah', 9, true);
  $p->teks($kolom[2], $y + 12, 'Harga satuan', 9, true);
  $p->teks($kolom[3], $y + 12, 'Nilai', 9, true);
  $y += 18;
  $p->garis($x, $y, $x + $lebar);
  $y += 4;
@endphp
@php
  $y += 14;
  $p->teks($kolom[0], $y, mb_substr((string) $pas->komoditas, 0, 38), 9.5);
  $p->teks($kolom[1], $y, $rp($pas->jumlah).' '.$pas->satuan, 9.5);
  $p->teks($kolom[2], $y, $pas->harga_satuan > 0 ? Keuangan::rupiah($pas->harga_satuan) : '-', 9.5);
  $p->teks($kolom[3], $y, Keuangan::rupiah($pas->nilai()), 9.5);
  $y += 6;
  $p->garis($x, $y, $x + $lebar);
  $y += 18;
  $p->teks($kolom[0], $y, 'JUMLAH DIBAYARKAN', 9.5, true);
  $p->teks($kolom[3], $y, Keuangan::rupiah($pas->nilai()), 10, true);
  $y += 20;
@endphp

@if (trim((string) $pas->catatan) !== '')
  @php $p->teks($x, $y, 'Catatan pasokan:', 8.5, true); $y += 12; @endphp
  @foreach (explode("\n", mb_substr(trim((string) $pas->catatan), 0, 200)) as $baris)
    @php $p->teks($x, $y, mb_substr($baris, 0, 95), 8.5); $y += 11; @endphp
  @endforeach
@endif

@php
  // Keterangan baku: apa yang dibayar dan oleh siapa. Ditulis di dokumen,
  // bukan hanya di layar, karena dokumen inilah yang ikut dicetak bendahara.
  $yy = max($y + 24, Pdf::TINGGI - 160);
  $p->garis($x, $yy - 6, $x + $lebar);
  $p->teks($x, $yy + 8, 'Dokumen ini diterbitkan otomatis oleh Agrinas Hub sebagai dasar', 8);
  $p->teks($x, $yy + 20, 'pembayaran Agrinas Pangan Nusantara kepada UMKM tersebut di atas.', 8);
  $p->teks($x, $yy + 40, 'Disetujui oleh', 9);
  $p->teks($x + 300, $yy + 40, 'Diterima oleh', 9);
  $p->garis($x, $yy + 102, $x + 220);
  $p->garis($x + 300, $yy + 102, $x + 520);
  $p->teks($x, $yy + 116, 'Keuangan Agrinas Pangan Nusantara', 9, true);
  $p->teks($x + 300, $yy + 116, mb_substr((string) $pas->umkm_nama, 0, 40), 9, true);
@endphp

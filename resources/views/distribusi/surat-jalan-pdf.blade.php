{{--
  Isi surat jalan untuk PDF server (bukan HTML).

  Menerima `$p` = App\Support\Pdf. View ini hanya MENENTUKAN letak baris dan garis;
  bentuk berkas PDF-nya ada di kelas Pdf. Dipisah begini supaya tata letak bisa
  disetel tanpa menyentuh penulis PDF-nya.
--}}
@php
  use App\Support\Pdf;
  // `pemohon` adalah kolom id pengguna, bukan relasi - controller yang
  // menerjemahkannya. Diberi nilai bawaan supaya view ini bisa dirender
  // sendirian (mis. dari uji) tanpa meledak.
  $pemohon = $pemohon ?? null;
  $y = 46;
  $x = 40;
  $lebar = Pdf::LEBAR - 2 * $x;
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp

@php $p->teks($x, $y, 'PT AGRINAS PANGAN NUSANTARA (PERSERO)', 13, true); $y += 15; @endphp
@php $p->teks($x, $y, 'SURAT JALAN - dokumen serah terima barang', 9); $y += 18; @endphp
@php $p->garis($x, $y, $x + $lebar); $y += 20; @endphp

@php $p->teks($x, $y, 'Nomor surat jalan', 8.5); $p->teks($x + 150, $y, ': '.$dis->kode, 8.5, true); $y += 13; @endphp
@php $p->teks($x, $y, 'Status', 8.5); $p->teks($x + 150, $y, ': '.$dis->label($dis->status), 8.5); $y += 13; @endphp
@php $p->teks($x, $y, 'Tanggal', 8.5); $p->teks($x + 150, $y, ': '.tanggal_id($dis->diperbarui, 'd M Y H:i'), 8.5); $y += 13; @endphp
@php $p->teks($x, $y, 'Koperasi tujuan', 8.5); $p->teks($x + 150, $y, ': '.($dis->koperasi->kode ?? '') . ' - ' . ($dis->koperasi->nama ?? '-'), 8.5, true); $y += 13; @endphp
@php $p->teks($x, $y, 'Alamat', 8.5); $p->teks($x + 150, $y, ': '.mb_substr((string) ($dis->koperasi->wilayah ?? '-'), 0, 70), 8.5); $y += 13; @endphp
@php $p->teks($x, $y, 'Diminta oleh', 8.5); $p->teks($x + 150, $y, ': '.(optional($pemohon)->nama ?? '-'), 8.5); $y += 22; @endphp

@php
  // Tabel: kepala + garis, lalu satu baris per item.
  $kolom = [$x + 6, $x + 300, $x + 380, $x + 460];
  $p->garis($x, $y, $x + $lebar);
  $p->teks($kolom[0], $y + 12, 'Nama barang', 9, true);
  $p->teks($kolom[1], $y + 12, 'Diminta', 9, true);
  $p->teks($kolom[2], $y + 12, 'Satuan', 9, true);
  $p->teks($kolom[3], $y + 12, 'ID', 9, true);
  $y += 18;
  $p->garis($x, $y, $x + $lebar);
  $y += 4;
@endphp
@foreach ($dis->item as $i)
  @php
    $y += 14;
    $p->teks($kolom[0], $y, mb_substr((string) ($i->barang->nama ?? '-'), 0, 52), 9.5);
    $p->teks($kolom[1], $y, $rp($i->jumlah), 9.5);
    $p->teks($kolom[2], $y, (string) ($i->barang->satuan ?? ''), 9.5);
    $p->teks($kolom[3], $y, (string) ($i->barang->kode ?? ''), 9);
  @endphp
@endforeach
@php
  $y += 10;
  $p->garis($x, $y, $x + $lebar);
  $p->teks($kolom[0], $y + 14, 'Jumlah jenis barang', 9, true);
  $p->teks($kolom[1], $y + 14, (string) $dis->item->count(), 9, true);
  $y += 34;
@endphp

@if (trim((string) $dis->catatan_agrinas) !== '')
  @php $p->teks($x, $y, 'Keterangan:', 9, true); $y += 12; @endphp
  @foreach (explode("\n", mb_substr(trim((string) $dis->catatan_agrinas), 0, 300)) as $baris)
    @php $p->teks($x, $y, mb_substr($baris, 0, 95), 9); $y += 12; @endphp
  @endforeach
  @php $y += 10; @endphp
@endif

@php
  // Blok tanda tangan: dua kolom, garis di atas nama.
  $yy = max($y + 30, Pdf::TINGGI - 150);
  $p->teks($x, $yy, 'Diserahkan oleh', 9);
  $p->teks($x + 300, $yy, 'Diterima oleh', 9);
  $p->garis($x, $yy + 62, $x + 220);
  $p->garis($x + 300, $yy + 62, $x + 300 + 220);
  $p->teks($x, $yy + 76, 'Gudang Agrinas Pangan Nusantara', 9, true);
  $p->teks($x + 300, $yy + 76, mb_substr((string) (optional($pemohon)->nama ?: ($dis->koperasi->nama ?? 'Manajer koperasi')), 0, 40), 9, true);
@endphp

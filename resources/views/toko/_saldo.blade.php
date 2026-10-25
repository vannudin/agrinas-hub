{{-- Kartu Saldo Agrinas. Sengaja menampilkan "tidak bisa ditarik" di kartunya,
     bukan hanya di dokumen: itu aturan uang, bukan catatan kaki. --}}
@if ($kop && ! empty($saldos[$kop->id]))
  @php $s = $saldos[$kop->id]; @endphp
  <div class="saldo-kartu">
    <div>
      <small>Saldo Agrinas · {{ $s['kode'] }}</small>
      <b>{{ \App\Models\Keuangan::rupiah($s['sisa']) }}</b>
      <p>Modal belanja awal {{ \App\Models\Keuangan::rupiah($s['awal']) }} ·
        terpakai {{ \App\Models\Keuangan::rupiah($s['terpakai']) }} ·
        <b>tidak dapat ditarik atau diuangkan</b></p>
    </div>
    <div class="tombol-baris">
      <a class="tombol samar" href="{{ route('toko.pesanan') }}">Riwayat pesanan</a>
    </div>
  </div>
@endif

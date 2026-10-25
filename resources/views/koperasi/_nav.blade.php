{{--
  Bilah modul Koperasi — dipakai keempat halaman (Data, Sarana, POS, Karyawan)
  supaya perpindahan antar bagian konsisten dan yang sedang dibuka ditandai.

  Label memuat kata, bukan hanya ikon: di ponsel tidak boleh ada informasi yang
  hilang. Ikon diambil dari peta yang sudah ada (Domain::IKON) — sengaja tidak
  menambah ikon baru untuk sesuatu yang bisa diwakili ikon lama.

  Param: $mod ('data'|'sarana'|'pos'|'karyawan'), $koperasi
--}}
<nav class="modul-nav modul-nav-4" aria-label="Modul Koperasi">
  @foreach ([['data', 'kop', 'koperasi', 'Data Koperasi'], ['sarana', 'kop.sarana', 'distribusi', 'Sarana Prasarana'], ['pos', 'kop.pos', 'keuangan', 'POS Penjualan'], ['karyawan', 'kop.karyawan', 'hr', 'Karyawan']] as [$kunci, $rute, $ik, $label])
    <a href="{{ route($rute, $koperasi ? ['koperasi' => $koperasi->id] : []) }}"
       class="{{ ($mod ?? '') === $kunci ? 'ini' : '' }}"
       {!! ($mod ?? '') === $kunci ? 'aria-current=page' : '' !!}>{!! ikon($ik, 'ik kecil') !!}<span>{{ $label }}</span></a>
  @endforeach
</nav>

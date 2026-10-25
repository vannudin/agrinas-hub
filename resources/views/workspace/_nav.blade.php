{{-- Bilah modul Ruang Kerja. Dipakai semua halaman modul supaya perpindahan
     antar modul konsisten; halaman yang sedang dibuka ditandai aria-current. --}}
<nav class="modul-nav" aria-label="Modul Ruang Kerja">
  <a href="{{ route('workspace') }}" class="{{ ($mod ?? '') === '' ? 'ini' : '' }}">{!! ikon('workspace', 'ik kecil') !!}<span>Ringkasan</span></a>
  @foreach (\App\Http\Controllers\WorkspaceController::modulTampil() as [$kunci, $rute, $label, $keterangan])
    <a href="{{ route($rute) }}" class="{{ ($mod ?? '') === $kunci ? 'ini' : '' }}"
       {!! ($mod ?? '') === $kunci ? 'aria-current=page' : '' !!} title="{{ $keterangan }}">
      {!! ikon($kunci, 'ik kecil') !!}<span>{{ $label }}</span>
    </a>
  @endforeach
</nav>

@extends('layouts.utama')

@section('judul', 'SIMKOPDES')
@section('isi')
<header class="kepala">
  <div>
    <h1>Integrasi SIMKOPDES</h1>
    <p class="tenang">Koperasi binaan akun pendamping ini, ditarik dari api.simkopdes.go.id.</p>
  </div>
</header>

<section class="blok">
  @if (! $siap)
    <div class="pesan ingat" role="status">
      <b>Belum tersambung.</b> Isi <code>SIMKOPDES_PASSWORD</code> (dan
      <code>SIMKOPDES_FACILITATOR_ID</code>) di berkas <code>.env</code> server, lalu
      jalankan <code>php artisan config:clear</code>. Setelah itu halaman ini bisa
      menarik data.
    </div>
  @endif

  <div class="kartu-baris">
    <div class="kartu angka"><small>Akun pendamping</small><b>{{ $email ?: '—' }}</b></div>
    <div class="kartu angka"><small>Facilitator ID</small><b>{{ $facilitator ?: '—' }}</b></div>
    <div class="kartu angka"><small>Status</small><b>{{ $siap ? 'Siap' : 'Belum diatur' }}</b></div>
  </div>

  @if ($siap)
    <form method="post" action="{{ route('admin.simkopdes.tarik') }}" class="tombol-baris">
      @csrf
      <button class="tombol samar" type="submit">Sambungkan &amp; uji</button>
      <button class="tombol utama" type="submit" name="terapkan" value="1">Tarik &amp; terapkan</button>
    </form>
    <p class="bantuan">Sambungkan = uji login &amp; tarik tanpa mengubah data. Terapkan =
      tulis hasilnya ke daftar koperasi hub (upsert per kode wilayah). Untuk penjadwalan,
      pakai <code>php artisan simkopdes:tarik</code>.</p>
  @endif
</section>

<section class="blok">
  <h2>Koperasi binaan di hub</h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Kode</th><th>Koperasi</th><th>Wilayah</th><th>Alamat</th><th>Status</th><th>Diperbarui</th></tr></thead>
    <tbody>
    @forelse ($koperasi as $k)
      <tr>
        <td data-l="Kode"><code>{{ $k->kode }}</code></td>
        <td data-l="Koperasi"><span class="judul-sel">{{ $k->nama }}</span></td>
        <td data-l="Wilayah">{{ $k->wilayah ?: '—' }}</td>
        <td data-l="Alamat">{{ $k->alamat ?: '—' }}</td>
        <td data-l="Status">{{ $k->status === 'AKTIF' ? 'Aktif' : $k->status }}</td>
        <td data-l="Diperbarui">{{ $k->diperbarui ? waktu_id($k->diperbarui) : '—' }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="kosong">Belum ada koperasi binaan. Tekan
        <b>Tarik &amp; terapkan</b> untuk mengambil dari SIMKOPDES.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</section>
@endsection

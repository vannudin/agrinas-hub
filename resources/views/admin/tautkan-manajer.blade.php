@extends('layouts.utama')

@section('judul', 'Tautkan Manajer')
@section('isi')
<header class="kepala">
  <div>
    <h1>Tautkan Manajer ke Koperasi</h1>
    <p class="tenang">Selama server SIMKOPDES tidak bisa dihubungi, tautan manajer &amp; koperasi
      tidak terbentuk otomatis. Tautkan di sini supaya fitur yang butuh data koperasi
      (keuangan, produk, stok, POS) aktif kembali untuk akun itu.</p>
  </div>
  <div class="tombol-baris">
    <a class="tombol samar" href="{{ route('admin.koperasi') }}">Daftar Koperasi</a>
  </div>
</header>

<section class="blok">
  <form class="saring" method="post" action="{{ route('admin.tautkan.simpan') }}">
    @csrf
    <label>
      <span class="sr">Akun manajer</span>
      <select name="user_id" required>
        <option value="">Pilih akun manajer…</option>
        @foreach ($tanpa as $t)
          <option value="{{ $t->id }}" @selected($pilihAkun === (int) $t->id)>
            {{ $t->nama }} — {{ $t->email }}@if (! $t->aktif) (nonaktif) @endif
          </option>
        @endforeach
      </select>
    </label>
    <label>
      <span class="sr">Koperasi</span>
      <select name="koperasi_id" required>
        <option value="">Pilih koperasi…</option>
        @foreach ($koperasi as $k)
          <option value="{{ $k->id }}">{{ $k->kode }} — {{ $k->nama }}@if ($k->wilayah) · {{ $k->wilayah }}@endif</option>
        @endforeach
      </select>
    </label>
    <button class="tombol" type="submit">Tautkan</button>
  </form>

  <p class="bantuan">
    <b>{{ number_format($jumlahTanpa, 0, ',', '.') }}</b> akun manajer belum tertaut ke koperasi.
    @if ($cari !== '')
      Menampilkan hasil pencarian “{{ $cari }}” ({{ $tanpa->count() }} akun, maksimal 100).
    @else
      Menampilkan {{ $tanpa->count() }} dari {{ number_format($jumlahTanpa, 0, ',', '.') }} akun —
      pakai pencarian untuk menemukan yang lain.
    @endif
    Satu akun hanya boleh menjadi manajer satu koperasi. Akun yang sudah tertaut
    dilepas dulu di tabel bawah sebelum ditempatkan ke koperasi lain.
  </p>

  {{-- Pencarian: daftar akun tanpa koperasi berisi puluhan ribu baris, jadi
       memilih nama dengan menggulir bukan pilihan. --}}
  <form class="saring" method="get" action="{{ route('admin.tautkan') }}">
    <label>
      <span class="sr">Cari akun manajer</span>
      <input name="q" value="{{ $cari }}" placeholder="Cari nama atau surel manajer…" maxlength="80">
    </label>
    <button class="tombol samar" type="submit">Cari</button>
    @if ($cari !== '')
      <a class="tombol samar" href="{{ route('admin.tautkan') }}">Reset</a>
    @endif
  </form>

  <div class="tabel-bungkus">
  <table class="tabel-koperasi">
    <thead><tr><th>Akun Manajer</th><th>Surel</th><th>Aksi</th></tr></thead>
    <tbody>
    @forelse ($tanpa as $t)
      <tr>
        <td data-l="Akun">{{ $t->nama }}@if (! $t->aktif) <span class="lencana samar">nonaktif</span> @endif</td>
        <td data-l="Surel"><small class="pudar">{{ $t->email }}</small></td>
        <td data-l="Aksi"><a class="tautan-mini" href="{{ route('admin.tautkan', ['akun' => $t->id]) }}">Pilih akun ini</a></td>
      </tr>
    @empty
      <tr><td colspan="3" class="kosong">Semua akun manajer sudah tertaut ke koperasi.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</section>

{{-- Koperasi yang SUDAH punya manajer. Dilepas di sini supaya akunnya bisa
     dipindah: selama tautan lama berdiri, akun itu tidak muncul di daftar
     pilihan atas dan koperasinya tidak bisa ditempati orang lain. --}}
<section class="blok">
  <h2>Koperasi yang sudah punya manajer</h2>
  <p class="bantuan">
    <b>{{ number_format($jumlahTertaut, 0, ',', '.') }}</b> koperasi sudah tertaut.
    Menampilkan {{ $tertaut->count() }} baris{{ $cari !== '' ? ' hasil pencarian' : '' }}.
    <b>Lepas Tautan</b> hanya memutus hubungan manajer–koperasi; data koperasi
    (stok, nota, aset, gerai) tidak dihapus.
  </p>
  <div class="tabel-bungkus">
  <table class="tabel-koperasi">
    <thead><tr><th>Koperasi</th><th>Manajer</th><th>Aksi</th></tr></thead>
    <tbody>
    @forelse ($tertaut as $k)
      <tr>
        <td data-l="Koperasi"><code>{{ $k->kode }}</code> — {{ $k->nama }}
          @if ($k->wilayah) <small class="pudar">{{ $k->wilayah }}</small> @endif</td>
        <td data-l="Manajer">
          @if ($k->manajer)
            {{ $k->manajer->nama }}
            <small class="pudar">{{ $k->manajer->email }}</small>
          @else
            <small class="pudar">akun manajer tidak ditemukan</small>
          @endif
        </td>
        <td data-l="Aksi" class="kolom-aksi">
          <form method="post" action="{{ route('admin.tautkan.lepas', ['id' => $k->id]) }}"
                data-konfirmasi="Lepas tautan manajer dari {{ $k->kode }}? Data koperasi tetap utuh; akun manajernya bisa ditempatkan ke koperasi lain.">
            @csrf
            <button class="tombol mini samar" type="submit">Lepas Tautan</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="3" class="kosong">Belum ada koperasi yang tertaut ke manajer.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</section>
@endsection

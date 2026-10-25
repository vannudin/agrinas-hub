{{--
  Tabel daftar generik untuk halaman AgriConnect HRIS.

  Kolom diturunkan dari DATA, bukan dipatok: kalau peta `$kolom` tidak memuat
  sebuah kunci, nama kuncinya dipakai apa adanya. Ini penting karena beberapa
  endpoint (dokumen, sertifikat, aset) kosong pada akun uji, jadi bentuk
  barisnya belum pernah terlihat — mematok kolom di situ menghasilkan tabel
  kosong yang tak bisa dibedakan dari "tidak ada data".

  $judul, $baris (list), $kolom (['kunci' => 'Label'])
--}}
@php
  $baris = is_array($baris ?? null) ? array_values(array_filter($baris, 'is_array')) : [];
  $kolom = $kolom ?? [];

  // Kunci yang benar-benar ada isinya di salah satu baris. Kolom teknis
  // (id/uuid/foreign key) DIBUANG: nilai seperti
  // "177bb311-6790-4ec6-806e-f7da48fe817c" tidak berguna bagi pegawai dan
  // membuat tabel Alamat/Pendidikan terbaca seperti dump database.
  $teknis = fn ($k) => (bool) preg_match('/(^id$|_id$|^uuid$|Id$)/', (string) $k);
  $pakai = [];
  foreach ($baris as $b) {
      foreach ($b as $k => $v) {
          if (is_array($v) || is_object($v)) continue;
          if ($teknis($k) && ! isset($kolom[$k])) continue;
          if (filled($v)) $pakai[$k] = true;
      }
  }

  // Judul kolom dari nama kunci: "workExperience" -> "Work experience".
  $label = function (string $k) use ($kolom): string {
      if (isset($kolom[$k])) return $kolom[$k];
      $t = preg_replace('/(?<=[a-z0-9])([A-Z])/', ' $1', $k);
      $t = str_replace('_', ' ', (string) $t);

      return ucfirst(strtolower(trim((string) $t)));
  };
  // Kolom yang dipakai: BILA peta `$kolom` diberikan, HANYA kunci di peta itu
  // yang tampil (dan hanya bila ada isinya). Dulu kunci lain ikut ditambahkan
  // otomatis sehingga tabel HRIS melebar dengan kolom mentah berbahasa Inggris
  // ("employee id", "file name") yang tidak dicari siapa pun — keluhan Bos
  // 2026-10-22: "kolom masih banyak yang nempel, tidak user friendly".
  $urut = array_values(array_filter(array_keys($kolom), fn ($k) => isset($pakai[$k])));
  if ($kolom === [] || ($urut === [] && $baris !== [])) {
      // Tanpa peta — atau peta tidak satupun cocok dengan bentuk data (endpoint
      // baru yang skemanya belum terlihat) — turunkan dari data, maks enam
      // kolom: tabel lebar tanpa label hanya bisa dibaca dengan menggulir.
      $urut = array_slice(array_keys($pakai), 0, 6);
  }
@endphp

<div class="blok">
  <div class="blok-kepala">
    <h2>{{ $judul }}</h2>
    @if ($baris !== [])<span class="pudar">{{ count($baris) }} baris</span>@endif
  </div>
  @if ($baris === [] || $urut === [])
    <p class="tenang">Belum ada data {{ strtolower($judul) }} pada akun ini.</p>
  @else
    <div class="tabel-bungkus">
      <table>
        <thead>
          <tr>@foreach ($urut as $k)<th>{{ $label($k) }}</th>@endforeach</tr>
        </thead>
        <tbody>
          @foreach ($baris as $b)
            <tr>
              @foreach ($urut as $k)
                <td data-l="{{ $label($k) }}">{{ $b[$k] ?? '—' }}</td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

@php
  // Data direksi dari docs/riset-direksi.json (hasil riset sumber resmi/Wikipedia).
  // Kalau berkas belum ada, seksi tetap tampil dengan keterangan menyusul —
  // bukan nama atau foto karangan.
  $berkas = base_path('docs/riset-direksi.json');
  $direksi = [];
  if (is_file($berkas)) {
      $isi = json_decode((string) file_get_contents($berkas), true);
      $direksi = is_array($isi['direksi'] ?? null) ? $isi['direksi'] : [];
  }

  // Terima path apa adanya ("public/img/..."), nama berkas saja, atau URL penuh.
  $fotoDari = function (?string $foto): ?string {
      if (! $foto) {
          return null;
      }
      if (preg_match('#^https?://#', $foto)) {
          return $foto;
      }
      $rel = preg_replace('#^public/#', '', ltrim($foto, '/'));
      foreach ([$rel, 'img/direksi/'.basename($rel)] as $kandidat) {
          if (is_file(public_path($kandidat))) {
              return '/'.$kandidat;
          }
      }

      return null;
  };

  $inisial = function (string $nama): string {
      $kata = preg_split('/\s+/', trim($nama));
      $huruf = array_map(fn ($k) => mb_substr($k, 0, 1), array_slice($kata, 0, 2));

      return mb_strtoupper(implode('', $huruf));
  };
@endphp

<section class="pub-bagian" id="direksi">
  <div class="pub-kepala-bagian">
    <p class="pub-eyebrow">Jajaran direksi</p>
    <h2>Yang memimpin perusahaan</h2>
    <p>Direksi PT Agrinas Pangan Nusantara (Persero) mengelola penugasan negara di
      sektor pangan dan konsultansi konstruksi.</p>
  </div>

  @if (count($direksi))
    {{-- Kartu profil mendatar (bukan potret 4/5): foto berdampingan dengan
         biodata, jadi tidak ada separuh kartu yang kosong. --}}
    <div class="pub-direksi">
      @foreach ($direksi as $d)
        @php
          $nama = trim((string) ($d['nama'] ?? ''));
          $foto = $fotoDari($d['foto'] ?? null);
        @endphp
        <article class="pub-orang">
          @if ($foto)
            <div class="foto">
              <img src="{{ $foto }}" alt="Foto {{ $nama }}" loading="lazy">
            </div>
          @else
            {{-- Tanpa foto resmi: tampilkan inisial, jangan pakai foto orang lain. --}}
            <div class="foto kosong" aria-hidden="true"><span>{{ $inisial($nama) }}</span></div>
          @endif
          <div class="orang-isi">
            <span class="orang-jabatan">{{ $d['jabatan'] ?? 'Direksi' }}</span>
            <h3>{{ $nama !== '' ? $nama : 'Data menyusul' }}</h3>
            @if (! empty($d['catatan']))
              <p>{{ $d['catatan'] }}</p>
            @endif
          </div>
        </article>
      @endforeach
    </div>
  @else
    <p class="pub-media-catatan">Data jajaran direksi sedang dilengkapi dari sumber resmi
      perusahaan. Halaman ini akan memuat nama dan foto begitu datanya terverifikasi.</p>
  @endif
</section>

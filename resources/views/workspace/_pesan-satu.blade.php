{{-- Satu gelembung pesan — SATU-SATUNYA tempat bentuk gelembung ditulis.

     Dipakai tiga jalur sekaligus: render halaman pertama (blade), gelombang
     "muat lebih lama", dan pesan baru dari denyut (controller merender partial
     ini jadi string HTML). Karena hanya ada satu bentuk, tidak mungkin ada dua
     perilaku yang diam-diam berbeda.

     Yang dibutuhkan: $p (larik dari WorkspaceController::muatanRuang()['pesan']),
     $ruang, $bahasa, $namaSebut, $lanjut, dan opsional $teruskanId (id <form>
     bersama untuk "Teruskan" — kolom pilihannya cukup satu di seluruh halaman). --}}
@php
  $saya = (bool) ($p['saya'] ?? false);
  $jenis = (string) ($p['jenis'] ?? 'teks');
  $ukuran = (int) ($p['lampiran_ukuran'] ?? 0);
  $kb = $ukuran >= 1048576 ? number_format($ukuran / 1048576, 1, ',', '.').' MB'
      : ($ukuran >= 1024 ? number_format($ukuran / 1024, 0, ',', '.').' KB' : $ukuran.' B');
  $adaIsi = trim((string) ($p['isi'] ?? '')) !== '';
  $lampiran = (string) ($p['lampiran'] ?? '');
  $tautanLampiran = $lampiran !== '' ? route('workspace.pesan.lampiran.unduh', $p['id']) : '';
  // Nama yang disebut diwarnai — teksnya TIDAK diubah (yang mengetik tetap
  // yang menentukan kalimatnya), hanya diberi penanda mata.
  $isiTampil = e((string) ($p['isi'] ?? ''));
  foreach (($namaSebut ?? []) as $nm) {
      $isiTampil = preg_replace('/@'.preg_quote(e($nm), '/').'\b/u',
          '<mark class="wa-sebut">@'.e($nm).'</mark>', $isiTampil) ?? $isiTampil;
  }
  $teruskanId = $teruskanId ?? 'wa-teruskan-form';
@endphp
<article class="wa-b {{ $saya ? 'dia' : '' }} {{ ! empty($p['sebut_saya']) ? 'sebut' : '' }}"
         id="p{{ $p['id'] }}" data-id="{{ $p['id'] }}" data-jenis="{{ $jenis }}"
         data-tanggal="{{ $p['tanggal'] ?? '' }}">
  @unless (! empty($lanjut))
    <span class="wa-nama">{{ $saya ? 'Anda' : ($p['nama'] ?? '') }}</span>
  @endunless

  @if (! empty($p['balas']))
    <a class="wa-kutip" href="#p{{ $p['balas']['id'] }}" data-ke="{{ $p['balas']['id'] }}">
      <b>{{ $p['balas']['nama'] }}</b><span>{{ $p['balas']['cuplikan'] }}</span>
    </a>
  @endif

  @if ($lampiran !== '')
    @if ($jenis === 'gambar')
      <a class="wa-foto" href="{{ $tautanLampiran }}" data-foto="{{ $tautanLampiran }}">
        <img src="{{ $tautanLampiran }}" alt="{{ $p['lampiran_nama'] ?: 'Foto' }}" loading="lazy">
      </a>
    @elseif ($jenis === 'suara')
      <div class="wa-suara">
        <audio controls preload="none" src="{{ $tautanLampiran }}"></audio>
        <small>{{ $kb }}</small>
      </div>
    @else
      <a class="wa-berkas" href="{{ $tautanLampiran }}">
        <span class="wa-berkas-ik" aria-hidden="true">📎</span>
        <span class="wa-berkas-teks"><b>{{ $p['lampiran_nama'] ?: 'Berkas' }}</b><small>{{ $kb }}</small></span>
      </a>
    @endif
  @endif

  @if (! empty($p['ditarik']))
    {{-- Pesan ditarik: gelembungnya TETAP ada supaya riwayat tidak berlubang
         (pembaca tahu ada sesuatu di antara dua pesan). Isinya sudah dimusnahkan
         saat penarikan, jadi yang tampil hanya keterangan. --}}
    <div class="wa-teks wa-ditarik"><em>Pesan ini telah ditarik</em></div>
  @elseif ($adaIsi)
    <div class="wa-teks">{!! nl2br($isiTampil) !!}</div>
  @endif

  @if (trim((string) ($p['terjemahan'] ?? '')) !== '')
    <div class="wa-terjemah"><small>Terjemahan ({{ $p['bahasa'] }})</small>{!! nl2br(e($p['terjemahan'])) !!}</div>
  @endif

  @if (! empty($p['reaksi']))
    <div class="wa-reaksi">
      @foreach ($p['reaksi'] as $rx)
        <button type="button" class="wa-rx {{ $rx['saya'] ? 'saya' : '' }}" data-emoji="{{ $rx['emoji'] }}"
                title="{{ $rx['saya'] ? 'Batalkan reaksi' : 'Ikut memberi reaksi' }}">{{ $rx['emoji'] }}<b>{{ $rx['jumlah'] }}</b></button>
      @endforeach
    </div>
  @endif

  <span class="wa-bawah">
    @if (! empty($p['disematkan']))<span class="wa-semat" title="Pesan disematkan">📌</span>@endif
    @if (! empty($p['suntingan']))<small class="wa-suntingan">disunting</small>@endif
    <time class="wa-jam" datetime="{{ $p['waktu'] }}">{{ $p['waktu'] }}</time>
    @if ($saya)
      <span class="wa-tanda {{ ! empty($p['dibaca']) ? 'dibaca' : '' }}"
            title="{{ ! empty($p['dibaca']) ? 'Dibaca' : 'Terkirim' }}">{{ ! empty($p['dibaca']) ? '✓✓' : '✓' }}</span>
    @endif
  </span>

  {{-- Alat per pesan: satu jalur untuk reaksi, sematkan, teruskan, sunting,
       tarik, terjemahkan. Tanpa JS pun berfungsi karena semuanya <form>. --}}
  <details class="wa-alat">
    <summary aria-label="Alat pesan" title="Balas, reaksi, sematkan, teruskan, sunting, tarik">⋯</summary>
    <div class="wa-alat-isi">
      <form method="post" action="{{ route('workspace.pesan.aksi', $ruang->id) }}" class="wa-rx-baris">
        @csrf
        <input type="hidden" name="aksi" value="reaksi">
        <input type="hidden" name="pesan_id" value="{{ $p['id'] }}">
        @foreach (\App\Models\ReaksiObrolan::PILIHAN as $e)
          <button class="tombol samar mini" type="submit" name="emoji" value="{{ $e }}"
                  aria-label="Beri reaksi {{ $e }}">{{ $e }}</button>
        @endforeach
      </form>

      <button class="tombol samar mini wa-balas" type="button" data-id="{{ $p['id'] }}"
              data-nama="{{ $p['nama'] }}" data-cuplikan="{{ \Illuminate\Support\Str::limit((string) ($p['isi'] ?? ''), 70) }}">Balas</button>

      <form method="post" action="{{ route('workspace.pesan.aksi', $ruang->id) }}" class="sebaris">
        @csrf
        <input type="hidden" name="aksi" value="{{ ! empty($p['disematkan']) ? 'lepas-semat' : 'sematkan' }}">
        <input type="hidden" name="pesan_id" value="{{ $p['id'] }}">
        <button class="tombol samar mini" type="submit">{{ ! empty($p['disematkan']) ? 'Lepas semat' : 'Sematkan' }}</button>
      </form>

      {{-- Tombol ini milik <form> bersama di bawah papan pesan (`form=` HTML,
           bukan bersarang) — kolom pilih percakapan cukup satu di halaman. --}}
      <button class="tombol samar mini" type="submit" form="{{ $teruskanId }}"
              name="pesan_id" value="{{ $p['id'] }}">Teruskan</button>

      @if ($saya)
        <details class="wa-sunting">
          <summary class="tombol samar mini">Sunting</summary>
          <form method="post" action="{{ route('workspace.pesan.aksi', $ruang->id) }}">
            @csrf
            <input type="hidden" name="aksi" value="sunting">
            <input type="hidden" name="pesan_id" value="{{ $p['id'] }}">
            <label>Sunting pesan<textarea name="isi" rows="2" maxlength="4000" required>{{ $p['isi'] }}</textarea></label>
            <button class="tombol utama mini" type="submit">Simpan</button>
          </form>
        </details>
        <form method="post" action="{{ route('workspace.pesan.aksi', $ruang->id) }}" class="sebaris">
          @csrf
          <input type="hidden" name="aksi" value="tarik">
          <input type="hidden" name="pesan_id" value="{{ $p['id'] }}">
          <button class="tombol samar mini bahaya" type="submit"
                  onclick="return confirm('Tarik pesan ini? Isinya hilang untuk semua peserta.')">Tarik</button>
        </form>
      @endif

      {{-- Hapus permanen: pengirimnya, atau pembuat grup menertibkan pesan
           peserta lain. Tetap <form> biasa supaya berfungsi tanpa JS. --}}
      @if (! empty($p['boleh_hapus']))
        <form method="post" action="{{ route('workspace.pesan.aksi', $ruang->id) }}" class="sebaris">
          @csrf
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="pesan_id" value="{{ $p['id'] }}">
          <button class="tombol samar mini bahaya" type="submit"
                  onclick="return confirm('Hapus pesan ini? Pesannya hilang permanen untuk semua peserta.')">Hapus</button>
        </form>
      @endif

      <form method="post" action="{{ route('workspace.pesan.terjemah', $ruang->id) }}" class="sebaris">
        @csrf
        <input type="hidden" name="pesan_id" value="{{ $p['id'] }}">
        <label class="sr" for="b{{ $p['id'] }}">Terjemahkan pesan ini</label>
        <select id="b{{ $p['id'] }}" name="bahasa">
          @foreach ($bahasa as $nilai => $label)<option value="{{ $nilai }}">{{ $label }}</option>@endforeach
        </select>
        <button class="tombol samar mini" type="submit">Terjemahkan</button>
      </form>
    </div>
  </details>
</article>

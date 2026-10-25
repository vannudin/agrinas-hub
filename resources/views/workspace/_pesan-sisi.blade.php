{{-- Sisi kiri Messenger: penyaring kategori + cari + daftar percakapan + "+ Baru".

     Dua lapis penyaring seperti Lark Messenger (`?kategori=` disaring di server,
     kotak cari disaring langsung di peramban karena datanya sudah ada di DOM):

       semua | belum | pribadi | grup | tersemat

     Baris percakapan membawa sendiri lencana belum-dibaca, hitungan sebutan,
     dan penanda tersemat — peramban hanya perlu menyembunyikan yang tak cocok,
     jadi menekan kategori tidak menunggu server.

     $aktif: id ruang yang sedang dibuka (0 = tidak ada). $daftar: hasil
     WorkspaceController::daftarRuang() — ruang pribadi sudah bernama lawan bicara. --}}
@push('gaya')
{{-- Select2: perkakas pencarian untuk <select> panjang (lingkup peserta & rekan).
     Disalin ke /vendor, bukan CDN — Hub dipakai di jaringan lapangan. --}}
<link rel="stylesheet" href="/vendor/select2/select2.min.css">
@endpush

@push('skrip')
{{-- Dimuat DI SINI, bukan di halaman: sisi kiri ini dipakai DUA halaman (daftar
     percakapan & ruang obrolan) dan keduanya butuh Select2. jQuery sudah dimuat
     layout (defer); ketiga skrip defer dijalankan menurut urutan dokumen, jadi
     Select2 pasti dapat jQuery dan hub-obrolan.js dapat Select2. --}}
{{-- jQuery dimuat DI SINI, bukan di layout: hanya Messenger yang memakainya
     (Select2). Urutan dokumen penting — ketiganya `defer`, jadi dijalankan
     berurutan: jQuery -> Select2 -> hub-obrolan.js. --}}
<script src="/vendor/jquery/jquery.min.js" defer></script>
<script src="/vendor/select2/select2.min.js" defer></script>
@endpush

@php
  $aktif = $aktif ?? 0;
  $kategori = $kategori ?? 'semua';
  $lingkupDaftar = $lingkupDaftar ?? [];
@endphp
<aside class="wa-sisi" aria-label="Daftar percakapan">
  <div class="wa-atas">
    <b>Percakapan</b>
    <button class="tombol samar mini" type="button" id="wa-baru-buka"
            aria-expanded="false" aria-controls="wa-baru" title="Percakapan, grup, atau forum baru">+ Baru</button>
  </div>

  <form class="wa-baru" id="wa-baru" method="post" action="{{ route('workspace.pesan.baru') }}"
        data-pribadi="{{ route('workspace.pesan.mulai') }}" hidden>
    @csrf
    {{-- Grup = ruang percakapan. Forum = ruang sama, tetapi punya TOPIK yang
         dibaca di daftar — inilah yang membuatnya terasa papan diskusi, bukan
         sekadar grup besar. Bentuk datanya sama, jadi tak ada tabel baru. --}}
    <div class="wa-jenis" role="radiogroup" aria-label="Jenis ruang">
      <label><input type="radio" name="mode" value="grup" checked> Grup</label>
      <label><input type="radio" name="mode" value="forum"> Forum</label>
    </div>
    <label>Nama<input name="nama" maxlength="120" placeholder="mis. Koordinasi Panen Bogor"></label>
    <label class="wa-topik" id="wa-topik" hidden>Topik forum
      <input name="topik" id="wa-topik-isi" maxlength="500" placeholder="mis. Harga gabah naik — bagaimana di daerah Anda?">
    </label>

    {{-- Lingkup peserta: pesertanya DITURUNKAN dari data Hub, jadi pengelola
         tidak perlu mencentang 30 nama satu per satu. Pilihannya dikelompokkan
         PER PROVINSI (provinsi = <optgroup>): di dalamnya kabupaten/kota,
         kecamatan, lalu koperasi beserta nama manajernya.
         Satu kolom saja — pencarian dibawa Select2, jadi kotak "cari" terpisah
         di atas <select> tidak diperlukan lagi (dua kolom yang tampak sama
         membuat pengguna mengira tak ada pencarian). --}}
    <label>Lingkup peserta
      <select name="lingkup" id="wa-lingkup" class="pilih2"
              data-placeholder="Ketik nama provinsi, kabupaten, kecamatan, atau koperasi…"
              aria-label="Lingkup peserta">
        <option value=""></option>
        @foreach ($lingkupDaftar as $kelompok => $pilihan)
          <optgroup label="{{ $kelompok }}">
            @foreach ($pilihan as $nilai => $label)
              <option value="{{ $nilai }}">{{ $label }}</option>
            @endforeach
          </optgroup>
        @endforeach
      </select>
    </label>

    <label>Percakapan pribadi
      <select name="rekan" id="wa-rekan" class="pilih2"
              data-placeholder="Ketik nama rekan…" aria-label="Rekan percakapan pribadi">
        <option value=""></option>
        @foreach ($rekan as $k)
          <option value="{{ $k->id }}">{{ $k->nama }} · {{ \App\Models\Domain::LABEL_PERAN[$k->peran] ?? $k->peran }}</option>
        @endforeach
      </select>
    </label>
    <label for="wa-cari-peserta">Peserta tambahan <span class="bantuan">(opsional — centang bila ingin menambah)</span></label>
    {{-- Daftar peserta bertanda centang + saringan. Tanpa JS tetap berarti:
         kotak centangnya bernama `anggota[]` dan bisa dicentang seperti biasa. --}}
    <div class="pilih" id="wa-pilih-peserta">
      <div class="pilih-kepala">
        <input type="search" id="wa-cari-peserta" placeholder="Cari nama…" autocomplete="off"
               aria-label="Cari calon peserta">
        <div class="pilih-aksi">
          <button class="tombol samar mini" type="button" id="wa-selesai" aria-label="Tutup daftar peserta">Selesai</button>
        </div>
      </div>
      <p class="pilih-ringkas" id="wa-terpilih" hidden></p>
      <ul class="pilih-daftar" id="wa-daftar-peserta">
        @foreach ($rekan as $k)
          <li><label class="pilih-baris" data-nama="{{ mb_strtolower($k->nama) }}">
            <input type="checkbox" name="anggota[]" value="{{ $k->id }}">
            <span class="pilih-nama">{{ $k->nama }}</span>
            <small>{{ \App\Models\Domain::LABEL_PERAN[$k->peran] ?? $k->peran }}</small>
          </label></li>
        @endforeach
      </ul>
      <p class="pilih-kosong" id="wa-peserta-kosong" hidden>Tidak ada nama yang cocok.</p>
    </div>
    <label>Koperasi
      <select name="koperasi_id">
        <option value="">Umum (semua peran)</option>
        @foreach ($koperasi as $k)<option value="{{ $k->id }}">{{ $k->kode }} — {{ $k->nama }}</option>@endforeach
      </select>
    </label>
    <div class="tombol-baris">
      <button class="tombol utama mini" type="submit" id="wa-buat">Buat Grup</button>
      <button class="tombol samar mini" type="button" id="wa-pribadi">Mulai Pribadi</button>
    </div>
    <small class="pudar">Percakapan pribadi hanya terlihat oleh Anda berdua. Lingkup menentukan siapa yang masuk grup —
      grup <b>tertutup</b> (hanya pesertanya). Tanpa peserta, ruangnya terbuka bagi lingkup koperasi yang dipilih.</small>
  </form>

  {{-- Penyaring kategori. Tanpa JS tetap berguna: tautannya membawa ?kategori=
       dan server menyaring (satu jalan, bukan dua perilaku berbeda). --}}
  <nav class="wa-saring" aria-label="Saring percakapan">
    @foreach (($kategoriDaftar ?? ['semua' => 'Semua']) as $nilai => $label)
      {{-- Satu label, sama di desktop dan ponsel. Tidak ada versi ringkas: memotong
           kata ("Belum", "Semat") menghilangkan makna. Lebar dihemat lewat CSS. --}}
      <a href="{{ route('workspace.pesan', $nilai === 'semua' ? [] : ['kategori' => $nilai]) }}"
         class="wa-pil {{ $kategori === $nilai ? 'ini' : '' }}"
         data-saring="{{ $nilai }}" aria-pressed="{{ $kategori === $nilai ? 'true' : 'false' }}"
         aria-label="Saring {{ $label }}"
         title="{{ $label }}">{{ $label }}</a>
    @endforeach
  </nav>

  <div class="wa-cari">
    <label class="sr" for="wa-cari">Cari percakapan</label>
    <input id="wa-cari" type="search" placeholder="Cari percakapan…" autocomplete="off">
  </div>

  <div class="wa-daftar" id="wa-daftar" data-kategori="{{ $kategori }}">
    @forelse ($daftar as $r)
      @php
        $t = $r->tautan();
        $p = $r->akhir ?? null;
        $belum = (int) ($r->belum ?? 0);
        $sebutan = (int) ($r->sebutan ?? 0);
        // `pribadi()` membaca kolom `jenis`; kunci ruang pribadi berisi "a:b",
        // bukan teks 'pribadi'. Perbandingan lama membuat data-pribadi SELALU 0,
        // sehingga penyaring lokal di peramban pun tidak pernah menemukan apa pun.
        $pribadi = $r->pribadi();
        $grup = in_array($r->kunci, ['anggota', ''], true) && $r->jenis !== 'topik';
        $nama = $r->nama_tampil ?? $r->nama;
      @endphp
      {{-- Baris = <div>, bukan <a> yang membungkus semuanya: tombol semat butuh
           <form>, dan <form> di dalam <a> tidak sah (peramban memecahnya jadi
           dua tautan, tombolnya jadi tak bisa diklik). Tautannya jadi lapisan
           utama, tombolnya duduk di sebelahnya. --}}
      <div class="wa-baris {{ (int) $aktif === (int) $r->id ? 'ini' : '' }} {{ $belum > 0 ? 'belum' : '' }}"
           data-nama="{{ mb_strtolower($nama) }}"
           data-belum="{{ $belum > 0 ? 1 : 0 }}"
           data-sebutan="{{ $sebutan > 0 ? 1 : 0 }}"
           data-pribadi="{{ $pribadi ? 1 : 0 }}"
           data-grup="{{ $grup ? 1 : 0 }}"
           data-tersemat="{{ $r->tersemat ? 1 : 0 }}">
        <a class="wa-baris-isi" href="{{ route('workspace.pesan.ruang', $r->id) }}">
          <span class="wa-avatar" aria-hidden="true">{{ $r->inisial ?? mb_strtoupper(mb_substr($nama, 0, 1)) }}</span>
          <span class="wa-baris-teks">
            <b>
              @if ($r->tersemat)<span class="wa-semat" title="Disematkan">📌</span>@endif
              {{ $nama }}
            </b>
            <small>
              @if ($p)
                @if ($p->nama && ! $pribadi){{ $p->nama ?: $p->oleh }}: @endif{{ $p->cuplikan(60) }}
              @elseif ($t) {{ ucfirst($t[0]).' · '.$t[1] }}
              @elseif ($r->jenis === 'pribadi') Ketuk untuk mulai mengobrol
              @else Belum ada pesan
              @endif
            </small>
          </span>
          <span class="wa-baris-meta">
            @if ($p)<time datetime="{{ $p->dibuat }}">{{ tanggal_id($p->dibuat, 'H:i') }}</time>@endif
            @if ($belum > 0)
              <span class="wa-lencana {{ $sebutan > 0 ? 'sebut' : '' }}"
                    title="{{ $sebutan > 0 ? $sebutan.' sebutan untuk Anda' : $belum.' pesan belum dibaca' }}">
                {{ $sebutan > 0 ? '@'.$belum : $belum }}
              </span>
            @endif
          </span>
        </a>
        <form method="post" action="{{ route('workspace.pesan.semat', $r->id) }}" class="wa-semat-form">
          @csrf
          <button class="tombol samar mini" type="submit"
                  title="{{ $r->tersemat ? 'Lepas sematan' : 'Sematkan percakapan' }}"
                  aria-label="{{ $r->tersemat ? 'Lepas sematan' : 'Sematkan percakapan' }}">📌</button>
        </form>
      </div>
    @empty
      <p class="wa-kosong">Belum ada percakapan. Tekan <b>+ Baru</b> untuk mulai.</p>
    @endforelse
    <p class="wa-kosong" id="wa-takada" hidden>Tidak ada percakapan yang cocok.</p>
  </div>
</aside>

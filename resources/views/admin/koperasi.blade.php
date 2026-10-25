@extends('layouts.utama')

@section('judul', 'Koperasi')
@section('isi')
<header class="kepala">
  <div>
    <h1>{{ auth()->user()->pic() ? 'Koperasi Wilayah' : 'Koperasi Binaan' }}</h1>
    {{-- Deskripsi SINGKAT: `.kepala .tenang` dipaksa satu baris (nowrap +
         ellipsis) supaya tinggi kepala seragam antarhalaman. Kalimat panjang
         di sini terukur TERPOTONG di 1280px saat tiga tombol aksi ikut tampil
         ("...dan penanggung jawabnya." hilang). Rincian isi sudah dijelaskan
         kartu-kartu di bawah, jadi kepalanya cukup menyebut lingkupnya. --}}
    <p class="tenang">Data koperasi, lahan, dan penanggung jawab.</p>
  </div>
  <div class="tombol-baris">
    {{-- Jalan ke halaman kerja koperasi. Menu sisi kini hanya punya SATU butir
         Koperasi (/kop), jadi daftar ini harus bisa ditemukan dari sana dan
         sebaliknya — bukan dua modul terpisah. --}}
    <a class="tombol samar ikon-kiri" href="{{ route('kop') }}"
       title="Data, sarana prasarana, dan POS satu koperasi">{!! ikon('kop', 'ik kecil') !!}<span>Kerja Koperasi</span></a>
    @if (Route::has('admin.tautkan') && auth()->user()->agrinas())
      <a class="tombol samar" href="{{ route('admin.tautkan') }}"
         title="Tautkan akun manajer yang belum punya koperasi">Tautkan Manajer</a>
    @endif
    @if (Route::has('admin.lahan.tarik'))
      <form method="post" action="{{ route('admin.lahan.tarik') }}" class="sebaris">
        @csrf
        <button class="tombol samar" type="submit"
          title="Tarik data lahan &amp; pembangunan (verval aset) dari SIMKOPDES">Tarik Data Lahan</button>
      </form>
    @endif
  </div>
</header>

<section class="blok">
  <form class="saring" method="get" action="{{ route('admin.koperasi') }}">
    <label class="cari">
      <span class="sr">Cari koperasi</span>
      <svg class="ikon-cari" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
        <circle cx="9" cy="9" r="6"></circle><path d="M13.5 13.5 18 18"></path>
      </svg>
      <input type="search" name="qk" value="{{ $qk }}" autocomplete="off" placeholder="Cari nama, kode, wilayah, atau manajer…">
    </label>
    <label>
      <span class="sr">Status koperasi</span>
      <select name="sk">
        <option value="">Semua status</option>
        @foreach (App\Models\Koperasi::LABEL_STATUS as $k => $v)
          <option value="{{ $k }}" @selected($sk === $k)>{{ $v }}</option>
        @endforeach
      </select>
    </label>
    <label>
      <span class="sr">Data lahan</span>
      <select name="lahan">
        <option value="">Semua koperasi</option>
        <option value="ada" @selected($lahan === 'ada')>Sudah ada data lahan</option>
        <option value="belum" @selected($lahan === 'belum')>Belum ada data lahan</option>
      </select>
    </label>
    <button class="tombol" type="submit">Terapkan</button>
    <a class="tombol samar bersih-saring" href="{{ route('admin.koperasi') }}"
       @if ($qk === '' && $sk === '' && $lahan === '') hidden @endif>Bersihkan</a>
  </form>

  <p class="bantuan">
    Kolom <b>Lahan</b> memuat luas bidang, status verval, dan kepemilikannya — dari
    verifikasi aset SIMKOPDES. <b>Rincian</b> membuka titik koordinat, akses
    (jalan/air/listrik), dokumen legal, serta penanggung jawabnya.
    {{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }} koperasi.
  </p>

  <div class="tabel-bungkus">
  <table class="tabel-koperasi">
    <thead><tr>
      <th>Koperasi</th><th>Lahan</th><th>Manajer</th><th>Status</th>
    </tr></thead>
    <tbody>
    @forelse ($koperasi as $k)
      @php $lahanKu = $lahanPeta[$k->koperasi_simkopdes_id] ?? $lahanPeta['id:'.$k->id] ?? collect(); @endphp
      {{-- Empat sel, bukan tujuh: di ponsel `.tabel-bungkus` mengubah tiap sel
           jadi label+nilai, jadi jumlah sel = tinggi baris. Tujuh sel menghasilkan
           baris 440–480px (hampir satu layar untuk satu koperasi; terukur).
           Kode & wilayah digabung ke sel Koperasi (pola yang sudah dipakai rumah
           di halaman lain), dan tombol rincian pindah ke sel Lahan — informasi
           sama, tiga sel lebih sedikit. --}}
      <tr>
        <td data-l="Koperasi">
          <span class="judul-sel">{{ $k->nama }}</span>
          <small class="pudar">
            <code>{{ $k->kode }}</code>
            @if ($k->alamat) · {{ $k->alamat }} @endif
            @if ($k->wilayah) · {{ $k->wilayah }} @endif
          </small>
        </td>
        <td data-l="Lahan">
          @if ($lahanKu->isEmpty())
            <span class="lencana samar">belum ada data</span>
          @else
            @php $utama = $lahanKu->first(); @endphp
            <span class="judul-sel">{{ number_format((float) $utama->luas, 0, ',', '.') }} m²</span>
            <small class="pudar">
              {{ $utama->status ?: '—' }} · {{ $utama->kepemilikan ?: 'kepemilikan belum diisi' }}
              @if ($lahanKu->count() > 1) · {{ $lahanKu->count() }} bidang @endif
            </small>
          @endif
          @if ($lahanKu->isNotEmpty())
            @php $tutup = 'rl-'.\Illuminate\Support\Str::slug((string) $k->kode).'-'.$k->id; @endphp
            <small><button class="tautan-mini" type="button" data-buka="{{ $tutup }}"
                    data-judul-dialog="Lahan — {{ $k->nama }}">Rincian lahan</button></small>

            <dialog class="modal" id="{{ $tutup }}">
              <div class="modal-kepala">
                <h2>Lahan &amp; Pembangunan</h2>
                <button class="tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
              </div>
              <div class="modal-badan">
                <p class="bantuan">{{ $k->nama }}@if ($k->kode !== '') · <code>{{ $k->kode }}</code>@endif
                  @if ($k->koperasi_simkopdes_id) · SIMKOPDES {{ $k->koperasi_simkopdes_id }}@endif</p>

                @foreach ($lahanKu as $i => $l)
                  @if ($lahanKu->count() > 1) <h3 class="petak-judul">Bidang {{ $i + 1 }}</h3> @endif
                  @php
                    $ukuran = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
                  @endphp
                  {{-- `.rincian` adalah grid dua kolom berisi dt/dd — label kiri,
                       nilai kanan. Dipakai apa adanya supaya bentuknya sama dengan
                       panel rincian lain di aplikasi ini. --}}
                  <dl class="rincian">
                    <dt>Luas</dt><dd>{{ number_format((float) $l->luas, 0, ',', '.') }} m²</dd>
                    <dt>Dimensi</dt><dd>{{ (float) $l->lebar > 0 && (float) $l->panjang > 0 ? $ukuran($l->lebar).' × '.$ukuran($l->panjang).' m' : '—' }}</dd>
                    {{-- Baris hasil sinkron titik pemetaan tidak punya status verval;
                         yang tersimpan di situ proses pembangunannya. Labelnya ikut
                         jujur, bukan menyebut "verval" untuk data yang bukan verval. --}}
                    <dt>{{ $l->asset_simkopdes_id ? 'Status verval' : 'Proses pembangunan' }}</dt><dd>{{ $l->status ?: '—' }}</dd>
                    <dt>Kepemilikan</dt><dd>{{ $l->kepemilikan ?: '—' }}</dd>
                    <dt>Jenis aset</dt><dd>{{ $l->status_aset ?: '—' }}</dd>
                    <dt>Akses jalan</dt><dd>{{ $l->akses_jalan ?: '—' }}</dd>
                    <dt>Alamat bidang</dt><dd>{{ $l->alamat ?: '—' }}</dd>
                    <dt>Kode desa</dt><dd>{{ $l->village_code ?: '—' }}</dd>
                    <dt>Kontak koperasi</dt><dd>{{ $l->kontak !== '' ? $l->kontak : '—' }}</dd>
                    <dt>Pemilik terdaftar</dt><dd>{{ $l->pemilik_terdaftar ?: '—' }}</dd>
                    <dt>Pendamping (surveyor)</dt><dd>{{ $l->surveyor ?: '—' }}</dd>
                    <dt>Diperbarui SIMKOPDES</dt><dd>{{ $l->aset_diperbarui ? tanggal_id($l->aset_diperbarui) : '—' }}</dd>
                  </dl>

                  {{-- Penanggung jawab + nomor HP. Nomor berasal dari profil yang
                       DISIMPAN saat pendamping itu masuk ke Hub: satu akun
                       SIMKOPDES tidak boleh membaca profil pendamping lain
                       (403), jadi ini satu-satunya sumber nomor per koperasi. --}}
                  @php $pj = $pjPeta[$k->koperasi_simkopdes_id] ?? null; @endphp
                  <h3 class="petak-judul">Penanggung Jawab Koperasi</h3>
                  <dl class="rincian">
                    @if ($pj)
                      <dt>Nama</dt><dd>{{ $pj->nama ?: '—' }}</dd>
                      <dt>Nomor HP</dt><dd>
                        @if (! empty($pj->telepon))
                          <a href="tel:{{ $pj->telepon }}">{{ $pj->telepon }}</a>
                          · <a href="https://wa.me/{{ preg_replace('/\D/', '', $pj->telepon) }}" target="_blank" rel="noopener">WhatsApp</a>
                        @else
                          belum tersimpan di profil Hub
                        @endif
                      </dd>
                      <dt>Surel</dt><dd>{{ $pj->email ?: '—' }}</dd>
                    @elseif ($k->manajer ?? null)
                      @php $tel = $telManajer[$k->manajer_id] ?? null; @endphp
                      <dt>Nama</dt><dd>{{ $k->manajer }} <small class="pudar">(manajer di Hub — belum ada akun yang ditempatkan sebagai pendamping)</small></dd>
                      <dt>Nomor HP</dt><dd>
                        @if (! empty($tel))
                          <a href="tel:{{ $tel }}">{{ $tel }}</a>
                          · <a href="https://wa.me/{{ preg_replace('/\D/', '', $tel) }}" target="_blank" rel="noopener">WhatsApp</a>
                        @else
                          belum tersimpan di profil Hub
                        @endif
                      </dd>
                      <dt>Surel</dt><dd>{{ $k->manajer_email ?: '—' }}</dd>
                    @else
                      <dt>Nama</dt><dd>belum ada akun yang ditempatkan di koperasi ini</dd>
                    @endif
                  </dl>

                  @php $ket = $l->keteranganRapi(); @endphp
                  @if ($ket)
                    <h3 class="petak-judul">Akses &amp; Lingkungan</h3>
                    <dl class="rincian">
                      @foreach ($ket as $label => $nilai)
                        <dt>{{ $label }}</dt><dd>{{ $nilai }}</dd>
                      @endforeach
                    </dl>
                  @endif

                  <p class="bantuan">
                    @if ($l->adaTitik())
                      Titik koordinat:
                      {{-- Angka koordinat ITU SENDIRI jadi tautan: orang mengetuk
                           angkanya, tidak mencari tulisan "buka di peta". Judul
                           memuat bentuk titik-desimal yang bisa dibaca mesin,
                           karena itulah yang diterima Google Maps. --}}
                      <a href="{{ $l->tautanPeta() }}" target="_blank" rel="noopener"
                         title="Buka {{ koordinat_mentah((float) $l->latitude, (float) $l->longitude) }} di Google Maps"
                      ><b>{{ koordinat_teks((float) $l->latitude, (float) $l->longitude) }}</b></a>
                      — <a href="{{ $l->tautanPeta() }}" target="_blank" rel="noopener">Buka di Google Maps</a>.
                    @else
                      Titik koordinat belum diisi di SIMKOPDES.
                    @endif
                    @if ($l->berkas !== '') · <a href="{{ $l->berkas }}" target="_blank" rel="noopener">Dokumen legal</a>@endif
                    @if ($l->foto !== '') · <a href="{{ $l->foto }}" target="_blank" rel="noopener">Foto lokasi</a>@endif
                  </p>
                @endforeach

                <p class="bantuan">
                  Nomor HP Babinsa tidak ada di API SIMKOPDES — yang tersedia hanya
                  kontak koperasi dan nama surveyor di atas. Nomor Babinsa per desa
                  ada di halaman <a href="{{ route('admin.pemetaan') }}">Pemetaan Lahan</a>.
                </p>
              </div>
            </dialog>
          @endif
        </td>
        <td data-l="Manajer">{{ $k->manajer ?: '—' }}<small class="pudar">{{ $k->manajer_email ?? '' }}</small></td>
        <td data-l="Status">{{ App\Models\Koperasi::LABEL_STATUS[$k->status] ?? $k->status }}</td>
      </tr>
    @empty
      <tr><td colspan="4" class="kosong">
        {{ $qk !== '' || $sk !== '' || $lahan !== '' ? 'Tidak ada koperasi yang cocok dengan saringan ini.' : 'Belum ada koperasi terdaftar.' }}
      </td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>
@endsection

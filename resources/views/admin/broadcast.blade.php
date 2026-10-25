@extends('layouts.utama')

@section('judul', 'Broadcast')
@section('isi')
<header class="kepala">
  <div><h1>Siaran Pesan</h1>
    <p class="tenang">Kirim pengumuman ke akun manajer &amp; admin.</p></div>
</header>

<section class="blok" id="form-siaran">
  <h2>Siaran Baru</h2>
  @if (session('galat'))<div class="pesan rusak" role="alert">{{ session('galat') }}</div>@endif
  <form method="post" action="{{ route('admin.broadcast.kirim') }}" class="grid-form">
    @csrf
    <label>Kanal
      <select name="kanal" id="kanal" required>
        @foreach ($kanal as $k => $v)
          <option value="{{ $k }}" @selected(old('kanal') === $k)>{{ $v }}</option>
        @endforeach
      </select>
    </label>
    <label>Sasaran
      <select name="sasaran" required>
        @foreach ($sasaran as $k => $v)
          <option value="{{ $k }}" @selected(old('sasaran', request('sasaran')) === $k)>{{ $v }}</option>
        @endforeach
        @foreach ($koperasiPilihan as $kop)
          <option value="kop:{{ $kop->kode }}" @selected(old('sasaran') === 'kop:'.$kop->kode)>
            Manajer {{ $kop->kode }} — {{ $kop->nama }}
          </option>
        @endforeach
      </select>
    </label>
    {{-- Gerbang WhatsApp: hanya muncul saat kanal WhatsApp dipilih. Tombolnya
         membuka dialog QR; sesudah tertaut berubah jadi status + tombol putus. --}}
    <div class="wa-gabung" id="wa-gabung" @if (old('kanal') !== 'WHATSAPP') hidden @endif>
      <button class="tombol mini" type="button" id="wa-konek" data-buka="wa-modal"
        @disabled(! $wa['hidup'])>Koneksikan WhatsApp</button>
      <span class="lencana s-DISETUJUI" id="wa-status" hidden></span>
      <button class="tombol samar mini" type="button" id="wa-putus" hidden>Disconnect</button>
      <span class="bantuan" id="wa-mati" @if ($wa['hidup']) hidden @endif>
        Kontainer baileys-runtime tidak menjawab — nyalakan dulu.
      </span>
    </div>
    <label class="lebar-penuh">Perihal / Judul
      <input name="perihal" maxlength="160" value="{{ old('perihal') }}" placeholder="Undangan Rapat Koordinasi Triwulan">
    </label>
    <label class="lebar-penuh">Isi Pesan
      <textarea name="pesan" rows="7" required maxlength="4000"
        placeholder="Kepada {{ '{nama}' }} di {{ '{koperasi}' }},&#10;&#10;...">{{ old('pesan') }}</textarea>
      <span class="bantuan">Tersedia penanda: <code>{nama}</code>, <code>{email}</code>, <code>{koperasi}</code>, <code>{perihal}</code>.</span>
    </label>
    <div class="lebar-penuh">
      <button class="tombol utama" type="submit">Proses Siaran</button>
    </div>
  </form>
  <p class="bantuan">
    <b>Email</b> dikirim langsung satu per satu ke tiap penerima.
    <b>WhatsApp</b> dikirim lewat gerbang yang tertaut di atas; penerima tanpa nomor
    di profil dicatat gagal, bukan dianggap terkirim.
  </p>
</section>

<section class="blok">
  <h2>Riwayat Siaran
    <small class="pudar">{{ $halaman->dari() }}–{{ $halaman->sampai() }} dari {{ $halaman->jumlah }}</small>
  </h2>
  <div class="tabel-bungkus">
  <table>
    <thead><tr><th>Waktu</th><th>Kanal</th><th>Perihal</th><th>Sasaran</th><th>Penerima</th><th>Oleh</th><th class="kolom-aksi"></th></tr></thead>
    <tbody>
    @forelse ($riwayat as $s)
      <tr>
        <td data-l="Waktu">{{ waktu_id($s->dibuat) }}</td>
        <td data-l="Kanal"><span class="lencana s-{{ $s->kanal === 'EMAIL' ? 'DIAJUKAN' : 'SELESAI' }}">{{ $kanal[$s->kanal] ?? $s->kanal }}</span></td>
        <td data-l="Perihal"><span class="judul-sel">{{ $s->perihal ?: '(tanpa perihal)' }}</span><small class="pudar">{{ Str::limit($s->pesan, 90) }}</small></td>
        <td data-l="Sasaran">{{ App\Models\Siaran::label_sasaran($s->sasaran) }}</td>
        <td data-l="Penerima">{{ in_array($s->kanal, ['EMAIL', 'WHATSAPP'], true) ? $s->jumlah.' terkirim' : 'antrian manual' }}</td>
        <td data-l="Oleh">{{ $s->pengirim }}</td>
        <td class="kolom-aksi">
          <a class="tombol samar mini" href="{{ route('admin.broadcast.tujuan', $s->id) }}">CSV</a>
        </td>
      </tr>
    @empty
      <tr><td colspan="7" class="kosong">Belum ada siaran terkirim.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
  @include('partials.halaman', ['halaman' => $halaman, 'ikon' => 'app'])
</section>

{{-- Dialog QR gerbang WhatsApp. Dibuka hub.js lewat `data-buka`. --}}
<dialog class="modal" id="wa-modal" aria-labelledby="wa-modal-judul">
  <header class="modal-kepala">
    <h2 id="wa-modal-judul">Koneksikan WhatsApp</h2>
    <button class="tombol samar mini ikon-tutup" type="button" data-tutup aria-label="Tutup">&times;</button>
  </header>
  <div class="modal-badan">
    <p class="bantuan">
      Buka WhatsApp di ponsel → <i>Perangkat tertaut</i> → <i>Tautkan perangkat</i>,
      lalu pindai kode ini. Kode berganti tiap ±20 detik; gambar menyegarkan sendiri.
    </p>
    <p class="wa-qr-bingkai"><img id="wa-qr" src="{{ route('admin.broadcast.wa_qr') }}"
      alt="Kode QR WhatsApp" width="280" height="280"></p>
    <p class="bantuan" id="wa-qr-gal">Menunggu dipindai…</p>
  </div>
  <footer class="modal-kaki">
    <button class="tombol samar" type="button" data-tutup>Tutup</button>
  </footer>
</dialog>

<script>
  // Gerbang WhatsApp: satu skrip untuk tombol/status di formulir + dialog QR.
  // Polling HANYA jalan selama dialog terbuka, jadi halaman diam tidak menembak
  // kontainer tiap detik.
  (function () {
    var kanal = document.getElementById('kanal');
    var kotak = document.getElementById('wa-gabung');
    var konek = document.getElementById('wa-konek');
    var status = document.getElementById('wa-status');
    var putus = document.getElementById('wa-putus');
    var modal = document.getElementById('wa-modal');
    var img = document.getElementById('wa-qr');
    var gal = document.getElementById('wa-qr-gal');
    var URL_STATUS = @json(route('admin.broadcast.wa_status'));
    var URL_QR = @json(route('admin.broadcast.wa_qr'));
    var URL_PUTUS = @json(route('admin.broadcast.wa_putus'));
    var TOKEN = @json(csrf_token());
    var siap = @json((bool) $wa['siap']);
    var nomor = @json($wa['nomor']);
    if (!kanal || !kotak) return;

    function lukis() {
      kotak.hidden = kanal.value !== 'WHATSAPP';
      konek.hidden = siap;
      putus.hidden = !siap;
      status.hidden = !siap;
      status.textContent = siap ? 'Terkoneksi' : '';
      status.title = nomor || '';
    }
    kanal.addEventListener('change', lukis);
    lukis();

    if (modal) {
      setInterval(function () {
        if (!modal.open) return;
        if (siap) { modal.close(); return; }
        img.src = URL_QR + '?t=' + Date.now();
        fetch(URL_STATUS, { headers: { Accept: 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            siap = !!d.siap;
            nomor = d.nomor || null;
            lukis();
            if (siap) { gal.textContent = 'Terkoneksi.'; modal.close(); }
          })
          .catch(function () { gal.textContent = 'Gerbang tidak menjawab.'; });
      }, 4000);
    }

    putus.addEventListener('click', function () {
      putus.disabled = true;
      fetch(URL_PUTUS, { method: 'POST', headers: { 'X-CSRF-TOKEN': TOKEN, Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { siap = !!d.siap; nomor = null; lukis(); })
        .catch(function () {})
        .finally(function () { putus.disabled = false; });
    });
  })();
</script>
@endsection

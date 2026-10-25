<?php

namespace App\Http\Controllers;

use App\Models\KonferensiRapat;
use App\Support\SfuRapat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Sisi server rapat Cloudflare Realtime. DUA bentuk:
 *
 *  - **meeting** : setiap peserta menerbitkan kamera+mikrofonnya dan menerima
 *                  milik orang lain (dua arah penuh).
 *  - **webinar** : hanya PEMBICARA yang menerbitkan; peserta lain menonton dan
 *                  menulis di obrolan / mengangkat tangan. Daftar pembicara
 *                  ditentukan pengelola rapat.
 *
 * Browser tidak pernah memegang App Secret. Ia mengambil media, membuat SDP, lalu
 * mengirimnya ke sini; server yang bicara ke Cloudflare dan mengembalikan jawaban.
 * Satu peserta = satu sessionId.
 *
 * Data peserta, kanal kendali, dan riwayat obrolan disimpan di kolom teks yang
 * sudah ada/kolom `kendali` baru — lihat migration 2026_10_04_180000.
 */
class RapatSfuController extends Controller
{
    /** Batas peserta yang diundang (jagaan ukuran kolom, bukan batas teknis SFU). */
    private const MAKS = 60;

    /**
     * Berapa detik tanpa denyut sebelum seorang peserta dianggap sudah keluar.
     *
     * Klien mengirim `ikut` tiap ~5 detik, jadi 45 detik = 9 denyut yang hilang —
     * cukup toleran terhadap jaringan yang tersendat, tetapi cukup cepat untuk
     * membuang tab yang ditutup paksa sebelum sesinya merusak join orang lain.
     */
    private const DENYUT_BASI = 45;

    /** Masuk rapat: buat sesi, catat kehadiran, kembalikan daftar + ICE + riwayat. */
    public function peran(Request $request, int $id)
    {
        // Dua pemeriksaan ini di LUAR kunci: keduanya memanggil Cloudflare, dan
        // menahan kunci selama panggilan jaringan membuat peserta lain menunggu.
        if (! SfuRapat::siap()) {
            return response()->json(['ok' => false, 'pesan' => 'Cloudflare Realtime belum dikonfigurasi.'], 503);
        }

        $sesi = SfuRapat::sesi();
        if ($sesi === null) {
            return response()->json(['ok' => false, 'pesan' => 'Gagal membuat sesi Cloudflare.'], 502);
        }

        return $this->denganKunci($id, function (KonferensiRapat $r) use ($request, $sesi) {
        // Orang pertama yang masuk menjadi pengelola (bisa memoderasi).
        // Pengelola hanya boleh dipindah bila yang lama SUDAH tidak menghuni daftar
        // hidup — kalau tidak, rapat jadi tak punya moderator.
        //
        // Urutannya penting: `daftar()` bisa MEMANGKAS sesi basi sambil menulis ke
        // DB, jadi `$kendali` dibaca ULANG sesudahnya. Kalau dibaca sebelum, kita
        // menulis balik denyut lama yang baru saja dipangkas.
        $pernahAda = (string) ($r->siaran_sesi ?? '') !== '';
        $penghuni = array_column($this->daftar($r), 'id');
        $kendali = $this->kendali($r);
        $tuanLama = (string) ($kendali['tuan'] ?? '');
        if (($tuanLama === '' || ! in_array($tuanLama, $penghuni, true))
            && (! $pernahAda || $penghuni === [])) {
            $kendali['tuan'] = $sesi;
        }
        // Denyut pertama sesi ini. WAJIB di sini: `siarkan()` berjalan SEBELUM
        // `ikut()` pertama, dan tanpa denyut sesi yang baru dibuat akan tersaring
        // oleh `daftar()` sebagai "basi" — akibatnya terbitkan ditolak dengan
        // "Sesi tidak terdaftar di rapat ini" (pernah terjadi saat denyut baru
        // ditambahkan hanya di `ikut()`).
        $kendali['denyut'][$sesi] = time();
        // Simpan di memori dulu: `boleh()` membacanya lewat `kendali($r)`, jadi nilai
        // pengelola harus terlihat sebelum pengecekan izin terbitkan berjalan.
        $r->kendali = json_encode($kendali, JSON_UNESCAPED_UNICODE);

        // Pembicara UNDANGAN: sesi ini langsung masuk daftar penerbit, jadi ia
        // tampil sebagai pembicara sejak detik pertama — bukan penonton yang harus
        // diangkat pengelola. Ditulis sebelum `boleh()` di bawah supaya izinnya
        // sudah terbaca pada pemeriksaan yang sama.
        $undang = (int) ($request->user()->id ?? 0);
        if ($r->pembicaraUndang($undang) && ! in_array($sesi, $this->penyiar($r), true)) {
            $penyiar = $this->penyiar($r);
            $penyiar[] = $sesi;
            $r->forceFill(['siaran_penyiar' => implode(',', $penyiar)])->save();
        }

        $daftar = $this->daftar($r);
        if (count($daftar) >= self::MAKS) {
            return response()->json(['ok' => false, 'pesan' => 'Rapat sudah penuh.'], 409);
        }

        $nama = (string) ($request->user()->nama ?? 'Peserta');
        $daftar[] = ['id' => $sesi, 'kode' => $nama, 'boleh' => $this->boleh($r, $sesi, true)];
        $this->simpan($r, $daftar, $kendali);

        return response()->json([
            'ok' => true,
            'sesi' => $sesi,
            'jenis' => $r->jenis,
            'tuan' => $kendali['tuan'] ?? '',
            'rapat' => $this->rapat($daftar),
            'peta' => $this->peta($daftar),
            'peserta' => $this->lain($daftar, $sesi),
            'boleh' => $this->petaBoleh($daftar),
            'ice' => SfuRapat::ice(),
        ]);
        });
    }

    /**
     * Denyut kehadiran + daftar terkini. TIDAK membuat sesi (polling harus murah),
     * dan mengembalikan semua yang berubah sejak `sejak`: peserta, obrolan,
     * angkat tangan, reaksi, serta perintah untuk sesi pemanggil.
     */
    public function ikut(Request $request, int $id)
    {
        return $this->denganKunci($id, function (KonferensiRapat $r) use ($request) {
        $d = $request->validate([
            'sesi' => ['required', 'string', 'max:64'],
            'kode' => ['nullable', 'string', 'max:120'],
            'sejak' => ['nullable', 'integer', 'min:0'],
        ]);

        if (! $this->terdaftar($r, $d['sesi'])) {
            return response()->json(['ok' => false, 'pesan' => 'Sesi tidak terdaftar di rapat ini.'], 409);
        }

        $kendali = $this->kendali($r);
        // Denyut nadi kehadiran. Tanpa ini tidak ada cara membedakan peserta yang
        // benar-benar hadir dari sesi yang ditinggalkan begitu saja — lihat `daftar()`.
        $kendali['denyut'][$d['sesi']] = time();
        $daftar = $this->daftar($r);
        foreach ($daftar as &$x) {
            if ($x['id'] === $d['sesi'] && (string) ($d['kode'] ?? '') !== '') {
                $x['kode'] = (string) $d['kode'];
            }
        }
        unset($x);
        $this->simpan($r, $daftar, $kendali);

        // Perintah yang menunggu sesi ini saja yang dikirim, lalu dihapus — supaya
        // tidak diulang terus oleh polling berikutnya.
        $perintah = [];
        $sisa = [];
        foreach (($kendali['perintah'] ?? []) as $p) {
            if (($p['untuk'] ?? '') === $d['sesi']) {
                $perintah[] = $p;
            } else {
                $sisa[] = $p;
            }
        }
        $kendali['perintah'] = $sisa;
        $this->simpan($r, $daftar, $kendali);

        return response()->json([
            'ok' => true,
            'jenis' => $r->jenis,
            'tuan' => $kendali['tuan'] ?? '',
            'rapat' => $this->rapat($daftar),
            'peta' => $this->peta($daftar),
            'peserta' => $this->lain($daftar, $d['sesi']),
            'boleh' => $this->petaBoleh($daftar),
            'angkat' => $kendali['angkat'] ?? [],
            'kanal' => $this->kanalSejak($kendali, (int) ($d['sejak'] ?? 0)),
            'perintah' => $perintah,
        ]);
        });
    }

    /** Terbitkan kamera/mikrofon dari tawaran (offer) browser. */
    public function siarkan(Request $request, int $id)
    {
        $r = KonferensiRapat::findOrFail($id);
        $d = $request->validate([
            'sesi' => ['required', 'string', 'max:64'],
            'trek' => ['required', 'array', 'min:1', 'max:2'],
            'trek.*.mid' => ['required', 'string', 'max:32'],
            'trek.*.jenis' => ['required', 'string', 'in:kamera,mikrofon'],
            'sdp' => ['required', 'string', 'max:200000'],
            'tipe' => ['nullable', 'string', 'max:16'],
        ]);

        if (! $this->terdaftar($r, $d['sesi'])) {
            return response()->json(['ok' => false, 'pesan' => 'Sesi tidak terdaftar di rapat ini.'], 409);
        }

        // Webinar: hanya pembicara yang boleh menerbitkan. Ditegakkan di server,
        // bukan hanya disembunyikan tombolnya.
        if ($r->jenis === 'webinar' && ! $this->boleh($r, $d['sesi'], false)) {
            return response()->json(['ok' => false, 'pesan' => 'Anda penonton di webinar ini — minta pembicara mengangkat Anda.'], 403);
        }

        return $this->balasApi(SfuRapat::terbitkan($d['sesi'], $d['trek'],
            ['type' => $d['tipe'] ?? 'offer', 'sdp' => $d['sdp']]));
    }

    /** Minta jalur milik peserta lain — balasannya berisi tawaran Cloudflare. */
    public function tonton(Request $request, int $id)
    {
        $r = KonferensiRapat::findOrFail($id);
        $d = $request->validate([
            'sesi' => ['required', 'string', 'max:64'],
            'minta' => ['required', 'array', 'min:1', 'max:60'],
            'minta.*.sesi' => ['required', 'string', 'max:64'],
            'minta.*.trek' => ['required', 'string', 'max:16'],
            'minta.*.slot' => ['required', 'integer', 'min:0', 'max:60'],
            'minta.*.jenis' => ['required', 'string', 'in:kamera,mikrofon'],
        ]);

        if (! $this->terdaftar($r, $d['sesi'])) {
            return response()->json(['ok' => false, 'pesan' => 'Sesi tidak terdaftar di rapat ini.'], 409);
        }

        $hasil = SfuRapat::tonton($d['sesi'], $d['minta']);

        // `peta` = slot & jenis → mid di sisi Cloudflare. Browser mencocokkannya
        // dengan `event.transceiver.mid` (mid tidak berubah saat negosiasi ulang).
        return $hasil['ok']
            ? response()->json(['ok' => true, 'peta' => $hasil['peta'], 'description' => $hasil['description']])
            : response()->json(['ok' => false, 'pesan' => $hasil['pesan']], 502);
    }

    /** Kirim jawaban (answer) browser ke Cloudflare. */
    public function jawab(Request $request, int $id)
    {
        KonferensiRapat::findOrFail($id);
        $d = $request->validate([
            'sesi' => ['required', 'string', 'max:64'],
            'sdp' => ['required', 'string', 'max:200000'],
        ]);

        return $this->balasApi(SfuRapat::jawab($d['sesi'], $d['sdp']));
    }

    /** Keluar dari rapat. */
    public function berhenti(Request $request, int $id)
    {
        return $this->denganKunci($id, function (KonferensiRapat $r) use ($request) {
        $d = $request->validate(['sesi' => ['required', 'string', 'max:64']]);

        if (! $this->terdaftar($r, $d['sesi'])) {
            return response()->json(['ok' => true]);
        }

        $kendali = $this->kendali($r);
        $lama = $this->daftar($r);
        $this->simpan($r, array_values(array_filter($lama, fn ($x) => $x['id'] !== $d['sesi'])),
            $kendali, true);  // niat jelas: sesi ini memang dibuang, sekalipun jadi kosong
        jurnal((int) $r->oleh, 'RAPAT_KELUAR', (string) $r->id, $r->judul, $request->ip() ?? '');

        return response()->json(['ok' => true]);
        });
    }

    /* ============================ fitur rapat (Zoom-like) ==================== */

    /** Obrolan, angkat tangan, reaksi, kendali pengelola — satu endpoint. */
    public function aksi(Request $request, int $id)
    {
        return $this->denganKunci($id, function (KonferensiRapat $r) use ($request) {
        $d = $request->validate([
            'sesi' => ['required', 'string', 'max:64'],
            'aksi' => ['required', 'string', 'in:chat,angkat,reaksi,kunci,bicara,tendang,pembicara,pesan'],
        ]);

        if (! $this->terdaftar($r, $d['sesi'])) {
            return response()->json(['ok' => false, 'pesan' => 'Sesi tidak terdaftar di rapat ini.'], 409);
        }

        $kendali = $this->kendali($r);
        $peta = $this->peta($this->daftar($r));
        $nama = $peta[$d['sesi']] ?? 'Peserta';
        $tuan = (string) ($kendali['tuan'] ?? '');
        $pesan = '';

        switch ($d['aksi']) {
            case 'chat':
                $teks = trim((string) $request->input('teks', ''));
                if ($teks === '') {
                    return response()->json(['ok' => false, 'pesan' => 'Pesan kosong.'], 422);
                }
                $this->kanal($kendali, 'obrolan', ['sesi' => $d['sesi'], 'nama' => $nama,
                    'teks' => mb_substr($teks, 0, 500)]);
                break;

            case 'angkat': // angkat / turunkan tangan
                $angkat = array_values(array_filter((array) ($kendali['angkat'] ?? []), fn ($s) => $s !== $d['sesi']));
                if (! in_array($d['sesi'], (array) ($kendali['angkat'] ?? []), true)) {
                    $angkat[] = $d['sesi'];
                }
                $kendali['angkat'] = $angkat;
                break;

            case 'reaksi':
                $emoji = mb_substr(trim((string) $request->input('emoji', '👍')), 0, 4) ?: '👍';
                $this->kanal($kendali, 'reaksi', ['sesi' => $d['sesi'], 'nama' => $nama, 'emoji' => $emoji]);
                break;

            default: // kunci / tendang / pembicara / pesan — hanya pengelola
                if ($tuan === '' || $d['sesi'] !== $tuan) {
                    return response()->json(['ok' => false, 'pesan' => 'Hanya pengelola rapat yang boleh melakukan ini.'], 403);
                }
                $pesan = $this->kendalikan($r, $kendali, $d['aksi'], (string) $request->input('sasaran', ''));
        }

        $this->simpan($r, $this->daftar($r), $kendali);

        return response()->json(['ok' => true, 'pesan' => $pesan]);
        });
    }

    /**
     * Jalankan baca-ubah-tulis kehadiran SATU rapat secara berurutan.
     *
     * `siaran_sesi` adalah satu kolom CSV yang ditulis ulang UTUH setiap kali
     * peserta masuk/denyut/keluar. Tanpa kunci, dua peserta yang bergabung hampir
     * bersamaan menulis dari snapshot masing-masing dan saling menimpa: peserta
     * yang datang lebih dulu HILANG dari daftar, lalu setiap tarikan berikutnya
     * dijawab "Sesi tidak terdaftar di rapat ini" (terukur: A masuk, B masuk,
     * A langsung 409 pada siarkan). Kunci ini hanya membungkus bagian cepat
     * (baca + tulis DB); panggilan Cloudflare tetap di luar.
     */
    private function denganKunci(int $id, callable $fn)
    {
        return Cache::lock('rapat:'.$id, 10)->block(8, fn () => $fn(KonferensiRapat::findOrFail($id)));
    }

    /* ============================ dalaman ==================================== */

    /** Jalankan perintah pengelola atas satu sesi sasaran. */
    private function kendalikan(KonferensiRapat $r, array &$kendali, string $aksi, string $sasaran): string
    {
        if ($sasaran === '') {
            return 'Sasaran tidak disebut.';
        }

        // "Bisukan semua" = perintah `kunci` untuk SETIAP peserta kecuali pengelola.
        // Dikirim satu per satu karena klien menerima perintah per sesi; pengelola
        // tidak dibisukan supaya ia tetap bisa memimpin rapat.
        if ($aksi === 'kunci' && $sasaran === 'semua') {
            $lain = array_values(array_filter(array_column($this->daftar($r), 'id'),
                fn ($s) => $s !== (string) ($kendali['tuan'] ?? '')));
            foreach ($lain as $s) {
                $kendali['perintah'][] = ['untuk' => $s, 'aksi' => 'kunci'];
            }
            $this->kanal($kendali, 'sistem', ['teks' => 'Pengelola meminta semua mikrofon dimatikan.']);

            return 'Permintaan matikan mikrofon dikirim ke '.count($lain).' peserta.';
        }

        $kendali['perintah'][] = ['untuk' => $sasaran, 'aksi' => $aksi];

        // "Beri bicara": menunjuk satu peserta untuk berbicara (kebalikan `kunci`).
        // Mikrofonnya dinyalakan lagi di sisi peserta, bukan hanya di UI pengelola.
        if ($aksi === 'bicara') {
            $this->kanal($kendali, 'sistem', ['teks' => 'Pengelola memberi izin bicara kepada seorang peserta.']);

            return 'Izin bicara dikirim ke peserta.';
        }

        if ($aksi === 'tendang') {
            $kendali['tendang'][] = $sasaran; // ditolak masuk lagi selama daftar ini berlaku
            $this->kanal($kendali, 'sistem', ['teks' => 'Seorang peserta dikeluarkan pengelola.']);
            $daftar = array_values(array_filter($this->daftar($r), fn ($x) => $x['id'] !== $sasaran));
            $this->simpan($r, $daftar, $kendali);

            return 'Peserta dikeluarkan.';
        }

        if ($aksi === 'pembicara') {
            $penyiar = $this->penyiar($r);
            if (! in_array($sasaran, $penyiar, true)) {
                $penyiar[] = $sasaran;
            }
            $r->forceFill(['siaran_penyiar' => implode(',', $penyiar)])->save();
            $this->kanal($kendali, 'sistem', ['teks' => 'Seorang penonton diangkat menjadi pembicara.']);

            return 'Peserta diangkat menjadi pembicara.';
        }

        $this->kanal($kendali, 'sistem', ['teks' => $aksi === 'kunci' ? 'Pengelola meminta mikrofon dimatikan.'
            : 'Pengelola mengirim pesan.']);

        return $aksi === 'kunci' ? 'Permintaan matikan mikrofon dikirim.' : 'Pesan dikirim.';
    }

    /** Peserta yang boleh menerbitkan. Meeting = semua; webinar = daftar pembicara. */
    private function boleh(KonferensiRapat $r, string $sesi, bool $saatGabung): bool
    {
        if ($r->jenis !== 'webinar') {
            return true;
        }

        if (in_array($sesi, $this->penyiar($r), true)) {
            return true;
        }

        // Pengelola (orang pertama yang masuk) selalu boleh bicara. Tanpa aturan ini
        // webinar tak pernah punya pembicara pertama: penonton tak boleh menerbitkan,
        // dan tak ada orang lain yang bisa mengangkatnya.
        $kendali = $this->kendali($r);
        if ($saatGabung && ($kendali['tuan'] ?? '') === $sesi) {
            $r->forceFill(['siaran_penyiar' => $sesi])->save();

            return true;
        }

        return false;
    }

    /** @return array<int,string> */
    private function penyiar(KonferensiRapat $r): array
    {
        return array_values(array_filter(explode(',', (string) ($r->siaran_penyiar ?? ''))));
    }

    /** @return array<string,mixed> */
    private function kendali(KonferensiRapat $r): array
    {
        $k = json_decode((string) ($r->kendali ?? ''), true);

        return is_array($k) ? $k : [];
    }

    /** @return array<int,array{id:string,kode:string,boleh:bool}> */
    private function daftar(KonferensiRapat $r): array
    {
        $kendali = $this->kendali($r);
        $kode = [];
        foreach (explode(',', (string) ($r->siaran_oleh ?? '')) as $b) {
            if (str_contains($b, '@')) {
                [$n, $v] = explode('@', $b, 2);
                $kode[$v] = $n;
            }
        }

        $denyut = (array) ($kendali['denyut'] ?? []);
        $out = [];
        foreach (explode(',', (string) ($r->siaran_sesi ?? '')) as $s) {
            if ($s === '') {
                continue;
            }
            // Sesi BASI dibuang, bukan dipercaya.
            //
            // `siaran_sesi` dulu hanya dibersihkan kalau peserta menekan "Keluar"
            // (atau pagehide terkirim). Peramban yang ditutup paksa, tab yang mati,
            // atau koneksi yang putus meninggalkan sesinya SELAMANYA — dan sesi
            // hantu itu lalu diminta sebagai jalur kamera ke Cloudflare, ditolak
            // `not_found_track_error`, sehingga SELURUH join gagal (502) dan rapat
            // tidak bisa dipakai sama sekali. Peserta pertama pun jadi korban.
            //
            // Peserta yang benar-benar hadir mengirim `ikut` tiap ~5 detik, jadi
            // denyutnya selalu segar. Yang tidak menyentuh 45 detik terakhir
            // dianggap sudah pergi. Data lama (tanpa denyut sama sekali) juga
            // terbuang — memang sesi dari versi sebelumnya dan tak bisa dipercaya.
            if (! isset($denyut[$s]) || (time() - (int) $denyut[$s]) > self::DENYUT_BASI) {
                continue;
            }
            $out[] = ['id' => $s, 'kode' => (string) ($kode[$s] ?? 'Peserta'),
                'boleh' => $this->boleh($r, $s, false)];
        }

        // Buang juga denyut milik sesi yang sudah tidak dipakai supaya kolom JSON
        // tidak tumbuh tanpa batas pada rapat panjang.
        //
        // `ARRAY_FILTER_USE_KEY` WAJIB: pada peta ini KUNCI = id sesi dan NILAI =
        // stempel waktu. Tanpa flag itu callback menerima stempel waktunya, sehingga
        // perbandingan `in_array(timestamp, $hidup)` selalu gagal dan SELURUH denyut
        // terhapus — sesi yang baru masuk pun langsung dianggap basi, dan
        // "Sesi tidak terdaftar di rapat ini" menghadang setiap tarikan berikutnya.
        // (Ini pernah terjadi; penyebabnya halus justru karena tidak melempar galat.)
        $hidup = array_column($out, 'id');
        $bersih = array_filter($denyut, fn ($s) => in_array($s, $hidup, true), ARRAY_FILTER_USE_KEY);
        if (count($bersih) !== count($denyut)) {
            $kendali['denyut'] = $bersih;
            $this->simpan($r, $out, $kendali);
        }

        return $out;
    }

    private function simpan(KonferensiRapat $r, array $daftar, array $kendali,
        bool $izinkanKosong = false): void
    {
        // Kanal dipangkas supaya kolom tidak tumbuh tanpa batas pada rapat panjang.
        if (isset($kendali['kanal']) && count($kendali['kanal']) > 400) {
            $kendali['kanal'] = array_slice($kendali['kanal'], -300, 300, true);
        }
        $kendali['angkat'] = array_values(array_filter((array) ($kendali['angkat'] ?? []),
            fn ($s) => in_array($s, array_column($daftar, 'id'), true)));
        $kendali['tendang'] = array_values(array_filter((array) ($kendali['tendang'] ?? []),
            fn ($s) => ! in_array($s, array_column($daftar, 'id'), true)));

        // JANGAN kosongkan kehadiran hanya karena daftar hidup kosong.
        //
        // Daftar hidup bisa kosong untuk sesaat (semua denyut kebetulan lewat batas,
        // atau kueri denyut yang tidak lengkap). Menulis kolom kosong berarti
        // MENGHAPUS seluruh jejak peserta — dulu itulah yang terjadi: satu kueri
        // daftar yang tidak hati-hati cukup untuk mengosongkan ruangan, dan sesudah
        // itu tidak ada yang bisa bergabung lagi karena tak seorang pun "terdaftar".
        // Karena itu: bila daftar kosong tetapi kolomnya masih berisi, biarkan
        // apa adanya — KECUALI pemanggil menyatakan niatnya dengan `$izinkanKosong`
        // (dipakai `berhenti()` saat peserta terakhir keluar: itu memang ingin
        // mengosongkan, dan tanpa penanda ini sesi terakhir tak pernah terhapus).
        $kosong = $daftar === [] && ! $izinkanKosong;
        $kolom = [
            'siaran_sesi' => $kosong ? (string) ($r->siaran_sesi ?? '')
                : implode(',', array_column($daftar, 'id')),
            'siaran_oleh' => $kosong ? (string) ($r->siaran_oleh ?? '')
                : implode(',', array_map(fn ($d) => $d['kode'].'@'.$d['id'], $daftar)),
            'kendali' => json_encode($kendali, JSON_UNESCAPED_UNICODE),
        ];

        $r->forceFill($kolom)->save();
    }

    /** Tulis satu pesan ke kanal dan kembalikan nomor urutnya. */
    private function kanal(array &$kendali, string $jenis, array $isi): int
    {
        $nomor = (int) ($kendali['nomor'] ?? 0) + 1;
        $kendali['nomor'] = $nomor;
        $kendali['kanal'][$nomor] = $isi + ['n' => $nomor, 'jenis' => $jenis, 'waktu' => now()->format('H:i')];

        return $nomor;
    }

    /** @return array<int,array<string,mixed>> */
    private function kanalSejak(array $kendali, int $sejak): array
    {
        $out = [];
        foreach ((array) ($kendali['kanal'] ?? []) as $n => $isi) {
            if ((int) $n > $sejak) {
                $out[] = $isi;
            }
        }

        return $out;
    }

    private function rapat(array $daftar): string
    {
        return (string) ($daftar[0]['id'] ?? '');
    }

    /** @return array<string,string> */
    private function peta(array $daftar): array
    {
        $out = [];
        foreach ($daftar as $d) {
            $out[$d['id']] = $d['kode'];
        }

        return $out;
    }

    /** @return array<string,bool> */
    private function petaBoleh(array $daftar): array
    {
        $out = [];
        foreach ($daftar as $d) {
            $out[$d['id']] = (bool) $d['boleh'];
        }

        return $out;
    }

    /** @return array<int,string> */
    private function lain(array $daftar, string $sendiri): array
    {
        return array_values(array_filter(array_column($daftar, 'id'), fn ($s) => $s !== $sendiri));
    }

    private function terdaftar(KonferensiRapat $r, string $sesi): bool
    {
        return in_array($sesi, array_column($this->daftar($r), 'id'), true);
    }

    private function balasApi(array $hasil)
    {
        if (! $hasil['ok']) {
            return response()->json(['ok' => false, 'pesan' => $hasil['pesan']], 502);
        }

        return response()->json(['ok' => true, 'description' => $hasil['description'] ?? null]);
    }
}

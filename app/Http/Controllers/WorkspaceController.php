<?php

namespace App\Http\Controllers;

use App\Models\AnggotaObrolan;
use App\Models\BacaObrolan;
use App\Models\CatatanHarian;
use App\Models\Dokumen;
use App\Models\DokumenAkses;
use App\Models\KonferensiRapat;
use App\Models\Koperasi;
use App\Models\Kiriman;
use App\Models\PesanObrolan;
use App\Models\Persetujuan;
use App\Models\ReaksiObrolan;
use App\Models\RuangObrolan;
use App\Models\Tugas;
use App\Models\User;
use App\Models\Wiki;
use App\Services\DokumenPenyimpan;
use App\Support\Halaman;
use App\Support\JobdescManajer;
use App\Support\Wilayah;
use App\Support\OnlyOffice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Ruang Kerja: tujuh modul kolaborasi — Messenger, Docs & Sheets, PhotoCraft,
 * Catatan Harian, Kalender, Tasks, Wiki & Approval.
 *
 * Lima dari tujuh modul ini bekerja di atas tabel yang sudah ada:
 * Kalender membaca `konferensi` + `tugas`, Tasks memakai `tugas` + `komentar`.
 * Yang punya tabel sendiri hanya Messenger, Docs, Catatan Harian, Wiki, dan
 * Persetujuan. PhotoCraft tidak punya tabel sama sekali: aplikasinya berdiri
 * sendiri di kontainer lain, halaman Ruang Kerja hanya membingkainya.
 *
 * Lingkup akses mengikuti aturan yang sama dengan modul lain: manajer terbatas
 * pada koperasinya, PIC pada wilayahnya, admin pusat melihat semua
 * (`User::koperasiQuery()`).
 */
class WorkspaceController extends Controller
{
    private const DISK = 'r2';

    /**
     * Ekstensi lampiran obrolan yang diterima.
     *
     * Nama berkas lampiran dibentuk dari ekstensi KIRIMAN KLIEN
     * (`getClientOriginalExtension()`), jadi tanpa daftar ini satu berkas
     * `.php`/`.phtml` ikut tersimpan apa adanya. Hari ini belum berbahaya
     * karena disk `r2` di luar docroot — tapi itu satu symlink/aturan server
     * dari menjadi RCE. Daftar ini sekaligus yang membuat `lampiran_unduh`
     * tak pernah menyajikan HTML sebagai halaman.
     */
    private const LAMPIRAN_EKSTENSI = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'bmp', 'tif', 'tiff',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf',
        'txt', 'csv', 'md', 'json', 'xml', 'log',
        'mp3', 'm4a', 'ogg', 'oga', 'opus', 'wav', 'aac', 'amr', 'weba',
        'mp4', 'm4v', 'mov', 'webm', 'mkv', 'avi', '3gp',
        'zip', 'rar', '7z', 'tar', 'gz',
    ];

    public function __construct(private readonly DokumenPenyimpan $dokumen) {}

    /** Tujuh modul Ruang Kerja; dipakai halaman indeks DAN bilah modul di view. */
    public const MODUL = [
        ['pesan', 'workspace.pesan', 'Messenger', 'Pusat komunikasi tim: obrolan grup, utas topik, terjemahan, konteks dari dokumen/rapat.'],
        ['dokumen', 'workspace.dokumen', 'Docs & Sheets', 'Dokumen & lembar sebar kolaboratif (ONLYOFFICE), terhubung ke obrolan.'],
        // PhotoCraft = editor GAMBAR (Photoshop-like) dari kontainer terpisah.
        // Masuk sini berarti ikut muncul di bilah modul (_nav me-loop MODUL ini)
        // SEKALIGUS jadi kartu di /workspace & /fitur. Sudah diukur: bilah jadi
        // 8 tautan, tinggi 166px, luber 0 di 360px — masih nyaman.
        ['gambar', 'workspace.gambar', 'PhotoCraft', 'Editor gambar: buka PSD/PSB, sunting lapisan, ekspor PNG/JPG. Berjalan di peramban Anda.'],
        // OpenCut = editor VIDEO (pengganti FilmCraft, 2026-10-25). Aplikasinya
        // bukan WASM statis seperti PhotoCraft: ia aplikasi Next/TanStack
        // tersendiri di 127.0.0.1:8086 (Next.js, kontainer
        // opencut-classic-web-1) yang dipasang nginx di /video/ dengan
        // gerbang auth_request ke sesi Hub (lihat /etc/nginx/agrinas.conf).
        // Masuk sini berarti ikut muncul di bilah modul (_nav me-loop MODUL ini)
        // SEKALIGUS jadi kartu di /workspace.
        ['video', 'workspace.video', 'OpenCut', 'Editor video: susun klip, potong, beri efek, ekspor MP4. Berjalan di peramban Anda.'],
        ['catatan-harian', 'workspace.catatan-harian', 'Catatan Harian', 'Catatan kerja harian manajer; admin pusat & PIC memantau.'],
        ['kalender', 'workspace.kalender', 'Calendar', 'Jadwal rapat & tenggat tugas, ketersediaan tim, mulai panggilan video dari acara.'],
        ['tugas', 'workspace.tugas', 'Tasks', 'Pecah pekerjaan besar jadi langkah kecil, terhubung ke obrolan & dokumen.'],
        // Dua modul terpisah (permintaan Bos 2026-10-19): Wiki = pengetahuan &
        // tutorial pengoperasian Hub, dibaca. Approval = pengajuan biaya
        // operasional gerai (listrik, air, service AC/kendaraan, perjalanan
        // dinas, rembes pembelian) yang diputuskan admin/keuangan, diputuskan.
        // Dulu satu halaman "Wiki & Approval" dengan dua daftar berhalaman.
        ['wiki', 'workspace.wiki', 'Wiki', 'Tutorial & pengetahuan mengoperasikan Hub: fungsi, fitur, dan SOP tiap modul.'],
        ['setuju', 'workspace.approval', 'Approval', 'Pengajuan biaya operasional gerai (listrik, air, service, perjalanan dinas) ke admin/keuangan.'],
    ];

    /**
     * Modul yang TIDAK dipajang di Ruang Kerja (kisi indeks + bilah modul).
     *
     * Messenger sudah punya pintunya sendiri: kartu "Message" di /fitur, bilah
     * bawah ponsel, dan lencana belum-dibaca di kepala. Kartunya di Ruang Kerja
     * jadi pintu ketiga ke halaman yang sama — itu yang diminta Bos dihapus
     * (2026-10-18). Yang dihapus HANYA pajangannya: rutenya tetap hidup dan
     * /fitur tetap memuat "Message", jadi tidak ada jalan yang hilang.
     *
     * Ditulis sebagai daftar terpisah — bukan dengan memindahkan butirnya ke
     * ujung MODUL — supaya MODUL tetap satu sumber urutan modul dan tidak ada
     * aturan "butir terakhir disembunyikan" yang harus ditebak pembaca kode.
     */
    public const MODUL_TERSEMBUNYI = ['pesan'];

    /** Modul yang benar-benar dipajang di Ruang Kerja — SATU daftar, dipakai
     *  halaman indeks DAN bilah modul (_nav), supaya keduanya tak bisa berbeda. */
    public static function modulTampil(): array
    {
        return array_values(array_filter(self::MODUL,
            fn ($m) => ! in_array($m[0], self::MODUL_TERSEMBUNYI, true)));
    }

    // ------------------------------------------------------------ Ruang Kerja

    /** Semua menu dalam bentuk ikon — halaman tujuan menu "Fitur Lain". */
    public function fitur(Request $request)
    {
        return view('fitur', ['aktif' => 'fitur', 'menu' => daftar_menu($request->user())]);
    }

    /** Halaman kerja admin: ringkasan, tindak lanjut, daftar manajer, catatan. */
    public function index(Request $request)
    {
        $u = $request->user();
        $agrinas = ! $u->manajerKoperasi();

        if ($agrinas) {
            // Tiap tabel dipaginasi SENDIRI dengan kunci halaman berbeda —
            // halaman ini punya tiga daftar, dan satu kunci untuk semuanya akan
            // membuat pindah halaman di satu tabel menggeser tabel lainnya.
            $cariMenunggu = trim((string) $request->query('q_menunggu', ''));
            $qMenunggu = Tugas::query()
                ->join('koperasi as k', 'k.id', '=', 'tugas.koperasi_id')
                ->select('tugas.*', 'k.nama as koperasi')
                ->whereIn('tugas.status', ['DIAJUKAN', 'REVISI'])
                ->when($cariMenunggu !== '', function ($w) use ($cariMenunggu) {
                    $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cariMenunggu).'%';
                    $w->where(fn ($x) => $x->where('tugas.judul', 'like', $pola)->orWhere('k.nama', 'like', $pola));
                });

            $cariManajer = trim((string) $request->query('q_manajer', ''));
            $qManajer = User::query()
                ->where('peran', \App\Models\Domain::PERAN_MANAJER)
                ->when($cariManajer !== '', function ($w) use ($cariManajer) {
                    $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cariManajer).'%';
                    $w->where(fn ($x) => $x->where('nama', 'like', $pola)->orWhere('email', 'like', $pola));
                });

            return view('workspace', [
                'aktif' => 'workspace', 'mode' => 'agrinas',
                'menunggu' => $this->berhalaman($qMenunggu, $request, 'halaman_menunggu',
                    fn ($w) => $w->orderByRaw("CASE tugas.status WHEN 'DIAJUKAN' THEN 0 ELSE 1 END")->orderBy('tugas.diperbarui')),
                'manajer' => $this->berhalaman($qManajer, $request, 'halaman_manajer',
                    fn ($w) => $w->orderBy('nama')),
                'cariMenunggu' => $cariMenunggu, 'cariManajer' => $cariManajer,
                'koperasi' => collect(),
                'ringkasKop' => $this->cariKoperasi($request, ''),
                'sasaranPilihan' => $this->sasaranPilihan(),
                'status' => Tugas::query()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all(),
                'terlambat' => Tugas::query()->where('status', '<>', 'SELESAI')->whereNotNull('deadline')
                    ->where('deadline', '<', now()->toDateString())->count(),
                'catatan' => $this->baca_catatan($request),
                'modul' => self::modulTampil(),
                'ringkas' => $this->ringkas($u),
            ]);
        }

        // Manajer: koperasinya + tugas aktif + berkas bersama.
        $kop = Koperasi::where('manajer_id', $u->id)->first();
        $cariTugas = trim((string) $request->query('q_tugas', ''));
        $saringTugas = mb_strtoupper((string) $request->query('status_tugas', ''));
        // Daftar status tugas tinggal di Domain (Tugas::ALUR hanya alur maju).
        $saringTugas = in_array($saringTugas, \App\Models\Domain::STATUS, true) ? $saringTugas : '';
        $semua = $kop ? Tugas::where('koperasi_id', $kop->id)
            ->when($saringTugas !== '', fn ($w) => $w->where('status', $saringTugas))
            ->when($cariTugas !== '', function ($w) use ($cariTugas) {
                $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cariTugas).'%';
                $w->where(fn ($x) => $x->where('judul', 'like', $pola)->orWhere('jenis', 'like', $pola));
            })
            ->orderByDesc('diperbarui') : null;
        $halaman = Halaman::buat($semua ? (clone $semua)->count() : 0, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return view('workspace', [
            'aktif' => 'workspace', 'mode' => 'manajer', 'koperasi' => $kop,
            'tugas' => $semua ? $halaman->batasi($semua)->get() : collect(), 'halaman' => $halaman,
            'halamanTugas' => $halaman,
            'cariTugas' => $cariTugas, 'saringTugas' => $saringTugas,
            'status' => $kop ? Tugas::where('koperasi_id', $kop->id)->selectRaw('status, COUNT(*) as n')
                ->groupBy('status')->pluck('n', 'status')->all() : [],
            'catatan' => $this->baca_catatan($request),
            'modul' => self::modulTampil(),
            'ringkas' => $this->ringkas($u),
        ]);
    }

    /**
     * Bungkus sebuah query jadi daftar berhalaman: hitung, batasi, lalu hitung
     * ulang jumlahnya. Mengembalikan ARRAY (baris + pagernya) supaya view tak
     * perlu tahu kunci halamannya.
     */
    private function berhalaman($q, Request $request, string $kunci, callable $urut): array
    {
        $q = $urut(clone $q);
        $halaman = Halaman::buat((clone $q)->count(), $request->query($kunci, 1), DasborController::PER_HALAMAN);

        return ['baris' => $halaman->batasi($q)->get(), 'halaman' => $halaman, 'kunci' => $kunci];
    }

    /**
     * Cari koperasi untuk pemilih "Tugas Cepat".
     *
     * Daftar lengkap TIDAK dirender sebagai <option>: pada puluhan ribu koperasi
     * itu berarti puluhan ribu baris di setiap pemuatan halaman, dan tetap tak
     * bisa dicari (pengguna harus menggulir). Yang dikirim hanya hasil pencarian,
     * sepotong 20 baris — pola yang sama dengan pemilih di /keuangan.
     *
     * Lingkupnya mengikuti wewenang akun (koperasiQuery()), jadi PIC maupun
     * pengelola pusat tetap tidak bisa mencari di luar wilayahnya.
     */
    private function cariKoperasi(Request $request, string $q, int $batas = 20)
    {
        $q = trim($q);

        return $request->user()->koperasiQuery()
            ->when($q !== '', function ($w) use ($q) {
                // `%` dan `_` milik pola LIKE, bukan milik ketikan pengguna.
                $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $w->where(fn ($x) => $x->where('kode', 'like', $pola)->orWhere('nama', 'like', $pola)
                    ->orWhere('wilayah', 'like', $pola)->orWhere('provinsi', 'like', $pola));
            })
            ->orderBy('nama')->limit($batas)->get(['id', 'kode', 'nama', 'wilayah']);
    }

    /**
     * Pilihan sasaran untuk "kirim ke banyak koperasi": daftar provinsi dari
     * Wilayah, dan daftar kabupaten/kecamatan DITURUNKAN dari data koperasi yang
     * ada — supaya tidak perlu memelihara daftar 514 kabupaten, dan pilihan yang
     * disodorkan dijamin ada isinya.
     */
    private function sasaranPilihan(): array
    {
        $provinsi = collect(Wilayah::PILIHAN)->except([Wilayah::JABODETABEK]);

        $kabupaten = [];
        $kecamatan = [];
        foreach (Koperasi::query()->whereRaw("coalesce(wilayah,'') <> ''")
            ->distinct()->pluck('wilayah') as $w) {
            if (preg_match('/Kab\.\s*([^,]+)/i', $w, $m)) {
                $kabupaten[mb_strtoupper(trim($m[1]))] = true;
            } elseif (preg_match('/Kota\s*([^,]+)/i', $w, $m)) {
                $kabupaten['KOTA '.mb_strtoupper(trim($m[1]))] = true;
            }
            if (preg_match('/Kec\.\s*([^,]+)/i', $w, $m)) {
                $kecamatan[mb_strtoupper(trim($m[1]))] = true;
            }
        }
        ksort($kabupaten);
        ksort($kecamatan);

        return ['provinsi' => $provinsi, 'kabupaten' => array_keys($kabupaten),
                'kecamatan' => array_keys($kecamatan)];
    }

    /**
     * Bagi satu tugas ke banyak koperasi — SATU baris `tugas` per koperasi.
     *
     * Kenapa per baris, bukan satu baris berlingkup: seluruh aplikasi membaca
     * tugas lewat `tugas.koperasi_id` (KerjaController, DasborController,
     * BerandaController, AsistenController) dan tiap koperasi memegang progres
     * sendiri (status, riwayat, komentar). Satu baris per penerima berarti TIDAK
     * ada titik baca yang perlu diubah.
     */
    private function kirimMassal(Request $request, array $d, $u): int
    {
        $q = $u->koperasiQuery();
        if ($d['sasaran'] === 'JABODETABEK') {
            $q->where(Wilayah::saring(Wilayah::JABODETABEK));
        } elseif ($d['sasaran'] === 'PROVINSI') {
            $q->where(Wilayah::saring((string) $d['wilayah']));
        } elseif ($d['sasaran'] === 'KABUPATEN') {
            $q->where('wilayah', 'like', '%'.$d['wilayah'].'%');
        } elseif ($d['sasaran'] === 'KECAMATAN') {
            $q->where('wilayah', 'like', '%Kec. '.$d['wilayah'].'%');
        }

        $n = 0;
        foreach ($q->pluck('id') as $id) {
            $t = Tugas::create([
                'koperasi_id' => $id, 'judul' => $d['judul'],
                'deskripsi' => $d['deskripsi'] ?? '',
                'sasaran' => $d['sasaran'], 'sasaran_wilayah' => $d['wilayah'] ?? '',
                // `jenis` tetap kategori tugas (DOKUMEN/LAPORAN) — sasaran
                // disimpan di kolomnya sendiri supaya informasi lama tak hilang.
                'jenis' => 'DOKUMEN',
                'prioritas' => 'NORMAL', 'deadline' => ($d['deadline'] ?? null) ?: null,
                'status' => 'BARU', 'dibuat_oleh' => $u->id,
                'dibuat' => kini(), 'diperbarui' => kini(),
            ]);
            jejak($t->id, (string) $u->nama, 'DIBUAT', '', 'BARU',
                'Kirim massal: '.(Tugas::SASARAN[$d['sasaran']] ?? $d['sasaran']));
            $n++;
        }

        return $n;
    }

    /** Potongan kotak hasil pencarian koperasi (bukan halaman penuh). */
    public function kop_cari(Request $request)
    {
        $q = trim((string) $request->query('cari_kop', ''));

        return response()->view('workspace._koperasi-kotak', [
            'q' => $q,
            'hasil' => $this->cariKoperasi($request, $q),
            'pilihanId' => (int) $request->query('koperasi_id', 0),
        ])->header('X-Jumlah', (string) $this->cariKoperasi($request, $q)->count());
    }

    /** Simpan catatan kerja pribadi ke berkas di storage (tanpa tabel baru). */
    public function simpan_catatan(Request $request)
    {
        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:20000']]);
        Storage::disk(self::DISK)->put($this->berkas($request), (string) ($data['catatan'] ?? ''));
        Cache::forget('catatan.'.$request->user()->id);

        return redirect()->route('workspace')->with('pesan', 'Catatan kerja disimpan.');
    }

    /** Tugas cepat: tugas pribadi admin pada koperasi yang dipilih. */
    public function tugas_cepat(Request $request)
    {
        $u = $request->user();
        if ($u->manajerKoperasi()) {
            abort(403, 'Tugas cepat hanya untuk pengelola wilayah maupun admin Agrinas.');
        }

        // Dua jalur: pemilih ber-JS mengirim id; jalur tanpa-JS mengirim KODE
        // koperasi (daftar lengkap tidak pernah dirender — lihat view).
        $data = $request->validate([
            'koperasi_id' => ['nullable', 'integer'],
            'kode_koperasi' => ['nullable', 'string', 'max:30'],
            'judul' => ['required', 'string', 'max:160'],
            // Keterangan tugas: kolom `deskripsi` sudah ada di tabel sejak awal
            // tetapi selalu diisi teks tetap — sekarang dipakai sungguhan.
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'deadline' => ['nullable', 'date'],
        ], [], ['koperasi_id' => 'koperasi', 'kode_koperasi' => 'kode koperasi',
                'judul' => 'judul', 'deskripsi' => 'keterangan', 'deadline' => 'tenggat']);

        // ---- Dua mode: satu koperasi tertentu, atau kirim ke banyak koperasi.
        $sasaran = mb_strtoupper((string) $request->input('sasaran', 'KOPERASI'));
        if ($sasaran !== 'KOPERASI') {
            $m = $request->validate([
                'sasaran' => ['required', 'in:'.implode(',', array_keys(Tugas::SASARAN))],
                'wilayah' => ['nullable', 'string', 'max:80'],
            ], [], ['wilayah' => 'wilayah sasaran']);
            if (in_array($sasaran, ['PROVINSI', 'KABUPATEN', 'KECAMATAN'], true)
                && trim((string) ($m['wilayah'] ?? '')) === '') {
                return redirect()->route('workspace')->with('galat',
                    'Wilayah sasaran belum dipilih untuk '.Tugas::SASARAN[$sasaran].'.');
            }
            $n = $this->kirimMassal($request, array_merge($data, $m, ['sasaran' => $sasaran]), $u);
            jurnal($u->id, 'TUGAS_MASSAL', $sasaran.' '.($m['wilayah'] ?? ''),
                $data['judul'].' x'.$n, $request->ip() ?? '');

            return redirect()->route('workspace')->with('pesan', $n === 0
                ? 'Tidak ada koperasi yang cocok dengan sasaran itu.'
                : 'Tugas "'.$data['judul'].'" dikirim ke '.number_format($n, 0, ',', '.').' koperasi.');
        }

        $kop = null;
        if (! empty($data['koperasi_id'])) {
            $kop = $u->koperasiQuery()->whereKey($data['koperasi_id'])->first();
        } elseif (! empty($data['kode_koperasi'])) {
            $kop = $u->koperasiQuery()->where('kode', mb_strtoupper(trim($data['kode_koperasi'])))->first();
        }
        if (! $kop) {
            // Kewenangan diperiksa DI SINI: koperasi di luar wilayah PIC tidak
            // akan ditemukan oleh koperasiQuery(), jadi tidak bisa dijadikan
            // sasaran tugas walau id-nya dikirim langsung.
            return redirect()->route('workspace')->with('galat',
                'Koperasi belum dipilih, atau berada di luar wewenang akun Anda.');
        }
        $data['koperasi_id'] = $kop->id;

        $t = Tugas::create([
            'koperasi_id' => $data['koperasi_id'], 'judul' => $data['judul'],
            'deskripsi' => $data['deskripsi'] ?? '', 'sasaran' => 'KOPERASI', 'jenis' => 'DOKUMEN',
            'prioritas' => 'NORMAL', 'deadline' => ($data['deadline'] ?? null) ?: null, 'status' => 'BARU',
            'dibuat_oleh' => $u->id, 'dibuat' => kini(), 'diperbarui' => kini(),
        ]);

        jejak($t->id, (string) $u->nama, 'DIBUAT', '', 'BARU', 'Dari Ruang Kerja');
        jurnal($u->id, 'TUGAS_BARU', (string) $t->id, $t->judul, $request->ip() ?? '');

        return redirect()->route('workspace')->with('pesan', 'Tugas "'.$t->judul.'" dibuat.');
    }

    /** Satu berkas catatan per akun. */
    private function berkas(Request $request): string
    {
        return 'catatan/'.$request->user()->id.'.txt';
    }

    private function baca_catatan(Request $request): string
    {
        $u = $request->user();

        // Catatan harian disimpan di R2, dan SATU panggilan ke R2 saja ~600 ms
        // (dulu bahkan dua: exists() lalu get()). Halaman Ruang Kerja jadi
        // terasa lag padahal query-nya cuma 4 ms. Simpan isinya di cache 10
        // menit; `simpan_catatan()` membuangnya begitu pengguna menyimpan.
        // ponytail: catatan yang ditulis dari perangkat lain baru terlihat
        // <=10 menit; turunkan TTL kalau Catatan dipakai bersama.
        return Cache::remember('catatan.'.$u->id, 600, function () use ($u) {
            try {
                return (string) Storage::disk(self::DISK)->get('catatan/'.$u->id.'.txt');
            } catch (\Throwable) {
                return '';
            }
        });
    }

    // ------------------------------------------------------------- Messenger
    //
    // Cara kerja mengikuti Lark, rasa mengikuti WhatsApp:
    //
    //   Lark    — penyaring kategori (belum dibaca/pribadi/grup/sebutan/tersemat),
    //             balas berkutip, utas, reaksi emoji, sematkan pesan, sebutan @,
    //             kirim terjadwal, teruskan, sunting, tarik
    //   WhatsApp— gelembung dua warna, centang terkirim/dibaca, lampiran foto/
    //             berkas/pesan suara, gulir-tak-berujung, percakapan tersemat
    //
    // Satu ruang bisa memuat ribuan pesan, jadi riwayat TIDAK pernah dikirim
    // seluruhnya: halaman pertama = SEKAT pesan terakhir, sisanya diambil saat
    // pengguna menggulir ke atas (lihat muatanRuang & pesan-ruang.blade.php).

    /** Jumlah pesan per gelombang (halaman pertama & tiap "muat lebih lama"). */
    private const SEKAT = 15;

    /**
     * Kategori penyaring percakapan — nilai yang dikenali ?kategori=.
     *
     * Label yang tampil SAMA di desktop dan ponsel — tidak ada versi ringkas.
     * Dulu ada `KATEGORI_PENDEK` ("Belum", "Semat") untuk menghemat lebar, tetapi
     * memotong kata menghilangkan makna. Sekarang lebar dihemat lewat CSS
     * (padding & huruf pil) sehingga kelima label utuh tetap muat satu baris.
     *
     * "Sebutan" sudah DIHAPUS dari daftar atas permintaan: penyaring itu hanya
     * menyisakan ruang yang punya sebutan belum dibaca, sehingga daftar
     * percakapan justru menyusut jadi kosong saat tidak ada yang menyebut.
     * Hitungan sebutan tetap dipakai untuk lencana "@n" pada baris percakapan —
     * informasi itu berguna, penyaringnya yang tidak.
     */
    public const KATEGORI = [
        'semua' => 'Semua',
        'belum' => 'Belum dibaca',
        'pribadi' => 'Pribadi',
        'grup' => 'Grup',
        'tersemat' => 'Disematkan',
    ];


    /** Daftar ruang obrolan dalam lingkup pengguna + ringkasan tiap ruang. */
    public function pesan(Request $request)
    {
        $u = $request->user();
        $daftar = $this->daftarRuang($u);
        // Forum = ruang yang punya topik. Dihitung dari daftar yang SUDAH ada
        // (kolomnya ikut terambil), bukan query kedua.
        $forum = $daftar->filter(fn ($r) => trim((string) $r->topik) !== '')->values();
        $kategori = (string) $request->query('kategori', 'semua');
        $kategori = array_key_exists($kategori, self::KATEGORI) ? $kategori : 'semua';
        $daftar = $this->saringKategori($daftar, $kategori, $u);

        return view('workspace.pesan', [
            'aktif' => 'workspace', 'daftar' => $daftar,
            'koperasi' => $this->koperasiPilihan($u), 'rekan' => $this->rekanPilihan($u),
            'bahasa' => self::BAHASA, 'kategori' => $kategori,
            'kategoriDaftar' => self::KATEGORI,
            'mod' => 'pesan',
            'forum' => $forum, 'lingkupDaftar' => $this->lingkupDaftar($u),
        ]);
    }

    /**
     * Saring daftar percakapan menurut kategori (Lark: "Filters and labels").
     *
     * Dipisah dari daftarRuang karena penyaringan INI hanya soal apa yang
     * ditampilkan — bukan soal data mana yang boleh dilihat (itu urusan
     * ruangQuery). Tanpa pemisahan, mudah tergoda menyaring di query dan
     * membuat hak akses bergantung pada pil yang ditekan pengguna.
     */
    private function saringKategori(\Illuminate\Support\Collection $daftar, string $kategori, User $u)
    {
        return match ($kategori) {
            'belum' => $daftar->filter(fn ($r) => (int) $r->belum > 0)->values(),
            'tersemat' => $daftar->filter(fn ($r) => (bool) $r->tersemat)->values(),
            // `pribadi()` — BUKAN perbandingan `kunci` dengan teks 'pribadi'.
            // Kunci ruang pribadi berisi pasangan id ("15:33"), jadi perbandingan
            // lama tidak pernah cocok dan kategori ini selalu kosong.
            'pribadi' => $daftar->filter(fn ($r) => $r->pribadi())->values(),
            'grup' => $daftar->filter(fn ($r) => ! $r->pribadi()
                && in_array($r->kunci, ['anggota', ''], true))->values(),
            default => $daftar,
        };
    }

    /**
     * Ruang yang layak tampil, sudah dilengkapi cuplikan, jumlah belum dibaca,
     * sebutan, dan sematan.
     *
     * Semuanya dikerjakan dengan query AGREGAT (satu per kebutuhan, bukan satu
     * per baris ruang): daftar bisa berisi puluhan percakapan dan sebelumnya
     * pola "satu query per baris" inilah yang membuat halaman ini lambat.
     */
    private function daftarRuang(User $u, ?string $cari = null)
    {
        $ruang = $this->ruangQuery($u)->orderByDesc('id')->get();

        // Judul percakapan pribadi = nama LAWAN bicara (satu baris melayani dua
        // orang, jadi namanya sengaja netral di tabel).
        $pribadi = $ruang->filter(fn ($x) => $x->pribadi());
        if ($pribadi->isNotEmpty()) {
            $peta = AnggotaObrolan::whereIn('ruang_id', $pribadi->pluck('id'))
                ->where('user_id', '!=', $u->id)->pluck('user_id', 'ruang_id');
            $nama = User::whereIn('id', $peta->values())->pluck('nama', 'id');
            foreach ($pribadi as $r) {
                $lawan = $nama[$peta[$r->id] ?? 0] ?? 'Pengguna';
                $r->setAttribute('nama_tampil', $lawan);
                $r->setAttribute('inisial', mb_strtoupper(mb_substr($lawan, 0, 1)));
            }
        }

        $id = $ruang->pluck('id');

        // Cuplikan pesan terakhir: keyBy menyimpan kemunculan TERAKHIR karena
        // hasilnya sudah urut id — cukup satu query untuk semua ruang.
        $akhir = $this->pesanTampil()->whereIn('ruang_id', $id)->orderBy('id')
            ->get(['id', 'ruang_id', 'nama', 'oleh', 'isi', 'jenis', 'lampiran_nama', 'dibuat', 'dihapus', 'kirim_pada'])
            ->keyBy('ruang_id');

        // Posisi baca + sematan milik saya: satu query.
        $baca = BacaObrolan::where('user_id', $u->id)->whereIn('ruang_id', $id)->get()->keyBy('ruang_id');

        // Belum dibaca per ruang: satu query agregat. COALESCE dipakai supaya
        // ruang yang belum pernah dibuka (belum ada baris) dihitung dari nol,
        // bukan hilang dari hasil sama sekali.
        $belum = PesanObrolan::query()
            ->leftJoin('obrolan_baca as b', function ($j) use ($u) {
                $j->on('b.ruang_id', '=', 'obrolan_pesan.ruang_id')->where('b.user_id', '=', $u->id);
            })
            ->whereIn('obrolan_pesan.ruang_id', $id)
            ->whereColumn('obrolan_pesan.id', '>', \Illuminate\Support\Facades\DB::raw('coalesce(b.pesan_id, 0)'))
            ->where('obrolan_pesan.oleh', '!=', (string) $u->email)
            ->where('obrolan_pesan.dihapus', '')
            ->where(fn ($q) => $q->whereNull('obrolan_pesan.kirim_pada')->orWhere('obrolan_pesan.kirim_pada', '<=', kini()))
            ->groupBy('obrolan_pesan.ruang_id')
            ->selectRaw('obrolan_pesan.ruang_id as rid, count(*) as n')
            ->pluck('n', 'rid');

        // Sebutan yang belum dibaca: pesan yang memuat id saya di kolom `sebut`.
        // Pesan terjadwal yang belum waktunya juga tidak dihitung — ia belum
        // terlihat, jadi tidak boleh memunculkan lencana "belum dibaca".
        $sebut = PesanObrolan::query()
            ->leftJoin('obrolan_baca as b', function ($j) use ($u) {
                $j->on('b.ruang_id', '=', 'obrolan_pesan.ruang_id')->where('b.user_id', '=', $u->id);
            })
            ->whereIn('obrolan_pesan.ruang_id', $id)
            ->where('obrolan_pesan.sebut', 'like', '%,'.$u->id.',%')
            ->whereColumn('obrolan_pesan.id', '>', \Illuminate\Support\Facades\DB::raw('coalesce(b.pesan_id, 0)'))
            ->where('obrolan_pesan.oleh', '!=', (string) $u->email)
            ->where('obrolan_pesan.dihapus', '')
            ->where(fn ($q) => $q->whereNull('obrolan_pesan.kirim_pada')
                ->orWhere('obrolan_pesan.kirim_pada', '<=', kini()))
            ->groupBy('obrolan_pesan.ruang_id')
            ->selectRaw('obrolan_pesan.ruang_id as rid, count(*) as n')
            ->pluck('n', 'rid');

        foreach ($ruang as $r) {
            $p = $akhir[$r->id] ?? null;
            $r->setAttribute('akhir', $p);
            $r->setAttribute('belum', (int) ($belum[$r->id] ?? 0));
            $r->setAttribute('sebutan', (int) ($sebut[$r->id] ?? 0));
            $r->setAttribute('tersemat', ($baca[$r->id] ?? null)?->tersemat() ?? false);
        }

        if ($cari !== null && trim($cari) !== '') {
            $q = mb_strtolower(trim($cari));
            $ruang = $ruang->filter(fn ($r) => str_contains(
                mb_strtolower((string) ($r->nama_tampil ?? $r->nama)), $q
            ))->values();
        }

        return $ruang;
    }

    /**
     * Query pesan yang boleh terlihat.
     *
     * Sengaja HANYA menyaring jadwal — pesan yang sudah ditarik TETAP ikut.
     * Kalau ia dibuang di sini, riwayat percakapan jadi berlubang: gelembung
     * hilang tanpa jejak dan pembaca tak tahu ada apa di antara dua pesan.
     * Yang ditarik diganti jadi keterangan ("Pesan ini telah ditarik") di
     * petakanPesan(), sedangkan isinya sudah dimusnahkan saat penarikan.
     */
    private function pesanTampil()
    {
        return PesanObrolan::query()
            ->where(fn ($q) => $q->whereNull('kirim_pada')->orWhere('kirim_pada', '<=', kini()));
    }

    /**
     * Buka percakapan pribadi dengan pengguna lain, buat sekali saja.
     *
     * Kunci "min:max" membuat A-B dan B-A menunjuk ruang yang sama — tanpa itu
     * setiap orang punya salinan percakapan sendiri dan pesan bisa terpisah.
     */
    public function pesan_orang(Request $request)
    {
        $u = $request->user();
        $d = $request->validate(['user_id' => ['required', 'integer']], [], ['user_id' => 'pengguna']);
        // Gerbangnya SAMA dengan daftar rekan di formulir: id di luar jangkauan
        // tidak bisa dipakai membuka percakapan dengan menebak angkanya.
        $lawan = $this->rekanPilihan($u)->firstWhere('id', (int) $d['user_id']);
        if (! $lawan) {
            return back()->with('galat', 'Pengguna itu di luar jangkauan Anda.');
        }
        if ((int) $lawan->id === (int) $u->id) {
            return back()->with('galat', 'Tidak bisa mengobrol dengan diri sendiri.');
        }

        $kunci = AnggotaObrolan::kunciPribadi((int) $u->id, (int) $lawan->id);
        $r = RuangObrolan::where('kunci', $kunci)->first();
        if (! $r) {
            $r = RuangObrolan::create([
                'nama' => 'Percakapan Pribadi', 'jenis' => 'pribadi', 'kunci' => $kunci,
                'acuan' => '', 'acuan_id' => null, 'koperasi_id' => null,
                'dibuat_oleh' => $u->id, 'dibuat' => kini(),
            ]);
            foreach ([(int) $u->id, (int) $lawan->id] as $peserta) {
                AnggotaObrolan::create(['ruang_id' => $r->id, 'user_id' => $peserta, 'dibuat' => kini()]);
            }
        }

        return redirect()->route('workspace.pesan.ruang', $r->id);
    }

    /** Satu ruang obrolan beserta pesannya (halaman pertama riwayat). */
    public function pesan_ruang(Request $request, int $id)
    {
        $u = $request->user();
        $ruang = $this->ruangQuery($u)->whereKey($id)->firstOrFail();

        // Membuka ruang = membaca: posisi baca dimajukan ke pesan terakhir.
        // Inilah yang membuat centang biru dan lencana "belum dibaca" jujur.
        $this->tandaiTerbaca($u, $ruang->id);

        // Denyut ala WhatsApp: peramban hanya menanyakan pesan BARU, jadi jawab
        // JSON kecil. Tanpa cabang ini setiap detak memuat ulang seluruh halaman
        // (menu + tabel + CSS) untuk satu baris pesan — jauh lebih berat.
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($this->muatanRuang($u, $ruang,
                (int) $request->query('sejak', 0), (int) $request->query('sebelum', 0)));
        }

        $anggota = $ruang->kunci === '' ? collect() : $ruang->anggota()->get();
        $daftar = $this->daftarRuang($u);
        $terpilih = $daftar->firstWhere('id', $ruang->id);
        $ruang->setAttribute('nama_tampil', $terpilih->nama_tampil ?? $terpilih->nama ?? $ruang->nama);
        $ruang->setAttribute('tersemat', $terpilih->tersemat ?? false);

        return view('workspace.pesan-ruang', [
            'aktif' => 'workspace', 'mod' => 'pesan', 'ruang' => $ruang,
            'muatan' => $this->muatanRuang($u, $ruang, 0),
            'tautan' => $ruang->tautan(), 'bahasa' => self::BAHASA,
            'dokumen' => $this->dokumenQuery($u)->orderByDesc('id')->limit(30)->get(),
            'tugas' => Tugas::orderByDesc('id')->limit(30)->get(['id', 'judul']),
            'daftar' => $daftar, 'rekan' => $this->rekanPilihan($u),
            'koperasi' => $this->koperasiPilihan($u),
            'anggota' => $anggota,
            'namaAnggota' => User::whereIn('id', $anggota->pluck('user_id'))->pluck('nama', 'id'),
            'kategoriDaftar' => self::KATEGORI,
            'lingkupDaftar' => $this->lingkupDaftar($u),
        ]);
    }

    /**
     * Muatan satu ruang sebagai larik siap-JSON: pesan, reaksi, status dibaca.
     *
     * Dipakai TIGA jalur dengan satu bentuk data: render halaman pertama (blade),
     * jawaban denyut (?sejak=<id>), dan "muat lebih lama" (?sebelum=<id>).
     * Satu bentuk berarti satu tempat untuk membetulkan perilaku.
     */
    private function muatanRuang(User $u, RuangObrolan $ruang, int $sejak = 0, int $sebelum = 0): array
    {
        $q = $this->pesanTampil()->where('ruang_id', $ruang->id);
        // Papan pesan tidak pernah menampilkan pesan yang DITARIK sebagai isi:
        // gelembungnya dikirim sebagai keterangan saja (isinya sudah musnah),
        // tapi urutannya tetap ada supaya riwayat tidak berlubang.
        $lebihLama = false;

        if ($sejak > 0) {
            // Denyut: hanya pesan yang lebih baru (dibatasi 200 supaya satu
            // gelombang tak pernah jadi jawaban raksasa).
            $pesan = $q->where('id', '>', $sejak)->orderBy('id')->limit(200)->get();
        } elseif ($sebelum > 0) {
            // "Muat lebih lama": SEKAT pesan sebelum yang paling tua di layar,
            // lalu dibalik supaya urut naik.
            $pesan = $q->where('id', '<', $sebelum)->orderByDesc('id')->limit(self::SEKAT)->get()
                ->sortBy('id')->values();
            $lebihLama = $pesan->isNotEmpty();
        } else {
            // Halaman pertama: SEKAT pesan TERAKHIR. Mengambil dari depan akan
            // mengirim pesan paling tua dulu — itu bukan yang ingin dibaca orang.
            $pesan = $q->orderByDesc('id')->limit(self::SEKAT)->get()->sortBy('id')->values();
            $lebihLama = $this->pesanTampil()->where('ruang_id', $ruang->id)
                ->where('id', '<', (int) ($pesan->first()->id ?? 0))->exists();
        }

        // Reaksi: satu query untuk semua pesan di gelombang ini.
        $reaksi = ReaksiObrolan::whereIn('pesan_id', $pesan->pluck('id'))
            ->orderBy('id')->get()->groupBy('pesan_id');

        // Siapa yang menyebut saya (untuk menandai "@Anda" di gelembung).
        $namaSaya = (int) $u->id;

        // Balasan berkutip: satu query untuk seluruh induk yang dirujuk.
        $indukId = $pesan->pluck('balas_id')->filter()->unique()->values();
        $induk = $indukId->isEmpty() ? collect()
            : PesanObrolan::whereIn('id', $indukId)->get(['id', 'nama', 'oleh', 'isi', 'jenis', 'lampiran_nama', 'dihapus'])
                ->keyBy('id');

        // "Dibaca sampai" milik orang LAIN di ruang ini: dasar centang biru.
        $lain = BacaObrolan::where('ruang_id', $ruang->id)->where('user_id', '!=', $u->id)->get();
        $dibacaSampai = (int) $lain->max('pesan_id');
        $adaLawan = $lain->isNotEmpty();

        $semua = $pesan->map(function (PesanObrolan $p) use ($u, $reaksi, $induk, $dibacaSampai, $adaLawan, $namaSaya, $ruang) {
            $i = $induk[$p->balas_id] ?? null;
            $ditarik = $p->ditarik();
            $saya = strcasecmp((string) $p->oleh, (string) $u->email) === 0;

            return [
                'id' => (int) $p->id,
                'nama' => (string) ($p->nama ?: $p->oleh),
                'oleh_id' => $this->idPengguna((string) $p->oleh, (string) $p->nama),
                // Pesan ditarik: isinya sudah dimusnahkan saat penarikan; yang
                // dikirim ke peramban hanya keterangannya.
                'isi' => $ditarik ? '' : (string) $p->isi,
                'terjemahan' => $ditarik ? '' : (string) $p->terjemahan,
                'ditarik' => $ditarik,
                'bahasa' => (string) $p->bahasa,
                'waktu' => tanggal_id($p->dibuat, 'H:i'),
                'hari' => tanggal_id($p->dibuat, 'd M Y'),
                'tanggal' => substr((string) $p->dibuat, 0, 10),
                'saya' => $saya,
                // Hapus: pengirimnya sendiri, atau pembuat grup (jenis grup).
                'boleh_hapus' => $saya || ($ruang->jenis === 'grup'
                    && (int) $ruang->dibuat_oleh === (int) $u->id),
                'jenis' => $ditarik ? 'teks' : (string) ($p->jenis ?: 'teks'),
                'lampiran' => $ditarik ? '' : (string) $p->lampiran,
                'lampiran_nama' => (string) $p->lampiran_nama,
                'lampiran_ukuran' => (int) $p->lampiran_ukuran,
                'suntingan' => (string) $p->disunting !== '',
                'disematkan' => (string) $p->disematkan !== '',
                'sebut_saya' => ! $ditarik && str_contains((string) $p->sebut, ','.$namaSaya.','),
                'dibaca' => $adaLawan && (int) $p->id <= $dibacaSampai,
                'reaksi' => ($reaksi[$p->id] ?? collect())
                    ->groupBy('emoji')
                    ->map(fn ($g, $e) => ['emoji' => (string) $e, 'jumlah' => $g->count(),
                        'saya' => $g->contains(fn ($x) => (int) $x->user_id === $namaSaya)])
                    ->values()->all(),
                'balas' => $i ? [
                    'id' => (int) $i->id,
                    'nama' => (string) ($i->nama ?: $i->oleh),
                    'cuplikan' => $i->ditarik() ? 'Pesan ini telah ditarik'
                        : (new PesanObrolan(['jenis' => $i->jenis, 'isi' => $i->isi,
                            'lampiran_nama' => $i->lampiran_nama]))->cuplikan(70),
                ] : null,
            ];
        })->all();

        // HTML gelembung dirender SERVER memakai partial yang sama dengan
        // halaman pertama. Ini yang membuat peramban tak perlu punya penyusun
        // gelembung sendiri: satu bentuk, satu tempat membetulkannya.
        $namaSebut = $ruang->kunci === '' ? collect()
            : User::whereIn('id', $ruang->anggota()->pluck('user_id'))->pluck('nama')->values();
        $html = '';
        $lanjutNama = null;
        foreach ($semua as $i => $lp) {
            $html .= view('workspace._pesan-satu', [
                'p' => $lp, 'lanjut' => $i > 0 && $lanjutNama === $lp['nama'],
                'namaSebut' => $namaSebut, 'ruang' => $ruang, 'bahasa' => self::BAHASA,
                'teruskanId' => 'wa-teruskan-form',
            ])->render();
            // Untuk gelombang "lebih lama" pengelompokan nama tidak boleh
            // bergantung pada pesan pertama gelombang itu (bisa salah pisah).
            $lanjutNama = $lp['nama'];
        }

        return ['ruang' => (int) $ruang->id, 'pesan' => $semua, 'lebih_lama' => $lebihLama,
            'html' => $html, 'saya' => (int) $u->id, 'dibaca_sampai' => $dibacaSampai];
    }

    /**
     * Email pesan -> id pengguna, untuk fitur yang butuh identitas (sebutan,
     * "kirim ke"). Satu query untuk nama yang muncul, bukan satu per pesan.
     */
    private function idPengguna(string $email, string $nama): ?int
    {
        static $peta = null;
        if ($peta === null) {
            $peta = User::pluck('id', 'email')->all();
        }

        return $peta[$email] ?? null;
    }

    /**
     * Larik `sebut` (",12,44,") dari isi pesan: nama pengguna yang diawali `@`.
     * Teks `@Nama` sengaja TIDAK dibersihkan — yang melihat nama itu mengenal
     * orangnya, dan menyembunyikan `@` membuat kalimat jadi aneh.
     */
    private function bacaSebutan(RuangObrolan $ruang, string $isi): string
    {
        if (! str_contains($isi, '@')) {
            return '';
        }

        $anggota = $ruang->kunci === '' ? collect() : $ruang->anggota()->pluck('user_id');
        $kandidat = $anggota->isNotEmpty()
            ? User::whereIn('id', $anggota)->get(['id', 'nama'])
            : User::orderBy('id')->limit(300)->get(['id', 'nama']);

        $id = [];
        foreach ($kandidat as $k) {
            if ($k->nama !== '' && mb_stripos($isi, '@'.$k->nama) !== false) {
                $id[] = (int) $k->id;
            }
        }

        return $id === [] ? '' : ','.implode(',', array_unique($id)).',';
    }

    /** Majukan posisi baca saya ke pesan terakhir yang sudah tampil. */
    private function tandaiTerbaca(User $u, int $ruangId): void
    {
        $akhir = (int) $this->pesanTampil()->where('ruang_id', $ruangId)->max('id');
        $b = BacaObrolan::firstOrNew(['ruang_id' => $ruangId, 'user_id' => $u->id]);
        if ($akhir > (int) $b->pesan_id || (int) $b->dibaca_pada === 0) {
            $b->pesan_id = max($akhir, (int) $b->pesan_id);
        }
        $b->dibaca_pada = time();
        $b->save();
    }

    /** Buat ruang: grup, utas topik, atau ruang yang menempel pada dokumen/rapat. */
    public function pesan_ruang_baru(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'jenis' => ['nullable', 'in:grup,topik'],
            'koperasi_id' => ['nullable', 'integer'],
            'acuan' => ['nullable', 'in:dokumen,rapat,tugas'],
            'acuan_id' => ['nullable', 'integer'],
            'anggota' => ['nullable', 'array', 'max:200'],
            'anggota.*' => ['integer'],
            // Lingkup menurunkan pesertanya dari data (wilayah/peran/koperasi);
            // topik membuat ruangnya jadi utas forum.
            'lingkup' => ['nullable', 'string', 'max:64'],
            'mode' => ['nullable', 'in:grup,forum'],
            'topik' => ['nullable', 'string', 'max:500'],
        ], [], ['nama' => 'nama ruang', 'koperasi_id' => 'koperasi', 'acuan_id' => 'acuan',
                'lingkup' => 'lingkup peserta', 'topik' => 'topik forum']);

        $kop = null;
        if (! empty($d['koperasi_id'])) {
            $kop = $this->koperasiPilihan($u)->firstWhere('id', (int) $d['koperasi_id'])?->id;
            if (! $kop) {
                return back()->with('galat', 'Koperasi di luar lingkup Anda.');
            }
        } elseif ($u->manajerKoperasi()) {
            $kop = Koperasi::where('manajer_id', $u->id)->value('id');
        }

        // Peserta yang dipilih menjadikan grup ini TERTUTUP: hanya mereka yang
        // bisa membukanya, bukan seluruh lingkup koperasi.
        $anggota = collect($d['anggota'] ?? [])->map(fn ($v) => (int) $v)
            ->reject(fn ($v) => $v === (int) $u->id)->unique()
            ->filter(fn ($v) => User::whereKey($v)->exists())->values();

        // Lingkup: pesertanya DITURUNKAN (semua PIC provinsi itu, semua manajer
        // kabupaten itu, dst). Digabung dengan pilihan manual, jadi "lingkup +
        // dua orang tambahan" juga sah. Daftar pilihan yang sama dipakai untuk
        // memvalidasi, jadi nilai di luar lingkup pembuat mustahil lolos.
        $lingkup = trim((string) ($d['lingkup'] ?? ''));
        if ($lingkup !== '') {
            if (! $this->lingkupSah($u, $lingkup)) {
                return back()->with('galat', 'Lingkup peserta tidak dikenali.');
            }
            $anggota = $anggota->merge($this->lingkupPeserta($lingkup))->unique()->values();
        }

        $r = RuangObrolan::create([
            'nama' => $d['nama'], 'jenis' => ($d['jenis'] ?? '') ?: 'grup',
            'acuan' => $d['acuan'] ?? '', 'acuan_id' => ($d['acuan_id'] ?? null) ?: null,
            'koperasi_id' => $kop, 'dibuat_oleh' => $u->id, 'dibuat' => kini(),
            'kunci' => $anggota->isEmpty() ? '' : 'anggota',
            // Forum tanpa topik akan terbaca sebagai grup biasa di daftar (topik
            // = penandanya), jadi topik kosong diisi nama ruangnya sendiri.
            'topik' => trim((string) ($d['topik'] ?? '')) ?: (($d['mode'] ?? '') === 'forum' ? $d['nama'] : ''),
            'lingkup' => $lingkup,
        ]);

        if ($anggota->isNotEmpty()) {
            foreach ($anggota->push((int) $u->id)->unique() as $peserta) {
                AnggotaObrolan::create(['ruang_id' => $r->id, 'user_id' => $peserta, 'dibuat' => kini()]);
            }
        }

        $pesan = $anggota->isEmpty() ? 'Ruang obrolan dibuat (terbuka untuk lingkupnya).'
            : 'Ruang '.($lingkup === '' ? 'grup' : 'grup lingkup').' dibuat untuk '
                .$anggota->count().' peserta.';

        return redirect()->route('workspace.pesan.ruang', $r->id)->with('pesan', $pesan);
    }

    /**
     * Kirim pesan. Satu jalur untuk semua bentuk kiriman: teks, balasan,
     * terjadwal, dan lampiran (yang datang lewat rute terpisah karena
     * multipart tidak bisa dikirim oleh fetch JSON dengan rapi).
     */
    public function pesan_kirim(Request $request, int $id)
    {
        $u = $request->user();
        $ruang = $this->ruangQuery($u)->whereKey($id)->firstOrFail();
        $d = $request->validate([
            'isi' => ['required_without:lampiran', 'nullable', 'string', 'max:4000'],
            'bahasa' => ['nullable', 'string', 'max:12'],
            'konteks' => ['nullable', 'string', 'max:4000'],
            'balas_id' => ['nullable', 'integer'],
            'kirim_pada' => ['nullable', 'date'],
            'jenis' => ['nullable', 'in:teks,gambar,berkas,suara'],
            'lampiran' => ['nullable', 'file', 'max:4096'],
        ], [], ['isi' => 'pesan', 'kirim_pada' => 'waktu kirim']);

        $baru = $this->tambahPesan($u, $ruang, $d);
        if (! $baru) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['ok' => false, 'galat' => 'Pesan kosong.'], 422);
            }

            return back()->with('galat', 'Pesan kosong.');
        }

        // Kirim lewat AJAX: pengguna tetap di kotak tulis, tanpa muat ulang.
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'id' => (int) $baru->id]);
        }

        return redirect()->route('workspace.pesan.ruang', $ruang->id);
    }

    /**
     * Lampiran: foto, berkas, suara. Terpisah dari `pesan_kirim` karena
     * multipart tidak bisa dibaca dari badan JSON.
     */
    public function pesan_lampiran(Request $request, int $id)
    {
        $u = $request->user();
        $ruang = $this->ruangQuery($u)->whereKey($id)->firstOrFail();
        $d = $request->validate([
            // Gambar maks 6 MB (foto ponsel dikompresi di peramban lebih dulu),
            // berkas & suara 10 MB. Batas di sini menjaga disk, bukan menakut-nakuti.
            'berkas' => ['required', 'file', 'max:10240', 'extensions:'.implode(',', self::LAMPIRAN_EKSTENSI)],
            'jenis' => ['nullable', 'in:gambar,berkas,suara'],
            'isi' => ['nullable', 'string', 'max:4000'],
            'balas_id' => ['nullable', 'integer'],
        ], [
            'berkas.extensions' => 'Jenis berkas lampiran tidak diizinkan.',
        ], ['berkas' => 'berkas lampiran']);

        $f = $d['berkas'];
        $jenis = $d['jenis'] ?? ($this->gambarDariIsi($f) ? 'gambar' : 'berkas');

        $nama = 'obrolan/'.date('Ymd').'-'.bin2hex(random_bytes(8)).'.'.$f->getClientOriginalExtension();
        Storage::disk(self::DISK)->put($nama, (string) file_get_contents($f->getRealPath()));

        $baru = $this->tambahPesan($u, $ruang, [
            'isi' => trim((string) ($d['isi'] ?? '')),
            'balas_id' => $d['balas_id'] ?? null,
            'jenis' => $jenis,
        ], [
            'lampiran' => $nama, 'lampiran_nama' => mb_substr($f->getClientOriginalName(), 0, 180),
            'lampiran_ukuran' => (int) $f->getSize(), 'lampiran_jenis' => (string) $f->getMimeType(),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => (bool) $baru, 'id' => (int) ($baru->id ?? 0)]);
        }

        return redirect()->route('workspace.pesan.ruang', $ruang->id);
    }

    /** Pesan suara: rekaman peramban (webm/ogg), jenisnya dipaksa 'suara'. */
    public function pesan_suara(Request $request, int $id)
    {
        $u = $request->user();
        $ruang = $this->ruangQuery($u)->whereKey($id)->firstOrFail();
        $d = $request->validate(['berkas' => ['required', 'file', 'max:10240']], [], ['berkas' => 'rekaman suara']);
        $f = $d['berkas'];

        $nama = 'obrolan/'.date('Ymd').'-'.bin2hex(random_bytes(8)).'.webm';
        Storage::disk(self::DISK)->put($nama, (string) file_get_contents($f->getRealPath()));

        $baru = $this->tambahPesan($u, $ruang, ['isi' => '', 'jenis' => 'suara'], [
            'lampiran' => $nama, 'lampiran_nama' => 'pesan-suara.webm',
            'lampiran_ukuran' => (int) $f->getSize(), 'lampiran_jenis' => (string) $f->getMimeType(),
        ]);

        return ($request->ajax() || $request->wantsJson())
            ? response()->json(['ok' => (bool) $baru, 'id' => (int) ($baru->id ?? 0)])
            : redirect()->route('workspace.pesan.ruang', $ruang->id);
    }

    /**
     * Satu tempat pembuatan pesan: konteks tempelan, sebutan @, jadwal, isi
     * instruksi Lark ("kirim konteks langsung dari dokumen") — semua jalur
     * kirim lewat sini supaya aturannya tidak bercabang.
     */
    private function tambahPesan(User $u, RuangObrolan $ruang, array $d, array $extra = []): ?PesanObrolan
    {
        $isi = trim((string) ($d['isi'] ?? ''));
        $jenis = (string) ($d['jenis'] ?? ($extra['jenis'] ?? 'teks'));

        if ($isi === '' && ($extra['lampiran'] ?? '') === '') {
            return null;
        }

        // "Kirim konteks langsung dari dokumen atau rapat": isi acuan ditempelkan
        // di depan pesan, bukan disimpan sebagai tabel baru.
        $tautan = $ruang->tautan();
        if ($tautan && ! empty($d['konteks'])) {
            $isi = '> **'.$tautan[1].'** ('.$tautan[0].')'."\n\n".$isi;
        }

        // Pesan terjadwal: waktu di masa lalu = kirim sekarang (jangan diam-diam
        // jadi pesan yang tak pernah muncul).
        //
        // Waktu dari <input type="datetime-local"> = jam dinding WIB, sedangkan
        // seluruh stempel aplikasi ini UTC. Tanpa dikonversi, pesan terjadwal
        // 15:00 WIB tersimpan seolah 15:00 UTC = 22:00 WIB — terlambat 7 jam.
        $jadwal = trim((string) ($d['kirim_pada'] ?? ''));
        if ($jadwal !== '') {
            try {
                $waktu = \Illuminate\Support\Carbon::parse(str_replace('T', ' ', $jadwal), 'Asia/Jakarta')->utc();
                $jadwal = $waktu->greaterThan(now()) ? $waktu->isoFormat('YYYY-MM-DDTHH:mm:ssZ') : '';
            } catch (\Throwable) {
                $jadwal = '';
            }
        }

        return PesanObrolan::create(array_merge([
            'ruang_id' => $ruang->id, 'oleh' => (string) $u->email, 'nama' => (string) $u->nama,
            'isi' => $isi, 'bahasa' => (string) ($d['bahasa'] ?? ''), 'dibuat' => kini(),
            'balas_id' => ((int) ($d['balas_id'] ?? 0)) ?: null,
            'jenis' => $jenis,
            'sebut' => $this->bacaSebutan($ruang, $isi),
            'kirim_pada' => $jadwal === '' ? null : $jadwal,
        ], $extra));
    }

    /** Apakah berkas ini gambar? Diperiksa dari ISINYA, bukan ekstensi. */
    private function gambarDariIsi(\Illuminate\Http\UploadedFile $f): bool
    {
        return @getimagesize($f->getRealPath()) !== false;
    }

    /**
     * Aksi pada satu pesan (Lark menaruh semua ini di menu titik-tiga pesan):
     * reaksi emoji, sematkan, teruskan, sunting, tarik.
     */
    public function pesan_aksi(Request $request, int $id)
    {
        $u = $request->user();
        $ruang = $this->ruangQuery($u)->whereKey($id)->firstOrFail();
        $d = $request->validate([
            'aksi' => ['required', 'in:reaksi,sematkan,lepas-semat,teruskan,sunting,tarik,hapus'],
            'pesan_id' => ['required', 'integer'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'isi' => ['nullable', 'string', 'max:4000'],
            'ke' => ['nullable', 'array', 'max:20'],
            'ke.*' => ['integer'],
        ], [], ['pesan_id' => 'pesan']);

        $p = PesanObrolan::where('ruang_id', $ruang->id)->whereKey($d['pesan_id'])->firstOrFail();
        $milikSaya = strcasecmp((string) $p->oleh, (string) $u->email) === 0;
        $pesan = '';

        switch ($d['aksi']) {
            case 'reaksi':
                // Menekan emoji yang sama dua kali = membatalkan (perilaku Lark
                // & WhatsApp). Satu baris per pengguna per emoji.
                $emoji = (string) ($d['emoji'] ?? '👍');
                $ada = ReaksiObrolan::where('pesan_id', $p->id)->where('user_id', $u->id)
                    ->where('emoji', $emoji)->first();
                if ($ada) {
                    $ada->delete();
                } else {
                    ReaksiObrolan::create(['pesan_id' => (int) $p->id, 'user_id' => (int) $u->id,
                        'emoji' => $emoji, 'dibuat' => kini()]);
                }
                break;

            case 'sematkan':
                $p->forceFill(['disematkan' => kini()])->save();
                $pesan = 'Pesan disematkan.';
                break;

            case 'lepas-semat':
                $p->forceFill(['disematkan' => ''])->save();
                $pesan = 'Sematan dilepas.';
                break;

            case 'teruskan':
                // Teruskan ke BEBERAPA ruang sekaligus (Lark "forward"): pesan
                // disalin apa adanya, termasuk lampirannya (berkasnya sama).
                $tujuan = RuangObrolan::whereIn('id', $d['ke'] ?? [])->get()
                    ->filter(fn ($r) => $this->ruangQuery($u)->whereKey($r->id)->exists());
                foreach ($tujuan as $r) {
                    PesanObrolan::create([
                        'ruang_id' => $r->id, 'oleh' => (string) $u->email, 'nama' => (string) $u->nama,
                        'isi' => (string) $p->isi, 'terjemahan' => '', 'bahasa' => '', 'dibuat' => kini(),
                        'jenis' => (string) ($p->jenis ?: 'teks'), 'lampiran' => (string) $p->lampiran,
                        'lampiran_nama' => (string) $p->lampiran_nama, 'lampiran_ukuran' => (int) $p->lampiran_ukuran,
                        'lampiran_jenis' => (string) $p->lampiran_jenis,
                    ]);
                }
                $pesan = 'Diteruskan ke '.$tujuan->count().' percakapan.';
                break;

            case 'sunting':
                abort_unless($milikSaya, 403, 'Hanya pengirimnya yang bisa menyunting.');
                $isi = trim((string) ($d['isi'] ?? ''));
                if ($isi !== '') {
                    $p->forceFill(['isi' => $isi, 'sebut' => $this->bacaSebutan($ruang, $isi),
                        'disunting' => kini(), 'terjemahan' => ''])->save();
                    $pesan = 'Pesan disunting.';
                }
                break;

            case 'tarik':
                // "Tarik kembali" ala WhatsApp: baris tetap ada (supaya urutan
                // percakapan tidak berlubang) tapi isinya dimusnahkan.
                abort_unless($milikSaya, 403, 'Hanya pengirimnya yang bisa menarik pesannya.');
                $p->forceFill(['isi' => '', 'terjemahan' => '', 'lampiran' => '',
                    'lampiran_nama' => '', 'lampiran_ukuran' => 0, 'jenis' => 'teks',
                    'sebut' => '', 'dihapus' => kini()])->save();
                $pesan = 'Pesan ditarik.';
                break;

            case 'hapus':
                // Hapus permanen: pengirimnya sendiri, atau pembuat grup
                // (`dibuat_oleh`) yang menertibkan pesan peserta lain. Ruang
                // pribadi/dokumen sengaja TIDAK memberi hak itu ke pembuatnya.
                $pembuatGrup = $ruang->jenis === 'grup' && (int) $ruang->dibuat_oleh === (int) $u->id;
                abort_unless($milikSaya || $pembuatGrup, 403,
                    'Hanya pengirim pesan atau pembuat grup yang bisa menghapus.');
                if ((string) $p->lampiran !== '') {
                    Storage::disk(self::DISK)->delete($p->lampiran);
                }
                // FK tidak aktif di Hub: reaksi harus dibuang sendiri, kalau
                // tidak barisnya jadi yatim yang menempel di id yang tak ada.
                ReaksiObrolan::where('pesan_id', $p->id)->delete();
                $p->delete();
                $pesan = 'Pesan dihapus.';
                break;
        }

        if ($request->ajax() || $request->wantsJson()) {
            // Muatan ulang yang sama dengan denyut, jadi peramban hanya perlu
            // menimpa papan pesannya — tanpa tahu bentuk JSON yang berbeda.
            return response()->json($this->muatanRuang($u, $ruang, 0) + ['ok' => true]);
        }

        return back()->with('pesan', $pesan);
    }

    /** Tandai ruang sudah dibaca sampai pesan tertentu (denyut & kejauhan tab). */
    public function pesan_baca(Request $request, int $id)
    {
        $u = $request->user();
        $ruang = $this->ruangQuery($u)->whereKey($id)->firstOrFail();
        $this->tandaiTerbaca($u, $ruang->id);

        return response()->json(['ok' => true]);
    }

    /**
     * Sematkan/lepas percakapan di puncak daftar (Lark "pin a chat to the top").
     * Menyimpan di baris baca milik SAYA saja — sematan bersifat pribadi.
     */
    public function pesan_semat(Request $request, int $id)
    {
        $u = $request->user();
        $ruang = $this->ruangQuery($u)->whereKey($id)->firstOrFail();
        $b = BacaObrolan::firstOrNew(['ruang_id' => $ruang->id, 'user_id' => $u->id]);
        $b->disematkan = $b->tersemat() ? '' : kini();
        if ((int) $b->dibaca_pada === 0) {
            $b->dibaca_pada = time();
        }
        $b->save();

        return back()->with('pesan', $b->tersemat() ? 'Percakapan disematkan.' : 'Sematan dilepas.');
    }

    /**
     * Pesan yang menempel pada konteks (dokumen/tugas) sebagai pilihan
     * cepat di kotak tulis — Lark menyebutnya "share to chat".
     */
    public function pesan_pilihan(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'rekan' => $this->rekanPilihan($u)->map(fn ($k) => ['id' => (int) $k->id, 'nama' => (string) $k->nama]),
            'dokumen' => $this->dokumenQuery($u)->orderByDesc('id')->limit(30)
                ->get(['id', 'judul'])->map(fn ($d) => ['id' => (int) $d->id, 'judul' => (string) $d->judul]),
            'tugas' => Tugas::orderByDesc('id')->limit(30)->get(['id', 'judul'])
                ->map(fn ($t) => ['id' => (int) $t->id, 'judul' => (string) $t->judul]),
        ]);
    }

    /** Berkas lampiran: hanya pesertanya (atau ruang lingkup terbuka) yang boleh. */
    public function pesan_lampiran_unduh(Request $request, int $id)
    {
        $u = $request->user();
        $p = PesanObrolan::whereKey($id)->firstOrFail();
        // Gerbang aksesnya sama dengan ruangnya — bukan URL bertanda tangan yang
        // bisa dibagikan ke siapa pun.
        $this->ruangQuery($u)->whereKey($p->ruang_id)->firstOrFail();
        abort_if((string) $p->lampiran === '' || (string) $p->dihapus !== '', 404, 'Lampiran tidak ada.');

        abort_unless(Storage::disk(self::DISK)->exists($p->lampiran), 404, 'Berkas lampiran hilang.');

        // MIME di kolom `lampiran_jenis` ditebak dari ISI berkas, jadi berkas
        // HTML/SVG yang lolos akan dijalankan peramban sebagai halaman di origin
        // aplikasi ini (cookie sesi ikut terbawa). Karena itu hanya jenis yang
        // memang untuk ditampilkan yang disajikan `inline`; selebihnya unduhan.
        $mime = strtolower((string) ($p->lampiran_jenis ?: 'application/octet-stream'));
        $tampil = preg_match('#^(image/(jpeg|png|webp|gif)|audio/[a-z0-9.+-]+|video/(mp4|webm)|application/pdf)$#', $mime) === 1;

        return Storage::disk(self::DISK)->response($p->lampiran, (string) ($p->lampiran_nama ?: 'lampiran'), [
            'Content-Type' => $tampil ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($tampil ? 'inline' : 'attachment').'; filename="'.addslashes((string) ($p->lampiran_nama ?: 'lampiran')).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Terjemahkan satu pesan; hasilnya disimpan supaya tidak menembak API berulang. */
    public function pesan_terjemah(Request $request, int $id)
    {
        $u = $request->user();
        $ruang = $this->ruangQuery($u)->whereKey($id)->firstOrFail();
        $d = $request->validate(['pesan_id' => ['required', 'integer'], 'bahasa' => ['required', 'string', 'max:12']]);

        $pesan = PesanObrolan::where('ruang_id', $ruang->id)->whereKey($d['pesan_id'])->firstOrFail();

        if ((string) $pesan->bahasa === $d['bahasa'] && (string) $pesan->terjemahan !== '') {
            return ($request->ajax() || $request->wantsJson())
                ? response()->json(['ok' => true, 'terjemahan' => (string) $pesan->terjemahan])
                : back();
        }
        $hasil = OnlyOffice::terjemahkan((string) $pesan->isi, $d['bahasa']);
        if ($hasil === null) {
            return ($request->ajax() || $request->wantsJson())
                ? response()->json(['ok' => false, 'galat' => 'Terjemahan tidak tersedia — Asisten AI belum siap.'], 503)
                : back()->with('galat', 'Terjemahan tidak tersedia — Asisten AI belum siap.');
        }
        $pesan->forceFill(['terjemahan' => $hasil, 'bahasa' => $d['bahasa']])->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'terjemahan' => $hasil]);
        }

        return back()->with('pesan', 'Pesan diterjemahkan ke '.$d['bahasa'].'.');
    }

    // ------------------------------------------------------------- PhotoCraft

    /**
     * Editor gambar PhotoCraft. Seluruh penggambaran terjadi DI PERAMBAN
     * pengunjung (WebAssembly), jadi halaman ini tidak menyentuh berkas di
     * server dan tidak perlu tabel apa pun. Yang dikerjakan Laravel cuma satu:
     * membingkai aplikasinya dengan kepala + bilah modul yang seragam dengan
     * halaman Ruang Kerja lain.
     */
    public function gambar()
    {
        return $this->sekali_segarkan(response()->view('workspace.gambar', [
            'aktif' => 'workspace',
            // Editor disalurkan Hub sendiri (lihat gambar_editor), jadi alamatnya
            // rute di bawah ini — bukan subdomain publik. Slash akhirnya penting:
            // dari situ editor menghitung letak berkas wasm/js-nya, dan tanpa itu
            // ia meminta berkas dari /workspace/ alih-alih dari editornya.
            'editor' => route('workspace.gambar.berkas').'/',
        ]));
    }

    /**
     * OpenCut: editor video (susun klip, potong, ekspor) di peramban.
     *
     * Berbeda dari PhotoCraft: berkasnya TIDAK disalurkan controller ini.
     * OpenCut adalah aplikasi penuh (TanStack/Next dengan aset /assets dan
     * navigasi /editor), jadi jalur per-berkas tidak cukup. Ia dilayani nginx
     * di /video/ — asal yang SAMA dengan Hub — dengan `auth_request` ke
     * /__video_cek, sehingga hanya pengguna yang sudah masuk bisa memuatnya.
     * Kalau nginx dimatikan, halaman ini menampilkan bingkai kosong: cek
     * `sudo nginx -t -c /etc/nginx/agrinas.conf` lalu `ss -ltn | grep 8086`.
     */
    public function video()
    {
        return $this->sekali_segarkan(response()->view('workspace.video', ['aktif' => 'workspace']));
    }

    /**
     * Buang cache peramban SEKALI per pengunjung, lalu beri tanda lewat cookie.
     *
     * Aset kedua editor sempat tersaji 404 karena regex statis nginx, dan
     * tanggapan 404 itu ikut tersimpan di peramban. Sesudah server dibetulkan,
     * peramban masih memakai salinan 404 lama sehingga editor tampak tanpa CSS
     * atau macet di "Loading" walau `curl` sudah 200. `Clear-Site-Data: cache`
     * menghapus salinan itu tanpa menyuruh pengguna membersihkan cache manual;
     * cookie penanda mencegahnya terjadi lagi pada kunjungan berikutnya.
     */
    private function sekali_segarkan($respons)
    {
        if (! request()->cookie('segarkan_editor')) {
            $respons->headers->set('Clear-Site-Data', '"cache"');
            $respons->headers->setCookie(cookie('segarkan_editor', '1', 60 * 24 * 365));
        }

        return $respons;
    }

    /**
     * Menyalurkan berkas editor PhotoCraft lewat Hub.
     *
     * Kontainer editor hanya mendengarkan di loopback dan tidak dijangkau dari
     * luar. Lewat rute ini (di dalam grup `auth`) hanya pengguna yang sudah masuk
     * yang bisa memuatnya, dan halaman + editor jadi SATU asal — cookie sesi Hub
     * tidak perlu dilebarkan ke subdomain mana pun.
     *
     * Isinya cuma diteruskan bita demi bita: tanpa menyentuh berkas di disk,
     * tanpa token di URL. Nama berkas dibatasi daftar putih supaya jalan pintas
     * ini tidak menjelma terowongan bebas ke jaringan dalam.
     */
    public function gambar_editor(string $berkas = '')
    {
        return $this->sajikan_editor('photocraft', 'Editor gambar', $berkas);
    }

    /**
     * Penyaji berkas editor WASM (PhotoCraft). OpenCut TIDAK lewat sini — ia
     * aplikasi penuh yang diproksikan nginx di /video/ (lihat video()).
     *
     * Kontainer editor hanya mendengarkan di loopback dan tidak dijangkau dari
     * luar. Lewat rute ini (di dalam grup `auth`) hanya pengguna yang sudah masuk
     * yang bisa memuatnya, dan halaman + editor jadi SATU asal — cookie sesi Hub
     * tidak perlu dilebarkan ke subdomain mana pun.
     *
     * Isinya cuma diteruskan bita demi bita: tanpa menyentuh berkas di disk,
     * tanpa token di URL. Nama berkas dibatasi daftar putih supaya jalan pintas
     * ini tidak menjelma terowongan bebas ke jaringan dalam.
     */
    private function sajikan_editor(string $layanan, string $sebutan, string $berkas = '')
    {
        // '' = halaman editornya sendiri.
        if ($berkas === '' || $berkas === 'index.html') {
            $berkas = 'index.html';
        } elseif (! preg_match('/^[A-Za-z0-9_-]+\.(?:js|wasm|css|json|map|ico|png|svg)$/', $berkas)) {
            abort(404);
        }

        // Sebutkan encoding yang peramban sanggup: nginx sendiri yang meng-gzip
        // berkas besar itu (gzip_static) dan kita teruskan apa adanya. Jangan
        // didekode lalu dikodekan ulang di sini — itu jalur paling mudah rusak.
        $terima = str_contains(strtolower(request()->header('Accept-Encoding', '')), 'gzip') ? 'gzip' : 'identity';
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => "Accept-Encoding: {$terima}\r\n",
            // 404 dari nginx diteruskan sebagai 404, bukan meletus jadi 500.
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $masuk = @fopen(rtrim((string) config("services.{$layanan}.loopback"), '/').'/'.$berkas, 'rb', false, $ctx);
        if ($masuk === false) {
            abort(502, "{$sebutan} tidak menjawab.");
        }

        $kode = 200; $tipe = 'application/octet-stream'; $pekat = '';
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $kode = (int) $m[1];
            } elseif (stripos($h, 'content-type:') === 0) {
                $tipe = trim(substr($h, 13));
            } elseif (stripos($h, 'content-encoding:') === 0) {
                $pekat = trim(substr($h, 17));
            }
        }

        return response()->stream(fn () => fpassthru($masuk), $kode, array_filter([
            'Content-Type' => $tipe,
            'Content-Encoding' => $pekat ?: null,
            // Jawaban ini berbeda menurut Accept-Encoding, jadi wajib disebut.
            'Vary' => 'Accept-Encoding',
            'X-Content-Type-Options' => 'nosniff',
            // Berkas ber-hash: aman di-cache lama di peramban TETAPI privat
            // (milik sesi pengguna) — jangan sampai cache bersama menyimpannya.
            'Cache-Control' => $berkas === 'index.html'
                ? 'no-store, private'
                : 'private, max-age=31536000, immutable',
        ]));
    }

    // ---------------------------------------------------------- Docs & Sheets

    /**
     * Daftar dokumen: saring + paging 10 baris, plus tab Pribadi / Bersama /
     * Publik. Semua daftar dibaca lewat SATU query beralingkup
     * (`dokumenQuery()`), jadi janji "dokumen pribadi hanya pemiliknya" tidak
     * bisa bocor lewat tab yang berbeda.
     */
    public function dokumen(Request $request)
    {
        $u = $request->user();

        // Bawaan = 'semua'. Dulu 'bersama' (koperasi saja), dan itu membuat
        // dokumen PUBLIK tidak muncul di halaman yang baru dibuka — pengguna
        // mengira dokumennya hilang, padahal hanya tersembunyi oleh tab.
        $tab = in_array($request->query('tab'), ['semua', 'bersama', 'pribadi', 'publik'], true)
            ? (string) $request->query('tab') : 'semua';
        $q = trim((string) $request->query('q', ''));
        $jenis = array_key_exists((string) $request->query('jenis'), Dokumen::JENIS)
            ? (string) $request->query('jenis') : '';

        $dasar = $this->dokumenQuery($u);

        // Kartu ringkasan: jumlah per kelompok, dari query beralingkup yang sama.
        $ringkas = [
            'semua' => (clone $dasar)->count(),
            'pribadi' => (clone $dasar)->where('akses', 'pribadi')->count(),
            'bersama' => (clone $dasar)->whereIn('akses', ['koperasi', 'tamu'])->count(),
            'publik' => (clone $dasar)->where('akses', 'publik')->count(),
        ];

        $daftar = match ($tab) {
            'pribadi' => (clone $dasar)->where('akses', 'pribadi'),
            'bersama' => (clone $dasar)->whereIn('akses', ['koperasi', 'tamu']),
            'publik' => (clone $dasar)->whereIn('akses', Dokumen::AKSES_TERBUKA),
            // 'semua': seluruh yang boleh dilihat — TIDAK ada dokumen yang
            // terjangkau akun ini tetapi tak bisa ditemukan dari daftar.
            default => $dasar,
        };

        if ($jenis !== '') {
            $daftar->where('jenis', $jenis);
        }
        if ($q !== '') {
            $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $daftar->where(fn ($w) => $w->where('judul', 'like', $pola)
                ->orWhere('berkas', 'like', $pola));
        }

        $halaman = Halaman::buat((clone $daftar)->count(), $request->query('halaman', 1),
            DasborController::PER_HALAMAN);
        $baris = $halaman->batasi((clone $daftar)->orderByDesc('diperbarui')->orderByDesc('id'))->get();
        // Pemilik & koperasi dipakai daftar untuk menampilkan "milik siapa" dan
        // tombol Bagikan/Buka — dimuat sekaligus, bukan satu query per baris.
        $baris->load(['pembuat', 'pemilik', 'koperasi']);

        // Status akses untuk baris yang tampil: permintaan/izin milik AKUN INI
        // dan permintaan orang lain yang menunggu keputusan akun ini.
        $ids = $baris->pluck('id');
        $milikSaya = $ids->isEmpty() ? collect() : DokumenAkses::query()
            ->where('pengguna_id', $u->id)->whereIn('dokumen_id', $ids)->get()->keyBy('dokumen_id');
        $bisaPutus = $ids->isEmpty() ? collect() : DokumenAkses::query()
            ->whereIn('status', ['diminta'])->whereIn('dokumen_id', $ids)->get();

        return view('workspace.dokumen', [
            'aktif' => 'workspace', 'baris' => $baris, 'halaman' => $halaman,
            'ringkas' => $ringkas, 'tab' => $tab, 'q' => $q, 'jenis' => $jenis,
            'aksesSaya' => $milikSaya, 'permintaan' => $bisaPutus,
            'koperasi' => $this->koperasiPilihan($u), 'siap' => OnlyOffice::siap(),
            'dasar' => OnlyOffice::dasar(),
        ]);
    }

    public function dokumen_baru(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'jenis' => ['required', 'in:'.implode(',', array_keys(Dokumen::JENIS))],
            'koperasi_id' => ['nullable', 'integer'],
            'akses' => ['required', 'in:'.implode(',', array_keys(Dokumen::AKSES))],
        ], [], ['judul' => 'judul', 'koperasi_id' => 'koperasi', 'akses' => 'akses']);

        // Koperasi hanya bermakna untuk akses terbatas. Dokumen pribadi dan
        // publik tidak menempel ke koperasi mana pun — itu yang membuat lingkup
        // koperasi tidak ikut menariknya (lihat dokumenQuery()).
        $akses = $d['akses'];
        $kop = null;
        if (in_array($akses, ['koperasi'], true)) {
            if (! empty($d['koperasi_id']) && ($k = $this->koperasiPilihan($u)->firstWhere('id', (int) $d['koperasi_id']))) {
                $kop = $k->id;
            } elseif ($u->manajerKoperasi()) {
                $kop = Koperasi::where('manajer_id', $u->id)->value('id');
            }
        }

        $dok = $this->dokumen->buat($u, $d['judul'], $d['jenis'], $kop, $akses);

        jurnal($u->id, 'DOKUMEN_BARU', $dok->jenis, $dok->judul.' ('.$akses.')', $request->ip() ?? '');

        return redirect()->route('workspace.dokumen.editor', $dok->id)
            ->with('pesan', match ($akses) {
                'pribadi' => 'Dokumen pribadi dibuat — hanya Anda yang bisa membukanya.',
                'publik' => 'Dokumen publik dibuat — semua pengguna bisa melihat, menyunting perlu izin.',
                'tamu' => 'Dokumen dibuat dan TERBUKA untuk disunting semua pengguna.',
                default => 'Dokumen dibuat dan bisa disunting bersama koperasinya.',
            });
    }

    /**
     * Boleh MENYUNTING dokumen ini?
     *
     * Terpisah dari "boleh membuka" — itu inti permintaan pemilik produk:
     * dokumen publik bisa dilihat semua orang, tetapi menyuntingnya harus
     * diminta. Satu tempat, dipakai halaman editor dan aksi bagikan.
     */
    private function bolehSunting(User $u, Dokumen $dok): bool
    {
        if ((int) $dok->dibuat_oleh === (int) $u->id || (int) $dok->pemilik_id === (int) $u->id) {
            return true;
        }
        // Dinaikkan ke "publik + izin sunting": semua pengguna boleh menyunting.
        if ($dok->akses === 'tamu') {
            return true;
        }
        // Dokumen koperasi: pengelola koperasi dokumen itu.
        if ($dok->akses === 'koperasi' && $dok->koperasi_id
            && $u->koperasiQuery()->whereKey($dok->koperasi_id)->exists()) {
            return true;
        }

        // Izin yang diberikan lewat tombol Bagikan / permintaan akses.
        return DokumenAkses::query()->where('dokumen_id', $dok->id)
            ->where('pengguna_id', $u->id)->where('status', 'diberi')->exists();
    }

    /** Halaman editor ONLYOFFICE. */
    /**
     * Menyiapkan semua yang dibutuhkan halaman editor.
     *
     * Dipisah dari `dokumen_editor()` karena sekarang ada DUA halaman yang
     * menampilkannya: halaman biasa (di dalam layout Hub) dan halaman PENUH
     * (dibuka di tab tersendiri). Kalau penyiapannya disalin, cepat atau lambat
     * salah satu jalur tertinggal — mis. satu memeriksa izin, yang lain lupa.
     *
     * @return array{dok: Dokumen, bolehSunting: bool, config: array, ruang: RuangObrolan,
     *               permintaanSaya: ?DokumenAkses, ukuran: int}
     */
    private function siapkanEditor(Request $request, int $id): array
    {
        $u = $request->user();
        // Boleh DILIHAT lewat lingkup biasa (daftar), atau karena sudah diberi izin.
        $dok = $this->dokumenQuery($u)->whereKey($id)->first();
        if (! $dok) {
            $izin = DokumenAkses::query()->where('dokumen_id', $id)
                ->where('pengguna_id', $u->id)->where('status', 'diberi')->exists();
            abort_unless($izin, 404);
            $dok = Dokumen::query()->whereKey($id)->firstOrFail();
        }
        $bolehSunting = $this->bolehSunting($u, $dok);

        // Ruang obrolan menempel pada dokumen. Bila belum ada, buat sekarang juga
        // supaya pembahasan selalu punya tempat — tanpa ini tautan "ruang
        // obrolannya" tidak pernah muncul dan tak ada cara membuatnya dari sini.
        $ruang = RuangObrolan::where('acuan', 'dokumen')->where('acuan_id', $dok->id)->first();
        if (! $ruang) {
            $ruang = RuangObrolan::create([
                'nama' => 'Pembahasan: '.mb_substr((string) $dok->judul, 0, 100),
                'jenis' => 'dokumen', 'acuan' => 'dokumen', 'acuan_id' => $dok->id,
                'koperasi_id' => $dok->pribadi ? null : ($dok->koperasi_id ?: null),
                'pemilik_id' => $dok->pribadi ? ($dok->dibuat_oleh ?: $u->id) : null,
                'dibuat_oleh' => $u->id, 'dibuat' => kini(),
            ]);
        }

        $tautan = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'workspace.dokumen.berkas', now()->addHour(), ['id' => $dok->id]);
        $callback = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'workspace.dokumen.simpan', now()->addHour(), ['id' => $dok->id]);

        // PDF tidak punya editor ONLYOFFICE: halaman ini menampilkan pratinjau
        // + tombol unduh / naikkan versi, bukan editor kosong yang membingungkan.
        $permintaanSaya = DokumenAkses::query()->where('dokumen_id', $dok->id)
            ->where('pengguna_id', $u->id)->first();
        // Ukuran berkas untuk panel PDF. Dikirim dari controller supaya view
        // tidak perlu menyentuh disk sama sekali.
        $ukuran = ($dok->jenis === 'pdf')
            ? (int) \Illuminate\Support\Facades\Storage::disk(DokumenPenyimpan::DISK)->size($dok->berkas)
            : 0;

        return [
            'dok' => $dok, 'bolehSunting' => $bolehSunting, 'ruang' => $ruang,
            'permintaanSaya' => $permintaanSaya, 'ukuran' => $ukuran,
            'config' => OnlyOffice::config($dok, $u, $tautan, $callback, $bolehSunting),
        ];
    }

    public function dokumen_editor(Request $request, int $id)
    {
        $d = $this->siapkanEditor($request, $id);

        return view('workspace.dokumen-editor', [
            'aktif' => 'workspace', 'dok' => $d['dok'], 'siap' => OnlyOffice::siap(),
            'dasar' => OnlyOffice::dasar(),
            'bolehSunting' => $d['bolehSunting'], 'permintaanSaya' => $d['permintaanSaya'],
            'ukuran' => $d['ukuran'],
            'config' => $d['config'],
            'ruang' => $d['ruang'],
        ]);
    }

    /**
     * Halaman editor PENUH — dibuka di tab tersendiri.
     *
     * Kenapa ada: di dalam layout Hub, editor hanya mendapat 70vh dan menyisakan
     * bilah sisi, kepala, serta nav modul yang tidak berguna saat sedang menulis
     * dokumen panjang. Halaman ini melepas semua itu: satu dokumen, satu layar.
     *
     * TIDAK memakai layout `utama` — sengaja. Tujuannya justru menghilangkan
     * kerangka itu. Karena itu ia juga memuat sendiri CSS/JS yang dibutuhkannya.
     */
    public function dokumen_penuh(Request $request, int $id)
    {
        $d = $this->siapkanEditor($request, $id);

        return view('workspace.dokumen-penuh', [
            'dok' => $d['dok'], 'siap' => OnlyOffice::siap(), 'dasar' => OnlyOffice::dasar(),
            'bolehSunting' => $d['bolehSunting'], 'config' => $d['config'],
        ]);
    }

    /**
     * Berkas mentah untuk diambil ONLYOFFICE. Ditandatangani (URL temporary),
     * jadi tidak bisa ditebak; pengguna biasa mengunduh lewat rute ini juga.
     */
    public function dokumen_berkas(Request $request, int $id)
    {
        // Tanpa cek sesi: ONLYOFFICE mengambil berkas ini sebagai klien anonim.
        // Penjaganya adalah tanda tangan URL (rute memakai middleware `signed`).
        $dok = Dokumen::findOrFail($id);
        $isi = $this->dokumen->isi($dok);

        if ($isi === null) {
            abort(404, 'Berkas dokumen tidak ada.');
        }

        $jenis = $dok->jenis === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        // Tanpa nama berkas: ONLYOFFICE hanya butuh bitanya, dan mengirim
        // Content-Disposition attachment membuatnya mencoba mengunduh.
        return response($isi, 200, ['Content-Type' => $jenis, 'Content-Length' => (string) strlen($isi)]);
    }

    /**
     * Minta izin menyunting sebuah dokumen. Satu baris per (dokumen, pengguna);
     * permintaan yang sudah ada diperbarui, bukan ditumpuk — supaya pemilik
     * dokumen tidak dibanjiri permintaan berulang dari orang yang sama.
     */
    public function dokumen_minta(Request $request, int $id)
    {
        $u = $request->user();
        // Cek "boleh dilihat", BUKAN "boleh disunting" — dokumen publik sengaja
        // bisa dilihat siapa pun, dan justru mereka yang meminta izin.
        $dok = $this->dokumenQuery($u)->whereKey($id)->first();
        if (! $dok) {
            $dok = Dokumen::query()->whereKey($id)->firstOrFail();
        }
        if ($this->bolehSunting($u, $dok)) {
            return back()->with('pesan', 'Anda sudah punya izin menyunting dokumen ini.');
        }

        $d = $request->validate(['pesan' => ['nullable', 'string', 'max:300']],
            [], ['pesan' => 'keterangan']);
        DokumenAkses::updateOrCreate(
            ['dokumen_id' => $dok->id, 'pengguna_id' => $u->id],
            ['pemohon_id' => $u->id, 'status' => 'diminta', 'pesan' => (string) ($d['pesan'] ?? ''),
             'diminta' => kini(), 'diputus' => null]);
        jurnal($u->id, 'DOKUMEN_MINTA', (string) $dok->id, $dok->judul, $request->ip() ?? '');

        return back()->with('pesan', 'Permintaan izin dikirim ke pemilik dokumen. '
            .'Anda bisa menyunting setelah disetujui.');
    }

    /** Pemilik/menyunting: putuskan permintaan, atau beri izin langsung. */
    public function dokumen_putus(Request $request, int $id)
    {
        $u = $request->user();
        $dok = $this->dokumenQuery($u)->whereKey($id)->firstOrFail();
        abort_unless($this->bolehSunting($u, $dok), 403, 'Hanya penyunting yang bisa memberi izin.');

        $d = $request->validate([
            'pengguna_id' => ['required', 'integer'],
            'putusan' => ['required', 'in:diberi,ditolak,dicabut'],
        ], [], ['pengguna_id' => 'pengguna', 'putusan' => 'keputusan']);

        if ((int) $d['pengguna_id'] === (int) $u->id) {
            return back()->with('galat', 'Izin Anda sendiri tidak perlu diatur di sini.');
        }

        $status = $d['putusan'] === 'diberi' ? 'diberi' : 'ditolak';
        $baris = DokumenAkses::updateOrCreate(
            ['dokumen_id' => $dok->id, 'pengguna_id' => (int) $d['pengguna_id']],
            ['pemutus_id' => $u->id, 'status' => $status, 'diputus' => kini()]);
        jurnal($u->id, 'DOKUMEN_IZIN', (string) $dok->id, $d['putusan'].' #'.$d['pengguna_id'],
            $request->ip() ?? '');

        return back()->with('pesan', $d['putusan'] === 'diberi'
            ? 'Izin menyunting diberikan.'
            : 'Izin menyunting tidak diberikan.');
    }

    /** Naikkan versi dokumen PDF dengan berkas yang diunggah sendiri. */
    public function dokumen_unggah(Request $request, int $id)
    {
        $u = $request->user();
        $dok = $this->dokumenQuery($u)->whereKey($id)->firstOrFail();
        abort_unless($this->bolehSunting($u, $dok), 403, 'Menyunting dokumen ini belum diizinkan.');

        $d = $request->validate([
            'berkas' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],
        ], [], ['berkas' => 'berkas PDF']);

        // `get()` (bukan storeAs): berkas selalu ditulis ke path yang sudah
        // ditentukan kolom `berkas`, jadi tak ada dua sumber kebenaran path.
        $this->dokumen->ganti($dok, (string) file_get_contents($d['berkas']->getRealPath()));
        jurnal($u->id, 'DOKUMEN_UNGGAH', (string) $dok->id, $d['berkas']->getClientOriginalName(),
            $request->ip() ?? '');

        return back()->with('pesan', 'Berkas PDF diperbarui.');
    }

    /**
     * Callback penyimpanan ONLYOFFICE.
     *
     * Status 2 = dokumen siap disimpan, 6 = paksa simpan. Kita wajib menjawab
     * `{"error":0}`; selain itu editor menampilkan galat simpan.
     */
    public function dokumen_simpan(Request $request, int $id)
    {
        $dok = Dokumen::find($id);
        if (! $dok) {
            return response()->json(['error' => 1]);
        }

        $status = (int) $request->input('status', 0);
        if (! in_array($status, [2, 6], true)) {
            return response()->json(['error' => 0]);
        }

        $tautan = (string) $request->input('url', '');
        if ($tautan === '') {
            return response()->json(['error' => 1]);
        }

        try {
            $res = \Illuminate\Support\Facades\Http::timeout(60)->get($tautan);
            if (! $res->successful() || $res->body() === '') {
                throw new \RuntimeException('Unduh hasil simpan gagal: HTTP '.$res->status());
            }
            $this->dokumen->simpan($dok, $res->body());
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 1]);
        }

        return response()->json(['error' => 0]);
    }

    // -------------------------------------------------------- Catatan Harian

    /**
     * Catatan harian = CATATAN PRIBADI. Setiap pengguna hanya melihat dan
     * mengubah catatannya sendiri — termasuk admin pusat, yang tidak membaca
     * catatan orang lain. Pengelompokan dilakukan lewat tanggal & jenis, bukan
     * lewat koperasi/wilayah.
     */
    public function catatan_harian(Request $request)
    {
        $u = $request->user();

        $tanggal = (string) $request->query('tanggal', now()->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = now()->toDateString();
        }
        $cari = trim((string) $request->query('q', ''));
        $jenis = array_key_exists((string) $request->query('jenis'), CatatanHarian::JENIS)
            ? (string) $request->query('jenis') : '';

        $dasar = CatatanHarian::query()->where('user_id', $u->id);

        // Jumlah per jenis = dari SELURUH catatan milik sendiri, bukan hanya
        // tanggal yang sedang dibuka — angka itu yang berguna sebagai gambaran.
        $ringkas = ['semua' => (clone $dasar)->count()];
        foreach (array_keys(CatatanHarian::JENIS) as $k) {
            $ringkas[$k] = (clone $dasar)->where('jenis', $k)->count();
        }

        // Semua catatan pada tanggal terpilih: ikut dipaging, bukan ditumpuk
        // tanpa batas — aturan repo: tiap daftar data 10 baris per halaman.
        $qTanggal = (clone $dasar)->where('tanggal', $tanggal);
        $halamanHari = Halaman::buat((clone $qTanggal)->count(), $request->query('halaman', 1),
            DasborController::PER_HALAMAN);

        $q = (clone $dasar);
        if ($cari !== '') {
            // `%` dan `_` dari pengguna diperlakukan sebagai huruf biasa, bukan
            // pola LIKE — kalau tidak, mencari "%" mengembalikan segalanya.
            $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], $cari).'%';
            $q->where(fn ($w) => $w->where('judul', 'like', $pola)
                ->orWhere('isi', 'like', $pola)->orWhere('kendala', 'like', $pola));
        }
        if ($jenis !== '') {
            $q->where('jenis', $jenis);
        }

        // Kunci halaman dipisah: halaman ini punya DUA daftar, dan satu kunci
        // untuk keduanya akan membuat pindah halaman di satu daftar menggeser
        // daftar lainnya (lihat tautan_halaman()).
        $halamanArsip = Halaman::buat((clone $q)->count(), $request->query('halaman_arsip', 1),
            DasborController::PER_HALAMAN);

        return view('workspace.catatan-harian', [
            'aktif' => 'workspace',
            'tanggal' => $tanggal,
            'cari' => $cari,
            'jenis' => $jenis,
            'ringkas' => $ringkas,
            // Dua daftar berhalaman: catatan tanggal yang dipilih, dan seluruh
            // catatan (mengikuti saringan) untuk penelusuran.
            'hariIni' => $halamanHari->batasi((clone $qTanggal)
                ->orderByDesc('diperbarui')->orderByDesc('id'))->get(),
            'arsip' => $halamanArsip->batasi($q->orderByDesc('tanggal')->orderByDesc('id'))->get(),
            'halamanHari' => $halamanHari,
            'halamanArsip' => $halamanArsip,
            'saranJudul' => CatatanHarian::SARAN_JUDUL,
        ]);
    }

    /** Simpan catatan: tanpa `id` = catatan baru, dengan `id` = ubah milik sendiri. */
    public function catatan_harian_simpan(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'id' => ['nullable', 'integer'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'judul' => ['required', 'string', 'max:160'],
            'jenis' => ['required', 'in:'.implode(',', array_keys(CatatanHarian::JENIS))],
            'isi' => ['nullable', 'string', 'max:8000'],
            'kendala' => ['nullable', 'string', 'max:4000'],
        ], [], ['judul' => 'judul', 'jenis' => 'jenis', 'isi' => 'isi catatan']);

        $ada = ['judul' => $d['judul'], 'jenis' => $d['jenis'],
                'isi' => (string) ($d['isi'] ?? ''), 'kendala' => (string) ($d['kendala'] ?? ''),
                'tanggal' => $d['tanggal'], 'diperbarui' => kini()];

        if (! empty($d['id'])) {
            // `where('user_id', $u->id)` WAJIB: tanpa itu siapa pun yang menebak
            // id bisa mengubah catatan orang lain. Kepemilikan diperiksa di query,
            // bukan di pemeriksaan terpisah yang bisa terlupa.
            $baris = CatatanHarian::where('user_id', $u->id)->whereKey((int) $d['id'])->firstOrFail();
            $baris->forceFill($ada)->save();
            $pesan = 'Catatan diperbarui.';
        } else {
            // Koperasi hanya diisi bila akun memang punya koperasi; tidak dipakai
            // untuk lingkup lagi (catatan sudah pribadi), tetapi tetap dicatat
            // sebagai keterangan asal.
            $kop = $u->manajerKoperasi() ? Koperasi::where('manajer_id', $u->id)->value('id') : null;
            CatatanHarian::create($ada + ['user_id' => $u->id, 'koperasi_id' => $kop, 'dibuat' => kini()]);
            $pesan = 'Catatan ditambahkan.';
        }

        return redirect()->route('workspace.catatan-harian', ['tanggal' => $d['tanggal']])
            ->with('pesan', $pesan);
    }

    /** Hapus catatan milik sendiri. */
    public function catatan_harian_hapus(Request $request, int $id)
    {
        $u = $request->user();
        $baris = CatatanHarian::where('user_id', $u->id)->whereKey($id)->firstOrFail();
        $tanggal = $baris->tanggal;
        $baris->delete();

        return redirect()->route('workspace.catatan-harian', ['tanggal' => $tanggal])
            ->with('pesan', 'Catatan dihapus.');
    }

    // ------------------------------------------------------------- Kalender

    /**
     * Kalender bulanan: rapat (tabel `konferensi`) + tenggat tugas (`tugas`).
     * Ketersediaan anggota tim terlihat dari rapat yang sudah terjadwal di jam itu.
     */
    public function kalender(Request $request)
    {
        $u = $request->user();
        $bulan = (string) $request->query('bulan', now()->format('Y-m'));
        // Query aneh (mis. `?bulan=abc`) membuat Carbon melempar exception → 500.
        // Jatuh ke bulan berjalan, bukan menolak halaman.
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $bulan = now()->format('Y-m');
        }
        $awal = \Carbon\Carbon::parse($bulan.'-01')->startOfMonth();
        $akhir = (clone $awal)->endOfMonth();
        $lingkup = $u->koperasiQuery()->select('koperasi.id');

        $rapat = KonferensiRapat::query()
            ->whereBetween('mulai', [$awal->toDateTimeString(), $akhir->endOfDay()->toDateTimeString()])
            ->orderBy('mulai')->get();

        $tenggat = Tugas::query()
            ->join('koperasi as k', 'k.id', '=', 'tugas.koperasi_id')
            ->select('tugas.*', 'k.nama as koperasi')
            ->whereBetween('tugas.deadline', [$awal->toDateString(), $akhir->toDateString()])
            ->when(! $u->lingkupPenuh(), fn ($q) => $q->whereIn('tugas.koperasi_id', $lingkup))
            ->when($u->manajerKoperasi(), fn ($q) => $q->where('k.manajer_id', $u->id))
            ->orderBy('tugas.deadline')->get();

        // Peta hari -> acara, siap dipakai Blade tanpa logika tanggal di view.
        $peta = [];
        foreach ($rapat as $r) {
            $peta[substr((string) $r->mulai, 0, 10)][] = ['jenis' => 'rapat', 'data' => $r];
        }
        foreach ($tenggat as $t) {
            $peta[substr((string) $t->deadline, 0, 10)][] = ['jenis' => 'tenggat', 'data' => $t];
        }

        // Agenda SATU daftar (rapat + tenggat) yang DIURUTKAN KRONOLOGIS.
        // Sebelumnya view menumpuk dua tabel berurutan — semua rapat dulu, baru
        // semua tenggat — jadi urutannya bukan urutan waktu, dan tidak ada
        // tempat menempelkan saring/paging pada satu daftar.
        $agenda = collect();
        foreach ($rapat as $r) {
            $agenda->push(['jenis' => 'rapat', 'waktu' => (string) $r->mulai, 'judul' => (string) $r->judul,
                'keterangan' => $r->label_jenis(), 'data' => $r]);
        }
        foreach ($tenggat as $t) {
            $agenda->push(['jenis' => 'tenggat', 'waktu' => (string) $t->deadline, 'judul' => (string) $t->judul,
                'keterangan' => (string) ($t->koperasi ?? ''), 'data' => $t]);
        }
        $agenda = $agenda->sortBy('waktu')->values();

        $cari = trim((string) $request->query('q', ''));
        $jenisSaring = in_array((string) $request->query('jenis'), ['rapat', 'tenggat'], true)
            ? (string) $request->query('jenis') : '';

        // ponytail: saring di memori. Satu bulan acara itu puluhan baris, bukan
        // puluhan ribu — dan sumbernya DUA tabel berbeda yang tak bisa disaring
        // dengan satu klausa SQL. Kalau rentangnya kelak jadi setahun, ganti ke
        // satu query UNION agar penyaringan terjadi di DB.
        $lihat = $agenda;
        if ($cari !== '') {
            $jarum = mb_strtolower($cari);
            $lihat = $lihat->filter(fn ($a) => str_contains(
                mb_strtolower($a['judul'].' '.$a['keterangan']), $jarum));
        }
        if ($jenisSaring !== '') {
            $lihat = $lihat->filter(fn ($a) => $a['jenis'] === $jenisSaring);
        }

        $halaman = Halaman::buat($lihat->count(), $request->query('halaman', 1),
            DasborController::PER_HALAMAN);
        $barisAgenda = $lihat->slice($halaman->offset(), $halaman->perHalaman)->values();

        return view('workspace.kalender', [
            'aktif' => 'workspace', 'awal' => $awal, 'akhir' => $akhir, 'bulan' => $awal->format('Y-m'),
            'peta' => $peta, 'rapat' => $rapat, 'tenggat' => $tenggat,
            'agenda' => $barisAgenda, 'halaman' => $halaman, 'cari' => $cari, 'jenisSaring' => $jenisSaring,
            'ringkas' => ['semua' => $agenda->count(), 'rapat' => $rapat->count(), 'tenggat' => $tenggat->count()],
            'boleh_jadwal' => ! $u->manajerKoperasi(),
            'hari_ini' => now()->toDateString(),
        ]);
    }

    /** Jadwalkan rapat dari kalender (pengelola wilayah & pusat). */
    public function kalender_rapat(Request $request)
    {
        $u = $request->user();
        if ($u->manajerKoperasi()) {
            abort(403, 'Penjadwalan rapat untuk pengelola wilayah dan admin Agrinas.');
        }

        $d = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'mulai' => ['required', 'date'],
            'jenis' => ['required', 'in:meeting,webinar'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [], ['judul' => 'judul rapat', 'mulai' => 'waktu mulai']);

        $mulai = \Carbon\Carbon::parse($d['mulai']);
        $r = KonferensiRapat::create([
            'judul' => $d['judul'], 'ruang' => KonferensiRapat::ruang_baru($d['judul'], $mulai->toDateTimeString()),
            'mulai' => $mulai->toDateTimeString(), 'selesai' => (clone $mulai)->addHour()->toDateTimeString(),
            'catatan' => (string) ($d['catatan'] ?? ''), 'oleh' => (string) $u->nama,
            'jenis' => $d['jenis'], 'dibuat' => kini(),
        ]);

        return redirect()->route('workspace.kalender', ['bulan' => $mulai->format('Y-m')])
            ->with('pesan', 'Rapat "'.$r->judul.'" dijadwalkan.');
    }

    // ------------------------------------------------------- Tasks (langkah)

    /** Papan tugas: tugas besar + langkah kecilnya, terhubung ke obrolan. */
    public function tugas(Request $request)
    {
        $u = $request->user();
        $lingkup = $u->koperasiQuery()->select('koperasi.id');

        // 🔴 SELURUH lingkup dibungkus SATU where(). Dulu `->orWhere('petugas_id')`
        // berdiri sendiri, sehingga SQL-nya `lingkup OR petugas_id = saya` — dan
        // karena AND mengikat lebih kuat daripada OR, setiap saringan yang
        // ditambahkan setelahnya hanya menempel ke cabang KEDUA:
        //   `lingkup OR (petugas_id = saya AND status = 'SELESAI')`
        // Akibatnya semua tugas dalam lingkup tetap lolos saringan. Halaman lama
        // tidak pernah menyaring setelah query ini, jadi bug-nya tidak terlihat
        // sampai pencarian/saringan ditambahkan.
        $semua = Tugas::query()
            ->leftJoin('koperasi as k', 'k.id', '=', 'tugas.koperasi_id')
            ->leftJoin('users as us', 'us.id', '=', 'tugas.petugas_id')
            ->select('tugas.*', 'k.nama as koperasi', 'k.kode as kode_koperasi',
                'us.nama as petugas_nama', 'us.peran as petugas_peran_akun')
            ->where(fn ($w) => $w
                ->when($u->manajerKoperasi(), fn ($q) => $q->where('k.manajer_id', $u->id))
                ->when(! $u->manajerKoperasi(), fn ($q) => $q->whereIn('tugas.koperasi_id', $lingkup))
                // Tugas yang ditugaskan langsung ke pengguna ini ikut tampil walau
                // koperasinya di luar lingkupnya.
                ->orWhere('tugas.petugas_id', $u->id))
            ->orderByRaw('CASE WHEN tugas.induk_id IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('tugas.diperbarui');

        // Saringan & pencarian di DB (bukan setelah ditarik) — pola yang sama
        // dengan dasbor manajer, supaya halaman tidak melambat saat tugas menumpuk.
        $cari = trim((string) $request->query('q', ''));
        $status = mb_strtoupper((string) $request->query('status', ''));
        $prioritas = mb_strtoupper((string) $request->query('prioritas', ''));

        $ringkas = [
            'semua' => (clone $semua)->count(),
            'TERLAMBAT' => (clone $semua)->where('tugas.status', '<>', 'SELESAI')
                ->whereNotNull('tugas.deadline')->where('tugas.deadline', '<', now()->toDateString())->count(),
        ];
        foreach (\App\Models\Domain::STATUS as $st) {
            $ringkas[$st] = (clone $semua)->where('tugas.status', $st)->count();
        }

        $q = clone $semua;
        if ($status === 'TERLAMBAT') {
            $q->where('tugas.status', '<>', 'SELESAI')->whereNotNull('tugas.deadline')
                ->where('tugas.deadline', '<', now()->toDateString());
        } elseif (in_array($status, \App\Models\Domain::STATUS, true)) {
            $q->where('tugas.status', $status);
        }
        if (in_array($prioritas, \App\Models\Domain::PRIORITAS, true)) {
            $q->where('tugas.prioritas', $prioritas);
        }
        if ($cari !== '') {
            $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cari).'%';
            $q->where(fn ($w) => $w->where('tugas.judul', 'like', $pola)
                ->orWhere('tugas.deskripsi', 'like', $pola)->orWhere('k.nama', 'like', $pola));
        }

        $halaman = Halaman::buat((clone $q)->count(), $request->query('halaman', 1), DasborController::PER_HALAMAN);
        $baris = $halaman->batasi((clone $q)->orderByRaw('CASE WHEN tugas.induk_id IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('tugas.diperbarui'))->get();

        $langkah = Tugas::whereIn('induk_id', $baris->pluck('id'))->orderBy('id')->get()->groupBy('induk_id');
        $progres = Kiriman::whereIn('tugas_id', $baris->pluck('id'))->orderBy('id')->get()->groupBy('tugas_id');

        // Penerima tiap baris dihitung sekali di sini (butuh query koperasi) —
        // view tidak boleh menyentuh DB per baris.
        $penerima = [];
        foreach ($baris as $t) {
            $penerima[$t->id] = $this->penerimaTugas($t);
        }

        return view('workspace.tugas', [
            'aktif' => 'workspace', 'tugas' => $baris, 'langkah' => $langkah, 'halaman' => $halaman,
            'ringkas' => $ringkas, 'cari' => $cari, 'statusSaring' => $status, 'prioritasSaring' => $prioritas,
            'penerima' => $penerima, 'progres' => $progres,
            'koperasi' => $this->koperasiPilihan($u),
            'ruang' => RuangObrolan::where('jenis', 'tugas')->get()->keyBy('acuan_id'),
            'dokumen' => $this->dokumenQuery($u)->orderByDesc('id')->limit(30)->get(),
            'label' => \App\Models\Domain::LABEL,
            // Hanya pengelola (pusat/PIC) yang menugaskan orang; manajer tidak.
            'petugas' => $this->petugasPilihan($u),
            'boleh_menugaskan' => $this->bolehMenugaskan($u),
            'jobdesc_wajib' => JobdescManajer::wajib(),
            'jobdesc_menit' => JobdescManajer::totalMenit(JobdescManajer::wajib()),
            'wilayah' => \App\Support\Wilayah::PILIHAN,
        ]);
    }

    /**
     * Boleh menugaskan orang lain? Hierarki penugasan:
     *
     *   admin pusat (AGRINAS) -> boleh menugaskan PIC DAN manajer
     *   PIC wilayah           -> boleh menugaskan manajer DI WILAYAHNYA saja
     *   manajer koperasi      -> TIDAK menugaskan; ia PELAKSANA (mengisi progres)
     *
     * Manajer tetap bisa membuat pekerjaan untuk koperasinya sendiri, hanya saja
     * tanpa memilih pelaksana — itu sudah perilaku lama dan tetap dipertahankan.
     */
    private function bolehMenugaskan(User $u): bool
    {
        return $u->agrinas() || $u->pic();
    }

    /**
     * Calon pelaksana yang SAH bagi pembuat ini — SATU-satunya penentu, dipakai
     * untuk mengisi daftar pilihan DAN untuk memvalidasi id yang dikirim, jadi id
     * di luar hierarki mustahil lolos.
     */
    private function petugasPilihan(User $u)
    {
        if (! $this->bolehMenugaskan($u)) {
            return collect();
        }

        $manajer = Koperasi::query()
            ->when(! $u->agrinas(), fn ($q) => $q->whereIn('id', $u->koperasiQuery()->select('koperasi.id')))
            ->pluck('manajer_id')->filter()->values();

        $q = User::query()->where('aktif', 1)->whereKeyNot($u->id);

        if ($u->agrinas()) {
            // Pusat: seluruh PIC + seluruh manajer koperasi.
            $q->where(fn ($w) => $w->where('peran', 'PIC')->orWhereIn('id', $manajer));
        } else {
            // PIC: hanya manajer di wilayahnya. PIC lain tidak muncul — penugasan
            // lintas-PIC bukan bagian hierarki ini (pusat yang menjembatani).
            $q->whereIn('id', $manajer);
        }

        return $q->orderBy('peran')->orderBy('nama')->get(['id', 'nama', 'peran', 'wilayah']);
    }

    /** Satu tempat untuk aturan "siapa penerima tugas ini" (dipakai daftar). */
    private function penerimaTugas(Tugas $t): array
    {
        if ($t->petugas_id) {
            $p = User::find($t->petugas_id);

            return ['label' => $p?->nama ?? ('#'.$t->petugas_id),
                    'catatan' => $p ? (\App\Models\Domain::LABEL_PERAN[$p->peran] ?? $p->peran) : 'Pengguna'];
        }

        if ($t->sasaran === Tugas::SASARAN_SATU) {
            $m = Koperasi::whereKey($t->koperasi_id)->first();
            $n = $m ? User::find($m->manajer_id) : null;

            return ['label' => $n?->nama ?? 'Manajer koperasi', 'catatan' => 'Manajer koperasi'];
        }

        $n = Koperasi::query()->where($t->saringPenerima())->count();

        return ['label' => $n.' koperasi', 'catatan' => Tugas::SASARAN[$t->sasaran] ?? $t->sasaran];
    }

    /** Pelaksana sah bagi pembuat ini? (id harus ada di daftar pilihannya). */
    private function petugasSah(User $u, int $id): bool
    {
        return $id > 0 && $this->petugasPilihan($u)->contains('id', $id);
    }

    public function tugas_baru(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'koperasi_id' => ['required', 'integer'],
            'judul' => ['required', 'string', 'max:160'],
            'deskripsi' => ['nullable', 'string', 'max:4000'],
            'deadline' => ['nullable', 'date'],
            'prioritas' => ['nullable', 'in:RENDAH,NORMAL,TINGGI'],
            'ruang' => ['nullable', 'boolean'],
            'petugas_id' => ['nullable', 'integer'],
            'petugas_wilayah' => ['nullable', 'string', 'max:60'],
        ], [], ['koperasi_id' => 'koperasi', 'judul' => 'judul', 'deadline' => 'tenggat',
                'petugas_id' => 'pelaksana', 'petugas_wilayah' => 'wilayah pelaksana']);

        // Koperasi acuan: tugas selalu menempel pada satu koperasi (dipakai untuk
        // lingkup, ruang obrolan, dan jejak). Bila pelaksananya PIC, koperasinya
        // boleh di luar lingkup pembuat — tapi harus ada dan sah.
        if (! Koperasi::whereKey($d['koperasi_id'])->exists()) {
            return back()->with('galat', 'Koperasi tidak ditemukan.');
        }
        if (! $u->agrinas() && ! $u->koperasiQuery()->whereKey($d['koperasi_id'])->exists()
                && ! $this->petugasSah($u, (int) ($d['petugas_id'] ?? 0))) {
            return back()->with('galat', 'Koperasi di luar lingkup Anda.');
        }

        // Pelaksana: pengguna tertentu (divalidasi terhadap daftar pilihan, jadi
        // tak ada id dari luar hierarki), atau default manajer koperasinya.
        $petugas = null;
        if (! empty($d['petugas_id']) && ! $this->bolehMenugaskan($u)) {
            return back()->with('galat', 'Menugaskan pengguna lain adalah wewenang admin pusat dan PIC wilayah.');
        }
        if (! empty($d['petugas_id'])) {
            $petugas = $this->petugasPilihan($u)->firstWhere('id', (int) $d['petugas_id']);
            if (! $petugas) {
                return back()->with('galat', 'Pelaksana di luar lingkup Anda.');
            }
        }

        $t = Tugas::create([
            'koperasi_id' => (int) $d['koperasi_id'], 'judul' => $d['judul'],
            'deskripsi' => (string) ($d['deskripsi'] ?? ''), 'jenis' => 'DOKUMEN',
            'prioritas' => ($d['prioritas'] ?? '') ?: 'NORMAL', 'deadline' => ($d['deadline'] ?? null) ?: null,
            'status' => 'BARU', 'dibuat_oleh' => $u->id, 'dibuat' => kini(), 'diperbarui' => kini(),
            'petugas_id' => $petugas?->id,
            'petugas_peran' => $petugas?->peran ?? 'MANAJER',
            'petugas_wilayah' => $petugas?->peran === 'PIC'
                ? (string) (($d['petugas_wilayah'] ?? '') ?: ($petugas->wilayah ?? '')) : '',
        ]);

        jejak($t->id, (string) $u->nama, 'DIBUAT', '', 'BARU',
            $petugas ? 'Ditugaskan ke '.$petugas->nama : 'Task Ruang Kerja');
        jurnal($u->id, 'TUGAS_BARU', (string) $t->id,
            $t->judul.($petugas ? ' -> '.$petugas->nama : ''), $request->ip() ?? '');

        // "Terhubung langsung dengan alur obrolan": setiap tugas besar dapat ruang
        // obrolannya sendiri supaya pembahasan menempel pada pekerjaannya.
        if (! empty($d['ruang'])) {
            RuangObrolan::firstOrCreate(
                ['acuan' => 'tugas', 'acuan_id' => $t->id],
                ['nama' => 'Tugas · '.$t->judul, 'jenis' => 'tugas',
                    'koperasi_id' => $t->koperasi_id, 'dibuat_oleh' => $u->id, 'dibuat' => kini()],
            );
        }

        return redirect()->route('workspace.tugas')->with('pesan', 'Tugas "'.$t->judul.'" dibuat.');
    }

    /**
     * PROGRES dari pelaksana: catatan perkembangan + lampiran bukti.
     *
     * Menempel pada tabel `kiriman` yang sudah ada sejak awal (kolomnya lengkap:
     * nama_asli, path, sha256, ukuran, catatan, oleh) tetapi selama ini tidak
     * pernah dipakai — jadi tidak perlu tabel baru. `sha256` disimpan supaya
     * berkas yang sama bisa dikenali (bukan untuk verifikasi kriptografis).
     */
    public function tugas_progres(Request $request, int $id)
    {
        $u = $request->user();
        $t = $this->tugasQuery($u)->whereKey($id)->firstOrFail();

        $d = $request->validate([
            'catatan' => ['required', 'string', 'max:2000'],
            'berkas' => ['nullable', 'file', 'max:20480',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp,'
                .'application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                .'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            // Pelaksana boleh sekalian memindahkan statusnya (mis. jadi Diajukan).
            'status' => ['nullable', 'in:'.implode(',', \App\Models\Domain::STATUS)],
        ], [], ['catatan' => 'catatan progres', 'berkas' => 'lampiran', 'status' => 'status']);

        $path = ''; $namaAsli = ''; $ukuran = 0; $sha = '';
        if ($request->hasFile('berkas')) {
            $f = $request->file('berkas');
            $namaAsli = (string) $f->getClientOriginalName();
            $isi = (string) file_get_contents($f->getRealPath());
            $ukuran = strlen($isi);
            $sha = hash('sha256', $isi);
            // Nama berkas disanitasi: nama dari pengguna bisa memuat '/' dan
            // menulis ke luar folder tugas.
            $path = 'progres/'.$t->id.'/'.uniqid().'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $namaAsli);
            if (! Storage::disk(self::DISK)->put($path, $isi)) {
                return back()->with('galat', 'Lampiran gagal disimpan.');
            }
        }

        Kiriman::create(['tugas_id' => $t->id, 'nama_asli' => $namaAsli, 'path' => $path,
            'sha256' => $sha, 'ukuran' => $ukuran, 'catatan' => $d['catatan'], 'oleh' => $u->id,
            'dibuat' => kini()]);

        if (! empty($d['status']) && $d['status'] !== $t->status) {
            $lama = (string) $t->status;
            $t->forceFill(['status' => $d['status'], 'diperbarui' => kini()])->save();
            jejak($t->id, (string) $u->nama, 'STATUS', $lama, $d['status'], 'Progres pelaksana');
        } else {
            $t->forceFill(['diperbarui' => kini()])->save();
        }
        jejak($t->id, (string) $u->nama, 'PROGRES', '', '', \Illuminate\Support\Str::limit($d['catatan'], 120));
        jurnal($u->id, 'TUGAS_PROGRES', (string) $t->id, $t->judul, $request->ip() ?? '');

        return back()->with('pesan', 'Progres tugas tersimpan.');
    }

    /** Unduh lampiran progres. Hanya yang boleh melihat tugasnya. */
    public function tugas_progres_berkas(Request $request, int $id, int $kiriman)
    {
        $u = $request->user();
        $t = $this->tugasQuery($u)->whereKey($id)->firstOrFail();
        $k = Kiriman::where('tugas_id', $t->id)->whereKey($kiriman)->firstOrFail();
        abort_unless($k->path !== '' && Storage::disk(self::DISK)->exists($k->path), 404);

        return Storage::disk(self::DISK)->download($k->path, $k->nama_asli ?: basename($k->path));
    }

    /**
     * JOB DESCRIPTION Manajer KDKMP (Lampiran 1 PKWT). Halaman rujukan — tugas
     * yang "sudah ditentukan": 17 tugas harian wajib + 22 tugas pilihan BMC.
     */
    public function jobdesc(Request $request)
    {
        $wajib = JobdescManajer::wajib();
        $pilihan = JobdescManajer::pilihan();

        return view('workspace.jobdesc', [
            'aktif' => 'workspace',
            'wajib' => $wajib, 'pilihan' => JobdescManajer::perPoinBmc(),
            'menitWajib' => JobdescManajer::totalMenit($wajib),
            'menitPilihan' => JobdescManajer::totalMenit($pilihan),
            'jumlah' => count(JobdescManajer::TASKS),
        ]);
    }

    /** Tambah langkah kecil pada tugas besar. */
    public function tugas_langkah(Request $request, int $id)
    {
        $u = $request->user();
        $induk = $this->tugasQuery($u)->whereKey($id)->firstOrFail();
        $d = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'deadline' => ['nullable', 'date'],
        ], [], ['judul' => 'judul langkah']);

        $t = Tugas::create([
            'koperasi_id' => $induk->koperasi_id, 'induk_id' => $induk->id, 'judul' => $d['judul'],
            'deskripsi' => '', 'jenis' => $induk->jenis, 'prioritas' => $induk->prioritas ?: 'NORMAL',
            'deadline' => ($d['deadline'] ?? null) ?: null, 'status' => 'BARU',
            'dibuat_oleh' => $u->id, 'dibuat' => kini(), 'diperbarui' => kini(),
        ]);
        jejak($t->id, (string) $u->nama, 'DIBUAT', '', 'BARU', 'Langkah dari #'.$induk->id);

        return redirect()->route('workspace.tugas')->with('pesan', 'Langkah ditambahkan.');
    }

    /** Ubah status satu tugas/langkah. Manajer tetap terbatas pada koperasinya. */
    public function tugas_status(Request $request, int $id)
    {
        $u = $request->user();
        $t = $this->tugasQuery($u)->whereKey($id)->firstOrFail();
        // Daftar diambil dari Domain::STATUS supaya status baru (DITOLAK) tidak
        // pernah bisa ditolak validasi karena lupa didaftarkan ulang di sini.
        $d = $request->validate(['status' => ['required', 'in:'.implode(',', \App\Models\Domain::STATUS)]]);

        $lama = (string) $t->status;
        $t->forceFill(['status' => $d['status'], 'diperbarui' => kini()])->save();
        jejak($t->id, (string) $u->nama, 'STATUS', $lama, $d['status'], 'Task Ruang Kerja');
        jurnal($u->id, 'TUGAS_STATUS', (string) $t->id, $t->judul, $request->ip() ?? '');

        return back()->with('pesan', 'Status diperbarui.');
    }

    /**
     * Keterangan dari pengelola pada satu tugas — alasan revisi/penolakan atau
     * instruksi lanjutan. Menempel di tugas (`catatan_agrinas`, kolom yang sudah
     * ada), bukan tabel komentar baru: yang dibutuhkan satu keterangan terkini,
     * bukan utas diskusi. Jejak perubahannya tetap tercatat di riwayat.
     */
    public function tugas_keterangan(Request $request, int $id)
    {
        $u = $request->user();
        $t = $this->tugasQuery($u)->whereKey($id)->firstOrFail();
        $d = $request->validate(['catatan' => ['nullable', 'string', 'max:2000']],
            [], ['catatan' => 'keterangan']);

        $catatan = trim((string) ($d['catatan'] ?? ''));
        $t->forceFill(['catatan_agrinas' => $catatan, 'diperbarui' => kini()])->save();

        jejak($t->id, (string) $u->nama, 'KETERANGAN', '', $catatan === '' ? '(dikosongkan)' : 'diisi', $catatan);
        jurnal($u->id, 'TUGAS_KETERANGAN', (string) $t->id, $t->judul, $request->ip() ?? '');

        return back()->with('pesan', 'Keterangan tugas disimpan.');
    }

    // ------------------------------------------------------------------ Wiki

    /**
     * Wiki = pengetahuan & tutorial pengoperasian Hub (SOP, panduan fitur).
     *
     * Approval dipisah ke halamannya sendiri (`workspace.approval`) karena
     * urusannya lain sama sekali: wiki dibaca, approval diputuskan. Dulu satu
     * halaman memuat dua daftar berhalaman dengan dua kunci pager — itu sumber
     * kebingungan yang tidak perlu begitu isinya jadi dua pekerjaan berbeda.
     *
     * DUA bentuk tampilan di sini, dipilih oleh saringan — bukan preferensi:
     *   tanpa saringan  → POHON penuh (hierarki = inti wiki; 10 baris/halaman
     *                     membuat cabang terpotong, jadi pohon memuat semuanya);
     *   ada q/koperasi  → DAFTAR DATAR berhalaman. Saat menyaring, 10 baris
     *                     pertama bisa jadi anak tanpa induknya — pohon yang
     *                     dipotong begitu menyesatkan, jadi hierarkinya
     *                     dimatikan dan hasilnya ditampilkan apa adanya.
     */
    public function wiki(Request $request)
    {
        $u = $request->user();
        $q = trim((string) $request->query('q', ''));
        $kopId = max(0, (int) $request->query('koperasi', 0));
        $cari = $q !== '' || $kopId > 0;

        $dasar = $this->wikiQuery($u)
            ->when($q !== '', fn ($w) => $this->cariTeks($w, ['judul', 'isi'], $q))
            ->when($kopId > 0, fn ($w) => $w->where('koperasi_id', $kopId));

        $semua = (clone $dasar)->orderBy('judul')->get();
        $halaman = Halaman::buat($semua->count(), $request->query('halaman', 1),
            DasborController::PER_HALAMAN);
        // `induk` & `koperasi` dimuat sekaligus — tanpa ini daftar 10 baris
        // memicu 20 kueri tambahan hanya untuk menulis nama induk/koperasi.
        $baris = $cari
            ? $halaman->batasi((clone $dasar)->orderBy('judul'))->get()->load(['induk', 'koperasi'])
            : collect();

        return view('workspace.wiki', [
            'aktif' => 'workspace',
            'q' => $q, 'kopId' => $kopId, 'cari' => $cari,
            'wiki' => $semua,
            'pohon' => $cari ? [] : $this->pohon($semua),
            'baris' => $baris,
            'halaman' => $halaman,
            'koperasi' => $this->koperasiPilihan($u),
            'boleh_ubah' => $u->agrinas(),
        ]);
    }

    /**
     * Approval = pengajuan BIAYA OPERASIONAL gerai koperasi (listrik, air,
     * service AC/kendaraan, perjalanan dinas, rembes pembelian, …) ke admin
     * pusat / petugas keuangan.
     *
     * Siapa memutuskan: semua yang bukan manajer (pusat, PIC wilayahnya,
     * keuangan). Manajer hanya mengajukan & memantau miliknya sendiri —
     * lingkup itu sudah dipagari `persetujuanQuery()`.
     */
    public function approval(Request $request)
    {
        $u = $request->user();
        $status = mb_strtoupper(trim((string) $request->query('status', '')));
        $status = in_array($status, Persetujuan::STATUS, true) ? $status : '';
        $kopId = max(0, (int) $request->query('koperasi', 0));
        $q = trim((string) $request->query('q', ''));

        $dasar = $this->persetujuanQuery($u)
            ->when($status !== '', fn ($w) => $w->where('status', $status))
            ->when($kopId > 0, fn ($w) => $w->where('koperasi_id', $kopId))
            ->when($q !== '', fn ($w) => $this->cariTeks($w, ['judul', 'isi'], $q));

        return view('workspace.approval', [
            'aktif' => 'workspace',
            'status' => $status, 'kopId' => $kopId, 'q' => $q,
            'persetujuan' => $this->berhalaman($dasar, $request, 'halaman',
                fn ($w) => $w->with(['pemohon', 'koperasi', 'pemutus'])->orderByRaw(
                    "CASE status WHEN 'DIAJUKAN' THEN 0 WHEN 'SETUJU' THEN 1 ELSE 2 END")
                    ->orderByDesc('id')),
            // Angka ringkasan dari SELURUH baris (bukan 10 baris halaman ini):
            // angka yang cuma menghitung satu halaman berbohong begitu pengajuan
            // lebih dari sepuluh.
            'ringkasSetuju' => $this->persetujuanQuery($u)->selectRaw('status, COUNT(*) n')
                ->groupBy('status')->pluck('n', 'status')->all(),
            'totalBiaya' => (int) $this->persetujuanQuery($u)->where('status', 'SETUJU')->sum('nominal'),
            'koperasi' => $this->koperasiPilihan($u),
            'boleh_putus' => ! $u->manajerKoperasi(),
        ]);
    }

    public function wiki_baru(Request $request)
    {
        $u = $request->user();
        // Wiki = pengetahuan resmi: hanya admin pusat yang menulis. Peran lain
        // membaca saja (ditegaskan juga di view, tapi pagar sebenarnya di sini).
        abort_unless($u->agrinas(), 403, 'Wiki hanya dapat disunting admin Agrinas.');
        $d = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'induk_id' => ['nullable', 'integer'],
            'koperasi_id' => ['nullable', 'integer'],
            'isi' => ['nullable', 'string', 'max:40000'],
        ], [], ['judul' => 'judul halaman', 'induk_id' => 'halaman induk']);

        $induk = null;
        if (! empty($d['induk_id'])) {
            $induk = $this->wikiQuery($u)->whereKey($d['induk_id'])->value('id');
        }
        $kop = null;
        if (! empty($d['koperasi_id']) && ($k = $this->koperasiPilihan($u)->firstWhere('id', (int) $d['koperasi_id']))) {
            $kop = $k->id;
        }

        $w = Wiki::create([
            'induk_id' => $induk, 'koperasi_id' => $kop, 'judul' => $d['judul'],
            'isi' => (string) ($d['isi'] ?? ''), 'dibuat_oleh' => $u->id,
            'dibuat' => kini(), 'diperbarui' => kini(),
        ]);

        jurnal($u->id, 'WIKI_BUAT', (string) $w->id, $w->judul, $request->ip() ?? '');

        return redirect()->route('workspace.wiki.lihat', $w->id)->with('pesan', 'Halaman wiki dibuat.');
    }

    public function wiki_lihat(Request $request, int $id)
    {
        $u = $request->user();

        return view('workspace.wiki-halaman', [
            'aktif' => 'workspace', 'hal' => $this->wikiQuery($u)->whereKey($id)->firstOrFail(),
            'anak' => $this->wikiQuery($u)->where('induk_id', $id)->orderBy('judul')->get(),
            'boleh_ubah' => $u->agrinas(),
            // Judul lengkap lingkup pengguna, untuk pilihan "Halaman Induk".
            // Sengaja di sini, bukan di view: view tidak boleh menyentuh DB.
            'semua' => $this->wikiQuery($u)->orderBy('judul')->get(['id', 'induk_id', 'judul']),
        ]);
    }

    /**
     * Simpan judul/isi halaman.
     *
     * 🔴 `induk_id` DIPERTAHANKAN bila tidak dikirim. Sebelum ini form sunting
     * tidak mengirim `induk_id`, sementara action ini selalu menulis
     * `'induk_id' => null` — jadi setiap kali pengguna menyimpan perbaikan
     * kalimat, halamannya DIAM-DIAM terlepas dari induknya dan hierarki wiki
     * rusak sedikit demi sedikit. Sekarang tidak mengirim = tidak diubah.
     */
    public function wiki_simpan(Request $request, int $id)
    {
        $u = $request->user();
        abort_unless($u->agrinas(), 403, 'Wiki hanya dapat disunting admin Agrinas.');
        $hal = $this->wikiQuery($u)->whereKey($id)->firstOrFail();
        $d = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'isi' => ['nullable', 'string', 'max:40000'],
            'induk_id' => ['nullable', 'integer'],
        ], [], ['judul' => 'judul halaman', 'induk_id' => 'halaman induk']);

        $induk = $hal->induk_id;                       // bawaan: JANGAN diubah
        if ($request->has('induk_id')) {
            [$ok, $nilai, $galat] = $this->indukSah($u, $hal, $request->input('induk_id'));
            if (! $ok) {
                return back()->with('galat', $galat);
            }
            $induk = $nilai;
        }

        $hal->forceFill([
            'judul' => $d['judul'], 'isi' => (string) ($d['isi'] ?? ''),
            'induk_id' => $induk, 'diperbarui' => kini(),
        ])->save();

        jurnal($u->id, 'WIKI_SIMPAN', (string) $hal->id, $hal->judul, $request->ip() ?? '');

        return redirect()->route('workspace.wiki.lihat', $hal->id)->with('pesan', 'Halaman disimpan.');
    }

    /**
     * Unggah gambar halaman wiki.
     *
     * Diperkecil ke maks 1280 px, metadata (EXIF/GPS) hilang karena gambar
     * ditulis ulang, lalu disimpan sebagai WebP di `public/img/wiki` — BUKAN di
     * storage/app. Berkas di public disajikan langsung oleh server tanpa lewat
     * PHP, dan WebP 1280 px biasanya 100–200 KB; itu yang membuat halaman wiki
     * tetap ringan dibuka di ponsel.
     *
     * ponytail: satu ukuran (1280 px, q=80) dan berkas lama tidak dihapus saat
     * gambar diganti. Tambah pembersihan kalau folder mulai menumpuk.
     */
    public function wiki_gambar(Request $request, int $id)
    {
        $u = $request->user();
        abort_unless($u->agrinas(), 403, 'Wiki hanya dapat disunting admin Agrinas.');
        $hal = $this->wikiQuery($u)->whereKey($id)->firstOrFail();
        $request->validate([
            'gambar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ], [], ['gambar' => 'gambar']);

        $jalur = $request->file('gambar')->getRealPath();
        $jenis = @getimagesize($jalur)[2] ?? 0;
        $sumber = match ($jenis) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($jalur),
            IMAGETYPE_PNG => @imagecreatefrompng($jalur),
            IMAGETYPE_WEBP => @imagecreatefromwebp($jalur),
            IMAGETYPE_GIF => @imagecreatefromgif($jalur),
            default => null,
        };
        abort_if(! $sumber, 422, 'Berkas itu tidak bisa dibaca sebagai gambar.');

        $lw = imagesx($sumber);
        $lh = imagesy($sumber);
        $skala = min(1, 1280 / max($lw, $lh));
        $tw = max(1, (int) round($lw * $skala));
        $th = max(1, (int) round($lh * $skala));

        $kanvas = imagecreatetruecolor($tw, $th);
        imagealphablending($kanvas, false);              // PNG transparan tetap transparan
        imagesavealpha($kanvas, true);
        imagecopyresampled($kanvas, $sumber, 0, 0, 0, 0, $tw, $th, $lw, $lh);

        // Nama berkas = nama asli yang dislug + ukuran + akhiran acak: terbaca
        // manusia, unik, dan aman jadi bagian URL (tanpa spasi/karakter aneh).
        $dasar = \Illuminate\Support\Str::slug(
            pathinfo($request->file('gambar')->getClientOriginalName(), PATHINFO_FILENAME)
        ) ?: 'gambar';
        $nama = mb_substr($dasar, 0, 60).'-'.$tw.'x'.$th.'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)).'.webp';

        $folder = public_path('img/wiki');
        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
        imagewebp($kanvas, $folder.'/'.$nama, 80);

        $url = asset('img/wiki/'.$nama);
        jurnal($u->id, 'WIKI_GAMBAR', (string) $hal->id, $hal->judul.' <- '.$nama, $request->ip() ?? '');

        if ($request->expectsJson()) {
            return response()->json([
                'url' => $url, 'nama' => $nama,
                'markdown' => '!['.$hal->judul.']('.$url.')',
            ]);
        }

        // Tanpa JS: sisipkan di akhir isi supaya gambarnya tidak hilang percuma.
        $hal->forceFill([
            'isi' => rtrim((string) $hal->isi)."\n\n![](".$url.")\n", 'diperbarui' => kini(),
        ])->save();

        return redirect()->route('workspace.wiki.lihat', $hal->id)->with('pesan', 'Gambar diunggah dan disisipkan di akhir isi.');
    }

    /**
     * Terjemahkan kiriman `induk_id` jadi nilai yang boleh disimpan.
     * Sengaja dipisah supaya aturan (diri sendiri, lingkup, siklus) berada di
     * SATU tempat — bukan tersebar di tiap jalur yang menulis `induk_id`.
     *
     * @return array{0: bool, 1: ?int, 2: string} [boleh, induk, alasan galat]
     */
    private function indukSah(User $u, Wiki $hal, mixed $kirim): array
    {
        $kirim = (string) ($kirim ?? '');
        if ($kirim === '') {
            return [true, null, ''];                       // dipilih "tanpa induk"
        }
        $calon = (int) $kirim;
        if ($calon === (int) $hal->id) {
            return [true, $hal->induk_id, ''];             // diri sendiri: abaikan
        }
        if (! $this->wikiQuery($u)->whereKey($calon)->exists()) {
            return [false, null, 'Halaman induk itu tidak ada di lingkup Anda.'];
        }
        if (! Wiki::boleh_induk((int) $hal->id, $calon)) {
            return [false, null, 'Induk itu akan membuat lingkaran hierarki.'];
        }

        return [true, $calon, ''];
    }

    /** Ajukan biaya operasional gerai (Approval). */
    public function setuju_ajukan(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'jenis' => ['required', 'string', 'max:40', 'in:'.implode(',', Persetujuan::BIAYA)],
            'nominal' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'isi' => ['nullable', 'string', 'max:8000'],
            'koperasi_id' => ['nullable', 'integer'],
        ], [], [
            'judul' => 'keperluan biaya', 'jenis' => 'kategori biaya',
            'nominal' => 'nominal', 'tanggal' => 'tanggal dibutuhkan',
        ]);

        $kop = null;
        if (! empty($d['koperasi_id']) && ($k = $this->koperasiPilihan($u)->firstWhere('id', (int) $d['koperasi_id']))) {
            $kop = $k->id;
        } elseif ($u->manajerKoperasi()) {
            $kop = Koperasi::where('manajer_id', $u->id)->value('id');
        }

        Persetujuan::create([
            'koperasi_id' => $kop, 'judul' => $d['judul'], 'jenis' => $d['jenis'],
            'nominal' => (int) $d['nominal'], 'tanggal' => $d['tanggal'] ?? null,
            'isi' => (string) ($d['isi'] ?? ''), 'pemohon_id' => $u->id, 'status' => 'DIAJUKAN',
            'dibuat' => kini(),
        ]);

        jurnal($u->id, 'SETUJU_AJUKAN', (string) $kop, $d['judul'], $request->ip() ?? '');

        return redirect()->route('workspace.approval')->with('pesan', 'Pengajuan biaya dikirim ke admin/keuangan.');
    }

    /** Putuskan pengajuan biaya: admin pusat, PIC wilayahnya, dan petugas keuangan. */
    public function setuju_putus(Request $request, int $id)
    {
        $u = $request->user();
        if ($u->manajerKoperasi()) {
            abort(403, 'Persetujuan diputuskan pengelola wilayah atau admin Agrinas.');
        }
        $d = $request->validate([
            'status' => ['required', 'in:SETUJU,TOLAK'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $p = $this->persetujuanQuery($u)->whereKey($id)->firstOrFail();

        $p->forceFill([
            'status' => $d['status'], 'catatan' => (string) ($d['catatan'] ?? ''),
            'pemutus_id' => $u->id, 'diputus' => kini(),
        ])->save();

        jurnal($u->id, 'SETUJU_'.$d['status'], (string) $p->id, $p->judul, $request->ip() ?? '');

        return back()->with('pesan', 'Pengajuan '.mb_strtolower(Persetujuan::LABEL[$d['status']]).'.');
    }

    // --------------------------------------------------------------- bantuan

    /** Bahasa sasaran terjemahan Messenger. */
    private const BAHASA = ['Indonesia' => 'Indonesia', 'Inggris' => 'Inggris', 'Mandarin' => 'Mandarin',
        'Arab' => 'Arab', 'Jepang' => 'Jepang', 'Korea' => 'Korea', 'Spanyol' => 'Spanyol'];

    /**
     * Cari substring LITERAL pada satu atau beberapa kolom.
     *
     * 🔴 Dulu dipakai `like` dengan pola `%…%` dan `%`/`_` "dilolosi" memakai
     * backslash. Itu TIDAK bekerja: SQLite (dan MySQL) baru menganggap
     * backslash sebagai pelolos bila kueri menyebut `ESCAPE`, sedangkan
     * `where('x','like',$pola)` tidak pernah menambahkannya. Akibatnya mencari
     * "%" justru mencari baris yang memuat backslash — di Hub itu halaman wiki
     * berisi `App\Models\…`, sehingga "cari %" mengembalikan satu halaman yang
     * sama sekali tidak memuat tanda persen.
     *
     * `instr()` mencocokkan teks apa adanya: tidak ada pola, tidak ada
     * pelolosan, jadi "%", "_", dan "\" semuanya aman. `lower()` di kedua sisi
     * menyamakan perilaku dengan `like` yang tidak peka huruf besar-kecil.
     */
    private function cariTeks($w, array $kolom, string $q)
    {
        $jarum = mb_strtolower($q);

        return $w->where(function ($x) use ($kolom, $jarum) {
            foreach ($kolom as $k) {
                $x->orWhereRaw("instr(lower(coalesce({$k}, '')), ?) > 0", [$jarum]);
            }
        });
    }

    /** Koperasi yang boleh dipilih pengguna (manajer: miliknya; lain: lingkupnya). */
    private function koperasiPilihan(User $u)
    {
        return $u->koperasiQuery()->orderBy('nama')->get();
    }

    /**
     * Calon lawan bicara: seluruh pengguna Hub yang relevan bagi peran ini.
     *
     * Admin pusat melihat semua; PIC melihat sesama pengelola + manajer di
     * wilayahnya; manajer melihat pengelola pusat & PIC (yang memang mengawasi
     * koperasinya). Ambil HANYA id/nama/peran — cukup untuk memilih, tidak
     * membocorkan surel atau sandi ke halaman.
     */
    private function rekanPilihan(User $u)
    {
        $q = User::query()->whereKeyNot($u->id)->orderBy('nama')->limit(300);

        if ($u->agrinas()) {
            return $q->get(['id', 'nama', 'peran']);
        }

        if ($u->pic()) {
            $manajer = Koperasi::whereIn('id', $u->koperasiQuery()->select('koperasi.id'))
                ->pluck('manajer_id')->filter()->values();

            return $q->where(fn ($w) => $w->whereIn('peran', ['AGRINAS', 'PIC'])->orWhereIn('id', $manajer))
                ->get(['id', 'nama', 'peran']);
        }

        // Manajer: sesama MANAJER ikut (berbagi pengalaman lapangan antar
        // koperasi) + pengelola yang mengawasinya. Sebelumnya hanya AGRINAS &
        // PIC, sehingga sesama manajer mustahil saling mengobrol.
        return $q->whereIn('peran', ['AGRINAS', 'PIC', 'MANAJER'])->get(['id', 'nama', 'peran']);
    }

    /**
     * Pilihan lingkup peserta, DITURUNKAN dari data yang ada (provinsi &
     * kabupaten/kecamatan yang benar-benar dipakai koperasi) — bukan daftar 514
     * kabupaten yang harus dipelihara sendiri.
     *
     * Provinsi seluruhnya 37 entri, dan sebagian belum punya koperasi sama
     * sekali; menawarkan pilihan yang pasti kosong hanya membingungkan.
     */
    private function lingkupDaftar(User $u): array
    {
        $kop = Koperasi::query()->get(['id', 'nama', 'wilayah', 'provinsi', 'manajer_id']);

        // Satu koperasi = satu baris: kabupaten/kota & kecamatan DITURUNKAN dari
        // `wilayah` ("Desa X, Kec. Y, Kab. Z"). Dulu kab & kec dilebur jadi dua
        // kelompok datar, sehingga pilihan "Kec. Cibadak" tidak memberi tahu
        // provinsi mana — pengelola harus menebak. Sekarang seluruh pilihan
        // provinsi MEMUAT kab/kec-nya, plus koperasi & manajernya.
        $namaManajer = User::whereIn('id', $kop->pluck('manajer_id')->filter()->unique())
            ->pluck('nama', 'id');

        // kunci wilayah (Wilayah::lingkup) => ['label' => …, 'kab' => […], 'kec' => […], 'kop' => […] ]
        $per = [];

        foreach ($kop as $k) {
            $wilayah = Wilayah::lingkup((string) $k->provinsi);
            if ($wilayah === '') {
                continue; // provinsi tak dikenal: jangan mengarang kelompok
            }

            if (! isset($per[$wilayah])) {
                $per[$wilayah] = ['label' => Wilayah::pendek($wilayah), 'kab' => [], 'kec' => [], 'kop' => []];
            }

            // Kab/kec dibaca dari kolom `wilayah` yang berbentuk daftar bertanda
            // "Kec."/"Kab."/"Kota". Kolom yang hanya berisi nama desa (tanpa
            // penanda) tidak dipaksa jadi kab/kec — ia tetap muncul sebagai
            // pilihan koperasi di bawah, bukan diam-diam hilang.
            foreach (array_map('trim', explode(',', (string) $k->wilayah)) as $b) {
                if (preg_match('/^Kec\.?\s*(.+)$/iu', $b, $m)) {
                    $per[$wilayah]['kec'][mb_strtoupper($m[1])] = $m[1];
                } elseif (preg_match('/^(?:Kab|Kota)\.?\s*(.+)$/iu', $b, $m)) {
                    $per[$wilayah]['kab'][mb_strtoupper($m[1])] = $m[1];
                }
            }

            $manajer = $namaManajer[$k->manajer_id] ?? null;
            $per[$wilayah]['kop'][(string) $k->id] = $k->kode.' — '.$k->nama
                .($manajer ? ' · '.$manajer : '');
        }

        // Kelompok provinsi: TANPA pilihan provinsi tersendiri. Memilih
        // "Jawa Barat" saja tidak bisa dijalankan (manajer mana?), jadi tiap
        // butir di sini selalu menunjuk koperasi/kab/kec yang nyata. Provinsi
        // menjadi <optgroup>-nya, bukan pilihannya.
        $kelompok = [];
        foreach ($per as $wilayah => $isi) {
            $butir = [];
            foreach ($isi['kab'] as $nama) {
                // Kunci sama seperti dulu (`kabupaten:<NAMA>`) supaya lingkup yang
                // sudah tersimpan di ruang lama tetap sah.
                $butir['kabupaten:'.mb_strtoupper($nama)] = 'Kabupaten/Kota '.\Illuminate\Support\Str::title(mb_strtolower($nama));
            }
            foreach ($isi['kec'] as $nama) {
                $butir['kecamatan:'.mb_strtoupper($nama)] = 'Kecamatan '.\Illuminate\Support\Str::title(mb_strtolower($nama));
            }
            foreach ($isi['kop'] as $id => $label) {
                $butir['koperasi:'.$id] = $label;
            }
            if ($butir !== []) {
                $kelompok[$isi['label']] = $butir;
            }
        }

        ksort($kelompok);

        // Peran hanya untuk pengelola pusat/HR: bagi PIC & manajer, "semua PIC
        // se-Indonesia" bukan lingkup kerja mereka.
        if ($u->lingkupPenuh()) {
            $peran = [];
            foreach (\App\Models\Domain::LABEL_PERAN as $kode => $label) {
                $peran['peran:'.$kode] = $label;
            }
            if ($peran !== []) {
                $kelompok['Peran (semua wilayah)'] = $peran;
            }
        }

        return array_filter($kelompok);
    }

    /** Lingkup yang dikirim benar-benar ada di daftar pilihan pembuatnya? */
    private function lingkupSah(User $u, string $lingkup): bool
    {
        foreach ($this->lingkupDaftar($u) as $pilihan) {
            if (array_key_exists($lingkup, $pilihan)) {
                return true;
            }
        }

        return false;
    }

    /** Peserta yang diturunkan dari satu lingkup (id pengguna). */
    private function lingkupPeserta(string $lingkup): \Illuminate\Support\Collection
    {
        [$jenis, $nilai] = array_pad(explode(':', $lingkup, 2), 2, '');

        return match ($jenis) {
            'peran' => User::where('aktif', 1)->where('peran', $nilai)->pluck('id'),
            'wilayah' => $this->pesertaWilayah($nilai),
            'kabupaten', 'kecamatan' => $this->pesertaKabKec($nilai),
            'koperasi' => Koperasi::whereKey((int) $nilai)->pluck('manajer_id')->filter(),
            default => collect(),
        };
    }

    /** Satu wilayah (provinsi / Jabodetabek): PIC-nya + manajer koperasi di situ. */
    private function pesertaWilayah(string $wilayah): \Illuminate\Support\Collection
    {
        $manajer = Koperasi::query()->where(Wilayah::saring($wilayah))->pluck('manajer_id')->filter();

        return $manajer->merge(User::where('aktif', 1)->where('peran', 'PIC')
            ->where('wilayah', $wilayah)->pluck('id'))->unique()->values();
    }

    /**
     * Satu kabupaten/kecamatan: manajer koperasi yang wilayahnya memuat nama
     * itu + PIC yang menaungi provinsinya.
     *
     * ponytail: cocok berbasis TEKS pada `koperasi.wilayah`, jadi nama kembar di
     * dua provinsi akan terkena keduanya (sama dengan Tugas::saringPenerima).
     * Naikkan ke pencocokan berkode wilayah kalau nama kembar benar-benar muncul.
     */
    private function pesertaKabKec(string $nama): \Illuminate\Support\Collection
    {
        $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $nama).'%';
        $kop = Koperasi::query()->where('wilayah', 'like', $pola)->get(['manajer_id', 'provinsi']);

        $wilayah = $kop->pluck('provinsi')->unique()
            ->map(fn ($p) => Wilayah::lingkup((string) $p))->filter()->unique()->values();

        return $kop->pluck('manajer_id')->filter()
            ->merge(User::where('aktif', 1)->where('peran', 'PIC')->whereIn('wilayah', $wilayah)->pluck('id'))
            ->unique()->values();
    }

    /**
     * Ruang obrolan dalam lingkup: milik sendiri, koperasi lingkup, atau umum
     * (tanpa koperasi & tanpa pemilik).
     *
     * Ruang ber-`pemilik_id` (pembahasan dokumen pribadi) SENGAJA tidak ikut
     * lewat cabang "koperasi_id NULL = umum" — kalau ikut, pembahasan pribadi
     * terbaca semua orang. Hanya pemiliknya (dan pengelola pusat) yang melihatnya.
     */
    private function ruangQuery(User $u)
    {
        return RuangObrolan::lingkup($u);
    }

    /**
     * Dokumen yang boleh DILIHAT akun ini. Satu tempat, dipakai daftar maupun
     * halaman editor — jadi mustahil ada dokumen yang tampil di daftar tetapi
     * membalas 404 saat dibuka.
     *
     *   publik & tamu  → siapa pun yang punya akun (semua pengguna)
     *   koperasi      → dokumen umum (tanpa koperasi) + koperasi dalam lingkup
     *   pribadi       → pemiliknya, ATAU yang sudah diberi izin (dokumen_akses)
     *
     * Catatan: `akses` menggantikan kolom lama `pribadi`, tetapi KEDUANYA tetap
     * dijaga di sini — baris lama yang belum punya akses tetap terbaca benar
     * lewat `pribadi`. Menghapus salah satunya akan membuat dokumen lama lenyap
     * dari daftar tanpa pesan apa pun.
     */
    private function dokumenQuery(User $u)
    {
        $lingkup = $u->koperasiQuery()->select('koperasi.id');
        $diizinkan = DokumenAkses::query()->where('pengguna_id', $u->id)
            ->where('status', 'diberi')->select('dokumen_id');

        return Dokumen::query()->where(function ($q) use ($lingkup, $u, $diizinkan) {
            // Terbuka untuk semua pengguna.
            $q->whereIn('akses', Dokumen::AKSES_TERBUKA);
            // Dokumen umum & dokumen koperasi dalam lingkup.
            $q->orWhere(fn ($w) => $w->whereIn('akses', ['koperasi'])
                ->where(fn ($x) => $x->whereNull('koperasi_id')->orWhereIn('koperasi_id', $lingkup)));
            // Dokumen pribadi MILIK SENDIRI — untuk SEMUA peran, termasuk admin
            // pusat. Janji "pribadi" adalah tentang dokumen ORANG LAIN: admin
            // tetap tidak melihat dokumen pribadi siapa pun, tetapi dokumen
            // pribadinya sendiri harus bisa dibuka (kalau tidak, ia membuat
            // dokumen yang langsung hilang dari daftarnya sendiri).
            $q->orWhere(fn ($w) => $w->where('akses', 'pribadi')
                ->where(fn ($x) => $x->where('pemilik_id', $u->id)->orWhere('dibuat_oleh', $u->id)));
            // Sudah diberi izin atas dokumen orang lain (boleh masuk daftar
            // supaya tautannya bisa dibuka lagi).
            $q->orWhereIn('id', $diizinkan);
            // Baris lama yang belum punya kolom akses.
            $q->orWhere(fn ($w) => $w->whereNull('akses')->where('pribadi', 0)
                ->where(fn ($x) => $x->whereNull('koperasi_id')->orWhereIn('koperasi_id', $lingkup)));
        });
    }

    private function tugasQuery(User $u)
    {
        $lingkup = $u->koperasiQuery()->select('koperasi.id');

        return Tugas::query()->where(fn ($w) => $w
            ->when($u->manajerKoperasi(), fn ($q) => $q->whereIn('koperasi_id',
                Koperasi::where('manajer_id', $u->id)->select('id')))
            ->when(! $u->manajerKoperasi(), fn ($q) => $q->whereIn('koperasi_id', $lingkup))
            // Tugas yang DITUGASKAN langsung ke pengguna ini selalu terlihat, walau
            // koperasinya di luar lingkupnya — kalau tidak, tugas lintas wilayah
            // (PIC A -> PIC B) tak pernah sampai ke pelaksananya. Dikelompokkan
            // dalam satu where() supaya sambungan ->where() berikutnya tidak
            // menempel ke OR ini dan diam-diam melonggarkan penyaringan.
            ->orWhere('petugas_id', $u->id));
    }

    private function wikiQuery(User $u)
    {
        $lingkup = $u->koperasiQuery()->select('koperasi.id');

        return Wiki::query()->where(fn ($q) => $q
            ->whereNull('koperasi_id')
            ->orWhereIn('koperasi_id', $lingkup));
    }

    private function persetujuanQuery(User $u)
    {
        $lingkup = $u->koperasiQuery()->select('koperasi.id');

        return Persetujuan::query()
            ->when($u->manajerKoperasi(), fn ($q) => $q->where('pemohon_id', $u->id))
            ->when(! $u->manajerKoperasi(), fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('koperasi_id')->orWhereIn('koperasi_id', $lingkup)));
    }

    /** Jumlah baris ringkas untuk kartu modul di halaman Ruang Kerja. */
    private function ringkas(User $u): array
    {
        $dok = fn () => $this->dokumenQuery($u)->count();
        try {
            return [
                'pesan' => $this->ruangQuery($u)->count(),
                'dokumen' => $dok(),
                'kalender' => KonferensiRapat::where('mulai', '>=', now()->toDateTimeString())->count(),
                'tugas' => $this->tugasQuery($u)->count(),
                'wiki' => $this->wikiQuery($u)->count(),
                'catatan' => CatatanHarian::when($u->manajerKoperasi(), fn ($q) => $q->where('user_id', $u->id))->count(),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Halaman wiki -> pohon (induk_id). Satu lintasan, bukan kueri per baris. */
    private function pohon($halaman): array
    {
        $peta = [];
        foreach ($halaman as $h) {
            $peta[(int) ($h->induk_id ?? 0)][] = $h;
        }

        $susun = function (int $induk, int $dalam) use (&$susun, $peta): array {
            $keluar = [];
            foreach ($peta[$induk] ?? [] as $h) {
                $keluar[] = ['hal' => $h, 'dalam' => $dalam, 'anak' => $susun((int) $h->id, $dalam + 1)];
            }

            return $keluar;
        };

        return $susun(0, 0);
    }
}

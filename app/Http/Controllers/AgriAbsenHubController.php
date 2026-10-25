<?php

namespace App\Http\Controllers;

use App\Models\AgriAbsenHub;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Absen Agrinashub — absensi milik sistem ini sendiri.
 *
 * Ini absen KETIGA di AgriConnect, dan bedanya bukan sekadar merek: SIMKOPDES
 * dan Absen Agriconnect menyimpan satu baris per hari berisi jam masuk & keluar,
 * sehingga istirahat tidak punya tempat dan jam kerja bersih tidak bisa
 * dihitung. Di sini setiap kejadian adalah satu baris — masuk, mulai istirahat,
 * selesai istirahat, keluar — dan semua angka di halaman dihitung dari kejadian
 * itu. Tidak ada ringkasan yang disimpan terpisah, jadi tidak ada dua angka yang
 * bisa saling bertentangan.
 *
 * Kenapa ini "fokus di sistem kita": absen ini tidak bergantung pada SIMKOPDES
 * maupun AgriConnect. Kalau salah satu layanan itu lambat atau mati, kehadiran
 * tetap tercatat di sini, lengkap dengan foto dan titik lokasi.
 */
class AgriAbsenHubController extends Controller
{
    private const DISK = 'r2';
    private const FOTO_MAKS_KB = 1536;
    private const TOLERANSI_MENIT = 15;
    // Disamakan dengan klien (hub-absen.js). 500 m terlalu ketat: di laptop/PC
    // geolocation memakai WiFi/IP sehingga akurasi 1000-3000 m itu normal, dan
    // dengan batas 500 absen dari komputer tidak akan pernah bisa disimpan.
    private const AKURASI_MAKS = 2000;

    /** Urutan tombol yang mungkin, ditentukan kejadian terakhir hari ini. */
    private const SESUDAH = [
        ''                 => ['masuk'],
        'masuk'            => ['istirahat_mulai', 'keluar'],
        'istirahat_mulai'  => ['istirahat_selesai'],
        'istirahat_selesai'=> ['istirahat_mulai', 'keluar'],
        'keluar'           => [],
    ];

    private const LABEL = [
        'masuk'             => 'Masuk',
        'istirahat_mulai'   => 'Mulai Istirahat',
        'istirahat_selesai' => 'Selesai Istirahat',
        'keluar'            => 'Keluar',
    ];

    public function index(Request $request)
    {
        $u = $request->user();

        // Pemantau kehadiran (admin pusat, HR, PIC, keuangan) TIDAK mengabsen lewat
        // halaman ini — yang mereka cari adalah kehadiran para manajer. Daftarnya
        // sudah ada di halaman Presensi (presensiPemantau), jadi satu pintu:
        // alihkan, bukan menyalin tabel yang sama ke halaman kedua.
        if ($u->pemantauKehadiran()) {
            return redirect()->route('agri.presensi')
                ->with('pesan', 'Halaman Absen Agrinashub untuk absen pribadi. Daftar kehadiran manajer ada di Presensi.');
        }

        $hari = now('Asia/Jakarta')->toDateString();
        $bulan = $this->bulanAktif($request);

        $kejadian = AgriAbsenHub::where('user_id', $u->id)->where('tanggal', $hari)
            ->orderBy('pada')->orderBy('id')->get();

        return view('agri.absen-hub', [
            'aktif'    => 'agri',
            'hari'     => $hari,
            'kejadian' => $kejadian,
            'status'   => $this->status($kejadian),
            'bulan'    => $bulan,
            // Halaman PERTAMA dirender di server, sisanya lewat AJAX. Tanpa ini
            // riwayat tidak terbaca sama sekali kalau JavaScript gagal dimuat —
            // dan riwayat kehadiran tidak boleh bergantung pada JS.
            'riwayat'  => $this->riwayatHalaman($u, $bulan, 1),
            'rekap'    => $this->rekap($u, $bulan),
            'bulanPilihan' => self::BULAN,
            'tahunPilihan' => $this->tahunPilihan($u),
        ]);
    }

    /**
     * Satu halaman riwayat untuk AJAX.
     *
     * Yang dibalas JSON berisi HTML yang SUDAH dirender server, bukan data
     * mentah. Alasannya: format tanggal Indonesia, nama hari, dan hitungan
     * durasi hidup di Blade + Carbon. Kalau JS yang memformat, tampilan halaman
     * pertama (server) dan halaman kedua (JS) pasti berbeda diam-diam — dan
     * itulah jenis bug yang paling sulit dilihat.
     */
    public function riwayat(Request $request)
    {
        $u = $request->user();
        $bulan = $this->bulanAktif($request);
        $halaman = max(1, (int) $request->query('halaman', 1));

        $riw = $this->riwayatHalaman($u, $bulan, $halaman);

        return response()->json([
            'ok' => true,
            'html' => view('agri._absen-riwayat', $riw)->render(),
            'bulan' => $bulan,
            'halaman' => $riw['halaman'],
            'totalHalaman' => $riw['totalHalaman'],
            'total' => $riw['total'],
            'url' => route('agri.absen-hub', ['bulan' => $bulan, 'halaman' => $riw['halaman']]),
        ]);
    }

    /**
     * Catat satu kejadian.
     *
     * Aturan urutan ditegakkan di sini, bukan hanya dengan menyembunyikan tombol:
     * tombol bisa ditekan dua kali (jaringan lambat, dua tab), dan yang lebih
     * penting, absen adalah bukti — "keluar" sebelum "masuk" bukan kesalahan
     * tampilan, itu data yang tidak masuk akal.
     */
    public function simpan(Request $request)
    {
        $u = $request->user();
        $hari = now('Asia/Jakarta')->toDateString();

        $v = $request->validate([
            'jenis'      => ['required', 'in:masuk,istirahat_mulai,istirahat_selesai,keluar'],
            'catatan'    => ['nullable', 'string', 'max:200'],
            'lat'        => ['nullable', 'numeric', 'between:-90,90'],
            'lng'        => ['nullable', 'numeric', 'between:-180,180'],
            'akurasi'    => ['nullable', 'numeric', 'between:1,'.self::AKURASI_MAKS],
            'lokasi'     => ['nullable', 'string', 'max:255'],
            'waktu'      => ['nullable', 'date'],
            'zona'       => ['nullable', 'string', 'max:64'],
            'foto_kunci' => ['nullable', 'string', 'max:120'],
            'foto_data'  => ['nullable', 'string', 'max:3000000'],
        ], [
            'akurasi.between' => 'Sinyal lokasi tidak wajar — matikan aplikasi lokasi palsu, lalu ambil titiknya di area terbuka.',
        ]);

        $jenis = $v['jenis'];

        // Jam perangkat, dengan penjagaan yang sama seperti absen lain: jam yang
        // melenceng jauh membuat catatan kehadiran tidak bisa dipakai.
        $kapan = $this->waktuPerangkat($request);
        if (trim((string) $request->input('waktu', '')) !== '' && $kapan === null) {
            return back()->with('galat',
                'Jam perangkat melenceng lebih dari '.self::TOLERANSI_MENIT.' menit dari jam server. Nyalakan jam otomatis, lalu coba lagi.');
        }
        $kapan = $kapan ?? now('Asia/Jakarta');
        $hari = $kapan->toDateString();

        $sebelum = AgriAbsenHub::where('user_id', $u->id)->where('tanggal', $hari)
            ->orderBy('pada')->orderBy('id')->get();
        $boleh = self::SESUDAH[$this->status($sebelum)['terakhir']] ?? [];

        if (! in_array($jenis, $boleh, true)) {
            return back()->with('galat', $this->pesanUrutan($jenis, $this->status($sebelum)['terakhir']));
        }

        // Foto & lokasi WAJIB untuk MASUK dan KELUAR. Istirahat cukup satu tanda;
        // memaksa selfie setiap kali mau istirahat membuat orang berhenti
        // memakai fiturnya, dan itu justru menghilangkan datanya.
        $wajibBukti = in_array($jenis, ['masuk', 'keluar'], true);

        $foto = null;
        if (($kunci = $this->fotoDariKunci($request)) !== null) {
            $foto = $kunci;
        } else {
            $foto = $this->simpanFotoData($request);
        }

        if ($wajibBukti) {
            if ($foto === null) {
                return back()->withInput()->with('galat',
                    self::LABEL[$jenis].' memerlukan foto selfie dari kamera.');
            }
            if (trim((string) $request->input('lat', '')) === ''
                || trim((string) $request->input('lng', '')) === '') {
                return back()->withInput()->with('galat',
                    self::LABEL[$jenis].' memerlukan titik lokasi — izinkan akses lokasi di peramban.');
            }
            if (trim((string) $request->input('lat')) === '0' && trim((string) $request->input('lng')) === '0') {
                return back()->withInput()->with('galat',
                    'Titik 0,0 bukan tempat tinggal siapa pun — matikan aplikasi lokasi palsu.');
            }
        }

        try {
            AgriAbsenHub::create([
                'user_id'     => $u->id,
                'koperasi_id' => $u->koperasi_id,
                'tanggal'     => $hari,
                'jenis'       => $jenis,
                'waktu'       => $kapan->format('H:i'),
                'pada'        => $kapan->toIso8601String(),
                'zona'        => mb_substr((string) $request->input('zona', ''), 0, 64),
                'foto'        => $foto ?? '',
                'lat'         => $this->angka($request->input('lat')),
                'lng'         => $this->angka($request->input('lng')),
                'akurasi'     => $this->angka($request->input('akurasi')),
                'lokasi'      => mb_substr(trim((string) $request->input('lokasi', '')), 0, 255),
                'catatan'     => mb_substr(trim((string) $request->input('catatan', '')), 0, 200),
                'ip'          => mb_substr((string) ($request->ip() ?? ''), 0, 64),
                'perangkat'   => mb_substr((string) $request->userAgent(), 0, 255),
                'dibuat'      => kini(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Dua kiriman hampir bersamaan: yang kedua ditolak index, dan itu
            // memang yang diinginkan. Beri tahu apa adanya, bukan error 500.
            return back()->with('galat', self::LABEL[$jenis].' hari ini sudah tercatat.');
        }

        return back()->with('pesan', self::LABEL[$jenis].' tercatat pukul '.$kapan->format('H:i').'.');
    }

    /** Izin tulis berdurasi pendek supaya foto ditaruh peramban LANGSUNG di R2. */
    public function urlUnggah(Request $request)
    {
        $u = $request->user();
        $v = $request->validate([
            'ukuran' => ['required', 'integer', 'min:1024', 'max:'.(self::FOTO_MAKS_KB * 1024)],
        ], [
            'ukuran.max' => 'Foto melebihi batas '.round(self::FOTO_MAKS_KB / 1024, 1).' MB.',
        ]);

        $nama = 'agri/absen-hub/'.now()->format('Ymd').'-'.bin2hex(random_bytes(16)).'.jpg';
        Cache::put('unggah:'.$nama, (int) $u->id, now()->addMinutes(15));

        $cfg = config('filesystems.disks.r2');
        $perintah = $this->r2()->getCommand('PutObject', [
            'Bucket' => $cfg['bucket'],
            'Key' => $nama,
            'ContentType' => 'image/jpeg',
            'ContentLength' => (int) $v['ukuran'],
        ]);

        return response()->json([
            'ok' => true,
            'kunci' => $nama,
            'url' => (string) $this->r2()->createPresignedRequest($perintah, '+10 minutes')->getUri(),
        ]);
    }

    /** Foto absen: WAJIB lewat rute ber-auth ini, bukan tautan langsung ke R2. */
    public function foto(Request $request, int $id)
    {
        $b = AgriAbsenHub::whereKey($id)->firstOrFail();
        abort_unless($request->user()->bolehLihatKehadiran((int) $b->user_id), 404, 'Foto absen tidak ditemukan.');
        $nama = trim((string) $b->foto);
        if ($nama === '' || Storage::disk(self::DISK)->size($nama) === 0) {
            abort(404, 'Foto absen tidak ditemukan.');
        }

        return Storage::disk(self::DISK)->response($nama);
    }

    // ------------------------------------------------------------ hitungan

    /**
     * Status hari ini dari KEJADIAN, bukan dari kolom ringkasan.
     *
     * `terakhir` menentukan tombol apa yang boleh ditekan; `masuk`, `keluar`,
     * dan `istirahat` adalah hasil hitung yang dipakai tampilan.
     */
    private function status($kejadian): array
    {
        $terakhir = '';
        $masuk = null;
        $keluar = null;
        $istirahatDetik = 0;
        $mulaiIstirahat = null;
        $jumlahIstirahat = 0;

        foreach ($kejadian as $k) {
            $terakhir = $k->jenis;
            $w = $k->pada ? Carbon::parse($k->pada) : null;

            if ($k->jenis === 'masuk') {
                $masuk = $w;
            } elseif ($k->jenis === 'keluar') {
                $keluar = $w;
            } elseif ($k->jenis === 'istirahat_mulai') {
                $mulaiIstirahat = $w;
                $jumlahIstirahat++;
            } elseif ($k->jenis === 'istirahat_selesai' && $mulaiIstirahat !== null) {
                $istirahatDetik += max(0, $mulaiIstirahat->diffInSeconds($w, false));
                $mulaiIstirahat = null;
            }
        }

        // Istirahat yang belum ditutup tetap dihitung berjalan, supaya angka
        // "kerja bersih" tidak terlihat lebih besar dari kenyataan.
        $istirahatBerjalan = $mulaiIstirahat !== null;
        if ($istirahatBerjalan) {
            $istirahatDetik += max(0, $mulaiIstirahat->diffInSeconds(now('Asia/Jakarta'), false));
        }

        $kerjaDetik = null;
        if ($masuk !== null) {
            $ujung = $keluar ?? now('Asia/Jakarta');
            $kerjaDetik = max(0, $masuk->diffInSeconds($ujung, false) - $istirahatDetik);
        }

        return [
            'terakhir' => $terakhir,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'kerjaDetik' => $kerjaDetik,
            'istirahatDetik' => $istirahatDetik,
            'istirahatBerjalan' => $istirahatBerjalan,
            'jumlahIstirahat' => $jumlahIstirahat,
            'boleh' => self::SESUDAH[$terakhir] ?? [],
        ];
    }

    /** Nama bulan Indonesia; dipakai di pilihan saring. */
    private const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** 10 hari per halaman: cukup untuk satu layar ponsel tanpa menggulir jauh. */
    private const PER_HALAMAN = 10;

    /**
     * Bulan yang sedang dilihat.
     *
     * Menerima DUA bentuk: `bulan=YYYY-MM` (dipakai tautan & AJAX) dan
     * `tahun`+`bln` (dipakai dua dropdown di formulir). Bentuk kedua ada supaya
     * formulir saring tetap bekerja TANPA JavaScript — dua dropdown lebih mudah
     * dipakai di ponsel daripada pemilih bulan bawaan peramban.
     */
    private function bulanAktif(Request $request): string
    {
        $bulan = (string) $request->query('bulan', '');
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            return $bulan;
        }

        $tahun = (string) $request->query('tahun', '');
        $bln = (string) $request->query('bln', '');
        if (preg_match('/^\d{4}$/', $tahun) && preg_match('/^(0?[1-9]|1[0-2])$/', $bln)) {
            return $tahun.'-'.str_pad($bln, 2, '0', STR_PAD_LEFT);
        }

        return now('Asia/Jakarta')->format('Y-m');
    }

    /** Tahun yang masuk akal untuk dipilih: dari data tertua sampai tahun ini. */
    private function tahunPilihan(User $u): array
    {
        $ini = (int) now('Asia/Jakarta')->format('Y');
        $tertua = AgriAbsenHub::where('user_id', $u->id)->min('tanggal');
        $awal = $tertua ? (int) substr((string) $tertua, 0, 4) : $ini;
        $awal = max($awal, $ini - 5);
        $daftar = [];
        for ($th = $ini; $th >= $awal; $th--) {
            $daftar[] = $th;
        }

        return $daftar;
    }

    /**
     * Satu halaman riwayat.
     *
     * Halaman dipotong pada HARI, bukan pada baris kejadian: satu hari bisa
     * berisi empat kejadian (masuk, dua istirahat, keluar), dan memotong pada
     * baris kejadian akan membelah satu hari kerja menjadi dua halaman.
     *
     * Karena itu tanggal diambil lebih dulu (DISTINCT), dipotong sesuai halaman,
     * baru kejadian hari-hari itu dimuat. Dua query, bukan satu query besar yang
     * seluruh bulannya ditahan di memori.
     */
    private function riwayatHalaman(User $u, string $bulan, int $halaman): array
    {
        $per = self::PER_HALAMAN;

        $semuaTanggal = AgriAbsenHub::where('user_id', $u->id)
            ->where('tanggal', 'like', $bulan.'-%')
            ->distinct()->orderByDesc('tanggal')->pluck('tanggal')->all();

        $total = count($semuaTanggal);
        $totalHalaman = max(1, (int) ceil($total / $per));
        $halaman = min(max(1, $halaman), $totalHalaman);
        $potong = array_slice($semuaTanggal, ($halaman - 1) * $per, $per);

        $baris = collect();
        if ($potong !== []) {
            $kejadian = AgriAbsenHub::where('user_id', $u->id)
                ->whereIn('tanggal', $potong)
                ->orderBy('pada')->orderBy('id')->get()->groupBy('tanggal');

            $baris = collect($potong)->map(function ($tgl) use ($kejadian) {
                $k = $kejadian->get($tgl, collect());
                $s = $this->status($k);
                // DUA foto, bukan satu. Absen masuk dan absen pulang adalah dua
                // KEJADIAN berbeda, masing-masing dengan fotonya sendiri. Dulu
                // hanya yang pertama ditemukan yang dikirim, sehingga mustahil
                // menampilkan keduanya sekaligus.
                $fMasuk = $k->first(fn ($x) => $x->jenis === 'masuk' && trim((string) $x->foto) !== '');
                $fKeluar = $k->first(fn ($x) => $x->jenis === 'keluar' && trim((string) $x->foto) !== '');
                $t = \Carbon\Carbon::parse($tgl);

                if ($s['masuk'] === null) {
                    $status = ['Tanpa masuk', 's-BARU'];
                } elseif ($s['keluar'] === null) {
                    $status = ['Belum keluar', 's-DIAJUKAN'];
                } else {
                    $status = ['Lengkap', 's-SELESAI'];
                }

                return [
                    'tanggal' => $tgl,
                    'tanggalTeks' => $t->locale('id')->translatedFormat('d M Y'),
                    'hariTeks' => $t->locale('id')->translatedFormat('l'),
                    'masukTeks' => $s['masuk']?->format('H:i') ?? '—',
                    'keluarTeks' => $s['keluar']?->format('H:i') ?? '—',
                    'kerjaTeks' => self::detikTeks($s['kerjaDetik']),
                    'istirahatTeks' => self::detikTeks($s['istirahatDetik']),
                    'jumlahIstirahat' => $s['jumlahIstirahat'],
                    'fotoMasukId' => $fMasuk?->id,
                    'fotoKeluarId' => $fKeluar?->id,
                    // Jam ikut dikirim: keterangan di dalam modal harus menyebut
                    // foto mana milik jam berapa — tanpa itu dua potret wajah
                    // terlihat sama dan pengguna tidak tahu mana yang mana.
                    'fotoMasukJam' => $fMasuk?->waktu,
                    'fotoKeluarJam' => $fKeluar?->waktu,
                    'statusTeks' => $status[0],
                    'statusKelas' => $status[1],
                ];
            });
        }

        $dari = $total === 0 ? 0 : (($halaman - 1) * $per) + 1;
        $sampai = min($total, $halaman * $per);

        return [
            'baris' => $baris,
            'total' => $total,
            'halaman' => $halaman,
            'totalHalaman' => $totalHalaman,
            'per' => $per,
            'bulan' => $bulan,
            'bulanTeks' => self::BULAN[(int) substr($bulan, 5, 2)].' '.substr($bulan, 0, 4),
            'dari' => $dari,
            'sampai' => $sampai,
        ];
    }

    /** Detik -> "7j 12m" / "45m" / "—". Satu tempat, dipakai semua tampilan. */
    private static function detikTeks(?int $detik): string
    {
        if ($detik === null) {
            return '—';
        }
        $jam = intdiv($detik, 3600);
        $menit = intdiv($detik % 3600, 60);

        return $jam > 0 ? $jam.'j '.$menit.'m' : $menit.'m';
    }

    /** Rekap satu bulan: hari hadir, jam kerja, istirahat, terlambat. */
    private function rekap(User $u, string $bulan): array
    {
        $semua = AgriAbsenHub::where('user_id', $u->id)
            ->where('tanggal', 'like', $bulan.'-%')
            ->orderBy('pada')->get()->groupBy('tanggal');

        $hariHadir = 0;
        $totalKerja = 0;
        $totalIstirahat = 0;
        $terlambat = 0;
        $jumlahMasuk = 0;
        $menitMasuk = 0;

        foreach ($semua as $tanggal => $k) {
            $s = $this->status($k);
            if ($s['masuk'] === null) {
                continue;
            }
            $hariHadir++;
            $totalKerja += (int) ($s['kerjaDetik'] ?? 0);
            $totalIstirahat += (int) $s['istirahatDetik'];
            $jam = (int) $s['masuk']->format('H');
            $menit = (int) $s['masuk']->format('i');
            $jumlahMasuk++;
            $menitMasuk += $jam * 60 + $menit;
            if ($jam * 60 + $menit > 8 * 60) {
                $terlambat++;
            }
        }

        $rata = $jumlahMasuk > 0 ? (int) round($menitMasuk / $jumlahMasuk) : null;

        return [
            'hariHadir' => $hariHadir,
            'kerjaDetik' => $totalKerja,
            'istirahatDetik' => $totalIstirahat,
            'terlambat' => $terlambat,
            'rataMasuk' => $rata === null ? '—' : sprintf('%02d:%02d', intdiv($rata, 60), $rata % 60),
        ];
    }

    private function pesanUrutan(string $minta, string $terakhir): string
    {
        if ($terakhir === 'keluar') {
            return 'Absen hari ini sudah lengkap — '.self::LABEL[$minta].' tidak bisa dicatat lagi.';
        }
        if ($terakhir === '' && $minta !== 'masuk') {
            return 'Belum ada absen masuk hari ini. Tekan Masuk dulu.';
        }
        if ($terakhir === 'istirahat_mulai' && $minta !== 'istirahat_selesai') {
            return 'Istirahat belum ditutup. Tekan Selesai Istirahat dulu.';
        }

        return 'Urutan tidak sesuai: setelah "'.self::LABEL[$terakhir].'", yang bisa dicatat adalah '
            .implode(' atau ', array_map(fn ($j) => self::LABEL[$j], self::SESUDAH[$terakhir] ?? [])).'.';
    }

    // ------------------------------------------------------------- bantuan

    private function waktuPerangkat(Request $request): ?Carbon
    {
        $w = trim((string) $request->input('waktu', ''));
        if ($w === '') {
            return null;
        }
        try {
            $c = Carbon::parse($w);
        } catch (\Throwable $e) {
            return null;
        }
        if (abs($c->diffInMinutes(now())) > self::TOLERANSI_MENIT) {
            return null;
        }

        return $c->setTimezone('Asia/Jakarta');
    }

    private function angka(mixed $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, 32);
    }

    private function r2(): \Aws\S3\S3Client
    {
        $cfg = config('filesystems.disks.r2');

        return new \Aws\S3\S3Client([
            'version' => 'latest',
            'region' => $cfg['region'] ?? 'auto',
            'endpoint' => $cfg['endpoint'],
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => $cfg['key'], 'secret' => $cfg['secret']],
        ]);
    }

    /**
     * Foto yang sudah ditaruh peramban di R2, diperiksa sebelum dipakai.
     * Satu permintaan saja: tiga byte pertama (magic JPEG) + ukuran total dari
     * ContentRange. Memeriksa isi tidak boleh mengembalikan beban yang baru saja
     * dihemat.
     */
    private function fotoDariKunci(Request $request): ?string
    {
        $nama = trim((string) $request->input('foto_kunci', ''));
        if ($nama === '') {
            return null;
        }
        if (! preg_match('#^agri/absen-hub/\d{8}-[a-f0-9]{32}\.jpg$#', $nama)) {
            return null;
        }
        if ((int) Cache::get('unggah:'.$nama) !== (int) $request->user()->id) {
            return null;
        }

        try {
            $hasil = $this->r2()->getObject([
                'Bucket' => config('filesystems.disks.r2.bucket'),
                'Key' => $nama,
                'Range' => 'bytes=0-2',
            ]);
            $kepala = (string) $hasil['Body'];
            $total = 0;
            if (preg_match('#/(\d+)$#', (string) ($hasil['ContentRange'] ?? ''), $m)) {
                $total = (int) $m[1];
            }
        } catch (\Throwable $e) {
            return null;
        }

        if (strncmp($kepala, "\xFF\xD8\xFF", 3) !== 0 || $total <= 0 || $total > self::FOTO_MAKS_KB * 1024) {
            Storage::disk(self::DISK)->delete($nama);
            Cache::forget('unggah:'.$nama);

            return null;
        }

        Cache::forget('unggah:'.$nama);

        return $nama;
    }

    /** Jalur cadangan: data URL di badan permintaan, kalau unggah langsung gagal. */
    private function simpanFotoData(Request $request): ?string
    {
        $data = trim((string) $request->input('foto_data', ''));
        if ($data === '') {
            return null;
        }
        if (! preg_match('#^data:image/(jpeg|png);base64,([A-Za-z0-9+/=]+)$#', $data, $m)) {
            return null;
        }
        $biner = base64_decode($m[2], true);
        if ($biner === false || $biner === '' || strlen($biner) > self::FOTO_MAKS_KB * 1024) {
            return null;
        }
        $ukuran = @getimagesizefromstring($biner);
        if (! $ukuran || $ukuran[0] < 2 || $ukuran[1] < 2) {
            return null;
        }
        $nama = 'agri/absen-hub/'.now()->format('Ymd').'-'.bin2hex(random_bytes(16))
            .($m[1] === 'png' ? '.png' : '.jpg');

        return Storage::disk(self::DISK)->put($nama, $biner) ? $nama : null;
    }
}

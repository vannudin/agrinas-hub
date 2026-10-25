<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Distribusi;
use App\Models\Domain;
use App\Models\Koperasi;
use App\Models\KoperasiLahan;
use App\Models\Tugas;
use App\Models\User;
use App\Services\LahanPenarik;
use App\Support\Analisa;
use App\Support\Halaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DasborController extends Controller
{
    /** Baris per halaman untuk seluruh daftar admin. */
    public const PER_HALAMAN = 10;

    /**
     * Dasbor admin pusat/PIC: kartu ringkasan + DUA daftar berhalaman
     * (tugas & koperasi binaan), masing-masing dengan pencarian dan saringannya
     * sendiri. Kunci halaman keduanya dipisah (`halaman` & `halaman_koperasi`)
     * supaya memindahkan satu daftar tidak menggeser daftar yang lain —
     * lihat tautan_halaman().
     */
    public function dasbor(Request $request)
    {
        $q = (string) $request->query('q', '');
        $status = mb_strtoupper((string) $request->query('status', ''));
        $qk = trim((string) $request->query('qk', ''));
        $sk = mb_strtoupper((string) $request->query('sk', ''));

        // Satu sumber lingkup: Agrinas pusat = semua, PIC = wilayahnya saja.
        // Ditaruh di awal supaya seluruh daftar & hitungan di bawah ikut tersaring
        // (bukan disaring di satu tempat saja lalu bocor di tempat lain).
        $lingkup = $request->user()->koperasiQuery()->select('koperasi.id');

        // Daftar koperasi JUGA berhalaman. Dulu dibiarkan tampil seluruhnya
        // (18 baris) sehingga satu tabel tanpa paging, pencarian, maupun saringan
        // — dan makin panjang seiring koperasi binaan bertambah. Saringan
        // dikerjakan di SQL supaya paginasi menghitung seluruh baris, bukan
        // hanya satu halaman.
        $qKoperasi = Koperasi::query()
            ->whereIn('koperasi.id', $lingkup)
            ->leftJoin('users as us', 'us.id', '=', 'koperasi.manajer_id')
            ->select('koperasi.*', 'us.nama as manajer', 'us.email as manajer_email',
                DB::raw('(SELECT COUNT(*) FROM tugas t WHERE t.koperasi_id = koperasi.id) as jumlah_tugas'),
                DB::raw("(SELECT COUNT(*) FROM tugas t WHERE t.koperasi_id = koperasi.id AND t.status <> 'SELESAI') as tugas_aktif"))
            ->when($qk !== '', fn ($w) => $w->where(function ($sub) use ($qk) {
                $sub->where('koperasi.nama', 'like', "%{$qk}%")
                    ->orWhere('koperasi.kode', 'like', "%{$qk}%")
                    ->orWhere('koperasi.wilayah', 'like', "%{$qk}%")
                    ->orWhere('us.nama', 'like', "%{$qk}%")
                    ->orWhere('us.email', 'like', "%{$qk}%");
            }))
            // Saringan status diambil dari daftar label model, bukan dirakit
            // mentah dari input, supaya nilainya selalu salah satu yang sah.
            ->when(isset(Koperasi::LABEL_STATUS[$sk]), fn ($w) => $w->where('koperasi.status', $sk))
            ->orderBy('koperasi.nama');

        $halamanKoperasi = Halaman::buat((clone $qKoperasi)->count(),
            $request->query('halaman_koperasi', 1), self::PER_HALAMAN);
        $koperasi = $halamanKoperasi->batasi($qKoperasi)->get();

        // Saringan dikerjakan di SQL supaya paginasi menghitung seluruh baris,
        // bukan hanya satu halaman.
        $qTugas = Tugas::query()
            ->whereIn('tugas.koperasi_id', $lingkup)
            ->join('koperasi as k', 'k.id', '=', 'tugas.koperasi_id')
            ->select('tugas.*', 'k.nama as koperasi')
            ->when($status !== '' && $status !== 'TERLAMBAT', fn ($w) => $w->where('tugas.status', $status))
            ->when($status === 'TERLAMBAT', fn ($w) => $w->where('tugas.status', '<>', 'SELESAI')
                ->whereNotNull('tugas.deadline')->where('tugas.deadline', '<', now()->toDateString()))
            ->when($q !== '', fn ($w) => $w->where(function ($sub) use ($q) {
                $sub->where('tugas.judul', 'like', "%{$q}%")->orWhere('k.nama', 'like', "%{$q}%");
            }))
            ->orderByRaw("CASE tugas.status WHEN 'DIAJUKAN' THEN 0 WHEN 'REVISI' THEN 1
                          WHEN 'DIKERJAKAN' THEN 2 WHEN 'BARU' THEN 3 ELSE 4 END")
            ->orderByRaw('tugas.deadline IS NULL')
            ->orderBy('tugas.deadline');

        // COUNT + LIMIT 10 di DB. Pola lama (->get() lalu potong di PHP) menarik
        // SELURUH tabel tugas tiap kali dasbor dibuka — boros memori dan makin
        // lambat seiring data bertambah, padahal yang tampil hanya 10 baris.
        $halamanTugas = Halaman::buat((clone $qTugas)->count(), $request->query('halaman', 1), self::PER_HALAMAN);
        $tugas = $halamanTugas->batasi($qTugas)->get();

        $stat = Tugas::query()->whereIn('koperasi_id', $lingkup)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')
            ->pluck('n', 'status')->all();
        $stat['TERLAMBAT'] = Tugas::query()->whereIn('koperasi_id', $lingkup)
            ->where('status', '<>', 'SELESAI')
            ->whereNotNull('deadline')->where('deadline', '<', now()->toDateString())->count();

        // Perjalanan barang (distribusi) dalam lingkup pengguna — kelompok kartu
        // "Proses Barang" di dasbor. Manajer punya versi tersendiri di /kerja.
        $statKirim = Distribusi::query()->whereIn('koperasi_id', $lingkup)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all();

        // Jumlah koperasi untuk kartu ringkasan: DIHITUNG, bukan dari count()
        // baris yang sudah dibatasi halaman — else kartunya berubah tiap pindah
        // halaman.
        $jumlahKoperasi = Koperasi::query()->whereIn('koperasi.id', $lingkup)->count();

        return view('admin.dasbor', [
            'aktif' => 'dasbor', 'koperasi' => $koperasi, 'tugas' => $tugas,
            'halaman' => $halamanTugas, 'halamanKoperasi' => $halamanKoperasi,
            'stat' => $stat, 'jumlahKoperasi' => $jumlahKoperasi,
            'q' => $q, 'status' => $status, 'qk' => $qk, 'sk' => $sk,
            'statKirim' => $statKirim,
        ]);
    }

    /** Tenggat lewat dan tugas belum selesai. Static — blade memanggilnya
     *  sebagai `DasborController::terlambat($t)`, sama seperti KerjaController. */
    public static function terlambat($t): bool
    {
        return (bool) $t->deadline && $t->deadline < now()->toDateString() && $t->status !== 'SELESAI';
    }

    public function koperasi(Request $request)
    {
        $qk = trim((string) $request->query('qk', ''));
        $sk = mb_strtoupper((string) $request->query('sk', ''));
        $lahan = (string) $request->query('lahan', '');

        $baris = Koperasi::query()
            ->whereIn('koperasi.id', $request->user()->koperasiQuery()->select('koperasi.id'))
            ->leftJoin('users as us', 'us.id', '=', 'koperasi.manajer_id')
            ->select('koperasi.*', 'us.nama as manajer', 'us.email as manajer_email',
                DB::raw('(SELECT COUNT(*) FROM tugas t WHERE t.koperasi_id = koperasi.id) as jumlah_tugas'),
                // Data lahan/pembangunan (verval aset). Diambil lewat sub-select,
                // bukan join: satu koperasi bisa punya >1 bidang, dan join akan
                // menggandakan barisnya sehingga paging menghitung salah.
                DB::raw('(SELECT COUNT(*) FROM koperasi_lahan l WHERE l.koperasi_id = koperasi.id) as jumlah_lahan'),
                DB::raw('(SELECT l.luas FROM koperasi_lahan l WHERE l.koperasi_id = koperasi.id ORDER BY l.luas DESC LIMIT 1) as luas_lahan'),
                DB::raw('(SELECT l.status FROM koperasi_lahan l WHERE l.koperasi_id = koperasi.id ORDER BY l.luas DESC LIMIT 1) as status_lahan'))
            ->when($qk !== '', fn ($w) => $w->where(function ($sub) use ($qk) {
                $sub->where('koperasi.nama', 'like', "%{$qk}%")
                    ->orWhere('koperasi.kode', 'like', "%{$qk}%")
                    ->orWhere('koperasi.wilayah', 'like', "%{$qk}%")
                    ->orWhere('us.nama', 'like', "%{$qk}%")
                    ->orWhere('us.email', 'like', "%{$qk}%");
            }))
            ->when(isset(Koperasi::LABEL_STATUS[$sk]), fn ($w) => $w->where('koperasi.status', $sk))
            // "Belum ada data lahan" = hal yang paling sering dicari pengelola,
            // karena itu daftar kerja mereka.
            ->when($lahan === 'ada', fn ($w) => $w->whereExists(fn ($x) => $x->select(DB::raw(1))
                ->from('koperasi_lahan as l')->whereColumn('l.koperasi_id', 'koperasi.id')))
            ->when($lahan === 'belum', fn ($w) => $w->whereNotExists(fn ($x) => $x->select(DB::raw(1))
                ->from('koperasi_lahan as l')->whereColumn('l.koperasi_id', 'koperasi.id')))
            ->orderBy('koperasi.nama');

        $halaman = Halaman::buat((clone $baris)->count(), $request->query('halaman', 1), self::PER_HALAMAN);

        // Lahan detail untuk baris yang TAMPIL saja (satu query, bukan per baris).
        //
        // Dipetakan lewat `koperasi_simkopdes_id`, BUKAN `koperasi_lahan.koperasi_id`:
        // id koperasi adalah kunci surrogat yang bisa DIPAKAI ULANG — suite uji lain
        // membuat & menghapus koperasi, dan id bekasnya bisa jatuh ke baris lain,
        // sehingga lahan nyangkut ke koperasi yang salah (terukur: baris lahan
        // menunjuk id milik koperasi uji sementara). `koperasi_simkopdes_id` adalah
        // kunci alaminya dan tetap benar walau baris `koperasi` dibuat ulang.
        $daftar = $halaman->batasi($baris)->get();
        $lahanPeta = KoperasiLahan::whereIn('koperasi_simkopdes_id',
            $daftar->pluck('koperasi_simkopdes_id')->filter()->all())
            ->orderByDesc('luas')->get()->groupBy('koperasi_simkopdes_id');

        // Koperasi ISIAN MANUAL tidak punya `koperasi_simkopdes_id`, jadi baris
        // lahannya hanya bisa ditemukan lewat `koperasi_id`. Dikunci "id:<id>"
        // supaya tidak bertabrakan dengan kunci simkopdes di peta yang sama.
        KoperasiLahan::whereIn('koperasi_id', $daftar->pluck('id')->all())
            ->whereNull('koperasi_simkopdes_id')->orderByDesc('luas')->get()
            ->groupBy('koperasi_id')
            ->each(fn ($rows, $kid) => $lahanPeta->put('id:'.$kid, $rows));

        // Penanggung jawab dari HUB: akun yang ditempatkan di koperasi ini
        // (`users.koperasi_simkopdes_id`), beserta telepon dari profilnya.
        //
        // Nomor ini berasal dari profil yang DISIMPAN saat pendamping itu masuk
        // (ProfilPenarik) — satu akun SIMKOPDES tidak boleh membaca profil
        // pendamping lain (`/regional-facilitators/detail/{id_lain}` → 403),
        // jadi ini satu-satunya jalur yang jujur untuk nomor per koperasi.
        $pjPeta = DB::table('users as u')
            ->leftJoin('profil as p', 'p.user_id', '=', 'u.id')
            ->whereIn('u.koperasi_simkopdes_id', $daftar->pluck('koperasi_simkopdes_id')->filter()->all())
            ->where('u.aktif', 1)
            ->select('u.id', 'u.nama', 'u.email', 'u.koperasi_simkopdes_id', 'p.telepon')
            ->get()->keyBy('koperasi_simkopdes_id');

        // Kalau tidak ada akun yang ditempatkan, manajer koperasi di Hub tetap
        // orang yang bertanggung jawab — nomornya dicari dari profilnya, BUKAN
        // dikira-kira dari surel (kolom ini berjudul "Nomor HP").
        $telManajer = DB::table('users as u')
            ->leftJoin('profil as p', 'p.user_id', '=', 'u.id')
            ->whereIn('u.id', $daftar->pluck('manajer_id')->filter()->all())
            ->pluck('p.telepon', 'u.id');

        return view('admin.koperasi', ['aktif' => 'kop',
            'koperasi' => $daftar, 'halaman' => $halaman, 'lahanPeta' => $lahanPeta,
            'pjPeta' => $pjPeta, 'telManajer' => $telManajer,
            'qk' => $qk, 'sk' => $sk, 'lahan' => $lahan]);
    }

    /**
     * Halaman penautan akun manajer ↔ koperasi (kewenangan pusat).
     *
     * Dibuat karena penautan otomatis (profil SIMKOPDES menyimpan
     * `cooperativeId`) tidak bisa jalan selama server SIMKOPDES putus: akun
     * manajer masuk tanpa koperasi, lalu fitur yang butuh koperasi (kas,
     * produk, stok) tampil kosong. Tanpa tautan, tidak ada satu pun cara
     * membuat fitur itu "normal" — datanya memang milik satu koperasi.
     */
    public function tautkan(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // 33 ribu akun manajer tanpa koperasi tidak bisa dirender utuh: satu
        // halaman jadi 6 juta px di ponsel. Daftarnya dibatasi + dicari.
        $dasar = fn () => User::query()
            ->where('peran', Domain::PERAN_MANAJER)
            ->whereNotIn('id', Koperasi::whereNotNull('manajer_id')->select('manajer_id'));

        $jumlahTanpa = $dasar()->count();

        $tanpa = $dasar()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('nama', 'like', '%'.$q.'%')
                ->orWhere('email', 'like', '%'.$q.'%')))
            ->orderBy('nama')->limit(100)->get(['id', 'nama', 'email', 'aktif']);

        return view('admin.tautkan-manajer', [
            'aktif' => 'kop',
            'tanpa' => $tanpa,
            'jumlahTanpa' => $jumlahTanpa,
            'cari' => $q,
            'koperasi' => Koperasi::orderBy('kode')->get(['id', 'kode', 'nama', 'wilayah', 'manajer_id']),
            // Koperasi yang sudah punya manajer: ditampilkan supaya tautannya
            // bisa DILEPAS di sini. Tanpa daftar ini, akun yang sudah tertaut
            // hilang dari halaman dan tak ada jalan memindahkannya. Dibatasi
            // 100 baris + ikut tersaring pencarian, sama seperti daftar di atas.
            'tertaut' => Koperasi::query()
                ->whereNotNull('manajer_id')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                    ->where('kode', 'like', '%'.$q.'%')
                    ->orWhere('nama', 'like', '%'.$q.'%')))
                ->with('manajer:id,nama,email')
                ->orderBy('kode')
                ->limit(100)
                ->get(['id', 'kode', 'nama', 'wilayah', 'manajer_id']),
            'jumlahTertaut' => Koperasi::whereNotNull('manajer_id')->count(),
            'pilihAkun' => (int) $request->query('akun', 0),
        ]);
    }

    /** Lepas tautan manajer dari koperasi (kewenangan pusat). */
    public function tautkan_lepas(Request $request, int $id)
    {
        $k = Koperasi::whereKey($id)->firstOrFail();
        $mj = $k->manajer_id ? User::whereKey($k->manajer_id)->first() : null;

        if (! $mj) {
            return back()->with('pesan', 'Koperasi '.$k->kode.' memang belum punya manajer.');
        }

        DB::connection('hub')->transaction(function () use ($k, $mj) {
            // Dua arah dilepas bersama: `koperasi.manajer_id` dan `users.koperasi_id`.
            User::whereKey($mj->id)->where('koperasi_id', $k->id)->update(['koperasi_id' => null]);
            $k->forceFill(['manajer_id' => null])->save();
        });

        jurnal($request->user()->id, 'KOPERASI', $k->id.':LEPAS-MANAJER',
            'manajer='.$mj->email, $request->ip() ?? '');

        return back()->with('pesan', "Tautan {$mj->nama} dilepas dari {$k->kode} — {$k->nama}. "
            .'Akun itu kini muncul di daftar pilihan dan bisa ditempatkan ke koperasi lain.');
    }

    /** Simpan tautan manajer → koperasi. */
    public function tautkan_simpan(Request $request)
    {
        $d = $request->validate([
            'user_id' => ['required', 'integer'],
            'koperasi_id' => ['required', 'integer'],
        ], [], ['user_id' => 'akun manajer', 'koperasi_id' => 'koperasi']);

        $u = User::whereKey($d['user_id'])->first();
        if (! $u || $u->peran !== Domain::PERAN_MANAJER) {
            return back()->withInput()->with('galat', 'Akun yang dipilih bukan akun manajer.');
        }
        if (! $u->aktif) {
            return back()->withInput()->with('galat', "Akun {$u->nama} nonaktif — aktifkan dulu di halaman Akun.");
        }

        $k = Koperasi::whereKey($d['koperasi_id'])->first();
        if (! $k) {
            return back()->withInput()->with('galat', 'Koperasi yang dipilih tidak ditemukan.');
        }

        // Satu akun = satu koperasi (dipakai `User::koperasiDiampu()` dan
        // `Koperasi::where('manajer_id')`). Pindah koperasi harus eksplisit:
        // lepas dulu di koperasi lama supaya tidak ada data yang berpindah diam-diam.
        $lain = Koperasi::where('manajer_id', $u->id)->where('id', '<>', $k->id)->first();
        if ($lain) {
            return back()->withInput()->with('galat',
                "Akun {$u->nama} sudah menjadi manajer {$lain->kode} — {$lain->nama}. Lepas tautannya di koperasi itu lebih dulu.");
        }

        DB::connection('hub')->transaction(function () use ($k, $u) {
            // Manajer lama kehilangan tautan di kedua arah.
            if ($k->manajer_id && (int) $k->manajer_id !== (int) $u->id) {
                User::whereKey($k->manajer_id)->where('koperasi_id', $k->id)
                    ->update(['koperasi_id' => null]);
            }
            $k->forceFill(['manajer_id' => $u->id])->save();
            // `users.koperasi_id` selama ini kosong untuk semua manajer; diisi di
            // sini supaya kedua arah tautan tidak berbeda arti (dipakai label
            // koperasi pada pengumuman & halaman profil).
            $u->forceFill(['koperasi_id' => $k->id])->save();
        });

        jurnal($request->user()->id, 'KOPERASI', $k->id.':MANAJER',
            'manajer='.$u->email, $request->ip() ?? '');

        return back()->with('pesan', "Akun {$u->nama} kini manajer {$k->kode} — {$k->nama}. "
            .'Fitur koperasi (keuangan, produk, stok) langsung aktif untuk akun itu.');
    }

    /**
     * Tarik data lahan/pembangunan KDKMP dari SIMKOPDES (kewenangan pusat).
     *
     * Menulis ke `koperasi_lahan` — idempoten per `cooperative_asset_id`, jadi
     * menekan tombolnya dua kali tidak menggandakan apa pun.
     */
    public function lahan_tarik(LahanPenarik $penarik)
    {
        try {
            $r = $penarik->tarik();
        } catch (\Throwable $e) {
            return back()->with('galat', 'Tarik data lahan gagal: '.substr($e->getMessage(), 0, 160));
        }

        $pesan = "{$r['baris']} aset lahan tersimpan, {$r['koperasi']} koperasi tertaut.";
        if ($r['gagal']) {
            $pesan .= ' '.count($r['gagal']).' pendamping gagal dibaca.';
        }

        // 'pesan', BUKAN 'sukses': tidak satu pun view/layout membaca
        // `session('sukses')` (yang dirender cuma pesan/galat/rahasia_baru),
        // jadi kunci itu membuat pesan berhasil hilang tanpa jejak — tombolnya
        // bekerja, tapi pengguna tidak pernah tahu. Terukur 2026-10-18:
        // `grep -rn "session('sukses')" resources/` = 0 hasil.
        return back()->with($r['baris'] > 0 ? 'pesan' : 'galat', $pesan);
    }

    /**
     * Tren aktivitas harian dari jurnal audit — dipakai panel ringkas di halaman
     * Jurnal Audit. Analisa lintas modul yang lain (cakupan kerja) tampil di
     * halaman Koperasi & Struktur, tempat angkanya benar-benar dibutuhkan.
     */
    public function audit(Request $request)
    {
        $u = $request->user();
        // Audit tumbuh tanpa batas (ribuan baris) dan hanya 10 yang tampil.
        // Pola lama memuat SELURUH jurnal tiap kali halaman dibuka.
        $qAudit = Audit::query()
            ->leftJoin('users as us', 'us.id', '=', 'audit.aktor')
            ->select('audit.*', 'us.nama as nama_aktor', 'us.email as email_aktor')
            ->when($u->pic() || $u->pantau(), fn ($w) => $w->whereIn('audit.aktor', Analisa::aktorQuery($u)))
            ->orderByDesc('audit.id');

        $halaman = Halaman::buat((clone $qAudit)->count(), $request->query('halaman', 1), self::PER_HALAMAN);

        // Panel ringkas tren di atas jurnal. Angka turunannya tetap dihitung di
        // App\Support\Analisa, bukan di sini.
        return view('admin.audit', ['aktif' => 'audit',
            'catatan' => $halaman->batasi($qAudit)->get(), 'halaman' => $halaman,
            'analisa' => ['tren' => Analisa::tren($u)],
        ]);
    }
}

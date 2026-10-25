<?php

namespace App\Http\Controllers;

use App\Models\PemetaanLahan;
use App\Support\Halaman;
use App\Support\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pemetaan lahan KDKMP — cermin data publik portalkdkmp: desa, luas, potensi,
 * status pembangunan, dan **Babinsa penanggung jawab beserta nomor HP-nya**.
 *
 * Penjaganya `wilayah` (pusat + PIC), sama seperti halaman Koperasi: ini data
 * program lintas wilayah. Isinya TIDAK disaring per wilayah pengguna — sengaja,
 * supaya semua pihak melihat gambaran nasional yang sama seperti di sumbernya.
 */
class PemetaanController extends Controller
{
    /** Baris per halaman. 25 = kelipatan `per_page` sumber, jadi terasa familiar. */
    public const PER_HALAMAN = 25;

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $kunci = (string) $request->query('pembangunan', '');
        $urut = (string) $request->query('urut', 'terbaru');

        // Nilai filter wilayah datang dari daftar yang kita sediakan sendiri
        // (`->provinsi`), bukan dari input bebas — jadi tidak ada nilai asing
        // yang lolos ke query.
        $provinsi = (string) $request->query('provinsi', '');
        $kabupaten = (string) $request->query('kabupaten', '');
        $kecamatan = (string) $request->query('kecamatan', '');
        $desa = (string) $request->query('desa', '');

        $dasar = PemetaanLahan::query()
            ->when($provinsi !== '', fn ($w) => $w->where('provinsi', $provinsi))
            ->when($kabupaten !== '', fn ($w) => $w->where('kabupaten', $kabupaten))
            ->when($kecamatan !== '', fn ($w) => $w->where('kecamatan', $kecamatan))
            ->when($desa !== '', fn ($w) => $w->where('desa', $desa))
            ->when($kunci === 'selesai', fn ($w) => $w->where('pembangunan_100', true))
            ->when($kunci === 'jalan', fn ($w) => $w->where('pembangunan_mulai', true)->where('pembangunan_100', false))
            ->when($kunci === 'belum', fn ($w) => $w->where('pembangunan_mulai', false))
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                // Nomor ikut dicari: orang mencari Babinsa lewat nomornya juga.
                $x->where('nama', 'like', "%{$q}%")
                    ->orWhere('lokasi', 'like', "%{$q}%")
                    ->orWhere('telepon', 'like', "%{$q}%")
                    ->orWhere('potensi', 'like', "%{$q}%");
            }))
            ->when($urut === 'luas', fn ($w) => $w->orderByDesc('luas'))
            ->when($urut === 'nama', fn ($w) => $w->orderBy('nama'))
            ->when($urut === 'terbaru', fn ($w) => $w->orderByDesc('pemetaan_id'));

        // Peran pengawasan memantau lahan menurut wilayahnya SENDIRI: PUSAT
        // (semua), provinsi, atau kabupaten — paling rendah kabupaten. Saringan
        // ini ditambahkan SETELAH filter pengguna, jadi filter tidak bisa
        // memperluas lingkup (semua kondisi dirangkai AND).
        $lingkup = '';
        if ($request->user()?->pantau()) {
            $w = trim((string) $request->user()->wilayah);
            if ($w !== '' && $w !== Wilayah::PUSAT) {
                if (Wilayah::sah($w)) {
                    $dasar->where('provinsi', 'like', Wilayah::normal($w));
                    $lingkup = 'Provinsi '.Wilayah::pendek($w);
                } else {
                    $dasar->where('kabupaten', 'like', $w);
                    $lingkup = 'Kabupaten/Kota '.$w;
                }
            } else {
                $lingkup = 'Nasional (pusat)';
            }
        }

        $halaman = Halaman::buat((clone $dasar)->count(), $request->query('halaman', 1), self::PER_HALAMAN);

        // Pilihan filter: satu query agregat per tingkat, dan HANYA dibangun bila
        // tingkat induknya sudah dipilih. Sebelumnya keempat tingkat dirender
        // selalu, dan tanpa pilihan induk "semua kabupaten/kecamatan/desa se-
        // Indonesia" ikut dikirim: terukur 39 + 495 + 5.518 + 29.838 = 35.890
        // <option> → 2,5 MB HTML untuk satu halaman, dan dropdown 29 ribu opsi
        // tidak bisa dipakai siapa pun. Kini: 39 <option> saat belum disaring.
        $pilihan = [
            'provinsi' => PemetaanLahan::query()->select('provinsi')
                ->where('provinsi', '<>', '')->distinct()->orderBy('provinsi')->pluck('provinsi'),
            'kabupaten' => $provinsi === '' ? collect()
                : PemetaanLahan::query()->select('kabupaten')->where('provinsi', $provinsi)
                    ->where('kabupaten', '<>', '')->distinct()->orderBy('kabupaten')->pluck('kabupaten'),
            'kecamatan' => $kabupaten === '' ? collect()
                : PemetaanLahan::query()->select('kecamatan')->where('kabupaten', $kabupaten)
                    ->where('kecamatan', '<>', '')->distinct()->orderBy('kecamatan')->pluck('kecamatan'),
            'desa' => $kecamatan === '' ? collect()
                : PemetaanLahan::query()->select('desa')->where('kecamatan', $kecamatan)
                    ->where('desa', '<>', '')->distinct()->orderBy('desa')->pluck('desa'),
        ];

        // Ringkasan: angka nasional atau angka hasil saring — mengikuti filter,
        // supaya kartunya menjawab pertanyaan yang sedang ditanyakan.
        $saring = fn () => (clone $dasar);
        $ringkas = [
            'total' => $saring()->count(),
            'selesai' => $saring()->where('pembangunan_100', true)->count(),
            'jalan' => $saring()->where('pembangunan_mulai', true)->where('pembangunan_100', false)->count(),
            'belum' => $saring()->where('pembangunan_mulai', false)->count(),
            // Sengaja TIDAK ada "total luas": kolom `luasLahan` sumber mencampur
            // satuan (858 = m², 2.5 = hektar, terverifikasi pada dua baris),
            // jadi menjumlahkannya menghasilkan angka yang tidak bermakna.
            // Yang bermakna & terverifikasi: berapa titik punya nomor Babinsa.
            'telepon' => $saring()->where('telepon', '<>', '')->count(),
        ];

        return view('pemetaan.index', [
            'aktif' => 'pemetaan',
            // Kosong untuk peran selain TNI; berisi keterangan lingkup untuk TNI.
            'lingkup' => $lingkup,
            'baris' => $halaman->batasi($dasar)->get(),
            'halaman' => $halaman,
            'pilihan' => $pilihan,
            'ringkas' => $ringkas,
            'q' => $q, 'pembangunan' => $kunci, 'urut' => $urut,
            'provinsi' => $provinsi, 'kabupaten' => $kabupaten,
            'kecamatan' => $kecamatan, 'desa' => $desa,
            // Satu view untuk dua rute: /admin/pemetaan (pengelola, boleh
            // "Sinkronkan") dan /pemetaan (manajer, hanya-baca). Rute & hak
            // tombolnya datang dari sini, bukan ditebak view.
            'rute' => $request->route()->getName() === 'admin.pemetaan' ? 'admin.pemetaan' : 'pemetaan',
            'bolehTarik' => $request->user() && ($request->user()->agrinas()
                || $request->user()->pic() || $request->user()->hrd()),
            'manajer' => (bool) $request->user()?->manajerKoperasi(),
        ]);
    }

    /** Tarik ulang dari sumber (kewenangan pusat) — idempoten. */
    public function tarik()
    {
        // Idempoten & lama (±2–4 menit), jadi TIDAK dijalankan di dalam
        // permintaan ini: tombolnya menjalankan perintah artisan di latar
        // belakang, lalu halaman dimuat ulang dengan pesan.
        $sudah = PemetaanLahan::count();
        if ($sudah > 0) {
            return back()->with('pesan', "Data pemetaan sudah ada ({$sudah} baris). Tarik ulang dijalankan lewat: php artisan pemetaan:tarik");
        }

        return back()->with('galat', 'Belum ada data pemetaan. Jalankan: php artisan pemetaan:tarik');
    }
}

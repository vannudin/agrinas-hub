<?php

namespace App\Http\Controllers;

use App\Models\Koperasi;
use App\Models\KoperasiBarang;
use App\Models\KoperasiGerai;
use App\Models\KoperasiJual;
use App\Models\KoperasiJualItem;
use App\Models\KoperasiKaryawan;
use App\Models\KoperasiLahan;
use App\Models\KoperasiSarana;
use App\Models\KoperasiSaranaCentang;
use App\Models\PemetaanLahan;
use App\Models\SaranaIndikator;
use App\Models\User;
use App\Support\Halaman;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Koperasi — satu halaman kerja koperasi, dengan tiga bagian yang saling
 * melengkapi (permintaan Bos 2026-10-18):
 *
 *   1. DATA KOPERASI  — identitas dari titik pemetaan lahan + status operasional
 *                       yang diisi manajer koperasi.
 *   2. SARANA PRASARANA — proses pembangunan (dari titik pemetaan lahan),
 *                       gerai/outlet yang diisi manajer, centang katalog sarana
 *                       program, dan aset/lahan hasil verval (baca saja).
 *   3. POS PENJUALAN  — kasir koperasi: nota baru, stok ikut turun, riwayat,
 *                       rekap harian.
 *
 * Dipisah dari DasborController (yang memuat /admin/koperasi, halaman DAFTAR
 * koperasi) karena keduanya pekerjaan yang berbeda: di sana pengelola menyisir
 * puluhan koperasi, di sini satu koperasi didalami. Menumpangkannya ke
 * DasborController akan mencampur dua lingkup dalam satu berkas.
 *
 * Lingkup akses mengikuti aturan yang sudah berlaku di repo ini lewat
 * `User::koperasiQuery()`: pusat & HR semua, PIC wilayahnya, manajer miliknya
 * sendiri. Koperasi di luar lingkup → 404 (bukan 403 yang membocorkan
 * keberadaan barisnya).
 */
class KoperasiController extends Controller
{
    private const PER_HALAMAN = 20;

    /** Baris per halaman daftar pemilih koperasi (sama dengan daftar lain di Hub). */
    private const PER_KOPERASI = 10;

    /**
     * Koperasi yang sedang dilihat.
     *
     * Manajer: koperasinya sendiri (tanpa perlu ?koperasi=). Peran lain: dari
     * `?koperasi=`, WAJIB berada dalam lingkupnya.
     */
    private function tampil(Request $request): ?Koperasi
    {
        $u = $request->user();

        if ($u->manajerKoperasi()) {
            return Koperasi::where('manajer_id', $u->id)->first();
        }

        $id = (int) $request->query('koperasi', 0);
        if ($id <= 0) {
            return null;
        }

        return Koperasi::whereKey($id)
            ->whereIn('id', $u->koperasiQuery()->select('koperasi.id'))
            ->firstOrFail();
    }

    /** Daftar koperasi untuk pemilih (dipakai saat belum ada yang dipilih). */
    private function pilihan(Request $request)
    {
        $cari = trim((string) $request->query('q', ''));

        return $request->user()->koperasiQuery()
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('kode', 'like', '%'.$cari.'%')
                ->orWhere('nama', 'like', '%'.$cari.'%')
                ->orWhere('wilayah', 'like', '%'.$cari.'%')))
            ->orderBy('kode');
    }

    // ------------------------------------------------------------------ halaman

    /**
     * Padanan titik pemetaan lahan untuk satu koperasi.
     *
     * Dicocokkan lewat NAMA DESA & KECAMATAN, bukan id: `pemetaan_lahan` adalah
     * cermin situs publik tingkat desa, `koperasi` dikelola di Hub, dan tidak
     * ada kolom yang menautkan keduanya. Sumber nama dicoba BERTINGKAT karena
     * satu saja tidak cukup:
     *   1. `koperasi.wilayah` — bentuk "Desa X, Kec. Y, Kab. Z";
     *   2. potongan nama koperasi SIMKOPDES ("… MERAH PUTIH <desa> KECAMATAN <kec>")
     *      untuk kasus wilayah yang tidak memuat kecamatan.
     * Akurasi terukur: 11/13 dari percobaan pertama, 13/13 setelah bertingkat.
     *
     * Kecamatan dipakai sebagai penyaring KETAT hanya bila ada; tanpa kecamatan,
     * nama desa saja (mis. "Telaga" ada 30 kali lintas provinsi) — itu sebabnya
     * provinsi ikut disaring supaya tidak mencampur desa bernama sama.
     */
    private function padananLahan(Koperasi $kop): array
    {
        $kandidat = [PemetaanLahan::pecahWilayah((string) $kop->wilayah)];
        $kandidat[] = PemetaanLahan::pecahWilayah((string) $kop->nama);

        $provinsi = strtoupper(trim((string) $kop->provinsi));

        foreach ($kandidat as $c) {
            if (($c['desa'] ?? '') === '') {
                continue;
            }

            $q = PemetaanLahan::query()
                ->whereRaw('UPPER(desa) LIKE ?', ['%'.strtoupper($c['desa']).'%'])
                ->when(($c['kecamatan'] ?? '') !== '',
                    fn ($w) => $w->whereRaw('UPPER(kecamatan) LIKE ?', ['%'.strtoupper($c['kecamatan']).'%']))
                ->when($provinsi !== '',
                    fn ($w) => $w->whereRaw('UPPER(provinsi) LIKE ?', ['%'.$provinsi.'%']));

            $titik = $q->orderByDesc('luas')->limit(50)->get();

            if ($titik->isNotEmpty()) {
                return [
                    'desa' => $c['desa'],
                    'kecamatan' => $c['kecamatan'] ?? '',
                    'cocok' => $titik->count(),
                    'luas' => (float) $titik->sum('luas'),
                    'selesai' => $titik->where('pembangunan_100', true)->count(),
                    'mulai' => $titik->where('pembangunan_mulai', true)->where('pembangunan_100', false)->count(),
                    'titik' => $titik->take(10)->values(),
                    // Padanan yang TUNGGAL bisa ditautkan sekali tekan; kalau
                    // lebih dari satu, menebak desa yang benar adalah pekerjaan
                    // manusia — tombolnya tidak ditawarkan.
                    'satu' => $titik->count() === 1 ? $titik->first() : null,
                ];
            }
        }

        return [];
    }

    /** Data koperasi + status: sudah operasional atau belum, dan kenapa. */
    public function index(Request $request)
    {
        $kop = $this->tampil($request);

        if ($kop === null) {
            return view('koperasi.index', array_merge($this->kerangka($request, $kop === null), [
                'koperasi' => null,
                'sarana' => null,
                'lahanAwal' => $this->lahanAwal(null),
                'bagian' => 'data',
                // Manajer tidak butuh angka semua koperasi — ia mengurus satu.
                'ringkasSemua' => $request->user()->manajerKoperasi()
                    ? null : $this->ringkasSemua($request),
            ]));
        }

        $sarana = KoperasiSarana::where('koperasi_id', $kop->id)->first();
        $gerai = KoperasiGerai::where('koperasi_id', $kop->id)->orderBy('nama')->get();
        $lahan = KoperasiLahan::where('koperasi_id', $kop->id)->orderByDesc('luas')->get();

        // Identitas & proses pembangunan dibaca dari TITIK PEMETAAN LAHAN, bukan
        // dari salinan di `koperasi`: sumbernya bisa diperbarui, dan halaman ini
        // yang menjawab "koperasi ini di mana & sudah sejauh apa".
        $titik = $kop->titikLahan;
        $centang = KoperasiSaranaCentang::where('koperasi_id', $kop->id)
            ->get()->keyBy('sarana_indikator_id');

        // Ringkasan yang benar-benar menjawab pertanyaan pengelola, bukan hiasan.
        $ringkas = [
            'gerai' => $gerai->count(),
            'barang' => KoperasiBarang::where('koperasi_id', $kop->id)->count(),
            'menipis' => KoperasiBarang::where('koperasi_id', $kop->id)
                ->where('stok_min', '>', 0)->whereColumn('stok', '<=', 'stok_min')->count(),
            'nilai_stok' => (int) KoperasiBarang::where('koperasi_id', $kop->id)
                ->selectRaw('COALESCE(SUM(stok * harga), 0) n')->value('n'),
            'lahan' => $lahan->count(),
            'luas' => (float) $lahan->sum('luas'),
            'tersedia' => $centang->count(),
            'rusak' => $centang->where('kondisi', 'RUSAK')->count(),
            'persen' => $titik === null ? 0 : ($titik->pembangunan_100 ? 100 : ($titik->pembangunan_mulai ? 50 : 0)),
            'penjualan_hari' => (int) KoperasiJual::where('koperasi_id', $kop->id)
                ->where('tanggal', now('Asia/Jakarta')->toDateString())
                ->where('status', '<>', KoperasiJual::BATAL)->sum('total'),
            'nota_hari' => KoperasiJual::where('koperasi_id', $kop->id)
                ->where('tanggal', now('Asia/Jakarta')->toDateString())
                ->where('status', '<>', KoperasiJual::BATAL)->count(),
        ];

        // Katalog indikator dikelompokkan menurut jenis: itu yang dibaca
        // pengelola saat menilai "sarana apa yang seharusnya ada di sini".
        $katalog = SaranaIndikator::orderBy('jenis_pengembangan')->orderBy('jenis_konstruksi')
            ->get()->groupBy(fn ($r) => $r->jenis_pengembangan.' · '.$r->jenis_konstruksi);

        return view('koperasi.index', array_merge($this->kerangka($request, $kop === null), [
            'koperasi' => $kop,
            'sarana' => $sarana,
            'gerai' => $gerai,
            'lahan' => $lahan,
            'titik' => $titik,
            'centang' => $centang,
            'ringkas' => $ringkas,
            'katalog' => $katalog,
            'padanan' => $this->padananLahan($kop),
            'lahanAwal' => $this->lahanAwal($kop),
            'bagian' => 'data',
        ]));
    }

    // ------------------------------------------------ data koperasi (CRUD manajer)

    /**
     * Titik pemetaan lahan untuk Select2 di /kop (AJAX, bukan 38.861 <option>).
     *
     * Manajer yang belum punya koperasi MEMILIH desanya dari sini, jadi nama
     * desa/kecamatan tidak diketik ulang — sumber salah tulis pertama. Daftar
     * ini cermin situs publik tingkat desa, bukan data rahasia.
     */
    public function cariLahan(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        // Di bawah 3 huruf hasilnya ribuan baris dan tak satu pun relevan.
        if (mb_strlen($q) < 3) {
            return response()->json(['results' => []]);
        }

        $suka = '%'.strtoupper($q).'%';

        $baris = PemetaanLahan::query()
            ->where(fn ($w) => $w
                ->whereRaw('UPPER(desa) LIKE ?', [$suka])
                ->orWhereRaw('UPPER(kecamatan) LIKE ?', [$suka])
                ->orWhereRaw('UPPER(kabupaten) LIKE ?', [$suka])
                ->orWhereRaw('UPPER(provinsi) LIKE ?', [$suka]))
            ->orderBy('provinsi')->orderBy('kabupaten')->orderBy('kecamatan')->orderBy('desa')
            ->limit(30)
            ->get(['id', 'desa', 'kecamatan', 'kabupaten', 'provinsi', 'luas', 'latitude', 'longitude']);

        // Peta titik yang sudah dipegang koperasi lain: dipakai untuk menandai
        // opsinya, bukan untuk menyembunyikannya — manajer perlu tahu titik itu
        // ada dan siapa pemegangnya.
        $dipakai = Koperasi::whereIn('pemetaan_lahan_id', $baris->pluck('id')->all())
            ->pluck('nama', 'pemetaan_lahan_id');

        return response()->json(['results' => $baris->map(fn (PemetaanLahan $l) => [
            'id' => $l->id,
            'text' => $this->teksLahan($l).($dipakai->has($l->id) ? ' · sudah tertaut: '.$dipakai[$l->id] : ''),
            // Titik yang sudah dipegang koperasi lain tidak bisa dipilih; kalau
            // tetap bisa, menyimpannya hanya berakhir dengan galat. Manajer yang
            // pindah lokasi melepas tautannya lebih dulu, bukan menimpa titik orang.
            'disabled' => $dipakai->has($l->id),
            // Ikut dikirim supaya formulir terisi sendiri saat titik dipilih;
            // mengetik ulang empat kolom hanya menambah peluang salah.
            'wilayah' => "Desa {$l->desa}, Kec. {$l->kecamatan}, {$l->kabupaten}",
            'provinsi' => (string) $l->provinsi,
            'nama' => $l->namaKoperasi(),
            'peta' => $l->adaTitik() ? $l->tautanPeta() : '',
            'koordinat' => $l->adaTitik() ? koordinat_teks((float) $l->latitude, (float) $l->longitude) : '',
            'luas' => $l->luas ? rtrim(rtrim(number_format($l->luas, 2, ',', '.'), '0'), ',').' ha' : '',
        ])]);
    }

    /**
     * Titik lahan yang harus sudah terpilih di Select2 saat formulir dibuka.
     *
     * `old()` didahulukan: bila validasi gagal, formulir kembali dengan isian
     * sebelumnya — kalau Select2 ikut direset, pilihan desa hilang dan manajer
     * harus mencari ulang. Teks opsi dibangun di sini karena Select2 tidak bisa
     * menampilkan opsi hanya dari id.
     */
    private function lahanAwal(?Koperasi $kop): ?array
    {
        $id = (int) old('pemetaan_lahan_id', $kop->pemetaan_lahan_id ?? 0);
        $l = $id > 0 ? PemetaanLahan::find($id) : null;

        return $l ? [
            'id' => $l->id,
            'text' => $this->teksLahan($l),
            'nama' => $l->namaKoperasi(),
            'peta' => $l->adaTitik() ? $l->tautanPeta() : '',
            'koordinat' => $l->adaTitik() ? koordinat_teks((float) $l->latitude, (float) $l->longitude) : '',
        ] : null;
    }

    /** Satu bentuk teks titik lahan — dipakai opsi Select2 dan opsi awal. */
    private function teksLahan(PemetaanLahan $l): string
    {
        return "Desa {$l->desa}, Kec. {$l->kecamatan} — {$l->kabupaten}, {$l->provinsi}";
    }

    /**
     * Simpan koperasi baru: manajer memilih satu titik pemetaan lahan.
     *
     * Jalannya selama SIMKOPDES tidak bisa disinkronkan: akun manajer masuk
     * tanpa koperasi, sehingga kas/produk/stok kosong. Hasilnya berstatus BARU
     * supaya pengelola memeriksanya terhadap berkas PKWT.
     */
    public function simpan(Request $request)
    {
        $u = $request->user();

        if ($k = $u->koperasiDiampu()) {
            return redirect()->route('kop')
                ->with('pesan', "Koperasi Anda sudah terdaftar: {$k->kode} — {$k->nama}.");
        }

        $d = $this->validasiData($request) + $request->validate([
            'pkwt' => ['accepted'],
        ], [], ['pkwt' => 'pernyataan kesesuaian PKWT']);

        $lahan = PemetaanLahan::find((int) $d['pemetaan_lahan_id']);

        if (! $lahan) {
            return back()->withInput()->with('galat',
                'Titik pemetaan lahan yang dipilih tidak ada. Pilih ulang dari daftar.');
        }

        // Satu titik = satu koperasi. Dulu ini terjaga TIDAK SENGAJA oleh kode
        // `LAHAN-<id>` yang unik, sehingga titik yang tautannya dilepas tetap
        // tidak bisa dipakai lagi. Sekarang aturannya diperiksa langsung.
        if ($this->lahanDipakaiLain($lahan->id, 0)) {
            return back()->withInput()->with('galat',
                'Titik pemetaan lahan itu sudah dipakai koperasi lain. Pilih titik lain, atau minta pengelola memeriksa daftar koperasi.');
        }

        try {
            $k = DB::connection('hub')->transaction(function () use ($u, $d, $lahan) {
                $k = Koperasi::create([
                    // `kode` unik & wajib. Isian mandiri tidak punya kode wilayah,
                    // jadi dipakai penanda asalnya: titik lahan yang dipilih.
                    'kode' => $this->kodeLahan($lahan->id),
                    // Nama diturunkan server dari desa, bukan dari isian formulir:
                    // kolomnya read-only, dan klien tidak boleh menentukan identitas.
                    'nama' => $lahan->namaKoperasi(),
                    'alamat' => trim((string) ($d['alamat'] ?? '')),
                    // Wilayah DITURUNKAN dari titik, bukan dari kiriman formulir:
                    // isian klien bisa cuma "KAB. GARUT" (JavaScript mati) dan
                    // kolom Wilayah di /admin/koperasi jadi tidak lengkap.
                    'wilayah' => $this->wilayahLahan($lahan),
                    'provinsi' => mb_strtoupper(trim((string) (($d['provinsi'] ?? '') ?: $lahan->provinsi))),
                    'no_rekening' => trim((string) ($d['no_rekening'] ?? '')),
                    'pemetaan_lahan_id' => $lahan->id,
                    'status' => 'BARU',
                    'versi' => 1,
                    'diperbarui' => now(),
                ]);
                $k->forceFill(['manajer_id' => $u->id])->save();
                $u->forceFill(['koperasi_id' => $k->id])->save();

                // Titik yang dipilih manajer sudah membawa desa, luas, dan
                // koordinat — catat sebagai bidang koperasi, kalau tidak
                // /admin/koperasi menampilkan "belum ada data lahan" untuk
                // koperasi yang sebenarnya sudah bertitik.
                $this->sinkronLahan($k, $lahan);

                return $k;
            });
        } catch (UniqueConstraintViolationException $e) {
            return back()->withInput()->with('galat',
                'Titik pemetaan lahan itu sudah dipakai koperasi lain. Pilih titik lain, atau minta pengelola memeriksa daftar koperasi.');
        }

        jurnal($u->id, 'KOPERASI', $k->id.':ISI', 'mandiri="'.$k->nama.'" lahan='.$lahan->id, $request->ip() ?? '');

        return redirect()->route('kop')
            ->with('pesan', "Data koperasi tersimpan: {$k->nama}. Status BARU — menunggu pemeriksaan pengelola terhadap PKWT.");
    }

    /**
     * Ubah identitas koperasi yang diampu sendiri.
     *
     * Status & kode tidak ikut diubah: keduanya milik pengelola (kode) dan hasil
     * pemeriksaan (status). Perubahan dicatat di jurnal supaya pengelola bisa
     * menelusuri siapa mengubah apa.
     */
    public function ubah(Request $request, int $id)
    {
        $kop = $this->milikSendiri($request, $id);
        $d = $this->validasiData($request, wajibLahan: false);

        // Titik lahan TIDAK wajib saat mengubah: koperasi lama (sebelum kolom
        // `pemetaan_lahan_id` ada) tetap harus bisa membetulkan nama/alamat
        // tanpa dipaksa memilih desa dulu. Bila diisi, tautannya diganti.
        $idLahan = (int) ($d['pemetaan_lahan_id'] ?? 0);
        $lahan = $idLahan > 0 ? PemetaanLahan::find($idLahan) : null;

        if ($idLahan > 0 && ! $lahan) {
            return back()->withInput()->with('galat',
                'Titik pemetaan lahan yang dipilih tidak ada. Pilih ulang dari daftar.');
        }

        if ($lahan && $this->lahanDipakaiLain($lahan->id, $kop->id)) {
            return back()->withInput()->with('galat', 'Titik '.$this->teksLahan($lahan)
                .' sudah tertaut ke koperasi lain. Titik itu harus dilepas lebih dulu.');
        }

        $isi = [
            'nama' => trim((string) $d['nama']),
            'alamat' => trim((string) ($d['alamat'] ?? '')),
            'wilayah' => trim((string) ($d['wilayah'] ?? '')),
            'provinsi' => mb_strtoupper(trim((string) (($d['provinsi'] ?? '') ?: ($lahan->provinsi ?? '')))),
            'no_rekening' => trim((string) ($d['no_rekening'] ?? '')),
            'diperbarui' => now(),
        ];

        if ($lahan) {
            $isi['pemetaan_lahan_id'] = $lahan->id;
            // Memilih desa berarti namanya ikut diturunkan dari desa itu — sama
            // dengan saat menambah. Koperasi lama tanpa titik lahan tetap memakai
            // nama yang dikirim formulir.
            $isi['nama'] = $lahan->namaKoperasi();
            // Wilayah pun diturunkan dari titik, bukan dari kiriman formulir.
            $isi['wilayah'] = $this->wilayahLahan($lahan);
        }

        $kop->fill($isi)->save();
        $this->sinkronLahan($kop, $lahan);

        jurnal($request->user()->id, 'KOPERASI', $kop->id.':UBAH',
            'nama="'.$kop->nama.'" lahan='.($lahan->id ?? $kop->pemetaan_lahan_id ?? 0), $request->ip() ?? '');

        return redirect()->route('kop')->with('pesan', "Data koperasi diperbarui: {$kop->nama}.");
    }

    /**
     * Tautkan koperasi yang diampu sendiri ke satu titik pemetaan lahan.
     *
     * Dipakai tombol "Tautkan titik ini" di bagian Proses Pembangunan: halaman
     * sudah MENEMUKAN titiknya lewat padanan desa, tapi tautan hanya ditulis
     * bila manajer menekan tombol — permintaan GET tidak boleh mengubah data.
     */
    public function taut(Request $request, int $id)
    {
        $kop = $this->milikSendiri($request, $id);
        $lahan = PemetaanLahan::find((int) $request->input('pemetaan_lahan_id'));

        if (! $lahan) {
            return back()->with('galat',
                'Titik pemetaan lahan yang dipilih tidak ada. Pilih ulang lewat Ubah Data.');
        }

        if ($this->lahanDipakaiLain($lahan->id, $kop->id)) {
            return back()->with('galat', 'Titik '.$this->teksLahan($lahan)
                .' sudah tertaut ke koperasi lain. Lepas tautan koperasi itu lebih dulu.');
        }

        $kop->forceFill([
            'pemetaan_lahan_id' => $lahan->id,
            // Nama TIDAK ditimpa: koperasi ini sudah punya nama resmi dari
            // pengelola, yang ditautkan hanya titiknya.
            'wilayah' => $this->wilayahLahan($lahan),
            'provinsi' => mb_strtoupper(trim((string) ($lahan->provinsi ?: $kop->provinsi))),
            'diperbarui' => now(),
        ])->save();

        $this->sinkronLahan($kop, $lahan);

        jurnal($request->user()->id, 'KOPERASI', $kop->id.':TAUT',
            'nama="'.$kop->nama.'" lahan='.$lahan->id, $request->ip() ?? '');

        return redirect()->route('kop')->with('pesan',
            'Koperasi tertaut ke titik '.$this->teksLahan($lahan).'. Proses pembangunan kini dibaca dari titik itu.');
    }

    /**
     * Lepas tautan titik pemetaan lahan dari koperasi yang diampu sendiri.
     *
     * Dipakai manajer yang pindah lokasi: setelah dilepas, titik lamanya bebas
     * dipakai koperasi lain dan desa baru dipilih lewat Ubah Data. Nama & kode
     * koperasi tidak diubah — identitasnya urusan pengelola.
     */
    public function lepas(Request $request, int $id)
    {
        $kop = $this->milikSendiri($request, $id);
        $lama = (int) ($kop->pemetaan_lahan_id ?? 0);

        if ($lama === 0) {
            return redirect()->route('kop')
                ->with('pesan', 'Koperasi ini memang belum tertaut ke titik pemetaan lahan.');
        }

        $kop->forceFill(['pemetaan_lahan_id' => null, 'diperbarui' => now()])->save();
        $this->sinkronLahan($kop, null);

        jurnal($request->user()->id, 'KOPERASI', $kop->id.':LEPAS', 'lahan='.$lama, $request->ip() ?? '');

        return redirect()->route('kop')->with('pesan',
            'Tautan titik pemetaan lahan dilepas. Pilih desa baru lewat Ubah Data bila koperasi ini pindah lokasi.');
    }

    /** Titik lahan ini sudah dipakai koperasi lain? (satu titik = satu koperasi) */
    private function lahanDipakaiLain(int $lahanId, int $kecuali): bool
    {
        return Koperasi::where('pemetaan_lahan_id', $lahanId)->where('id', '<>', $kecuali)->exists();
    }

    /** Bentuk wilayah yang sama dengan hasil `pecahWilayah()` — satu ejaan saja. */
    private function wilayahLahan(PemetaanLahan $l): string
    {
        return "Desa {$l->desa}, Kec. {$l->kecamatan}, {$l->kabupaten}";
    }

    /**
     * Samakan baris lahan koperasi dengan titik pemetaan yang sedang tertaut.
     *
     * Isian manual manajer sudah membawa titik lahan; tanpa baris `koperasi_lahan`
     * kolom Lahan di /admin/koperasi berbunyi "belum ada data" dan koperasi itu
     * ikut terhitung di saringan "Belum ada data lahan" — padahal titiknya ada.
     * Satu titik = satu bidang, jadi barisnya dibuat/diperbarui, bukan ditambah.
     *
     * Baris hasil verval SIMKOPDES (`asset_simkopdes_id` terisi) TIDAK disentuh:
     * itu sumber yang lebih lengkap (status aset, kepemilikan, surveyor), dan
     * menimpanya dengan data titik akan menghapus hasil verifikasi.
     */
    private function sinkronLahan(Koperasi $kop, ?PemetaanLahan $lahan): void
    {
        $milikSendiri = KoperasiLahan::where('koperasi_id', $kop->id)
            ->whereNull('asset_simkopdes_id');

        if (KoperasiLahan::where('koperasi_id', $kop->id)->whereNotNull('asset_simkopdes_id')->exists()) {
            return;
        }

        if ($lahan === null) {
            // Tautan dilepas: bidang itu bukan milik koperasi ini lagi.
            $milikSendiri->delete();

            return;
        }

        $isi = [
            'nama' => (string) $lahan->nama,
            'nama_koperasi' => $lahan->namaKoperasi(),
            // `kontak` sengaja kosong: `pemetaan_lahan.telepon` adalah nomor
            // Babinsa, bukan kontak koperasi — menyalinnya ke sini membuat kolom
            // "Kontak koperasi" berbohong.
            'kontak' => '',
            'luas' => (float) $lahan->luas,
            // Yang diketahui dari titik: proses pembangunan, bukan status verval.
            'status' => $lahan->statusPembangunan(),
            'pemilik_terdaftar' => (string) $lahan->nama,
            'alamat' => $this->wilayahLahan($lahan),
            'latitude' => $lahan->adaTitik() ? (float) $lahan->latitude : null,
            'longitude' => $lahan->adaTitik() ? (float) $lahan->longitude : null,
            'ditarik' => now(),
        ];

        $baris = $milikSendiri->first();
        $baris ? $baris->fill($isi)->save()
               : KoperasiLahan::create($isi + ['koperasi_id' => $kop->id, 'koperasi_simkopdes_id' => null]);
    }

    /**
     * Kode koperasi mandiri: `LAHAN-<id titik>`.
     *
     * Titik yang sudah dilepas bisa dipakai lagi, sedangkan koperasi lamanya
     * masih memegang kode `LAHAN-<id yang sama>` — tanpa akhiran, pembuatan
     * koperasi berikutnya gagal karena `kode` unik.
     */
    private function kodeLahan(int $lahanId): string
    {
        $kode = 'LAHAN-'.$lahanId;

        for ($n = 2; Koperasi::where('kode', $kode)->exists() && $n < 100; $n++) {
            $kode = 'LAHAN-'.$lahanId.'-'.$n;
        }

        return $kode;
    }

    /**
     * Hapus koperasi yang diampu sendiri — HANYA bila belum punya data apa pun.
     *
     * Hub tidak menegakkan foreign key, jadi menghapus induk beranak akan
     * meninggalkan baris yatim yang tak bisa dibaca siapa pun. Karena itu yang
     * diperiksa bukan satu tabel, melainkan SELURUH tabel yang punya kolom
     * `koperasi_id` (dibaca dari skema, jadi tabel baru ikut terjaga).
     */
    public function hapus(Request $request, int $id)
    {
        $kop = $this->milikSendiri($request, $id);

        $pakai = $this->tabelYangMemakai($kop);

        if ($pakai !== []) {
            return back()->with('galat', 'Koperasi '.$kop->kode
                .' belum bisa dihapus — sudah punya data: '.implode(', ', $pakai)
                .'. Lepas/hapus data itu lebih dulu.');
        }

        DB::connection('hub')->transaction(function () use ($kop) {
            User::where('koperasi_id', $kop->id)->update(['koperasi_id' => null]);
            // Bidang hasil sinkron titik pemetaan lahir bersama koperasi ini,
            // jadi ikut mati bersamanya — bukan "data" yang menghalangi hapus.
            KoperasiLahan::where('koperasi_id', $kop->id)->whereNull('asset_simkopdes_id')->delete();
            $kop->delete();
        });

        jurnal($request->user()->id, 'KOPERASI', $kop->id.':HAPUS',
            'kode='.$kop->kode, $request->ip() ?? '');

        return redirect()->route('kop')->with('pesan',
            'Koperasi '.$kop->kode.' dihapus. Akun Anda belum tertaut koperasi — pilih titik pemetaan lahan untuk membuatnya lagi.');
    }

    /** Nama tabel yang masih memakai koperasi ini (tanpa `users`, yang dilepas saat hapus). */
    private function tabelYangMemakai(Koperasi $kop): array
    {
        $hub = DB::connection('hub');

        $tabel = collect($hub->select(
            "select name from sqlite_master where type='table' and name not like 'sqlite_%'"))
            ->pluck('name')
            ->reject(fn ($t) => in_array($t, ['koperasi', 'users'], true));

        $pakai = [];
        foreach ($tabel as $t) {
            $adaKolom = collect($hub->select('pragma table_info("'.$t.'")'))
                ->contains(fn ($k) => $k->name === 'koperasi_id');

            if (! $adaKolom) {
                continue;
            }

            $q = $hub->table($t)->where('koperasi_id', $kop->id);

            // Baris lahan hasil sinkron titik pemetaan bukan data pengelola —
            // ia ikut terhapus bersama koperasinya (lihat hapus()).
            if ($t === 'koperasi_lahan') {
                $q->whereNotNull('asset_simkopdes_id');
            }

            $n = $q->count();

            if ($n > 0) {
                $pakai[] = $t.' ('.$n.')';
            }
        }

        return $pakai;
    }

    /** Koperasi milik manajer yang sedang masuk; selain itu 404. */
    private function milikSendiri(Request $request, int $id): Koperasi
    {
        return Koperasi::whereKey($id)
            ->where('manajer_id', $request->user()->id)
            ->firstOrFail();
    }

    /** Aturan isian identitas — dipakai `simpan` DAN `ubah`, satu sumber. */
    private function validasiData(Request $request, bool $wajibLahan = true): array
    {
        return $request->validate([
            'pemetaan_lahan_id' => $wajibLahan ? ['required', 'integer'] : ['nullable', 'integer'],
            'nama' => ['required', 'string', 'min:4', 'max:160'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'wilayah' => ['nullable', 'string', 'max:120'],
            'provinsi' => ['nullable', 'string', 'max:80'],
            'no_rekening' => ['nullable', 'string', 'max:60'],
        ], [], [
            'pemetaan_lahan_id' => 'desa pada pemetaan lahan',
            'nama' => 'nama koperasi', 'alamat' => 'alamat koperasi',
            'wilayah' => 'wilayah', 'provinsi' => 'provinsi',
            'no_rekening' => 'nomor rekening',
        ]);
    }

    /** Sarana prasarana: katalog patokan + progres + aset. */
    public function sarana(Request $request)
    {
        $kop = $this->tampil($request);

        if ($kop === null) {
            return view('koperasi.index', array_merge($this->kerangka($request, $kop === null), [
                'koperasi' => null, 'sarana' => null, 'bagian' => 'sarana',
                // Wajib: view yang sama merender formulir "Tambah Data Koperasi"
                // (Select2 pemetaan lahan); tanpa ini halaman 500 begitu dibuka
                // manajer yang belum punya koperasi.
                'lahanAwal' => $this->lahanAwal(null),
            ]));
        }

        return view('koperasi.sarana', array_merge($this->kerangka($request, $kop === null), [
            'koperasi' => $kop,
            'sarana' => KoperasiSarana::where('koperasi_id', $kop->id)->first(),
            'gerai' => KoperasiGerai::where('koperasi_id', $kop->id)->orderBy('nama')->get(),
            'lahan' => KoperasiLahan::where('koperasi_id', $kop->id)->orderByDesc('luas')->get(),
            // Proses pembangunan dibaca dari titik pemetaan lahan yang dipilih
            // manajer — satu sumber, sama dengan halaman Data Koperasi.
            'titik' => $kop->titikLahan,
            // Centang katalog per indikator, berkunci id indikator.
            'centang' => KoperasiSaranaCentang::where('koperasi_id', $kop->id)
                ->get()->keyBy('sarana_indikator_id'),
            'katalog' => SaranaIndikator::orderBy('jenis_pengembangan')->orderBy('jenis_konstruksi')
                ->get()->groupBy(fn ($r) => $r->jenis_pengembangan.' · '.$r->jenis_konstruksi),
            // Padanan pemetaan lahan ikut ditampilkan di sini: halaman Sarana yang
            // menjawab "aset & pembangunannya bagaimana", dan titik pemetaan
            // desa adalah bagian dari jawaban itu.
            'padanan' => $this->padananLahan($kop),
            'bagian' => 'sarana',
        ]));
    }

    /** Kasir POS: katalog barang siap jual + riwayat nota. */
    public function pos(Request $request)
    {
        $kop = $this->tampil($request);

        if ($kop === null) {
            return view('koperasi.index', array_merge($this->kerangka($request, $kop === null), [
                'koperasi' => null, 'sarana' => null, 'bagian' => 'pos',
                'lahanAwal' => $this->lahanAwal(null),
            ]));
        }

        $cari = trim((string) $request->query('q', ''));
        $barang = KoperasiBarang::where('koperasi_id', $kop->id)
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('nama', 'like', '%'.$cari.'%')
                ->orWhere('kode', 'like', '%'.$cari.'%')
                ->orWhere('barcode', 'like', '%'.$cari.'%')))
            ->orderBy('nama')->limit(300)->get();

        $qNota = KoperasiJual::where('koperasi_id', $kop->id)
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('nomor', 'like', '%'.$cari.'%')
                ->orWhere('pembeli', 'like', '%'.$cari.'%')))
            ->orderByDesc('id');
        $halaman = Halaman::buat((clone $qNota)->count(), $request->query('halaman', 1), self::PER_HALAMAN);

        $hari = now('Asia/Jakarta')->toDateString();
        $nota = $halaman->batasi($qNota)->get();

        return view('koperasi.pos', array_merge($this->kerangka($request, $kop === null), [
            'koperasi' => $kop,
            'barang' => $barang,
            'nota' => $nota,
            'halaman' => $halaman,
            'cari' => $cari,
            'bayar' => KoperasiJual::BAYAR,
            // Item diambil sekali untuk nota yang tampil, bukan per baris.
            'itemPeta' => KoperasiJualItem::whereIn('koperasi_jual_id', $nota->pluck('id')->all())
                ->get()->groupBy('koperasi_jual_id'),
            'rekap' => $this->rekap($kop),
            // Nama barang bonus untuk promo GRATIS — SATU kueri untuk seluruh
            // daftar, bukan satu kueri per baris (daftar kasir sampai 300 barang).
            'bonusNama' => KoperasiBarang::where('koperasi_id', $kop->id)
                ->whereIn('id', $barang->pluck('promo_bonus_id')->filter()->unique()->all())
                ->pluck('nama', 'id'),
            'bagian' => 'pos',
        ]));
    }

    // -------------------------------------------------------------------- aksi

    // --------------------------------------------- sarana & gerai (isi manajer)

    /**
     * Koperasi yang boleh DITULIS pemanggil.
     *
     * Semua aksi di bawah (gerai, status operasional, centang katalog) milik
     * manajer pengampu. Peran lain hanya membaca halaman; menulis = 403, bukan
     * 404 — koperasinya memang terlihat olehnya, aksinya yang tidak.
     */
    private function wajibTulis(Request $request): Koperasi
    {
        // Peran dulu, baru koperasi: peran yang memang tidak berhak menulis
        // dijawab 403 (bukan 404) — koperasinya terlihat olehnya, aksinya tidak.
        if (! $request->user()->manajerKoperasi()) {
            abort(403);
        }

        $kop = $this->tampil($request);
        if ($kop === null) {
            abort(404);
        }

        return $kop;
    }

    /**
     * Kembali ke halaman modul yang sedang dibuka.
     *
     * Nilai `kembali` dipetakan ke RUTE, bukan dipakai sebagai URL: menerima
     * alamat bebas dari formulir berarti membuka pengalihan ke mana saja.
     */
    private function kembaliKe(Request $request, Koperasi $kop)
    {
        return redirect()->route(
            $request->input('kembali') === 'sarana' ? 'kop.sarana' : 'kop',
            ['koperasi' => $kop->id]
        );
    }

    /** Aturan isian gerai — satu tempat untuk tambah & ubah. */
    private function validasiGerai(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'min:2', 'max:120'],
            'jenis' => ['nullable', 'string', 'max:60'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
        ], [], ['nama' => 'nama gerai']);
    }

    /**
     * Angka SELURUH koperasi dalam lingkup pengguna, untuk halaman /kop peran
     * non-manajer (permintaan Bos 2026-10-25).
     *
     * Dihitung di sini, bukan di view: "operasional" dan "nilai aset" harus
     * punya SATU definisi. Koperasi yang belum punya baris `koperasi_sarana`
     * dihitung BELUM — kalau dilewati, jumlah status tidak sama dengan jumlah
     * koperasi dan angkanya jadi tak bisa dicocokkan pembaca.
     */
    private function ringkasSemua(Request $request): array
    {
        $ids = $request->user()->koperasiQuery()->pluck('koperasi.id')->all();

        $ops = ['SUDAH' => 0, 'SEBAGIAN' => 0, 'BELUM' => 0];
        $terisi = 0;
        foreach (DB::table('koperasi_sarana')->whereIn('koperasi_id', $ids)
            ->selectRaw('operasional, COUNT(*) n')->groupBy('operasional')->get() as $r) {
            $terisi += (int) $r->n;
            $k = strtoupper((string) $r->operasional);
            $ops[array_key_exists($k, $ops) ? $k : 'BELUM'] += (int) $r->n;
        }
        $ops['BELUM'] += max(0, count($ids) - $terisi);

        $sarana = ['BAIK' => 0, 'RUSAK' => 0];
        foreach (DB::table('koperasi_sarana_centang')->whereIn('koperasi_id', $ids)
            ->selectRaw('kondisi, COUNT(*) n')->groupBy('kondisi')->get() as $r) {
            $sarana[strtoupper((string) $r->kondisi) === 'RUSAK' ? 'RUSAK' : 'BAIK'] += (int) $r->n;
        }

        return [
            'koperasi' => count($ids),
            'operasional' => $ops,
            'nilai_stok' => (int) KoperasiBarang::whereIn('koperasi_id', $ids)
                ->selectRaw('COALESCE(SUM(stok * harga), 0) n')->value('n'),
            'barang' => (int) KoperasiBarang::whereIn('koperasi_id', $ids)->count(),
            'sarana' => $sarana,
            'indikator' => (int) SaranaIndikator::count(),
            'gerai' => (int) KoperasiGerai::whereIn('koperasi_id', $ids)->count(),
            'karyawan' => (int) KoperasiKaryawan::whereIn('koperasi_id', $ids)->count(),
        ];
    }

    // ------------------------------------------------------------- karyawan

    /** Menu Karyawan: manajer mengisi, peran lain membaca dalam lingkupnya. */
    public function karyawan(Request $request)
    {
        $kop = $this->tampil($request);
        if ($kop === null) {
            return redirect()->route('kop');
        }

        return view('koperasi.karyawan', array_merge($this->kerangka($request, false), [
            'koperasi' => $kop,
            'karyawan' => KoperasiKaryawan::where('koperasi_id', $kop->id)
                ->orderByDesc('aktif')->orderBy('nama')->get(),
            'jabatan' => KoperasiKaryawan::JABATAN,
            'bagian' => 'karyawan',
        ]));
    }

    private function validasiKaryawan(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'min:2', 'max:120'],
            'jabatan' => ['nullable', 'string', 'max:60'],
            'telepon' => ['nullable', 'string', 'max:40'],
            'tanggal_masuk' => ['nullable', 'date'],
            'aktif' => ['nullable', 'in:0,1'],
            'catatan' => ['nullable', 'string', 'max:200'],
        ], [], ['nama' => 'nama karyawan', 'tanggal_masuk' => 'tanggal masuk']);
    }

    public function karyawanSimpan(Request $request)
    {
        $kop = $this->wajibTulis($request);
        $d = $this->validasiKaryawan($request);

        $k = KoperasiKaryawan::create([
            'koperasi_id' => $kop->id,
            'nama' => trim($d['nama']),
            'jabatan' => trim((string) ($d['jabatan'] ?? '')),
            'telepon' => trim((string) ($d['telepon'] ?? '')),
            'tanggal_masuk' => $d['tanggal_masuk'] ?? null,
            'aktif' => (int) ($d['aktif'] ?? 1),
            'catatan' => trim((string) ($d['catatan'] ?? '')),
        ]);

        jurnal($request->user()->id, 'KOPERASI_KARYAWAN', $kop->id.':TAMBAH',
            'id='.$k->id.' nama="'.$k->nama.'"', $request->ip() ?? '');

        return redirect()->route('kop.karyawan', ['koperasi' => $kop->id])
            ->with('pesan', 'Karyawan tersimpan: '.$k->nama.'.');
    }

    public function karyawanUbah(Request $request, int $id)
    {
        $kop = $this->wajibTulis($request);
        $k = KoperasiKaryawan::where('koperasi_id', $kop->id)->findOrFail($id);
        $d = $this->validasiKaryawan($request);

        $k->fill([
            'nama' => trim($d['nama']),
            'jabatan' => trim((string) ($d['jabatan'] ?? '')),
            'telepon' => trim((string) ($d['telepon'] ?? '')),
            'tanggal_masuk' => $d['tanggal_masuk'] ?? null,
            'aktif' => (int) ($d['aktif'] ?? 1),
            'catatan' => trim((string) ($d['catatan'] ?? '')),
        ])->save();

        jurnal($request->user()->id, 'KOPERASI_KARYAWAN', $kop->id.':UBAH',
            'id='.$k->id.' nama="'.$k->nama.'"', $request->ip() ?? '');

        return redirect()->route('kop.karyawan', ['koperasi' => $kop->id])
            ->with('pesan', 'Karyawan diperbarui: '.$k->nama.'.');
    }

    public function karyawanHapus(Request $request, int $id)
    {
        $kop = $this->wajibTulis($request);
        $k = KoperasiKaryawan::where('koperasi_id', $kop->id)->findOrFail($id);
        $nama = $k->nama;
        $k->delete();

        jurnal($request->user()->id, 'KOPERASI_KARYAWAN', $kop->id.':HAPUS',
            'id='.$id.' nama="'.$nama.'"', $request->ip() ?? '');

        return redirect()->route('kop.karyawan', ['koperasi' => $kop->id])
            ->with('pesan', 'Karyawan dihapus: '.$nama.'.');
    }

    /** Tambah gerai/outlet milik sendiri — diisi manajer, bukan hasil tarikan. */
    public function geraiSimpan(Request $request)
    {
        $kop = $this->wajibTulis($request);
        $d = $this->validasiGerai($request);

        // `outlet_simkopdes_id` sengaja NULL: baris ini lahir di Hub, bukan
        // salinan SIMKOPDES, dan tidak ada id sumber yang bisa ditunjuk.
        KoperasiGerai::create([
            'koperasi_id' => $kop->id,
            'nama' => trim($d['nama']),
            'jenis' => trim((string) ($d['jenis'] ?? '')),
            'alamat' => trim((string) ($d['alamat'] ?? '')),
            'telepon' => trim((string) ($d['telepon'] ?? '')),
            'status' => trim((string) ($d['status'] ?? '')),
        ]);

        jurnal($request->user()->id, 'KOPERASI_GERAI', $kop->id.':TAMBAH',
            'nama="'.trim($d['nama']).'"', $request->ip() ?? '');

        return $this->kembaliKe($request, $kop)->with('pesan', "Gerai tersimpan: {$d['nama']}.");
    }

    /** Ubah gerai milik sendiri. Gerai koperasi lain dijawab 404. */
    public function geraiUbah(Request $request, int $id)
    {
        $kop = $this->wajibTulis($request);
        $gerai = KoperasiGerai::where('koperasi_id', $kop->id)->findOrFail($id);
        $d = $this->validasiGerai($request);

        $gerai->fill([
            'nama' => trim($d['nama']),
            'jenis' => trim((string) ($d['jenis'] ?? '')),
            'alamat' => trim((string) ($d['alamat'] ?? '')),
            'telepon' => trim((string) ($d['telepon'] ?? '')),
            'status' => trim((string) ($d['status'] ?? '')),
        ])->save();

        jurnal($request->user()->id, 'KOPERASI_GERAI', $kop->id.':UBAH',
            'id='.$gerai->id.' nama="'.$gerai->nama.'"', $request->ip() ?? '');

        return $this->kembaliKe($request, $kop)->with('pesan', "Gerai diperbarui: {$gerai->nama}.");
    }

    /**
     * Hapus gerai milik sendiri.
     *
     * Gerai TIDAK dirujuk tabel lain (nota POS menyalin nama barang, bukan
     * gerainya), jadi menghapusnya tidak meninggalkan baris yatim.
     */
    public function geraiHapus(Request $request, int $id)
    {
        $kop = $this->wajibTulis($request);
        $gerai = KoperasiGerai::where('koperasi_id', $kop->id)->findOrFail($id);
        $nama = $gerai->nama;
        $gerai->delete();

        jurnal($request->user()->id, 'KOPERASI_GERAI', $kop->id.':HAPUS',
            'id='.$id.' nama="'.$nama.'"', $request->ip() ?? '');

        return $this->kembaliKe($request, $kop)->with('pesan', "Gerai dihapus: {$nama}.");
    }

    /**
     * Status operasional: DINILAI MANAJER, bukan dihitung penarik.
     *
     * SIMKOPDES tidak punya kolom "sudah operasional"; dulu angka ini hasil
     * bobot otomatis (badan hukum, gerai, barang, aset) yang ikut basi begitu
     * tarikan diputus. Sekarang manajer yang menyatakan, dan alasannya dicatat.
     */
    public function operasional(Request $request)
    {
        $kop = $this->wajibTulis($request);
        $d = $request->validate([
            'operasional' => ['required', Rule::in(array_keys(KoperasiSarana::LABEL))],
            'catatan' => ['nullable', 'string', 'max:200'],
        ], [], ['operasional' => 'status operasional']);

        $sarana = KoperasiSarana::firstOrNew(['koperasi_id' => $kop->id]);
        $lama = $sarana->operasional;
        $sarana->fill([
            'operasional' => $d['operasional'],
            'alasan' => trim((string) ($d['catatan'] ?? '')),
        ])->save();

        jurnal($request->user()->id, 'KOPERASI_OPERASIONAL', (string) $kop->id,
            'dari='.($lama ?: '-').' jadi='.$d['operasional'], $request->ip() ?? '');

        return $this->kembaliKe($request, $kop)
            ->with('pesan', 'Status operasional disimpan: '.KoperasiSarana::LABEL[$d['operasional']].'.');
    }

    /**
     * Simpan centang katalog sarana prasarana program.
     *
     * Yang dikirim hanyalah indikator yang DICENTANG; sisanya dihapus, sehingga
     * menghapus centang = satu langkah yang sama dengan menyimpan. Keterangan
     * per indikator disimpan bersama centangnya (kolom `keterangan[]`).
     */
    public function centang(Request $request)
    {
        $kop = $this->wajibTulis($request);

        $d = $request->validate([
            'tersedia' => ['nullable', 'array'],
            'tersedia.*' => ['integer'],
            'keterangan' => ['nullable', 'array'],
            'keterangan.*' => ['nullable', 'string', 'max:200'],
            // Kondisi per indikator: BAIK (lengkap/berfungsi) atau RUSAK. Tanpa
            // kolom ini pertanyaan "berapa sarana rusak" tak bisa dijawab.
            'kondisi' => ['nullable', 'array'],
            'kondisi.*' => ['nullable', 'in:BAIK,RUSAK'],
        ]);

        // Hanya id yang benar-benar ada di katalog: formulir bisa basi (katalog
        // berubah) dan id karangan tidak boleh masuk tabel.
        $diminta = collect($d['tersedia'] ?? [])->map(fn ($v) => (int) $v)->unique();
        $sah = SaranaIndikator::whereIn('id', $diminta)->pluck('id')->all();
        $ket = $d['keterangan'] ?? [];
        $kon = $d['kondisi'] ?? [];

        DB::connection('hub')->transaction(function () use ($kop, $sah, $ket, $kon) {
            KoperasiSaranaCentang::where('koperasi_id', $kop->id)
                ->whereNotIn('sarana_indikator_id', $sah)->delete();

            foreach ($sah as $id) {
                KoperasiSaranaCentang::updateOrCreate(
                    ['koperasi_id' => $kop->id, 'sarana_indikator_id' => $id],
                    ['tersedia' => true, 'keterangan' => trim((string) ($ket[$id] ?? '')) ?: null,
                        'kondisi' => strtoupper((string) ($kon[$id] ?? 'BAIK')) === 'RUSAK' ? 'RUSAK' : 'BAIK'],
                );
            }
        });

        jurnal($request->user()->id, 'KOPERASI_SARANA', (string) $kop->id,
            'tersedia='.count($sah), $request->ip() ?? '');

        return $this->kembaliKe($request, $kop)
            ->with('pesan', count($sah).' sarana prasarana ditandai tersedia.');
    }

    // -------------------------------------------------------------- POS: kasir

    /**
     * Simpan satu nota penjualan.
     *
     * Satu permintaan = satu nota: keranjangnya dikirim sebagai JSON dan divalidasi
     * utuh sebelum apa pun ditulis, supaya nota setengah jadi tidak pernah ada.
     * Stok dicek di sini (server), bukan dipercaya dari klien — halaman kasir
     * memang menampilkan sisa stok, tapi angka di peramban bisa basi.
     */
    public function jual(Request $request)
    {
        $kop = $this->tampil($request);
        if ($kop === null) {
            abort(404);
        }

        $d = $request->validate([
            'pembeli' => ['nullable', 'string', 'max:120'],
            'kontak' => ['nullable', 'string', 'max:40'],
            'bayar' => ['required', Rule::in(array_keys(KoperasiJual::BAYAR))],
            'dibayar' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'catatan' => ['nullable', 'string', 'max:200'],
            'item' => ['required', 'string'],
        ], [], ['item' => 'daftar barang', 'bayar' => 'cara bayar', 'dibayar' => 'jumlah dibayar']);

        $item = json_decode($d['item'], true);
        if (! is_array($item) || $item === []) {
            return back()->withInput()->with('galat', 'Keranjang masih kosong — tambahkan minimal satu barang.');
        }

        // Barang yang boleh dijual: HANYA milik koperasi ini. Id dari klien
        // tidak dipercaya; yang dipakai sebagai rujukan adalah id yang benar-
        // benar ada di koperasi ini.
        $id = collect($item)->pluck('id')->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();
        $peta = KoperasiBarang::where('koperasi_id', $kop->id)->whereIn('id', $id)->get()->keyBy('id');

        $baris = [];
        $total = 0;
        foreach ($item as $b) {
            $b = (array) $b;
            $jml = (int) ($b['jumlah'] ?? 0);
            if ($jml <= 0) {
                continue;
            }
            $brg = $peta[(int) ($b['id'] ?? 0)] ?? null;
            if ($brg === null) {
                return back()->withInput()->with('galat', 'Ada barang yang tidak ada di koperasi ini. Muat ulang halaman kasir lalu coba lagi.');
            }
            if ($jml > (int) $brg->stok) {
                return back()->withInput()->with('galat', 'Stok "'.$brg->nama.'" hanya '.$brg->stok.' '
                    .$brg->satuan.'. Tidak bisa menjual '.$jml.'.');
            }

            // Harga diambil dari SERVER. Klien boleh mengirim harga (untuk
            // tampilan), tapi yang dicatat adalah harga di koperasi ini —
            // kalau tidak, harga nota bisa diubah dari peramban.
            $harga = (int) $brg->harga;
            $subtotal = $harga * $jml;
            // PROMO dihitung di sini juga, bukan dipercaya dari layar kasir:
            // potongan rupiah diturunkan dari harga server + aturan promo
            // barangnya (KoperasiBarang::diskonUntuk).
            $diskon = $brg->diskonUntuk($jml);
            $total += $subtotal - $diskon;
            $baris[] = ['barang' => $brg, 'jumlah' => $jml, 'harga' => $harga,
                'subtotal' => $subtotal, 'diskon' => $diskon, 'bonus' => $brg->bonusUntuk($jml)];
        }

        if ($baris === []) {
            return back()->withInput()->with('galat', 'Tidak ada baris yang bisa dijual — periksa jumlahnya.');
        }

        // BONUS GRATIS: stoknya barang LAIN, jadi ketersediaannya diperiksa
        // sebelum menulis apa pun. Kalau stok bonus tidak cukup, bonusnya
        // dikurangi (bukan menggagalkan nota) dan kasir diberi tahu — pembeli
        // yang sudah di kasir tidak boleh pulang tanpa nota karena bonus habis.
        $bonusBaris = [];
        $bonusKurang = [];
        $bonusDiminta = [];
        foreach ($baris as $b) {
            if ($b['bonus'] !== null) {
                $bonusDiminta[$b['bonus']['id']] = ($bonusDiminta[$b['bonus']['id']] ?? 0)
                    + (int) $b['bonus']['jumlah'];
            }
        }
        if ($bonusDiminta !== []) {
            // Sisa stok bonus = stok sekarang dikurangi barang itu yang juga
            // terjual di keranjang yang sama.
            $terjual = [];
            foreach ($baris as $b) {
                $terjual[$b['barang']->id] = ($terjual[$b['barang']->id] ?? 0) + $b['jumlah'];
            }
            $petaBonus = KoperasiBarang::where('koperasi_id', $kop->id)
                ->whereIn('id', array_keys($bonusDiminta))->get()->keyBy('id');
            foreach ($bonusDiminta as $idBonus => $minta) {
                $bb = $petaBonus[$idBonus] ?? null;
                if ($bb === null) {
                    continue;
                }
                $tersedia = (int) $bb->stok - (int) ($terjual[$idBonus] ?? 0);
                $pakai = min($minta, max(0, $tersedia));
                if ($pakai < $minta) {
                    $bonusKurang[] = $bb->nama;
                }
                if ($pakai > 0) {
                    $bonusBaris[] = ['barang' => $bb, 'jumlah' => $pakai];
                }
            }
        }

        $dibayar = (int) ($d['dibayar'] ?? 0);
        $status = $d['bayar'] === 'HUTANG' ? KoperasiJual::HUTANG : KoperasiJual::LUNAS;
        if ($status === KoperasiJual::LUNAS && $dibayar < $total) {
            return back()->withInput()->with('galat', 'Jumlah dibayar ('.number_format($dibayar, 0, ',', '.')
                .') kurang dari total nota ('.number_format($total, 0, ',', '.').'). Pilih cara bayar Hutang bila memang belum dibayar.');
        }

        $tanggal = now('Asia/Jakarta')->toDateString();

        // Satu transaksi: nota, rinciannya, dan penurunan stoknya masuk bersama —
        // kalau salah satu gagal, tidak ada nota yang menggantung tanpa stok.
        // Nomor diulang beberapa kali: urutannya diambil dari nota TERBESAR hari
        // itu (bukan count()+1), sebab count() meleset begitu ada baris terhapus —
        // celah nomor membuat count()+1 menabrak nomor yang masih terpakai, dan
        // indeks unik (koperasi_id, nomor) melempar 500 ke wajah kasir. Sisa
        // balapan dua kasir pada detik yang sama ditutup percobaan ulang ini.
        // ponytail: 5 percobaan sudah jauh melewati kemungkinan nyata; naikkan
        // kalau kelak satu koperasi punya banyak kasir bersamaan.
        $nota = DB::connection('hub')->transaction(function () use ($kop, $request, $d, $baris, $bonusBaris, $total, $dibayar, $status, $tanggal) {
            $urutan = KoperasiJual::where('koperasi_id', $kop->id)->where('tanggal', $tanggal)
                ->max(DB::raw('cast(substr(nomor, -3) as integer)'));
            $n = ((int) $urutan) + 1;

            for ($coba = 1; ; $coba++) {
                try {
                    $nota = KoperasiJual::create([
                        'koperasi_id' => $kop->id, 'kasir_id' => $request->user()->id,
                        'nomor' => 'INV-'.str_replace('-', '', $tanggal).'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                        'pembeli' => (string) ($d['pembeli'] ?? ''), 'kontak' => (string) ($d['kontak'] ?? ''),
                        'bayar' => $d['bayar'], 'total' => $total, 'dibayar' => $dibayar,
                        'kembali' => max(0, $dibayar - $total),
                        'catatan' => (string) ($d['catatan'] ?? ''), 'status' => $status,
                        'tanggal' => $tanggal, 'dibuat' => kini(),
                    ]);
                    break;
                } catch (UniqueConstraintViolationException $e) {
                    if ($coba >= 5) {
                        throw $e;
                    }
                    $n++;
                }
            }

            foreach ($baris as $b) {
                KoperasiJualItem::create([
                    'koperasi_jual_id' => $nota->id, 'koperasi_id' => $kop->id,
                    'koperasi_barang_id' => $b['barang']->id,
                    // Nama/satuan/harga DISALIN: harga bisa berubah besok, nota
                    // lama harus tetap menunjukkan apa yang dibayar hari itu.
                    'nama' => $b['barang']->nama, 'satuan' => $b['barang']->satuan,
                    'harga' => $b['harga'], 'jumlah' => $b['jumlah'], 'subtotal' => $b['subtotal'],
                ]);

                // Potongan promo ditulis sebagai BARIS TERSENDIRI yang negatif,
                // bukan harga barang yang diturunkan: nota lalu menunjukkan
                // "harga normal − diskon", dan pembatalan nota tidak perlu
                // mengembalikan apa pun untuk baris ini (`koperasi_barang_id`
                // kosong = tak menyentuh stok).
                if ($b['diskon'] > 0) {
                    KoperasiJualItem::create([
                        'koperasi_jual_id' => $nota->id, 'koperasi_id' => $kop->id,
                        'koperasi_barang_id' => null,
                        'nama' => 'Diskon promo — '.$b['barang']->nama,
                        'satuan' => '', 'harga' => 0, 'jumlah' => 1, 'subtotal' => -$b['diskon'],
                    ]);
                }

                $this->kurangiStok($b['barang'], $b['jumlah'], $request->user(), $nota->nomor);
            }

            // Bonus promo: harga 0, stoknya tetap berkurang dan ikut kembali
            // bila nota dibatalkan (koperasi_barang_id terisi).
            foreach ($bonusBaris as $bb) {
                KoperasiJualItem::create([
                    'koperasi_jual_id' => $nota->id, 'koperasi_id' => $kop->id,
                    'koperasi_barang_id' => $bb['barang']->id,
                    'nama' => $bb['barang']->nama, 'satuan' => $bb['barang']->satuan,
                    'harga' => 0, 'jumlah' => $bb['jumlah'], 'subtotal' => 0,
                ]);
                $this->kurangiStok($bb['barang'], $bb['jumlah'], $request->user(), $nota->nomor);
            }

            return $nota;
        });

        jurnal($request->user()->id, 'POS_JUAL', $nota->nomor,
            'koperasi='.$kop->id.' total='.$total.' item='.count($baris), $request->ip() ?? '');

        return back()->with('pesan', 'Nota '.$nota->nomor.' tersimpan — '
            .number_format($total, 0, ',', '.').' untuk '.count($baris).' barang.'
            .($nota->kembali > 0 ? ' Kembalian '.number_format($nota->kembali, 0, ',', '.').'.' : '')
            .($bonusBaris !== [] ? ' Bonus gratis: '
                .collect($bonusBaris)->map(fn ($b) => $b['jumlah'].' '.$b['barang']->nama)->implode(', ').'.' : '')
            .($bonusKurang !== [] ? ' Stok bonus ('.implode(', ', $bonusKurang)
                .') tidak cukup — bonus diberikan sebagian/susut.' : ''));
    }

    /**
     * Batalkan nota: stok DIKEMBALIKAN, barisnya tetap ada sebagai riwayat.
     *
     * Nota bernomor yang dihapus membuat riwayat tidak bisa dipercaya; karena itu
     * pembatalan mengubah status, bukan menghapus. Stok kembali memakai sebab
     * 'MASUK' dengan catatan nomor notanya, jadi penambahannya bisa ditelusuri.
     */
    public function jual_batal(Request $request, int $id)
    {
        $kop = $this->tampil($request);
        if ($kop === null) {
            abort(404);
        }

        $nota = KoperasiJual::where('koperasi_id', $kop->id)->whereKey($id)->firstOrFail();
        if ($nota->status === KoperasiJual::BATAL) {
            return back()->with('galat', 'Nota '.$nota->nomor.' sudah dibatalkan sebelumnya.');
        }

        DB::connection('hub')->transaction(function () use ($nota, $request) {
            foreach (KoperasiJualItem::where('koperasi_jual_id', $nota->id)->get() as $it) {
                $brg = $it->koperasi_barang_id
                    ? KoperasiBarang::where('koperasi_id', $nota->koperasi_id)->whereKey($it->koperasi_barang_id)->first()
                    : null;
                if ($brg === null) {
                    continue;   // barangnya sudah dihapus dari daftar: stok tak bisa dikembalikan
                }
                $this->ubahStok($brg, (int) $brg->stok + (int) $it->jumlah, 'MASUK',
                    'Batal nota '.$nota->nomor, $request->user()->id, $nota->koperasi_id);
            }

            $nota->update(['status' => KoperasiJual::BATAL]);
        });

        jurnal($request->user()->id, 'POS_BATAL', $nota->nomor, 'koperasi='.$nota->koperasi_id, $request->ip() ?? '');

        return back()->with('pesan', 'Nota '.$nota->nomor.' dibatalkan dan stok barangnya dikembalikan.');
    }

    /** Nota untuk dicetak/dibaca ulang (halaman sendiri, siap Ctrl+P). */
    public function nota(Request $request, int $id)
    {
        $kop = $this->tampil($request);
        if ($kop === null) {
            abort(404);
        }

        $nota = KoperasiJual::where('koperasi_id', $kop->id)->whereKey($id)->firstOrFail();

        return view('koperasi.nota', [
            'koperasi' => $kop, 'nota' => $nota,
            'item' => KoperasiJualItem::where('koperasi_jual_id', $nota->id)->get(),
            'kasir' => $nota->kasir_id ? User::find($nota->kasir_id)?->nama : null,
        ]);
    }

    // ------------------------------------------------------------------ bantuan

    /**
     * Kerangka bersama: nav koperasi, pemilih koperasi, dan daftar pilihan.
     *
     * `$perluPilihan` = false saat satu koperasi sedang dibuka: daftar pemilih
     * tidak dirender, jadi menghitung & mengambil 10 barisnya hanya membebani
     * setiap permintaan tanpa ada yang membacanya.
     */
    private function kerangka(Request $request, bool $perluPilihan = true): array
    {
        $u = $request->user();

        $pilihan = null;
        $halamanKop = null;
        if ($perluPilihan) {
            $q = $this->pilihan($request);
            $halamanKop = Halaman::buat((clone $q)->count(), $request->query('halaman', 1), self::PER_KOPERASI);
            $pilihan = $halamanKop->batasi($q)->get(['id', 'kode', 'nama', 'wilayah']);
        }

        return [
            // Kunci menu sidebar yang harus MENYALA di halaman modul Koperasi.
            // Harus sama dengan kunci di `daftar_menu()` ('kop' — nama rute),
            // bukan 'koperasi': layout membandingkan `$aktifNav === $kunci`, jadi
            // nilai yang tidak cocok membuat butir menunya tidak pernah tersorot,
            // dan kunci 'koperasi' sudah dipakai menu Koperasi Wilayah (admin).
            'aktif' => 'kop',
            'pilihan' => $pilihan,
            'halamanKop' => $halamanKop,
            'cariKop' => trim((string) $request->query('q', '')),
            'bolehTulis' => $u->manajerKoperasi(),
        ];
    }

    /**
     * Rekap penjualan: hari ini, 7 hari, dan bulan berjalan — dihitung dari nota
     * LUNAS/HUTANG (BATAL tidak dihitung, uangnya tidak masuk).
     */
    private function rekap(Koperasi $kop): array
    {
        $dasar = fn () => KoperasiJual::where('koperasi_id', $kop->id)
            ->where('status', '<>', KoperasiJual::BATAL);

        $hari = now('Asia/Jakarta')->toDateString();
        $tujuh = now('Asia/Jakarta')->subDays(6)->toDateString();
        $awalBulan = now('Asia/Jakarta')->startOfMonth()->toDateString();

        $ambil = fn (string $dari) => [
            'total' => (int) $dasar()->where('tanggal', '>=', $dari)->sum('total'),
            'nota' => (int) $dasar()->where('tanggal', '>=', $dari)->count(),
        ];

        $hutang = (int) $dasar()->where('status', KoperasiJual::HUTANG)->sum('total');

        return [
            'hari' => $ambil($hari),
            'minggu' => $ambil($tujuh),
            'bulan' => $ambil($awalBulan),
            'hutang' => $hutang,
            'terlaris' => KoperasiJualItem::where('koperasi_jual_item.koperasi_id', $kop->id)
                ->join('koperasi_jual as j', 'j.id', '=', 'koperasi_jual_item.koperasi_jual_id')
                ->where('j.status', '<>', KoperasiJual::BATAL)
                ->where('j.tanggal', '>=', $awalBulan)
                ->selectRaw('koperasi_jual_item.nama, SUM(koperasi_jual_item.jumlah) as jumlah, SUM(koperasi_jual_item.subtotal) as nilai')
                ->groupBy('koperasi_jual_item.nama')->orderByDesc('nilai')->limit(5)->get(),
        ];
    }

    /** Kurangi stok + catat mutasinya (satu jalur, sebab KELUAR). */
    private function kurangiStok(KoperasiBarang $b, int $jml, User $u, string $nomor): void
    {
        $this->ubahStok($b, max(0, (int) $b->stok - $jml), 'KELUAR', 'Penjualan '.$nomor, $u->id, $b->koperasi_id);
    }

    /**
     * Satu tempat untuk mengubah stok & mencatat riwayatnya.
     *
     * Dipakai penjualan (KELUAR) DAN pembatalan (MASUK) supaya riwayatnya
     * seragam; tanpa itu, stok yang naik-turun karena kasir tidak akan pernah
     * bisa ditelusuri dari halaman Stok Koperasi.
     */
    private function ubahStok(KoperasiBarang $b, int $baru, string $sebab, string $catatan,
        ?int $userId, int $kopId): void
    {
        $sebelum = (int) $b->stok;
        if ($baru === $sebelum) {
            return;
        }

        \App\Models\KoperasiBarangStok::create([
            'koperasi_barang_id' => $b->id, 'koperasi_id' => $kopId, 'user_id' => $userId,
            'sebelum' => $sebelum, 'sesudah' => $baru, 'selisih' => $baru - $sebelum,
            'sebab' => $sebab, 'catatan' => mb_substr($catatan, 0, 200),
            'tanggal' => now('Asia/Jakarta')->toDateString(), 'dibuat' => kini(),
        ]);

        $b->update(['stok' => $baru, 'oleh_id' => $userId, 'diperbarui' => kini()]);
    }
}

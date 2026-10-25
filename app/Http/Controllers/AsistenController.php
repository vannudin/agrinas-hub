<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Distribusi;
use App\Models\Keuangan;
use App\Models\Koperasi;
use App\Models\Pasokan;
use App\Models\Tugas;
use App\Services\AsistenAi;
use App\Support\Markdown;
use Illuminate\Http\Request;

/**
 * Asisten AI di dalam aplikasi.
 *
 * Halaman ini TIDAK menyimpan jawaban di database — percakapan hidup di sesi
 * Laravel (maks 6 tur). Alasannya: asisten ini alat bantu kerja, bukan jurnal;
 * jejak yang perlu diaudit (dokumen, tugas, pengajuan) sudah punya tabelnya sendiri.
 */
class AsistenController extends Controller
{
    public function __construct(private readonly AsistenAi $ai) {}

    /** Halaman asisten: percakapan sesi + konteks ringkas peran. */
    public function index(Request $request)
    {
        $u = $request->user();

        // Jawaban asisten adalah Markdown. `teks` mentah tetap dikirim (dipakai
        // untuk menyalin), `html` dipakai untuk menampilkan. Dikonversi DI SINI,
        // sekali, supaya blade & jalur JSON menghasilkan tampilan yang sama.
        $riwayat = collect($request->session()->get('asisten', []))
            ->map(fn (array $r) => [...$r, 'html' => Markdown::keHtml($r['content'] ?? '')]);

        return view('asisten.index', [
            'aktif' => 'asisten',
            'riwayat' => $riwayat,
            'konteks' => $this->konteks($u),
            'siap' => (bool) config('services.ai.api_key'),
        ]);
    }

    /** Kirim pertanyaan ke 9Router; jawaban hidup di sesi. */
    public function tanya(Request $request)
    {
        $d = $request->validate(['tanya' => ['required', 'string', 'max:2000']],
            [], ['tanya' => 'pertanyaan']);

        $riwayat = collect($request->session()->get('asisten', []));
        $riwayat->push(['role' => 'user', 'content' => $d['tanya']]);

        try {
            // Persona diambil dari KUNCI peran, bukan `hrd()`: `hrd()` juga true
            // untuk admin pusat, sehingga admin mendapat persona HR.
            $jawab = $this->ai->tanya($riwayat->all(), $this->konteks($request->user()),
                $request->user()->kunciAi());
        } catch (\Throwable $e) {
            report($e);

            // Tanpa AJAX halaman di-reload penuh dan jawaban menunggu model (detik);
            // balasan JSON membuat halaman menampilkan gelembung "sedang menulis" lebih
            // dulu, lalu menggantinya dengan jawaban atau galat ini.
            if ($request->expectsJson()) {
                return response()->json(['galat' => 'Asisten tidak bisa dihubungi: '.$e->getMessage()], 502);
            }

            return back()->with('galat', 'Asisten tidak bisa dihubungi: '.$e->getMessage());
        }

        // Simpan berpasangan, lalu potong ke MAKS_TUR terakhir supaya sesi tidak
        // membengkak (dan biaya token tetap terbatas).
        $riwayat->push(['role' => 'assistant', 'content' => $jawab]);
        $tersimpan = $riwayat->take(-2 * AsistenAi::MAKS_TUR)->values();
        $request->session()->put('asisten', $tersimpan->all());

        if ($request->expectsJson()) {
            return response()->json([
                'jawab' => $jawab,
                'jawab_html' => Markdown::keHtml($jawab),
                'tanya' => $d['tanya'],
                'riwayat' => $tersimpan->all(),
            ]);
        }

        return back();
    }

    /** Bersihkan percakapan. */
    public function bersih(Request $request)
    {
        $request->session()->forget('asisten');

        if ($request->expectsJson()) {
            return response()->json(['pesan' => 'Percakapan dibersihkan.']);
        }

        return back()->with('pesan', 'Percakapan dibersihkan.');
    }

    /**
     * Ringkasan kepegawaian untuk petugas HR — angka yang benar-benar ia kerjakan.
     *
     * Sengaja TIDAK memuat nominal gaji (Hub tidak menyimpannya) dan TIDAK memuat
     * data pribadi (NIK/rekening utuh): model hanya butuh AGREGAT untuk memberi
     * keputusan; daftar orangnya sudah ada di meja /admin/hr.
     */
    private function konteksHr(): string
    {
        $aktif = \App\Models\User::where('aktif', 1)->count();
        $total = \App\Models\User::count();
        $denganSdm = \App\Models\SdmPegawai::whereIn('user_id',
            \App\Models\User::where('aktif', 1)->select('id'))->count();

        $punyaBank = \App\Models\SdmPegawai::where('bank_nomor', '!=', '')->whereNotNull('bank_nomor')
            ->whereIn('user_id', \App\Models\User::where('aktif', 1)->select('id'))->count();
        $punyaBpjs = \App\Models\SdmPegawai::where('bpjs_kesehatan', '!=', '')->whereNotNull('bpjs_kesehatan')
            ->whereIn('user_id', \App\Models\User::where('aktif', 1)->select('id'))->count();

        $sdmStat = [];
        foreach ([\App\Models\SdmPegawai::STATUS, ['BELUM_ADA_LEMBAR']] as $grup) {
            foreach ((array) $grup as $s) {
                $sdmStat[$s] = \App\Models\SdmPegawai::where('status', $s)->count();
            }
        }

        $tanpaPenilaian = \App\Models\User::where('aktif', 1)
            ->whereNotIn('id', \App\Models\SdmPegawai::whereNotNull('nilai')->select('user_id'))->count();

        $pelamar = [];
        foreach (\App\Models\RekrutmenPelamar::STATUS as $s) {
            $pelamar[$s] = \App\Models\RekrutmenPelamar::where('status', $s)->count();
        }

        $peran = \App\Models\User::where('aktif', 1)
            ->selectRaw('peran, COUNT(*) n')->groupBy('peran')->pluck('n', 'peran')->all();
        $wilayah = \App\Models\User::where('aktif', 1)->whereNotNull('wilayah')
            ->where('wilayah', '!=', '')->selectRaw('wilayah, COUNT(*) n')
            ->groupBy('wilayah')->orderByDesc('n')->limit(12)->pluck('n', 'wilayah')->all();

        // Kontrak dari Hub; yang berakhir ≤90 hari = pekerjaan HR paling mendesak.
        $kontrakAktif = \App\Models\Kontrak::count();
        $kontrakHampir = \App\Models\Kontrak::whereNotNull('tanggal_akhir')->where('tanggal_akhir', '!=', '')
            ->where('tanggal_akhir', '<=', now('Asia/Jakarta')->addDays(90)->toDateString())->count();

        // Kehadiran resmi (tarikan SIMKOPDES) — bukan angka karangan.
        $absen = \Illuminate\Support\Facades\DB::connection('hub')->table('simkopdes_absensi')
            ->selectRaw('COUNT(*) n, COUNT(DISTINCT user_id) org, MAX(tanggal) akhir')->first();

        return 'PERAN: Petugas HR (meja SDM & Kepegawaian; lingkup seluruh pegawai, TANPA kendali akun pusat).'
            ."\nAkun: total {$total}, aktif {$aktif}. Yang punya lembar SDM: {$denganSdm}."
            ."\nStatus kerja: ".json_encode($sdmStat, JSON_UNESCAPED_UNICODE)
            ."\nPeran akun aktif: ".json_encode($peran, JSON_UNESCAPED_UNICODE)
            ."\nWilayah (12 terbesar): ".json_encode($wilayah, JSON_UNESCAPED_UNICODE)
            ."\nKesiapan payroll: rekening terisi {$punyaBank}, BPJS terisi {$punyaBpjs} dari {$aktif} aktif"
            .' → belum lengkap '.max(0, $aktif - min($punyaBank, $punyaBpjs)).' orang.'
            ."\nBelum dinilai kinerjanya: {$tanpaPenilaian} orang."
            ."\nKontrak di Hub: {$kontrakAktif}; berakhir ≤90 hari: {$kontrakHampir}."
            ."\nRekrutmen: ".json_encode($pelamar, JSON_UNESCAPED_UNICODE)
            ."\nAbsensi SIMKOPDES: {$absen->n} baris, {$absen->org} orang, terakhir {$absen->akhir}."
            ."\nCATATAN: Hub TIDAK menyimpan angka gaji. Rekening & BPJS tersinkron saat pegawai"
            .' login SIMKOPDES; nominal dihitung di HRIS.'
            ."\n".$this->kehadiran(\App\Models\User::where('aktif', 1)->pluck('id')->all())
            .$this->belajar(\App\Models\User::where('aktif', 1)->pluck('id')->all())
            .$this->komunikasi();
    }

    /**
     * Ringkasan angka untuk model — dibatasi ke data yang boleh dilihat pemanggil.
     * Manajer: koperasinya sendiri. Admin Agrinas: seluruh koperasi (agregat).
     */
    private function konteks(\App\Models\User $u): string
    {
        // Petugas HR punya pertanyaan yang tidak bisa dijawab angka koperasi:
        // kesiapan payroll, kontrak yang berakhir, penilaian yang tertinggal.
        // Karena itu ia mendapat ringkasan kepegawaian, bukan ringkasan koperasi.
        //
        // DIBATASI ke peran HR saja — dulu `hrd()` (yang juga true untuk admin)
        // membuat ADMIN PUSAT tidak pernah melihat angka koperasi sama sekali;
        // pekerjaannya justru lini koperasi (distribusi, stok, pasokan, keuangan).
        if ($u->kunciAi() === 'hr') {
            return $this->konteksHr();
        }

        // Pusat melihat seluruh negeri; PIC hanya wilayahnya. Satu sumber lingkup
        // dipakai untuk semua angka di bawah supaya tidak ada yang bocor.
        if (! $u->manajerKoperasi()) {
            $kop = $u->koperasiQuery()->orderBy('nama')->get();
            $lingkup = $kop->pluck('id');
            $tugas = Tugas::query()->whereIn('koperasi_id', $lingkup)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();
            $dist = Distribusi::query()->whereIn('koperasi_id', $lingkup)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();
            $pasok = Pasokan::query()->whereIn('koperasi_id', $lingkup)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();
            $uang = Keuangan::query()->whereIn('koperasi_id', $lingkup)->selectRaw('SUM(pemasukan) m, SUM(pengeluaran) k')->first();
            $barangRendah = Barang::query()->where('stok_min', '>', 0)->whereColumn('stok', '<=', 'stok_min')
                ->count();

            $baris = $kop->take(20)->map(fn ($k) => sprintf(
                '- %s (%s) %s: manajer_id=%s',
                $k->nama, $k->kode, $k->wilayah ?: '-', $k->manajer_id ?? 'belum ditautkan'
            ))->implode("\n");

            $orang = \App\Models\User::whereIn('id',
                $kop->pluck('manajer_id')->filter()->all())->pluck('id')->all();

            return 'PERAN: '.match (true) {
                    $u->pic() => 'PIC Wilayah '.\App\Support\Wilayah::label((string) $u->wilayah).' (hanya koperasi di wilayah ini)',
                    // Keuangan melihat semua koperasi (ia membayar tagihan siapa
                    // pun) tetapi bukan admin: jangan sampai persona & angkanya
                    // terbaca sebagai kewenangan pusat.
                    $u->keuangan() => 'Petugas Keuangan (semua koperasi; urus pembayaran & kas, bukan stok)',
                    default => 'Admin Agrinas (melihat semua koperasi)',
                }."\n"
                ."Total koperasi: {$kop->count()}\n{$baris}\n"
                ."Tugas per status: ".json_encode($tugas, JSON_UNESCAPED_UNICODE)."\n"
                ."Distribusi per status: ".json_encode($dist, JSON_UNESCAPED_UNICODE)."\n"
                ."Pasokan UMKM per status: ".json_encode($pasok, JSON_UNESCAPED_UNICODE)."\n"
                ."Keuangan koperasi (semua): pemasukan=".Keuangan::rupiah($uang->m ?? 0)
                .", pengeluaran=".Keuangan::rupiah($uang->k ?? 0)."\n"
                ."Barang di bawah stok minimum: {$barangRendah}\n"
                .$this->toko($lingkup->all())
                .$this->uang($lingkup->all())
                .$this->aset($lingkup->all())
                .$this->kehadiran($orang)
                .$this->belajar($orang)
                .$this->komunikasi();
            }

        $kop = Koperasi::where('manajer_id', $u->id)->first();
        if (! $kop) {
            return "PERAN: Manajer Koperasi. Akun ini BELUM ditautkan ke koperasi mana pun, "
                ."jadi tidak ada data koperasi yang bisa ditampilkan.\n";
        }

        $tugas = Tugas::where('koperasi_id', $kop->id)->selectRaw('status, COUNT(*) n')
            ->groupBy('status')->pluck('n', 'status')->all();
        $dist = Distribusi::where('koperasi_id', $kop->id)->selectRaw('status, COUNT(*) n')
            ->groupBy('status')->pluck('n', 'status')->all();
        $pasok = Pasokan::where('koperasi_id', $kop->id)->selectRaw('status, COUNT(*) n')
            ->groupBy('status')->pluck('n', 'status')->all();
        $keluar = Keuangan::where('koperasi_id', $kop->id)
            ->orderByDesc('tanggal')->take(7)->get();
        $bulan = \App\Http\Controllers\KeuanganController::rekap(
            $kop->id, 'bulanan', now()->toDateString());

        // Stok koperasi = barang yang sudah diterima.
        $stok = \App\Models\DistribusiItem::query()
            ->join('distribusi as d', 'd.id', '=', 'distribusi_item.distribusi_id')
            ->join('barang as b', 'b.id', '=', 'distribusi_item.barang_id')
            ->where('d.koperasi_id', $kop->id)->where('d.status', 'DITERIMA')
            ->selectRaw('b.nama, b.satuan, SUM(distribusi_item.jumlah_diterima) j')
            ->groupBy('b.nama', 'b.satuan')->orderByDesc('j')->take(15)->get()
            ->map(fn ($s) => "- {$s->nama}: {$s->j} {$s->satuan}")->implode("\n");

        return "PERAN: Manajer Koperasi\n"
            ."Koperasi: {$kop->nama} ({$kop->kode})".($kop->wilayah ? ", {$kop->wilayah}" : '')."\n"
            ."Tugas per status: ".json_encode($tugas, JSON_UNESCAPED_UNICODE)."\n"
            ."Distribusi barang Agrinas per status: ".json_encode($dist, JSON_UNESCAPED_UNICODE)."\n"
            ."Pasokan UMKM per status: ".json_encode($pasok, JSON_UNESCAPED_UNICODE)."\n"
            ."Keuangan bulan ini ({$bulan['awal']} s/d {$bulan['akhir']}): pemasukan="
            .Keuangan::rupiah($bulan['pemasukan']).", pengeluaran=".Keuangan::rupiah($bulan['pengeluaran'])
            .", hari tercatat={$bulan['hari_terisi']}\n"
            ."Catatan keuangan terakhir:\n"
            .$keluar->map(fn ($k) => "- {$k->tanggal}: masuk ".Keuangan::rupiah($k->pemasukan)
                .", keluar ".Keuangan::rupiah($k->pengeluaran))->implode("\n")
            ."\nStok koperasi (dari barang diterima):\n".($stok ?: '- belum ada')
            ."\n".$this->toko([$kop->id])
            .$this->uang([$kop->id])
            .$this->aset([$kop->id])
            .$this->kehadiran([$u->id])
            .$this->belajar([$u->id])
            .$this->komunikasi();
    }

    // ------------------------------------------------- potongan konteks bersama
    //
    // Tiap potongan = agregat SATU domain fitur yang sudah ada di Hub, dibatasi
    // ke lingkup pemanggil ($koperasi = id koperasi, $orang = id pengguna).
    // Dipakai bersama oleh semua peran supaya angka admin, PIC, dan manajer
    // berasal dari kueri yang sama — bukan tiga versi yang bisa berbeda.

    /** Toko & penjualan koperasi (koperasi_jual, pesanan toko). */
    private function toko(array $koperasi): string
    {
        if ($koperasi === []) {
            return '';
        }
        $jual = \App\Models\KoperasiJual::whereIn('koperasi_id', $koperasi)
            ->selectRaw('status, COUNT(*) n, SUM(total) t')->groupBy('status')->get();
        $nota = (int) $jual->sum('n');
        $nilai = (float) $jual->sum('t');
        $rinci = $jual->map(fn ($j) => $j->status.':'.(int) $j->n)->implode(', ');

        $belanja = \App\Models\Pesanan::whereIn('koperasi_id', $koperasi)
            ->selectRaw('status, COUNT(*) n, SUM(total) t')->groupBy('status')->get();
        $belanjaRinci = $belanja->map(fn ($j) => $j->status.':'.(int) $j->n)->implode(', ');

        return 'Penjualan koperasi (toko): '.($nota > 0
                ? "{$nota} nota senilai ".Keuangan::rupiah($nilai)." ({$rinci})"
                : 'belum ada nota')
            ."\nPesanan pembelian barang: ".($belanja->isEmpty()
                ? 'belum ada pesanan' : "{$belanja->sum('n')} pesanan, nilai "
                    .Keuangan::rupiah((float) $belanja->sum('t'))." ({$belanjaRinci})")
            ."\n";
    }

    /** Uang keluar: saldo koperasi, pembayaran pasokan UMKM, persetujuan. */
    private function uang(array $koperasi): string
    {
        if ($koperasi === []) {
            return '';
        }
        $saldo = \App\Models\Saldo::whereIn('koperasi_id', $koperasi)->sum('saldo');

        $bayar = \App\Models\PasokanBayar::whereIn('koperasi_id', $koperasi)
            ->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all();

        $setuju = \App\Models\Persetujuan::whereIn('koperasi_id', $koperasi)
            ->selectRaw('status, COUNT(*) n, SUM(nominal) t')->groupBy('status')->get();
        $setujuRinci = $setuju->map(fn ($s) => $s->status.':'.(int) $s->n
            .' ('.Keuangan::rupiah((float) $s->t).')')->implode(', ');

        return 'Saldo koperasi (buku Hub): '.Keuangan::rupiah((float) $saldo)."\n"
            .'Pembayaran pasokan UMKM: '.(empty($bayar) ? 'belum ada pengajuan'
                : json_encode($bayar, JSON_UNESCAPED_UNICODE))."\n"
            .'Persetujuan (DIAJUKAN/SETUJU/TOLAK): '.($setuju->isEmpty() ? 'belum ada' : $setujuRinci)."\n";
    }

    /** Aset & legalitas: sarana, lahan, gerai. */
    private function aset(array $koperasi): string
    {
        if ($koperasi === []) {
            return '';
        }
        $sarana = \App\Models\KoperasiSarana::whereIn('koperasi_id', $koperasi)
            ->selectRaw('operasional, COUNT(*) n')->groupBy('operasional')->pluck('n', 'operasional')->all();
        $lahan = \App\Models\KoperasiLahan::whereIn('koperasi_id', $koperasi);
        $jumlahLahan = (clone $lahan)->count();
        $luas = (float) (clone $lahan)->sum('luas');
        $gerai = \App\Models\KoperasiGerai::whereIn('koperasi_id', $koperasi)->count();

        return 'Sarana/legalitas koperasi: '.(empty($sarana) ? 'belum ditarik'
                : json_encode($sarana, JSON_UNESCAPED_UNICODE))."\n"
            ."Lahan terdata: {$jumlahLahan} bidang".($luas > 0 ? ', luas '.number_format($luas, 0, ',', '.').' m2' : '')
            ."; gerai/outlet: {$gerai}\n";
    }

    /** Kehadiran: cermin AgriConnect, Absen Hub, presensi SIMKOPDES. */
    private function kehadiran(array $orang): string
    {
        if ($orang === []) {
            return '';
        }
        $hari = now('Asia/Jakarta')->toDateString();
        $awalBulan = now('Asia/Jakarta')->startOfMonth()->toDateString();

        $ac = \Illuminate\Support\Facades\DB::connection('hub')->table('agri_ac_absen')
            ->whereIn('user_id', $orang)->where('tanggal', $hari)->get();
        $acBulan = \Illuminate\Support\Facades\DB::connection('hub')->table('agri_ac_absen')
            ->whereIn('user_id', $orang)->whereBetween('tanggal', [$awalBulan, $hari])
            ->selectRaw('COUNT(*) n, SUM(telat) telat, SUM(alpha) alpha, MAX(disinkron) akhir')->first();

        $hub = \Illuminate\Support\Facades\DB::connection('hub')->table('agri_absen_hub')
            ->whereIn('user_id', $orang)->where('tanggal', $hari)->where('jenis', 'masuk')->count();

        $sim = \App\Models\SimkopdesAbsensi::whereIn('user_id', $orang)->where('tanggal', $hari)->count();

        return 'Kehadiran hari ini: AgriConnect hadir '.$ac->where('masuk', '!=', '')->count()
            .' orang (terlambat '.$ac->where('telat', 1)->count().')'
            ."; Absen Hub {$hub}; presensi SIMKOPDES {$sim}.\n"
            .'Bulan ini AgriConnect: '.(int) ($acBulan->n ?? 0).' hari-orang'
            .', terlambat '.(int) ($acBulan->telat ?? 0)
            .', alpha '.(int) ($acBulan->alpha ?? 0)
            .', cermin terakhir ditarik '.($acBulan->akhir ?? '-')
            // Time-sheet AgriConnect dikenal tertinggal beberapa hari dari CICO
            // (absensi hari ini bisa Approved di aplikasi tetapi belum muncul di
            // time-sheet). Sebutkan supaya "0 hadir" tidak dibaca sebagai alpha.
            ." (time-sheet AgriConnect bisa tertinggal; absen hari ini cek di aplikasi AgriConnect)\n";
    }

    /** Pembelajaran & poin pegawai. */
    private function belajar(array $orang): string
    {
        if ($orang === []) {
            return '';
        }
        $materi = \App\Models\LmsMateri::count();
        $selesai = \App\Models\LmsProgres::whereIn('user_id', $orang)->count();
        $poin = (int) \App\Models\AgriPoin::whereIn('user_id', $orang)->sum('jumlah');

        return "Pembelajaran: {$materi} materi, {$selesai} penyelesaian oleh orang di lingkup ini; "
            ."poin terkumpul {$poin}\n";
    }

    /** Rapat & aspirasi. */
    private function komunikasi(): string
    {
        $rapat = \App\Models\KonferensiRapat::where('mulai', '>=', now('Asia/Jakarta')->toDateTimeString())
            ->orderBy('mulai')->take(3)->get();
        $aspirasi = \App\Models\Aspirasi::selectRaw('status, COUNT(*) n')->groupBy('status')
            ->pluck('n', 'status')->all();

        return 'Rapat mendatang: '.($rapat->isEmpty() ? 'tidak ada'
                : $rapat->map(fn ($r) => $r->judul.' ('.substr((string) $r->mulai, 0, 16).')')->implode('; '))
            ."\nAspirasi masuk: ".(empty($aspirasi) ? 'belum ada' : json_encode($aspirasi, JSON_UNESCAPED_UNICODE))."\n";
    }
}

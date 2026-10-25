<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Kontrak;
use App\Models\RekrutmenPelamar;
use App\Models\SdmPegawai;
use App\Models\User;
use App\Support\Halaman;
use App\Support\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * SDM & Kepegawaian — meja kerja petugas HR.
 *
 * Halaman ini TIDAK menduplikasi HRIS. Ia mengerjakan tiga hal yang memang
 * datanya ada di Hub, lalu menyebut apa adanya yang tidak dikerjakan di sini:
 *
 *   1. Pegawai  — identitas (`users`) + kontak/profil SIMKOPDES (`profil`) +
 *                 kontrak & penempatan (`kontrak`) + rekap kehadiran
 *                 (`simkopdes_absensi`). Inilah yang dibaca HR tiap hari.
 *   2. Kehadiran— rekap absensi resmi SIMKOPDES per orang per bulan + siklus
 *                 kerja (status, masa kerja, atasan) dan penilaian kinerja
 *                 berperiode yang DICATAT HR (`sdm_pegawai`): angka, periode,
 *                 catatan, dan siapa penilainya.
 *   3. Penggajian & Rekrutmen — slip TIDAK disimpan Hub (tidak ada satu pun
 *                 angka rupiah di sini); yang disimpan hanya RUJUKAN nomor +
 *                 nama berkas di HRIS, sehingga HR dan pegawai melihat slip
 *                 yang sama tanpa Hub memegang duplikat angka. Rekrutmen
 *                 berhenti di KEPUTUSAN (terima/tolak); wawancara & kontrak
 *                 kerja tetap di HRIS.
 *
 * Kewenangan: penjaga rute `hrd` — admin pusat ATAU petugas HR. Memuat
 * identitas seluruh pengguna lintas wilayah, jadi bukan lingkup PIC.
 */
class HrdController extends Controller
{
    /** Saringan "kelengkapan data": kolom yang menentukan adalah tanggal lahir. */
    private const DATA = ['lengkap', 'belum', 'payroll'];

    /** Tab meja SDM. Kunci ini juga dipakai sebagai nama tab di URL (?tab=). */
    public const TAB = [
        'pegawai' => ['Pegawai', 'Daftar pegawai, kontak, dan kontrak'],
        'kehadiran' => ['Kehadiran & Kinerja', 'Rekap hadir + penilaian berperiode'],
        'pembayaran' => ['Payroll & Bank', 'Rekening & BPJS dari SIMKOPDES + rujukan slip'],
        'masuk' => ['Rekrutmen & Onboarding', 'Pelamar sampai keputusan'],
    ];

    /**
     * `q` di tab Rekrutmen berarti cari PELAMAR; di tab lain berarti cari
     * pegawai. Untuk potongan AJAX `?pelamar=1` selalu berarti pelamar.
     */
    private function qPelamar(Request $request): string
    {
        if ($request->boolean('pelamar') || $this->tabAktif($request) === 'masuk') {
            return trim((string) $request->query('q', ''));
        }

        return '';
    }

    /** Status pelamar yang sedang disaring ('' = semua). Berlaku di tab Rekrutmen. */
    private function statusPelamar(Request $request): string
    {
        $s = (string) $request->query('pstatus', '');

        return in_array($s, RekrutmenPelamar::STATUS, true) ? $s : '';
    }

    private function tabAktif(Request $request): string
    {
        $t = (string) $request->query('tab', 'pegawai');

        return isset(self::TAB[$t]) ? $t : 'pegawai';
    }

    /**
     * Daftar pegawai yang disaring: q (nama/email/koperasi/telepon), peran,
     * kelengkapan data, dan status lembar SDM (aktif/cuti/nonaktif).
     */
    private function saring(Request $request)
    {
        $cari = trim((string) $request->query('q', ''));
        $peran = (string) $request->query('peran', '');
        $data = (string) $request->query('data', '');
        $status = (string) $request->query('status', '');

        // `koperasi` di-join sekali untuk nama koperasi yang diampu manajer.
        // LEFT JOIN: admin/PIC tidak punya baris koperasi dan harus tetap muncul.
        $q = User::query()
            ->leftJoin('koperasi as kp', 'kp.manajer_id', '=', 'users.id')
            ->leftJoin('profil as pr', 'pr.user_id', '=', 'users.id')
            ->leftJoin('sdm_pegawai as sd', 'sd.user_id', '=', 'users.id')
            ->select('users.*', 'kp.nama as nama_koperasi', 'kp.kode as kode_koperasi',
                'pr.telepon as telepon', 'pr.kabupaten as kabupaten',
                // `tgl_lahir` (bukan `tanggal_lahir`): kolom `users.tanggal_lahir`
                // kini terenkripsi dan di-cast model, jadi menimpanya dengan
                // tanggal teks dari `profil` membuat dekripsi gagal ("The payload
                // is invalid"). Alias berbeda = dua sumber tidak lagi bertabrakan.
                'pr.tanggal_lahir as tgl_lahir', 'pr.gender as gender',
                'pr.peran_simkopdes as peran_simkopdes',
                'sd.status as sdm_status', 'sd.tanggal_awal as tanggal_awal',
                'sd.tanggal_akhir as tanggal_akhir', 'sd.jabatan as jabatan', 'sd.unit as unit',
                'sd.atasan_id as atasan_id', 'sd.nilai as nilai', 'sd.periode_nilai as periode_nilai',
                'sd.catatan_nilai as catatan_nilai', 'sd.nilai_pada as nilai_pada', 'sd.catatan as sdm_catatan',
                'sd.slip_nomor as slip_nomor', 'sd.slip_berkas as slip_berkas', 'sd.slip_pada as slip_pada',
                // Data payroll hasil sinkron login SIMKOPDES — HR memakainya untuk
                // transfer gaji & memeriksa keanggotaan BPJS tanpa membuka HRIS.
                'sd.bank_nama as bank_nama', 'sd.bank_nomor as bank_nomor',
                'sd.bank_pemilik as bank_pemilik', 'sd.bank_cabang as bank_cabang',
                'sd.bank_berkas as bank_berkas', 'sd.bpjs_kesehatan as bpjs_kesehatan',
                'sd.bpjs_bpjs_ketenagakerjaan as bpjs_bpjs_ketenagakerjaan',
                'sd.bpjs_status as bpjs_status', 'sd.bpjs_program as bpjs_program',
                'sd.nik_bpjs as nik_bpjs', 'sd.data_bank_pada as data_bank_pada');

        if ($cari !== '') {
            $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cari).'%';
            $q->where(fn ($w) => $w->where('users.nama', 'like', $pola)
                ->orWhere('users.email', 'like', $pola)
                ->orWhere('kp.nama', 'like', $pola)
                ->orWhere('kp.kode', 'like', $pola)
                ->orWhere('pr.telepon', 'like', $pola));
        }

        if (isset(Domain::LABEL_PERAN[$peran])) {
            $q->where('users.peran', $peran);
        }

        if ($data === 'lengkap') {
            $q->where(fn ($w) => $w->whereNotNull('pr.tanggal_lahir')->where('pr.tanggal_lahir', '!=', ''));
        } elseif ($data === 'payroll') {
            // Saringan untuk pekerjaan HR yang sesungguhnya: siapa yang belum
            // punya rekening/BPJS sehingga gajinya tak bisa ditransfer otomatis.
            $q->where(fn ($w) => $w->whereNull('sd.bank_nomor')->orWhere('sd.bank_nomor', '')
                ->orWhereNull('sd.bpjs_kesehatan')->orWhere('sd.bpjs_kesehatan', '')
                ->orWhereNull('sd.bpjs_bpjs_ketenagakerjaan')->orWhere('sd.bpjs_bpjs_ketenagakerjaan', ''));
        } elseif ($data === 'belum') {
            // Termasuk yang belum punya baris `profil` sama sekali — mereka justru
            // yang paling sering terlewat kalau syaratnya hanya "kolom kosong".
            $q->where(fn ($w) => $w->whereNull('pr.tanggal_lahir')->orWhere('pr.tanggal_lahir', ''));
        }

        if (in_array($status, SdmPegawai::STATUS, true)) {
            // Akun nonaktif selalu terbaca nonaktif; tanpa ini lembar ber-status
            // AKTIF milik akun mati ikut muncul di saringan "aktif". Pegawai yang
            // belum punya lembar dihitung AKTIF (default).
            if ($status === 'NONAKTIF') {
                $q->where(fn ($w) => $w->where('users.aktif', 0)->orWhere('sd.status', 'NONAKTIF'));
            } else {
                $q->where('users.aktif', 1);
                $q->where($status === 'AKTIF'
                    ? fn ($w) => $w->whereNull('sd.status')->orWhere('sd.status', 'AKTIF')
                    : fn ($w) => $w->where('sd.status', $status));
            }
        }

        return $q->orderBy('users.peran')->orderBy('users.nama');
    }

    public function index(Request $request)
    {
        $tab = $this->tabAktif($request);
        $q = $this->saring($request);
        $halaman = Halaman::buat((clone $q)->count(), $request->query('halaman', 1), DasborController::PER_HALAMAN);

        // Kontrak TIDAK di-join ke daftar: satu orang bisa punya beberapa baris
        // kontrak (satu per periode), dan join-nya menggandakan baris pegawai —
        // HR melihat orang yang sama dua kali. Diambil terpisah untuk 10 id yang
        // sedang tampil, lalu dipetakan. Satu query, tanpa N+1.
        $baris = $halaman->batasi($q)->get();
        $id = $baris->pluck('id');

        $kontrak = Kontrak::whereIn('user_id', $id)
            ->orderByDesc('periode_id')->get()->keyBy('user_id');

        // Kehadiran: DUA sumber, digabung. `simkopdes_absensi` = catatan resmi
        // SIMKOPDES, `agri_ac_absen` = cermin time-sheet AgriConnect (presensi
        // yang diakui Hub). Dulu hanya sumber pertama dibaca, sehingga orang
        // yang hanya absen lewat AgriConnect tampil "belum pernah hadir" di
        // meja HR padahal catatannya ada. `distinct tanggal` mencegah satu hari
        // terhitung dua kali saat orang yang sama tercatat di kedua sumber.
        $absen = DB::query()->fromSub(
            DB::table('simkopdes_absensi')->select('user_id', 'tanggal')->whereIn('user_id', $id)
                ->unionAll(
                    DB::table('agri_ac_absen')->select('user_id', 'tanggal')->whereIn('user_id', $id)
                ),
            'gabung'
        )
            ->select('user_id', DB::raw('COUNT(DISTINCT tanggal) as jumlah'), DB::raw('MAX(tanggal) as terakhir'))
            ->groupBy('user_id')->get()->keyBy('user_id');

        // Nama atasan untuk baris yang tampil (maks 10 id) — dipetakan, bukan di-join.
        $atasan = User::whereIn('id', $baris->pluck('atasan_id')->filter()->all())
            ->pluck('nama', 'id');

        // Kartu ringkasan DIHITUNG dari SELURUH meja, bukan dari 10 baris halaman
        // aktif — kalau tidak, angkanya berubah tiap pindah halaman.
        $stat = [
            'manajer' => User::where('peran', Domain::PERAN_MANAJER)->count(),
            'pic' => User::where('peran', Domain::PERAN_PIC)->count(),
            'admin' => User::where('peran', Domain::PERAN_AGRINAS)->count(),
        ];
        $tanpaData = User::whereDoesntHave('profilLengkap')->count();

        // Lembar SDM: status kerja (belum punya lembar = aktif), dan berapa orang
        // yang masih menunggu dinilai / belum punya slip — angka inilah yang
        // membuat HR tahu pekerjaan mana yang tertinggal.
        $sdmStat = [
            'aktif' => User::where('aktif', 1)->whereNotIn('id', SdmPegawai::where('status', '!=', 'AKTIF')->select('user_id'))->count(),
            'cuti' => SdmPegawai::where('status', 'CUTI')->count(),
            'nonaktif' => SdmPegawai::where('status', 'NONAKTIF')->count() + User::where('aktif', 0)->count(),
        ];
        $belumDinilai = User::where('aktif', 1)
            ->whereNotIn('id', SdmPegawai::whereNotNull('nilai')->select('user_id'))->count();
        $tanpaSlip = User::where('aktif', 1)
            ->whereNotIn('id', SdmPegawai::where('slip_nomor', '!=', '')->select('user_id'))->count();
        $tanpaLembar = User::where('aktif', 1)
            ->whereNotIn('id', SdmPegawai::select('user_id'))->count();

        // Kesiapan payroll: berapa orang yang rekening/BPJS-nya sudah ada. Dihitung
        // dari KOLOM sdm_pegawai, bukan blob JSON, supaya satu query untuk semua.
        $siapPayroll = User::where('aktif', 1)
            ->whereIn('id', SdmPegawai::where('bank_nomor', '!=', '')->whereNotNull('bank_nomor')
                ->where('bpjs_kesehatan', '!=', '')->whereNotNull('bpjs_kesehatan')
                ->select('user_id'))->count();
        $tanpaBank = User::where('aktif', 1)
            ->whereNotIn('id', SdmPegawai::where('bank_nomor', '!=', '')->whereNotNull('bank_nomor')
                ->select('user_id'))->count();
        $tanpaBpjs = User::where('aktif', 1)
            ->whereNotIn('id', SdmPegawai::where('bpjs_kesehatan', '!=', '')->whereNotNull('bpjs_kesehatan')
                ->select('user_id'))->count();

        // Pilihan atasan langsung: pusat, PIC, dan manajer. ponytail: seluruh
        // daftar dikirim ke <select> (39 baris sekarang); ganti ke pencarian
        // bilamana pengguna melewati beberapa ratus.
        $atasanPilihan = User::where('aktif', 1)
            ->whereIn('peran', [Domain::PERAN_AGRINAS, Domain::PERAN_PIC, Domain::PERAN_MANAJER])
            ->orderBy('nama')->pluck('nama', 'id');

        // Daftar pelamar: satu jalur saringan untuk halaman penuh & potongan AJAX,
        // dengan halaman SENDIRI (kunci 'phalaman') supaya paging tabel pegawai
        // (kunci 'halaman') tidak saling menimpa.
        $pstatus = $this->statusPelamar($request);
        $qp = $this->qPelamar($request);
        $pelamarHalaman = Halaman::buat(
            (clone RekrutmenPelamar::daftar($qp, 1, $pstatus))->count(),
            $request->query('phalaman', 1),
        );
        $pelamar = $pelamarHalaman->batasi(RekrutmenPelamar::daftar($qp, PHP_INT_MAX, $pstatus))->get();
        $rekrutStat = [];
        foreach (RekrutmenPelamar::STATUS as $s) {
            $rekrutStat[$s] = RekrutmenPelamar::where('status', $s)->count();
        }

        return view('admin.hr', [
            'aktif' => 'hr',
            'tab' => $tab,
            'pegawai' => $baris, 'halaman' => $halaman,
            'kontrak' => $kontrak, 'absen' => $absen, 'atasan' => $atasan,
            'q' => trim((string) $request->query('q', '')),
            'peran' => (string) $request->query('peran', ''),
            'data' => (string) $request->query('data', ''),
            'status' => (string) $request->query('status', ''),
            'stat' => $stat, 'total' => $halaman->jumlah,
            'tanpaData' => $tanpaData,
            'sdmStat' => $sdmStat, 'belumDinilai' => $belumDinilai, 'tanpaSlip' => $tanpaSlip,
            'tanpaLembar' => $tanpaLembar, 'atasanPilihan' => $atasanPilihan,
            'siapPayroll' => $siapPayroll, 'tanpaBank' => $tanpaBank, 'tanpaBpjs' => $tanpaBpjs,
            'pelamar' => $pelamar, 'pelamarHalaman' => $pelamarHalaman, 'rekrutStat' => $rekrutStat,
            'pstatus' => $this->statusPelamar($request),
            'wilayah' => Wilayah::PILIHAN,
        ]);
    }

    /**
     * Semua pelamar sebagai potongan daftar (mode `?pelamar=`): hub.js memakainya
     * untuk menyaring pelamar saat mengetik, karena daftar pelamar tidak
     * berhalaman. Baris tabel pelamar saja — bukan seluruh halaman.
     */
    /**
     * Ekspor CSV kesiapan payroll — berkas yang benar-benar dipakai untuk
     * memeriksa & menyiapkan transfer gaji. TIDAK ada satu pun nominal gaji di
     * sini (Hub tidak menyimpannya): yang diekspor adalah rekening tujuan dan
     * keanggotaan BPJS apa adanya dari SIMKOPDES.
     */
    public function payroll_csv(Request $request): Response
    {
        $q = $this->saring($request);
        $baris = (clone $q)->orderBy('users.peran')->orderBy('users.nama')
            ->get(['users.id', 'users.nama', 'users.email', 'users.peran',
                'sd.bank_nama', 'sd.bank_nomor', 'sd.bank_pemilik', 'sd.bank_cabang',
                'sd.bpjs_kesehatan', 'sd.bpjs_bpjs_ketenagakerjaan', 'sd.bpjs_status',
                'sd.bpjs_program', 'sd.nik_bpjs', 'sd.slip_nomor', 'sd.data_bank_pada']);

        $out = "Nama;Email;Peran;Bank;Nomor Rekening;Pemilik Rekening;Cabang;"
            ."BPJS Kesehatan;BPJS Ketenagakerjaan;Status BPJS;Program BPJS;NIK;Rujukan Slip;Ditarik\n";
        foreach ($baris as $b) {
            $out .= implode(';', array_map(
                fn ($v) => str_replace([';', "\n", "\r"], [',', ' ', ' '], (string) $v),
                [$b->nama, $b->email, Domain::LABEL_PERAN[$b->peran] ?? $b->peran,
                    $b->bank_nama, $b->bank_nomor, $b->bank_pemilik, $b->bank_cabang,
                    $b->bpjs_kesehatan, $b->bpjs_bpjs_ketenagakerjaan, $b->bpjs_status,
                    $b->bpjs_program, $b->nik_bpjs, $b->slip_nomor, $b->data_bank_pada]
            ))."\n";
        }

        $nama = 'payroll-'.now('Asia/Jakarta')->format('Y-m-d').'.csv';
        jurnal($request->user()->id, 'PAYROLL_EKSPOR', 'hr', $baris->count().' baris', $request->ip() ?? '');

        return response($out, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }

    /**
     * Tarik-ulang data payroll dari JSON profil yang SUDAH tersimpan.
     *
     * Tidak memanggil SIMKOPDES: data bank & BPJS sudah ikut tersimpan saat
     * pendamping login (`account_banks`, `insurances`). Jadi ini aman diulang
     * kapan saja, tanpa rate limit dan tanpa token siapa pun.
     */
    public function payroll_tarik(Request $request): \Illuminate\Http\RedirectResponse
    {
        $ubah = 0;
        foreach (DB::connection('hub')->table('profil')->whereNotNull('profil')->get(['user_id', 'profil']) as $r) {
            $p = json_decode((string) $r->profil, true);
            if (is_array($p) && SdmPegawai::simpanPayroll((int) $r->user_id, $p)) {
                $ubah++;
            }
        }

        jurnal($request->user()->id, 'PAYROLL_TARIK', 'hr', $ubah.' baris', $request->ip() ?? '');

        return back()->with('pesan', "Data payroll ditarik dari profil SIMKOPDES: {$ubah} baris diperbarui.");
    }

    public function pelamar_semua(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        // Potongan ini menggantikan <tbody>, jadi jumlah baris ditampilkan
        // seluruhnya (bukan 10 per halaman seperti tabel pegawai).
        $semua = RekrutmenPelamar::daftar($q, 200, $this->statusPelamar($request))->get();

        return response()->view('admin._hr-pelamar', [
            'pelamar' => $semua,
            'q' => $q,
            'kelas' => fn (string $s) => Domain::KELAS_SDM[$s] ?? 's-BARU',
        ], 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** Simpan siklus kerja: status, masa kerja, jabatan/unit, atasan, catatan. */
    public function sdm_simpan(Request $request, int $id)
    {
        $p = User::findOrFail($id);
        $d = $request->validate([
            'status' => ['required', 'in:'.implode(',', SdmPegawai::STATUS)],
            'tanggal_awal' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date'],
            'jabatan' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:120'],
            'atasan_id' => ['nullable', 'integer'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $atasan = ! empty($d['atasan_id']) && User::whereKey($d['atasan_id'])->exists() ? (int) $d['atasan_id'] : null;

        $row = SdmPegawai::untuk($p->id);
        $row->fill([
            'status' => $d['status'],
            'tanggal_awal' => (string) ($d['tanggal_awal'] ?? ''),
            'tanggal_akhir' => (string) ($d['tanggal_akhir'] ?? ''),
            'jabatan' => (string) ($d['jabatan'] ?? ''),
            'unit' => (string) ($d['unit'] ?? ''),
            'atasan_id' => $atasan,
            // Catatan HANYA ditimpa bila diisi: formulir siklus kerja selalu
            // mengirim field ini (bisa kosong), dan tanpa penjagaan ini menyimpan
            // status kerja akan menghapus catatan yang sudah ditulis sebelumnya.
            'dicatat_oleh' => $request->user()->id,
            'dibuat' => $row->dibuat ?: kini(),
            'diubah' => kini(),
        ]);
        if (trim((string) ($d['catatan'] ?? '')) !== '') {
            $row->catatan = $d['catatan'];
        }
        $row->save();

        jurnal($request->user()->id, 'SDM_SIMPAN', $p->email,
            'Status '.$d['status'].($d['jabatan'] ? ', jabatan '.$d['jabatan'] : ''), $request->ip() ?? '');

        return back()->with('pesan', 'Lembar kepegawaian '.$p->nama.' disimpan.');
    }

    /** Catat penilaian kinerja berperiode: angka, periode, catatan, penilai. */
    public function nilai_simpan(Request $request, int $id)
    {
        $p = User::findOrFail($id);
        $d = $request->validate([
            'nilai' => ['required', 'integer', 'min:1', 'max:100'],
            'periode_nilai' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'catatan_nilai' => ['nullable', 'string', 'max:500'],
        ]);

        $row = SdmPegawai::untuk($p->id);
        $row->fill([
            'nilai' => (int) $d['nilai'],
            'periode_nilai' => $d['periode_nilai'],
            'catatan_nilai' => (string) ($d['catatan_nilai'] ?? ''),
            'nilai_oleh' => $request->user()->id,
            'nilai_pada' => kini(),
            'dibuat' => $row->dibuat ?: kini(),
            'diubah' => kini(),
        ]);
        $row->save();

        jurnal($request->user()->id, 'SDM_NILAI', $p->email,
            'Nilai '.$d['nilai'].' periode '.$d['periode_nilai'], $request->ip() ?? '');

        return back()->with('pesan', 'Penilaian kinerja '.$p->nama.' ('.$d['periode_nilai'].') tercatat: '.$d['nilai'].'.');
    }

    /** Catat rujukan slip gaji: nomor + nama berkas di HRIS. TANPA angka rupiah. */
    public function slip_simpan(Request $request, int $id)
    {
        $p = User::findOrFail($id);
        $d = $request->validate([
            'slip_nomor' => ['required', 'string', 'max:80'],
            'slip_berkas' => ['nullable', 'string', 'max:160'],
        ]);

        $row = SdmPegawai::untuk($p->id);
        $row->fill([
            'slip_nomor' => $d['slip_nomor'],
            'slip_berkas' => (string) ($d['slip_berkas'] ?? ''),
            'slip_pada' => kini(),
            'slip_oleh' => (string) $request->user()->nama,
            'dibuat' => $row->dibuat ?: kini(),
            'diubah' => kini(),
        ]);
        $row->save();

        jurnal($request->user()->id, 'SDM_SLIP', $p->email, 'Rujukan slip '.$d['slip_nomor'], $request->ip() ?? '');

        return back()->with('pesan', 'Rujukan slip '.$p->nama.' disimpan: '.$d['slip_nomor']
            .'. Angkanya tetap di HRIS — Hub tidak menyalinnya.');
    }

    /** Tambah pelamar baru (dari luar Hub, belum punya akun). */
    public function rekrut_simpan(Request $request)
    {
        $d = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'telepon' => ['nullable', 'string', 'max:32'],
            'jabatan' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:120'],
            'sumber' => ['nullable', 'string', 'max:80'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'dilamar' => ['nullable', 'date'],
        ]);

        $p = RekrutmenPelamar::create([
            'nama' => $d['nama'],
            'email' => mb_strtolower(trim((string) ($d['email'] ?? ''))),
            'telepon' => (string) ($d['telepon'] ?? ''),
            'jabatan' => (string) ($d['jabatan'] ?? ''),
            'unit' => (string) ($d['unit'] ?? ''),
            'sumber' => (string) ($d['sumber'] ?? ''),
            'catatan' => (string) ($d['catatan'] ?? ''),
            'dilamar' => (string) ($d['dilamar'] ?? date('Y-m-d')),
            'status' => 'MENUNGGU',
            'dicatat_oleh' => $request->user()->id,
            'dibuat' => kini(),
        ]);

        jurnal($request->user()->id, 'SDM_REKRUT', $p->nama,
            'Pelamar baru'.($p->jabatan !== '' ? ' — '.$p->jabatan : ''), $request->ip() ?? '');

        return back()->with('pesan', 'Pelamar '.$p->nama.' dicatat. Keputusannya menyusul di daftar yang sama.');
    }

    /**
     * Putuskan pelamar. DITERIMA = angkat jadi pegawai lewat `users` (jadi satu
     * kenyataan dengan onboarding), TANPA sandi acak yang tak pernah dikirim ke
     * siapa pun: akun dibuat dengan `wajib_ganti=1` dan sandi awal yang
     * ditampilkan sekali di halaman akun.
     */
    public function keputusan(Request $request, int $id)
    {
        $p = RekrutmenPelamar::findOrFail($id);
        $d = $request->validate([
            'status' => ['required', 'in:DITERIMA,DITOLAK,TARIK_DIRI'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $pesan = 'Keputusan '.$p->nama.': '.RekrutmenPelamar::STATUS_LABEL[$d['status']].'.';

        if ($d['status'] === 'DITERIMA') {
            if ($p->user_id) {
                return back()->with('pesan', $p->nama.' sudah pernah diangkat jadi pegawai.');
            }
            if ((string) $p->email === '') {
                return back()->with('galat', 'Untuk mengangkat jadi pegawai, surel pelamar wajib diisi lebih dulu.');
            }

            $ada = User::where('email', $p->email)->first();
            if ($ada) {
                // Sudah punya akun: cukup jadikan manajer? TIDAK — peran tidak
                // ditentukan di sini. Hubungkan saja, biar admin mengangkat
                // perannya di /admin/akun (satu tempat).
                $p->user_id = $ada->id;
                $pesan .= ' Akun '.$p->email.' sudah ada; perannya diatur di halaman Akun.';
            } else {
                $sandi = 'Sdm#'.substr(bin2hex(random_bytes(6)), 0, 10);
                $u = User::create([
                    'email' => $p->email, 'nama' => $p->nama, 'peran' => Domain::PERAN_MANAJER,
                    'sandi' => \App\Support\ScryptHasher::buat($sandi), 'wajib_ganti' => 1,
                    'aktif' => 1, 'dibuat' => kini(),
                ]);
                $p->user_id = $u->id;
                $request->session()->flash('rahasia_baru', $sandi);
                $pesan .= ' Akun pegawai dibuat. Sandi awal ditampilkan di halaman ini (sekali saja).';
            }
        }

        $p->fill([
            'status' => $d['status'],
            'catatan' => (string) ($d['catatan'] ?? $p->catatan),
            'diputus_pada' => kini(),
            'diputus_oleh' => (string) $request->user()->nama,
        ]);
        $p->save();

        jurnal($request->user()->id, 'SDM_KEPUTUSAN', $p->nama, $d['status'], $request->ip() ?? '');

        return back()->with('pesan', $pesan);
    }
}

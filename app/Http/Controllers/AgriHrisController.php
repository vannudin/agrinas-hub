<?php

namespace App\Http\Controllers;

use App\Models\AgriPresensi;
use App\Models\Domain;
use App\Models\Koperasi;
use App\Models\User;
use App\Services\Agriconnect;
use App\Support\AbsenAc;
use App\Support\Halaman;
use App\Support\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Halaman modul AgriConnect yang datanya LANGSUNG dari API AgriConnect HRIS
 * (Starconnect) — bukan dari tabel Hub.
 *
 * Dipisah dari AgriController karena beda sifatnya: AgriController mengurus
 * data MILIK HUB (presensi berfoto, tugas pribadi, poin, berkas, materi) yang
 * tetap hidup tanpa jaringan ke Starconnect. Halaman di sini adalah cermin
 * aplikasi AgriConnect: kalau akunnya belum tersambung atau tokennya mati,
 * halamannya menyatakan itu — bukan menampilkan halaman kosong.
 *
 * Semua endpoint yang dipakai sudah diuji dengan akun Manajer KDKMP nyata
 * (2026-10-14). Yang belum punya data tetap ditampilkan sebagai daftar kosong
 * dengan keterangan sumbernya, supaya pengguna tahu bedanya "tidak ada data"
 * dari "tidak tersambung".
 */
class AgriHrisController extends Controller
{
    public function __construct(private readonly Agriconnect $ac) {}

    // ------------------------------------------------------------------- akun

    public function akun(Request $request)
    {
        $u = $request->user();

        // Peran pemantau (admin/HR/PIC/keuangan) TIDAK punya akun pegawai di
        // AgriConnect. Halaman akun pribadi hanya akan menampilkan sesi milik
        // siapa pun yang kebetulan tertaut — itulah yang terbaca "Terhubung akun
        // saya irpan" — padahal pekerjaan mereka memantau akun para manajer.
        if (! $u->manajerKoperasi()) {
            return $this->pemantauan($request);
        }

        return view('agri.hris.akun', [
            'aktif' => 'agri',
            'tersambung' => $this->ac->tersambung($u),
            'sesi' => $this->ac->sesi($u),
            'namaAgri' => $u->agri_username,
            'sejak' => $u->agri_tersambung_pada,
            'terkonfigurasi' => $this->ac->terkonfigurasi(),
            // Peta seluruh menu aplikasi AgriConnect beserta keadaan datanya di
            // akun ini — inilah "menunya sama", tanpa 20 halaman kosong.
            'peta' => $this->petaMenu($u),
        ]);
    }

    /** Daftar akun AgriConnect milik manajer + keadaan sambungannya (pemantau). */
    private function pemantauan(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $peran = (string) $request->query('peran', '');
        $semua = $peran === 'semua';

        $dasar = User::query()
            ->whereNotNull('agri_username')->where('agri_username', '!=', '')
            ->when(! $semua, fn ($w) => $w->where('peran', Domain::PERAN_MANAJER))
            // PIC melihat manajer di wilayahnya; pengawasan (TNI/Dinas) menurut
            // lingkupnya sendiri — PUSAT seluruh negeri, provinsi/kabupaten
            // disaring; admin/HR/keuangan seluruh negeri.
            ->when($request->user()->pic() || $request->user()->pantau(), function ($w) use ($request) {
                $u = $request->user();
                $s = $u->pic()
                    ? Wilayah::saring((string) $u->wilayah)
                    : Wilayah::saringLingkup((string) $u->wilayah);

                return $s === null ? $w : $w->whereIn('id',
                    Koperasi::query()->where($s)->select('manajer_id'));
            })
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('nama', 'like', '%'.$q.'%')
                ->orWhere('agri_username', 'like', '%'.$q.'%')));

        $jumlah = (clone $dasar)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), 10);
        $baris = $halaman->batasi($dasar->orderBy('nama'))->get();

        // Presensi Hub bulan ini untuk baris yang tampil saja: satu query,
        // bukan satu kueri per baris.
        $sejak = now('Asia/Jakarta')->startOfMonth()->format('Y-m-d');
        $hadir = AgriPresensi::query()->whereIn('user_id', $baris->pluck('id'))
            ->where('tanggal', '>=', $sejak)
            ->selectRaw('user_id, count(*) as n')->groupBy('user_id')->pluck('n', 'user_id');

        return view('agri.hris.pemantau', [
            'aktif' => 'agri',
            'baris' => $baris, 'halaman' => $halaman, 'jumlah' => $jumlah,
            'q' => $q, 'peran' => $peran, 'hadir' => $hadir,
            'tersambung' => $baris->mapWithKeys(fn ($b) => [$b->id => $this->ac->tersambung($b)])->all(),
        ]);
    }

    public function akun_masuk(Request $request)
    {
        // Pop-up di /agri mengirim permintaan ini lewat fetch dan mengharapkan
        // JSON supaya hasilnya jadi notifikasi (bukan halaman galat). POST biasa
        // dari /agri/hris/akun tetap dapat redirect + flash seperti semula.
        $json = $request->wantsJson();

        // Akun AgriConnect melekat pada pegawai, tetapi SIAPA PUN boleh
        // menautkan akunnya sendiri: halaman agri.hris.* membaca token pada baris
        // user yang masuk (users.agri_sesi), jadi tidak ada sesi orang lain yang
        // ikut terbaca. Dulu peran pemantau diblokir di sini, akibatnya HR,
        // keuangan, dan PIC tidak bisa membuka halaman AgriConnect sama sekali
        // (keluhan Bos 2026-10-21).
        $d = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'sandi' => ['required', 'string', 'max:200'],
            'otp' => ['nullable', 'string', 'max:12'],
        ], [
            // Pesan Indonesia: aplikasi ini tidak memuat lang/en, jadi bawaan
            // Laravel berbahasa Inggris — dan di pop-up hasilnya jadi notifikasi
            // Inggris ("The nama pengguna field is required.").
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute terlalu panjang.',
        ], ['username' => 'nama pengguna', 'sandi' => 'sandi', 'otp' => 'kode OTP']);

        try {
            $h = $this->ac->hubungkan($request->user(), $d['username'], $d['sandi'], $d['otp'] ?? null);
        } catch (\Throwable $e) {
            $pesan = 'Gagal masuk AgriConnect: '.$e->getMessage();
            if ($json) {
                return response()->json(['ok' => false, 'pesan' => $pesan], 422);
            }

            return back()->with('galat', $pesan);
        }

        $pesan = 'Tersambung ke AgriConnect sebagai '.$h['pesan'].'. Seluruh layanan mandiri pegawai sekarang terbuka.';
        if ($json) {
            return response()->json(['ok' => true, 'pesan' => $pesan, 'nama' => $h['pesan']]);
        }

        return redirect()->route('agri.hris.akun')->with('pesan', $pesan);
    }

    public function akun_keluar(Request $request)
    {
        $this->ac->lepas($request->user());

        return redirect()->route('agri.hris.akun')
            ->with('pesan', 'Sesi AgriConnect dilepas. Data pegawai tidak lagi dibaca dari akun ini.');
    }

    // ----------------------------------------------------------------- profil

    public function profil(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return $this->belum($request);
        }

        $data = [
            // Nomor pegawai HANYA ada di endpoint profil ini (bukan di personal
            // maupun employment) — tanpa panggilan ini kolomnya kosong.
            'profil' => $this->ac->coba($u, '/api/user/profile', [], []),
            'diri' => $this->ac->coba($u, '/api/user/profile/personal', [], []),
            'kerja' => $this->ac->coba($u, '/api/user/profile/employment', [], []),
            'alamat' => $this->ac->coba($u, '/api/user/profile/address', [], []),
            'pendidikan' => $this->ac->coba($u, '/api/user/profile/education', [], []),
            'berkas' => $this->ac->coba($u, '/api/user/profile/document', [], []),
            'sertifikat' => $this->ac->coba($u, '/api/user/profile/certificate', [], []),
            'pengalaman' => $this->ac->coba($u, '/api/user/profile/work-experience', [], []),
            'kompetensi' => $this->ac->coba($u, '/api/user/profile/competencies', [], []),
        ];

        return view('agri.hris.profil', [
            'aktif' => 'agri', 'data' => $data,
            // Satu-satunya data profil yang boleh DITULIS langsung oleh pegawai
            // lewat API (bukan pengajuan ber-approval): tautan media sosial.
            'sosmed' => $this->sosmed($u),
        ]);
    }

    /** Tautan media sosial akun ini, dinormalkan jadi [tipe => [path,id]]. */
    private function sosmed(User $u): array
    {
        $hasil = ['FACEBOOK' => ['path' => '', 'id' => null],
            'INSTAGRAM' => ['path' => '', 'id' => null],
            'LINKEDIN' => ['path' => '', 'id' => null]];

        foreach ($this->isiHalaman($this->ac->coba($u, '/api/user/my-profile/sosmed', [], [])) as $s) {
            $tipe = strtoupper((string) ($s['type'] ?? ''));
            if (isset($hasil[$tipe])) {
                $hasil[$tipe] = ['path' => (string) ($s['path'] ?? ''), 'id' => $s['id'] ?? null];
            }
        }

        return $hasil;
    }

    /**
     * Simpan tautan media sosial ke AgriConnect (POST /api/user/my-profile/sosmed).
     *
     * Bentuk body disalin dari APK 1.0.2 (`submitSocialMedia`): array
     * `[{type, path, sosmedId}]`, `path` null berarti tautan dikosongkan.
     * Ini MENULIS ke database pusat — bukan pengajuan, tidak butuh approval.
     */
    public function profil_sosmed(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return redirect()->route('agri.hris.akun')
                ->with('galat', 'Hubungkan akun AgriConnect dulu sebelum menyimpan.');
        }

        $d = $request->validate([
            'facebook' => ['nullable', 'string', 'max:180'],
            'instagram' => ['nullable', 'string', 'max:180'],
            'linkedin' => ['nullable', 'string', 'max:180'],
        ], ['max' => ':attribute terlalu panjang.']);

        $lama = $this->sosmed($u);
        $body = [];
        foreach (['FACEBOOK' => 'facebook', 'INSTAGRAM' => 'instagram', 'LINKEDIN' => 'linkedin'] as $tipe => $kunci) {
            $nilai = trim((string) ($d[$kunci] ?? ''));
            $body[] = [
                'type' => $tipe,
                'path' => $nilai === '' ? null : $nilai,
                'sosmedId' => $lama[$tipe]['id'] ?? null,
            ];
        }

        try {
            $jawab = $this->ac->kirim($u, '/api/user/my-profile/sosmed', $body);
        } catch (\Throwable $e) {
            jurnal($u->id, 'AGRI_SOSMED', 'simpan', 'gagal', $request->ip() ?? '');

            return back()->withInput()->with('galat', 'Gagal menyimpan ke AgriConnect: '.$e->getMessage());
        }

        jurnal($u->id, 'AGRI_SOSMED', 'simpan', 'ok', $request->ip() ?? '');

        return back()->with('pesan', (string) ($jawab['message'] ?? 'Tautan media sosial tersimpan di AgriConnect.'));
    }

    /**
     * Ubah biodata pegawai — dipakai DUA formulir: Profil Pegawai (identitas)
     * dan BPJS & NPWP (pajak, jaminan, rekening).
     *
     * AgriConnect tidak punya "simpan profil" langsung. Yang ada di bundel APK
     * 1.0.2 (`src_app_profile_edit_edit-detail…js` → `submitForm("Biodata")`)
     * adalah PENGAJUAN: `POST /api/user/self-service/biodata`, berstatus
     * In Progress sampai disetujui SDM. Body-nya memuat SELURUH field personal
     * sekaligus, jadi payload di sini dibangun dari data yang sekarang ada di
     * server lalu hanya field yang dikirim formulir yang ditimpa. Mengirim
     * sebagian field saja akan mengosongkan sisanya di sisi AgriConnect.
     *
     * `bpjsKesehatanNo` tidak ada di `submitForm("Biodata")` versi APK, tetapi
     * endpoint detail pengajuan membacanya (`dataDetail.bpjsKesehatanNo`,
     * `previousBpjsKesehatanNo`) — jadi medan itu memang dikenal request ini.
     */
    public function biodata_simpan(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return redirect()->route('agri.hris.akun')
                ->with('galat', 'Hubungkan akun AgriConnect dulu sebelum mengirim perubahan.');
        }

        $d = $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
            'maritalDate' => ['nullable', 'date'],
            'birthPlace' => ['nullable', 'string', 'max:80'],
            'birthDate' => ['nullable', 'date'],
            'bloodType' => ['nullable', 'in:A,B,AB,O'],
            'mainPhone' => ['nullable', 'string', 'max:30'],
            'otherPhone' => ['nullable', 'string', 'max:30'],
            'personalMail' => ['nullable', 'email', 'max:120'],
            'nationalId' => ['nullable', 'string', 'max:32'],
            'familyId' => ['nullable', 'string', 'max:32'],
            'npwp' => ['nullable', 'string', 'max:32'],
            'bpjsKesehatanNo' => ['nullable', 'string', 'max:30'],
            'bank' => ['nullable', 'string', 'max:60'],
            'branchName' => ['nullable', 'string', 'max:60'],
            'accountNo' => ['nullable', 'string', 'max:40'],
            'accountName' => ['nullable', 'string', 'max:120'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $diri = $this->ac->coba($u, '/api/user/profile/personal', [], []);
        $diri = is_array($diri) ? $diri : [];

        try {
            $jawab = $this->ac->kirim($u, '/api/user/self-service/biodata', $this->payloadBiodata($diri, $d));
        } catch (\Throwable $e) {
            jurnal($u->id, 'AGRI_BIODATA', 'simpan', 'gagal', $request->ip() ?? '');

            return back()->withInput()->with('galat', 'Gagal mengirim perubahan ke AgriConnect: '.$e->getMessage());
        }

        jurnal($u->id, 'AGRI_BIODATA', 'simpan', 'ok', $request->ip() ?? '');

        return back()->with('pesan', $this->pesanAjuan($jawab, 'Perubahan biodata'));
    }

    /** Body pengajuan biodata: nilai server untuk field yang tidak diubah. */
    private function payloadBiodata(array $diri, array $ubah): array
    {
        $ambil = function (string $kunci) use ($diri, $ubah) {
            return array_key_exists($kunci, $ubah) ? $ubah[$kunci] : ($diri[$kunci] ?? null);
        };
        $tgl = fn ($v) => filled($v) ? Carbon::parse($v)->format('Y-m-d') : null;

        return [
            'maritalStatus' => $ambil('status'),
            'maritalDate' => $tgl($ambil('maritalDate')),
            'birthPlace' => $ambil('birthPlace'),
            'birthDate' => $tgl($ambil('birthDate')),
            'bloodType' => $ambil('bloodType'),
            'mainPhone' => $ambil('mainPhone'),
            'otherPhone' => $ambil('otherPhone'),
            'personalEmail' => $ambil('personalMail'),
            'nationalIdentifier' => $ambil('nationalId'),
            'familyCardNo' => $ambil('familyId'),
            'employeeTaxNo' => $ambil('npwp'),
            'bpjsKesehatanNo' => $ambil('bpjsKesehatanNo'),
            'bankName' => $ambil('bank'),
            'bankBranchName' => $ambil('branchName'),
            'bankAccountNo' => $ambil('accountNo'),
            'bankAccountName' => $ambil('accountName'),
            'remark' => $ubah['remark'] ?? null,
        ];
    }

    /** Pesan seragam untuk kiriman yang berbentuk pengajuan (bukan simpan langsung). */
    private function pesanAjuan(array $jawab, string $awalan): string
    {
        $pesan = (string) ($jawab['message'] ?? $awalan.' terkirim ke AgriConnect.');
        $id = $jawab['id'] ?? null;

        return filled($id)
            ? $pesan.' No. pengajuan '.$id.' — menunggu persetujuan SDM AgriConnect.'
            : $pesan.' Menunggu persetujuan SDM AgriConnect.';
    }

    // ------------------------------------------------------------ data keluarga

    /**
     * Data keluarga. Dipisah dari Profil Pegawai karena inilah yang benar-benar
     * dicari orang saat urusan HR: siapa yang terdaftar sebagai tanggungan, dan
     * siapa yang dihubungi kalau terjadi sesuatu.
     *
     * `emergencyContact` dari API dipakai untuk menandai kontak darurat — bukan
     * diasumsikan "pasangan" seperti di aplikasi aslinya.
     */
    public function keluarga(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return $this->belum($request);
        }

        $diri = $this->ac->coba($u, '/api/user/profile/personal', [], []);
        $anggota = $this->ac->coba($u, '/api/user/profile/family', [], []);
        $anggota = is_array($anggota) ? array_values(array_filter($anggota, 'is_array')) : [];

        // Urutkan kontak darurat lebih dulu — itu baris yang dicari saat panik.
        usort($anggota, fn ($a, $b) => (int) ($b['emergencyContact'] ?? false) <=> (int) ($a['emergencyContact'] ?? false));

        return view('agri.hris.keluarga', [
            'aktif' => 'agri',
            'diri' => is_array($diri) ? $diri : [],
            'anggota' => $anggota,
        ]);
    }

    /**
     * Tambah / ubah anggota keluarga — satu rute untuk dua aksi: `id` (UUID
     * anggota) ada di body berarti UBAH, kosong berarti TAMBAH.
     *
     * Jalur & bentuk body disalin dari bundel APK 1.0.2
     * (`submitForm("Family")`): `POST /api/user/self-service/family`, JSON,
     * `empFamilyId` = id anggota saat mengubah. Sama seperti biodata, ini
     * PENGAJUAN yang menunggu persetujuan SDM — bukan simpan langsung.
     */
    public function keluarga_simpan(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return redirect()->route('agri.hris.akun')
                ->with('galat', 'Hubungkan akun AgriConnect dulu sebelum mengirim perubahan.');
        }

        $d = $request->validate([
            'id' => ['nullable', 'uuid'],
            'relationship' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'emergencyContact' => ['nullable', 'boolean'],
            'phoneNo' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:Male,Female'],
            'birthPlace' => ['nullable', 'string', 'max:80'],
            'birthDate' => ['nullable', 'date'],
            'maritalStatus' => ['nullable', 'string', 'max:30'],
            'maritalDate' => ['nullable', 'date'],
            'decease' => ['nullable', 'boolean'],
            'nationalIdentifier' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:120'],
            'bpjsKesehatanNo' => ['nullable', 'string', 'max:30'],
            'faskes' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:255'],
        ], ['required' => ':attribute wajib diisi.']);

        $tgl = fn ($v) => filled($v) ? Carbon::parse($v)->format('Y-m-d') : null;
        $body = [
            'relationship' => $d['relationship'],
            'name' => $d['name'],
            'emergencyContact' => (bool) ($d['emergencyContact'] ?? false),
            'phoneNo' => $d['phoneNo'] ?? null,
            'gender' => $d['gender'] ?? null,
            'birthPlace' => $d['birthPlace'] ?? null,
            'birthDate' => $tgl($d['birthDate'] ?? null),
            'maritalStatus' => $d['maritalStatus'] ?? null,
            'maritalDate' => $tgl($d['maritalDate'] ?? null),
            'decease' => (bool) ($d['decease'] ?? false),
            'nationalIdentifier' => $d['nationalIdentifier'] ?? null,
            'email' => $d['email'] ?? null,
            'bpjsKesehatanNo' => $d['bpjsKesehatanNo'] ?? null,
            'faskes' => $d['faskes'] ?? null,
            'address' => $d['address'] ?? null,
            'remark' => $d['remark'] ?? null,
        ];
        if (filled($d['id'] ?? null)) {
            $body['empFamilyId'] = $d['id'];
        }

        try {
            $jawab = $this->ac->kirim($u, '/api/user/self-service/family', $body);
        } catch (\Throwable $e) {
            jurnal($u->id, 'AGRI_KELUARGA', 'simpan', 'gagal', $request->ip() ?? '');

            return back()->withInput()->with('galat', 'Gagal mengirim ke AgriConnect: '.$e->getMessage());
        }

        jurnal($u->id, 'AGRI_KELUARGA', 'simpan', 'ok', $request->ip() ?? '');

        $awalan = filled($d['id'] ?? null) ? 'Perubahan data keluarga' : 'Anggota keluarga baru';

        return back()->with('pesan', $this->pesanAjuan($jawab, $awalan));
    }

    // -------------------------------------------------------- jaminan & pajak

    /**
     * BPJS, NPWP, asuransi, dan rekening penggajian — satu halaman karena satu
     * urusan: berkas administrasi pegawai.
     *
     * Datanya dari `/api/user/profile/personal`; TIDAK ada endpoint tersendiri
     * untuk BPJS (sudah disondir: `/api/user/balance/*` hanya saldo cuti &
     * benefit, dan keduanya kosong di akun ini). Jadi halaman ini TIDAK
     * mengarang nomor: yang kosong dinyatakan kosong apa adanya, karena nomor
     * jaminan yang salah baca lebih berbahaya daripada kolom yang jelas kosong.
     */
    public function bpjs(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return $this->belum($request);
        }

        $d = $this->ac->coba($u, '/api/user/profile/personal', [], []);
        $d = is_array($d) ? $d : [];
        // Saldo cuti/benefit memang kosong untuk akun ini, tapi kalau kelak ada
        // isinya, ia muncul di halaman ini tanpa perubahan kode.
        $saldo = [
            'Cuti' => $this->ac->coba($u, '/api/user/balance/absence', [], []),
            'Benefit' => $this->ac->coba($u, '/api/user/balance/benefit', [], []),
        ];

        return view('agri.hris.bpjs', [
            'aktif' => 'agri', 'diri' => $d, 'saldo' => $saldo,
        ]);
    }

    // ----------------------------------------------------------------- cuti

    /**
     * Cuti, izin, dan sakit: pengajuan MILIK SENDIRI beserta statusnya.
     *
     * Bentuk kueri diambil apa adanya dari bundel APK 1.0.2
     * (`"/api/user/self-service/absence/status/my-request?type=Leave&status="`
     * + `orderBy`), termasuk `status` KOSONG di depan `&orderBy` — dengan nilai
     * status diisi, server menjawab 400 (sudah diuji: In Progress / Approved /
     * Rejected / Canceled semuanya 400). Halaman ini hanya MEMBACA; pengajuan
     * baru tetap lewat aplikasi AgriConnect, karena endpoint pengajuan
     * (`absence/v1/request`) hanya menerima POST dan itu bukan urusan halaman
     * baca ini.
     *
     * Tiga jenis diambil terpisah lalu disatukan supaya jenisnya terbaca di
     * baris, bukan tersembunyi di URL.
     */
    public function cuti(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return $this->belum($request);
        }

        $jenis = ['Leave' => 'Cuti', 'Permission' => 'Izin', 'Sick' => 'Sakit'];
        $baris = [];
        foreach ($jenis as $kode => $label) {
            $r = $this->ac->coba($u, '/api/user/self-service/absence/status/my-request?type='
                .$kode.'&status=&orderBy=DESC', [], []);
            foreach (is_array($r) ? $r : [] as $b) {
                if (! is_array($b)) continue;
                $b['_jenis'] = $label;
                $baris[] = $b;
            }
        }

        // Terbaru dulu. Urutan dari server tidak disandarkan pada `orderBy`:
        // tiga panggilan terpisah digabung di sini, jadi pengurutannya tetap
        // harus dilakukan sendiri.
        usort($baris, fn ($a, $b) => strcmp(
            (string) ($b['requestDate'] ?? $b['startDate'] ?? ''),
            (string) ($a['requestDate'] ?? $a['startDate'] ?? '')));

        $saldo = $this->ac->coba($u, '/api/user/balance/absence', [], []);

        return view('agri.hris.cuti', [
            'aktif' => 'agri',
            'baris' => $baris, 'jenis' => $jenis,
            'saldo' => is_array($saldo) ? $saldo : [],
        ]);
    }

    // ---------------------------------------------------------------- absensi

    public function absensi(Request $request)
    {
        $u = $request->user();

        // Pemantau (admin pusat, HR, PIC, keuangan) TIDAK punya akun AgriConnect
        // dan tidak perlu punya: yang mereka cari kehadiran para manajer. Datanya
        // dibaca dari cermin `agri_ac_absen` (lihat AbsenAc), bukan dari sesi
        // AgriConnect mereka sendiri — kalau tidak, admin melihat absen DIRINYA
        // dan mengira itu data manajer.
        if ($u->pemantauKehadiran()) {
            return $this->absensiPemantau($request);
        }

        if (! $this->ac->tersambung($u)) {
            return $this->belum($request);
        }

        // Rentang 6 bulan terakhir — cukup untuk melihat pola, dan satu panggilan.
        $akhir = now('Asia/Jakarta')->toDateString();
        $awal = now('Asia/Jakarta')->subMonthsNoOverflow(5)->startOfMonth()->toDateString();
        $kueri = ['start' => $awal, 'end' => $akhir];

        $baris = $this->ac->coba($u, '/api/user/time-sheet', $kueri, []);
        $baris = is_array($baris) ? array_values(array_filter($baris, 'is_array')) : [];

        // Sekalian jadi cermin untuk pemantau: data yang sudah di tangan tidak
        // perlu diambil dua kali. Satu baris per tanggal (upsert), jadi membuka
        // halaman berkali-kali tidak menggandakan rekap.
        AbsenAc::simpan($u->id, $baris);

        // Ringkasan diturunkan dari baris — bukan angka terpisah yang bisa beda.
        // Dihitung dari SELURUH periode, bukan dari hasil saringan: kartu angka
        // adalah keadaan periode, saringan hanya menyempitkan tabel di bawahnya.
        $ringkas = [
            'total' => count($baris),
            'hadir' => count(array_filter($baris, fn ($b) => ! empty($b['actualInTime']))),
            'alpha' => count(array_filter($baris, fn ($b) => ($b['isAlpha'] ?? false) === true)),
            'libur' => count(array_filter($baris, fn ($b) => ($b['isDayOff'] ?? false) === true)),
            'terlambat' => count(array_filter($baris, fn ($b) => ($b['isLateIn'] ?? false) === true)),
            'lembur' => array_sum(array_map(fn ($b) => (int) ($b['workingHoursOvtReal'] ?? 0) > 0
                || ($b['ovtRealStartTime'] ?? null) ? 1 : 0, $baris)),
            'jam' => $this->jumlahJam($baris),
        ];

        // Terbaru dulu: yang dicari di halaman ini hampir selalu "hari ini/kemarin".
        usort($baris, fn ($a, $b) => strcmp(
            (string) ($b['startDate'] ?? ''), (string) ($a['startDate'] ?? '')));

        $keadaan = [
            'hadir' => 'Hadir',
            'terlambat' => 'Terlambat',
            'alpha' => 'Alpha',
            'libur' => 'Libur',
            'kosong' => 'Belum absen',
        ];
        $daftar = \App\Support\Saring::olah(
            $baris,
            ['startDate', 'actualInTime', 'actualOutTime', 'remark', 'holiday', 'absenceCategory'],
            (string) $request->query('q', ''),
            function (array $b, string $nilai) {
                return match ($nilai) {
                    'hadir' => ! empty($b['actualInTime']),
                    'terlambat' => ($b['isLateIn'] ?? false) === true,
                    'alpha' => ($b['isAlpha'] ?? false) === true,
                    'libur' => ($b['isDayOff'] ?? false) === true,
                    'kosong' => empty($b['actualInTime']) && ($b['isAlpha'] ?? false) !== true
                        && ($b['isDayOff'] ?? false) !== true,
                    default => true,
                };
            },
            (string) $request->query('keadaan', ''),
        );

        return view('agri.hris.absensi', [
            'aktif' => 'agri',
            'baris' => $daftar['baris'], 'ringkas' => $ringkas,
            'awal' => $awal, 'akhir' => $akhir,
            'daftar' => $daftar, 'keadaan' => $keadaan,
            // Absen AgriConnect: keadaan hari ini + titik yang akan dipakai,
            // supaya pengguna melihat titik mana yang tercatat SEBELUM menekan.
            'cico' => $this->ac->hariIni($u),
            'titik' => $this->ac->titik($u),
            // Pilihan "Tujuan" Report CheckInOut — dari server, daftar yang sama
            // dengan aplikasi AgriConnect.
            'tujuan' => $this->ac->tujuan($u),
        ]);
    }

    /**
     * Absensi AgriConnect untuk PEMANTAU: daftar kehadiran orang lain.
     *
     * Datanya dari cermin `agri_ac_absen` — diisi saat orang yang bersangkutan
     * membuka halaman Absensinya, atau oleh `php artisan hub:tarik-absen`.
     * Halaman ini hanya MEMBACA: tidak ada tombol absen, tidak ada kirim.
     *
     * Dua tampilan dalam satu rute (pola yang sama dengan Presensi): tanpa
     * `?orang=` berupa rekap semua orang; dengan `?orang=<id>` rincian harian
     * satu orang. Tidak menambah rute kedua supaya menu & tautan lama tetap satu.
     */
    private function absensiPemantau(Request $request)
    {
        $cari = trim((string) $request->query('q', ''));
        $hari = now('Asia/Jakarta')->toDateString();
        $awalBulan = now('Asia/Jakarta')->startOfMonth()->toDateString();

        // Daftar orang = yang PUNYA baris di cermin. Menampilkan seluruh 46 akun
        // Hub hanya akan berisi "belum ada data" — yang dicari pemantau adalah
        // orang yang absennya benar-benar tercatat di AgriConnect.
        $dasar = User::whereIn('id', function ($q) {
            $q->select('user_id')->from('agri_ac_absen');
        });

        $orangId = (int) $request->query('orang', 0);
        if ($orangId > 0) {
            $orang = (clone $dasar)->whereKey($orangId)->first();
            if ($orang === null) {
                return redirect()->route('agri.hris.absensi')
                    ->with('galat', 'Orang itu belum punya data absensi AgriConnect di Hub.');
            }

            $h = Halaman::buat(
                DB::connection('hub')->table('agri_ac_absen')->where('user_id', $orang->id)->count(),
                $request->query('halaman', 1),
            );
            $rincian = DB::connection('hub')->table('agri_ac_absen')
                ->where('user_id', $orang->id)
                ->orderByDesc('tanggal')
                ->limit($h->perHalaman)->offset($h->offset())
                ->get();

            return view('agri.hris.absensi-pemantau', [
                'aktif' => 'agri',
                'cari' => $cari, 'orang' => $orang,
                'rincian' => $rincian, 'halaman' => $h,
                'koperasi' => Koperasi::where('manajer_id', $orang->id)->first(),
            ]);
        }

        if ($cari !== '') {
            $dasar->where(fn ($x) => $x
                ->where('nama', 'like', '%'.$cari.'%')
                ->orWhere('email', 'like', '%'.$cari.'%'));
        }

        $jumlah = (clone $dasar)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1));
        $baris = $halaman->batasi((clone $dasar)->orderBy('nama'))->get();
        $ids = $baris->pluck('id')->all();

        // Dua kueri agregat, bukan satu per orang: 10 baris per halaman tidak
        // boleh berarti 10 (atau 46) kueri tambahan.
        $hariIni = DB::connection('hub')->table('agri_ac_absen')
            ->whereIn('user_id', $ids)->where('tanggal', $hari)->get()->keyBy('user_id');

        $rekap = DB::connection('hub')->table('agri_ac_absen')
            ->whereIn('user_id', $ids)->whereBetween('tanggal', [$awalBulan, $hari])
            ->selectRaw('user_id, sum(case when masuk <> \'\' then 1 else 0 end) as hadir')
            ->selectRaw('sum(telat) as telat, sum(alpha) as alpha, max(disinkron) as sinkron')
            ->groupBy('user_id')->get()->keyBy('user_id');

        return view('agri.hris.absensi-pemantau', [
            'aktif' => 'agri',
            'cari' => $cari, 'orang' => null,
            'baris' => $baris, 'hariIni' => $hariIni, 'rekap' => $rekap,
            'jumlah' => $jumlah, 'halaman' => $halaman,
            'hari' => $hari, 'awalBulan' => $awalBulan,
        ]);
    }

    /**
     * Absen Masuk / Absen Pulang AgriConnect dari halaman Absensi.
     *
     * Foto WAJIB (AgriConnect menolaknya tanpa berkas) dan keterangan wajib —
     * baris absen tanpa keterangan tidak bisa dibedakan dari lupa isi.
     * Titik lokasi diambil server dari absen terakhir yang sah, bukan dari
     * peramban: lihat Agriconnect::titik().
     */
    public function absensi_simpan(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return redirect()->route('agri.hris.akun')
                ->with('galat', 'Hubungkan akun AgriConnect dulu sebelum absen.');
        }

        $d = $request->validate([
            'tipe' => ['required', 'in:CHECKIN,CHECKOUT'],
            'ket' => ['required', 'string', 'max:190'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'required' => ':attribute wajib diisi.',
            'foto.image' => 'Foto harus berkas gambar (JPG/PNG).',
            'foto.mimes' => 'Foto harus JPG atau PNG.',
            'foto.max' => 'Foto terlalu besar (maks 4 MB).',
            'ket.max' => 'Keterangan maksimal 190 huruf.',
        ], ['tipe' => 'jenis absen', 'ket' => 'keterangan', 'foto' => 'foto']);

        $jenis = $d['tipe'] === 'CHECKIN' ? 'Absen masuk' : 'Absen pulang';

        // Penjaga ganda: halaman yang sudah lama terbuka masih memuat tombol
        // yang sama. Tanpa ini, dua klik = dua baris absen di AgriConnect dan
        // tidak ada cara menghapusnya dari Hub.
        $kini = $this->ac->hariIni($u);
        $sudah = $d['tipe'] === 'CHECKIN' ? $kini['masuk'] : $kini['pulang'];
        if ($sudah !== null) {
            return back()->with('galat', $jenis.' hari ini sudah tercatat di AgriConnect ('
                .\Illuminate\Support\Carbon::createFromTimestampMs((int) ($sudah['actualTimeMilliSecond'] ?? 0), 'UTC')
                    ->setTimezone('Asia/Jakarta')->format('H:i').'). Tidak dikirim ulang.');
        }

        try {
            $titik = isset($d['lat'], $d['lng']) ? [(string) $d['lat'], (string) $d['lng']] : null;
            $hasil = $this->ac->absen($u, $d['tipe'], (string) file_get_contents($request->file('foto')->getRealPath()), $d['ket'], $titik);
        } catch (\Throwable $e) {
            jurnal($u->id, 'AGRI_ABSEN', $d['tipe'], 'gagal', $request->ip() ?? '');

            return back()->with('galat', $jenis.' AgriConnect gagal: '.$e->getMessage());
        }

        jurnal($u->id, 'AGRI_ABSEN', $d['tipe'], 'ok', $request->ip() ?? '');

        return back()->with('pesan', $jenis.' AgriConnect tercatat ('.$hasil['id'].') · titik '
            .$hasil['titik'][0].', '.$hasil['titik'][1].'.');
    }

    /**
     * Report CheckInOut (Check In / Check Out Report) — laporan absen susulan
     * untuk hari yang tidak bisa diabsen dari aplikasi (belum ada akses, belum
     * bisa login). Dikirim ke jalur yang SAMA dengan aplikasi AgriConnect
     * (`backdate/v1/request`), jadi hasilnya masuk ke data server yang sama dan
     * menunggu persetujuan SDM seperti di APK.
     */
    public function absensi_lapor(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return redirect()->route('agri.hris.akun')
                ->with('galat', 'Hubungkan akun AgriConnect dulu sebelum mengirim laporan absen.');
        }

        $d = $request->validate([
            'jenis' => ['required', 'in:CHECKIN,CHECKOUT'],
            'tgl' => ['required', 'date_format:Y-m-d\TH:i'],
            'tujuan' => ['required', 'string', 'max:20'],
            'alasan' => ['required', 'string', 'max:190'],
            'lampiran' => ['nullable', 'array', 'max:5'],
            'lampiran.*' => ['image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ], [
            'required' => ':attribute wajib diisi.',
            'lampiran.max' => 'Lampiran maksimal 5 gambar.',
            'lampiran.*.image' => 'Lampiran harus berkas gambar (JPG/PNG).',
            'lampiran.*.mimes' => 'Lampiran harus JPG atau PNG.',
            'lampiran.*.max' => 'Tiap lampiran maksimal 4 MB.',
            'alasan.max' => 'Alasan/keterangan maksimal 190 huruf.',
            'tgl.date_format' => 'Tanggal absen tidak terbaca.',
        ], ['jenis' => 'jenis absen', 'tgl' => 'tanggal absen', 'tujuan' => 'tujuan',
            'alasan' => 'alasan/keterangan', 'lampiran' => 'lampiran']);

        // Tanggal di masa depan tidak masuk akal untuk laporan susulan. Dibandingkan
        // sebagai teks "Y-m-d\TH:i" (format yang urut secara harfiah) karena jam
        // aplikasi UTC sedangkan isian pengguna WIB.
        if ($d['tgl'] > now('Asia/Jakarta')->format('Y-m-d\TH:i')) {
            return back()->withInput()->with('galat', 'Tanggal absen tidak boleh melewati waktu sekarang.');
        }

        // Daftar tujuan yang sah = daftar server; kalau daftar tak terbaca (mis.
        // sesi baru saja mati), kirim kode apa adanya daripada menolak laporan.
        $sah = array_keys($this->ac->tujuan($u));
        if ($sah !== [] && ! in_array($d['tujuan'], $sah, true)) {
            return back()->withInput()->with('galat', 'Tujuan itu tidak ada di daftar AgriConnect.');
        }

        $lampiran = [];
        foreach ((array) $request->file('lampiran', []) as $f) {
            if ($f !== null && $f->isValid()) {
                $lampiran[] = ['isi' => (string) file_get_contents($f->getRealPath()),
                    'nama' => 'lampiran-'.count($lampiran).'.'.$f->getClientOriginalExtension()];
            }
        }

        $jenis = $d['jenis'] === 'CHECKIN' ? 'Check In Report' : 'Check Out Report';

        try {
            $hasil = $this->ac->lapor($u, $d['jenis'], $d['tgl'].':00', $d['tujuan'],
                $d['alasan'], $lampiran);
        } catch (\Throwable $e) {
            jurnal($u->id, 'AGRI_LAPOR', $d['jenis'], 'gagal', $request->ip() ?? '');

            return back()->withInput()->with('galat', $jenis.' gagal dikirim: '.$e->getMessage());
        }

        jurnal($u->id, 'AGRI_LAPOR', $d['jenis'], 'ok', $request->ip() ?? '');

        return back()->with('pesan', $jenis.' terkirim ke AgriConnect ('.$hasil['id']
            .') · '.count($lampiran).' lampiran. Menunggu persetujuan SDM.');
    }

    /** Total jam kerja terbaca "HH:MM:SS" -> jam desimal, supaya bisa dijumlahkan. */
    private function jumlahJam(array $baris): float
    {
        $detik = 0;
        foreach ($baris as $b) {
            $w = (string) ($b['workingHours'] ?? '');
            if (preg_match('/^(\d+):(\d+):(\d+)$/', $w, $m)) {
                $detik += ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
            }
        }

        return round($detik / 3600, 1);
    }

    // --------------------------------------------------------------- performa

    public function performa(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return $this->belum($request);
        }

        $data = [
            'tujuan' => $this->ac->coba($u, '/api/user/goal', [], []),
            'selesai' => $this->ac->coba($u, '/api/user/evaluation', [], []),
            'berjalan' => $this->ac->coba($u, '/api/user/evaluation/in-progress', [], []),
            'riwayat' => $this->ac->coba($u, '/api/user/evaluation/history', [], []),
            'survei' => $this->ac->coba($u, '/api/user/evaluation/survey', [], []),
            'umpan' => $this->ac->coba($u, '/api/user/performance/feedback', [], []),
            'riwayat_umpan' => $this->ac->coba($u, '/api/user/performance/feedback/history', [], []),
            'asesmen' => $this->ac->coba($u, '/api/user/career/assessments', [], []),
            'evaluasi_karir' => $this->ac->coba($u, '/api/user/career/evaluations', [], []),
        ];

        // Spring pageable -> ambil array isinya saja, satu bentuk untuk view.
        foreach ($data as $k => $v) {
            $data[$k] = $this->isiHalaman($v);
        }

        return view('agri.hris.performa', [
            'aktif' => 'agri', 'data' => $data,
        ]);
    }

    /** {content:[…],totalElements:n} -> list; list tetap list; lain -> []. */
    private function isiHalaman(mixed $v): array
    {
        if (! is_array($v)) {
            return [];
        }
        if (isset($v['content']) && is_array($v['content'])) {
            return array_values(array_filter($v['content'], 'is_array'));
        }

        return array_values(array_filter($v, 'is_array'));
    }

    // ------------------------------------------------------ pengajuan (semua)

    /**
     * SELURUH pengajuan mandiri pegawai dalam satu daftar: cuti/izin/sakit,
     * lembur, time off, klaim biaya, pinjaman, tunjangan, tukar shift, koreksi
     * absen, pemakaian ruang kerja, dan perjalanan dinas.
     *
     * Satu halaman, bukan sebelas: semuanya berbentuk sama (diajukan → disetujui
     * /ditolak) dan yang dicari orang adalah "pengajuan saya yang mana yang
     * masih menggantung" — bukan satu menu per jenis.
     *
     * Semua jalur di bawah sudah diuji dengan akun Manajer KDKMP nyata
     * (2026-10-21): 200. `status` sengaja KOSONG di tengah kueri — diisi nilai
     * apa pun server menjawab 400 (bentuk kueri disalin dari bundel APK 1.0.2).
     * `business-trip` memakai jalur `/status` yang berbeda bentuk.
     */
    public function pengajuan(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return $this->belum($request);
        }

        $jalur = [
            'Cuti' => '/api/user/self-service/absence/status/my-request?type=Leave&status=&orderBy=DESC',
            'Izin' => '/api/user/self-service/absence/status/my-request?type=Permission&status=&orderBy=DESC',
            'Sakit' => '/api/user/self-service/absence/status/my-request?type=Sick&status=&orderBy=DESC',
            'Lembur' => '/api/user/self-service/overtime/status/my-request?status=&orderBy=DESC',
            'Time off' => '/api/user/self-service/time-off/status/my-request?status=&orderBy=DESC',
            'Klaim biaya' => '/api/user/self-service/expense/status/my-request?status=&orderBy=DESC',
            'Pinjaman' => '/api/user/self-service/loan/status/my-request?status=&orderBy=DESC',
            'Tunjangan' => '/api/user/self-service/benefit/status/my-request?status=&orderBy=DESC',
            'Tukar shift' => '/api/user/self-service/shift-change/status/my-request?status=&orderBy=DESC',
            'Koreksi absen' => '/api/user/self-service/check-in-out/status/my-request?status=&orderBy=DESC',
            'Ruang kerja' => '/api/user/self-service/workspace/status/my-request?status=&orderBy=DESC',
            'Perjalanan dinas' => '/api/user/self-service/business-trip/status',
        ];

        $baris = [];
        $gagal = [];
        foreach ($jalur as $label => $j) {
            $r = $this->ac->coba($u, $j, [], null);
            if ($r === null) {
                $gagal[] = $label;

                continue;
            }
            foreach ($this->isiHalaman($r) as $b) {
                $b['_jenis'] = $label;
                $baris[] = $b;
            }
        }

        // Terbaru dulu. Urutan server tidak bisa diandalkan: 12 panggilan
        // terpisah digabung di sini.
        usort($baris, fn ($a, $b) => strcmp(
            (string) ($b['requestDate'] ?? $b['createdDate'] ?? $b['date'] ?? ''),
            (string) ($a['requestDate'] ?? $a['createdDate'] ?? $a['date'] ?? '')));

        $status = static fn (array $b) => strtolower(trim((string) (
            $b['status'] ?? $b['approvalStatus'] ?? $b['requestStatus'] ?? '')));

        $ringkas = [
            'total' => count($baris),
            'menunggu' => count(array_filter($baris, fn ($b) => in_array($status($b), ['', 'in progress', 'waiting', 'pending', 'submitted'], true))),
            'disetujui' => count(array_filter($baris, fn ($b) => in_array($status($b), ['approved', 'disetujui'], true))),
            'ditolak' => count(array_filter($baris, fn ($b) => in_array($status($b), ['rejected', 'canceled', 'ditolak', 'dibatalkan'], true))),
        ];

        $keadaan = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
        $daftar = \App\Support\Saring::olah(
            $baris,
            ['_jenis', 'reason', 'note', 'remark', 'title', 'status', 'startDate'],
            (string) $request->query('q', ''),
            fn (array $b, string $nilai) => match ($nilai) {
                'menunggu' => in_array($status($b), ['', 'in progress', 'waiting', 'pending', 'submitted'], true),
                'disetujui' => in_array($status($b), ['approved', 'disetujui'], true),
                'ditolak' => in_array($status($b), ['rejected', 'canceled', 'ditolak', 'dibatalkan'], true),
                default => true,
            },
            (string) $request->query('keadaan', ''),
        );

        return view('agri.hris.pengajuan', [
            'aktif' => 'agri',
            'ringkas' => $ringkas, 'daftar' => $daftar, 'keadaan' => $keadaan,
            'jenis' => array_keys($jalur), 'gagal' => $gagal,
            // Berapa pengajuan BAWAHAN yang menunggu keputusan saya — angka ini
            // yang membuat halaman ini bukan sekadar arsip pribadi.
            'perluSaya' => (int) $this->ac->coba($u, '/api/user/self-service/data-approval/count-need-approval', [], 0),
        ]);
    }

    // ------------------------------------------------- penggajian & pajak

    /**
     * Slip gaji dan bukti potong pajak 1721-A1 milik sendiri.
     *
     * Dulu menu ini berstatus "belum ada sumbernya". Sekarang sumbernya ada
     * (`/self-service/payslip`, `/self-service/1721a1/tax-year` — keduanya 200),
     * jadi halamannya dibuat. Kalau payroll belum menerbitkan slip untuk akun
     * ini, daftarnya kosong dan itu dinyatakan apa adanya.
     */
    public function gaji(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return $this->belum($request);
        }

        return view('agri.hris.gaji', [
            'aktif' => 'agri',
            'slip' => $this->isiHalaman($this->ac->coba($u, '/api/user/self-service/payslip', [], [])),
            'pajak' => $this->isiHalaman($this->ac->coba($u, '/api/user/self-service/1721a1/tax-year', [], [])),
            'saldo' => [
                'Cuti' => $this->ac->coba($u, '/api/user/balance/absence', [], []),
                'Benefit' => $this->ac->coba($u, '/api/user/balance/benefit', [], []),
            ],
        ]);
    }

    // ------------------------------------------------------------- informasi

    /**
     * Informasi pegawai: notifikasi, pengumuman (Starfeed), pegawai baru, poin,
     * ruang kerja, marketplace/relawan, dan regulasi.
     *
     * Semua endpoint di halaman ini diuji 2026-10-21 dan menjawab 200 untuk akun
     * Manajer KDKMP — termasuk yang isinya masih kosong. Chat TIDAK ada di sini:
     * `/api/user/chat*` menjawab **503** (layanan obrolan mereka sedang mati),
     * jadi halamannya tidak dibuat supaya tidak menjanjikan yang tidak ada.
     */
    public function info(Request $request)
    {
        $u = $request->user();
        if (! $this->ac->tersambung($u)) {
            return $this->belum($request);
        }

        // Judul/isi notifikasi datang sebagai HTML (<b>, <span>) — dibersihkan
        // di sini, sekali, supaya view tidak perlu memanggil strip_tags.
        $notif = $this->isiHalaman($this->ac->coba($u, '/api/user/notification', ['size' => 50], []));
        foreach ($notif as $i => $n) {
            $notif[$i]['notificationTitle'] = trim(strip_tags((string) ($n['notificationTitle'] ?? '')));
            $notif[$i]['notificationBody'] = trim(strip_tags((string) ($n['notificationBody'] ?? '')));
        }

        return view('agri.hris.info', [
            'aktif' => 'agri',
            'notif' => $notif,
            'feed' => $this->isiHalaman($this->ac->coba($u, '/api/user/starfeed', ['size' => 50], [])),
            'kategori' => $this->isiHalaman($this->ac->coba($u, '/api/user/starfeed/category', [], [])),
            'baru' => $this->isiHalaman($this->ac->coba($u, '/api/user/profile/new-joiner/home', [], [])),
            'poin' => (array) $this->ac->coba($u, '/api/user/point-systems/header', [], []),
            'poinRiwayat' => $this->isiHalaman($this->ac->coba($u, '/api/user/point-systems/history', [], [])),
            'ruang' => $this->isiHalaman($this->ac->coba($u, '/api/user/self-service/workspace', [], [])),
            'event' => $this->isiHalaman($this->ac->coba($u, '/api/user/volunteer/all-event', [], [])),
            'lelang' => $this->isiHalaman($this->ac->coba($u, '/api/user/auctions/ongoing', [], [])),
            'regulasi' => $this->isiHalaman($this->ac->coba($u, '/api/user/regulation', [], [])),
        ]);
    }

    // ------------------------------------------------------------------ bantu

    private function belum(Request $request)
    {
        return view('agri.hris.belum', [
            'aktif' => 'agri',
            'kembali' => url()->previous(),
        ]);
    }

    /**
     * Peta menu aplikasi AgriConnect: judul, bagian (section) di aplikasi,
     * dan di mana ia hidup di Hub. Diturunkan dari daftar menu bundel APK 1.0.2
     * (`this.menu=[…]` di main.js) — jadi urutan & pengelompokannya sama dengan
     * yang dilihat pegawai di aplikasi, bukan karangan.
     *
     * `ada` = rute Hub yang mengerjakan hal yang sama; null berarti belum ada
     * padanannya dan itu dinyatakan terbuka di halaman, bukan disembunyikan.
     */
    public function petaMenu(User $u): array
    {
        return [
            ['Time', 'Check In / Check Out', 'agri.hris.absensi', 'Absen Agriconnect — absen masuk/pulang langsung ke AgriConnect'],
            ['Time', 'Overtime', 'agri.hris.pengajuan', 'Pengajuan — lembur beserta status persetujuannya'],
            ['Time', 'Timesheet', 'agri.hris.absensi', 'Absen Agriconnect — kalender jam kerja dari AgriConnect'],
            ['Top Services', 'Self Service', 'agri.hris.pengajuan', 'Pengajuan — 12 jenis pengajuan mandiri dalam satu daftar'],
            ['Top Services', 'Balance', 'agri.hris.bpjs', 'BPJS & NPWP — saldo cuti dan benefit'],
            ['Top Services', 'Status', 'agri.hris.pengajuan', 'Pengajuan — status tiap pengajuan'],
            ['Performance', 'Goal', 'agri.hris.performa', 'Performa — tujuan & capaian'],
            ['Performance', 'Performance', 'agri.hris.performa', 'Performa — penilaian kinerja'],
            ['Performance', 'Feedback', 'agri.hris.performa', 'Performa — umpan balik yang diterima'],
            ['Career', 'Assessment', 'agri.hris.performa', 'Performa — asesmen karir'],
            ['Career', 'Evaluation', 'agri.hris.performa', 'Performa — evaluasi karir'],
            ['Information', 'Notification', 'agri.hris.info', 'Informasi — notifikasi AgriConnect'],
            ['Information', 'Birthday', 'agri.ulangtahun', 'Ulang Tahun — rekan kerja sebulan ini'],
            ['Information', 'News & Career Update', 'agri.hris.info', 'Informasi — pengumuman Starfeed'],
            ['Information', 'New Joiner', 'agri.hris.info', 'Informasi — pegawai baru'],
            ['Top Services', 'Interview', 'agri.hris.info', 'Informasi — jadwal wawancara (kosong bila bukan pewawancara)'],
            ['Top Services', 'Business Trip', 'agri.hris.pengajuan', 'Pengajuan — dinas & status persetujuannya'],
            ['Top Services', 'Payslip & Pajak (1721-A1)', 'agri.hris.gaji', 'Penggajian — slip gaji & bukti potong pajak'],
            ['Top Services', 'Loan, Benefit, MPP, Expense', 'agri.hris.pengajuan', 'Pengajuan — pinjaman, tunjangan, MPP, dan klaim biaya'],
            ['Top Services', 'Point & Level', 'agri.hris.info', 'Informasi — poin, level, dan riwayatnya'],
            ['—', 'Regulation', 'agri.hris.info', 'Informasi — regulasi perusahaan'],
            ['—', 'E-Learning', 'agri.pelatihan', 'E-Learning — pelajaran + progres'],
            ['—', 'Workspace & Room Booking', 'agri.hris.info', 'Informasi — ruang kerja & pemesanan'],
            ['—', 'Approval', 'agri.hris.pengajuan', 'Pengajuan — keputusan pengajuan bawahan ada di halaman terkait'],
            ['—', 'Hierarchy & My Team', 'agri.struktur', 'Struktur — peta pengelola'],
            ['—', 'Volunteer & Marketplace', 'agri.hris.info', 'Informasi — relawan, lelang, dan marketplace'],
            ['—', 'Chat & Friends', 'agri.ruang', 'Kolaborasi Hub. Chat AgriConnect belum bisa dipakai: /api/user/chat menjawab 503'],
        ];
    }
}

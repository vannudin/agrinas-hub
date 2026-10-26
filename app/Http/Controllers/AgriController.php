<?php

namespace App\Http\Controllers;

use App\Models\AgriBerkas;
use App\Models\AgriFokus;
use App\Models\AgriImbalan;
use App\Models\AgriMateri;
use App\Models\AgriPengajuan;
use App\Models\AgriPoin;
use App\Models\AgriPresensi;
use App\Models\AgriTugas;
use App\Models\AgriUmpan;
use App\Models\Domain;
use App\Models\Koperasi;
use App\Models\SimkopdesAbsensi as BarisAbsen;
use App\Models\User;
use App\Services\SimkopdesAbsensi;
use App\Support\Halaman;
use App\Support\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Modul AgriConnect — layanan mandiri pegawai, diadopsi dari aplikasi
 * AgriConnect / Agrinas HRIS ke Agrinas Hub.
 *
 * Peta adopsinya sengaja TIDAK satu menu per layar aplikasi. Aplikasi Android
 * memecah layar per paket (Silver/Gold) dan per wewenang, jadi satu pekerjaan
 * yang sama muncul di beberapa menu. Di Hub dipetakan ke SEPULUH halaman yang
 * masing-masing menyelesaikan satu pekerjaan:
 *
 *   Presensi              Check In / Check Out (Time)
 *   Tugas Saya            Task + Overtime + Reminder — di aplikasi tiga menu,
 *                         isinya satu: daftar pekerjaan berwaktu milik sendiri.
 *   Perjalanan Dinas      Business Trip (pengajuan + persetujuan)
 *   Imbalan & Poin        Balance + StarPoint + Marketplace + Klaim Imbalan
 *   Umpan Balik           Feedback
 *   Regulasi              Regulation (berkas panduan/regulasi)
 *   E-Learning            E-Learn (pelajaran berbasis teks + progres)
 *   Kolaborasi            Messenger, Docs & Sheets, Kalender, Approval —
 *                         DIARAHKAN ke Ruang Kerja yang sudah mengerjakannya,
 *                         bukan disalin jadi modul kedua yang lebih lemah.
 *   Struktur & Tim        Hierarchy + My Team + Direktori — halaman yang sama
 *                         dengan Koperasi & Akun, ditampilkan dari sisi pegawai.
 *
 * Dua kelompok fitur aplikasi TIDAK diadopsi, dan ini keputusan sadar:
 *   - Absensi payroll, slip gaji, financial wellness: tidak ada sumber datanya
 *     di hub ini. Menyalin layarnya hanya menghasilkan halaman kosong.
 *   - Onboarding/Offboarding, Career, Interview: alur kepegawaian HR formal
 *     yang di luar lingkup hub koordinasi koperasi.
 * Keduanya disebutkan apa adanya di halaman Ringkasan supaya tidak ada yang
 * menebak-nebak ke mana perginya menu itu.
 *
 * Lingkup: hampir semua isi modul ini MILIK PENGGUNA SENDIRI (presensi, tugas,
 * poin, pengajuan, umpan). Hanya persetujuan pengajuan, katalog hadiah, berkas
 * regulasi, dan materi pelajaran yang lintas pengguna — tiga terakhir dikelola
 * admin Agrinas, persetujuan mengikuti wilayah/koperasi seperti modul lain.
 */
class AgriController extends Controller
{
    private const DISK = 'r2';

    /**
     * Bobot poin. Angka kecil & tetap supaya bisa dijelaskan ke pengguna, dan
     * `public` karena halaman presensi menampilkan besarannya.
     */
    public const POIN_PRESENSI = 10;
    public const POIN_TUGAS = 5;
    public const POIN_UMPAN = 2;
    public const POIN_LANGKAH = 2;

    /** Batas jam masuk; dipakai rekap Kinerja untuk menghitung keterlambatan. */
    public const JAM_MASUK = '08:00';

    /**
     * Batas foto presensi. `upload_max_filesize` PHP di kontainer ini hanya 2M,
     * jadi foto ponsel (2-8 MB) DITOLAK server sebelum sampai ke Laravel —
     * karena itu peramban mengecilkannya lebih dulu (canvas, lihat
     * public/js/hub-agri.js) sampai di bawah angka ini. Angka 1.5 MB dipilih
     * di bawah 2M agar tidak ada berkas yang jatuh tepat di batas.
     */
    private const FOTO_MAKS_KB = 1536;

    /**
     * Toleransi selisih jam perangkat terhadap jam server (menit). Waktu yang
     * dicatat adalah waktu PERANGKAT — itu yang diminta, dan itu pula yang
     * membetulkan jam check-in (server ini berzona UTC, jadi sebelumnya jam
     * masuk tersimpan 7 jam lebih awal dari WIB). Tapi waktu perangkat tidak
     * boleh dipercaya buta: kalau jamnya melenceng lebih dari toleransi ini,
     * presensinya DITOLAK. Kenop kalibrasi — HP dengan jam kronis melenceng
     * perlu dibetulkan (jam otomatis/NTP), bukan ditoleransi.
     */
    public const TOLERANSI_MENIT = 15;

    /**
     * Penanda otomasi di User-Agent. Presensi hanya boleh dari peramban biasa:
     * peramban yang dikendalikan skrip (headless/WebDriver) memudahkan
     * permainan lokasi dan bisa mengirim foto apa pun, jadi permintaannya
     * ditolak. Daftarnya SENGAJA sempit — hanya penanda yang mustahil muncul di
     * peramban orang sungguhan, supaya pegawai yang membuka tautan dari
     * peramban dalam aplikasi (WhatsApp/Instagram) tidak ikut terblokir.
     */
    private const UA_OTOMASI = ['headlesschrome', 'phantomjs', 'webdriver', 'selenium',
        'puppeteer', 'playwright', 'electron/', 'cypress'];

    /** Akurasi GPS terburuk yang masih dianggap bukti titik lokasi (meter). */
    private const AKURASI_MAKS = 500;

    /**
     * Halaman modul; dipakai bilah modul di view.
     *
     * Bentuknya `[kunci, rute, label, keterangan, seksi]`. `seksi` = judul
     * kelompok di kisi menu; string kosong berarti butir itu berdiri sendiri
     * (Ringkasan). Dulu kisi ini satu deret 17 kartu tanpa pengelompokan, dan
     * dengan tambahan modul kepegawaian ia jadi dinding kartu yang tidak
     * memberi tahu mana urusan kehadiran dan mana data pribadi.
     */
    public const MODUL = [
        ['ringkasan', 'agri.beranda', 'Ringkasan', 'Ikhtisar layanan mandiri pegawai dan apa saja yang tersedia di modul ini.', ''],
        // Butir ini BUKAN tautan halaman, melainkan pembuka <dialog> login
        // (lihat agri/_nav.blade.php + agri/_hubung.blade.php). Rutenya tetap
        // `agri.hris.akun` karena halaman itu masih hidup sebagai status sesi
        // dan tujuan `route()`; Bos meminta butirnya berdiri di samping
        // Ringkasan (2026-10-21), bukan di kelompok Layanan.
        ['hris-hubungkan', 'agri.hris.akun', 'Hubungkan AgriConnect', 'Masuk sekali dengan akun AgriConnect; seluruh layanan mandiri pegawai langsung terbuka.', ''],

        // ---- Kehadiran: absen, cuti/sakit/izin, dan perjalanan dinas.
        // Dua absen memang harus tetap ada dua, karena sumbernya berbeda dan
        // keduanya diakui pengguna: Hub (foto sendiri) dan SIMKOPDES (daftar
        // hadir resmi KDKMP). Labelnya menyebut sumbernya supaya tidak
        // tertukar. Kunci 'presensi' & 'hris-absensi' SENGAJA tidak diubah —
        // rute, gate (uji_presensi.py, uji_absen_kdkmp.php, uji_agri_hris.sh),
        // dan rute foto semuanya memakai kunci itu.
        ['presensi', 'agri.presensi', 'Absen Simkopdes', 'Check In & Check Out mandiri dengan foto kamera & titik lokasi, plus daftar hadir KDKMP dari SIMKOPDES — satu halaman, satu foto potret.', 'Kehadiran'],
        // Absen KETIGA (permintaan Bos 2026-10-21): milik sistem ini sendiri,
        // jadi tetap bekerja walau SIMKOPDES/AgriConnect tidak bisa dihubungi.
        // Bentuknya kejadian — istirahat punya tempat, jam kerja bersih dihitung.
        ['absen-hub', 'agri.absen-hub', 'Absen Agrinashub', 'Absen milik sistem kita sendiri: masuk, istirahat, dan keluar dengan foto & titik lokasi. Jam kerja bersih dihitung otomatis.', 'Kehadiran'],
        ['hris-absensi', 'agri.hris.absensi', 'Absen Agriconnect', 'Kalender kehadiran AgriConnect: jam masuk/pulang, jadwal shift, lembur, hari libur.', 'Kehadiran'],
        ['hris-cuti', 'agri.hris.cuti', 'Cuti & Izin', 'Pengajuan cuti, izin, dan sakit milik sendiri beserta status persetujuannya, dari AgriConnect.', 'Kehadiran'],
        // Pengajuan lain (lembur, klaim, pinjaman, tunjangan, dinas, tukar shift,
        // koreksi absen, ruang kerja) semuanya berbentuk sama — satu halaman,
        // bukan sebelas menu. Sumbernya jalur `.../status/my-request` AgriConnect.
        ['hris-pengajuan', 'agri.hris.pengajuan', 'Pengajuan', 'Lembur, klaim biaya, pinjaman, tunjangan, dinas, tukar shift, dan koreksi absen — status persetujuannya dari AgriConnect.', 'Kehadiran'],
        ['hris-gaji', 'agri.hris.gaji', 'Penggajian', 'Slip gaji dan bukti potong pajak 1721-A1 dari payroll AgriConnect.', 'Kesejahteraan'],
        ['hris-info', 'agri.hris.info', 'Informasi', 'Notifikasi, pengumuman Starfeed, pegawai baru, poin, ruang kerja, dan regulasi dari AgriConnect.', 'Kesejahteraan'],

        // ---- Data Pegawai: identitas, keluarga, dan jaminan/administrasi.
        ['hris-profil', 'agri.hris.profil', 'Profil Pegawai', 'Identitas, kepegawaian, alamat, pendidikan, dokumen, dan sertifikat dari AgriConnect.', 'Data Pegawai'],
        ['hris-keluarga', 'agri.hris.keluarga', 'Data Keluarga', 'Anggota keluarga, status perkawinan, dan kontak darurat Anda.', 'Data Pegawai'],
        ['hris-bpjs', 'agri.hris.bpjs', 'BPJS & NPWP', 'NPWP, BPJS Kesehatan & Ketenagakerjaan, asuransi, dan rekening penggajian.', 'Data Pegawai'],
        // Kontrak pendamping: kontrak, periode, dan penempatan koperasi — salinan
        // SIMKOPDES. Dipindahkan ke sini atas permintaan Bos (2026-10-18): ia
        // data KEPEGAWAIAN, jadi tempatnya bersama identitas/keluarga/BPJS.
        // Butirnya sudah TIDAK didaftarkan lagi di daftar_menu() (sidebar, bilah
        // bawah ponsel, /fitur) — kalau masih ada di sana, menu Kontrak tetap
        // tampil di dua tempat sekaligus dan Bos menegurnya (2026-10-18). Rutenya
        // tetap hidup karena kartu di halaman ini, lonceng, dan tautan lama
        // mengarah ke sana; pagar lingkupnya di KontrakController. Kunci 'kontrak'
        // dipertahankan supaya ikonnya, gate uji_kontrak.php, dan penanda menu
        // aktif tidak berubah.
        ['kontrak', 'kontrak', 'Kontrak', 'Kontrak, periode pendampingan, dan penempatan koperasi Anda dari SIMKOPDES.', 'Data Pegawai'],

        // ---- Pekerjaan: tugas harian sampai penilaian kinerja.
        ['tugas', 'agri.tugas', 'Tugas', 'Task, Overtime, dan Reminder milik sendiri dalam satu daftar.', 'Pekerjaan'],
        ['kinerja', 'agri.kinerja', 'Kinerja', 'Rekap kehadiran, tugas, dan poin Anda sendiri.', 'Pekerjaan'],
        ['hris-performa', 'agri.hris.performa', 'Performa', 'Tujuan, penilaian kinerja, umpan balik, dan asesmen karir dari AgriConnect.', 'Pekerjaan'],
        ['pelatihan', 'agri.pelatihan', 'E-Learning', 'Pelajaran singkat berbasis teks beserta progres belajar.', 'Pekerjaan'],

        // ---- Kesejahteraan: poin, apresiasi, dan informasi pegawai.
        ['imbalan', 'agri.imbalan', 'Imbalan', 'Saldo StarPoint, riwayat mutasinya, dan katalog hadiah yang bisa ditukar.', 'Kesejahteraan'],
        ['umpan', 'agri.umpan', 'Umpan Balik', 'Kirim dan terima umpan balik antar-rekan kerja.', 'Kesejahteraan'],
        ['ulangtahun', 'agri.ulangtahun', 'Ulang Tahun', 'Rekan kerja yang berulang tahun bulan ini.', 'Kesejahteraan'],
        ['pengumuman', 'agri.pengumuman', 'Pengumuman', 'Siaran dan pemberitahuan untuk pegawai.', 'Kesejahteraan'],

        // ---- Layanan: berkas acuan, kolaborasi, struktur, dan sesi akun.
        // Dipindah dari Kehadiran ke Layanan atas permintaan Bos (2026-10-21):
        // perjalanan dinas itu pengajuan/administrasi, bukan pencatatan kehadiran.
        ['perjalanan', 'agri.perjalanan', 'Perjalanan Dinas', 'Ajukan perjalanan dinas & klaim imbalan, lalu putuskan bila berwenang.', 'Layanan'],
        ['berkas', 'agri.berkas', 'Regulasi', 'Berkas regulasi, panduan, dan template yang berlaku.', 'Layanan'],
        ['ruang', 'agri.ruang', 'Kolaborasi', 'Pintu ke Messenger, Docs, Kalender, dan Approval di Ruang Kerja.', 'Layanan'],
        ['struktur', 'agri.struktur', 'Struktur', 'Peta pengelola: pusat, PIC wilayah, dan manajer koperasi.', 'Layanan'],
    ];

    // ---------------------------------------------------------------- Ringkasan

    public function ringkasan(Request $request)
    {
        $u = $request->user();
        $hari = now()->toDateString();
        $koperasiId = $this->koperasiId($u);

        return view('agri.ringkasan', [
            'aktif' => 'agri',
            'modul' => self::MODUL,
            'presensiHariIni' => AgriPresensi::where('user_id', $u->id)->where('tanggal', $hari)->first(),
            'saldo' => $this->saldoPoin($u->id),
            'tugasBaru' => AgriTugas::where('user_id', $u->id)->where('status', '!=', 'SELESAI')->count(),
            'umpanDiterima' => AgriUmpan::where('kepada_id', $u->id)->count(),
            'ajuanMenunggu' => AgriPengajuan::where('user_id', $u->id)->where('status', 'DIAJUKAN')->count(),
            // Yang menunggu KEPUTUSAN kita, bukan pengajuan kita.
            'menungguPutus' => $this->bolehMemutus($u)
                ? $this->queryPengajuan($request, true)->where('status', 'DIAJUKAN')->count() : 0,
            'koperasiId' => $koperasiId,
        ]);
    }

    // ---------------------------------------------------------------- Presensi

    public function presensi(Request $request, SimkopdesAbsensi $svc)
    {
        $u = $request->user();

        // Pemantau (admin, HR, PIC, keuangan) tidak mengurus presensi sendiri —
        // yang mereka cari adalah kehadiran para manajer. Satu cabang di sini,
        // bukan halaman kedua dengan rute baru: menu, tautan lama, dan penanda
        // aktif tetap satu pintu.
        if ($u->pemantauKehadiran()) {
            return $this->presensiPemantau($request, $u);
        }

        // Tanggal lokal, bukan UTC: presensi pukul 00:30 WIB milik hari itu.
        $tanggal = now('Asia/Jakarta')->toDateString();
        // Baris presensi hari ini WAJIB dicari dengan tanggal yang SAMA seperti
        // yang dipakai saat menyimpan. Dulu di sini `now()->toDateString()`
        // (UTC) sementara `$tanggal` memakai zona Jakarta — pada pukul 00:00–07:00
        // WIB keduanya BEDA HARI, sehingga Check In yang barusan tersimpan tidak
        // ditemukan lagi: halaman kembali menawarkan tombol Check In dan Check Out
        // ditolak dengan "Belum ada Check In hari ini".
        $hariIni = AgriPresensi::where('user_id', $u->id)->where('tanggal', $tanggal)->first();

        return view('agri.presensi', [
            'aktif' => 'agri',
            'cari' => trim((string) $request->query('q', '')),
            'hariIni' => $hariIni,
            // Presensi hari ini bisa datang dari DUA sisi: Hub (Check In di sini)
            // atau SIMKOPDES (pendamping sudah absen di sana). Dua-duanya harus
            // terbaca — kalau hanya Hub yang dilihat, orang yang sudah absen di
            // SIMKOPDES akan diminta Check In lagi dan mendapat tombol Check In,
            // padahal sisa kerjanya cuma Check Out.
            'asli' => $this->sumberHariIni($u, $tanggal, $hariIni),
            'rekap' => $this->kehadiranRekap($u),
            // Peta tanggal -> sudah terkirim? Dipakai lencana & tombol di baris
            // riwayat, supaya halaman ini juga memperlihatkan mana yang sudah
            // jadi catatan resmi SIMKOPDES.
            'kdkmp' => $this->kdkmpRingkas($svc, $u),
            'namaKoperasi' => $svc->namaKoperasi($u),
            'cutoff' => SimkopdesAbsensi::CUTOFF_JAM,
        ] + $this->daftarHadir($request, $u));
    }

    // ------------------------------------------------- Presensi (pemantau)

    /**
     * Presensi untuk peran pemantau — daftar kehadiran MANAJER.
     *
     * Dua tampilan dalam satu rute: tanpa `?orang=` berupa rekap semua manajer
     * dalam lingkupnya; dengan `?orang=<id>` berupa rincian harian satu orang
     * (read-only: tidak ada tombol Check In, Kirim, atau ubah).
     */
    private function presensiPemantau(Request $request, User $u)
    {
        $cari = trim((string) $request->query('q', ''));
        $dasar = $this->manajerTerpantau($u)
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('nama', 'like', '%'.$cari.'%')
                ->orWhere('email', 'like', '%'.$cari.'%')));

        $orangId = (int) $request->query('orang', 0);
        if ($orangId > 0) {
            $orang = (clone $dasar)->whereKey($orangId)->first();
            if ($orang === null) {
                return redirect()->route('agri.presensi')
                    ->with('galat', 'Manajer itu di luar lingkup pemantauan Anda.');
            }

            return view('agri.presensi-pemantau', [
                'aktif' => 'agri',
                'cari' => $cari, 'orang' => $orang,
                'koperasi' => Koperasi::where('manajer_id', $orang->id)->first(),
                'rincian' => $this->rincianHadir($request, $orang),
                'jamMasuk' => self::JAM_MASUK,
            ]);
        }

        $jumlah = (clone $dasar)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), 10);
        $baris = $halaman->batasi($dasar->orderBy('nama'))->get();

        return view('agri.presensi-pemantau', [
            'aktif' => 'agri', 'cari' => $cari, 'orang' => null,
            'baris' => $baris, 'halaman' => $halaman, 'jumlah' => $jumlah,
            'rekap' => $this->rekapBanyak($baris->pluck('id')->all()),
            'koperasi' => Koperasi::whereIn('manajer_id', $baris->pluck('id'))->get()->keyBy('manajer_id'),
            'jamMasuk' => self::JAM_MASUK,
        ]);
    }

    /**
     * Manajer yang boleh dipantau pengguna ini.
     *
     * Lingkupnya memakai `User::koperasiQuery()` yang sudah menghormati wilayah
     * PIC — bukan salinan aturan wilayah di sini. Admin/HR/keuangan melihat
     * seluruh manajer (termasuk yang koperasinya belum tertaut).
     */
    private function manajerTerpantau(User $u)
    {
        return User::query()->where('peran', Domain::PERAN_MANAJER)
            ->when(! ($u->lingkupPenuh() || $u->keuangan()), fn ($w) => $w->whereIn('id',
                $u->koperasiQuery()->whereNotNull('manajer_id')->select('manajer_id')));
    }

    /**
     * Rekap bulan berjalan untuk BANYAK manajer sekaligus: satu kueri per
     * sumber, bukan dua kueri per baris tabel.
     *
     * Sumbernya sama seperti `kehadiranRekap()`: cermin SIMKOPDES dulu (catatan
     * resmi), presensi Hub menambal tanggal yang belum ada di sana.
     */
    private function rekapBanyak(array $ids): array
    {
        $bulan = now('Asia/Jakarta')->format('Y-m');
        $hari = now('Asia/Jakarta')->toDateString();
        $jam = [];
        $kini = [];

        foreach (BarisAbsen::whereIn('user_id', $ids)->where('tanggal', 'like', $bulan.'-%')
            ->get(['user_id', 'tanggal', 'masuk', 'keluar']) as $b) {
            $t = (string) $b->tanggal;
            $jam[$b->user_id][$t] = trim((string) $b->masuk);
            if ($t === $hari) {
                $kini[$b->user_id] = ['masuk' => trim((string) $b->masuk),
                    'keluar' => trim((string) $b->keluar), 'dari' => 'SIMKOPDES'];
            }
        }

        foreach (AgriPresensi::whereIn('user_id', $ids)->where('tanggal', 'like', $bulan.'-%')
            ->get(['user_id', 'tanggal', 'masuk', 'keluar']) as $p) {
            $t = (string) $p->tanggal;
            $jam[$p->user_id][$t] ??= trim((string) $p->masuk);
            if ($t !== $hari) {
                continue;
            }
            $k = $kini[$p->user_id] ?? ['masuk' => '', 'keluar' => '', 'dari' => 'Hub'];
            $k['masuk'] = $k['masuk'] !== '' ? $k['masuk'] : trim((string) $p->masuk);
            $k['keluar'] = $k['keluar'] !== '' ? $k['keluar'] : trim((string) $p->keluar);
            if ($k['dari'] === 'SIMKOPDES' && ($k['masuk'] !== '' || $k['keluar'] !== '')) {
                $k['dari'] = 'SIMKOPDES + Hub';
            }
            $kini[$p->user_id] = $k;
        }

        $out = [];
        foreach ($ids as $id) {
            $terisi = array_filter($jam[$id] ?? [], fn ($j) => $j !== '');
            $out[$id] = [
                'hariIni' => $kini[$id] ?? null,
                'hadir' => count($terisi),
                'terlambat' => count(array_filter($terisi, fn ($j) => $j > self::JAM_MASUK)),
                'rataMasuk' => $this->jamRata($terisi),
            ];
        }

        return $out;
    }

    /**
     * Rincian kehadiran satu manajer untuk pemantau — READ-ONLY.
     *
     * Sengaja TIDAK memakai `daftarHadir()`: fungsi itu menyambung presensi Hub
     * ke cermin SIMKOPDES dengan MENGHAPUS baris Hub yang tanggalnya sudah jadi
     * catatan resmi. Membiarkan pemantau memicu jalur tulis itu berarti membuka
     * halaman orang lain bisa mengubah datanya.
     */
    private function rincianHadir(Request $request, User $orang): array
    {
        $tgl = [];
        foreach (BarisAbsen::where('user_id', $orang->id)->get() as $b) {
            $tgl[(string) $b->tanggal] = (object) [
                'tanggal' => (string) $b->tanggal,
                'masuk' => trim((string) $b->masuk), 'keluar' => trim((string) $b->keluar),
                'sumber' => 'SIMKOPDES',
                'kegiatan' => (string) $b->aktivitas, 'catatan' => (string) $b->catatan,
                'lat' => trim((string) $b->lat), 'lng' => trim((string) $b->lng),
                'fotoMasuk' => trim((string) $b->masuk_foto) !== '' ? route('agri.absen.foto', [$b->id]) : '',
                'fotoKeluar' => trim((string) $b->keluar_foto) !== ''
                    ? route('agri.absen.foto', [$b->id, 'sisi' => 'keluar']) : '',
            ];
        }

        foreach (AgriPresensi::where('user_id', $orang->id)->get() as $p) {
            $t = (string) $p->tanggal;
            $hub = (object) [
                'tanggal' => $t,
                'masuk' => trim((string) $p->masuk), 'keluar' => trim((string) $p->keluar),
                'sumber' => 'Hub',
                'kegiatan' => (string) $p->catatan, 'catatan' => '',
                'lat' => trim((string) $p->masuk_lat), 'lng' => trim((string) $p->masuk_lng),
                'fotoMasuk' => trim((string) $p->masuk_foto) !== '' ? route('agri.presensi.foto', [$p->id]) : '',
                'fotoKeluar' => trim((string) $p->keluar_foto) !== ''
                    ? route('agri.presensi.foto', [$p->id, 'sisi' => 'keluar']) : '',
            ];
            if (! isset($tgl[$t])) {
                $tgl[$t] = $hub;
                continue;
            }
            // Tanggal yang sudah resmi: hanya menambal jam/foto yang masih kosong.
            $resmi = $tgl[$t];
            if ($resmi->keluar === '' && $hub->keluar !== '') {
                $resmi->keluar = $hub->keluar;
            }
            foreach (['fotoMasuk', 'fotoKeluar'] as $f) {
                if ($resmi->{$f} === '' && $hub->{$f} !== '') {
                    $resmi->{$f} = $hub->{$f};
                }
            }
            if ($hub->masuk !== '' || $hub->keluar !== '') {
                $resmi->sumber = 'SIMKOPDES + Hub';
            }
        }

        krsort($tgl);
        $h = Halaman::buat(count($tgl), $request->query('halaman', 1));

        return ['baris' => array_slice(array_values($tgl), $h->offset(), $h->perHalaman), 'halaman' => $h];
    }

    /**
     * Presensi untuk satu tanggal, digabung dari DUA sumber — catatan resmi
     * SIMKOPDES lebih dulu, presensi Hub menutupinya bila lebih lengkap.
     *
     * Bentuknya sama seperti `AgriPresensi` (`masuk`, `keluar`, `masuk_foto`,
     * …) pakai `stdClass`, supaya view tidak peduli dari mana datanya datang.
     *
     * KENAPA SIMKOPDES DIDAHULUKAN: barisnya adalah catatan resmi dan permanen —
     * jam di sana yang dilihat pengelola. Presensi Hub boleh MENAMBAH apa yang
     * belum ada (mis. Check Out yang belum diisi di sana), tapi tidak boleh
     * menggeser jam yang sudah resmi.
     *
     * Kelengkapan foto/titik dicatat di `dari` (`SIMP`/`HUB`/`HUB+SIMP`) supaya
     * halaman bisa berterus terang soal asal buktinya.
     */
    private function sumberHariIni(User $u, string $tanggal, ?AgriPresensi $hub): ?object
    {
        $s = BarisAbsen::where('user_id', $u->id)->where('tanggal', $tanggal)
            ->where('sumber', 'SIMP')->first();

        if ($hub === null && $s === null) {
            return null;
        }

        $p = (object) [
            'tanggal' => $tanggal,
            'dari' => $s !== null && $hub !== null ? 'HUB+SIMP' : ($s !== null ? 'SIMP' : 'HUB'),
        ];

        foreach ([['masuk', 'masuk_pada', 'masuk_foto', 'masuk_lat', 'masuk_lng', 'masuk_akurasi'],
                  ['keluar', 'keluar_pada', 'keluar_foto', 'keluar_lat', 'keluar_lng', 'keluar_akurasi']] as [$jam, $pada, $foto, $lat, $lng, $akur]) {
            $nilai = '';
            $padaNilai = '';
            if ($s !== null) {
                $nilai = (string) $s->{$jam};
                $padaNilai = (string) $s->{$pada};
                $p->{$foto} = (string) $s->{$foto};
                $p->{$lat} = (string) $s->{$lat};
                $p->{$lng} = (string) $s->{$lng};
                $p->{$akur} = '';
            } else {
                $p->{$foto} = '';
                $p->{$lat} = '';
                $p->{$lng} = '';
                $p->{$akur} = '';
            }
            if ($hub !== null) {
                $h = (string) $hub->{$jam};
                if ($h !== '') {
                    $nilai = $s === null || $nilai === '' ? $h : $nilai;
                    if ($s === null || (string) $s->{$jam} === '') {
                        $padaNilai = (string) $hub->{$pada};
                        $p->{$foto} = (string) $hub->{$foto};
                        $p->{$lat} = (string) $hub->{$lat};
                        $p->{$lng} = (string) $hub->{$lng};
                        $p->{$akur} = (string) $hub->{$akur};
                    }
                }
            }
            $p->{$jam} = $nilai;
            $p->{$pada} = $padaNilai;
        }

        return $p;
    }

    /**
     * Ringkasan kehadiran bulan berjalan — DIHITUNG DARI DUA SUMBER.
     *
     * Dulu angka ini hanya membaca `agri_presensi` (presensi ber-foto di Hub),
     * sehingga selalu berbunyi 0 bagi pendamping yang absennya dari SIMKOPDES.
     * Itulah sebabnya kartu "Hadir / Terlambat / Rata-rata masuk" di halaman ini
     * tidak cocok dengan daftar hadir di bawahnya: dua sumber berbeda untuk satu
     * kenyataan. Sekarang satu fungsi menyatukan keduanya — baris SIMKOPDES
     * diutamakan (catatan resmi), baris Hub menambal tanggal yang belum ada di
     * sana — jadi judul dan isinya pasti sepakat.
     */
    private function kehadiranRekap(User $u): array
    {
        $bulan = now('Asia/Jakarta')->format('Y-m');
        $jam = [];

        // 1) Cermin SIMKOPDES: catatan resmi.
        foreach (BarisAbsen::where('user_id', $u->id)->where('tanggal', 'like', $bulan.'-%')
            ->get(['tanggal', 'masuk']) as $b) {
            $jam[(string) $b->tanggal] = trim((string) $b->masuk);
        }

        // 2) Presensi Hub: hanya untuk tanggal yang belum ada di atas.
        foreach (AgriPresensi::where('user_id', $u->id)->where('tanggal', 'like', $bulan.'-%')
            ->get(['tanggal', 'masuk']) as $p) {
            $jam[(string) $p->tanggal] ??= trim((string) $p->masuk);
        }

        $terisi = array_filter($jam, fn ($j) => $j !== '');

        return [
            'hariIni' => $this->statusHariIni($u),
            'hariBulanIni' => count($terisi),
            'terlambat' => count(array_filter($terisi, fn ($j) => $j > self::JAM_MASUK)),
            'jamMasukRata' => $this->jamRata($terisi),
            'mulaiBulan' => now('Asia/Jakarta')->startOfMonth()->translatedFormat('F Y'),
            'jamMasuk' => self::JAM_MASUK,
        ];
    }

    /** 'lengkap' | 'menunggu' | 'kosong' untuk presensi hari ini (semua sumber). */
    private function statusHariIni(User $u): string
    {
        $hari = now('Asia/Jakarta')->toDateString();
        $s = BarisAbsen::where('user_id', $u->id)->where('tanggal', $hari)->where('sumber', 'SIMP')->first();
        $h = AgriPresensi::where('user_id', $u->id)->where('tanggal', $hari)->first();

        $masuk = trim((string) ($s?->masuk ?? '')) !== '' || trim((string) ($h?->masuk ?? '')) !== '';
        if (! $masuk) {
            return 'kosong';
        }
        $keluar = trim((string) ($s?->keluar ?? '')) !== '' || trim((string) ($h?->keluar ?? '')) !== '';

        return $keluar ? 'lengkap' : 'menunggu';
    }

    /**
     * Rata-rata jam kehadiran (`HH:MM`) dari daftar jam; '' bila belum ada isi.
     *
     * Dihitung sebagai rata-rata MENIT sejak tengah malam, bukan rata-rata jam
     * & menit terpisah: 07:55 dan 08:05 harus jadi 08:00, bukan 08:00 dari
     * pembulatan menit yang berbeda-beda.
     */
    private function jamRata(array $jam): string
    {
        $menit = [];
        foreach ($jam as $j) {
            $j = trim((string) $j);
            if (preg_match('/^(\d{2}):(\d{2})/', $j, $m)) {
                $menit[] = (int) $m[1] * 60 + (int) $m[2];
            }
        }

        return $menit === [] ? '' : sprintf('%02d:%02d', intdiv((int) round(array_sum($menit) / count($menit)), 60),
            (int) round(array_sum($menit) / count($menit)) % 60);
    }



    /**
     * Daftar hadir KDKMP — satu daftar untuk SATU halaman.
     *
     * Presensi Hub sudah DIJAHIT ke sini: setiap tanggal punya satu baris saja.
     * Sebelumnya halaman ini memuat dua daftar untuk data yang sama (Riwayat
     * Presensi dari `agri_presensi` + Daftar Hadir KDKMP dari cermin SIMKOPDES),
     * sehingga satu hari muncul dua kali dengan kolom yang berbeda-beda — dan
     * yang membaca harus mencocokkan sendiri mana yang resmi. Sekarang barisnya
     * dari cermin SIMKOPDES (memuat `aktivitas` = judul kegiatan), dan presensi
     * Hub yang belum punya catatan resmi dijahit masuk apa adanya. Itu juga
     * sebabnya `sumber` dihitung: 'SIMP' (resmi), 'HUB' (belum diteruskan), atau
     * 'HUB+SIMP' (presensi Hub yang tanggalnya SUDAH jadi catatan resmi).
     *
     * Kolom Ke SIMKOPDES karena itu bisa bernilai empat: akun non-manajer tidak
     * meneruskan apa pun; 'SIMP' sudah resmi; 'HUB' belum terkirim (tombolnya
     * muncul); 'HUB+SIMP' sudah tercakup catatan resmi.
     */
    private function daftarHadir(Request $request, User $u): array
    {
        // Kunci halaman tersendiri (`ahalaman`), bukan `halaman`. Dulu halaman
        // ini memuat DUA daftar berhalaman yang memakai parameter sama, dan
        // daftar presensi Hub (yang tidak lagi dirender) terus-menerus MENANG
        // mengambil nomor halaman — sehingga mengklik "halaman 2" pada daftar
        // hadir memuat ulang daftar yang salah dan halamannya tampak tidak
        // berpindah.
        $cari = trim((string) $request->query('q', ''));
        $semua = BarisAbsen::where('user_id', $u->id)
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('aktivitas', 'like', '%'.$cari.'%')
                ->orWhere('catatan', 'like', '%'.$cari.'%')
                ->orWhere('tanggal', 'like', '%'.$cari.'%')))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->get();

        // Dijahit di memori, bukan di SQL: presensi Hub adalah daftar pendek
        // (satu baris per hari kerja), sedangkan menggabung dua tabel di SQL
        // menuntut UNION + agregat yang jauh lebih panjang daripada satu peta
        // tanggal. ponytail: seluruh daftar ditarik, cukup sampai ribuan baris;
        // pindahkan ke UNION + LIMIT bila daftar hadir per akun sudah puluhan ribu.
        $tanggalResmi = $semua->where('sumber', 'SIMP')->pluck('tanggal')->all();
        $hapus = BarisAbsen::where('user_id', $u->id)->where('sumber', 'HUB')
            ->whereIn('tanggal', $tanggalResmi)->pluck('id');
        if ($hapus->isNotEmpty()) {
            BarisAbsen::whereIn('id', $hapus->all())->delete();
            $semua = $semua->whereNotIn('id', $hapus->all())->values();
        }

        // Tanggal yang SUDAH punya baris (dari cermin SIMKOPDES). Dipakai di
        // bawah untuk tidak menambahkan baris presensi Hub pada tanggal yang
        // sama. Dulu variabel ini dirujuk tapi tidak pernah dibuat — akibatnya
        // setiap baris Hub SELALU ditambahkan lagi sebagai baris kedua, jadi
        // satu hari muncul dua kali di daftar hadir (dan jumlah baris paging
        // ikut membengkak).
        $punya = $semua->keyBy('tanggal')->all();

        $presensi = AgriPresensi::where('user_id', $u->id)->get()->keyBy('tanggal');
        foreach ($semua as $b) {
            // Foto & titik dari presensi Hub yang belum ada di cermin SIMKOPDES
            // (cermin hanya punya SATU kolom lokasi dan berkas fotonya sering
            // belum terunggah). Tiap sisi mencatat dari mana datangnya, sebab
            // fotonya dilayani DUA rute berbeda: `agri.presensi.foto` (id baris
            // `agri_presensi`) dan `agri.absen.foto` (id baris cermin). Tanpa
            // penanda itu, bukti Check Out Hub hilang dari baris yang tanggalnya
            // sudah jadi catatan resmi — tersimpan tapi tak bisa dilihat.
            $b->masuk_dari = 'SIMP';
            $b->masuk_id = $b->id;
            $b->keluar_dari = 'SIMP';
            $b->keluar_id = $b->id;
            $p = $b->sumber === 'SIMP' ? ($presensi[$b->tanggal] ?? null) : null;
            if ($p === null) {
                continue;
            }
            if (trim((string) $b->masuk_foto) === '' && trim((string) $p->masuk_foto) !== '') {
                $b->masuk_foto = (string) $p->masuk_foto;
                $b->masuk_dari = 'HUB';
                $b->masuk_id = $p->id;
            }
            if (($k = trim((string) $b->keluar_foto)) === '' && trim((string) $p->keluar_foto) !== '') {
                $b->keluar_foto = (string) $p->keluar_foto;
                $b->keluar_dari = 'HUB';
                $b->keluar_id = $p->id;
                $b->lat_keluar = trim((string) $p->keluar_lat) !== '' ? (string) $p->keluar_lat : '';
                $b->lng_keluar = trim((string) $p->keluar_lng) !== '' ? (string) $p->keluar_lng : '';
            }
            // Baris lama bisa tertinggal sebelum penyelarasan Check Out sempat
            // jalan, jadi jam keluarnya ikut dijahit dari presensi Hub.
            if ($b->keluar === '' && trim((string) $p->keluar) !== '') {
                $b->keluar = (string) $p->keluar;
                $b->keluar_pada = (string) $p->keluar_pada;
            }
        }
        $hasil = $semua->all();
        foreach ($presensi as $p) {
            if (isset($punya[$p->tanggal])) {
                continue;
            }
            $hasil[] = (object) [
                'id' => $p->id, 'tanggal' => $p->tanggal,
                'masuk' => (string) $p->masuk, 'keluar' => (string) $p->keluar,
                'masuk_pada' => (string) $p->masuk_pada, 'keluar_pada' => (string) $p->keluar_pada,
                'masuk_foto' => (string) $p->masuk_foto, 'keluar_foto' => (string) $p->keluar_foto,
                'lat' => trim((string) $p->masuk_lat) !== '' ? (string) $p->masuk_lat : '',
                'lng' => trim((string) $p->masuk_lng) !== '' ? (string) $p->masuk_lng : '',
                'lat_keluar' => trim((string) $p->keluar_lat) !== '' ? (string) $p->keluar_lat : '',
                'lng_keluar' => trim((string) $p->keluar_lng) !== '' ? (string) $p->keluar_lng : '',
                'aktivitas' => (string) $p->catatan, 'catatan' => '',
                'sumber' => 'HUB', 'kirim_pada' => '',
                'masuk_dari' => 'HUB', 'masuk_id' => $p->id,
                'keluar_dari' => 'HUB', 'keluar_id' => $p->id,
            ];
        }
        usort($hasil, fn ($a, $b) => [$b->tanggal, $b->id] <=> [$a->tanggal, $a->id]);

        $h = Halaman::buat(count($hasil), $request->query('halaman', 1));

        return [
            'baris' => array_slice($hasil, $h->offset(), $h->perHalaman), 'halaman' => $h,
            'kunciHalaman' => 'halaman',
            'cari' => $cari,
        ];
    }
    /**
     * Ringkasan sinkronisasi KDKMP untuk lencana di halaman Presensi: apakah
     * akun sudah terhubung, berapa baris daftar hadir, dan baris mana yang masih
     * menunggu dikirim. Sengaja TIDAK menyentuh jaringan — halaman presensi
     * tidak boleh menggantung karena API SIMKOPDES sedang lambat.
     */
    private function kdkmpRingkas(SimkopdesAbsensi $svc, User $u): array
    {
        // Akun yang sandinya dititipkan saat login bisa belum lengkap profilnya,
        // dan periode + koperasi binaan HANYA ada di profil SIMKOPDES. Tanpa baris
        // ini halaman akan berkata "belum tersambung" padahal sandinya sudah
        // diterima — diambil sekali di sini (paling banyak sekali seumur akun),
        // dan kegagalannya tidak menghalangi halaman terbuka.
        if ($u->manajerKoperasi() && ((string) $u->simkopdes_sandi !== '' || $svc->tokenBrowser($u) !== '') && ! $svc->adaProfil($u)) {
            try {
                $svc->ambilProfil($u);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // `tertunda` datang dari ringkas() — jangan dihitung ulang di sini:
        // `whereNull()` pada kolom teks NOT NULL selalu berbunyi 0.
        return $svc->ringkas($u) + [
            'punyaSandi' => (string) $u->simkopdes_sandi !== '',
            'manajer' => $u->manajerKoperasi(),
        ];
    }

    /**
     * Hubungkan akun SIMKOPDES manajer ini: uji sandinya ke SIMKOPDES, catat
     * facilitator id, lalu tarik daftar hadirnya. Sandi disimpan terenkripsi
     * karena menulis absensi atas nama seseorang menuntut kredensialnya sendiri
     * — tidak ada jalan lain (SIMKOPDES tanpa OAuth/SSO).
     */
    public function kdkmp_hubungkan(Request $request, SimkopdesAbsensi $svc)
    {
        $u = $request->user();
        $d = $request->validate([
            'sandi' => ['nullable', 'string', 'max:120'],
            'token' => ['nullable', 'string', 'max:300'],
        ], [], ['sandi' => 'sandi SIMKOPDES', 'token' => 'token SIMKOPDES']);

        $token = trim((string) ($d['token'] ?? ''));
        $sandi = (string) ($d['sandi'] ?? '');

        try {
            if ($token !== '') {
                // Jalur utama sejak login otomatis ditolak SIMKOPDES (23-10-2026):
                // token dari browser pengguna sendiri diuji ke `/profile`.
                $profil = $svc->simpanTokenBrowser($u, $token);
            } elseif ($sandi !== '') {
                $svc->simpanSandi($u, $sandi);
                // ambilProfil() sudah menyimpan token BARU dari login ini, jadi tidak
                // perlu segarkanToken() lagi — itu login kedua, dan login kedua
                // justru mematikan sesi yang barusan dibuat (satu sesi per akun).
                $profil = $svc->ambilProfil($u);
            } else {
                return back()->with('galat', 'Isi token SIMKOPDES (dari browser) atau sandi akun.');
            }
        } catch (\Throwable $e) {
            $svc->lupakanSandi($u);

            return back()->with('galat', 'Tidak bisa menghubungkan akun '.$u->email.' ke SIMKOPDES: '.$e->getMessage());
        }

        $fac = (int) ($profil['regional_facilitator']['regional_facilitator_id'] ?? 0);
        if ($fac <= 0) {
            $svc->lupakanSandi($u);

            return back()->with('galat', 'Akun ini bukan akun pendamping SIMKOPDES — daftar hadir KDKMP tidak tersedia untuknya.');
        }

        $u->forceFill(['simkopdes_facilitator_id' => $fac])->save();
        jurnal($u->id, 'SIMP_ABSEN_HUBUNG', (string) $fac, '', $request->ip() ?? '');

        try {
            $r = $svc->tarik($u);
            $pesan = 'Akun SIMKOPDES terhubung (facilitator '.$fac.'). Daftar hadir ditarik: '.$r['baris'].' baris.';
        } catch (\Throwable $e) {
            $pesan = 'Akun SIMKOPDES terhubung (facilitator '.$fac.'), tetapi tarikan gagal: '.$e->getMessage();
        }

        return back()->with('pesan', $pesan);
    }

    /** Tarik ulang daftar hadir SIMKOPDES (turun). Tidak menulis apa pun ke sana. */
    public function kdkmp_tarik(Request $request, SimkopdesAbsensi $svc)
    {
        $u = $request->user();

        if (! $svc->terkonfigurasi($u)) {
            return back()->with('galat', 'Hubungkan akun SIMKOPDES dulu: tarikan menuntut sandi akun sendiri, bukan akun bersama.');
        }

        try {
            $r = $svc->tarik($u);
        } catch (\Throwable $e) {
            return back()->with('galat', 'Tarikan gagal: '.$e->getMessage());
        }

        return back()->with('pesan', 'Daftar hadir diperbarui: '.$r['baris'].' baris dari '.$r['halaman'].' halaman.');
    }

    /**
     * Kirim satu baris absensi ke SIMKOPDES (naik). Tombolnya SENGAJA terpisah
     * dan hanya muncul untuk baris yang masih tertunda, karena absensi
     * SIMKOPDES permanen — tidak ada endpoint ubah maupun hapus di sana.
     */
    public function kdkmp_kirim(Request $request, SimkopdesAbsensi $svc)
    {
        $u = $request->user();
        $d = $request->validate(['id' => ['required', 'integer']]);
        $baris = BarisAbsen::whereKey((int) $d['id'])->firstOrFail();

        $hasil = $svc->kirim($u, $baris, (bool) $request->boolean('paksa'));
        jurnal($u->id, 'SIMP_ABSEN_KIRIM', (string) $baris->tanggal, $hasil['ok'] ? 'ok' : 'gagal', $request->ip() ?? '');

        return back()->with($hasil['ok'] ? 'pesan' : 'galat', $hasil['pesan']);
    }

    /** Lepas hubungan: sandi SIMKOPDES dibuang. Daftar hadir yang sudah ditarik tetap ada. */
    public function kdkmp_lepas(Request $request, SimkopdesAbsensi $svc)
    {
        $u = $request->user();
        $svc->lupakanSandi($u);
        jurnal($u->id, 'SIMP_ABSEN_LEPAS', '', '', $request->ip() ?? '');

        return back()->with('pesan', 'Akun SIMKOPDES dilepas. Sandi tidak lagi tersimpan di Hub.');
    }

    /**
     * Absen KDKMP — daftar hadir SIMKOPDES, ditambah baris Hub yang belum
     * diteruskan ke sana.
     *
     * Halaman ini TIDAK menghubungi jaringan saat dibuka: tarikan hanya jalan
     * lewat tombolnya, supaya daftar hadir tetap terbuka walau SIMKOPDES sedang
     * lambat atau tidak bisa dijangkau.
     */
    public function absen(Request $request, SimkopdesAbsensi $svc)
    {
        // Absen KDKMP kini hidup di halaman Presensi (id="absen"), karena
        // presensi & absen KDKMP itu satu fungsi: satu foto potret, satu titik
        // lokasi, satu baris per hari. Tautan lama & rutenya dibiarkan hidup dan
        // dialihkan — alamat yang sudah dibagikan ke pendamping tidak boleh mati.
        return redirect()->to(route('agri.presensi').'?view=absen#absen');
    }

    /**
     * Foto baris absensi KDKMP. Sama seperti foto presensi: lewat rute ber-`auth`
     * dan hanya milik sendiri, karena isinya wajah dan titik lokasi.
     */
    public function absen_foto(Request $request, int $id)
    {
        // Aturan sama seperti `presensi_foto`: pemilik atau pemantau sejauh
        // lingkupnya (User::bolehLihatKehadiran).
        $baris = BarisAbsen::whereKey($id)->firstOrFail();
        abort_unless($request->user()->bolehLihatKehadiran((int) $baris->user_id), 404, 'Foto absensi tidak tersedia di Hub.');
        $nama = trim((string) ($request->query('sisi') === 'keluar' ? $baris->keluar_foto : $baris->masuk_foto));

        if ($nama === '' || ! Storage::disk(self::DISK)->exists($nama)) {
            abort(404, 'Foto absensi tidak tersedia di Hub.');
        }

        return Storage::disk(self::DISK)->response($nama);
    }

    /**
     * Check In / Check Out. Satu baris per hari; menekan tombol yang sama dua
     * kali tidak menambah baris kedua — ia memperbarui jamnya. Poin hanya
     * diberikan pada Check In pertama hari itu, jadi presensi tidak bisa
     * dipakai mencetak poin berulang kali.
     */
    public function presensi_simpan(Request $request, SimkopdesAbsensi $svc)
    {
        $u = $request->user();
        $aksi = $request->validate([
            'aksi' => ['required', 'in:masuk,keluar'],
            // Keterangan = judul kegiatan (kolom `activity` SIMKOPDES). Wajib,
            // seperti di sana: baris daftar hadir tanpa keterangan tidak bisa
            // diaudit, dan justru itu isi laporan pendampingan.
            'catatan' => ['required', 'string', 'min:3', 'max:200'],
            // Rentang koordinat divalidasi di batas kepercayaan: angka di luar
            // ini bukan titik di bumi, dan tersimpan berarti sampah di laporan.
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'akurasi' => ['nullable', 'numeric', 'between:1,'.self::AKURASI_MAKS],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'waktu' => ['nullable', 'date'],
            'zona' => ['nullable', 'string', 'max:64'],
            'foto_data' => ['nullable', 'string', 'max:3000000'],
            // Nama berkas yang sudah ditaruh peramban LANGSUNG di R2
            // (lihat presensi_url_unggah). Tidak ada byte foto di sini.
            'foto_kunci' => ['nullable', 'string', 'max:120'],
        ], [
            'catatan.required' => 'Isi keterangan kegiatan dulu — judul kegiatan wajib diisi.',
            'catatan.min' => 'Keterangan kegiatan terlalu pendek — tulis kegiatannya, mis. "Kunjungan koperasi binaan".',
            'lat.between' => 'Titik lokasi di luar jangkauan bumi — matikan aplikasi lokasi palsu.',
            'lng.between' => 'Titik lokasi di luar jangkauan bumi — matikan aplikasi lokasi palsu.',
            'akurasi.between' => 'Sinyal lokasi tidak wajar — matikan aplikasi lokasi palsu, lalu ambil titiknya di area terbuka.',
            'foto_data.max' => 'Foto dari kamera terlalu besar. Ulangi pengambilan foto.',
        ])['aksi'];

        $lbl = $aksi === 'masuk' ? 'Check In' : 'Check Out';

        // Presensi hanya dari peramban biasa. Lihat UA_OTOMASI: skrip bisa
        // mengarang lokasi dan mengirim foto apa pun, jadi jalur itu ditutup.
        $ua = mb_strtolower((string) $request->userAgent());
        foreach (self::UA_OTOMASI as $tanda) {
            if (str_contains($ua, $tanda)) {
                return back()->withInput()->with('galat',
                    $lbl.' hanya bisa dilakukan dari peramban ponsel/komputer biasa — '
                    .'alat otomasi tidak diterima.');
            }
        }

        $waktu = $this->waktuPerangkat($request);
        if (trim((string) $request->input('waktu', '')) !== '' && $waktu === null) {
            return back()->withInput()->with('galat',
                'Jam perangkat melenceng lebih dari '.self::TOLERANSI_MENIT.
                ' menit dari jam server. Nyalakan jam & zona waktu otomatis di ponsel, lalu coba lagi.');
        }
        // (0,0) bukan tempat tinggal siapa pun — itu titik kosong yang dikirim
        // aplikasi lokasi palsu atau sensor yang belum dapat sinyal.
        if (trim((string) $request->input('lat', '')) === '0' && trim((string) $request->input('lng', '')) === '0') {
            return back()->withInput()->with('galat', $lbl.' menolak titik lokasi 0,0 — matikan aplikasi lokasi palsu.');
        }

        // Foto & koordinat WAJIB untuk Check In MAUPUN Check Out. Dulu keduanya
        // hanya wajib saat Check In; Check Out boleh kosong supaya "menutup hari
        // kerja tidak gagal gara-gara izin kamera". Aturan itu dibatalkan: satu
        // hari kerja bisa jadi tanpa foto, sehingga laporan presensi separuh
        // kosong dan tidak bisa diaudit. Sekarang kedua sisi memakai bukti yang
        // sama — dan kalau izin kamera/lokasi belum diberikan, pesannya menyebut
        // sebabnya, bukan menyimpan baris tanpa bukti.
        //
        // Jalur berkas unggahan (`foto`) sengaja TIDAK diterima lagi: satu-satunya
        // sumber foto adalah kamera langsung saat itu juga (data URL kanvas).
        $adaFoto = trim((string) $request->input('foto_data', '')) !== ''
            || trim((string) $request->input('foto_kunci', '')) !== '';
        if (! $adaFoto) {
            return back()->withInput()->with('galat', $lbl.' memerlukan foto selfie dari kamera.');
        }
        if (trim((string) $request->input('lat', '')) === '') {
            return back()->withInput()->with('galat', $lbl.' memerlukan titik lokasi — izinkan akses lokasi di peramban.');
        }

        // Tanggal & jam dari PERANGKAT. Kalau memakai jam server (UTC), Check In
        // pukul 00:30 WIB tercatat di tanggal kemarin dan jamnya meleset 7 jam.
        $kapan = $waktu ?? now();
        $hari = $kapan->toDateString();
        $baris = AgriPresensi::where('user_id', $u->id)->where('tanggal', $hari)->first();

        // Check In/Check Out hari ini bisa SUDAH ada di SIMKOPDES: pendamping
        // kerap absen dari sana, atau admin memasukkan absensinya. Itu catatan
        // resmi, jadi dua hal: (1) Hub tidak perlu Check In lagi — cukup
        // menawarkan Check Out; (2) jam yang sudah resmi TIDAK boleh digeser,
        // termasuk dengan menulis baris Hub kedua untuk tanggal yang sama.
        $resmi = BarisAbsen::where('user_id', $u->id)->where('tanggal', $hari)
            ->where('sumber', 'SIMP')->first();
        if ($resmi !== null && trim((string) $resmi->keluar) !== '') {
            return back()->with('pesan',
                'Presensi '.tanggal_id($hari, 'd M Y').' sudah lengkap di SIMKOPDES '
                .'(masuk '.$resmi->masuk.', keluar '.$resmi->keluar.') — tidak ada yang perlu diisi lagi.');
        }

        // Satu foto, dua alamat kolom: masuk_* atau keluar_*. Keduanya bergaya
        // sama, jadi bentuk namanya diturunkan dari $aksi, bukan ditulis dua kali.
        $kol = fn (string $k) => $aksi === 'masuk' ? 'masuk_'.$k : 'keluar_'.$k;

        if (! $baris) {
            // Sudah Check In di SIMKOPDES tapi belum Check Out: sisa hari ini
            // HANYA sisi keluar. Menulis baris Hub ber-Check In di sini akan
            // membuat dua catatan untuk satu hari — dan yang resmi justru yang
            // tidak dilihat.
            if ($aksi === 'masuk' && $resmi !== null) {
                return back()->with('pesan',
                    'Sudah Check In di SIMKOPDES pukul '.$resmi->masuk
                    .'. Sisa hari ini: Check Out — tekan tombolnya, foto & titik lokasinya tetap wajib.');
            }

            // Check Out saat Check In-nya dari SIMKOPDES: dulu ini ditolak
            // ("Belum ada Check In hari ini"), padahal justru inilah jalan yang
            // paling sering dipakai pendamping — mereka absen masuk dari
            // SIMKOPDES, lalu menutup hari dari sini. Baris Hub dibuat dengan jam
            // masuk DISALIN dari catatan resmi (bukan dikarang), jadi sisi keluar
            // punya tempat untuk foto & titiknya tanpa menggeser jam resmi.
            if ($aksi === 'keluar' && ($resmi === null || trim((string) $resmi->masuk) === '')) {
                return back()->with('galat', 'Belum ada Check In hari ini.');
            }

            $foto = $this->simpanFotoPresensi($request);
            if ($foto === null) {
                return back()->withInput()->with('galat',
                    'Foto dari kamera tidak terbaca. Ulangi pengambilan foto, lalu kirim lagi.');
            }

            $masuk = $resmi !== null ? trim((string) $resmi->masuk) : $kapan->format('H:i');
            $masukPada = $resmi !== null ? (string) $resmi->masuk_pada : $kapan->toIso8601String();

            $barisPresensi = AgriPresensi::create([
                'user_id' => $u->id, 'koperasi_id' => $this->koperasiId($u), 'tanggal' => $hari,
                'masuk' => $masuk, 'catatan' => trim((string) $request->input('catatan', '')),
                'masuk_foto' => $resmi !== null ? '' : $foto,
                'masuk_lat' => $resmi !== null ? '' : $this->angka($request->input('lat')),
                'masuk_lng' => $resmi !== null ? '' : $this->angka($request->input('lng')),
                'masuk_akurasi' => $resmi !== null ? '' : $this->angka($request->input('akurasi')),
                'masuk_lokasi' => $resmi !== null ? '' : trim((string) $request->input('lokasi', '')),
                'masuk_pada' => $masukPada,
                'dibuat' => kini(),
            ]);
            $baris = $barisPresensi;

            if ($aksi === 'masuk') {
                $this->poin($u->id, self::POIN_PRESENSI, 'Check In '.tanggal_id($hari, 'd M Y'));
                jurnal($u->id, 'AGRI_PRESENSI', $hari, 'masuk', $request->ip() ?? '');

                return back()->with('pesan', 'Check In '.$kapan->format('H:i')
                    .' tercatat — foto & lokasi tersimpan.'.$this->sinkronKdkmp($svc, $u, $barisPresensi, $kapan))->withFragment('daftar-hadir');
            }

            // Lanjut ke blok Check Out di bawah memakai baris yang barusan dibuat
            // (jam masuk = jam resmi SIMKOPDES).
        }

        // Check In ulang di hari yang sama (mis. menekan tombolnya sekali lagi
        // sesudah jam masuk tercatat). Tidak menambah baris, tetapi baris absen
        // KDKMP yang masih menunggu WAJIB ikut diselaraskan — kalau tidak,
        // Check In yang barusan ditekan tidak pernah sampai ke SIMKOPDES.
        if ($aksi === 'masuk') {
            return back()->with('pesan', 'Sudah Check In hari ini pukul '.$baris->masuk.'.'
                .$this->sinkronKdkmp($svc, $u, $baris, $kapan))->withFragment('daftar-hadir');
        }
        // Sisi keluar sudah tercatat di Hub ATAU sudah resmi di SIMKOPDES: tidak
        // ada jalur yang boleh menimpanya — jam absen resmi permanen.
        if (trim((string) $baris->keluar) !== '' || ($resmi !== null && trim((string) $resmi->keluar) !== '')) {
            $jamKeluar = trim((string) $resmi?->keluar) !== '' && trim((string) $baris->keluar) === ''
                ? (string) $resmi->keluar : (string) $baris->keluar;

            return back()->with('pesan', 'Sudah Check Out hari ini pukul '.$jamKeluar.'.');
        }

        // Check Out memakai bukti yang sama seperti Check In: foto, koordinat,
        // dan (bila diisi) keterangan. Foto yang tidak terbaca DITOLAK, bukan
        // dilewati — menyimpan setengah bukti lebih buruk daripada gagal terang-terangan.
        $foto = $this->simpanFotoPresensi($request);
        if ($foto === null) {
            return back()->withInput()->with('galat',
                'Foto dari kamera tidak terbaca. Ulangi pengambilan foto, lalu kirim lagi.');
        }

        $isi = ['keluar' => $kapan->format('H:i'), $kol('pada') => $kapan->toIso8601String(),
            $kol('foto') => $foto];
        foreach (['lat', 'lng', 'akurasi'] as $k) {
            if (($v = $this->angka($request->input($k))) !== null) {
                $isi[$kol($k)] = $v;
            }
        }
        if (($v = trim((string) $request->input('lokasi', ''))) !== '') {
            $isi[$kol('lokasi')] = $v;
        }
        // Keterangan Check Out menempel di catatan yang sama; kalau catatan
        // Check In sudah ada, keterangan baru ditambahkan, tidak menimpanya.
        if (($v = trim((string) $request->input('catatan', ''))) !== '') {
            $isi['catatan'] = trim($baris->catatan.' '.$v);
        }

        $baris->update($isi);
        jurnal($u->id, 'AGRI_PRESENSI', $hari, 'keluar', $request->ip() ?? '');

        return back()->with('pesan', 'Check Out '.$kapan->format('H:i').' tercatat.'
            .$this->sinkronKeluar($svc, $u, $baris))->withFragment('daftar-hadir');
    }

    /**
     * Teruskan Check Out yang baru saja tercatat ke SIMKOPDES (bila absensi hari
     * itu sudah ada di sana).
     *
     * KENAPA INI PENTING: SIMKOPDES hanya punya satu baris per hari, dan Check In
     * Hub sudah membuat barisnya. Sebelum ini, Check Out berhenti di Hub — absensi
     * resmi di SIMKOPDES tetap berakhir di jam masuk, jadi rekap kehadiran di sana
     * memang tidak pernah sinkron dengan yang dilihat pendamping di Hub.
     *
     * Kegagalan TIDAK membatalkan Check Out di Hub: yang tercatat tetap tercatat,
     * dan pesannya menyebut sebabnya supaya bisa dicoba lagi.
     */
    private function sinkronKeluar(SimkopdesAbsensi $svc, User $u, AgriPresensi $p): string
    {
        if (! $svc->bolehHantar($u) || trim((string) $p->keluar) === '') {
            return '';
        }

        try {
            // Baris yang harus diperbarui di SIMKOPDES: cermin tanggal itu —
            // baris HUB kalau ada, kalau tidak baris resmi (SIMP) yang ditarik
            // dari sana. `samakan()` saja tidak cukup: ia sengaja memulangkan
            // null bila tanggal itu SUDAH punya catatan resmi, sedangkan justru
            // baris itulah yang jam keluarnya perlu diisi.
            $baris = $svc->samakan($u, $p)
                ?? BarisAbsen::where('user_id', $u->id)->where('tanggal', (string) $p->tanggal)
                    ->where('simkopdes_absensi_id', '!=', '')->orderByDesc('id')->first();
            if ($baris === null) {
                return '';
            }
            $hasil = $svc->kirimKeluar($u, $baris, $p);

            return $hasil['ok'] ? ' Terkirim juga ke SIMKOPDES (jam keluar resmi).' : ' SIMKOPDES: '.$hasil['pesan'];
        } catch (\Throwable $e) {
            report($e);

            return ' Check Out Hub tercatat, tetapi SIMKOPDES belum bisa dihubungi: '.$e->getMessage();
        }
    }

    /**
     * Teruskan Check In yang baru saja tercatat ke SIMKOPDES (bila manajernya
     * punya akun SIMKOPDES dan jamnya masih dalam jam kirim).
     *
     * Dipisah dari alur presensi supaya kegagalan sinkronisasi TIDAK pernah
     * membatalkan presensi Hub: yang sudah tercatat tetap tercatat, dan barisnya
     * bisa dikirim ulang dari halaman Absen KDKMP. Itu juga sebabnya hanya satu
     * keterangan singkat yang dikembalikan, bukan galat yang menghentikan alur.
     */
    private function sinkronKdkmp(SimkopdesAbsensi $svc, User $u, AgriPresensi $p, \Carbon\CarbonInterface $kapan): string
    {
        if (! $svc->bolehHantar($u)) {
            return '';
        }

        // Absensi SIMKOPDES permanen — tidak ada ubah, tidak ada hapus. Check In
        // yang jatuh di luar jam kirim (mis. pukul 18.00) TIDAK diteruskan
        // otomatis: menulis "check_in 18:00" untuk kedatangan pagi adalah bukti
        // yang salah selamanya. Barisnya tetap muncul di daftar sebagai tertunda,
        // dan bisa dikirim sendiri lewat tombolnya.
        if (! $svc->dalamJamKirim()) {
            $svc->samakan($u, $p);

            return ' Sinkronisasi SIMKOPDES ditahan: sekarang lewat jam '.SimkopdesAbsensi::CUTOFF_JAM
                .' WIB. Barisnya menunggu di daftar KDKMP di bawah.';
        }

        try {
            $baris = $svc->samakan($u, $p);
            if ($baris === null) {
                return '';
            }
            $hasil = $svc->kirim($u, $baris);

            return $hasil['ok'] ? ' Terkirim juga ke SIMKOPDES.' : ' SIMKOPDES: '.$hasil['pesan'];
        } catch (\Throwable $e) {
            report($e);

            return ' Presensi Hub tercatat, tetapi SIMKOPDES belum bisa dihubungi: '.$e->getMessage();
        }
    }

    /**
     * Waktu dari perangkat, dinyatakan dalam zona waktu yang dilaporkan
     * perangkat (mis. Asia/Jakarta) — jadi jam yang tersimpan adalah jam yang
     * DILIHAT pengguna di ponselnya.
     *
     * Mengembalikan null bila perangkat tidak melaporkan waktu, atau bila
     * jamnya melenceng lebih dari TOLERANSI_MENIT dari jam server. Yang kedua
     * itulah penjagaan terhadap jam yang diubah-ubah: waktu boleh milik
     * perangkat, tapi tidak boleh jauh dari jam server.
     */
    private function waktuPerangkat(Request $request): ?\Carbon\CarbonImmutable
    {
        $iso = trim((string) $request->input('waktu', ''));
        if ($iso === '') {
            return null;
        }

        try {
            $momen = \Carbon\CarbonImmutable::parse($iso);
        } catch (\Throwable) {
            return null;
        }

        if (abs(now()->getTimestamp() - $momen->getTimestamp()) > self::TOLERANSI_MENIT * 60) {
            return null;
        }

        // Nama zona dipakai HANYA untuk menampilkan jam lokal; titik waktunya
        // sudah pasti dari offset di ISO. Zona tak dikenal = pakai zona server.
        $zona = trim((string) $request->input('zona', ''));

        return in_array($zona, timezone_identifiers_list(), true) ? $momen->setTimezone($zona) : $momen;
    }

    /**
     * Simpan potret presensi, kembalikan nama berkasnya — atau null bila
     * potretnya tidak terbaca.
     *
     * SATU-SATUNYA sumber: `foto_data`, data URL dari kanvas yang diisi
     * hub-agri.js tepat sesudah kamera dijepret. Tidak ada jalur berkas
     * unggahan — kalau ada, foto lama dari galeri bisa dipakai dan bukti
     * kehadiran kehilangan artinya.
     *
     * Data URL diperiksa ISI-nya, bukan namanya, karena tanpa GD/Imagick kita
     * tidak bisa menyucikan gambar:
     *
     *   - hanya JPEG/PNG (raster). SVG DITOLAK: ia bisa memuat skrip dan
     *     disajikan inline oleh rute foto, dan kita tidak punya alat untuk
     *     membersihkannya;
     *   - dimensi wajib > 1 px. Potret 1x1 adalah tanda kamera palsu.
     *
     * `getimagesizefromstring` adalah bawaan PHP, tidak butuh GD.
     */
    /** Klien S3 untuk R2, dipakai hanya untuk menandatangani izin tulis. */
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
     * Izin tulis berdurasi pendek supaya peramban menaruh foto LANGSUNG di R2.
     *
     * Kenapa begini: kalau fotonya lewat server, satu berkas menahan satu worker
     * FPM selama seluruh byte berpindah (terukur 1.605-1.768 ms untuk 235 KB,
     * dengan plafon bandwidth server 17,95 Mbps). Sepuluh ribu absensi berarti
     * sepuluh ribu worker-detik yang dihabiskan hanya untuk memindahkan berkas.
     * Dengan presigned PUT, server bekerja 0,6 ms — hanya menandatangani — dan
     * byte-nya tidak pernah menyentuh mesin ini.
     *
     * Yang dikunci pada tanda tangan itu penting: ukuran PERSIS dan tipe
     * `image/jpeg`. R2 sendiri yang menolak kiriman yang menyimpang, jadi URL
     * ini tidak bisa dipakai menaruh berkas raksasa atau berkas lain.
     *
     * Nama berkas DIBUAT DI SINI (bukan oleh peramban) supaya tidak ada yang
     * bisa memilih sendiri nama, folder, atau menunjuk berkas milik orang lain.
     */
    public function presensi_url_unggah(Request $request)
    {
        $u = $request->user();
        $v = $request->validate([
            'ukuran' => ['required', 'integer', 'min:1024', 'max:'.(self::FOTO_MAKS_KB * 1024)],
        ], [
            'ukuran.max' => 'Foto melebihi batas '.round(self::FOTO_MAKS_KB / 1024, 1).' MB.',
        ]);

        $nama = 'agri/presensi/'.now()->format('Ymd').'-'.bin2hex(random_bytes(16)).'.jpg';

        // Kunci diikat ke penggunanya. Tanpa ikatan ini, siapa pun yang tahu nama
        // berkas orang lain bisa mengirimkannya sebagai fotonya sendiri.
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
            'url' => (string) $this->r2()
                ->createPresignedRequest($perintah, '+10 minutes')->getUri(),
        ]);
    }

    /**
     * Foto yang sudah ditaruh peramban di R2, diperiksa sebelum dipakai.
     *
     * Tanda tangan hanya mengatur IZIN TULIS; kebenaran isinya tetap harus
     * dipastikan di sini: kunci itu benar terbit untuk pengguna ini, ukurannya
     * masih di bawah batas, dan tiga byte pertamanya benar-benar JPEG. Tiga byte
     * saja yang dibaca (Range), bukan seluruh berkas — memeriksa isi tidak boleh
     * mengembalikan beban yang baru saja kita hemat.
     */
    private function fotoDariKunci(Request $request): ?string
    {
        $nama = trim((string) $request->input('foto_kunci', ''));
        if ($nama === '') {
            return null;
        }
        // Bentuk nama ditentukan server; yang tidak cocok bukan berkas kita.
        if (! preg_match('#^agri/presensi/\d{8}-[a-f0-9]{32}\.jpg$#', $nama)) {
            return null;
        }
        if ((int) Cache::get('unggah:'.$nama) !== (int) $request->user()->id) {
            return null;
        }

        // SATU permintaan ke R2, bukan tiga. `GET Range: bytes=0-2` mengembalikan
        // tiga byte pertama (magic JPEG) DAN `ContentRange` yang memuat ukuran
        // total berkas — jadi keberadaan, ukuran, dan keaslian gambar terbaca
        // sekaligus. Tiga round-trip (exists + size + get) menambah ~300 ms per
        // absensi; itu waktu yang tidak perlu dibayar sepuluh ribu kali.
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
            return null;   // belum diunggah / gagal di tengah jalan
        }

        if (strncmp($kepala, "\xFF\xD8\xFF", 3) !== 0 || $total <= 0 || $total > self::FOTO_MAKS_KB * 1024) {
            // Bukan JPEG, atau lebih besar dari yang dijanjikan: buang, jangan
            // biarkan berkas asing menumpuk di bucket.
            Storage::disk(self::DISK)->delete($nama);
            Cache::forget('unggah:'.$nama);

            return null;
        }

        Cache::forget('unggah:'.$nama);

        return $nama;
    }

    private function simpanFotoPresensi(Request $request): ?string
    {
        // Jalur utama: foto sudah ditaruh peramban LANGSUNG di R2, dan yang
        // sampai ke sini hanya NAMANYA. Tidak ada byte foto yang melewati
        // server, jadi worker FPM bebas begitu nama diterima — bedanya besar:
        // lewat server satu foto menahan satu worker 1,6 detik penuh.
        if (($kunci = $this->fotoDariKunci($request)) !== null) {
            return $kunci;
        }

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

        // Foto presensi TIDAK dipotong ulang di server: kontainer ini tidak punya
        // GD/Imagick, dan menyunting header JPEG (yang dulu dilakukan untuk
        // "memaksa" 3:4) tidak memotong piksel apa pun — dimensinya berubah di
        // atas kertas, gambarnya justru teregang, dan lebar/tinggi JPEG jadi
        // berbeda dari isi aslinya. Rasio 3:4 sekarang dijaga di dua tempat yang
        // memang bisa: hub-agri.js memotret tengah frame ke kanvas 3:4 sebelum
        // dikirim, dan CSS `.foto-presensi` menampilkannya dalam kotak 3:4.
        $nama = 'agri/presensi/'.now()->format('Ymd').'-'.bin2hex(random_bytes(12))
            .($m[1] === 'png' ? '.png' : '.jpg');

        return Storage::disk(self::DISK)->put($nama, $biner) ? $nama : null;
    }

    /** Koordinat disimpan sebagai teks apa adanya; kosong berarti tidak diisi. */
    private function angka(mixed $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, 32);
    }

    /**
     * Foto presensi. WAJIB lewat rute ber-`auth` ini, bukan tautan langsung ke
     * storage: foto presensi memuat wajah dan titik lokasi, jadi tidak boleh
     * bisa dibuka siapa pun yang menebak nama berkasnya.
     *
     * Foto hanya berguna kalau bisa DILIHAT, jadi responsnya inline (bukan
     * unduhan seperti berkas regulasi).
     *
     * Lingkupnya SAMA dengan daftar riwayat — presensi hanya milik sendiri,
     * termasuk untuk admin pusat. Daftar yang hanya menampilkan baris sendiri
     * tapi detailnya bisa membuka baris orang lain adalah dua lingkup untuk
     * satu data; hub ini memang tidak memegang mandat pengawasan kehadiran.
     */
    public function presensi_foto(Request $request, int $id)
    {
        // Pemilik baris selalu boleh; pemantau hanya sejauh lingkupnya — lihat
        // User::bolehLihatKehadiran(). Dulu di sini hanya peran yang diperiksa,
        // sehingga PIC Jogja bisa membuka foto manajer Papua dengan menebak id,
        // padahal daftar di halamannya sendiri sudah tersaring wilayah.
        $p = AgriPresensi::whereKey($id)->firstOrFail();
        abort_unless($request->user()->bolehLihatKehadiran((int) $p->user_id), 404, 'Foto presensi tidak ditemukan.');
        $nama = trim((string) ($request->query('sisi') === 'keluar' ? $p->keluar_foto : $p->masuk_foto));
        // `exists()` selalu benar untuk baris yang kolomnya menyimpan path —
        // yang perlu diperiksa adalah berkasnya benar-benar ada di disk.
        if ($nama === '' || Storage::disk(self::DISK)->size($nama) === 0) {
            abort(404, 'Foto presensi tidak ditemukan.');
        }

        return Storage::disk(self::DISK)->response($nama);
    }

    // ------------------------------------------------------------ Tugas pribadi

    public function tugas(Request $request)
    {
        $u = $request->user();
        $jenis = mb_strtoupper((string) $request->query('jenis', ''));
        $jenis = in_array($jenis, ['TASK', 'OVERTIME', 'REMINDER'], true) ? $jenis : '';

        $q = AgriTugas::where('user_id', $u->id)
            ->when($jenis !== '', fn ($w) => $w->where('jenis', $jenis))
            ->when($request->query('q'), fn ($w, $c) => $w->where('judul', 'like', '%'.$c.'%'))
            ->orderByRaw("CASE status WHEN 'BARU' THEN 0 WHEN 'PROSES' THEN 1 ELSE 2 END")
            ->orderBy('tanggal')->orderByDesc('id');

        $h = Halaman::buat((clone $q)->count(), $request->query('halaman', 1));

        return view('agri.tugas', [
            'aktif' => 'agri',
            'daftar' => $h->batasi($q)->get(), 'halaman' => $h,
            'cari' => trim((string) $request->query('q', '')), 'jenis' => $jenis,
            'ringkas' => AgriTugas::where('user_id', $u->id)->selectRaw('status, COUNT(*) n')
                ->groupBy('status')->pluck('n', 'status')->all(),
            // Kartu atas menyaring per JENIS, jadi angkanya pun per jenis — bukan
            // memakai ulang `ringkas` yang berisi status.
            'ringkasAktif' => AgriTugas::where('user_id', $u->id)->where('status', '!=', 'SELESAI')
                ->selectRaw('jenis, COUNT(*) n')->groupBy('jenis')->pluck('n', 'jenis')->all(),
        ]);
    }

    public function tugas_simpan(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'jenis' => ['required', 'in:TASK,OVERTIME,REMINDER'],
            'prioritas' => ['required', 'in:RENDAH,NORMAL,TINGGI'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jam' => ['nullable', 'date_format:H:i'],
        ]);

        AgriTugas::create($this->tanpaNull($d) + [
            'user_id' => $u->id, 'koperasi_id' => $this->koperasiId($u),
            'status' => 'BARU', 'jam' => $d['jam'] ?? '', 'dibuat' => kini(),
        ]);

        return back()->with('pesan', 'Tugas "'.$d['judul'].'" dicatat.');
    }

    /** BARU -> PROSES -> SELESAI (+ poin sekali saja, saat pertama selesai). */
    public function tugas_status(Request $request, int $id)
    {
        $u = $request->user();
        $t = AgriTugas::where('user_id', $u->id)->whereKey($id)->firstOrFail();
        $status = $request->validate(['status' => ['required', 'in:BARU,PROSES,SELESAI']])['status'];

        $baruSelesai = $status === 'SELESAI' && $t->status !== 'SELESAI';
        $t->update(['status' => $status]);
        if ($baruSelesai) {
            $this->poin($u->id, self::POIN_TUGAS, 'Tugas selesai: '.$t->judul);
        }

        return back()->with('pesan', 'Status tugas diubah ke '.$status.'.');
    }

    public function tugas_hapus(Request $request, int $id)
    {
        AgriTugas::where('user_id', $request->user()->id)->whereKey($id)->firstOrFail()->delete();

        return back()->with('pesan', 'Tugas dihapus.');
    }

    // ------------------------------------------------ Perjalanan dinas & klaim

    public function perjalanan(Request $request)
    {
        $u = $request->user();
        $jenis = mb_strtoupper((string) $request->query('jenis', ''));
        $jenis = array_key_exists($jenis, AgriPengajuan::JENIS) ? $jenis : '';
        $cari = trim((string) $request->query('q', ''));

        $saring = fn ($w) => $w->when($jenis !== '', fn ($x) => $x->where('jenis', $jenis))
            ->when($cari !== '', fn ($x) => $x->where('judul', 'like', '%'.$cari.'%'));

        // Dua daftar berhalaman di satu halaman: pengajuan SAYA dan antrean yang
        // menunggu keputusan saya. Kunci halaman dipisah, kalau tidak memindah
        // halaman antrean ikut menggeser daftar milik saya.
        $qSaya = $saring(AgriPengajuan::where('user_id', $u->id))->orderByDesc('id');
        $hSaya = Halaman::buat((clone $qSaya)->count(), $request->query('halaman', 1));

        $bolehPutus = $this->bolehMemutus($u);
        $qAntre = $bolehPutus
            ? $saring($this->queryPengajuan($request, true)->where('status', 'DIAJUKAN'))->orderByDesc('id')
            : null;
        $hAntre = $qAntre
            ? Halaman::buat((clone $qAntre)->count(), $request->query('halaman_antre', 1)) : null;

        return view('agri.perjalanan', [
            'aktif' => 'agri', 'jenis' => $jenis, 'cari' => $cari,
            'saya' => $hSaya->batasi($qSaya)->get(), 'halamanSaya' => $hSaya,
            'antre' => $qAntre ? $hAntre->batasi($qAntre)->get() : collect(),
            'halamanAntre' => $hAntre, 'bolehPutus' => $bolehPutus,
            'ringkas' => AgriPengajuan::where('user_id', $u->id)->selectRaw('status, COUNT(*) n')
                ->groupBy('status')->pluck('n', 'status')->all(),
            // Kartu atas menyaring per JENIS, jadi angkanya per jenis.
            'ringkasJenis' => AgriPengajuan::where('user_id', $u->id)->selectRaw('jenis, COUNT(*) n')
                ->groupBy('jenis')->pluck('n', 'jenis')->all(),
        ]);
    }

    public function ajuan_simpan(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'jenis' => ['required', 'in:PERJALANAN,IMBALAN,UMUM'],
            'judul' => ['required', 'string', 'max:160'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'nilai' => ['required', 'integer', 'min:0', 'max:999999999'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['nullable', 'date_format:Y-m-d'],
        ]);

        // Perjalanan wajib punya tanggal kembali; dua tanggal lain cukup satu.
        if ($d['jenis'] === 'PERJALANAN' && empty($d['tanggal_akhir'])) {
            return back()->withInput()->with('galat', 'Tanggal kembali wajib diisi untuk perjalanan dinas.');
        }
        if (! empty($d['tanggal_akhir']) && $d['tanggal_akhir'] < $d['tanggal']) {
            return back()->withInput()->with('galat', 'Tanggal kembali tidak boleh sebelum tanggal berangkat.');
        }

        $a = AgriPengajuan::create($this->tanpaNull($d) + [
            'user_id' => $u->id, 'koperasi_id' => $this->koperasiId($u),
            'tanggal_akhir' => $d['tanggal_akhir'] ?? '', 'status' => 'DIAJUKAN', 'dibuat' => kini(),
        ]);

        jurnal($u->id, 'AGRI_AJUAN', 'agri#'.$a->id, $a->jenis.' nilai='.$a->nilai, $request->ip() ?? '');

        return back()->with('pesan', 'Pengajuan "'.$a->judul.'" dikirim.');
    }

    /** Keputusan: pusat untuk semua, PIC untuk pengajuan koperasi di wilayahnya. */
    public function ajuan_putus(Request $request, int $id)
    {
        $u = $request->user();
        $a = AgriPengajuan::findOrFail($id);

        if (! $this->bolehMemutus($u) || ! $u->bolehKoperasi((int) $a->koperasi_id)) {
            abort(403, 'Pengajuan diputuskan admin Agrinas atau PIC wilayah koperasi pengaju.');
        }
        if ($a->status !== 'DIAJUKAN') {
            return back()->with('pesan', 'Pengajuan ini sudah diputuskan ('.$a->status.').');
        }

        $d = $request->validate([
            'status' => ['required', 'in:SETUJU,TOLAK'],
            'catatan' => ['nullable', 'string', 'max:300'],
        ]);
        if ($d['status'] === 'TOLAK' && trim((string) ($d['catatan'] ?? '')) === '') {
            return back()->with('galat', 'Alasan wajib diisi bila pengajuan ditolak.');
        }

        $a->update(['status' => $d['status'], 'catatan' => trim((string) ($d['catatan'] ?? '')),
            'pemutus_id' => $u->id, 'diputus' => kini()]);

        jurnal($u->id, 'AGRI_AJUAN_PUTUS', 'agri#'.$a->id, $d['status'], $request->ip() ?? '');

        return back()->with('pesan', 'Pengajuan '.mb_strtolower($d['status'] === 'SETUJU' ? 'disetujui' : 'ditolak').'.');
    }

    // ------------------------------------------------------- Imbalan & poin

    public function imbalan(Request $request)
    {
        $u = $request->user();
        $q = AgriPoin::where('user_id', $u->id)->orderByDesc('id');
        $h = Halaman::buat((clone $q)->count(), $request->query('halaman', 1));

        $qHadiah = AgriImbalan::where('aktif', 1)
            ->when($request->query('q'), fn ($w, $c) => $w->where('judul', 'like', '%'.$c.'%'))
            ->orderBy('biaya');
        $hHadiah = Halaman::buat((clone $qHadiah)->count(), $request->query('halaman_hadiah', 1), 6);

        return view('agri.imbalan', [
            'aktif' => 'agri',
            'saldo' => $this->saldoPoin($u->id),
            'mutasi' => $h->batasi($q)->get(), 'halaman' => $h,
            'hadiah' => $hHadiah->batasi($qHadiah)->get(), 'halamanHadiah' => $hHadiah,
            'cari' => trim((string) $request->query('q', '')),
            'pusat' => $u->agrinas(),
        ]);
    }

    /** Penukaran: satu transaksi — potong poin dan catat mutasinya. */
    public function imbalan_tukar(Request $request, int $id)
    {
        $u = $request->user();
        $g = AgriImbalan::where('aktif', 1)->whereKey($id)->firstOrFail();

        if ($g->stok !== 0 && $g->stok < 1) {
            return back()->with('galat', 'Stok hadiah ini habis.');
        }
        if ($this->saldoPoin($u->id) < $g->biaya) {
            return back()->with('galat', 'Poin belum cukup untuk menukar '.$g->judul.'.');
        }

        AgriPoin::create(['user_id' => $u->id, 'jumlah' => -$g->biaya,
            'hal' => 'Tukar: '.$g->judul, 'dibuat' => kini()]);
        if ($g->stok > 0) {
            $g->decrement('stok');
        }
        jurnal($u->id, 'AGRI_TUKAR', 'imbalan#'.$g->id, $g->judul.' biaya='.$g->biaya, $request->ip() ?? '');

        return back()->with('pesan', 'Penukaran '.$g->judul.' dicatat — poin terpotong '.$g->biaya.'.');
    }

    /**
     * Katalog hadiah hanya dikelola admin Agrinas (kewenangan pusat).
     *
     * Dipakai untuk TAMBAH maupun UBAH: satu formulir, satu penangan. `id` ada di
     * body berarti menyunting baris yang sudah ada — jadi tidak ada dua jalur yang
     * bisa saling menyimpang (dulu hanya ada tambah, sehingga hadiah yang salah
     * ketik tidak bisa diperbaiki sama sekali).
     */
    public function imbalan_simpan(Request $request)
    {
        if (! $request->user()->agrinas()) {
            abort(403, 'Katalog hadiah dikelola admin Agrinas.');
        }
        $d = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'judul' => ['required', 'string', 'max:160'],
            'keterangan' => ['nullable', 'string', 'max:300'],
            'biaya' => ['required', 'integer', 'min:0', 'max:10000000'],
            'stok' => ['required', 'integer', 'min:0', 'max:100000'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        $isi = $this->tanpaNull($d) + ['aktif' => (int) ($d['aktif'] ?? 1)];
        $id = (int) ($d['id'] ?? 0);
        $g = $id > 0 ? AgriImbalan::whereKey($id)->first() : null;

        if ($id > 0 && ! $g) {
            return back()->with('galat', 'Hadiah yang ingin diubah tidak ditemukan.');
        }
        if ($g) {
            unset($isi['id']);
            $g->update($isi);

            return back()->with('pesan', 'Hadiah "'.$d['judul'].'" diperbarui.');
        }

        unset($isi['id']);
        AgriImbalan::create($isi + ['dibuat' => kini()]);

        return back()->with('pesan', 'Hadiah "'.$d['judul'].'" ditambahkan ke katalog.');
    }

    /**
     * Hapus hadiah dari katalog.
     *
     * Dihapus benar-benar, bukan ditandai nonaktif: barisnya tidak dirujuk tabel
     * lain — penukaran hanya menulis mutasi poin ("Tukar: <judul>") di
     * `agri_poin`, bukan kunci asing. Jadi menghapus tidak memutus riwayat siapa
     * pun. Hadiah yang sudah pernah ditukar tetap boleh dihapus karena riwayatnya
     * hidup di mutasi poin.
     */
    public function imbalan_hapus(Request $request, int $id)
    {
        if (! $request->user()->agrinas()) {
            abort(403, 'Katalog hadiah dikelola admin Agrinas.');
        }
        $g = AgriImbalan::whereKey($id)->firstOrFail();
        $judul = $g->judul;
        $g->delete();

        return back()->with('pesan', 'Hadiah "'.$judul.'" dihapus dari katalog.');
    }

    // ----------------------------------------------------------- Umpan balik

    public function umpan(Request $request)
    {
        $u = $request->user();
        $arah = $request->query('arah') === 'keluar' ? 'keluar' : 'masuk';
        $cari = trim((string) $request->query('q', ''));

        $q = ($arah === 'masuk'
                ? AgriUmpan::where('kepada_id', $u->id)
                : AgriUmpan::where('oleh_id', $u->id))
            ->when($cari !== '', fn ($w) => $w->where('isi', 'like', '%'.$cari.'%'))
            ->orderByDesc('id');
        $h = Halaman::buat((clone $q)->count(), $request->query('halaman', 1));

        // Batas 500 nama: halaman ini memilih penerima dari satu daftar pilihan,
        // bukan menjelajah seluruh direktori. Kalau pegawai melewati 500, ganti
        // jadi kotak pencarian ber-AJAX seperti /workspace/koperasi.
        $penerima = User::where('aktif', 1)->whereKeyNot($u->id)
            ->orderBy('nama')->limit(500)->get(['id', 'nama', 'peran']);

        return view('agri.umpan', [
            'aktif' => 'agri', 'arah' => $arah, 'cari' => $cari,
            'daftar' => $h->batasi($q)->get(), 'halaman' => $h,
            'penerima' => $penerima,
            'ringkas' => [
                'masuk' => AgriUmpan::where('kepada_id', $u->id)->count(),
                'keluar' => AgriUmpan::where('oleh_id', $u->id)->count(),
            ],
        ]);
    }

    public function umpan_simpan(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'kepada_id' => ['required', 'integer'],
            'jenis' => ['required', 'in:UMUM,APRESIASI,SARAN'],
            'isi' => ['required', 'string', 'max:1000'],
            'anonim' => ['nullable', 'in:0,1'],
        ]);

        $kepada = User::where('aktif', 1)->whereKey($d['kepada_id'])->first();
        if (! $kepada || $kepada->id === $u->id) {
            return back()->withInput()->with('galat', 'Penerima umpan balik tidak sah.');
        }

        AgriUmpan::create(['kepada_id' => $kepada->id, 'oleh_id' => $u->id, 'jenis' => $d['jenis'],
            'isi' => $d['isi'], 'anonim' => (int) ($d['anonim'] ?? 0), 'dibuat' => kini()]);
        $this->poin($u->id, self::POIN_UMPAN, 'Umpan balik untuk '.$kepada->nama);

        return back()->with('pesan', 'Umpan balik terkirim ke '.$kepada->nama.'.');
    }

    // ------------------------------------------------------- Berkas regulasi

    public function berkas(Request $request)
    {
        $u = $request->user();
        $kategori = mb_strtoupper((string) $request->query('kategori', ''));
        $kategori = array_key_exists($kategori, AgriBerkas::KATEGORI) ? $kategori : '';
        $cari = trim((string) $request->query('q', ''));

        $q = $this->queryBerkas($request)
            ->when($kategori !== '', fn ($w) => $w->where('kategori', $kategori))
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('judul', 'like', '%'.$cari.'%')->orWhere('keterangan', 'like', '%'.$cari.'%')))
            ->orderByDesc('id');
        $h = Halaman::buat((clone $q)->count(), $request->query('halaman', 1));

        return view('agri.berkas', [
            'aktif' => 'agri', 'kategori' => $kategori, 'cari' => $cari,
            'daftar' => $h->batasi($q)->get(), 'halaman' => $h,
            'pusat' => $u->agrinas(),
            // Kartu atas menyaring per kategori; angkanya dari lingkup pengguna,
            // bukan seluruh tabel (PIC tidak boleh melihat hitungan pusat).
            'ringkasKategori' => (clone $this->queryBerkas($request))
                ->selectRaw('kategori, COUNT(*) n')->groupBy('kategori')->pluck('n', 'kategori')->all(),
        ]);
    }

    public function berkas_unggah(Request $request)
    {
        if (! $request->user()->agrinas()) {
            abort(403, 'Berkas regulasi diunggah admin Agrinas.');
        }
        $d = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'kategori' => ['required', 'in:REGULASI,PANDUAN,TEMPLATE'],
            'keterangan' => ['nullable', 'string', 'max:300'],
            'berkas' => ['required', 'file', 'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,png,jpg,jpeg'],
        ]);

        $f = $d['berkas'];
        $simpan = $f->store('agri/berkas', self::DISK);
        AgriBerkas::create(['koperasi_id' => null, 'kategori' => $d['kategori'], 'judul' => $d['judul'],
            'keterangan' => trim((string) ($d['keterangan'] ?? '')), 'nama_asli' => $f->getClientOriginalName(),
            'nama_simpan' => $simpan, 'mime' => (string) $f->getClientMimeType(),
            'ukuran' => (int) $f->getSize(), 'oleh' => $request->user()->id, 'dibuat' => kini()]);

        jurnal($request->user()->id, 'AGRI_BERKAS', $d['judul'], 'unggah', $request->ip() ?? '');

        return back()->with('pesan', 'Berkas "'.$d['judul'].'" tersimpan.');
    }

    public function berkas_hapus(Request $request, int $id)
    {
        if (! $request->user()->agrinas()) {
            abort(403, 'Berkas regulasi dihapus admin Agrinas.');
        }
        $b = AgriBerkas::findOrFail($id);
        if ($b->nama_simpan !== '') {
            Storage::disk(self::DISK)->delete($b->nama_simpan);
        }
        $b->delete();

        return back()->with('pesan', 'Berkas dihapus.');
    }

    /**
     * Unduhan WAJIB lewat rute ber-`auth` ini, bukan tautan langsung ke storage:
     * berkas regulasi hanya untuk pengguna yang sudah masuk.
     */
    public function berkas_unduh(Request $request, int $id)
    {
        $b = $this->queryBerkas($request)->whereKey($id)->firstOrFail();
        if ($b->nama_simpan === '' || ! Storage::disk(self::DISK)->exists($b->nama_simpan)) {
            abort(404, 'Berkas tidak ditemukan di penyimpanan.');
        }

        return Storage::disk(self::DISK)->download($b->nama_simpan, $b->nama_asli ?: null);
    }

    // ------------------------------------------------------------ E-Learning

    public function pelatihan(Request $request)
    {
        $u = $request->user();
        $cari = trim((string) $request->query('q', ''));

        $q = AgriMateri::query()
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('judul', 'like', '%'.$cari.'%')->orWhere('ringkas', 'like', '%'.$cari.'%')))
            ->orderBy('posisi')->orderBy('id');
        $h = Halaman::buat((clone $q)->count(), $request->query('halaman', 1));
        $baris = $h->batasi($q)->get();

        // Progres MILIK pengguna ini saja; satu query untuk semua kartu di
        // halaman aktif — bukan satu query per kartu.
        $selesai = \App\Models\LmsProgres::where('user_id', $u->id)
            ->whereIn('lms_id', $baris->pluck('id')->all())->pluck('lms_id')->all();
        $selesai = array_flip($selesai);

        return view('agri.pelatihan', [
            'aktif' => 'agri', 'cari' => $cari, 'daftar' => $baris, 'halaman' => $h,
            'selesai' => $selesai, 'pusat' => $u->agrinas(),
            'jumlahSelesai' => \App\Models\LmsProgres::where('user_id', $u->id)->count(),
            'jumlahMateri' => AgriMateri::count(),
        ]);
    }

    public function pelatihan_baca(Request $request, int $id)
    {
        $u = $request->user();
        $m = AgriMateri::findOrFail($id);

        return view('agri.pelatihan-baca', [
            'aktif' => 'agri', 'materi' => $m,
            'selesai' => (bool) \App\Models\LmsProgres::where('user_id', $u->id)
                ->where('lms_id', $m->id)->first(),
            'tetangga' => AgriMateri::orderBy('posisi')->orderBy('id')->get(['id', 'judul']),
            'langkah' => $this->langkahMateri($m),
            // Dibalik jadi peta supaya view bisa `isset($tercentang[$teks])` —
            // daftar langkah di view berupa TEKS, bukan id.
            'tercentang' => array_flip(AgriFokus::where('user_id', $u->id)
                ->where('materi_id', $m->id)->where('selesai', 1)->pluck('langkah')->all()),
            'pusat' => $u->agrinas(),
        ]);
    }

    public function pelatihan_selesai(Request $request, int $id)
    {
        $u = $request->user();
        AgriMateri::findOrFail($id);
        // Tabel progres warisan sudah punya kunci unik (user_id, lms_id).
        \App\Models\LmsProgres::firstOrCreate(
            ['user_id' => $u->id, 'lms_id' => $id],
            ['selesai_pada' => kini()]);

        return back()->with('pesan', 'Ditandai selesai.');
    }

    public function pelatihan_simpan(Request $request)
    {
        if (! $request->user()->agrinas()) {
            abort(403, 'Materi E-Learning ditambahkan admin Agrinas.');
        }
        $d = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'ringkas' => ['nullable', 'string', 'max:300'],
            'isi' => ['required', 'string', 'max:20000'],
            'menit' => ['required', 'integer', 'min:1', 'max:600'],
            'langkah' => ['nullable', 'string', 'max:4000'],
        ], [], ['langkah' => 'langkah']);

        AgriMateri::create($this->tanpaNull($d) + ['tautan' => '', 'posisi' => (int) AgriMateri::max('posisi') + 1,
            'oleh' => $request->user()->id, 'dibuat' => kini()]);

        return back()->with('pesan', 'Pelajaran "'.$d['judul'].'" ditambahkan.');
    }

    // ------------------------------------------------- Kolaborasi & struktur

    /**
     * Titik temu ke modul yang sudah mengerjakan pekerjaan yang sama. Sengaja
     * berupa halaman penunjuk: menyalin Messenger/Docs/Kalender ke dalam modul
     * ini akan menghasilkan dua tempat untuk satu percakapan, dan versi yang
     * lebih lemah.
     */
    public function ruang(Request $request)
    {
        return view('agri.ruang', [
            'aktif' => 'agri',
            'pesan' => \App\Models\PesanObrolan::count(),
            'terakhir' => \App\Models\PesanObrolan::orderByDesc('id')->limit(5)->get(),
            'dokumen' => \App\Models\Dokumen::orderByDesc('id')->limit(5)->get(),
            'rapat' => \App\Models\KonferensiRapat::orderByDesc('id')->limit(5)->get(),
        ]);
    }

    /**
     * Peta pengelola: admin pusat, PIC wilayah, dan manajer koperasi.
     *
     * Daftar manajer dibaca sebagai DAFTAR KOPERASI (satu manajer satu
     * koperasi), bukan daftar akun, supaya ikut lingkup yang terjangkau
     * pengguna — manajer yang koperasinya di luar wilayah PIC tidak ikut
     * tampil. Sekaligus memberi kolom Wilayah untuk disaring; kolom wilayah
     * tidak ada di tabel `users` sama sekali (hanya di `koperasi`).
     *
     * Dulu daftar ini diambil lewat `users` + `limit(500)` lalu disaring di
     * memori: pencarian tidak ada, dan `Halaman::buat` atas hasil `limit()`
     * membuat paging diam-diam mati (selalu 1 halaman). Sekarang jumlah,
     * pencarian, saringan wilayah, dan halaman dikerjakan SQL.
     */
    public function struktur(Request $request)
    {
        $u = $request->user();
        $cari = trim((string) $request->query('q', ''));
        $wilayah = mb_strtoupper(trim((string) $request->query('wilayah', '')));
        $wilayah = array_key_exists($wilayah, Wilayah::PILIHAN) ? $wilayah : '';

        // Dasar lingkup tanpa saringan: dipakai kartu ringkasan DI LUAR wadah
        // AJAX. Kalau kartunya ikut tersaring, setelah saring tanpa muat ulang
        // kartu tetap memuat angka lama — dua kebenaran di satu halaman.
        $dasar = ($u->lingkupPenuh() ? Koperasi::query() : $u->koperasiQuery())
            ->whereNotNull('manajer_id')
            ->whereHas('manajer', fn ($m) => $m->where('aktif', 1));

        $q = (clone $dasar)
            ->when($cari !== '', fn ($w) => $w->where(function ($x) use ($cari) {
                // Nama tempat (`koperasi.wilayah`) ikut dicari: kolom Koperasi
                // menampilkannya, jadi mengetik "Denpasar" harus menemukannya.
                $x->where('nama', 'like', '%'.$cari.'%')
                    ->orWhere('kode', 'like', '%'.$cari.'%')
                    ->orWhere('wilayah', 'like', '%'.$cari.'%')
                    ->orWhereHas('manajer', fn ($m) => $m
                        ->where('nama', 'like', '%'.$cari.'%')
                        ->orWhere('email', 'like', '%'.$cari.'%'));
            }))
            ->when($wilayah !== '', fn ($w) => $w->where(Wilayah::saring($wilayah)))
            ->with('manajer:id,nama,email')
            ->orderBy('nama')->orderBy('id');

        $h = Halaman::buat((clone $q)->count(), $request->query('halaman', 1));

        return view('agri.struktur', [
            'aktif' => 'agri',
            'pusat' => User::where('peran', 'AGRINAS')->where('aktif', 1)->orderBy('nama')->get(),
            'pic' => User::where('peran', 'PIC')->where('aktif', 1)->orderBy('wilayah')->get(),
            'koperasi' => $h->batasi($q)->get(),
            'halaman' => $h,
            'cari' => $cari,
            'wilayah' => $wilayah,
            'wilayahPilihan' => Wilayah::PILIHAN,
            'jumlahManajer' => $dasar->count(),
            'jumlahKoperasi' => $u->lingkupPenuh() ? Koperasi::count() : $u->koperasiQuery()->count(),
            // Cakupan kerja ikut halaman ini: halaman Struktur sudah memegang
            // peta pengelolanya, jadi menempelkan "berapa koperasi yang benar-
            // benar punya jejak kerja" di sini menghindari satu halaman analisa
            // tambahan untuk satu angka. Dihitung di App\Support\Analisa supaya
            // definisinya sama dengan halaman Analisa.
            'cakupan' => \App\Support\Analisa::cakupan($u),
            'saya' => $u,
        ]);
    }

    // ------------------------------------------- Ulang Tahun & Pengumuman

    /**
     * Rekan kerja yang berulang tahun. Tanggal lahir sudah ada di tabel
     * `profil` (hasil tarikan SIMKOPDES), jadi tidak perlu kolom baru. Bulan
     * berjalan didahulukan, tapi daftar setahun penuh tetap dikirim supaya
     * pengguna bisa melihat bulan lain tanpa pindah halaman.
     */
    public function ulangtahun(Request $request)
    {
        $u = $request->user();
        $bulan = (int) $request->query('bulan', 0);
        $bulan = $bulan >= 1 && $bulan <= 12 ? $bulan : (int) now()->format('n');

        $pokok = Koperasi::query()
            ->when(! $u->agrinas(), fn ($w) => $w->whereIn('id', $u->koperasiQuery()->select('koperasi.id')))
            ->pluck('nama', 'manajer_id');

        $profil = \App\Models\Profil::query()
            ->whereNotNull('tanggal_lahir')->where('tanggal_lahir', '!=', '')
            ->when($pokok->isNotEmpty(), fn ($w) => $w->whereIn('user_id', $pokok->keys()))
            ->get();

        $email = User::whereIn('id', $profil->pluck('user_id'))->pluck('email', 'id');
        $namaKop = Koperasi::pluck('nama', 'id');

        $rows = $profil
            ->map(function ($p) use ($email, $namaKop) {
                $tgl = $this->pisahTanggal($p->tanggal_lahir);
                if ($tgl === null) {
                    return null;
                }

                return (object) [
                    'nama' => (string) ($p->nama ?: ($email->get($p->user_id) ? 'Pengguna '.$p->user_id : '')),
                    'email' => (string) ($email->get($p->user_id) ?? ''),
                    'hari' => $tgl['h'], 'bulan' => $tgl['b'],
                    'umur' => $tgl['t'] > 0 ? max(0, (int) now()->format('Y') - $tgl['t']) : 0,
                    'koperasi' => (string) ($namaKop->get($p->koperasi_id) ?? ''),
                ];
            })
            ->filter()
            ->values();

        $bulanIni = $rows->filter(fn ($r) => $r->bulan === $bulan)
            ->sortBy(fn ($r) => $r->hari)->values();

        return view('agri.ulangtahun', [
            'aktif' => 'agri', 'bulan' => $bulan,
            'bulanIni' => $bulanIni,
            'setahun' => $rows->groupBy('bulan')->sortKeys(),
            'jumlahOrang' => $rows->count(),
        ]);
    }

    /**
     * Pengumuman pegawai. Membaca tabel `broadcast` — siaran yang sama dengan
     * yang dikirim admin di /admin/broadcast, dilihat dari sisi penerima.
     * Karena itu satu pengumuman tidak pernah perlu diketik dua kali.
     */
    // -------------------------------------------------------------- Kinerja

    /**
     * Rekap kinerja pribadi. Tidak menyimpan apa pun — ia membaca ulang data
     * yang sudah ditulis di Presensi, Tugas Saya, dan StarPoint. Menyalinnya ke
     * tabel "kinerja" tersendiri hanya akan membuat dua angka yang bisa berbeda.
     */
    public function kinerja(Request $request)
    {
        $u = $request->user();
        $bulan = (string) $request->query('bulan', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $bulan = now()->format('Y-m');
        }
        $dari = $bulan.'-01';
        $sampai = date('Y-m-t', strtotime($dari));

        $presensi = AgriPresensi::where('user_id', $u->id)
            ->whereBetween('tanggal', [$dari, $sampai])->orderBy('tanggal')->get();

        $menit = $presensi->sum(fn ($p) => $this->selisihMenit((string) $p->masuk, (string) $p->keluar));
        $terlambat = $presensi->filter(fn ($p) => $p->masuk !== '' && $p->masuk > self::JAM_MASUK)->count();

        $tugas = AgriTugas::where('user_id', $u->id)->whereBetween('tanggal', [$dari, $sampai])->get();
        $lembur = $tugas->where('jenis', 'OVERTIME')->count();

        $poinBulan = (int) AgriPoin::where('user_id', $u->id)
            ->where('dibuat', 'like', $bulan.'%')->sum('jumlah');

        return view('agri.kinerja', [
            'aktif' => 'agri', 'bulan' => $bulan,
            'hariHadir' => $presensi->count(),
            'lengkap' => $presensi->filter(fn ($p) => $p->masuk !== '' && $p->keluar !== '')->count(),
            'terlambat' => $terlambat, 'jamKerja' => intdiv($menit, 60), 'menitKerja' => $menit % 60,
            'jamMasuk' => self::JAM_MASUK,
            'tugasTotal' => $tugas->count(),
            'tugasSelesai' => $tugas->where('status', 'SELESAI')->count(),
            'lembur' => $lembur,
            'poinBulan' => $poinBulan,
            'saldo' => $this->saldoPoin($u->id),
            'umpanDiterima' => AgriUmpan::where('kepada_id', $u->id)->count(),
            'umpanDikirim' => AgriUmpan::where('oleh_id', $u->id)->count(),
        ]);
    }

    // ------------------------------------------------- E-Learn terarah

    /**
     * Simpan daftar langkah sebuah pelajaran (satu langkah per baris). Karena
     * daftarnya milik PELAJARAN — bukan per akun — ia disimpan sebagai kolom,
     * bukan tabel.
     */
    public function fokus_simpan(Request $request, int $id)
    {
        if (! $request->user()->agrinas()) {
            abort(403, 'Langkah pelajaran diatur admin Agrinas.');
        }
        $m = AgriMateri::findOrFail($id);
        $d = $request->validate(['langkah' => ['nullable', 'string', 'max:4000']], [], ['langkah' => 'langkah']);

        $m->forceFill(['langkah' => (string) ($d['langkah'] ?? '')])->save();

        return back()->with('pesan', 'Langkah pelajaran "'.$m->judul.'" disimpan.');
    }

    /**
     * Centang satu langkah. Poin hanya keluar pada centang PERTAMA — kuncinya
     * `firstOrCreate` + pemeriksaan `selesai`, jadi menekan dua kali (atau dua
     * tab) tidak menggandakan poin. Setelah langkah terakhir, pelajaran ditandai
     * selesai di `lms_progres` yang sudah ada.
     */
    public function fokus_centang(Request $request, int $id, string $langkah)
    {
        $u = $request->user();
        $m = AgriMateri::findOrFail($id);
        $daftar = $this->langkahMateri($m);

        $i = (int) $langkah;
        if ($i < 1 || ! isset($daftar[$i - 1])) {
            abort(404);
        }
        $teks = mb_substr($daftar[$i - 1], 0, 160);

        $f = AgriFokus::firstOrCreate(
            ['user_id' => $u->id, 'materi_id' => $m->id, 'langkah' => $teks],
            ['selesai' => 0, 'dibuat' => kini()]);

        if (! $f->selesai) {
            $f->forceFill(['selesai' => 1, 'dicatat' => kini()])->save();
            $this->poin($u->id, self::POIN_LANGKAH, 'Langkah E-Learning: '.mb_substr($m->judul, 0, 100));
        }

        $tercentang = AgriFokus::where('user_id', $u->id)->where('materi_id', $m->id)
            ->where('selesai', 1)->count();
        if ($daftar !== [] && $tercentang >= count($daftar)) {
            \App\Models\LmsProgres::firstOrCreate(['user_id' => $u->id, 'lms_id' => $m->id],
                ['selesai_pada' => kini()]);
        }

        return back()->with('pesan', 'Langkah '.$i.' ditandai selesai.');
    }

    /** Daftar langkah sebuah pelajaran; satu per baris, baris kosong dibuang. */
    private function langkahMateri(AgriMateri $m): array
    {
        $pisah = preg_split('/\r\n|\r|\n/u', (string) $m->langkah) ?: [];

        return array_values(array_filter(array_map('trim', $pisah), fn ($l) => $l !== ''));
    }

    // ------------------------------------------------------------------ utilitas

    /**
     * [tahun, bulan, hari] dari tanggal apa pun bentuknya; null bila tak
     * terbaca. Tanggal lahir di tabel `profil` datang dari tarikan SIMKOPDES
     * dengan bentuk yang tidak seragam, jadi jangan mengandaikan satu format.
     */
    private function pisahTanggal(?string $tgl): ?array
    {
        $t = trim((string) $tgl);
        if ($t === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'] as $f) {
            $d = \DateTimeImmutable::createFromFormat('!'.$f, $t);
            if ($d !== false && $d->format($f) === $t) {
                return ['t' => (int) $d->format('Y'), 'b' => (int) $d->format('n'), 'h' => (int) $d->format('j')];
            }
        }
        $ts = strtotime($t);

        return $ts ? ['t' => (int) date('Y', $ts), 'b' => (int) date('n', $ts), 'h' => (int) date('j', $ts)] : null;
    }

    /** Menit antara dua jam "HH:MM"; 0 bila salah satu kosong atau mundur. */
    private function selisihMenit(string $masuk, string $keluar): int
    {
        if (strlen($masuk) < 5 || strlen($keluar) < 5) {
            return 0;
        }
        $a = ((int) substr($masuk, 0, 2) * 60) + (int) substr($masuk, 3, 2);
        $b = ((int) substr($keluar, 0, 2) * 60) + (int) substr($keluar, 3, 2);

        return max(0, $b - $a);
    }

    // ------------------------------------------------------------------ utilitas

    /**
     * Buang nilai null dari hasil `validate()` sebelum insert.
     *
     * Kolom teks di modul ini NOT NULL DEFAULT '' — dan `validate()` mengisi
     * field opsional dengan NULL EKSPLISIT, bukan menghilangkannya. Karena
     * `$data + [tambahan]` (union array) TIDAK menimpa kunci yang sudah ada,
     * `'jam' => $data['jam'] ?? ''` tidak pernah berlaku saat `jam` bernilai
     * null: insert-nya gagal "NOT NULL constraint failed". Satu perbaikan di
     * sini menutup semua titik tulis, bukan satu guard per field.
     */
    private function tanpaNull(array $d): array
    {
        return array_filter($d, fn ($v) => $v !== null);
    }

    /** Koperasi milik pengguna ini; null untuk admin pusat (lingkupnya seluruh negeri). */
    private function koperasiId(User $u): ?int
    {
        return Koperasi::where('manajer_id', $u->id)->value('id');
    }

    private function saldoPoin(int $userId): int
    {
        return (int) AgriPoin::where('user_id', $userId)->sum('jumlah');
    }

    /** Satu-satunya tempat menulis mutasi poin, supaya saldo selalu bisa ditelusuri. */
    private function poin(int $userId, int $jumlah, string $hal): void
    {
        AgriPoin::create(['user_id' => $userId, 'jumlah' => $jumlah,
            'hal' => mb_substr($hal, 0, 160), 'dibuat' => kini()]);
    }

    /** Pusat & PIC boleh memutuskan; manajer tidak. */
    private function bolehMemutus(User $u): bool
    {
        return ! $u->manajerKoperasi();
    }

    /**
     * Pengajuan yang boleh diputus pengguna ini. `$lingkupSaja` = true membatasi
     * pada koperasi dalam jangkauannya (pusat: semua).
     */
    private function queryPengajuan(Request $request, bool $lingkupSaja = false)
    {
        $q = AgriPengajuan::query();
        $u = $request->user();
        if ($lingkupSaja && ! $u->agrinas()) {
            $q->whereIn('koperasi_id', $u->koperasiQuery()->select('koperasi.id'));
        }

        return $q;
    }

    /** Berkas regulasi: milik pusat (koperasi_id kosong) + milik koperasi terjangkau. */
    private function queryBerkas(Request $request)
    {
        $u = $request->user();
        $q = AgriBerkas::query();
        if ($u->agrinas()) {
            return $q;
        }

        return $q->where(fn ($w) => $w->whereNull('koperasi_id')
            ->orWhereIn('koperasi_id', $u->koperasiQuery()->select('koperasi.id')));
    }
}

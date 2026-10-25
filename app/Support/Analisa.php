<?php

namespace App\Support;

use App\Models\Koperasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Analisa ringkas lintas modul — satu tempat untuk seluruh angka turunan.
 *
 * Semua angka di sini DIHITUNG dari tabel yang sudah ada (tugas, distribusi,
 * pasokan, saldo, audit, koperasi). Tidak ada tabel ringkasan tersendiri: dua
 * tempat yang menyimpan angka yang sama akan berbeda begitu salah satunya
 * tertinggal, dan yang paling sering tertinggal adalah yang diperbarui manual.
 *
 * Dipakai dua halaman — Jurnal Audit (admin & PIC) dan Struktur & Tim
 * (AgriConnect) — supaya keduanya membaca definisi yang sama, bukan masing-masing
 * menghitung ulang dengan selera sendiri.
 */
final class Analisa
{
    /** Rentang tren harian, dalam hari. */
    public const HARI_TREN = 30;

    /**
     * Label aksi jurnal audit. Di sini, bukan di blade, karena dua halaman
     * memakainya (tabel jurnal & panel "aksi teratas") dan satu aksi tidak boleh
     * punya dua nama di aplikasi yang sama.
     *
     * Daftar ini SENGAJA tidak mencakup seluruh kode aksi: kode baru muncul tiap
     * kali ada modul baru, dan daftar manual akan selalu tertinggal. Nama yang
     * tidak ada di peta diturunkan dari kodenya (`TOKO_PESAN` → "Toko Pesan"),
     * jadi tidak pernah ada kode mentah tampil di dasbor.
     */
    public const LABEL_AKSI = [
        // Masuk/keluar — dua kode untuk satu hal, disamakan di sini.
        'MASUK' => 'Masuk', 'LOGIN' => 'Masuk',
        'MASUK_GAGAL' => 'Masuk gagal', 'LOGIN_GAGAL' => 'Masuk gagal',
        'KELUAR' => 'Keluar',
        'GANTI_SANDI' => 'Ganti sandi', 'RESET_SANDI' => 'Reset sandi',
        'KOPERASI_BARU' => 'Koperasi baru', 'KOPERASI_UBAH' => 'Koperasi diubah',
        'AKUN_BARU' => 'Akun baru', 'AKUN_AKTIF' => 'Akun diaktifkan',
        'AKUN_NONAKTIF' => 'Akun dinonaktifkan',
        'TUGAS_BARU' => 'Tugas baru', 'TUGAS_MASSAL' => 'Tugas massal',
        'TUGAS_STATUS' => 'Status tugas', 'TUGAS_KETERANGAN' => 'Keterangan tugas',
        'KIRIM_BERKAS' => 'Berkas dikirim', 'KIRIMAN' => 'Berkas dikirim',
        'KOMENTAR' => 'Komentar', 'SIARAN' => 'Siaran',
        'MATERI_UNGGAH' => 'Materi diunggah', 'MATERI_HAPUS' => 'Materi dihapus',
        'MATERI_UNDUH' => 'Materi diunduh',
        'RAPAT_JADWAL' => 'Rapat dijadwalkan', 'RAPAT_HAPUS' => 'Rapat dihapus',
        'DOKUMEN_BARU' => 'Dokumen baru', 'DOKUMEN_MINTA' => 'Akses dokumen diminta',
        // Katalog & pesanan (modul Toko). Kode mentah di sini paling mengganggu
        // karena "TOKO_PESAN" terbaca sebagai galat, bukan sebagai tindakan.
        'TOKO_PESAN' => 'Pesan katalog', 'TOKO_BAYAR' => 'Bayar pesanan',
        'TOKO_LEPAS' => 'Lepas pesanan', 'TOKO_HARGA' => 'Ubah harga',
        'TOKO_TERIMA' => 'Terima pesanan', 'TOKO_BATAL' => 'Batalkan pesanan',
        'TOKO_PANTAU' => 'Pantau pesanan', 'TOKO_AMBIL' => 'Ambil pesanan',
        'TOKO_KIRIM' => 'Kirim pesanan',
        'KATALOG_TAMBAH' => 'Katalog ditambah', 'KATALOG_UBAH' => 'Katalog diubah',
        'KATALOG_HAPUS' => 'Katalog dihapus', 'BARANG_BARU' => 'Barang baru',
        'KEUANGAN_SIMPAN' => 'Catat keuangan', 'KEUANGAN_EKSPOR' => 'Ekspor keuangan',
        'PASOKAN_AJU' => 'Pasokan diajukan', 'PASOKAN_DIAJUKAN' => 'Pasokan diajukan',
        'PASOKAN_DISETUJUI' => 'Pasokan disetujui', 'PASOKAN_DITOLAK' => 'Pasokan ditolak',
        'PASOKAN_TERIMA' => 'Pasokan diterima',
        'SDM_SIMPAN' => 'Data SDM disimpan', 'SDM_KEPUTUSAN' => 'Keputusan SDM',
        'SDM_REKRUT' => 'Rekrutmen', 'SDM_SLIP' => 'Slip gaji', 'SDM_NILAI' => 'Penilaian',
        'PAYROLL_TARIK' => 'Tarik data payroll', 'PAYROLL_EKSPOR' => 'Ekspor payroll',
        'AGRI_PRESENSI' => 'Presensi', 'AGRI_TUKAR' => 'Tukar StarPoint',
        'AGRI_AJUAN' => 'Ajuan perjalanan',
        'SIMP_TARIK' => 'Tarik SIMKOPDES', 'SIMP_LOGIN' => 'Masuk SIMKOPDES',
        'SIMP_ABSEN_TARIK' => 'Tarik absensi', 'SIMP_PROFIL' => 'Tarik profil',
        'PROFIL_TARIK' => 'Tarik profil',
        'WIKI_BUAT' => 'Wiki dibuat', 'WIKI_SIMPAN' => 'Wiki disimpan',
        'WIKI_GAMBAR' => 'Gambar wiki diunggah',
        'PELATIHAN_SELESAI' => 'Pelatihan selesai',
    ];

    /**
     * Nama enak dibaca untuk satu kode aksi. Kode yang belum terdaftar
     * diturunkan dari kodenya sendiri — bukan ditampilkan mentah, dan bukan
     * alasan untuk menambah entri setiap kali ada modul baru.
     */
    public static function labelAksi(string $aksi): string
    {
        return self::LABEL_AKSI[$aksi]
            ?? ucwords(strtolower(str_replace('_', ' ', trim($aksi))));
    }

    /**
     * Lingkup AKTOR jurnal, sudah berupa query id pengguna.
     *
     * PIC memegang satu wilayah: ia melihat jejak warganya saja. Pusat & HR
     * melihat seluruhnya. Dipakai bersama oleh `tren()`, `aksiTeratas()`, dan
     * pengambil daftar di DasborController — satu definisi lingkup, bukan tiga.
     */
    public static function aktorQuery(User $u): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()->select('id')
            ->when($u->pic(), fn ($w) => $w->where('wilayah', $u->wilayah))
            // Pengawasan hanya membaca jejak manajer di lingkupnya: aktor tidak
            // punya kolom wilayah sendiri (users.wilayah manajer kosong), jadi
            // wilayahnya diturunkan dari koperasi yang ia pimpin.
            ->when($u->pantau(), function ($w) use ($u) {
                $s = Wilayah::saringLingkup((string) $u->wilayah);

                return $s === null ? $w : $w->whereIn('id',
                    Koperasi::query()->where($s)->select('manajer_id'));
            });
    }

    /**
     * Cakupan kerja: berapa koperasi di lingkup pengguna, dan berapa yang benar-
     * benar punya jejak di tiap modul. Angka ini yang menjawab "sudah jalan
     * semua?" — jumlah koperasi saja tidak pernah menjawabnya.
     */
    public static function cakupan(User $u): array
    {
        $kop = $u->koperasiQuery()->select('koperasi.id')->pluck('id');

        $hitung = fn (string $tabel) => $kop->isEmpty() ? 0
            : (int) DB::table($tabel)->whereIn('koperasi_id', $kop)->distinct()->count('koperasi_id');

        return [
            'koperasi' => $kop->count(),
            'tugas' => $hitung('tugas'),
            'distribusi' => $hitung('distribusi'),
            'pasokan' => $hitung('pasokan'),
            // `saldo` boleh punya beberapa baris per koperasi (per akun/kanal),
            // jadi dikurangi ke koperasi UNIK sebelum dibandingkan.
            'saldo' => $kop->isEmpty() ? 0
                : (int) DB::table('saldo')->whereIn('koperasi_id', $kop)->distinct()->count('koperasi_id'),
            'gudang' => DB::table('barang')->whereColumn('stok', '<=', 'stok_min')->count(),
        ];
    }

    /**
     * Tren aktivitas harian dari jurnal audit.
     *
     * `audit.dibuat` disimpan sebagai ISO-8601 UTC; dikonversi ke Waktu Indonesia
     * Barat dulu supaya hari yang dihitung adalah hari kerja pengguna — tanpa itu
     * aktivitas pukul 00–07 WIB jatuh ke tanggal kemarin dan batangnya salah hari.
     */
    public static function tren(User $u, int $hari = self::HARI_TREN): array
    {
        $aktor = self::aktorQuery($u);
        $batas = now('Asia/Jakarta')->subDays($hari - 1)->toDateString();

        $n = DB::table('audit')->whereIn('aktor', $aktor)
            ->whereRaw("substr(datetime(dibuat, '+7 hours'), 1, 10) >= ?", [$batas])
            ->selectRaw("substr(datetime(dibuat, '+7 hours'), 1, 10) as tgl, count(*) as n")
            ->groupBy('tgl')->pluck('n', 'tgl');

        // Jendela dipangkas ke umur data yang benar-benar ada. Tanpa ini, jurnal
        // yang baru berjalan sembilan hari tetap digambar 30 batang — 21 batang
        // pertama kosong dan separuh grafiknya cuma latar. Jurnal yang sudah
        // cukup tua tetap memakai jendela penuh.
        $pertama = (string) DB::table('audit')->whereIn('aktor', $aktor)
            ->min(DB::raw("substr(datetime(dibuat, '+7 hours'), 1, 10)"));

        // Deret dibangun dengan MENAMBAH hari dari tanggal awal, bukan menghitung
        // selisih hari: `diffInDays` Carbon 3 bertanda sesuai arah argumen, jadi
        // memanggilnya dengan urutan yang salah menghasilkan selisih NEGATIF dan
        // jendelanya menciut jadi satu batang (pernah terjadi, terukur "1 Hari").
        $seri = [];
        $kaki = \Illuminate\Support\Carbon::parse($pertama !== '' ? max($batas, $pertama) : $batas, 'Asia/Jakarta')->startOfDay();
        $akhir = now('Asia/Jakarta')->startOfDay();
        while ($kaki->lessThanOrEqualTo($akhir)) {
            $tgl = $kaki->toDateString();
            // Hari tanpa aktivitas tetap muncul sebagai batang nol; kalau tidak,
            // grafiknya memampatkan hari sepi dan trennya terbaca lebih ramai.
            $seri[] = ['tgl' => $tgl, 'n' => (int) ($n[$tgl] ?? 0)];
            $kaki->addDay();
        }

        $nilai = array_column($seri, 'n');

        return [
            'seri' => $seri,
            'hari' => count($seri),
            'total' => array_sum($nilai),
            'puncak' => $nilai ? max($nilai) : 0,
            'hari_ini' => $seri ? $seri[count($seri) - 1]['n'] : 0,
            'rata' => (int) round(array_sum($nilai) / max(1, count($seri))),
        ];
    }

    /** Aksi apa yang paling sering terjadi — dari jurnal yang sama. */
    public static function aksiTeratas(User $u, int $batas = 6): array
    {
        return DB::table('audit')
            ->whereIn('aktor', self::aktorQuery($u))
            ->selectRaw('aksi, count(*) as n')
            ->groupBy('aksi')->orderByDesc('n')->limit($batas)
            ->get()->map(fn ($r) => ['aksi' => $r->aksi, 'n' => (int) $r->n])->all();
    }

    /**
     * Sebaran aktivitas per koperasi — menjawab "mana yang sudah jalan, mana yang
     * belum", yang tidak terlihat dari daftar tabel biasa. Per koperasi dihitung
     * pakai sub-select, jadi jumlah query-nya tetap satu untuk seluruh daftar.
     */
    public static function sebaran(User $u): array
    {
        $lingkup = $u->koperasiQuery()->select('koperasi.id');

        $baris = Koperasi::query()
            ->whereIn('koperasi.id', $lingkup)
            ->select('koperasi.id', 'koperasi.nama')
            ->selectRaw('(SELECT COUNT(*) FROM tugas t WHERE t.koperasi_id = koperasi.id) as tugas')
            ->selectRaw('(SELECT COUNT(*) FROM distribusi d WHERE d.koperasi_id = koperasi.id) as distribusi')
            ->selectRaw('(SELECT COUNT(*) FROM pasokan p WHERE p.koperasi_id = koperasi.id) as pasokan')
            ->orderBy('koperasi.nama')->get()
            ->map(fn ($k) => [
                'nama' => $k->nama,
                'jumlah' => (int) $k->tugas + (int) $k->distribusi + (int) $k->pasokan,
            ]);

        // Ambang sengaja kasar (0 / 1–4 / 5–19 / 20+): untuk data sekecil ini
        // batas yang lebih halus hanya memindahkan batang tanpa menambah arti.
        // ponytail: ambang tetap, ganti jadi kuantil kalau jumlah koperasi
        // melewati ratusan dan sebarannya menumpuk di satu ember.
        $ember = [
            ['label' => 'Belum ada aktivitas', 'kunci' => 'nol', 'jml' => 0],
            ['label' => '1–4 catatan', 'kunci' => 'rendah', 'jml' => 0],
            ['label' => '5–19 catatan', 'kunci' => 'sedang', 'jml' => 0],
            ['label' => '20+ catatan', 'kunci' => 'tinggi', 'jml' => 0],
        ];
        $dorman = [];
        foreach ($baris as $b) {
            $i = $b['jumlah'] === 0 ? 0 : ($b['jumlah'] <= 4 ? 1 : ($b['jumlah'] <= 19 ? 2 : 3));
            $ember[$i]['jml']++;
            if ($i === 0) {
                $dorman[] = $b['nama'];
            }
        }

        return [
            'ember' => $ember,
            'total' => $baris->count(),
            'dorman' => $dorman,
        ];
    }

    /**
     * Aktivitas menurut provinsi — peta kehadiran program lintas wilayah.
     * Koperasi tanpa provinsi dikelompokkan apa adanya, bukan dibuang.
     */
    public static function wilayah(User $u): array
    {
        $lingkup = $u->koperasiQuery()->select('koperasi.id');

        $r = Koperasi::query()
            ->whereIn('koperasi.id', $lingkup)
            ->selectRaw("COALESCE(NULLIF(provinsi, ''), '(tanpa provinsi)') as provinsi")
            ->selectRaw('COUNT(DISTINCT koperasi.id) as koperasi')
            ->selectRaw('(SELECT COUNT(*) FROM tugas t JOIN koperasi k2 ON k2.id = t.koperasi_id WHERE k2.provinsi = COALESCE(koperasi.provinsi, \'\')) as tugas')
            ->selectRaw('(SELECT COUNT(*) FROM distribusi d JOIN koperasi k3 ON k3.id = d.koperasi_id WHERE k3.provinsi = COALESCE(koperasi.provinsi, \'\')) as distribusi')
            ->selectRaw('(SELECT COUNT(*) FROM pasokan p JOIN koperasi k4 ON k4.id = p.koperasi_id WHERE k4.provinsi = COALESCE(koperasi.provinsi, \'\')) as pasokan')
            ->groupBy('provinsi')
            ->orderByDesc('koperasi')->orderBy('provinsi')
            ->get()
            ->map(function ($x) {
                $x->jumlah = (int) $x->tugas + (int) $x->distribusi + (int) $x->pasokan;

                return $x;
            })->all();

        return ['baris' => $r, 'maks' => max(1, ...array_map(fn ($x) => $x->jumlah, $r ?: [(object) ['jumlah' => 0]]))];
    }

    /**
     * Kesiapan yang bisa ditindaklanjuti. Semua dihitung dari kolom yang sudah
     * terisi — tidak ada input baru yang diminta ke pengguna.
     */
    public static function kesiapan(User $u): array
    {
        $kop = $u->koperasiQuery()->select('koperasi.id');
        $total = Koperasi::query()->whereIn('koperasi.id', $kop)->count();

        $ada = fn (string $kolom) => (int) Koperasi::query()->whereIn('koperasi.id', $kop)
            ->whereNotNull($kolom)->where($kolom, '<>', '')->count();

        $akun = User::query();
        if ($u->pic()) {
            $akun->where('wilayah', $u->wilayah);
        }

        return [
            'total' => $total,
            'manajer' => (int) Koperasi::query()->whereIn('koperasi.id', $kop)->whereNotNull('manajer_id')->count(),
            'rekening' => $ada('no_rekening'),
            'provinsi' => $ada('provinsi'),
            'akun_aktif' => (clone $akun)->where('aktif', 1)->count(),
            'akun_total' => (clone $akun)->count(),
        ];
    }
}

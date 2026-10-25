<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Keuangan;
use App\Models\Koperasi;
use App\Support\Halaman;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Keuangan koperasi: manajer memasukkan angka HARIAN, sistem merekapnya menjadi
 * mingguan, bulanan, dan tahunan. Rekap tidak pernah disimpan — selalu dihitung
 * dari baris harian, jadi mustahil ada dua angka yang berbeda untuk periode sama.
 *
 * Halaman menyajikan SATU daftar bernama "Buku Kas": baris harian, dicari,
 * disaring, dan dihalamankan. Sebelumnya ada DUA tabel berisi baris harian yang
 * sama — "Rekap <periode>" (cakupan periode) dan "Riwayat" (seluruh riwayat) —
 * sehingga angka yang sama muncul dua kali di satu layar dengan cakupan berbeda.
 */
class KeuanganController extends Controller
{
    /** Periode rekap yang dikenal, dengan rentang tanggalnya. */
    public const PERIODE = ['mingguan' => 'Mingguan', 'bulanan' => 'Bulanan', 'tahunan' => 'Tahunan'];

    private function koperasi(Request $request): ?Koperasi
    {
        $u = $request->user();

        // Pusat & PIC: pilih koperasi lewat ?koperasi=ID, tetapi hanya yang masuk
        // lingkupnya (PIC tidak bisa mengintip wilayah lain).
        if (! $u->manajerKoperasi()) {
            $id = (int) $request->query('koperasi', 0);

            // BAWAAN = AGREGAT (null) = seluruh koperasi dalam lingkup akun.
            // Dulu bawaan di sini `orderBy('nama')->first()`: halaman selalu
            // membuka koperasi pertama menurut abjad (KOP-06 KDMP Bina Usaha),
            // padahal pengelola datang untuk melihat gambaran keseluruhan.
            // Memilih satu koperasi harus tindakan yang disengaja (?koperasi=ID).
            //
            // `koperasi=-1` juga berarti agregat — dipakai tautan "Seluruh
            // koperasi" supaya tetap bisa kembali dari mode satu koperasi.
            if ($id === 0 || $id === -1) {
                return null;
            }

            // ID yang diminta tapi di luar lingkup juga jadi agregat, bukan galat:
            // halaman tetap menampilkan sesuatu yang benar.
            return $u->koperasiQuery()->whereKey($id)->first();
        }

        return Koperasi::where('manajer_id', $u->id)->first();
    }

    /** Rentang tanggal [awal, akhir] untuk sebuah periode, dari satu tanggal acuan. */
    public static function rentang(string $periode, string $acuan): array
    {
        $d = Carbon::parse($acuan);

        return match ($periode) {
            'mingguan' => [$d->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                           $d->copy()->endOfWeek(Carbon::SUNDAY)->toDateString()],
            'tahunan'  => [$d->copy()->startOfYear()->toDateString(),
                           $d->copy()->endOfYear()->toDateString()],
            default    => [$d->copy()->startOfMonth()->toDateString(),
                           $d->copy()->endOfMonth()->toDateString()],
        };
    }

    /**
     * Rekap ANGKA satu periode: total + pecahan per bulan. Sengaja TIDAK menarik
     * baris hariannya — pemanggil yang butuh barisnya (Buku Kas, ekspor) punya
     * query sendiri yang sudah dibatasi halaman/periode. Dulu di sini ada
     * `->get()` seluruh periode lalu dijumlahkan di PHP: dua kali kerja untuk
     * angka yang bisa dihitung database dalam satu baris.
     */
    /**
     * Rekap satu periode. `$koperasiId = null` berarti SELURUH koperasi dalam
     * lingkup akun (mode agregat) — satu ekspresi, dua mode, jadi mustahil
     * menyimpang antara tampilan satu koperasi dan tampilan keseluruhan.
     *
     * `hari_terisi` memakai COUNT(DISTINCT tanggal): untuk satu koperasi ia sama
     * dengan jumlah baris (ada UNIQUE(koperasi_id,tanggal)), sedangkan pada
     * agregat ia berarti "berapa hari yang punya catatan" — angka yang masuk akal
     * (jumlah baris 18 koperasi akan meledak jadi ratusan dan tak berarti).
     */
    public static function rekap(?int $koperasiId, string $periode, string $acuan,
                                 ?array $lingkupIds = null): array
    {
        [$awal, $akhir] = self::rentang($periode, $acuan);

        $q = Keuangan::query()
            ->when($koperasiId !== null, fn ($w) => $w->where('koperasi_id', $koperasiId))
            ->when($koperasiId === null && $lingkupIds !== null,
                fn ($w) => $w->whereIn('koperasi_id', $lingkupIds))
            ->whereBetween('tanggal', [$awal, $akhir]);

        $t = (clone $q)->selectRaw('COALESCE(SUM(pemasukan),0) m, COALESCE(SUM(pengeluaran),0) k,
                                    COUNT(DISTINCT tanggal) n')->first();

        return [
            'awal' => $awal, 'akhir' => $akhir,
            'pemasukan' => (int) $t->m, 'pengeluaran' => (int) $t->k, 'hari_terisi' => (int) $t->n,
            // Pecahan per bulan hanya berguna di tampilan tahunan — jangan
            // dihitung untuk tampilan yang tak memakainya.
            'bulan' => $periode !== 'tahunan' ? collect() : (clone $q)
                ->selectRaw('substr(tanggal,1,7) b, SUM(pemasukan) m, SUM(pengeluaran) k')
                ->groupBy('b')->orderBy('b')->get()
                ->mapWithKeys(fn ($r) => [$r->b => ['pemasukan' => (int) $r->m,
                                                     'pengeluaran' => (int) $r->k]]),
        ];
    }

    /** Selisih satu periode saja (untuk label tab pemilih periode). */
    public static function neto(?int $koperasiId, string $periode, string $acuan,
                               ?array $lingkupIds = null): int
    {
        [$awal, $akhir] = self::rentang($periode, $acuan);
        $t = Keuangan::query()
            ->when($koperasiId !== null, fn ($w) => $w->where('koperasi_id', $koperasiId))
            ->when($koperasiId === null && $lingkupIds !== null,
                fn ($w) => $w->whereIn('koperasi_id', $lingkupIds))
            ->whereBetween('tanggal', [$awal, $akhir])
            ->selectRaw('COALESCE(SUM(pemasukan),0) - COALESCE(SUM(pengeluaran),0) n')->first();

        return (int) $t->n;
    }

    /**
     * Saldo kas SEBELUM satu tanggal: seluruh riwayat sampai sehari sebelumnya.
     *
     * Buku Kas mencatat setiap rupiah masuk & keluar sejak baris pertamanya, jadi
     * saldo berjalan = jumlah neto seluruh baris sebelumnya. Tidak ada kolom
     * "saldo" yang disimpan — angka turunan, mustahil berselisih dengan buku.
     */
    public static function saldoSebelum(?int $koperasiId, string $tanggal,
                                       ?array $lingkupIds = null): int
    {
        $t = Keuangan::query()
            ->when($koperasiId !== null, fn ($w) => $w->where('koperasi_id', $koperasiId))
            ->when($koperasiId === null && $lingkupIds !== null,
                fn ($w) => $w->whereIn('koperasi_id', $lingkupIds))
            ->where('tanggal', '<', $tanggal)
            ->selectRaw('COALESCE(SUM(pemasukan),0) - COALESCE(SUM(pengeluaran),0) n')->first();

        return (int) $t->n;
    }

    /**
     * Rekap per KATEGORI kas satu periode: berapa masuk dari penjualan, berapa
     * keluar untuk operasional, dan seterusnya.
     *
     * Satu kategori per total harian (lihat Domain::KATEGORI_PEMASUKAN): tabel
     * `keuangan` menyimpan total masuk & total keluar sehari, bukan jurnal per
     * transaksi. Baris lama tanpa kategori dikelompokkan sebagai "lain-lain"
     * supaya jumlahnya tetap utuh — total per kategori SELALU sama dengan total
     * periode, dan gate memeriksa invarian itu.
     */
    public static function rekapKategori(?int $koperasiId, string $periode, string $acuan,
                                         ?array $lingkupIds = null): array
    {
        [$awal, $akhir] = self::rentang($periode, $acuan);

        $dasar = fn () => Keuangan::query()
            ->when($koperasiId !== null, fn ($w) => $w->where('koperasi_id', $koperasiId))
            ->when($koperasiId === null && $lingkupIds !== null,
                fn ($w) => $w->whereIn('koperasi_id', $lingkupIds))
            ->whereBetween('tanggal', [$awal, $akhir]);

        $masuk = $dasar()->where('pemasukan', '>', 0)
            ->selectRaw("COALESCE(NULLIF(kategori_pemasukan,''),'LAIN_MASUK') k, SUM(pemasukan) n")
            ->groupBy('k')->pluck('n', 'k')->all();

        $keluar = $dasar()->where('pengeluaran', '>', 0)
            ->selectRaw("COALESCE(NULLIF(kategori_pengeluaran,''),'LAIN_KELUAR') k, SUM(pengeluaran) n")
            ->groupBy('k')->pluck('n', 'k')->all();

        // Urutan = urutan daftar kategori (bukan urutan nominal): pembaca
        // membandingkan pos yang sama antar periode, jadi posisi barisnya tetap.
        $rapi = function (array $angka, array $label): array {
            $hasil = [];
            foreach ($label as $kode => $teks) {
                if (($angka[$kode] ?? 0) > 0) {
                    $hasil[] = ['kode' => $kode, 'label' => $teks, 'nilai' => (int) $angka[$kode]];
                }
            }
            // Kode di luar daftar (mis. kategori lama/dihapus) tetap tampil.
            foreach ($angka as $kode => $n) {
                if (! isset($label[$kode]) && (int) $n > 0) {
                    $hasil[] = ['kode' => $kode, 'label' => $kode, 'nilai' => (int) $n];
                }
            }

            return $hasil;
        };

        return ['masuk' => $rapi($masuk, Domain::KATEGORI_PEMASUKAN),
                'keluar' => $rapi($keluar, Domain::KATEGORI_PENGELUARAN)];
    }

    /** Halaman utama: ringkasan periode, Buku Kas (cari/saring/halaman), form catat. */
    public function index(Request $request)
    {
        $u = $request->user();
        $kop = $this->koperasi($request);
        $periode = in_array($request->query('periode'), array_keys(self::PERIODE), true)
            ? $request->query('periode') : 'bulanan';
        $acuan = (string) ($request->query('acuan') ?: now()->toDateString());

        // Agregat = pengelola pusat/PIC, atau manajer yang koperasinya belum ada.
        // Keduanya berarti "tidak ada satu koperasi yang dipilih", tetapi hanya
        // pengelola yang boleh melihat gabungan semua koperasi.
        $pengelola = ! $u->manajerKoperasi();
        $agregat = $pengelola && $kop === null;
        // Daftar ID, bukan builder: builder yang sama tidak bisa dipakai dua kali,
        // dan daftarnya kecil (satu baris per koperasi dalam lingkup).
        $idsLingkup = $pengelola ? $u->koperasiQuery()->pluck('koperasi.id')->all() : [];

        if (! $kop && ! $agregat) {
            return view('keuangan.index', ['aktif' => 'keuangan', 'koperasi' => null,
                'agregat' => false, 'periode' => $periode, 'acuan' => $acuan, 'rekap' => null,
                'bulan' => collect(), 'buku' => collect(), 'halaman' => null,
                'aliran' => collect(), 'maks' => 1,
                'q' => '', 'jenis' => '', 'lingkup' => 'periode', 'kategori' => '',
                'pilihanKoperasi' => null, 'totalKoperasi' => 0,
                'cariKop' => '', 'hasilKoperasi' => collect(), 'neto' => collect(),
                'kategoriRekap' => ['masuk' => [], 'keluar' => []],
                'saldoAwal' => 0, 'saldoAkhir' => 0]);
        }

        $q = trim((string) $request->query('q', ''));
        $jenis = in_array($request->query('jenis'), ['manual', 'otomatis'], true)
            ? $request->query('jenis') : '';
        // `lingkup=semua` = seluruh riwayat (dulu tabel "Riwayat" terpisah).
        $lingkup = $request->query('lingkup') === 'semua' ? 'semua' : 'periode';
        // Saringan kategori: satu nilai dari daftar yang sah, '' = semua.
        $kategori = (string) $request->query('kategori', '');
        if (! isset(Domain::KATEGORI_PEMASUKAN[$kategori])
            && ! isset(Domain::KATEGORI_PENGELUARAN[$kategori])) {
            $kategori = '';
        }

        $rekap = self::rekap($agregat ? null : $kop->id, $periode, $acuan,
            $agregat ? $idsLingkup : null);

        // ------------------------------------------------- pemilih koperasi (cari)
        // Dijalankan hanya bila pengguna benar-benar mengetik sesuatu; satu
        // pencarian dipakai bersama jalur tanpa-JavaScript (Enter mengirim
        // formulir) dan jalur JavaScript (kotak mengambang), jadi keduanya
        // mustahil menampilkan hasil yang berbeda.
        $cariKop = trim((string) $request->query('cari_kop', ''));
        $hasilKoperasi = $this->cariKoperasi($request, $cariKop);

        // ------------------------------------------------------------------ Buku Kas
        // Agregat: baris dari seluruh koperasi dalam lingkup, dan nama koperasinya
        // diikutkan supaya buku kas gabungan tetap bisa ditelusuri.
        $dasar = $agregat
            ? Keuangan::query()->whereIn('keuangan.koperasi_id', $idsLingkup)
                ->join('koperasi as k', 'k.id', '=', 'keuangan.koperasi_id')
                ->select('keuangan.*', 'k.nama as nama_koperasi', 'k.kode as kode_koperasi')
            : Keuangan::query()->where('koperasi_id', $kop->id);

        $buku = (clone $dasar);
        if ($lingkup === 'periode') {
            $buku->whereBetween('tanggal', [$rekap['awal'], $rekap['akhir']]);
        }

        // Asal baris: `kanal` diisi hanya oleh mutasi otomatis (toko memakai
        // 'KAS'/'AGRN'); baris yang dicatat manual meninggalkannya kosong.
        if ($jenis === 'manual') {
            $buku->where(fn ($w) => $w->whereNull('kanal')->orWhere('kanal', ''));
        } elseif ($jenis === 'otomatis') {
            $buku->whereNotNull('kanal')->where('kanal', '<>', '');
        }

        if ($q !== '') {
            $buku->where(fn ($w) => $w->where('rincian_pemasukan', 'like', '%'.$q.'%')
                ->orWhere('rincian_pengeluaran', 'like', '%'.$q.'%')
                ->orWhere('catatan', 'like', '%'.$q.'%')
                ->orWhere('ref', 'like', '%'.$q.'%'));
        }

        // Satu pilihan kategori bisa berasal dari sisi masuk ATAU keluar, jadi
        // pencocokannya dua kolom — tanpa mengubah total: baris yang kategorinya
        // hanya di satu sisi tetap tampil utuh (kedua angkanya).
        if ($kategori !== '') {
            $buku->where(fn ($w) => $w->where('kategori_pemasukan', $kategori)
                ->orWhere('kategori_pengeluaran', $kategori));
        }

        $buku->orderByDesc('tanggal');
        $halaman = Halaman::buat((clone $buku)->count(), $request->query('halaman', 1),
            DasborController::PER_HALAMAN);

        // ------------------------------------------------------------- arus kas
        // Batang arus kas: per hari untuk mingguan/bulanan, per bulan untuk
        // tahunan (365 batang tak terbaca). Dihitung dari periode, TANPA saringan
        // — ia menjawab "bagaimana bentuk kas periode ini", bukan "hasil carian".
        $aliran = $periode === 'tahunan'
            ? $rekap['bulan']->map(fn ($v, $k) => ['label' => tanggal_id($k.'-01', 'M'),
                                                   'net' => $v['pemasukan'] - $v['pengeluaran']])->values()
            : (clone $dasar)->whereBetween('tanggal', [$rekap['awal'], $rekap['akhir']])
                ->orderBy('tanggal')->get(['tanggal', 'pemasukan', 'pengeluaran'])
                ->map(fn ($h) => ['label' => substr($h->tanggal, 8, 2),
                                  'net' => $h->pemasukan - $h->pengeluaran])->values();

        return view('keuangan.index', [
            'aktif' => 'keuangan', 'koperasi' => $kop, 'agregat' => $agregat,
            'periode' => $periode, 'acuan' => $acuan,
            'rekap' => $rekap, 'bulan' => $rekap['bulan'],
            'neto' => collect(self::PERIODE)->keys()->mapWithKeys(fn ($p) => [$p => self::neto(
                $agregat ? null : $kop->id, $p, $acuan, $agregat ? $idsLingkup : null)]),
            'buku' => $halaman->batasi($buku)->get(), 'halaman' => $halaman,
            'aliran' => $aliran, 'maks' => max(1, (int) $aliran->max(fn ($a) => abs($a['net']))),
            'q' => $q, 'jenis' => $jenis, 'lingkup' => $lingkup, 'kategori' => $kategori,
            // Rekap per kategori + saldo kas periode itu: dua hal yang membuat
            // buku kas bisa dipakai menyusun laporan tanpa menghitung manual.
            'kategoriRekap' => self::rekapKategori($agregat ? null : $kop->id, $periode, $acuan,
                $agregat ? $idsLingkup : null),
            'saldoAwal' => self::saldoSebelum($agregat ? null : $kop->id, $rekap['awal'],
                $agregat ? $idsLingkup : null),
            'saldoAkhir' => self::saldoSebelum($agregat ? null : $kop->id, $rekap['awal'],
                $agregat ? $idsLingkup : null)
                + ($rekap['pemasukan'] - $rekap['pengeluaran']),
            // Pemilih koperasi: yang TERPILIH (satu objek) + jumlah yang
            // terjangkau, bukan seluruh daftarnya. Dulu di sini SELURUH koperasi
            // dikirim ke blade sebagai <option>; dengan 30 ribu koperasi itu 30
            // ribu baris di setiap pemuatan halaman — lambat dikirim dan tetap
            // tak bisa dicari. Daftarnya kini diambil sepotong saat pengguna
            // mengetik (lihat `cariKoperasi()`), baik sebagai hasil-halaman
            // (tanpa JavaScript) maupun kotak mengambang (dengan JavaScript).
            'pilihanKoperasi' => $kop, 'idsLingkup' => $idsLingkup,
            'totalKoperasi' => $u->manajerKoperasi() ? 1 : $u->koperasiQuery()->count(),
            'cariKop' => $cariKop, 'hasilKoperasi' => $hasilKoperasi,
        ]);
    }

    /**
     * Simpan catatan harian. `koperasi_id`+`tanggal` unik, jadi hari yang sudah
     * ada diperbarui (bukan ditolak) — manajer memperbaiki angka lebih mudah
     * daripada harus menghapus dulu.
     *
     * Dua hal yang dijaga di sini:
     *
     * 1. `dibuat` hanya diisi saat baris LAHIR. `updateOrCreate()` dulu mengirim
     *    ulang `dibuat` tiap simpan, jadi tanggal pembuatan pertama hilang
     *    tertimpa setiap kali angkanya dikoreksi.
     * 2. Baris yang memuat mutasi otomatis (`ref` terisi) tidak kehilangan
     *    rinciannya hanya karena manajer menyimpan angkanya lewat form: kolom
     *    rincian yang dikirim KOSONG berarti "jangan diubah", bukan "hapus".
     *    Tanpa ini, sekali angka hari itu dikoreksi, jejak invoice yang membentuk
     *    angkanya ikut terhapus dan kas tak lagi bisa ditelusuri.
     */
    public function simpan(Request $request)
    {
        // Baris tertentu boleh menyebut koperasinya sendiri (`koperasi_id`): di
        // mode agregat, buku kas menampilkan catatan SEMUA koperasi dan admin
        // pusat harus bisa memperbaiki milik koperasi mana pun — tanpa ini
        // tombol Ubah di mode agregat tidak punya tujuan. ID-nya diperiksa
        // terhadap lingkup akun, jadi PIC tetap tak bisa menyentuh luar wilayah.
        $idKirim = (int) $request->input('koperasi_id', 0);
        $kop = $idKirim > 0
            ? $request->user()->koperasiQuery()->whereKey($idKirim)->first()
            : $this->koperasi($request);
        if (! $kop) {
            return back()->with('galat', $idKirim > 0
                ? 'Koperasi itu di luar wewenang akun Anda.'
                : 'Akun ini belum terhubung ke koperasi mana pun.');
        }

        $d = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'pemasukan' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'pengeluaran' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'rincian_pemasukan' => ['nullable', 'string', 'max:200'],
            'rincian_pengeluaran' => ['nullable', 'string', 'max:200'],
            'catatan' => ['nullable', 'string', 'max:300'],
            // Kategori WAJIB salah satu kunci yang sah — nilai asing ditolak di
            // sini, bukan disimpan lalu tampil sebagai teks aneh di rekap.
            'kategori_pemasukan' => ['nullable', Rule::in(array_keys(Domain::KATEGORI_PEMASUKAN))],
            'kategori_pengeluaran' => ['nullable', Rule::in(array_keys(Domain::KATEGORI_PENGELUARAN))],
        ], [], ['tanggal' => 'tanggal', 'pemasukan' => 'pemasukan', 'pengeluaran' => 'pengeluaran']);

        $baris = Keuangan::query()->where('koperasi_id', $kop->id)
            ->where('tanggal', $d['tanggal'])->first();

        if ($baris && (string) $baris->ref !== '') {
            // Dua penjagaan untuk baris yang lahir dari pesanan toko:
            // 1. angka masuk/keluar tidak boleh diubah — kalau diubah, buku kas
            //    berselisih dengan pesanan tanpa jejak. DITOLAK dengan alasan,
            //    bukan diam-diam diabaikan (dulu angka kiriman menimpa nilai
            //    pesanan, padahal niatnya melindungi).
            if ((int) $d['pemasukan'] !== (int) $baris->pemasukan
                || (int) $d['pengeluaran'] !== (int) $baris->pengeluaran) {
                return back()->with('galat', 'Baris ini otomatis dari pesanan toko '
                    .$baris->ref.' — angka masuk/keluar tidak bisa diubah di sini. '
                    .'Batalkan pesanannya bila memang keliru.');
            }
            // 2. rincian yang dikirim KOSONG berarti "jangan diubah", bukan "hapus".
            foreach (['rincian_pemasukan', 'rincian_pengeluaran', 'catatan',
                      'kategori_pemasukan', 'kategori_pengeluaran'] as $k) {
                if ((string) ($d[$k] ?? '') === '') {
                    unset($d[$k]);
                }
            }
        }

        // Kolom teks di tabel `keuangan` NOT NULL dengan default '' — tetapi
        // Laravel mengubah isian kosong dari formulir menjadi null, dan menulis
        // null ke kolom itu ditolak database. Karena itu kolom opsional
        // dinormalkan ke '' persis seperti controller lain (lihat
        // DistribusiController::barangBaru). Tanpa ini, menyimpan catatan harian
        // dengan rincian/catatan kosong selalu gagal 500 — jalur yang paling
        // mungkin dipakai manajer.
        foreach (['rincian_pemasukan', 'rincian_pengeluaran', 'catatan',
                  'kategori_pemasukan', 'kategori_pengeluaran'] as $k) {
            if (array_key_exists($k, $d) && $d[$k] === null) {
                $d[$k] = '';
            }
        }

        $baris ? $baris->forceFill([...$d, 'dicatat_oleh' => $request->user()->id,
                                    'diperbarui' => kini()])->save()
               : Keuangan::create([...$d, 'koperasi_id' => $kop->id,
                                   'dicatat_oleh' => $request->user()->id,
                                   'dibuat' => kini(), 'diperbarui' => kini()]);

        jurnal($request->user()->id, 'KEUANGAN_SIMPAN', $kop->kode.' '.$d['tanggal'],
            'masuk='.$d['pemasukan'].' keluar='.$d['pengeluaran']
                .($baris ? ' (diperbarui)' : '') .($baris ? ' ref='.$baris->ref : ''),
            $request->ip() ?? '');

        return back()->with('pesan', 'Catatan '.$d['tanggal'].' '.($baris ? 'diperbarui' : 'disimpan').'.');
    }

    public function hapus(Request $request, int $id)
    {
        // `findOrFail` (bukan baris hasil join dari daftar) supaya seluruh kolom
        // ada: baris buku kas mode agregat di-select bersama kolom koperasi.
        $k = Keuangan::query()->whereKey($id)->firstOrFail();
        if (! $request->user()->bolehKoperasi((int) $k->koperasi_id)) {
            abort(403);
        }
        // Baris bermutasi otomatis (dari toko) bukan milik halaman ini; menghapusnya
        // di sini membuat buku kas koperasi berselisih dengan data pesanan tanpa
        // jejak. Ditolak, bukan diam-diam dihapus.
        if ((string) $k->ref !== '') {
            return back()->with('galat', 'Baris ini dibuat otomatis dari pesanan toko '
                .$k->ref.' — batalkan pesanannya, bukan catatannya.');
        }

        jurnal($request->user()->id, 'KEUANGAN_HAPUS', $k->tanggal, '', $request->ip() ?? '');
        $k->delete();

        return back()->with('pesan', 'Catatan '.$k->tanggal.' dihapus.');
    }

    /**
     * Koperasi yang cocok dengan ketikan pencarian, dibatasi 20 baris.
     *
     * Satu `like` untuk kode, nama, wilayah, dan provinsi sekaligus supaya
     * ketikan bebas tetap menemukan barisnya tanpa pengguna perlu tahu kolom
     * mana yang cocok. Lingkupnya mengikuti `koperasiQuery()` — admin pusat
     * seluruh negeri, PIC hanya wilayahnya — jadi kotak pencarian tak bisa
     * dipakai mengintip koperasi di luar wewenang.
     *
     * ponytail: `like` = pemindaian tabel, bukan indeks; pada 30 ribu baris
     * masih di bawah 100ms. Naikkan ke FTS5 (atau kolom `nama` ber-indeks)
     * kalau suatu hari barisnya menembus jutaan.
     */
    private function cariKoperasi(Request $request, string $q): \Illuminate\Support\Collection
    {
        if (mb_strlen($q) < 2) {
            return collect();
        }

        // `%` dan `_` milik pola LIKE, bukan milik ketikan pengguna — dilolosi
        // supaya mencari "KOP_01" tidak berubah menjadi pola buatan.
        $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

        return $request->user()->koperasiQuery()
            ->where(fn ($w) => $w->where('kode', 'like', $pola)->orWhere('nama', 'like', $pola)
                ->orWhere('wilayah', 'like', $pola)->orWhere('provinsi', 'like', $pola))
            ->orderBy('nama')->limit(20)->get(['id', 'kode', 'nama', 'wilayah']);
    }

    /**
     * Potongan daftar hasil pencarian koperasi (bukan halaman penuh). Hanya
     * markup yang sama dengan yang dirender `index()`, jadi tidak ada bentuk
     * kedua yang bisa menyimpang.
     */
    public function pilih(Request $request)
    {
        $q = trim((string) $request->query('cari_kop', ''));

        return response()->view('keuangan._koperasi-kotak', [
            'q' => $q,
            'hasil' => $this->cariKoperasi($request, $q),
            'pilihanId' => (int) $request->query('koperasi', 0),
            'periode' => in_array($request->query('periode'), array_keys(self::PERIODE), true)
                ? $request->query('periode') : 'bulanan',
            'acuan' => (string) ($request->query('acuan') ?: now()->toDateString()),
        ]);
    }

    /**
     * Unduh Buku Kas sebagai CSV — SATU jalur untuk mode satu koperasi DAN
     * agregat (seluruh koperasi dalam lingkup akun).
     *
     * Dulu hanya mode satu koperasi: `$this->koperasi()` mengembalikan null saat
     * agregat, lalu `abort(404)`. Akibatnya tombol "Unduh CSV" tidak pernah
     * dirender untuk pengelola dan URL-nya 404 — padahal justru pengelola yang
     * menyusun laporan gabungan. Sekarang agregat diekspor sebagai satu berkas
     * dengan kolom Koperasi, dan lingkupnya sama dengan yang terlihat di layar
     * (`koperasiQuery()`), jadi tak ada baris di luar wewenang yang ikut.
     */
    public function ekspor(Request $request)
    {
        $u = $request->user();
        $kop = $this->koperasi($request);

        $periode = in_array($request->query('periode'), array_keys(self::PERIODE), true)
            ? $request->query('periode') : 'bulanan';
        $acuan = (string) ($request->query('acuan') ?: now()->toDateString());
        [$awal, $akhir] = self::rentang($periode, $acuan);

        $agregat = $kop === null;
        if ($agregat && $u->manajerKoperasi()) {
            // Manajer tanpa koperasi memang tak punya apa pun untuk diekspor.
            abort(404);
        }

        $idsLingkup = $agregat ? $u->koperasiQuery()->pluck('koperasi.id')->all() : [];

        $q = Keuangan::query()
            ->when($kop !== null, fn ($w) => $w->where('koperasi_id', $kop->id))
            ->when($agregat, fn ($w) => $w->whereIn('keuangan.koperasi_id', $idsLingkup))
            ->whereBetween('tanggal', [$awal, $akhir]);

        if ($agregat) {
            // Nama koperasi ikut supaya berkas gabungan tetap bisa ditelusuri
            // per baris (layar agregat pun begitu).
            $q->join('koperasi as k', 'k.id', '=', 'keuangan.koperasi_id')
                ->select('keuangan.*', 'k.kode as kode_koperasi', 'k.nama as nama_koperasi');
        }

        $hari = $q->orderBy('tanggal')->orderBy('keuangan.koperasi_id')->get();
        $rekap = self::rekap($kop?->id, $periode, $acuan, $agregat ? $idsLingkup : null);

        $nama = 'keuangan-'.($kop?->kode ?? 'semua-koperasi').'-'.$periode.'-'.$acuan.'.csv';

        // Pemisah ';' + BOM UTF-8: Excel locale Indonesia membuka ';' sebagai
        // kolom dan tanpa BOM huruf beraksen rusak.
        $out = "\xEF\xBB\xBF";
        $out .= $kop
            ? "Koperasi;{$kop->kode} - {$kop->nama}\n"
            : 'Koperasi;SELURUHNYA ('.count($idsLingkup)." koperasi dalam lingkup)\n";
        $out .= "Periode;{$awal} s/d {$akhir}\n\n";
        $out .= "Tanggal;Koperasi;Kode;Pemasukan;Pengeluaran;Selisih;"
            ."Kategori Masuk;Rincian Masuk;Kategori Keluar;Rincian Keluar;Catatan;Rujukan\n";

        $bersih = fn (?string $s) => str_replace([';', "\r", "\n"], [',', ' ', ' '], (string) $s);

        foreach ($hari as $h) {
            $out .= implode(';', [
                $h->tanggal,
                $bersih($h->nama_koperasi ?? ($kop->nama ?? '')),
                $bersih($h->kode_koperasi ?? ($kop->kode ?? '')),
                $h->pemasukan, $h->pengeluaran, $h->selisih(),
                $bersih(Domain::labelKategori('masuk', $h->kategori_pemasukan)),
                $bersih($h->rincian_pemasukan),
                $bersih(Domain::labelKategori('keluar', $h->kategori_pengeluaran)),
                $bersih($h->rincian_pengeluaran),
                $bersih($h->catatan),
                $bersih($h->ref),
            ])."\n";
        }

        $out .= "\nTOTAL;{$rekap['pemasukan']};{$rekap['pengeluaran']};"
            .($rekap['pemasukan'] - $rekap['pengeluaran'])."\n";

        // Rekap kategori ikut di berkas: laporan bulanan butuh "uangnya masuk dari
        // mana", bukan hanya totalnya — dan angka ini dihitung dari baris yang
        // sama, jadi mustahil berselisih dengan isi CSV di atasnya.
        $kat = self::rekapKategori($kop?->id, $periode, $acuan, $agregat ? $idsLingkup : null);
        foreach (['masuk' => 'PEMASUKAN', 'keluar' => 'PENGELUARAN'] as $sisi => $judul) {
            $out .= "\nKATEGORI {$judul}\n";
            $jumlah = 0;
            foreach ($kat[$sisi] as $r) {
                $out .= "{$bersih($r['label'])};{$r['nilai']}\n";
                $jumlah += $r['nilai'];
            }
            $out .= "Jumlah;{$jumlah}\n";
        }

        jurnal($u->id, 'KEUANGAN_EKSPOR', $kop?->kode ?? 'semua', $periode.' '.$acuan,
            $request->ip() ?? '');

        return response($out, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }
}

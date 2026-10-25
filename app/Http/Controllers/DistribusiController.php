<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Distribusi;
use App\Models\DistribusiItem;
use App\Models\Koperasi;
use App\Models\KoperasiBarang;
use App\Models\KoperasiBarangStok;
use App\Models\Vendor;
use App\Support\Halaman;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Distribusi barang: Agrinas (gudang pusat) -> koperasi.
 *
 * Siklus: manajer mengajukan permintaan -> Agrinas memproses, boleh tolak
 * atau kirim (stok gudang dipotong di sini) -> manajer menandai barang tiba
 * beserta jumlah yang benar-benar diterima.
 *
 * Stok Agrinas ada di `barang.stok`; stok koperasi tidak disimpan terpisah,
 * dihitung dari SUM(jumlah_diterima) supaya tidak ada dua angka yang bisa
 * saling menyimpang.
 */
class DistribusiController extends Controller
{
    /**
     * Draf pesanan barang hidup di SESI, bukan di tabel `distribusi`: menyusun
     * pesanan bukan permintaan. Baris baru dibuat di `distribusi` hanya saat
     * manajer menekan "Kirim Pesanan" (`ajukan`). Dikunci ke satu koperasi.
     */
    private const DRAF = 'distribusi_draf';

    /**
     * Koperasi yang dikelola seorang manajer. Pautan kanonik di sistem ini
     * adalah koperasi.manajer_id (kolom users.koperasi_id tidak pernah diisi
     * oleh seeder maupun Flask), jadi jangan dibalik.
     */
    private function koperasi(\App\Models\User $u): ?Koperasi
    {
        return Koperasi::where('manajer_id', $u->id)->first();
    }

    /** Daftar pengajuan + ringkasan; Agrinas melihat semua, manajer hanya miliknya. */
    public function daftar(Request $request)
    {
        $u = $request->user();
        // PIC wilayah memakai tampilan "pengelola" (statistik + saringan status),
        // tetapi daftarnya hanya koperasi di wilayahnya.
        $agrinas = ! $u->manajerKoperasi();
        $lingkup = $u->koperasiQuery()->select('koperasi.id');
        $kop = $u->manajerKoperasi() ? $this->koperasi($u) : null;

        $semua = $this->saringDistribusi($request, $kop, $lingkup);
        $jumlah = (clone $semua)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        $stat = $agrinas ? Distribusi::query()->whereIn('koperasi_id', $lingkup)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all() : [];

        return view('distribusi.daftar', [
            'aktif' => 'distribusi', 'agrinas' => $agrinas,
            'daftar' => $halaman->batasi($semua)->get(),
            'halaman' => $halaman, 'stat' => $stat, 'jumlah' => $jumlah,
            'status' => (string) $request->query('status', ''),
            'cari' => trim((string) $request->query('q', '')),
            'saringStatus' => $this->statusBarisDiminta($request),
        ]);
    }

    /**
     * Saringan daftar distribusi (cari + status baris), SATU tempat untuk kedua
     * jalur: halaman penuh dan potongan AJAX. Kalau disusun terpisah, cepat atau
     * lambat salah satunya tertinggal saat saringan ditambah.
     */
    private function saringDistribusi(Request $request, ?Koperasi $kop, $lingkup)
    {
        $cari = trim((string) $request->query('q', ''));
        $st = $this->statusBarisDiminta($request);

        return Distribusi::query()
            ->leftJoin('koperasi as k', 'k.id', '=', 'distribusi.koperasi_id')
            ->select('distribusi.*', 'k.nama as koperasi', 'k.kode as kode_koperasi')
            ->selectRaw('(SELECT COUNT(*) FROM distribusi_item i WHERE i.distribusi_id = distribusi.id) as jumlah_item')
            ->when($kop, fn ($w) => $w->where('distribusi.koperasi_id', $kop->id))
            ->when(! $kop, fn ($w) => $w->whereIn('distribusi.koperasi_id', $lingkup))
            ->when($st !== '', fn ($w) => $w->where('distribusi.status', $st))
            ->when($cari !== '', function ($w) use ($cari) {
                // `%` dan `_` dilolosi: keduanya pola LIKE, dan tanpa ini mencari
                // `_` mencocokkan hampir semua baris.
                $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cari).'%';
                $w->where(fn ($x) => $x->where('distribusi.kode', 'like', $pola)
                    ->orWhere('k.nama', 'like', $pola)->orWhere('k.kode', 'like', $pola));
            })
            ->orderByRaw("CASE distribusi.status WHEN 'DIAJUKAN' THEN 0 WHEN 'DIKIRIM' THEN 1 ELSE 2 END")
            ->orderByDesc('distribusi.id');
    }

    /** Status baris dari URL; nilai asing berarti tanpa saringan. */
    private function statusBarisDiminta(Request $request): string
    {
        $st = mb_strtoupper((string) $request->query('status_baris', ''));

        return in_array($st, Distribusi::STATUS, true) ? $st : '';
    }

    /** Potongan baris tabel distribusi untuk AJAX (tanpa layout halaman). */
    public function baris(Request $request)
    {
        $u = $request->user();
        $agrinas = ! $u->manajerKoperasi();
        $lingkup = $u->koperasiQuery()->select('koperasi.id');
        $kop = $u->manajerKoperasi() ? $this->koperasi($u) : null;

        $q = $this->saringDistribusi($request, $kop, $lingkup);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        // Dibuka langsung: alihkan ke halaman dengan saringan yang sama, supaya
        // pengunjung tidak menerima potongan baris telanjang.
        if (! $this->inginPanel($request)) {
            return redirect()->route('distribusi.daftar', $request->query());
        }

        return response()->view('distribusi._baris', [
            'daftar' => $halaman->batasi($q)->get(),
            'agrinas' => $agrinas,
            'kosong' => ($this->statusBarisDiminta($request) !== '' || trim((string) $request->query('q', '')) !== ''),
        ])->header('X-Jumlah', (string) $jumlah);
    }

    /**
     * Formulir pengajuan barang untuk manajer koperasi.
     *
     * `?barang=ID` (boleh diulang) membatasi daftar ke barang yang benar-benar
     * diklik dari katalog Stok Koperasi. Tanpa parameter itu daftar tetap penuh
     * supaya tautan lama dan "ajukan beberapa barang sekaligus" tetap jalan.
     */
    public function pengajuan(Request $request)
    {
        // Mengajukan barang adalah pekerjaan koperasi; PIC ikut melihat stok
        // gudang (bukan mengajukan) supaya tahu apa yang bisa disalurkan.
        if (! $request->user()->manajerKoperasi()) {
            return redirect()->route($request->user()->pic() ? 'distribusi.daftar' : 'stok');
        }

        $pilih = collect((array) $request->query('barang'))
            ->map(fn ($v) => (int) $v)->filter()->unique()->take(20);

        $semua = Barang::query()
            ->when($pilih->isNotEmpty(), fn ($w) => $w->whereIn('id', $pilih))
            ->orderBy('nama');
        $halaman = Halaman::buat((clone $semua)->count(), $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return view('distribusi.pengajuan', [
            'aktif' => 'distribusi',
            'barang' => $halaman->batasi($semua)->get(),
            'halaman' => $halaman,
            'pilih' => $pilih,
            'draf' => $this->rincianDraf($request),
        ]);
    }

    private function isiDraf(Request $request): array
    {
        $k = (array) $request->session()->get(self::DRAF, []);

        return ['koperasi_id' => $k['koperasi_id'] ?? null, 'item' => (array) ($k['item'] ?? [])];
    }

    private function simpanDraf(Request $request, array $k): void
    {
        $request->session()->put(self::DRAF, $k);
    }

    /** Draf pesanan dari sesi; barisnya dilengkapi data barang untuk tampilan. */
    private function rincianDraf(Request $request): array
    {
        $k = (array) $request->session()->get(self::DRAF, []);
        $item = (array) ($k['item'] ?? []);
        $barang = $item ? Barang::whereIn('id', array_keys($item))->get()->keyBy('id') : collect();

        $baris = [];
        foreach ($item as $id => $jumlah) {
            $b = $barang[(int) $id] ?? null;
            if (! $b) {
                continue;
            }
            $baris[] = ['id' => (int) $b->id, 'kode' => $b->kode, 'nama' => $b->nama,
                'satuan' => $b->satuan, 'stok' => (int) $b->stok,
                'kategori' => Barang::KATEGORI[$b->kategori] ?? $b->kategori,
                'jumlah' => (int) $jumlah];
        }

        return ['baris' => $baris, 'jumlah' => count($baris)];
    }

    /**
     * Simpan pilihan ke DRAF pesanan (bukan permintaan). Menambah barang yang
     * sama menimpa jumlahnya, tidak menumpuk — draf adalah usulan, bukan riwayat.
     */
    public function draf(Request $request)
    {
        $u = $request->user();
        $kop = $this->koperasi($u);
        if (! $kop) {
            return back()->with('galat', 'Akun ini belum terhubung ke koperasi mana pun.');
        }

        $d = $request->validate([
            'item' => ['nullable', 'array', 'max:20'],
            'item.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ], [], ['item' => 'jumlah barang']);

        $diminta = collect($d['item'] ?? [])
            ->filter(fn ($n, $id) => (int) $n > 0 && Barang::query()->whereKey($id)->exists())
            ->map(fn ($n) => (int) $n);

        // Formulir draf mengirim "ganti=1": isinya menggantikan draf seutuhnya
        // (baris yang dijadikan 0 ikut hilang). Formulir katalog menambahkan.
        if ($request->input('ganti')) {
            $this->simpanDraf($request, ['koperasi_id' => $kop->id, 'item' => $diminta->all()]);

            return back()->with('pesan', $diminta->isEmpty()
                ? 'Draf pesanan dikosongkan.'
                : 'Draf pesanan diperbarui — belum dikirim ke Agrinas.');
        }

        if ($diminta->isEmpty()) {
            return back()->with('galat', 'Isi jumlah minimal satu barang dulu.');
        }

        $k = $this->isiDraf($request);
        if (($k['koperasi_id'] ?? null) !== $kop->id) {
            $k = ['koperasi_id' => $kop->id, 'item' => []];
        }
        foreach ($diminta as $id => $n) {
            $k['item'][(int) $id] = $n;
        }
        $this->simpanDraf($request, $k);

        return redirect()->route('distribusi.pengajuan')
            ->with('pesan', 'Disimpan ke draf pesanan — belum dikirim ke Agrinas.');
    }

    /** Buang satu barang dari draf (id 0 = kosongkan semua). */
    public function draf_hapus(Request $request)
    {
        $k = $this->isiDraf($request);
        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            unset($k['item'][$id]);
        } else {
            $k = ['koperasi_id' => null, 'item' => []];
        }
        $this->simpanDraf($request, $k);

        return back()->with('pesan', $id > 0 ? 'Barang dihapus dari draf.' : 'Draf pesanan dikosongkan.');
    }

    /** Formulir pengajuan (manajer) / stok gudang (Agrinas), tergantung peran. */
    public function stok(Request $request)
    {
        $u = $request->user();
        // Stok gudang: pusat & PIC boleh melihat (PIC perlu tahu persediaan yang
        // bisa disalurkan ke wilayahnya); manajer diarahkan ke pengajuan.
        if ($u->manajerKoperasi()) {
            return redirect()->route('distribusi.daftar')
                ->with('galat', 'Daftar dan pengajuan barang ada di halaman Distribusi.');
        }

        $semua = $this->saringBarang($request);
        $jumlah = (clone $semua)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return view('distribusi.stok', [
            'aktif' => 'stok',
            'barang' => $halaman->batasi($semua)->get(),
            'halaman' => $halaman, 'jumlah' => $jumlah,
            'cari' => trim((string) $request->query('q', '')),
            'saringKategori' => $this->kategoriDiminta($request),
            'satuan' => Barang::SATUAN, 'kategori' => Barang::KATEGORI,
            'vendor' => Vendor::query()->orderBy('nama')->get(),
            'menipis' => Barang::query()->where('stok_min', '>', 0)
                ->whereColumn('stok', '<=', 'stok_min')->count(),
        ]);
    }

    /**
     * Saringan stok gudang, SATU tempat untuk kedua jalur.
     *
     * Jalur AJAX (potongan baris) dan jalur muat-penuh memanggil fungsi yang sama,
     * jadi hasil keduanya mustahil berbeda — kalau disusun terpisah, cepat atau
     * lambat salah satunya tertinggal saat saringan ditambah.
     *
     * `%` dan `_` dilolosi: keduanya pola LIKE, dan tanpa ini mencari `_`
     * mencocokkan hampir semua baris.
     */
    private function saringBarang(Request $request)
    {
        $cari = trim((string) $request->query('q', ''));
        $kategori = $this->kategoriDiminta($request);

        return Barang::query()
            ->when($cari !== '', function ($q) use ($cari) {
                $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cari).'%';
                $q->where(fn ($w) => $w->where('nama', 'like', $pola)->orWhere('kode', 'like', $pola));
            })
            ->when($kategori !== '', fn ($q) => $q->where('kategori', $kategori))
            ->orderBy('nama');
    }

    /** Kategori dari URL; nilai asing diperlakukan sebagai "semua". */
    private function kategoriDiminta(Request $request): string
    {
        $k = (string) $request->query('kategori', '');

        return array_key_exists($k, Barang::KATEGORI) ? $k : '';
    }

    /** Potongan baris tabel stok untuk AJAX (tanpa layout halaman). */
    public function barang_daftar(Request $request)
    {
        $u = $request->user();
        if ($u->manajerKoperasi()) {
            abort(403);
        }

        $q = $this->saringBarang($request);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        // Dibuka langsung (bukan lewat fetch): alihkan ke halaman stok dengan
        // saringan yang sama. Tanpa ini pengunjung menerima potongan baris
        // telanjang — tabel tanpa judul, menu, atau jalan kembali.
        if (! $this->inginPanel($request)) {
            return redirect()->route('stok', $request->query());
        }

        return response()->view('distribusi._stok-baris', [
            'barang' => $halaman->batasi($q)->get(),
            'cari' => trim((string) $request->query('q', '')),
            'saringKategori' => $this->kategoriDiminta($request),
        ])->header('X-Jumlah', (string) $jumlah);
    }

    /** Aturan kolom barang; kode hanya saat tambah, vendor_id boleh kosong. */
    private function aturan_barang(bool $denganKode): array
    {
        return array_filter([
            'kode' => $denganKode ? ['nullable', 'string', 'max:24', 'alpha_dash'] : null,
            'nama' => ['required', 'string', 'max:120'],
            'satuan' => ['required', 'in:'.implode(',', Barang::SATUAN)],
            'kategori' => ['required', 'in:'.implode(',', array_keys(Barang::KATEGORI))],
            'stok' => ['required', 'integer', 'min:0', 'max:100000000'],
            'stok_min' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            // Harga jual (Rp). Wajib: barang tanpa harga tidak bisa dibeli dan
            // membuat etalase menampilkan "Rp 0" — dulu kolom ini hanya bisa
            // diisi dari /admin/toko sehingga gudang punya barang tanpa harga.
            'harga' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendor,id'],
            'catatan' => ['nullable', 'string', 'max:200'],
        ]);
    }

    public function barang_simpan(Request $request)
    {
        // `gagalValidasi` mengalihkan galat validasi ke pesan JSON khusus saat
        // pemanggilnya modal: tanpa itu jawabannya 302 dan modal menganggapnya
        // berhasil (lihat DistribusiController::gagalValidasi).
        $this->gagalValidasi($request);
        $d = $request->validate($this->aturan_barang(true));

        $kode = mb_strtoupper($d['kode'] ?? 'BRG-'.str_pad((string) (Barang::query()->count() + 1), 4, '0', STR_PAD_LEFT));
        if (Barang::query()->where('kode', $kode)->exists()) {
            return $this->balasBarang($request, 'galat', 'Kode barang '.$kode.' sudah dipakai.');
        }

        $b = Barang::create([
            'kode' => $kode, 'nama' => $d['nama'], 'satuan' => $d['satuan'],
            'kategori' => $d['kategori'], 'stok' => $d['stok'], 'stok_min' => $d['stok_min'] ?? 0,
            'harga' => (int) $d['harga'], 'vendor_id' => $d['vendor_id'] ?? null,
            'catatan' => $d['catatan'] ?? '', 'dibuat' => kini(), 'diperbarui' => kini(),
        ]);

        jurnal($request->user()->id, 'BARANG_BARU', $b->kode, 'stok awal='.$b->stok, $request->ip() ?? '');

        return $this->balasBarang($request, 'pesan', 'Barang '.$b->nama.' ditambahkan.');
    }

    public function barang_ubah(Request $request, int $id)
    {
        $b = Barang::findOrFail($id);
        $this->gagalValidasi($request);
        $d = $request->validate($this->aturan_barang(false));

        $lama = $b->stok;
        // `catatan` sengaja tidak ditulis: formulir Ubah tak memuatnya, jadi
        // menyentuhnya hanya akan menghapus catatan yang sudah ada.
        $b->update([...$d, 'stok_min' => $d['stok_min'] ?? 0,
            'vendor_id' => $d['vendor_id'] ?? null, 'diperbarui' => kini()]);

        jurnal($request->user()->id, 'BARANG_UBAH', $b->kode, "stok $lama -> {$b->stok} (penyesuaian manual)",
            $request->ip() ?? '');

        return $this->balasBarang($request, 'pesan', 'Barang '.$b->nama.' diperbarui.');
    }

    /** Manajer mengajukan permintaan barang (satu atau banyak item sekaligus). */
    public function ajukan(Request $request)
    {
        $u = $request->user();
        $kop = $this->koperasi($u);
        if (! $kop) {
            return back()->with('galat', 'Akun ini belum terhubung ke koperasi mana pun.');
        }

        // Halaman Tinjau mengirim tanpa `item` sama sekali dan mengandalkan DRAF
        // di sesi; pengiriman eksplisit (katalog) tidak pernah ditimpa draf.
        // WAJIB sebelum validate(): kalau sesudah, validasi `item` sudah gagal
        // lebih dulu dan permintaan dari draf tak pernah sampai.
        if (! $request->has('item')) {
            $request->merge(['item' => $this->isiDraf($request)['item']]);
        }

        $d = $request->validate([
            'catatan' => ['nullable', 'string', 'max:500'],
            'item' => ['required', 'array', 'min:1', 'max:20'],
            'item.*' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [], ['item' => 'jumlah barang']);

        // Buang baris yang barangnya tidak dipilih (0/kosong dari formulir).
        $diminta = collect($request->input('item', []))
            ->filter(fn ($n, $barangId) => (int) $n > 0 && Barang::query()->whereKey($barangId)->exists());
        if ($diminta->isEmpty()) {
            return back()->with('galat', 'Pilih minimal satu barang beserta jumlahnya.');
        }

        $dibuat = DB::connection('hub')->transaction(function () use ($diminta, $d, $u, $kop) {
            $dis = Distribusi::create([
                'kode' => Distribusi::kodeBaru(), 'koperasi_id' => $kop->id,
                'pemohon' => $u->id, 'status' => 'DIAJUKAN',
                'catatan' => $d['catatan'] ?? '', 'catatan_agrinas' => '',
                'dibuat' => kini(), 'diperbarui' => kini(),
            ]);
            foreach ($diminta as $barangId => $jumlah) {
                DistribusiItem::create(['distribusi_id' => $dis->id, 'barang_id' => (int) $barangId,
                    'jumlah' => (int) $jumlah, 'jumlah_diterima' => 0]);
            }

            return $dis;
        });

        $request->session()->forget(self::DRAF);
        jurnal($u->id, 'DISTRIBUSI_AJU', $dibuat->kode,
            'koperasi='.$u->koperasi_id.' item='.$diminta->count(), $request->ip() ?? '');

        return redirect()->route('distribusi.daftar')
            ->with('pesan', 'Pesanan '.$dibuat->kode.' terkirim ke Agrinas.');
    }

    /**
     * Agrinas/PIC menandai pengajuan sedang dikerjakan. Stok BELUM dipotong —
     * ini hanya penanda supaya manajer tahu pengajuannya tidak menggantung.
     */
    public function proses(Request $request, int $id)
    {
        // Rute lama tetap hidup (tautan/formulir yang masih beredar), tapi
        // perpindahannya dikerjakan SATU fungsi supaya tidak ada dua tempat yang
        // bisa berbeda soal stok atau riwayat.
        return $this->pindahKe($request, $id, 'DIPROSES');
    }

    /** Rute khusus: pindahkan status ke tujuan tetap, lalu serahkan ke simpanStatus. */
    private function pindahKe(Request $request, int $id, string $ke)
    {
        $dis = Distribusi::findOrFail($id);
        $ket = '';
        if ($ke === 'DIKIRIM') {
            $ket = (string) ($request->input('catatan_agrinas') ?? '');
        } elseif ($ke === 'DITOLAK') {
            $ket = (string) ($request->input('catatan_agrinas') ?? '');
        }
        $request->merge(['status' => $ke, 'keterangan' => $ket]);

        return $this->simpanStatus($request, $id);
    }

    /** Agrinas: kirim barang. Stok gudang dipotong di `simpanStatus`. */
    public function kirim(Request $request, int $id)
    {
        return $this->pindahKe($request, $id, 'DIKIRIM');
    }

    public function tolak(Request $request, int $id)
    {
        return $this->pindahKe($request, $id, 'DITOLAK');
    }

    /** Manajer: barang tiba. Isi jumlah yang benar-benar diterima per item. */
    public function terima(Request $request, int $id)
    {
        $u = $request->user();
        $dis = Distribusi::with('item')->findOrFail($id);

        // Hanya koperasi pemohon, PIC wilayah pemohon, atau pusat yang boleh bertindak.
        if (! $u->bolehKoperasi((int) $dis->koperasi_id)) {
            abort(403);
        }
        if (! $dis->boleh('DITERIMA')) {
            return $this->balasRincian($request, $dis, 'galat', 'Pengajuan '.$dis->kode.' berstatus '.Distribusi::label($dis->status)
                .' — belum bisa diterima.');
        }

        $this->gagalValidasi($request);
        $d = $request->validate([
            'terima' => ['required', 'array'],
            'terima.*' => ['required', 'integer', 'min:0', 'max:1000000'],
            'catatan' => ['nullable', 'string', 'max:300'],
        ], [], ['terima' => 'jumlah diterima']);

        // Jumlah diterima tidak boleh melebihi yang dikirim.
        foreach ($dis->item as $it) {
            $n = (int) ($d['terima'][$it->id] ?? 0);
            if ($n > $it->jumlah) {
                return $this->balasRincian($request, $dis, 'galat',
                    "Jumlah diterima untuk item #{$it->id} melebihi jumlah dikirim ({$it->jumlah}).");
            }
        }

        DB::connection('hub')->transaction(function () use ($dis, $d, $u, $request) {
            $total = 0;
            foreach ($dis->item as $it) {
                $n = (int) ($d['terima'][$it->id] ?? 0);
                $it->update(['jumlah_diterima' => $n]);
                $total += $n;
                if ($n > 0) {
                    $this->tambahStokKoperasi((int) $dis->koperasi_id, (int) $u->id, $it, $n, $dis->kode);
                }
            }
            $kembali = $dis->item->sum('jumlah') - $total;
            $catatan = trim(($dis->catatan !== '' ? $dis->catatan."\n" : '').($d['catatan'] ?? ''));
            $dis->update(['status' => 'DITERIMA',
                'catatan_agrinas' => $catatan !== '' ? $catatan : $dis->catatan_agrinas,
                'diperbarui' => kini()]);

            jurnal($u->id, 'DISTRIBUSI_TERIMA', $dis->kode,
                "diterima=$total selisih=$kembali", $request->ip() ?? '');
        });

        return $this->balasRincian($request, $dis, 'pesan',
            'Penerimaan '.$dis->kode.' dicatat. Stok koperasi bertambah.');
    }

    /**
     * Barang kiriman Agrinas yang DITERIMA masuk ke inventaris koperasi.
     *
     * Sebelum ini penerimaan hanya menandai status: barangnya tak pernah muncul
     * di daftar barang koperasi, sehingga kasir POS tidak bisa menjualnya tanpa
     * mengetik ulang — rantai gudang → rak → kasir putus di tengah. Tautan
     * antar-baris memakai `barcode` = kode barang gudang (unik per koperasi di
     * skema), dengan `nama` sebagai cadangan untuk data lama. Penerimaan hanya
     * bisa terjadi sekali (transisi status ke DITERIMA), jadi stok tidak
     * terhitung dua kali.
     */
    private function tambahStokKoperasi(int $kopId, int $userId, DistribusiItem $it, int $jml, string $kodeDistribusi): void
    {
        $b = Barang::find($it->barang_id);
        if (! $b || $kopId <= 0) {
            return;
        }

        $baris = KoperasiBarang::where('koperasi_id', $kopId)
            ->where(fn ($w) => $w->where('barcode', (string) $b->kode)->orWhere('nama', $b->nama))
            ->first();

        if (! $baris) {
            $baris = KoperasiBarang::create([
                'koperasi_id' => $kopId, 'kode' => mb_substr((string) $b->kode, 0, 32),
                'barcode' => mb_substr((string) $b->kode, 0, 64),
                'nama' => $b->nama, 'satuan' => mb_strtolower((string) ($b->satuan ?: 'pcs')),
                'kategori' => 'LAINNYA', 'stok' => 0, 'stok_min' => 0,
                'harga' => (int) $b->harga, 'catatan' => 'Kiriman Agrinas',
                'oleh_id' => $userId, 'dibuat' => kini(), 'diperbarui' => kini(),
            ]);
        }

        $sebelum = (int) $baris->stok;
        $baris->update(['stok' => $sebelum + $jml, 'oleh_id' => $userId, 'diperbarui' => kini()]);

        KoperasiBarangStok::create([
            'koperasi_barang_id' => $baris->id, 'koperasi_id' => $kopId, 'user_id' => $userId,
            'sebelum' => $sebelum, 'sesudah' => $sebelum + $jml, 'selisih' => $jml,
            'sebab' => 'MASUK', 'catatan' => mb_substr('Penerimaan '.$kodeDistribusi, 0, 200),
            'tanggal' => now('Asia/Jakarta')->toDateString(), 'dibuat' => kini(),
        ]);
    }

    /** Stok di sisi koperasi (manajer) — dihitung dari barang yang sudah diterima. */
    public function stok_koperasi(Request $request)
    {
        $u = $request->user();
        if ($u->agrinas()) {
            return redirect()->route('stok');
        }

        $kop = $this->koperasi($u);

        $baris = DistribusiItem::query()
            ->join('distribusi as d', 'd.id', '=', 'distribusi_item.distribusi_id')
            ->join('barang as b', 'b.id', '=', 'distribusi_item.barang_id')
            ->where('d.koperasi_id', $kop?->id ?? 0)
            ->where('d.status', 'DITERIMA')
            ->selectRaw('b.kode, b.nama, b.satuan, SUM(distribusi_item.jumlah_diterima) as jumlah,
                         SUM(distribusi_item.jumlah) as diminta, MAX(d.dibuat) as terakhir')
            ->groupBy('b.kode', 'b.nama', 'b.satuan')
            ->orderBy('b.nama')->get();

        $semua = Barang::query()->orderBy('nama');
        $halaman = Halaman::buat((clone $semua)->count(), $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return view('distribusi.stok-koperasi', [
            'aktif' => 'distribusi.stok-koperasi', 'stok' => $baris,
            'barang' => $halaman->batasi($semua)->get(),
            'halaman' => $halaman,
            'koperasi' => $kop,
        ]);
    }

    /**
     * Jawaban seragam untuk Tambah/Ubah barang.
     *
     * Modal tidak bisa mengikuti `redirect()` — halaman tidak berpindah — jadi
     * ia meminta balasan JSON, sedangkan jalur lama (form biasa) tetap
     * dialihkan dengan pesan seperti sebelumnya. Dua bentuk, satu tempat.
     */
    private function balasBarang(Request $request, string $kunci, string $pesan, int $kode = 200)
    {
        if ($this->inginPanel($request)) {
            return response()->json(['ok' => $kunci === 'pesan', $kunci => $pesan], $kode);
        }

        return redirect()->route('stok')->with($kunci, $pesan);
    }

    /** Data Surat Jalan + kewenangan pengguna ini atasnya. */
    private function rincianData(Request $request, int $id): array
    {
        $u = $request->user();
        $dis = Distribusi::with(['item.barang', 'koperasi'])->findOrFail($id);

        if (! $u->bolehKoperasi((int) $dis->koperasi_id)) {
            abort(403);
        }

        return [$dis, [
            'dis' => $dis,
            // PIC melihat tampilan pengelola (tombol kirim/tolak) untuk wilayahnya.
            'agrinas' => ! $u->manajerKoperasi(),
            'pemohon' => $dis->pemohon ? \App\Models\User::find($dis->pemohon) : null,
        ]];
    }

    /** Apakah pemanggil minta POTONGAN (modal), bukan halaman penuh? */
    private function inginPanel(Request $request): bool
    {
        return $request->ajax() || $request->wantsJson();
    }

    /** Panel Surat Jalan untuk modal: potongan tanpa layout halaman. */
    public function panel(Request $request, int $id)
    {
        [$dis, $v] = $this->rincianData($request, $id);

        return response()->view('distribusi._rincian',
            $v + ['di_modal' => true, 'aktif' => 'distribusi'])->header('X-Rincian', $dis->kode);
    }

    /** Halaman penuh Surat Jalan. Alamat lama — tetap hidup untuk tautan langsung. */
    public function rincian(Request $request, int $id)
    {
        [$dis, $v] = $this->rincianData($request, $id);

        return view('distribusi.rincian', $v + ['aktif' => 'distribusi', 'di_modal' => false]);
    }

    /**
     * Satu pintu pengubah status dari modal.
     *
     * Modal hanya punya SATU select + SATU keterangan; dulu setiap perpindahan
     * punya rute & formulirnya sendiri (proses/kirim/tolak), sehingga menebak
     * tombol mana yang benar adalah beban pengguna. Di sini pilihannya divalidasi
     * terhadap `Distribusi::PINDAH` — matriks perpindahan yang sudah ada — jadi
     * status mustahil pun ditolak di satu tempat.
     */
    public function simpanStatus(Request $request, int $id)
    {
        $u = $request->user();
        $dis = Distribusi::with('item.barang')->findOrFail($id);

        if (! $u->bolehKoperasi((int) $dis->koperasi_id)) {
            abort(403);
        }

        $this->gagalValidasi($request);
        $d = $request->validate([
            'status' => ['required', 'string', Rule::in(Distribusi::STATUS)],
            'keterangan' => ['nullable', 'string', 'max:300'],
        ], [], ['status' => 'status', 'keterangan' => 'keterangan']);

        $ke = $d['status'];
        $ket = trim((string) ($d['keterangan'] ?? ''));

        if ($ke === $dis->status) {
            return $this->balasRincian($request, $dis, 'pesan',
                'Status '.Distribusi::label($ke).' tidak berubah.');
        }

        if (! $dis->boleh($ke)) {
            return $this->balasRincian($request, $dis, 'galat', 'Status tidak bisa berpindah dari '
                .Distribusi::label($dis->status).' ke '.Distribusi::label($ke).'.');
        }

        // Keterangan wajib pada penolakan: alasan penolakan tanpa keterangan
        // tidak berarti apa-apa bagi koperasi yang mengajukan.
        if ($ke === 'DITOLAK' && $ket === '') {
            return $this->balasRincian($request, $dis, 'galat',
                'Keterangan wajib diisi saat menolak pengajuan.');
        }

        // Penerimaan TIDAK bisa dari select: jumlah diterima dicatat PER BARANG
        // (dan bisa kurang dari yang dikirim). Jalurnya formulir penerimaan di
        // panel, bukan perpindahan status tanpa angka.
        if ($ke === 'DITERIMA') {
            return $this->balasRincian($request, $dis, 'galat',
                'Penerimaan dicatat lewat formulir "Barang Sudah Diterima" beserta jumlah per barang.');
        }

        // Cek stok lebih dulu supaya tidak ada surat jalan terkirim separuh.
        if ($ke === 'DIKIRIM') {
            $kurang = [];
            foreach ($dis->item as $it) {
                $stok = (int) ($it->barang->stok ?? 0);
                if ($stok < $it->jumlah) {
                    $kurang[] = "{$it->barang->nama} (butuh {$it->jumlah} {$it->barang->satuan}, stok $stok)";
                }
            }
            if ($kurang) {
                return $this->balasRincian($request, $dis, 'galat',
                    'Stok gudang tidak cukup: '.implode('; ', $kurang).'.');
            }
        }

        $pesan = '';
        DB::connection('hub')->transaction(function () use ($dis, $ke, $ket, $u, $request, &$pesan) {
            if ($ke === 'DIKIRIM') {
                // Stok gudang dipotong TEPAT saat status menjadi DIKIRIM.
                foreach ($dis->item as $it) {
                    $it->barang->decrement('stok', $it->jumlah);
                    $it->barang->update(['diperbarui' => kini()]);
                }
                $pesan = 'Barang untuk '.$dis->kode.' dikirim; stok gudang sudah dipotong.';
            } elseif ($ke === 'DITOLAK') {
                $pesan = 'Pengajuan '.$dis->kode.' ditolak.';
            } elseif ($ke === 'DIPROSES') {
                $pesan = 'Pengajuan '.$dis->kode.' mulai diproses.';
            } else {
                $pesan = 'Status '.$dis->kode.' menjadi '.Distribusi::label($ke).'.';
            }

            // Keterangan menumpuk di catatan pengelola, bukan menimpanya: riwayat
            // ("stok kosong" lalu "dikirim sebagian") harus tetap terbaca.
            $catatan = $dis->catatan_agrinas;
            if ($ket !== '') {
                $catatan = mb_substr(trim(($catatan !== '' ? $catatan."\n" : '').$ket), 0, 300);
            }

            $dis->update(['status' => $ke,
                'catatan_agrinas' => $catatan !== '' ? $catatan : $dis->catatan_agrinas,
                'diperbarui' => kini()]);

            // Nama aksi audit dipetakan BALIK ke nama lama (KIRIM/TOLAK/PROSES/TERIMA):
            // jejak audit adalah kontrak — rute boleh berganti, nama aksinya tidak.
            $aksi = ['DIKIRIM' => 'KIRIM', 'DITOLAK' => 'TOLAK', 'DIPROSES' => 'PROSES',
                     'DITERIMA' => 'TERIMA'][$ke] ?? $ke;
            jurnal($u->id, 'DISTRIBUSI_'.$aksi, $dis->kode,
                'stok='.($ke === 'DIKIRIM' ? 'dipotong' : '-').' ket='.$ket, $request->ip() ?? '');
        });

        return $this->balasRincian($request, $dis, 'pesan', $pesan);
    }

    /** Surat jalan siap cetak — PDF dibuat server, bukan diserahkan ke dialog cetak. */
    public function suratJalan(Request $request, int $id)
    {
        $u = $request->user();
        // `pemohon` adalah KOLOM (id pengguna), bukan relasi — jangan dimuat lewat
        // with(), modelnya tidak punya relasi itu.
        $dis = Distribusi::with(['item.barang', 'koperasi'])->findOrFail($id);

        if (! $u->bolehKoperasi((int) $dis->koperasi_id)) {
            abort(403);
        }
        if (! $dis->bolehUnduh()) {
            return back()->with('galat', 'Surat jalan terbit saat status Dalam Pengiriman.');
        }

        return view('distribusi.surat-jalan', [
            'dis' => $dis, 'aktif' => 'distribusi',
            'pemohon' => $dis->pemohon ? \App\Models\User::find($dis->pemohon) : null,
        ]);
    }

    /** Unduhan PDF surat jalan (dibuat server). */
    public function suratJalanPdf(Request $request, int $id)
    {
        $u = $request->user();
        $dis = Distribusi::with(['item.barang', 'koperasi'])->findOrFail($id);

        if (! $u->bolehKoperasi((int) $dis->koperasi_id)) {
            abort(403);
        }
        if (! $dis->bolehUnduh()) {
            return back()->with('galat', 'Surat jalan terbit saat status Dalam Pengiriman.');
        }

        $pdf = Pdf::dariBlade('distribusi.surat-jalan-pdf', [
            'dis' => $dis,
            'pemohon' => $dis->pemohon ? \App\Models\User::find($dis->pemohon) : null,
        ], 'Surat Jalan '.$dis->kode);

        return response($pdf->keluaran(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="surat-jalan-'.$dis->kode.'.pdf"',
        ]);
    }

    /**
     * Balasan seragam untuk aksi surat jalan.
     *
     * Modal tidak bisa mengikuti `redirect()` (halaman tidak berpindah): ia
     * meminta POTONGAN, jadi yang dikirim kembali adalah panel yang sudah
     * diperbarui beserta pesannya — termasuk saat GAGAL, sehingga galat
     * ("stok gudang tidak cukup") muncul di dalam modal alih-alih membuang
     * halaman. Tanpa JavaScript, jalur lama (`back()` + flash) tetap utuh.
     */
    private function balasRincian(Request $request, Distribusi $dis, string $kunci, string $pesan)
    {
        if ($this->inginPanel($request)) {
            $dis->refresh()->load(['item.barang', 'koperasi']);

            return response()->view('distribusi._rincian', [
                'dis' => $dis, 'agrinas' => ! $request->user()->manajerKoperasi(),
                'pemohon' => $dis->pemohon ? \App\Models\User::find($dis->pemohon) : null,
                'di_modal' => true, 'pesan' => $pesan, 'kunci' => $kunci,
                'aktif' => 'distribusi',
            ])->header('X-Rincian', $dis->kode);
        }

        return redirect()->route('distribusi.daftar')->with($kunci, $pesan);
    }

    /**
     * Alihkan galat validasi ke `balasRincian` untuk pemanggil AJAX.
     *
     * Tanpa ini, permintaan fetch yang gagal validasi dijawab 302 ke /masuk
     * (perilaku baku `ValidationException` pada permintaan non-JSON) — modal
     * menganggapnya sukses lalu menampilkan panel basi.
     */
    private function gagalValidasi(Request $request): void
    {
        if ($this->inginPanel($request)) {
            $request->headers->set('Accept', 'application/json');
        }
    }
}

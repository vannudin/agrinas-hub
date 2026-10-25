<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorTagihan;
use App\Support\Halaman;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Vendor penyedia barang subsidi (BULOG, Pupuk Indonesia, Pertamina, ID FOOD,
 * Agrinas Pangan Nusantara). Hanya Agrinas yang mengelola; manajer koperasi
 * tidak melihat harga/asal pasokan, jadi rutenya dikunci middleware `agrinas`.
 *
 * Tanpa hapus, sama seperti halaman Stok Gudang: vendor lama tetap sengaja
 * disimpan agar kaitan `barang.vendor_id` tidak putus.
 */
class VendorController extends Controller
{
    private function aturan(): array
    {
        return [
            'nama' => ['required', 'string', 'max:120'],
            'kode' => ['nullable', 'string', 'max:24', 'alpha_dash'],
            'jenis' => ['required', 'in:'.implode(',', array_keys(Vendor::JENIS))],
            'komoditas' => ['nullable', 'string', 'max:200'],
            'kontak' => ['nullable', 'string', 'max:200'],
            'catatan' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * Satu sumber penyaringan untuk daftar penuh & potongan AJAX. Kalau aturan
     * saringnya ditulis dua kali, hasil muat-ulang dan hasil AJAX bisa berbeda.
     */
    private function saring(Request $request): Builder
    {
        $cari = trim((string) $request->query('q', ''));
        $jenis = mb_strtoupper((string) $request->query('jenis', ''));
        $jenis = array_key_exists($jenis, Vendor::JENIS) ? $jenis : '';

        return Vendor::query()
            ->select('vendor.*')
            ->selectRaw('(SELECT COUNT(*) FROM barang b WHERE b.vendor_id = vendor.id) as jumlah_barang')
            ->when($jenis !== '', fn ($w) => $w->where('vendor.jenis', $jenis))
            ->when($cari !== '', function ($w) use ($cari) {
                // `%` dan `_` dilolosi: keduanya pola LIKE, dan tanpa ini mencari
                // `_` mencocokkan hampir semua baris.
                $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cari).'%';
                $w->where(fn ($x) => $x->where('vendor.nama', 'like', $pola)
                    ->orWhere('vendor.kode', 'like', $pola)
                    ->orWhere('vendor.komoditas', 'like', $pola)
                    ->orWhere('vendor.kontak', 'like', $pola));
            })
            ->orderBy('vendor.nama');
    }

    public function daftar(Request $request)
    {
        $q = $this->saring($request);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return view('vendor.daftar', [
            'aktif' => 'vendor', 'jenis' => Vendor::JENIS,
            'vendor' => $halaman->batasi($q)->get(),
            'halaman' => $halaman,
            'jumlah' => $jumlah,
            'cari' => trim((string) $request->query('q', '')),
            'saringJenis' => $request->query('jenis', ''),
            // Hitungan kartu peran: dihitung di SQL, tanpa saringan — supaya
            // angkanya tetap menunjukkan sebaran seluruh vendor.
            'stat' => Vendor::query()->selectRaw('jenis, COUNT(*) as n')
                ->groupBy('jenis')->pluck('n', 'jenis')->all(),
            // Satu query agregat — jangan tarik semua baris vendor hanya untuk
            // menjumlahkan (pola yang sama dengan paginasi: hitung di SQL).
            'total_barang' => Vendor::query()->selectRaw(
                'COALESCE(SUM((SELECT COUNT(*) FROM barang b WHERE b.vendor_id = vendor.id)), 0) as t'
            )->value('t'),
        ]);
    }

    /**
     * Potongan baris tabel untuk AJAX (cari & saring tanpa muat ulang).
     * Markup-nya memakai partial yang SAMA dengan daftar penuh, jadi hasil AJAX
     * mustahil berbeda dari hasil muat ulang.
     */
    public function baris(Request $request)
    {
        $q = $this->saring($request);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        // Dibuka langsung (bukan lewat fetch): alihkan ke halaman dengan saringan
        // yang sama, supaya pengunjung tidak menerima potongan baris telanjang.
        if (! ($request->ajax() || $request->wantsJson())) {
            return redirect()->route('vendor', $request->query());
        }

        return response()->view('vendor._baris', [
            'vendor' => $halaman->batasi($q)->get(),
            'kosong' => ($this->saringKosong($request)),
        ])->header('X-Jumlah', (string) $jumlah);
    }

    private function saringKosong(Request $request): bool
    {
        $jenis = mb_strtoupper((string) $request->query('jenis', ''));

        return trim((string) $request->query('q', '')) !== ''
            || array_key_exists($jenis, Vendor::JENIS);
    }

    public function simpan(Request $request)
    {
        $d = $request->validate($this->aturan());

        // `??` bukan `?:` — 'nullable' hanya mengizinkan null bila key-nya ada;
        // formulir yang tak mengirim kolom kode sama sekali akan meledak.
        $kode = mb_strtoupper($d['kode'] ?? Vendor::kodeBaru());
        if (Vendor::query()->where('kode', $kode)->exists()) {
            return back()->with('galat', 'Kode vendor '.$kode.' sudah dipakai.');
        }

        $v = Vendor::create([
            'kode' => $kode, 'nama' => $d['nama'], 'jenis' => $d['jenis'],
            'komoditas' => $d['komoditas'] ?? '', 'kontak' => $d['kontak'] ?? '',
            'catatan' => $d['catatan'] ?? '', 'dibuat' => kini(), 'diperbarui' => kini(),
        ]);

        jurnal($request->user()->id, 'VENDOR_BARU', $v->kode, 'nama='.$v->nama, $request->ip() ?? '');

        return redirect()->route('vendor')->with('pesan', 'Vendor '.$v->nama.' ditambahkan.');
    }

    public function ubah(Request $request, int $id)
    {
        $v = Vendor::findOrFail($id);
        $d = $request->validate(collect($this->aturan())->except('kode')->all());

        // Kolom opsional yang tidak dikirim permintaan mempertahankan nilai
        // lama, bukan dikosongkan — kalau tidak, mengubah satu kolom menghapus
        // catatan/kontak yang sudah ada. String kosong tetap dihormati (bukan
        // null), jadi pengosongan yang disengaja lewat formulir tetap jalan.
        $v->update([...$d,
            'komoditas' => $d['komoditas'] ?? $v->komoditas,
            'kontak' => $d['kontak'] ?? $v->kontak,
            'catatan' => $d['catatan'] ?? $v->catatan,
            'diperbarui' => kini()]);

        jurnal($request->user()->id, 'VENDOR_UBAH', $v->kode, 'nama='.$v->nama, $request->ip() ?? '');

        return redirect()->route('vendor')->with('pesan', 'Vendor '.$v->nama.' diperbarui.');
    }

    /**
     * Tagihan vendor ke Agrinas: daftar + keputusan bayar/tolak.
     *
     * Dipisah dari daftar vendor karena PEKERJAANNYA beda — di sini yang dibaca
     * adalah uang keluar, bukan data mitra. Pagar rutenya `bayar` (pusat, PIC,
     * keuangan), bukan `agrinas`: petugas keuangan yang melunasi, dan ia tidak
     * punya akses kendali pusat.
     */
    public function tagihan(Request $request)
    {
        $status = mb_strtoupper((string) $request->query('status', ''));
        $status = in_array($status, VendorTagihan::STATUS, true) ? $status : '';

        $q = VendorTagihan::query()->select('vendor_tagihan.*')
            ->when($status !== '', fn ($w) => $w->where('vendor_tagihan.status', $status))
            ->orderByDesc('vendor_tagihan.id');

        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return view('vendor.tagihan', [
            // `aktif` = kunci menu, BUKAN nama peran halaman: halaman ini butir
            // menunya "Tagihan Vendor" (kunci 'tagihan'). Dulu diisi 'vendor',
            // sehingga yang menyala justru butir "Vendor" di sebelahnya.
            'aktif' => 'tagihan', 'tagihan' => $halaman->batasi($q)->get(),
            'halaman' => $halaman, 'jumlah' => $jumlah, 'saringStatus' => $status,
            'vendorNama' => Vendor::query()->pluck('nama', 'id')->all(),
            'stat' => VendorTagihan::query()->selectRaw('status, COUNT(*) as n')
                ->groupBy('status')->pluck('n', 'status')->all(),
            // Dua angka uang yang dipakai memutuskan: yang masih menunggu dan
            // yang sudah keluar. Dihitung di SQL, bukan dari baris halaman aktif.
            'menunggu' => VendorTagihan::where('status', 'DIAJUKAN')->sum('jumlah'),
            'dibayar' => VendorTagihan::where('status', 'DIBAYAR')->sum('jumlah'),
            'bolehBayar' => $request->user()->bolehBayar(),
        ]);
    }

    /** Lunasi atau tolak satu tagihan. DIAJUKAN adalah satu-satunya status yang bisa diputus. */
    public function tagihan_putus(Request $request, int $id)
    {
        $t = VendorTagihan::findOrFail($id);
        $d = $request->validate([
            'status' => ['required', 'in:DIBAYAR,DITOLAK'],
            'catatan' => ['nullable', 'string', 'max:300'],
        ]);

        if (! $t->boleh($d['status'])) {
            return back()->with('galat', 'Tagihan '.$t->kode.' sudah berstatus '.$t->labelStatus().'.');
        }

        // Tandai DIBAYAR hanya boleh pusat/keuangan — PIC memutus di wilayahnya,
        // tetapi uang keluar tetap dicatat bendahara (pola sama dengan pesanan).
        if ($d['status'] === 'DIBAYAR' && ! $request->user()->bolehBayar()) {
            abort(403, 'Pelunasan tagihan dicatat admin pusat atau petugas keuangan.');
        }

        $t->forceFill([
            'status' => $d['status'],
            'catatan' => (string) ($d['catatan'] ?? ''),
            'dibayar' => $d['status'] === 'DIBAYAR' ? kini() : '',
            'diperbarui' => kini(),
        ])->save();

        jurnal($request->user()->id, 'VENDOR_TAGIHAN_'.$d['status'], $t->kode,
            'jumlah='.$t->jumlah.' kanal='.$t->kanal, $request->ip() ?? '');

        return back()->with('pesan', 'Tagihan '.$t->kode.' '
            .($d['status'] === 'DIBAYAR' ? 'ditandai dibayar' : 'ditolak').'.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use App\Models\Vendor;
use App\Models\VendorTagihan;
use App\Support\Halaman;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Halaman kerja VENDOR penyalur (`/mitra`).
 *
 * Vendor hanya melihat barang MILIKNYA: pesanan disaring lewat
 * `pesanan_item.barang_id` → `barang.vendor_id`. Tanpa saringan itu, satu akun
 * vendor membaca seluruh pesanan Agrinas — termasuk harga barang vendor lain.
 *
 * Dua pekerjaan di sini, sesuai permintaan Bos:
 *   1. DISTRIBUSI: menandai pesanan yang sudah dibayar sebagai DIKIRIM, lengkap
 *      dengan nomor surat jalan (barang fisik berangkat dari gudang vendor).
 *   2. TAGIHAN: menerbitkan invoice yang ditagihkan ke Agrinas untuk pesanan
 *      yang barangnya sudah berangkat. `kanal` menyalin cara koperasi membayar:
 *      SALDO (dipotong dari Saldo Agrinas) atau SENDIRI (kas koperasi).
 *
 * Pembayaran tagihan BUKAN wewenang vendor: tombolnya di `/admin/vendor/tagihan`
 * (pusat & keuangan). Vendor yang menagih tidak boleh sekaligus melunasi.
 */
class MitraController extends Controller
{
    private function vendor(Request $request): Vendor
    {
        // `vendorAkun()` sudah menjamin vendor_id > 0; findOrFail menjaga kalau
        // barisnya keburu dihapus di halaman admin.
        return Vendor::findOrFail($request->user()->vendor_id);
    }

    /** Pesanan yang memuat minimal satu barang milik vendor ini. */
    private function pesananVendor(Request $request): Builder
    {
        $vendorId = (int) $request->user()->vendor_id;

        return Pesanan::query()
            ->select('pesanan.*')
            ->whereExists(function ($q) use ($vendorId) {
                $q->selectRaw('1')->from('pesanan_item')
                    ->join('barang', 'barang.id', '=', 'pesanan_item.barang_id')
                    ->whereColumn('pesanan_item.pesanan_id', 'pesanan.id')
                    ->where('barang.vendor_id', $vendorId);
            })
            // DIPESAN belum dibayar: barang belum boleh berangkat. DIBATALKAN
            // tidak perlu dikerjakan. Yang tersisa = pekerjaan nyata vendor.
            ->whereIn('pesanan.status', ['DIBAYAR', 'DIKIRIM', 'DITERIMA']);
    }

    public function index(Request $request)
    {
        $v = $this->vendor($request);
        $q = $this->pesananVendor($request)->orderByDesc('pesanan.id');

        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);
        $baris = $halaman->batasi($q)->get();

        // Tagihan yang sudah terbit untuk pesanan di halaman ini — satu query
        // untuk seluruh tabel, bukan satu query per baris.
        $sudahDitagih = VendorTagihan::query()
            ->where('vendor_id', $v->id)
            ->whereIn('pesanan_id', $baris->pluck('id')->all() ?: [0])
            ->pluck('kode', 'pesanan_id')->all();

        return view('mitra.index', [
            'aktif' => 'distribusi', 'vendor' => $v,
            'pesanan' => $baris,
            'halaman' => $halaman, 'jumlah' => $jumlah,
            'sudahDitagih' => $sudahDitagih,
            // Ringkasan dihitung di SQL: satu vendor bisa punya ratusan pesanan,
            // dan menarik semuanya ke memori hanya untuk menjumlahkan itu boros.
            'belumKirim' => (clone $this->pesananVendor($request))->where('status', 'DIBAYAR')->count(),
            'tagihanDiajukan' => VendorTagihan::where('vendor_id', $v->id)->where('status', 'DIAJUKAN')->sum('jumlah'),
            'tagihanDibayar' => VendorTagihan::where('vendor_id', $v->id)->where('status', 'DIBAYAR')->sum('jumlah'),
        ]);
    }

    /**
     * Tandai barang berangkat ke koperasi (status DIBAYAR → DIKIRIM).
     *
     * Nomor surat jalan diterbitkan di sini dengan `Pesanan::suratJalanBaru()`
     * — penomoran yang SAMA dengan jalur pengelola, supaya satu pesanan tidak
     * punya dua seri nomor bukti serah-terima.
     */
    public function kirim(Request $request, int $id)
    {
        $p = $this->pesananVendor($request)->whereKey($id)->firstOrFail();

        if ($p->status !== 'DIBAYAR') {
            return back()->with('galat', $p->status === 'DIKIRIM'
                ? 'Pesanan '.$p->invoice.' sudah dikirim.'
                : 'Pesanan '.$p->invoice.' sudah diterima koperasi.');
        }

        $kapan = kini();
        $p->forceFill([
            'status' => 'DIKIRIM',
            'dikirim' => $kapan,
            'antar' => $p->antar ?: 'KIRIM',
            'surat_jalan' => $p->surat_jalan !== '' ? $p->surat_jalan : Pesanan::suratJalanBaru(),
            'diperbarui' => $kapan,
        ])->save();

        jurnal($request->user()->id, 'MITRA_KIRIM', $p->invoice,
            'sj='.$p->surat_jalan, $request->ip() ?? '');

        return back()->with('pesan', 'Surat jalan '.$p->surat_jalan.' terbit — barang '
            .$p->invoice.' dalam perjalanan ke koperasi.');
    }

    /**
     * Terbitkan tagihan (invoice) ke Agrinas untuk satu pesanan.
     *
     * Jumlah TIDAK diketik vendor: diambil dari `pesanan.total`, angka yang
     * sudah disepakati saat koperasi memesan. Kolom angka yang bisa diisi bebas
     * oleh pihak yang menagih adalah cara termudah membuat tagihan membengkak.
     */
    public function tagih(Request $request, int $id)
    {
        $p = $this->pesananVendor($request)->whereKey($id)->firstOrFail();
        $d = $request->validate(['keterangan' => ['nullable', 'string', 'max:200']]);

        if ($p->status === 'DIBAYAR') {
            return back()->with('galat', 'Kirim barangnya dulu: tagihan terbit setelah '
                .$p->invoice.' berstatus Dikirim.');
        }

        if (VendorTagihan::where('pesanan_id', $p->id)->exists()) {
            return back()->with('galat', 'Pesanan '.$p->invoice.' sudah punya tagihan.');
        }

        $t = VendorTagihan::create([
            'kode' => VendorTagihan::kodeBaru(),
            'vendor_id' => (int) $request->user()->vendor_id,
            'koperasi_id' => $p->koperasi_id,
            'pesanan_id' => $p->id,
            'keterangan' => $d['keterangan'] ?? ('Barang pesanan '.$p->invoice),
            'jumlah' => (int) $p->total,
            'kanal' => VendorTagihan::kanalPesanan($p->kanal),
            'status' => 'DIAJUKAN',
            'catatan' => '', 'dibuat' => kini(), 'diperbarui' => kini(), 'dibayar' => '',
        ]);

        jurnal($request->user()->id, 'MITRA_TAGIH', $t->kode,
            'pesanan='.$p->invoice.' jumlah='.$t->jumlah.' kanal='.$t->kanal, $request->ip() ?? '');

        return back()->with('pesan', 'Tagihan '.$t->kode.' terbit untuk '.$p->invoice
            .' — Rp '.number_format($t->jumlah, 0, ',', '.').' ('.$t->labelKanal().').');
    }

    public function tagihan(Request $request)
    {
        $v = $this->vendor($request);
        $q = VendorTagihan::query()->where('vendor_id', $v->id)->orderByDesc('id');
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), 10);

        return view('mitra.tagihan', [
            'aktif' => 'keuangan', 'vendor' => $v,
            'tagihan' => $halaman->batasi($q)->get(),
            'halaman' => $halaman, 'jumlah' => $jumlah,
            'diajukan' => VendorTagihan::where('vendor_id', $v->id)->where('status', 'DIAJUKAN')->sum('jumlah'),
            'dibayar' => VendorTagihan::where('vendor_id', $v->id)->where('status', 'DIBAYAR')->sum('jumlah'),
        ]);
    }
}

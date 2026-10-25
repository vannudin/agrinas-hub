<?php

namespace App\Http\Controllers;

use App\Models\KonferensiRapat;
use App\Support\Halaman;
use App\Support\SfuRapat;
use Illuminate\Http\Request;

class RapatController extends Controller
{
    /**
     * Daftar rapat — HANYA /rapat (menu samping), sejak 2026-10-14.
     *
     * Dulu ada method kedua (`pegawai()`) untuk /agri/konferensi; butir kisi
     * modul AgriConnect itu sudah dihapus atas permintaan Bos, jadi satu pintu
     * cukup. Method `pegawai()` DIPERTAHANKAN sebagai pengalih supaya tautan
     * lama /agri/konferensi tidak buntu — tidak ada lagi jalur yang bisa
     * menyajikan view berbeda untuk data yang sama.
     *
     * Halaman ini memuat tombol **Ruang**; dulu halaman Konferensi hanya
     * menyuruh pindah ke Kalender Ruang Kerja, jalan buntu — pengguna melihat
     * jadwalnya tetapi tidak bisa langsung masuk.
     */
    public function daftar(Request $request)
    {
        return $this->tampilkan($request, false);
    }

    /** Pengalih rute lama /agri/konferensi → /rapat (butir kisinya sudah dihapus). */
    public function pegawai(Request $request)
    {
        $kueri = $request->query();
        unset($kueri['tampil']);

        return redirect()->route('rapat', $kueri);
    }

    /** Satu penyusun data untuk halaman Konferensi — jadwal & saringannya satu sumber. */
    private function tampilkan(Request $request, bool $pegawai)
    {
        $cari = trim((string) $request->query('q', ''));
        $jenis = (string) $request->query('jenis', '');

        $semua = KonferensiRapat::query()
            ->when($cari !== '', fn ($w) => $w->where('judul', 'like', '%'.$cari.'%'))
            ->when($jenis !== '', fn ($w) => $w->where('jenis', $jenis))
            ->orderByDesc('mulai')->orderByDesc('id');
        $jumlah = (clone $semua)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return view('agri.konferensi', [
            'aktif' => $pegawai ? 'agri' : 'rapat',
            'daftar' => $halaman->batasi($semua)->get(), 'halaman' => $halaman,
            'sekarang' => now()->format('Y-m-d H:i'),
            'cari' => $cari, 'jenis' => $jenis,
            'jenisPilihan' => KonferensiRapat::JENIS,
            'pusat' => $request->user()->agrinas(),
            'sfu' => SfuRapat::siap(),
        ]);
    }

    /** Ruang video — disematkan, tanpa SDK tambahan. */
    public function ruang(Request $request, int $id)
    {
        $r = KonferensiRapat::findOrFail($id);
        $u = $request->user();
        $peserta = array_filter(explode(',', (string) ($r->siaran_sesi ?? '')));

        return view('rapat-ruang', ['aktif' => 'rapat', 'rapat' => $r,
            'sfu' => SfuRapat::siap(), 'nama' => $u->nama,
            // Pembicara undangan boleh menerbitkan sejak detik pertama — tanpa
            // ini, undangan hanya nama di daftar dan orangnya tetap jadi penonton
            // sampai pengelola mengangkatnya dari dalam ruang.
            'penyiar' => $u->agrinas() || $r->jenis === 'meeting' || $r->pembicaraUndang((int) $u->id),
            'jumlah' => count($peserta)]);
    }

    /** Kelola jadwal rapat — hanya Agrinas. */
    public function kelola(Request $request)
    {
        $cari = trim((string) $request->query('q', ''));
        $jenis = in_array($request->query('jenis'), ['meeting', 'webinar'], true)
            ? (string) $request->query('jenis') : '';
        $status = in_array($request->query('status'), ['AKAN', 'KINI', 'SELESAI'], true)
            ? (string) $request->query('status') : '';

        // Waktu kini menurut WIB. `mulai`/`selesai` disimpan sebagai WIB polos, jadi
        // penyaringan status dilakukan di SQL dengan pembanding yang sama — kalau
        // disaring di PHP sesudah paging, jumlah halaman jadi salah.
        $kini = now('Asia/Jakarta')->format('Y-m-d H:i:s');
        $durasi = '+'.KonferensiRapat::DURASI_BAWAAN_JAM.' hours';

        $semua = KonferensiRapat::query()
            ->when($cari !== '', fn ($w) => $w->where('judul', 'like', '%'.$cari.'%'))
            ->when($jenis !== '', fn ($w) => $w->where('jenis', $jenis))
            ->when($status === 'AKAN', fn ($w) => $w->where('mulai', '>', $kini))
            ->when($status === 'SELESAI', fn ($w) => $w->whereRaw(
                "coalesce(nullif(selesai, ''), datetime(mulai, ?)) < ?", [$durasi, $kini]))
            ->when($status === 'KINI', fn ($w) => $w
                ->where('mulai', '<=', $kini)
                ->whereRaw("coalesce(nullif(selesai, ''), datetime(mulai, ?)) >= ?", [$durasi, $kini]))
            ->orderByDesc('mulai')->orderByDesc('id');

        $halaman = Halaman::buat((clone $semua)->count(), $request->query('halaman', 1),
            DasborController::PER_HALAMAN);

        return view('admin.rapat', [
            'aktif' => 'rapat',
            'daftar' => $halaman->batasi($semua)->get(),
            'halaman' => $halaman,
            'cari' => $cari,
            'jenis' => $jenis,
            'status' => $status,
            // Pilihan undangan pembicara. Hub kecil (puluhan akun), jadi seluruh
            // akun aktif dikirim sekali dan penyaringan nama dilakukan di peramban
            // — tidak perlu endpoint pencarian tersendiri.
            'pengguna' => \App\Models\User::query()->where('aktif', 1)
                ->orderBy('nama')->get(['id', 'nama', 'email']),
        ]);
    }

    /**
     * Tetapkan PEMBICARA UNDANGAN untuk satu rapat.
     *
     * Daftar dikirim UTUH setiap kali (centang = ganti seluruh daftar), sama
     * seperti formulir lain di Hub: tidak ada tambah/hapus terpisah yang bisa
     * membuat dua sumber kebenaran.
     */
    public function undang(Request $request, int $id)
    {
        $r = KonferensiRapat::findOrFail($id);
        $d = $request->validate([
            'undang' => ['nullable', 'array', 'max:60'],
            'undang.*' => ['integer'],
        ], [], ['undang' => 'pembicara undangan']);

        // Hanya akun aktif yang benar-benar ada — id karangan dari luar ditolak
        // diam-diam di sini, bukan disimpan lalu gagal saat rapat berjalan.
        $sah = \App\Models\User::query()->whereIn('id', $d['undang'] ?? [])
            ->where('aktif', 1)->orderBy('nama')->get(['id', 'nama']);

        $r->forceFill(['pembicara' => $sah->pluck('id')->map(fn ($x) => (string) $x)->implode(',')])->save();

        jurnal($request->user()->id, 'RAPAT_UNDANG', (string) $r->id,
            $r->judul.' — pembicara: '.($sah->pluck('nama')->implode(', ') ?: 'kosong'),
            $request->ip() ?? '');

        return redirect()->route('admin.rapat')->with('pesan', $sah->isEmpty()
            ? 'Daftar pembicara rapat "'.$r->judul.'" dikosongkan.'
            : count($sah).' pembicara diundang ke "'.$r->judul.'": '.$sah->pluck('nama')->implode(', ').'.');
    }

    /**
     * Jadwalkan rapat BARU, atau ubah jadwal yang sudah ada bila `id` diisi.
     *
     * Satu jalur untuk dua tindakan: yang berubah hanya apakah baris konferensi
     * di-`create` atau di-`update`. `ruang` sengaja TIDAK dibuat ulang saat
     * mengubah — ruangnya sudah dibagikan ke peserta, mengganti namanya berarti
     * tautan yang sudah dikirim jadi salah.
     */
    public function jadwalkan(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:konferensi,id'],
            'judul' => ['required', 'string', 'max:160'],
            'mulai' => ['required', 'date'],
            'selesai' => ['nullable', 'date', 'after:mulai'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'jenis' => ['nullable', 'string', 'in:meeting,webinar'],
        ], ['selesai.after' => 'Waktu selesai harus setelah waktu mulai.'], [
            'id' => 'rapat', 'judul' => 'judul', 'mulai' => 'waktu mulai',
            'selesai' => 'waktu selesai', 'catatan' => 'catatan', 'jenis' => 'jenis',
        ]);

        $mulai = date('Y-m-d H:i:s', strtotime((string) $data['mulai']) ?: time());
        $isi = [
            'judul' => $data['judul'],
            'mulai' => $mulai,
            'selesai' => ! empty($data['selesai']) ? date('Y-m-d H:i:s', strtotime((string) $data['selesai'])) : '',
            'catatan' => (string) ($data['catatan'] ?? ''),
            'jenis' => (string) ($data['jenis'] ?? 'meeting'),
        ];

        $ubah = (int) ($data['id'] ?? 0);
        if ($ubah > 0) {
            $r = KonferensiRapat::findOrFail($ubah);
            $r->forceFill($isi)->save();
            jurnal($request->user()->id, 'RAPAT_UBAH', (string) $r->id, $r->judul.' mulai '.$r->mulai,
                $request->ip() ?? '');

            return redirect()->route('admin.rapat')->with('pesan', 'Jadwal "'.$r->judul.'" diperbarui.');
        }

        $r = KonferensiRapat::create($isi + [
            'ruang' => KonferensiRapat::ruang_baru($data['judul'], $mulai),
            'oleh' => $request->user()->id,
            'dibuat' => kini(),
        ]);

        jurnal($request->user()->id, 'RAPAT_JADWAL', (string) $r->id, $r->judul.' mulai '.$r->mulai,
            $request->ip() ?? '');

        return redirect()->route('admin.rapat')->with('pesan', 'Rapat "'.$r->judul.'" dijadwalkan.');
    }

    public function hapus(Request $request, int $id)
    {
        $r = KonferensiRapat::findOrFail($id);
        jurnal($request->user()->id, 'RAPAT_HAPUS', (string) $r->id, $r->judul, $request->ip() ?? '');
        $r->delete();

        return redirect()->route('admin.rapat')->with('pesan', 'Jadwal rapat dihapus.');
    }
}

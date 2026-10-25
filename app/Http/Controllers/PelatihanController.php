<?php

namespace App\Http\Controllers;

use App\Models\LmsMateri;
use App\Models\LmsProgres;
use App\Services\LmsPenarik;
use Illuminate\Http\Request;

/**
 * Materi pelatihan (dari LMS APN) + progres belajar.
 *
 * Manajer hanya bisa membaca dan menandai modul selesai; Agrinas mengelola
 * (menarik ulang dari LMS, menyunting, menghapus salinan lokal).
 */
class PelatihanController extends Controller
{
    /** Daftar path + modul + progres pengguna (dashboard pembelajaran). */
    public function sesi(Request $request)
    {
        $uid = (int) $request->user()->id;
        $semua = LmsMateri::query()->orderBy('path_id')->orderBy('posisi')->orderBy('id')->get();
        $selesai = LmsMateri::selesaiUntuk($uid);

        // Modul dikelompokkan per learning path supaya sejalan dengan LMS.
        $path = $semua->groupBy('path_id')->map(function ($modul) use ($selesai) {
            $tuntas = $modul->filter(fn ($m) => in_array($m->lms_id, $selesai, true))->count();

            return ['id' => $modul->first()->path_id, 'judul' => $modul->first()->path_judul,
                    'modul' => $modul->values(), 'selesai' => $tuntas, 'jumlah' => $modul->count(),
                    'persen' => $modul->count() ? (int) round($tuntas / $modul->count() * 100) : 0];
        })->values();

        return view('pelatihan.daftar', [
            'aktif' => 'pelatihan', 'path' => $path,
            'ringkas' => LmsMateri::ringkasanProgres($uid), 'selesai' => $selesai,
        ]);
    }

    /** Baca satu modul + navigasi antar modul dalam path yang sama. */
    public function baca(Request $request, int $lmsId)
    {
        $m = LmsMateri::query()->where('lms_id', $lmsId)->firstOrFail();
        $uid = (int) $request->user()->id;
        $sudah = in_array($m->lms_id, LmsMateri::selesaiUntuk($uid), true);

        $serumpun = LmsMateri::query()->where('path_id', $m->path_id)
            ->orderBy('posisi')->orderBy('id')->get();
        $posisi = $serumpun->search(fn ($x) => $x->id === $m->id);

        return view('pelatihan.baca', [
            'aktif' => 'pelatihan', 'm' => $m, 'sudah' => $sudah, 'serumpun' => $serumpun,
            'sebelum' => $posisi > 0 ? $serumpun[$posisi - 1] : null,
            'sesudah' => $posisi !== false && $posisi < $serumpun->count() - 1 ? $serumpun[$posisi + 1] : null,
            'ringkas' => LmsMateri::ringkasanProgres($uid),
        ]);
    }

    /** Tandai / batalkan tanda selesai untuk modul ini. */
    public function selesai(Request $request, int $lmsId)
    {
        LmsMateri::query()->where('lms_id', $lmsId)->firstOrFail();
        $uid = (int) $request->user()->id;
        $ada = LmsProgres::query()->where('user_id', $uid)->where('lms_id', $lmsId)->first();

        if ($ada) {
            $ada->delete();
            $pesan = 'Tanda selesai dibatalkan — modul kembali ke daftar belajar.';
        } else {
            LmsProgres::create(['user_id' => $uid, 'lms_id' => $lmsId, 'selesai_pada' => kini()]);
            jurnal($uid, 'PELATIHAN_SELESAI', (string) $lmsId, '', $request->ip() ?? '');
            $pesan = 'Bagus! Modul ditandai selesai.';
        }

        return back()->with('pesan', $pesan);
    }

    /** Halaman kelola (khusus Agrinas): tarik ulang dari LMS + CRUD salinan lokal. */
    public function kelola(Request $request)
    {
        $semua = LmsMateri::query()->orderBy('path_id')->orderBy('posisi')->orderBy('id')->get();

        return view('admin.pelatihan', [
            'aktif' => 'pelatihan', 'daftar' => $semua,
            'terakhir' => $semua->max('diperbarui'), 'jumlahPath' => $semua->pluck('path_id')->unique()->count(),
        ]);
    }

    /** Tarik ulang seluruh modul dari LMS APN. */
    public function tarik(Request $request)
    {
        try {
            $hasil = (new LmsPenarik())->tarik();
        } catch (\Throwable $e) {
            return back()->with('galat', 'Gagal menarik dari LMS: '.$e->getMessage());
        }

        jurnal($request->user()->id, 'PELATIHAN_TARIK', '', json_encode($hasil), $request->ip() ?? '');

        return back()->with('pesan', 'Materi ditarik dari LMS — '.$hasil['baru'].' baru, '.$hasil['diperbarui'].' diperbarui.');
    }

    /** Sunting salinan lokal (judul/ringkas/konten) tanpa menyentuh LMS. */
    public function simpan(Request $request, int $id)
    {
        $m = LmsMateri::findOrFail($id);
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:180'],
            'ringkas' => ['nullable', 'string', 'max:1000'],
            'konten' => ['nullable', 'string'],
            'menit' => ['nullable', 'integer', 'min:0', 'max:600'],
        ], [], ['judul' => 'judul', 'ringkas' => 'ringkasan', 'konten' => 'konten', 'menit' => 'durasi']);

        $m->update(['judul' => $data['judul'], 'ringkas' => (string) ($data['ringkas'] ?? ''),
                    'konten' => (string) ($data['konten'] ?? ''), 'menit' => (int) ($data['menit'] ?? 0),
                    'disunting_oleh' => $request->user()->id, 'diperbarui' => kini()]);
        jurnal($request->user()->id, 'PELATIHAN_SUNTING', (string) $m->id, $m->judul, $request->ip() ?? '');

        return back()->with('pesan', 'Materi "'.$m->judul.'" diperbarui.');
    }

    /** Hapus salinan lokal. Modul tetap ada di LMS dan bisa ditarik ulang. */
    public function hapus(Request $request, int $id)
    {
        $m = LmsMateri::findOrFail($id);
        LmsProgres::query()->where('lms_id', $m->lms_id)->delete();
        jurnal($request->user()->id, 'PELATIHAN_HAPUS', (string) $m->id, $m->judul, $request->ip() ?? '');
        $m->delete();

        return back()->with('pesan', 'Salinan materi dihapus (masih ada di LMS, bisa ditarik ulang).');
    }
}

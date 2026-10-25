<?php

namespace App\Http\Controllers;

use App\Models\VoteSuara;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pemungutan "Manager Memilih Sistem" untuk manajer koperasi.
 *
 * Halaman ini sengaja TIDAK membandingkan aplikasi lain: isinya hanya kebutuhan
 * manajer (satu sistem terpusat yang lengkap, mudah, stabil) lalu dua opsi.
 *
 * Hasil dihitung di SQL (`count(*) group by pilihan`), bukan di PHP: target
 * populasi 29.000 manajer, jadi menghitung di memori berarti menarik semua
 * baris setiap kali halaman dibuka.
 */
class VoteController extends Controller
{
    public function halaman(Request $request)
    {
        $u = $request->user();

        $hitung = VoteSuara::where('topik', VoteSuara::TOPIK)
            ->selectRaw('pilihan, count(*) as n')
            ->groupBy('pilihan')
            ->pluck('n', 'pilihan');

        $suara = VoteSuara::where('topik', VoteSuara::TOPIK)->where('user_id', $u->id)->first();

        return view('vote', [
            'aktif' => 'vote',
            'suara' => $suara,
            'hitung' => $hitung,
            'total' => (int) $hitung->sum(),
        ]);
    }

    public function simpan(Request $request)
    {
        $data = $request->validate([
            'pilihan' => ['required', 'integer', Rule::in(array_keys(VoteSuara::PILIHAN))],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $u = $request->user();

        // firstOrNew: satu baris per (topik, user_id) — memilih ulang menimpa,
        // tidak menggandakan. `dibuat` hanya diisi saat baris benar-benar baru,
        // supaya waktu suara pertama tidak ikut berubah saat pilihan diubah.
        $suara = VoteSuara::firstOrNew(['topik' => VoteSuara::TOPIK, 'user_id' => $u->id]);
        $baru = ! $suara->exists;
        $suara->fill([
            'pilihan' => (int) $data['pilihan'],
            'catatan' => (string) ($data['catatan'] ?? ''),
            'diperbarui' => kini(),
        ]);
        if ($baru) {
            $suara->dibuat = kini();
        }
        $suara->save();

        jurnal($u->id, 'VOTE', 'sistem-manajer',
            'pilihan='.$data['pilihan'].' ('.VoteSuara::PILIHAN[(int) $data['pilihan']].')', $request->ip());

        return redirect()->route('vote')->with('pesan', $baru
            ? 'Suara Anda tersimpan. Terima kasih.'
            : 'Pilihan Anda diperbarui.');
    }
}

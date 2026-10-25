<?php

namespace App\Http\Controllers;

use App\Models\Kontrak;
use App\Models\User;
use App\Services\SimkopdesKontrak;
use App\Support\Halaman;
use App\Support\Wilayah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Kontrak pendamping KDKMP: PERIODE, PENEMPATAN, dan KONTRAK-nya dalam satu
 * halaman. Datanya salinan SIMKOPDES yang diisi saat pendamping login (lihat
 * App\Services\SimkopdesKontrak) — jadi admin pusat & PIC bisa memeriksa data
 * kontrak tanpa perlu akun SIMKOPDES siapa pun.
 *
 * Lingkup: pusat melihat semua, PIC wilayahnya, manajer hanya dirinya.
 */
class KontrakController extends Controller
{
    /**
     * Lingkup peran: pusat semua, PIC wilayahnya (dari kolom penempatan kontrak
     * sendiri — baris `koperasi` Hub belum tentu ada untuk koperasi itu),
     * manajer hanya dirinya. Satu tempat, dipakai daftar & hitungan kartu.
     */
    private function lingkup(Request $request, Builder $q): Builder
    {
        $u = $request->user();

        if ($u->pic()) {
            $q->where(Wilayah::saring((string) $u->wilayah,
                'kontrak.penempatan_provinsi', 'kontrak.penempatan_wilayah'));
        } elseif (! $u->agrinas()) {
            $q->where('kontrak.user_id', $u->id);
        }

        return $q;
    }

    /**
     * Satu sumber penyaringan untuk daftar penuh & potongan AJAX. Kalau saringan
     * ditulis dua kali, hasil muat-ulang dan hasil AJAX bisa berbeda.
     */
    private function saring(Request $request): Builder
    {
        $cari = trim((string) $request->query('q', ''));
        $status = mb_strtoupper((string) $request->query('status', ''));

        $q = $this->lingkup($request, Kontrak::query())
            ->join('users', 'users.id', '=', 'kontrak.user_id')
            ->select('kontrak.*', 'users.nama as nama_pendamping', 'users.email as email_pendamping');

        if ($status !== '') {
            $q->where('kontrak.kontrak_status', $status);
        }

        if ($cari !== '') {
            $pola = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $cari).'%';
            $q->where(fn ($x) => $x->where('users.nama', 'like', $pola)
                ->orWhere('users.email', 'like', $pola)
                ->orWhere('kontrak.kontrak_nomor', 'like', $pola)
                ->orWhere('kontrak.kontrak_komitmen', 'like', $pola)
                ->orWhere('kontrak.penempatan_nama', 'like', $pola));
        }

        return $q->orderByDesc('kontrak.periode_id')->orderBy('users.nama');
    }

    public function daftar(Request $request)
    {
        $q = $this->saring($request);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        $u = $request->user();

        // Hitungan kartu status: dihitung di SQL dengan lingkup peran yang sama,
        // tapi TANPA saringan — supaya angkanya menunjukkan sebaran seluruh
        // kontrak, bukan hasil pencarian yang sedang aktif. TANPA join users:
        // hitungan tidak butuh namanya, dan join-nya menambah baris kembar.
        $stat = $this->lingkup($request, Kontrak::query())
            ->selectRaw('kontrak.kontrak_status as s, COUNT(*) as n')
            ->groupBy('kontrak.kontrak_status')->pluck('n', 's')->all();

        return view('kontrak.daftar', [
            'aktif' => 'kontrak',
            'user' => $u,
            'kontrak' => $halaman->batasi($q)->get(),
            'halaman' => $halaman,
            'jumlah' => $jumlah,
            'cari' => trim((string) $request->query('q', '')),
            'saringStatus' => mb_strtoupper((string) $request->query('status', '')),
            'stat' => $stat,
            'total' => array_sum($stat),
            'sandiSiap' => $u->manajerKoperasi() && (string) ($u->simkopdes_sandi ?? '') !== '',
        ]);
    }

    /** Potongan baris tabel untuk AJAX (cari & saring tanpa muat ulang). */
    public function baris(Request $request)
    {
        $q = $this->saring($request);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        if (! ($request->ajax() || $request->wantsJson())) {
            return redirect()->route('kontrak', $request->query());
        }

        return response()->view('kontrak._baris', [
            'kontrak' => $halaman->batasi($q)->get(),
            'kosong' => trim((string) $request->query('q', '')) !== ''
                || (string) $request->query('status', '') !== '',
            'pemilik' => (int) $request->user()->id,
        ])->header('X-Jumlah', (string) $jumlah);
    }

    /** Tarik ulang kontrak sendiri dari SIMKOPDES (selain tarikan otomatis saat login). */
    public function tarik(Request $request)
    {
        $u = $request->user();

        if (! $u->manajerKoperasi()) {
            return back()->with('galat', 'Hanya akun pendamping/manajer yang punya kontrak di SIMKOPDES.');
        }

        try {
            $r = app(SimkopdesKontrak::class)->tarik($u);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('galat', 'Gagal menarik kontrak: '.$e->getMessage());
        }

        if ($r['baris'] === 0) {
            return back()->with('galat', 'Belum ada periode/kontrak yang bisa ditarik. '
                .'Hubungkan akun SIMKOPDES lebih dulu di halaman Presensi & Absensi.');
        }

        jurnal($u->id, 'KONTRAK_TARIK', $u->email,
            "periode={$r['periode']} baris={$r['baris']} kontrak={$r['kontrak']}", $request->ip() ?? '');

        return back()->with('pesan', "Kontrak diperbarui dari SIMKOPDES ({$r['baris']} penempatan, "
            ."{$r['kontrak']} berkontrak).");
    }

    /**
     * Unduh berkas kontrak (PDF) — diambil SEGAR dari SIMKOPDES lalu diteruskan.
     *
     * Tidak disimpan di Hub karena `contract_file` adalah URL presigned GCS yang
     * mati ~1 jam; menyalinnya cuma menyimpan alamat mati. Karena itu unduhan
     * hanya bisa dilakukan pemilik akunnya (token sendiri), sedangkan admin &
     * PIC membaca nomor/status/tanggalnya dari salinan Hub.
     */
    public function berkas(Request $request, int $id)
    {
        $k = Kontrak::findOrFail($id);

        if ((int) $k->user_id !== (int) $request->user()->id) {
            return back()->with('galat', 'Berkas kontrak hanya bisa diunduh pemilik akunnya. '
                .'Nomor, status, dan tanggalnya tetap bisa diperiksa di tabel ini.');
        }

        $u = User::find($k->user_id);

        try {
            $url = app(SimkopdesKontrak::class)->berkasUrl($u, (int) $k->periode_id);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('galat', 'Berkas belum bisa diambil: '.$e->getMessage());
        }

        if ($url === '') {
            return back()->with('galat', 'SIMKOPDES belum mengunggah berkas kontrak ini.');
        }

        $r = Http::timeout(60)->connectTimeout(20)->get($url);
        if (! $r->successful()) {
            return back()->with('galat', 'Berkas kontrak gagal diunduh (HTTP '.$r->status().').');
        }

        $nama = 'Kontrak-'.$k->periode_id.'-'.($k->kontrak_jenis ?: 'PKWT').'.pdf';

        return response($r->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Koperasi;
use App\Models\Profil as ProfilModel;
use App\Models\User;
use App\Services\ProfilPenarik;
use App\Support\ScryptHasher;
use Illuminate\Http\Request;

/**
 * Profil pengguna: identitas (cerminan SIMKOPDES) + ganti sandi.
 *
 * Menu keamanan lama digabung ke sini supaya pengguna punya satu tempat untuk
 * "tentang saya" dan "kredensial saya", bukan dua menu terpisah.
 */
class ProfilController extends Controller
{
    public function __construct(private readonly ProfilPenarik $penarik) {}

    public function index(Request $request)
    {
        $u = $request->user();
        $profil = ProfilModel::where('user_id', $u->id)->first();

        return view('profil', [
            'aktif' => 'profil',
            'profil' => $profil,
            'mentah' => (array) ($profil->profil ?? []),
            'koperasi' => $this->koperasi($u),
        ]);
    }

    /**
     * Tarik ulang profil sendiri dari SIMKOPDES. Tidak menyalin sandi dari sini:
     * penyalinan sandi hanya lewat CLI (`profil:tarik --sandi=...`) supaya sandi
     * tidak pernah melewati formulir web.
     */
    public function tarik(Request $request)
    {
        if (! $this->penarik->terkonfigurasi()) {
            return back()->with('galat', 'SIMKOPDES belum diatur di server ini.');
        }

        try {
            $r = $this->penarik->tarik();
        } catch (\Throwable $e) {
            return back()->with('galat', 'Gagal menarik profil: '.$e->getMessage());
        }

        jurnal($request->user()?->id, 'PROFIL_TARIK', $request->user()?->email,
            "profil={$r['profil']}", $request->ip() ?? '');

        return back()->with('pesan', "Profil diperbarui dari SIMKOPDES (koperasi: {$r['koperasi_baru']} baru, "
            ."{$r['koperasi_ubah']} diubah).");
    }

    /**
     * Simpan perubahan profil oleh pemiliknya sendiri.
     *
     * Hanya kolom identitas yang boleh diubah. `peran`, `koperasi_id`, dan
     * `users.tanggal_lahir` TIDAK ikut: peran & pautan koperasi menentukan hak
     * akses, dan tanggal lahir di `users` adalah kunci verifikasi aktivasi.
     *
     * Baris `profil` dibuat kalau belum ada (akun hasil impor belum punya
     * salinan SIMKOPDES), dengan `sumber='HUB'` supaya jelas asalnya isian
     * sendiri, bukan tarikan.
     */
    public function simpan(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'niak' => ['nullable', 'digits:16'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'tempat_lahir' => ['nullable', 'string', 'max:80'],
            'tanggal_lahir' => ['nullable', 'date_format:Y-m-d'],
            'gender' => ['nullable', 'string', 'max:20'],
            'agama' => ['nullable', 'string', 'max:30'],
            'status_kawin' => ['nullable', 'string', 'max:30'],
            'golongan_darah' => ['nullable', 'string', 'max:5'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'desa' => ['nullable', 'string', 'max:80'],
            'kecamatan' => ['nullable', 'string', 'max:80'],
            'kabupaten' => ['nullable', 'string', 'max:80'],
            'provinsi' => ['nullable', 'string', 'max:80'],
        ], ['niak.digits' => 'NIK harus 16 angka.'], ['niak' => 'NIK', 'tanggal_lahir' => 'tanggal lahir']);

        $u = $request->user();
        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);

        $u->forceFill(['nama' => $data['nama']])->save();
        unset($data['nama']);

        ProfilModel::updateOrCreate(
            ['user_id' => $u->id],
            $data + ['sumber' => 'HUB', 'email' => $u->email, 'nama' => $u->nama],
        );

        jurnal($u->id, 'PROFIL_UBAH', $u->email, implode(',', array_keys($data)), $request->ip() ?? '');

        return redirect('/profil?pesan=Profil+tersimpan.');
    }

    /** Ganti sandi (dulu ada di SandiController). */
    public function sandi(Request $request)
    {
        $data = $request->validate([
            'lama' => ['required', 'string'],
            'baru' => ['required', 'string', 'min:10'],
            'ulang' => ['required', 'string', 'same:baru'],
        ], [
            'baru.min' => 'Sandi minimal 10 karakter.',
            'ulang.same' => 'Ulangi sandi tidak sama.',
        ], ['lama' => 'sandi lama', 'baru' => 'sandi baru', 'ulang' => 'ulangi sandi']);

        $u = $request->user();

        if (! ScryptHasher::cek($data['lama'], $u->sandi)) {
            // Akun yang sandinya masih milik SIMKOPDES tidak punya sandi lokal
            // yang diketahui pengguna — jelaskan, jangan cuma bilang "salah".
            $pesan = $u->sandi_sumber === 'SIMKOPDES'
                ? 'Sandi+halaman+ini+masih+mengikuti+sandi+SIMKOPDES+Anda.'
                : 'Sandi+lama+salah.';

            return redirect('/profil?galat='.$pesan.'#sandi');
        }

        $u->forceFill(['sandi' => ScryptHasher::buat($data['baru']),
            'sandi_sumber' => 'HUB', 'wajib_ganti' => 0])->save();

        jurnal($u->id, 'GANTI_SANDI', $u->email, '', $request->ip() ?? '');

        return redirect('/profil?pesan=Sandi+berhasil+diperbarui.#sandi');
    }

    /**
     * Koperasi yang ditampilkan: tempat bertugas (dari profil SIMKOPDES), lalu
     * koperasi yang dikelola akun ini. Dulu keduanya hanya lewat `manajer_id`.
     *
     * @return \Illuminate\Support\Collection<int, Koperasi>
     */
    private function koperasi(User $u)
    {
        $ids = [];
        if ($u->koperasi_id) {
            $ids[] = (int) $u->koperasi_id;
        }

        return Koperasi::whereIn('id', $ids)->orWhere('manajer_id', $u->id)
            ->orderBy('nama')->get();
    }
}

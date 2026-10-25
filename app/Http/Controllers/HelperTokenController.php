<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SimkopdesAbsensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penerima token SIMKOPDES dari helper yang berjalan di PC admin.
 *
 * Login otomatis dari server ditolak SIMKOPDES (reCAPTCHA + IP datacenter),
 * jadi login dikerjakan Chrome ASLI di PC admin — IP rumahan, captcha bisa
 * diselesaikan manusia — lalu token sesinya dikirim ke sini dan disimpan untuk
 * akun pemiliknya. Hanya menerima permintaan yang membawa kunci helper.
 */
class HelperTokenController extends Controller
{
    public function __construct(private readonly SimkopdesAbsensi $absen) {}

    public function simpan(Request $request): JsonResponse
    {
        $kunci = (string) config('services.simkopdes.helper_key');
        if ($kunci === '' || ! hash_equals($kunci, (string) $request->header('X-Helper-Key'))) {
            return response()->json(['ok' => false, 'pesan' => 'Kunci helper salah.'], 403);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string', 'min:20', 'max:300'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $u = User::where('email', $email)->where('aktif', 1)->first();
        if (! $u) {
            return response()->json(['ok' => false, 'pesan' => 'Email tidak terdaftar di Hub.'], 404);
        }

        try {
            $profil = $this->absen->simpanTokenBrowser($u, trim($data['token']));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'pesan' => 'Token ditolak SIMKOPDES.'], 422);
        }

        // Token harus benar-benar milik email yang dilaporkan — kalau tidak,
        // sesi orang lain bisa tersimpan di akun yang salah.
        $pemilik = mb_strtolower(trim((string) ($profil['email'] ?? '')));
        if ($pemilik !== '' && $pemilik !== $email) {
            $u->forceFill(['simkopdes_token' => ''])->save();

            return response()->json(['ok' => false, 'pesan' => "Token milik {$pemilik}, bukan {$email}."], 409);
        }

        jurnal($u->id, 'SIMP_TOKEN_HELPER', $email, 'token dari helper PC', $request->ip() ?? '');

        return response()->json(['ok' => true, 'user_id' => $u->id, 'nama' => $u->nama]);
    }
}

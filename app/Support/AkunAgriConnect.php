<?php

namespace App\Support;

use App\Models\Siaran;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Kredensial AgriConnect untuk akun manajer SIMKOPDES.
 *
 * Aturan dari Bos (2026-10-22), satu tempat supaya tidak tersebar di command,
 * view, dan gate:
 *
 *   Email    : email SIMKOPDES tanpa penanda `+sppi`
 *              `budi+sppi@gmail.com` → `budi@gmail.com`
 *   Password : `Agrinas#` + empat angka terakhir nomor kontrak
 *              `12345/HCMM - PKWT KDKMP/IX/2026` → `Agrinas#2345`
 *              `9848/HCMM - PKWT KDKMP/IX/2026`  → `Agrinas#9848`
 *
 * Nomor kontrak yang tidak punya angka di depan TIDAK ditebak: hasilnya null,
 * dan pemanggil wajib melewatinya (bukan mengarang sandi).
 */
class AkunAgriConnect
{
    /** Perihal pengumuman pribadi. Dipakai bersama oleh command & jalur baca. */
    public const PERIHAL = 'Pemberitahuan Akun AgriConnect (rahasia)';

    /** Email AgriConnect: buang penanda `+sppi` sebelum `@`. */
    public static function email(string $email): string
    {
        return (string) preg_replace('/\+sppi(?=@)/i', '', trim($email));
    }

    /** Sandi AgriConnect dari nomor kontrak; null bila nomornya tak berangka. */
    public static function sandi(string $nomorKontrak): ?string
    {
        // Angka di DEPAN nomor kontrak saja: "12345/HCMM - PKWT…" → 12345.
        // Nomor kontrak lain di belakangnya (IX/2026) bukan bagian sandi.
        if (! preg_match('/^\s*(\d+)/', (string) $nomorKontrak, $m)) {
            return null;
        }

        return 'Agrinas#'.substr($m[1], -4);
    }

    /** Isi pengumuman pribadi untuk satu akun. */
    public static function pesan(string $email, string $sandi): string
    {
        return "Pemberitahuan Akun AgriConnect\n\n"
            ."Email: {$email}\n"
            ."Password: {$sandi}\n\n"
            ."Email = email Anda tanpa penanda +sppi. Password = Agrinas# diikuti 4 angka terakhir nomor kontrak PKWT 1.\n"
            ."Nomor kontrak bisa dilihat di aplikasi SIMKOPDES, tab Kontrak.\n"
            ."Kalau sudah ada PKWT 2, sandinya TETAP memakai nomor PKWT 1 (sistem memakai PKWT 1).\n"
            ."Contoh: nomor kontrak 12345/HCMM - PKWT KDKMP/IX/2026 → password Agrinas#2345\n\n"
            ."Akun ini dipakai untuk masuk AgriConnect (presensi & profil kepegawaian) — bisa lewat\n"
            ."web Agrinas Hub (menu AgriConnect) atau aplikasi Android AgriConnect.\n"
            ."Simpan baik-baik dan jangan bagikan ke siapa pun — pengumuman ini hanya untuk Anda.";
    }

    /**
     * Nomor kontrak PKWT PERTAMA akun ini; '' bila belum ada.
     *
     * AgriConnect selalu memakai nomor PKWT 1 dari SIMKOPDES, juga untuk orang
     * yang sudah punya PKWT 2 — jadi sandi tidak pernah ikut berubah saat PKWT 2
     * terbit. Dulu di sini `orderByDesc('id')` (kontrak TERAKHIR): begitu PKWT 2
     * berisi nomor, pengumuman akun yang kita terbitkan akan memuat sandi yang
     * salah. Urutan naik = ambil yang pertama terisi (baris kontrak kosong
     * dilewati).
     */
    public static function nomorKontrak(int $userId): string
    {
        return (string) (DB::connection('hub')->table('kontrak')
            ->where('user_id', $userId)->where('kontrak_nomor', '!=', '')
            ->orderBy('id')->value('kontrak_nomor') ?? '');
    }

    /**
     * Terbitkan pengumuman pribadi (sasaran `usr:<id>`) untuk satu akun.
     *
     * Dua pemanggil: command massal dan jalur baca lonceng. Yang kedua itu
     * sebabnya akun yang baru masuk SETELAH command dijalankan tetap kebagian
     * — tanpa cron, tanpa menjalankan command lagi.
     *
     * @return 'dibuat'|'sudah'|'tanpa-nomor'|'bukan-manajer'
     */
    public static function terbitkan(User $u, bool $paksa = false): string
    {
        if (! $u->manajerKoperasi() || ! str_contains((string) $u->email, '+sppi@')) {
            return 'bukan-manajer';
        }

        $sasaran = 'usr:'.$u->id;
        if (! $paksa && Siaran::query()->where('sasaran', $sasaran)
            ->where('perihal', self::PERIHAL)->exists()) {
            return 'sudah';
        }

        $sandi = self::sandi(self::nomorKontrak((int) $u->id));
        if ($sandi === null) {
            return 'tanpa-nomor';
        }

        Siaran::create([
            'kanal' => Siaran::KANAL_PENGUMUMAN,
            'perihal' => self::PERIHAL,
            'pesan' => self::pesan(self::email((string) $u->email), $sandi),
            'sasaran' => $sasaran,
            'jumlah' => 1,
            'pengirim' => 'sistem',
            'dibuat' => kini(),
        ]);

        return 'dibuat';
    }
}

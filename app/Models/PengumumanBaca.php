<?php

namespace App\Models;

use App\Support\AkunAgriConnect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Penanda "sudah dibaca" untuk pengumuman (`broadcast`).
 *
 * Pengumumannya sendiri tinggal di tabel `broadcast` — lihat App\Models\Siaran
 * dan halaman /pengumuman. Tabel ini hanya menyimpan PASANGAN (user, pengumuman)
 * yang sudah dibaca, supaya ikon lonceng bisa menghitung yang belum dibaca.
 *
 * Dibuat satu tabel tersendiri, bukan kolom di `broadcast`: pembacanya banyak
 * (satu baris per orang per pengumuman), sedangkan `broadcast` satu baris per
 * pengumuman.
 */
class PengumumanBaca extends Model
{
    protected $connection = 'hub';
    protected $table = 'pengumuman_baca';

    public $timestamps = false;

    protected $fillable = ['user_id', 'siaran_id', 'dibaca'];

    /**
     * Pengumuman yang berlaku untuk seorang pengguna.
     *
     * Aturan sasaran disamakan dengan yang sudah dipakai jalur kirim
     * (Siaran::SASARAN) supaya tidak ada dua arti "manajer" di aplikasi ini:
     *   SEMUA   → semua akun aktif
     *   MANAJER → hanya manajer
     *   AGRINAS → admin Agrinas + petugas HR (keduanya di meja pusat)
     *   kop:XXX → manajer koperasi berkode XXX saja
     *
     * Master cabang peran ('MANAJER'/'AGRINAS') cukup dibandingkan dengan peran
     * pengguna; hanya `kop:` yang butuh kueri koperasi.
     */
    public static function untuk(User $u)
    {
        return Siaran::query()->where(function ($w) use ($u) {
            $w->where('sasaran', 'SEMUA');
            // Pengumuman PRIBADI (`usr:<id>`): hanya pemilik akun yang melihat.
            // Inilah satu-satunya jalur yang boleh memuat kredensial per orang —
            // sasaran MANAJER/SEMUA selalu dibaca banyak akun.
            $w->orWhere('sasaran', 'usr:'.$u->id);
            if ($u->manajerKoperasi()) {
                $w->orWhere('sasaran', 'MANAJER');
                $kode = $u->koperasiDiampu()?->kode;
                if ($kode) {
                    $w->orWhere('sasaran', 'kop:'.mb_strtoupper((string) $kode));
                }
            }
            if ($u->agrinas() || $u->hrd()) {
                $w->orWhere('sasaran', 'AGRINAS');
            }
        });
    }

    /** Jumlah pengumuman yang BELUM dibaca pengguna ini — untuk lencana lonceng. */
    public static function belumDibaca(User $u): int
    {
        // Akun manajer yang masuk SETELAH command massal dijalankan tetap
        // kebagian: pengumuman pribadinya diterbitkan di sini, sekali saja,
        // saat lonceng pertama kali dihitung. Tanpa cron.
        AkunAgriConnect::terbitkan($u);

        $sudah = DB::connection('hub')->table('pengumuman_baca')
            ->where('user_id', $u->id)->pluck('siaran_id');

        return self::untuk($u)->whereNotIn('id', $sudah)->count();
    }

    /**
     * Pengumuman PRIBADI yang belum dibaca — dipakai layar sambutan setelah
     * masuk. Isinya bisa kredensial, jadi yang diambil HANYA sasaran `usr:<id>`
     * milik pengguna ini (bukan SEMUA/MANAJER).
     */
    public static function pribadiBelumDibaca(User $u)
    {
        // Sama seperti belumDibaca(): dipanggil saat login, jadi akun baru
        // langsung menerima kredensialnya di layar pertama — bukan esok hari.
        AkunAgriConnect::terbitkan($u);

        $sudah = DB::connection('hub')->table('pengumuman_baca')
            ->where('user_id', $u->id)->pluck('siaran_id');

        return Siaran::query()->where('sasaran', 'usr:'.$u->id)
            ->whereNotIn('id', $sudah)->orderByDesc('id')->get();
    }

    /** Tandai seluruh pengumuman yang berlaku sebagai sudah dibaca. */
    public static function tandaiSemua(User $u): int
    {
        $belum = self::untuk($u)->whereNotIn('id',
            DB::connection('hub')->table('pengumuman_baca')->where('user_id', $u->id)->pluck('siaran_id')
        )->pluck('id');

        if ($belum->isEmpty()) {
            return 0;
        }

        $kini = now();
        DB::connection('hub')->table('pengumuman_baca')->insert(
            $belum->map(fn ($id) => ['user_id' => $u->id, 'siaran_id' => $id, 'dibaca' => $kini])->all()
        );

        return $belum->count();
    }
}

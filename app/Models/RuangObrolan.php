<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Ruang Messenger: grup (jenis=grup), utas topik (jenis=topik), atau ruang yang
 *  menempel pada dokumen/rapat/tugas (kolom acuan + acuan_id). */
class RuangObrolan extends Model
{
    public const JENIS = ['grup' => 'Grup', 'topik' => 'Utas Topik', 'dokumen' => 'Dokumen',
        'rapat' => 'Rapat', 'tugas' => 'Tugas', 'pribadi' => 'Pribadi'];

    /**
     * Penanda jenis akses: '' terbuka, 'anggota' hanya peserta.
     *
     * TIDAK ADA penanda 'pribadi' di sini — dan itu memang disengaja.
     * Percakapan pribadi punya kolom `kunci` berisi pasangan id "a:b"
     * (lihat AnggotaObrolan::kunciPribadi), supaya A-B dan B-A menunjuk ruang
     * yang SAMA. Dulu konstanta ini memuat `'pribadi' => 'pribadi'`, dan tiga
     * tempat membandingkan `$ruang->kunci === KUNCI['pribadi']` — hasilnya
     * SELALU salah: filter "Pribadi" kosong padahal ruangnya ada, nama lawan
     * bicara tak pernah dipakai, dan kepala ruang salah label. Penentunya
     * sekarang satu method: `pribadi()`.
     */
    public const KUNCI = ['terbuka' => '', 'anggota' => 'anggota'];

    protected $connection = 'hub';
    protected $table = 'obrolan_ruang';
    public $timestamps = false;

    protected $fillable = ['nama', 'jenis', 'acuan', 'acuan_id', 'koperasi_id', 'pemilik_id',
        'dibuat_oleh', 'dibuat', 'kunci', 'topik', 'lingkup'];

    /**
     * Ruang obrolan dalam lingkup pengguna: milik sendiri, koperasi lingkup, atau
     * umum (tanpa koperasi & tanpa pemilik).
     *
     * Ruang ber-`pemilik_id` (pembahasan dokumen pribadi) SENGAJA tidak ikut
     * lewat cabang "koperasi_id NULL = umum" — kalau ikut, pembahasan pribadi
     * terbaca semua orang. Hanya pemiliknya (dan pengelola pusat) yang melihatnya.
     *
     * Tinggal di MODEL, bukan di controller, karena dipakai dua konsumen: daftar
     * ruang di WorkspaceController dan lencana "belum dibaca" di layout. Dulu ia
     * salinan tunggal di controller; menyalinnya ke layout berarti aturan akses
     * punya dua tempat dan bisa berbeda diam-diam (bocor).
     */
    public static function lingkup(User $u): Builder
    {
        $lingkup = $u->koperasiQuery()->select('koperasi.id');
        $anggota = AnggotaObrolan::where('user_id', $u->id)->select('ruang_id');

        return static::query()->where(function ($q) use ($lingkup, $u, $anggota) {
            // Terbuka: umum (tanpa koperasi & tanpa pemilik) atau koperasi lingkup.
            // Ruang terbatas (kunci != '') SENGAJA tidak lewat cabang ini — kalau
            // ikut, percakapan pribadi lain terbaca siapa pun yang tahu id-nya.
            $q->where(fn ($w) => $w->where('kunci', '')
                ->where(fn ($x) => $x
                    ->where(fn ($y) => $y->whereNull('koperasi_id')->whereNull('pemilik_id'))
                    ->orWhereIn('koperasi_id', $lingkup)));

            // Ruang terbatas: hanya pesertanya (dan pemilik dokumen pribadi).
            $q->orWhereIn('id', $anggota);
            if (! $u->manajerKoperasi()) {
                $q->orWhere('pemilik_id', $u->id);
            }
        });
    }

    /**
     * Jumlah pesan belum dibaca pengguna ini di SELURUH ruang lingkupnya — satu
     * angka untuk lencana menu Message.
     *
     * Sama persis dengan perhitungan di WorkspaceController::daftarRuang():
     * dibandingkan dengan posisi baca (`obrolan_baca.pesan_id`, 0 bila belum
     * pernah dibuka), bukan kiriman sendiri, bukan pesan ditarik, dan pesan
     * terjadwal yang belum waktunya tidak dihitung. Batasnya lewat `lingkup()`
     * supaya angka ini TIDAK PERNAH menghitung ruang yang tak boleh dibuka.
     */
    public static function belumDibaca(User $u): int
    {
        return PesanObrolan::query()
            ->leftJoin('obrolan_baca as b', function ($j) use ($u) {
                $j->on('b.ruang_id', '=', 'obrolan_pesan.ruang_id')->where('b.user_id', '=', $u->id);
            })
            ->whereIn('obrolan_pesan.ruang_id', static::lingkup($u)->select('obrolan_ruang.id'))
            ->whereColumn('obrolan_pesan.id', '>', DB::raw('coalesce(b.pesan_id, 0)'))
            ->where('obrolan_pesan.oleh', '!=', (string) $u->email)
            ->where('obrolan_pesan.dihapus', '')
            ->where(fn ($q) => $q->whereNull('obrolan_pesan.kirim_pada')
                ->orWhere('obrolan_pesan.kirim_pada', '<=', kini()))
            ->count();
    }

    public function pesan()
    {
        return $this->hasMany(PesanObrolan::class, 'ruang_id');
    }

    /** Peserta ruang terbatas (pribadi / grup tertutup). */
    public function anggota()
    {
        return $this->hasMany(AnggotaObrolan::class, 'ruang_id');
    }

    public function jumlah_pesan(): int
    {
        return PesanObrolan::where('ruang_id', $this->id)->count();
    }

    /** Ruang melekat pada sesuatu? Mengembalikan [jenis, judul] atau null. */
    /**
     * Apakah ini percakapan pribadi (dua orang)?
     *
     * Sumber kebenaran UTAMA adalah `jenis`, karena itulah yang ditulis saat
     * ruang dibuat. `kunci` berpola "a:b" dipakai sebagai cadangan untuk baris
     * lama yang jenis-nya mungkin belum terisi. Jangan membandingkan `kunci`
     * dengan teks 'pribadi' — nilai aslinya adalah pasangan id.
     */
    public function pribadi(): bool
    {
        return $this->jenis === 'pribadi'
            || (bool) preg_match('/^\d+:\d+$/', (string) $this->kunci);
    }

    public function tautan(): ?array
    {
        $judul = match ($this->acuan) {
            'rapat' => KonferensiRapat::find($this->acuan_id)?->judul,
            'dokumen' => Dokumen::find($this->acuan_id)?->judul,
            'tugas' => Tugas::find($this->acuan_id)?->judul,
            default => null,
        };

        return $judul ? [$this->acuan, $judul] : null;
    }
}

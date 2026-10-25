<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Siaran extends Model
{
    public const KANAL = ['EMAIL' => 'Email', 'WHATSAPP' => 'WhatsApp'];

    /** Sasaran broadcast; kode koperasi memakai awalan kop:. */
    public const SASARAN = ['SEMUA' => 'Semua akun aktif', 'MANAJER' => 'Semua manajer',
                            'AGRINAS' => 'Semua admin Agrinas'];

    /** Kanal pengumuman: tabel `broadcast` dipakai bersama siaran email/WA. */
    public const KANAL_PENGUMUMAN = 'PENGUMUMAN';

    protected $connection = 'hub';
    protected $table = 'broadcast';
    public $timestamps = false;

    protected $fillable = ['kanal', 'perihal', 'pesan', 'sasaran', 'jumlah', 'pengirim', 'dibuat'];

    /**
     * Penerima menurut sasaran, beserta kode koperasi untuk penautan.
     *
     * SATU kueri ber-join, bukan `User::with(...)->get()`: sasaran MANAJER kini
     * 33 ribu akun pendamping, dan model Eloquent + relasi per baris membuat
     * endpoint CSV siaran habis waktu (badan jawaban hanya tajuk). Join hanya
     * menarik empat kolom yang dipakai, jadi tetap ringan di skala nasional.
     */
    public static function penerima(string $sasaran): Collection
    {
        $q = DB::table('users as u')
            ->where('u.aktif', 1)
            ->leftJoin('koperasi as k', 'k.id', '=', 'u.koperasi_id')
            ->leftJoin('profil as p', function ($j) {
                $j->on('p.user_id', '=', 'u.id')
                    ->whereNotNull('p.tanggal_lahir')->where('p.tanggal_lahir', '!=', '');
            })
            ->select('u.nama', 'u.email', 'p.telepon', 'k.nama as koperasi', 'k.kode');

        if ($sasaran === 'SEMUA') {
            $q->orderBy('u.peran')->orderBy('u.nama');
        } elseif ($sasaran === 'AGRINAS') {
            $q->where('u.peran', Domain::PERAN_AGRINAS)->orderBy('u.nama');
        } elseif ($sasaran === 'MANAJER') {
            $q->where('u.peran', Domain::PERAN_MANAJER)->orderBy('u.nama');
        } elseif (str_starts_with($sasaran, 'kop:')) {
            $kode = mb_strtoupper(substr($sasaran, 4));
            $q->whereIn('u.id', Koperasi::where('kode', $kode)->select('manajer_id'));
        } else {
            return collect();
        }

        return $q->get()->map(fn ($r) => (object) [
            'nama' => (string) $r->nama,
            'email' => (string) $r->email,
            'telepon' => (string) ($r->telepon ?? ''),
            'koperasi' => (string) ($r->koperasi ?? ''),
            'kode' => (string) ($r->kode ?? ''),
        ]);
    }

    /** Perihal kosong (siaran WhatsApp sering tanpa subjek) — jangan tampilkan hampa. */
    public function label_perihal(): string
    {
        return $this->perihal !== '' && $this->perihal !== null
            ? (string) $this->perihal
            : 'Pengumuman '.(string) $this->kanal;
    }

    public function ringkas(int $maks = 180): string
    {
        $teks = trim(preg_replace('/\s+/', ' ', (string) $this->pesan) ?? '');

        return mb_strlen($teks) <= $maks ? $teks : mb_substr($teks, 0, $maks).'…';
    }

    public static function label_sasaran(string $sasaran): string
    {
        if (str_starts_with($sasaran, 'usr:')) {
            $id = (int) substr($sasaran, 4);

            return 'Pribadi — '.(User::whereKey($id)->value('nama') ?? '#'.$id);
        }

        if (str_starts_with($sasaran, 'kop:')) {
            $kode = mb_strtoupper(substr($sasaran, 4));

            return 'Manajer '.$kode.' — '.(Koperasi::where('kode', $kode)->value('nama') ?? $kode);
        }

        return self::SASARAN[$sasaran] ?? $sasaran;
    }

    /** Isi pesan yang sudah diganti placeholder. */
    public static function isi_pesan(string $pesan, object $penerima, ?string $perihal = null): string
    {
        return strtr($pesan, [
            '{nama}' => $penerima->nama,
            '{email}' => $penerima->email,
            '{koperasi}' => $penerima->koperasi,
            '{perihal}' => (string) $perihal,
        ]);
    }
}

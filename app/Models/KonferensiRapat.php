<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal konferensi video (Cloudflare Realtime).
 *
 * `jenis` menentukan bentuk rapat: 'meeting' (semua orang bicara dua arah) atau
 * 'webinar' (pembicara → penonton). `ruang` sengaja dipertahankan sebagai
 * pengenal unik rapat meski tidak lagi dipakai membentuk tautan Jitsi.
 */
class KonferensiRapat extends Model
{
    /** Bentuk rapat. */
    public const JENIS = ['meeting' => 'Video Meeting', 'webinar' => 'Video Webinar'];

    protected $connection = 'hub';
    protected $table = 'konferensi';
    public $timestamps = false;

    protected $fillable = ['judul', 'ruang', 'mulai', 'selesai', 'catatan', 'oleh', 'dibuat',
        'jenis', 'siaran_sesi', 'siaran_oleh', 'siaran_penyiar', 'kendali', 'pembicara'];

    /** Id pengguna yang DIUNDANG admin sebagai pembicara (CSV). @return array<int,string> */
    public function daftarPembicara(): array
    {
        return array_values(array_filter(explode(',', (string) ($this->pembicara ?? ''))));
    }

    /** Apakah pengguna ini diundang sebagai pembicara untuk rapat ini? */
    public function pembicaraUndang(?int $userId): bool
    {
        return $userId !== null && in_array((string) $userId, $this->daftarPembicara(), true);
    }

    /** Pengenal ruang: slug judul + tanggal + akhiran acak. Tidak lagi jadi tautan
     *  Jitsi, hanya nama unik yang tampil di daftar rapat. */
    public static function ruang_baru(string $judul, string $mulai): string
    {
        $dasar = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($judul)), '-');
        $dasar = $dasar === '' ? 'rapat-agrinas' : mb_substr($dasar, 0, 40);

        return 'agrinas-'.$dasar.'-'.date('Ymd', strtotime($mulai) ?: time())
            .'-'.bin2hex(random_bytes(2));
    }

    public function mulai_manusia(): string
    {
        return tanggal_id($this->mulai, 'd M Y H:i');
    }

    public function label_jenis(): string
    {
        return self::JENIS[$this->jenis] ?? self::JENIS['meeting'];
    }

    /**
     * Pendahulu `status()`: bandingkan dengan waktu yang diberikan.
     *
     * PENTING — zona waktu. Kolom `mulai`/`selesai` disimpan sebagai waktu WIB polos
     * (`2026-10-11 22:00:00`), sedangkan `now()` di aplikasi ini UTC. Membandingkan
     * keduanya apa adanya membuat rapat yang SEDANG BERLANGSUNG terbaca "akan
     * datang": rapat 22:00 WIB baru dianggap mulai ketika UTC menyentuh 22:00,
     * yaitu 05:00 WIB besoknya — tujuh jam setelah rapatnya selesai.
     *
     * Karena itu perbandingan SELALU memakai WIB: nilai naive diperlakukan sebagai
     * WIB, dan pemanggil boleh TIDAK memberi argumen (yang dianjurkan) supaya
     * waktunya diambil dari jam WIB sekarang, bukan dari UTC.
     */
    public const DURASI_BAWAAN_JAM = 2;

    /** Batas waktu WIB dari sebuah kolom naive. */
    private function wib(?string $nilai): ?\Carbon\Carbon
    {
        return trim((string) $nilai) !== ''
            ? \Carbon\Carbon::parse((string) $nilai, 'Asia/Jakarta')
            : null;
    }

    /** Saat sekarang dalam WIB — argumen opsional untuk pengujian. */
    private function kiniWib(string $sekarang = ''): \Carbon\Carbon
    {
        return trim($sekarang) !== ''
            ? \Carbon\Carbon::parse($sekarang, 'Asia/Jakarta')
            : now('Asia/Jakarta');
    }

    /**
     * Status rapat terhadap waktu sekarang: 'AKAN', 'KINI', atau 'SELESAI'.
     *
     * Satu tempat untuk SEMUA tampilan (halaman pegawai, halaman mandiri, dan meja
     * penjadwalan) — dulu tiap view menghitung sendiri di Blade dan memakai
     * `now()` (UTC), sehingga rapat yang sedang berjalan terbaca "akan datang".
     * Rapat tanpa jam selesai dianggap berjalan paling lama DURASI_BAWAAN_JAM,
     * bukan selamanya (dulu rapat 4 Oktober masih "Berlangsung" pada 11 Oktober).
     *
     * ponytail: durasi bawaan 2 jam dipaku di kode. Jadikan kolom `durasi_menit`
     * kalau ternyata ada rapat yang memang berjalan lebih lama.
     */
    public function status(string $sekarang = ''): string
    {
        $mulai = $this->wib($this->mulai);
        if (! $mulai) {
            return 'AKAN';
        }
        $kini = $this->kiniWib($sekarang);
        $selesai = $this->wib($this->selesai) ?? $mulai->copy()->addHours(self::DURASI_BAWAAN_JAM);

        if ($kini->lt($mulai)) {
            return 'AKAN';
        }

        return $kini->lte($selesai) ? 'KINI' : 'SELESAI';
    }

    /**
     * Boleh masuk ruang SEKARANG? Pintu dibuka `JENDELA_BUKA_MENIT` sebelum mulai
     * dan ditutup saat rapat berakhir.
     *
     * Ada jeda sebelum mulai karena orang butuh waktu menyiapkan kamera, dan
     * menutupnya setelah selesai supaya ruang kosong tidak bisa dipakai rapat liar.
     */
    public const JENDELA_BUKA_MENIT = 30;

    public function bisaMasuk(string $sekarang = ''): bool
    {
        $mulai = $this->wib($this->mulai);
        if (! $mulai) {
            return false;
        }
        $kini = $this->kiniWib($sekarang);
        $selesai = $this->wib($this->selesai) ?? $mulai->copy()->addHours(self::DURASI_BAWAAN_JAM);

        return $kini->gte($mulai->copy()->subMinutes(self::JENDELA_BUKA_MENIT)) && $kini->lte($selesai);
    }

    /** Keterangan mengapa ruang masih terkunci ('' bila sudah bisa dibuka). */
    public function alasanKunci(string $sekarang = ''): string
    {
        return match ($this->status($sekarang)) {
            'KINI' => '',
            'AKAN' => 'Ruang dibuka '.self::JENDELA_BUKA_MENIT.' menit sebelum mulai ('
                .tanggal_id($this->mulai, 'd M Y H:i').').',
            default => 'Rapat ini sudah selesai'.($this->selesai ? ' ('
                .tanggal_id($this->selesai, 'd M Y H:i').')' : '').'.',
        };
    }

    /** Jumlah peserta yang sedang hadir (sesi di kolom `siaran_sesi`). */
    public function jumlah_peserta(): int
    {
        return count(array_filter(explode(',', (string) ($this->siaran_sesi ?? ''))));
    }

    /**
     * Peserta yang BENAR-BENAR sedang hadir — 0 kalau rapatnya belum mulai
     * atau sudah selesai.
     *
     * `siaran_sesi` hanya dibersihkan saat peserta keluar lewat tombol. Kalau
     * peramban ditutup paksa (atau koneksi putus), sesinya tertinggal di kolom
     * itu selamanya — akibatnya rapat yang sudah lewat sepekan masih tertulis
     * "1 peserta". Yang ditampilkan ke pengguna harus yang bisa dipercaya.
     */
    public function peserta_aktif(string $sekarang = ''): int
    {
        return $this->status($sekarang) === 'KINI' ? $this->jumlah_peserta() : 0;
    }
}

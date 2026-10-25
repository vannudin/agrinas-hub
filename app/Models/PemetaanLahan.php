<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pemetaan lahan KDKMP tingkat desa (cermin `pemetaan-lahan.portalkdkmp.id`).
 *
 * Lihat migrasi `pemetaan_lahan` untuk alasan disalin. Yang penting bagi
 * pemanggil: `telepon` = nomor Babinsa penanggung jawab (satu-satunya sumber
 * nomor itu), dan `lokasi` = "Desa, Kecamatan, Kabupaten" apa adanya dari sumber.
 */
class PemetaanLahan extends Model
{
    protected $connection = 'hub';
    protected $table = 'pemetaan_lahan';

    protected $fillable = [
        'pemetaan_id', 'nama', 'telepon', 'lokasi', 'desa', 'kecamatan', 'kabupaten',
        'provinsi', 'luas', 'potensi', 'latitude', 'longitude',
        'pembangunan_100', 'pembangunan_mulai', 'ditarik',
    ];

    protected function casts(): array
    {
        return [
            'luas' => 'float', 'latitude' => 'float', 'longitude' => 'float',
            'pembangunan_100' => 'boolean', 'pembangunan_mulai' => 'boolean',
            'ditarik' => 'datetime',
        ];
    }

    /** Status pembangunan yang bisa dibaca manusia — sumber hanya punya 2 flag. */
    public function statusPembangunan(): string
    {
        if ($this->pembangunan_100) {
            return 'Selesai 100%';
        }

        return $this->pembangunan_mulai ? 'Sedang dibangun' : 'Belum mulai';
    }

    /** Kode warna lencana: hijau = selesai, kuning = jalan, abu = belum. */
    public function statusKunci(): string
    {
        if ($this->pembangunan_100) {
            return 'SELESAI';
        }

        return $this->pembangunan_mulai ? 'DIKERJAKAN' : 'BARU';
    }

    /** Nomor apa adanya dari sumber (85… tanpa 0) → bentuk siap-telepon 085…. */
    public function teleponLokal(): string
    {
        $t = preg_replace('/\D/', '', $this->telepon);
        if ($t === '') {
            return '';
        }

        return str_starts_with($t, '62') ? '0'.substr($t, 2) : (str_starts_with($t, '0') ? $t : '0'.$t);
    }

    public function adaTitik(): bool
    {
        return $this->latitude !== null && $this->longitude !== null
            && ($this->latitude !== 0.0 || $this->longitude !== 0.0);
    }

    /**
     * Nama koperasi baku untuk desa titik ini:
     * "KOPERASI DESA/KELURAHAN MERAH PUTIH (SENDANGTIRTO)".
     *
     * Sumber tidak punya penanda desa-vs-kelurahan, jadi "DESA/KELURAHAN"
     * ditulis apa adanya — lebih jujur daripada menebak status wilayahnya.
     * Kurung di nama desa ("20 Ilir D IV (20 Ilir IV)") dibuang supaya tidak
     * bersarang di dalam kurung templat.
     */
    public function namaKoperasi(): string
    {
        $desa = trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $this->desa));

        return 'KOPERASI DESA/KELURAHAN MERAH PUTIH ('.mb_strtoupper($desa).')';
    }

    /** Tautan Google Maps ke titik lahan. Satu definisi di helper tautan_peta(). */
    public function tautanPeta(): string
    {
        return tautan_peta((float) $this->latitude, (float) $this->longitude);
    }

    /**
     * Luas + satuannya, SELALU m².
     *
     * Sumber (`luasLahan` portalkdkmp) menulis angka gaya Indonesia: titik =
     * pemisah RIBUAN, bukan desimal. Terukur pada 400 sampel: setiap nilai
     * bertitik punya tepat 3 digit di belakang titik ("1.455", "10.680"), dan
     * tidak ada satu pun berkoma atau berdesimal 1–2 digit. `1.455` karena itu
     * 1455 m² — cocok dengan aset desa yang sama di SIMKOPDES (1455 m²).
     *
     * Dulu kolom ini digelembungkan `(float)` di penarik (1.455 → 1,455) lalu
     * ditebak "di bawah 10 berarti hektar", sehingga 1455 m² tampil "1 m²" dan
     * tidak sama dengan halaman Pemetaan Lahan. Pembacaan sekarang ada di
     * `parseLuas()`, satu tempat, dan tampilan tidak menebak satuan lagi.
     */
    public function luasTeks(): string
    {
        $l = (float) $this->luas;
        if ($l <= 0) {
            return '—';
        }

        return number_format($l, 0, ',', '.').' m²';
    }

    /**
     * `luasLahan` sumber → meter persegi.
     *
     * Titik = pemisah ribuan (lihat `luasTeks()`). Titik hanya diperlakukan
     * sebagai ribuan bila di belakangnya tepat 3 digit; selain itu dianggap
     * desimal, supaya angka bergaya "1.5" dari sumber lain tidak membengkak
     * sepuluh kali.
     */
    public static function parseLuas(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }

        if (str_contains($s, ',')) {
            // "1.455,5" / "1455,5" — koma desimal, titik ribuan.
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * Bagi "Desa, Kecamatan, Kabupaten" jadi tiga bagian.
     *
     * Formatnya konsisten `Desa, Kecamatan, Kabupaten`, tetapi sebagian baris
     * memuat koma di dalam nama (mis. "Lawe Desky/Deski I, Babul Makmur, Aceh
     * Tenggara" → 3 bagian, benar). Karena itu dipecah dari KANAN: dua bagian
     * terakhir selalu kecamatan & kabupaten.
     */
    public static function pecahLokasi(string $lokasi): array
    {
        $bagian = array_map('trim', explode(',', $lokasi));
        if (count($bagian) < 3) {
            return ['desa' => $bagian[0] ?? '', 'kecamatan' => '', 'kabupaten' => ''];
        }
        $kab = array_pop($bagian);
        $kec = array_pop($bagian);

        return ['desa' => implode(', ', $bagian), 'kecamatan' => $kec, 'kabupaten' => $kab];
    }

    /**
     * Nama desa & kecamatan dari teks wilayah/alamat bebas.
     *
     * Dua bentuk yang benar-benar ada di basis data:
     *   Hub    : "Cibeber, Sukagumiwang, KAB. INDRAMAYU"      (wilayah)
     *   Hub    : "Desa Sukamaju, Kec. Cibadak, Kab. Sukabumi" (contoh lain)
     *   SIMKOPDES: "KOPERASI DESA MERAH PUTIH CIBEBER KECAMATAN SUKAGUMIWANG"
     *
     * Karena itu bagian pertama dibersihkan dari awalan "Desa/Kel./Kelurahan"
     * dan kecamatan diambil dari bagian KEDUA yang bukan KAB/KOTA. Awalan
     * "MERAH PUTIH" dibuang lebih dulu: nama koperasi hampir selalu memuatnya,
     * dan tanpa itu desa terbaca "Merah Putih Cibeber" (terukur 0 cocok).
     *
     * @return array{desa:string, kecamatan:string}
     */
    public static function pecahWilayah(string $teks): array
    {
        $teks = trim($teks);
        if ($teks === '') {
            return ['desa' => '', 'kecamatan' => ''];
        }

        // Bentuk nama koperasi SIMKOPDES: "... MERAH PUTIH <desa> KECAMATAN <kec>".
        $s = preg_replace('/^KOPERASI\s+DESA\s+MERAH\s+PUTIH\s*/i', '', $teks) ?? $teks;
        if (preg_match('/^(.+?)\s+KECAMATAN\s+(.+?)(?:\s+KABUPATEN\b.*)?$/i', $s, $m)) {
            return ['desa' => trim($m[1]), 'kecamatan' => trim($m[2])];
        }

        $bagian = array_values(array_filter(array_map('trim', explode(',', $s)), fn ($x) => $x !== ''));
        if ($bagian === []) {
            return ['desa' => '', 'kecamatan' => ''];
        }

        $desa = preg_replace('/^(Desa|Kel\.|Kelurahan)\s+/i', '', $bagian[0]) ?? $bagian[0];
        $kec = '';
        foreach (array_slice($bagian, 1) as $b) {
            if (preg_match('/^(KAB|KOTA)\b/i', $b)) {
                break;
            }
            $kec = preg_replace('/^Kec\.?\s*/i', '', $b) ?? $b;
            break;
        }

        return ['desa' => trim($desa), 'kecamatan' => trim($kec)];
    }
}

<?php

namespace App\Support;

/**
 * Lingkup wilayah untuk peran PIC (penanggung jawab wilayah).
 *
 * Satu PIC memegang SATU wilayah. Jabodetabek berdiri sendiri walaupun
 * tersebar di tiga provinsi (DKI Jakarta, sebagian Jawa Barat, sebagian
 * Banten) — karena di lapangan pengelolaannya memang satu meja. Karena itu
 * Jawa Barat & Banten tetap punya PIC sendiri untuk daerah di luar
 * Jabodetabek.
 *
 * Pencocokan memakai NAMA provinsi dari SIMKOPDES (bukan kode BPS) supaya
 * koperasi contoh yang tidak punya kode wilayah ikut terbaca lewat teks
 * `wilayah`. Nama provinsi dinormalkan dulu — SIMKOPDES menulis beberapa
 * wilayah dengan awalan berbeda (DKI JAKARTA, DAERAH ISTIMEWA YOGYAKARTA).
 */
final class Wilayah
{
    public const JABODETABEK = 'JABODETABEK';

    /**
     * Lingkup pengawasan tingkat PUSAT (nasional) — nilai `users.wilayah` untuk
     * TNI pusat. Provinsi memakai kunci PILIHAN; selain itu diperlakukan sebagai
     * nama kabupaten/kota (paling rendah kabupaten).
     */
    public const PUSAT = 'PUSAT';

    /** Kabupaten/kota yang masuk lingkup Jabodetabek (kata kunci di kolom wilayah). */
    public const KABUPATEN_JABODETABEK = ['BEKASI', 'BOGOR', 'DEPOK', 'TANGERANG', 'JAKARTA'];

    /** Pilihan wilayah: kunci = nilai tersimpan di users.wilayah. */
    public const PILIHAN = [
        self::JABODETABEK => 'Jabodetabek (DKI Jakarta, Bogor, Depok, Tangerang, Bekasi)',
        'ACEH' => 'Aceh',
        'SUMATERA UTARA' => 'Sumatera Utara',
        'SUMATERA BARAT' => 'Sumatera Barat',
        'RIAU' => 'Riau',
        'KEPULAUAN RIAU' => 'Kepulauan Riau',
        'JAMBI' => 'Jambi',
        'BENGKULU' => 'Bengkulu',
        'SUMATERA SELATAN' => 'Sumatera Selatan',
        'KEPULAUAN BANGKA BELITUNG' => 'Kepulauan Bangka Belitung',
        'LAMPUNG' => 'Lampung',
        'BANTEN' => 'Banten (luar Tangerang)',
        'JAWA BARAT' => 'Jawa Barat (luar Jabodetabek)',
        'JAWA TENGAH' => 'Jawa Tengah',
        'YOGYAKARTA' => 'DI Yogyakarta',
        'JAWA TIMUR' => 'Jawa Timur',
        'BALI' => 'Bali',
        'NUSA TENGGARA BARAT' => 'Nusa Tenggara Barat',
        'NUSA TENGGARA TIMUR' => 'Nusa Tenggara Timur',
        'KALIMANTAN BARAT' => 'Kalimantan Barat',
        'KALIMANTAN TENGAH' => 'Kalimantan Tengah',
        'KALIMANTAN SELATAN' => 'Kalimantan Selatan',
        'KALIMANTAN TIMUR' => 'Kalimantan Timur',
        'KALIMANTAN UTARA' => 'Kalimantan Utara',
        'SULAWESI UTARA' => 'Sulawesi Utara',
        'GORONTALO' => 'Gorontalo',
        'SULAWESI TENGAH' => 'Sulawesi Tengah',
        'SULAWESI BARAT' => 'Sulawesi Barat',
        'SULAWESI SELATAN' => 'Sulawesi Selatan',
        'SULAWESI TENGGARA' => 'Sulawesi Tenggara',
        'MALUKU' => 'Maluku',
        'MALUKU UTARA' => 'Maluku Utara',
        'PAPUA' => 'Papua',
        'PAPUA BARAT' => 'Papua Barat',
        'PAPUA SELATAN' => 'Papua Selatan',
        'PAPUA TENGAH' => 'Papua Tengah',
        'PAPUA PEGUNUNGAN' => 'Papua Pegunungan',
        'PAPUA BARAT DAYA' => 'Papua Barat Daya',
    ];

    /** Buang awalan yang berubah-ubah supaya "DKI JAKARTA" == "JAKARTA". */
    public static function normal(string $teks): string
    {
        $t = mb_strtoupper(trim($teks));
        $t = str_replace(['PROVINSI', 'DAERAH ISTIMEWA', 'DKI', 'KEP.'], ' ', $t);
        $t = trim((string) preg_replace('/\s+/', ' ', $t));

        // "DI YOGYAKARTA" / "D.I. YOGYAKARTA" -> "YOGYAKARTA"
        return (string) preg_replace('/^(D[.\s]*I[.\s]+)+/', '', $t);
    }

    /**
     * Constraint koperasi milik SATU wilayah PIC.
     *
     * Dipakai sebagai `where` bersarang pada query Koperasi; kolom yang dibaca
     * `koperasi.provinsi` (hasil tarik SIMKOPDES) dan `koperasi.wilayah`.
     *
     * Nama kolom bisa diganti karena aturan yang SAMA juga dipakai untuk tabel
     * yang menyimpan provinsi/wilayahnya sendiri (mis. `kontrak`, yang menyalin
     * penempatan dari SIMKOPDES dan tidak selalu punya baris `koperasi` di Hub).
     */
    public static function saring(string $wilayah, string $kolProvinsi = 'koperasi.provinsi',
        string $kolWilayah = 'koperasi.wilayah'): \Closure
    {
        $w = self::normal($wilayah);
        $kabupaten = self::KABUPATEN_JABODETABEK;

        return function ($q) use ($w, $kabupaten, $kolProvinsi, $kolWilayah) {
            $cocokKabupaten = function ($sub) use ($kabupaten, $kolWilayah) {
                foreach ($kabupaten as $k) {
                    $sub->orWhere($kolWilayah, 'like', "%{$k}%");
                }
            };

            if ($w === self::JABODETABEK) {
                $q->where(function ($sub) use ($kolProvinsi, $cocokKabupaten) {
                    // Seluruh DKI Jakarta + kabupaten/kota penyangga di Jabar & Banten.
                    $sub->where($kolProvinsi, 'JAKARTA')
                        ->orWhere(function ($x) use ($kolProvinsi, $cocokKabupaten) {
                            $x->whereIn($kolProvinsi, ['JAWA BARAT', 'BANTEN'])
                                ->where(function ($y) use ($cocokKabupaten) { $cocokKabupaten($y); });
                        });
                });

                return;
            }

            if ($w === 'JAWA BARAT' || $w === 'BANTEN') {
                // Sisa provinsi ini setelah Jabodetabek diambil.
                $q->where($kolProvinsi, $w)
                    ->where(function ($sub) use ($kabupaten, $kolWilayah) {
                        $sub->where($kolWilayah, 'not like', '%'.$kabupaten[0].'%');
                        foreach (array_slice($kabupaten, 1) as $k) {
                            $sub->where($kolWilayah, 'not like', "%{$k}%");
                        }
                    });

                return;
            }

            $q->where($kolProvinsi, $w);
        };
    }

    /**
     * Lingkup PENGAWASAN (TNI & Dinas): PUSAT = tanpa pagar, provinsi = aturan
     * `saring()`, selain itu nama kabupaten/kota yang dicocokkan ke kolom
     * wilayah (nama kabupaten tidak pernah muncul di kolom provinsi).
     *
     * Mengembalikan `null` untuk lingkup nasional supaya pemanggil bisa membedakan
     * "tanpa pagar" dari "pagar yang kebetulan kosong".
     */
    public static function saringLingkup(string $wilayah, string $kolProvinsi = 'koperasi.provinsi',
        string $kolWilayah = 'koperasi.wilayah'): ?\Closure
    {
        $w = trim($wilayah);
        if ($w === '' || $w === self::PUSAT) {
            return null;
        }

        if (self::sah($w)) {
            return self::saring($w, $kolProvinsi, $kolWilayah);
        }

        $kab = mb_strtoupper($w);

        return fn ($q) => $q->where($kolWilayah, 'like', "%{$kab}%");
    }

    /** Label enak dibaca untuk satu nilai wilayah. */
    /**
     * Label ringkas untuk lencana/tabel. Kurung penjelas dibuang — mis.
     * "Jabodetabek (DKI Jakarta, Bogor, Depok, Tangerang, Bekasi)" → "Jabodetabek".
     * Teks penuhnya 347px dan, digabung `white-space: nowrap` milik `.lencana`,
     * memaksa kolom Wilayah jadi 422px (39% lebar tabel) untuk tanda kurung.
     * Penjelasannya tetap ada di dropdown pemilih wilayah.
     */
    public static function pendek(string $wilayah): string
    {
        return trim(explode(' (', self::label($wilayah))[0]);
    }

    public static function label(string $wilayah): string
    {
        return self::PILIHAN[$wilayah] ?? $wilayah;
    }

    /** Wilayah sah? Dipakai validasi command & form. */
    public static function sah(string $wilayah): bool
    {
        return isset(self::PILIHAN[$wilayah]);
    }

    /**
     * Tebak wilayah PIC dari nama provinsi SIMKOPDES — hanya untuk mengisi
     * bawaan form, BUKAN untuk mengambil keputusan izin.
     */
    public static function dariProvinsi(string $provinsi): string
    {
        $p = self::normal($provinsi);

        return $p === 'JAKARTA' ? self::JABODETABEK : (self::sah($p) ? $p : '');
    }

    /**
     * Lingkup wilayah yang MEMEGANG satu koperasi — pasangan `saring()`.
     *
     * Dipakai untuk MENAMPILKAN kolom Wilayah. Kolom harus memakai aturan yang
     * sama dengan filternya: kalau tabel menampilkan `koperasi.wilayah` (nama
     * desa/kecamatan) sementara saringan bekerja atas `provinsi`, pengguna
     * menyaring "Bali" lalu tidak melihat kata "Bali" di satu baris pun —
     * saringan benar, tampilannya yang membohongi.
     *
     * Kabupaten/kota penyangga di Jabar & Banten masuk JABODETABEK, sesuai
     * pemetaan `saring()`.
     */
    public static function lingkup(string $provinsi, string $wilayah = ''): string
    {
        $p = self::normal($provinsi);
        if ($p === 'JAKARTA') {
            return self::JABODETABEK;
        }

        if (($p === 'JAWA BARAT' || $p === 'BANTEN') && $wilayah !== '') {
            $w = mb_strtoupper($wilayah);
            foreach (self::KABUPATEN_JABODETABEK as $k) {
                if (str_contains($w, $k)) {
                    return self::JABODETABEK;
                }
            }
        }

        return self::sah($p) ? $p : '';
    }
}

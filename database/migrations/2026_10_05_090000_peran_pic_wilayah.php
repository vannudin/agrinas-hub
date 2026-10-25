<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Peran PIC (penanggung jawab wilayah) + penyimpanan provinsi.
 *
 * - `users.wilayah`  : lingkup satu PIC. Nilainya kunci `App\Support\Wilayah::PILIHAN`
 *                      (JABODETABEK berdiri sendiri; Jabar/Banten untuk luar Jabodetabek).
 * - `koperasi.provinsi`: nama provinsi apa adanya dari SIMKOPDES (`province_name`),
 *                      supaya penyaringan wilayah tidak perlu menebak dari teks alamat.
 *
 * Sekaligus mengisi kolom baru dari data yang sudah ada, jadi tidak perlu tombol
 * "tarik ulang" hanya untuk membuat PIС bisa melihat koperasinya.
 */
return new class extends Migration
{
    /** Wilayah ≠ Jabodetabek di Jawa Barat/Banten: kurangi daftar kabupaten penyangga. */
    private const KABUPATEN_JABODETABEK = ['BEKASI', 'BOGOR', 'DEPOK', 'TANGERANG', 'JAKARTA'];

    public function up(): void
    {
        $s = Schema::connection('hub');

        if ($s->hasTable('users') && ! $s->hasColumn('users', 'wilayah')) {
            $s->table('users', function (Blueprint $t) {
                $t->string('wilayah', 40)->default('')->index();
            });
        }

        if ($s->hasTable('koperasi') && ! $s->hasColumn('koperasi', 'provinsi')) {
            $s->table('koperasi', function (Blueprint $t) {
                $t->string('provinsi', 60)->default('')->index();
            });
        }

        $this->isiProvinsi();
        $this->isiWilayahPic();
    }

    public function down(): void
    {
        $s = Schema::connection('hub');

        foreach ([['koperasi', 'provinsi'], ['users', 'wilayah']] as [$tabel, $kolom]) {
            if ($s->hasTable($tabel) && $s->hasColumn($tabel, $kolom)) {
                $s->table($tabel, fn (Blueprint $t) => $t->dropColumn($kolom));
            }
        }
    }

    /** Isi koperasi.provinsi dari kolom `wilayah` yang sudah ada (kabupaten/kota terakhir). */
    private function isiProvinsi(): void
    {
        $kabupaten = DB::connection('hub')->table('koperasi')->select('id', 'wilayah')->get();

        foreach ($kabupaten as $k) {
            $provinsi = $this->provinsiDari((string) $k->wilayah);
            if ($provinsi !== '') {
                DB::connection('hub')->table('koperasi')->where('id', $k->id)
                    ->update(['provinsi' => $provinsi]);
            }
        }
    }

    /**
     * Tebak provinsi dari teks wilayah. Data contoh memakai singkatan yang tidak
     * ada di daftar resmi, jadi ada tabel kecil khusus untuk itu; sisanya diambil
     * dari bagian terakhir alamat ("..., Kota Semarang" + kabupaten tak disebut).
     */
    private function provinsiDari(string $wilayah): string
    {
        $singkat = [
            'KAB. SUKABUMI' => 'JAWA BARAT', 'KAB. BOGOR' => 'JAWA BARAT',
            'KAB. BEKASI' => 'JAWA BARAT', 'KAB. NGAWI' => 'JAWA TIMUR',
            'KAB. MALANG' => 'JAWA TIMUR', 'KAB. SIDOARJO' => 'JAWA TIMUR',
            'KAB. SLEMAN' => 'YOGYAKARTA', 'KAB. LEBAK' => 'BANTEN',
            'KAB. MAMUJU' => 'SULAWESI BARAT',
        ];

        $kota = [
            'KOTA SEMARANG' => 'JAWA TENGAH', 'KOTA JAKARTA' => 'JAKARTA',
            'KOTA BANDAR LAMPUNG' => 'LAMPUNG', 'KOTA DENPASAR' => 'BALI',
            'KOTA MAKASSAR' => 'SULAWESI SELATAN',
        ];

        $t = mb_strtoupper($wilayah);
        foreach ($singkat + $kota as $petunjuk => $provinsi) {
            if (str_contains($t, $petunjuk)) {
                return $provinsi;
            }
        }

        return '';
    }

    /** PIC yang sudah ada diarahkan ke wilayah profilnya; kalau kosong, biarkan '' (admin pusat). */
    private function isiWilayahPic(): void
    {
        $ada = DB::connection('hub')->table('users')->where('aktif', 1)
            ->where('wilayah', '')->whereNotNull('simkopdes_id')->where('simkopdes_id', '>', 0)
            ->pluck('id');

        foreach ($ada as $id) {
            $provinsi = (string) DB::connection('hub')->table('profil')
                ->where('user_id', $id)->value('provinsi');
            if ($provinsi === '') {
                continue;
            }

            $w = \App\Support\Wilayah::dariProvinsi($provinsi);
            if ($w !== '') {
                DB::connection('hub')->table('users')->where('id', $id)->update(['wilayah' => $w]);
            }
        }
    }
};

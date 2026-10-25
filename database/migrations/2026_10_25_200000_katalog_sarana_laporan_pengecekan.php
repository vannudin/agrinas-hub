<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Katalog centang sarana prasarana mengikuti "Laporan Harian Pengecekan Titik
 * KDKMP" (dokumen Bos, 21 Oktober 2026) — bukan daftar 50 indikator SIMKOPDES.
 *
 * Alasan: daftar SIMKOPDES berisi pekerjaan konstruksi (III.1.1 Pekerjaan
 * Pasangan, V.1 Distribusi listrik, …) yang tidak pernah diperiksa manajer, dan
 * melewatkan barang yang justru ada di laporan: kipas angin, exhaust fan,
 * tempat sampah, peralatan kebersihan, freezer, chiller, komputer, ATK, mesin
 * presensi, alarm, brankas, loker, genset, Lark.
 *
 * Yang mencentang tetap MANAJER. Migrasi ini hanya menyiapkan daftar butirnya;
 * tidak ada satu pun yang ditandai tersedia.
 */
return new class extends Migration
{
    protected $connection = 'hub';

    /** [jenis_pengembangan, jenis_konstruksi, indikator] */
    private const KATALOG = [
        // 1. Konstruksi/struktur bangunan (kondisi dan kebersihan)
        ['1. Konstruksi Bangunan', 'A. Bangunan dan Infrastruktur Dasar', 'Bangunan gerai dan pergudangan'],
        ['1. Konstruksi Bangunan', 'A. Bangunan dan Infrastruktur Dasar', 'Instalasi dan ketersediaan listrik'],
        ['1. Konstruksi Bangunan', 'A. Bangunan dan Infrastruktur Dasar', 'Instalasi dan ketersediaan air bersih'],
        ['1. Konstruksi Bangunan', 'A. Bangunan dan Infrastruktur Dasar', 'Akses kendaraan dan bongkar muat'],
        ['1. Konstruksi Bangunan', 'B. Kenyamanan dan Kebersihan', 'AC 2 PK — 3 unit'],
        ['1. Konstruksi Bangunan', 'B. Kenyamanan dan Kebersihan', 'AC 1 PK — 2 unit'],
        ['1. Konstruksi Bangunan', 'B. Kenyamanan dan Kebersihan', 'Kipas angin jumbo — 1 unit'],
        ['1. Konstruksi Bangunan', 'B. Kenyamanan dan Kebersihan', 'Exhaust fan — 2 unit'],
        ['1. Konstruksi Bangunan', 'B. Kenyamanan dan Kebersihan', 'Tempat sampah — 1 set (3 unit)'],
        ['1. Konstruksi Bangunan', 'B. Kenyamanan dan Kebersihan', 'Peralatan kebersihan — 2 set sapu & pengki, 2 set pel lantai'],

        // 2. Kelengkapan (jenis, jumlah dan kondisi)
        ['2. Kelengkapan', 'A. Peralatan Perdagangan dan Pelayanan', 'Rak gerai'],
        ['2. Kelengkapan', 'A. Peralatan Perdagangan dan Pelayanan', 'Keranjang belanja — 10 unit'],
        ['2. Kelengkapan', 'A. Peralatan Perdagangan dan Pelayanan', 'Peralatan kasir — 1 set'],
        ['2. Kelengkapan', 'A. Peralatan Perdagangan dan Pelayanan', 'Printer — 1 unit'],
        ['2. Kelengkapan', 'A. Peralatan Perdagangan dan Pelayanan', 'Komputer All-in-One — 1 unit'],
        ['2. Kelengkapan', 'A. Peralatan Perdagangan dan Pelayanan', 'ATK berupa stempel, struk, dan label — 1 set'],
        ['2. Kelengkapan', 'B. Penyimpanan dan Pengendalian Mutu', 'Palet — 10 unit'],
        ['2. Kelengkapan', 'B. Penyimpanan dan Pengendalian Mutu', 'Freezer — 2 unit'],
        ['2. Kelengkapan', 'B. Penyimpanan dan Pengendalian Mutu', 'Chiller — 2 unit'],
        ['2. Kelengkapan', 'C. Kendaraan Distribusi dan Logistik', 'Truk 6 ban — 1 unit'],
        ['2. Kelengkapan', 'C. Kendaraan Distribusi dan Logistik', 'Pikap 4×4 — 1 unit'],
        ['2. Kelengkapan', 'C. Kendaraan Distribusi dan Logistik', 'Sepeda motor bak roda tiga — 2 unit'],
        ['2. Kelengkapan', 'D. Digitalisasi dan Sistem Informasi', 'Perangkat lunak kasir/POS — 1 paket'],
        ['2. Kelengkapan', 'D. Digitalisasi dan Sistem Informasi', 'Internet LTE atau Starlink (wilayah 3T) — 1 unit'],
        ['2. Kelengkapan', 'D. Digitalisasi dan Sistem Informasi', 'Mesin presensi — 1 unit'],
        ['2. Kelengkapan', 'D. Digitalisasi dan Sistem Informasi', 'Sistem komunikasi operasional/Lark (minimal level manajer terinstal di HP)'],
        ['2. Kelengkapan', 'E. Keamanan dan Keselamatan', 'APAR — 4 unit (2 unit 3 kg, 2 unit 9 kg)'],
        ['2. Kelengkapan', 'E. Keamanan dan Keselamatan', 'CCTV — 8 titik beserta perangkat penyimpanan'],
        ['2. Kelengkapan', 'E. Keamanan dan Keselamatan', 'Alarm keamanan — 1 set'],
        ['2. Kelengkapan', 'E. Keamanan dan Keselamatan', 'Brankas — 1 unit'],
        ['2. Kelengkapan', 'E. Keamanan dan Keselamatan', 'Loker/lemari — 1 unit'],
        ['2. Kelengkapan', 'E. Keamanan dan Keselamatan', 'Genset — 1 unit'],

        // 3. Awak KDKMP (manajer dan pegawai)
        ['3. Awak KDKMP', 'A. SDM dan Kelangsungan Operasional', 'Seragam petugas — 18 potong (1 kemeja dan 2 kaos polo per orang)'],
    ];

    public function up(): void
    {
        $db = DB::connection($this->connection);

        $db->transaction(function () use ($db) {
            // Katalog SIMKOPDES diganti. Centang yang menunjuk baris lama jadi
            // yatim (SQLite tanpa FK aktif) → dibuang, supaya angka "tersedia"
            // di ringkasan tidak menghitung indikator yang sudah tidak ada.
            $db->table('sarana_indikator')->whereNotNull('indikator_simkopdes_id')->delete();
            $db->table('koperasi_sarana_centang')
                ->whereNotIn('sarana_indikator_id', $db->table('sarana_indikator')->select('id'))
                ->delete();

            $ada = array_flip($db->table('sarana_indikator')->pluck('indikator')->all());
            $kini = now();

            foreach (self::KATALOG as [$pengembangan, $konstruksi, $indikator]) {
                if (isset($ada[$indikator])) {
                    continue;
                }

                $db->table('sarana_indikator')->insert([
                    'indikator_simkopdes_id' => null, // butir laporan pengecekan, bukan id SIMKOPDES
                    'jenis_pengembangan' => $pengembangan,
                    'jenis_konstruksi' => $konstruksi,
                    'indikator' => $indikator,
                    'keterangan' => null,
                    'created_at' => $kini,
                    'updated_at' => $kini,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Katalog SIMKOPDES lama tidak dipulihkan: sumbernya (tarik SIMKOPDES)
        // sudah diputus dan isinya pekerjaan konstruksi, bukan barang periksa.
        DB::connection($this->connection)->table('sarana_indikator')
            ->whereNull('indikator_simkopdes_id')->delete();
    }
};

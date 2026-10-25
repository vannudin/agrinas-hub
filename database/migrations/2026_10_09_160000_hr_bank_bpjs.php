<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data payroll pada lembar kepegawaian: rekening bank + BPJS.
 *
 * Datanya SUDAH menarik diri saat pendamping login SIMKOPDES (tersimpan di
 * `profil.profil` sebagai JSON: `account_banks`, `insurances`), tetapi
 * terkubur di blob JSON — HR tidak bisa menyaring, memaginasi, atau
 * mengekspornya. Kolom ringkas di bawah membuat data itu benar-benar dipakai,
 * tanpa satu pun angka gaji: Hub tetap TIDAK menyimpan nominal penggajian.
 *
 * Backfill dijalankan sekali di sini dari JSON yang sudah ada, jadi tidak ada
 * panggilan API baru dan tidak ada data yang dikarang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hub')->table('sdm_pegawai', function ($t) {
            // Rekening penggajian — dari `account_banks` (satu baris terisi).
            $t->string('bank_nama', 60)->nullable();
            $t->string('bank_nomor', 40)->nullable();
            $t->string('bank_pemilik', 120)->nullable();
            $t->string('bank_cabang', 120)->nullable();
            $t->string('bank_berkas')->nullable();

            // BPJS — dari `insurances` (Health = Kesehatan; Employment = 6 program).
            $t->string('bpjs_kesehatan', 60)->nullable();
            $t->string('bpjs_bpjs_ketenagakerjaan', 60)->nullable();
            $t->string('bpjs_status', 40)->nullable();
            $t->string('bpjs_program')->nullable();
            $t->string('nik_bpjs', 32)->nullable();

            $t->string('data_bank_pada', 25)->nullable();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::connection('hub')->table('sdm_pegawai', function ($t) {
            $t->dropColumn(['bank_nama', 'bank_nomor', 'bank_pemilik', 'bank_cabang',
                'bank_berkas', 'bpjs_kesehatan', 'bpjs_bpjs_ketenagakerjaan', 'bpjs_status',
                'bpjs_program', 'nik_bpjs', 'data_bank_pada']);
        });
    }

    /**
     * Isi kolom ringkas dari `profil.profil` yang sudah tersimpan. Bentuknya
     * berbeda antar-baris (ada yang `insurances` kosong, ada yang rekeningnya
     * null), jadi TIDAK ada nilai yang ditebak: yang kosong dibiarkan kosong.
     */
    private function backfill(): void
    {
        $sekarang = now('Asia/Jakarta')->toDateTimeString();

        foreach (DB::connection('hub')->table('profil')->whereNotNull('profil')->get(['user_id', 'profil']) as $r) {
            $p = json_decode((string) $r->profil, true);
            if (! is_array($p)) {
                continue;
            }

            // Rekening: ambil baris PERTAMA yang benar-benar punya nomor.
            $bank = null;
            foreach ((array) ($p['account_banks'] ?? []) as $b) {
                if (is_array($b) && trim((string) ($b['account_number'] ?? '')) !== '') {
                    $bank = $b;
                    break;
                }
            }

            $kes = null;      // BPJS Kesehatan (insurance_type = Health)
            $kerja = [];      // BPJS Ketenagakerjaan — satu baris per program
            $program = [];
            $status = '';
            $nik = '';

            foreach ((array) ($p['insurances'] ?? []) as $i) {
                if (! is_array($i)) {
                    continue;
                }
                $jenis = (string) ($i['insurance_type'] ?? '');
                $nomor = trim((string) ($i['membership_number'] ?? ''));
                $nama = (string) ($i['insurance_program']['name'] ?? '');
                $aktif = stripos((string) ($i['membership_status'] ?? ''), 'tidak') === false
                    && trim((string) ($i['membership_status'] ?? '')) !== '';

                if ($jenis === 'Health' && $kes === null && $nomor !== '') {
                    $kes = $nomor;
                    $nik = (string) ($i['nik'] ?? '');
                    $status = (string) ($i['membership_status'] ?? '');
                } elseif ($jenis === 'Employment' && $nomor !== '') {
                    if ($aktif && $nama !== '') {
                        $program[$nama] = true;
                    }
                    // Satu nomor keanggotaan dipakai seluruh program; simpan yang aktif.
                    if ($aktif || $kerja === []) {
                        $kerja[] = $nomor;
                    }
                }
            }

            $isi = [
                'bank_nama' => $bank ? mb_substr(trim((string) ($bank['bank_name'] ?? '')), 0, 60) : null,
                'bank_nomor' => $bank ? mb_substr(trim((string) ($bank['account_number'] ?? '')), 0, 40) : null,
                'bank_pemilik' => $bank ? mb_substr(trim((string) ($bank['account_name'] ?? '')), 0, 120) : null,
                'bank_cabang' => $bank ? mb_substr(trim((string) ($bank['branch_name'] ?? '')), 0, 120) : null,
                'bank_berkas' => $bank ? (string) ($bank['account_book_attachment_file'] ?? '') : null,
                'bpjs_kesehatan' => $kes ? mb_substr($kes, 0, 60) : null,
                'bpjs_bpjs_ketenagakerjaan' => $kerja !== [] ? mb_substr((string) end($kerja), 0, 60) : null,
                'bpjs_status' => $status !== '' ? mb_substr($status, 0, 40) : null,
                'bpjs_program' => $program !== [] ? implode(', ', array_keys($program)) : null,
                'nik_bpjs' => $nik !== '' ? mb_substr($nik, 0, 32) : null,
                'data_bank_pada' => $sekarang,
            ];

            // Hanya sentuh baris yang memang punya setidaknya satu data.
            if (array_filter($isi) === []) {
                continue;
            }

            $ada = DB::connection('hub')->table('sdm_pegawai')->where('user_id', $r->user_id)->exists();
            if ($ada) {
                DB::connection('hub')->table('sdm_pegawai')->where('user_id', $r->user_id)->update($isi);
            } else {
                DB::connection('hub')->table('sdm_pegawai')->insert(
                    ['user_id' => $r->user_id, 'dibuat' => $sekarang] + $isi
                );
            }
        }
    }
};

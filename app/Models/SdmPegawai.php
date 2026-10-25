<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Lembar kepegawaian satu orang: siklus kerja, atasan, penilaian kinerja
 * berperiode, rujukan slip, dan hasil rekrutmen.
 *
 * `nilai` dan angka penggajian SENGAJA tidak pernah dihitung di sini:
 * penilaian dicatat apa adanya (angka + siapa yang menilai + kapan), dan
 * penggajian hanya berupa rujukan berkas. Hub bukan sistem HRIS.
 */
class SdmPegawai extends Model
{
    public const STATUS = ['AKTIF', 'CUTI', 'NONAKTIF'];
    public const STATUS_LABEL = ['AKTIF' => 'Aktif', 'CUTI' => 'Cuti', 'NONAKTIF' => 'Nonaktif'];
    public const REKRUT = ['DITERIMA', 'DITOLAK', 'TARIK_DIRI'];

    protected $connection = 'hub';
    protected $table = 'sdm_pegawai';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'status', 'tanggal_awal', 'tanggal_akhir', 'jabatan', 'unit', 'atasan_id',
        'nilai', 'periode_nilai', 'catatan_nilai', 'nilai_oleh', 'nilai_pada',
        'slip_nomor', 'slip_berkas', 'slip_pada', 'slip_oleh',
        // Data payroll yang ditarik saat pendamping login SIMKOPDES (bukan
        // angka gaji — hanya rekening tujuan & keanggotaan BPJS).
        'bank_nama', 'bank_nomor', 'bank_pemilik', 'bank_cabang', 'bank_berkas',
        'bpjs_kesehatan', 'bpjs_bpjs_ketenagakerjaan', 'bpjs_status', 'bpjs_program', 'nik_bpjs',
        'data_bank_pada',
        'rekrut_status', 'rekrut_pada', 'rekrut_oleh',
        'catatan', 'dicatat_oleh', 'dibuat', 'diubah',
    ];

    protected function casts(): array
    {
        return ['nilai' => 'integer', 'atasan_id' => 'integer'];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function atasan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atasan_id');
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nilai_oleh');
    }

    /** Lembar untuk seorang pengguna; dibuatkan yang kosong bila belum ada. */
    public static function untuk(int $userId): self
    {
        return self::firstOrNew(['user_id' => $userId]);
    }

    /**
     * Ekstrak kolom ringkas payroll (rekening + BPJS) dari payload SIMKOPDES.
     *
     * SATU tempat untuk tiga pemanggil: simpan profil saat login (`ProfilPenarik`),
     * tombol "Tarik Ulang dari Profil", dan uji. Bentuk payload berbeda antar-baris,
     * jadi tidak ada nilai yang ditebak — yang kosong dibiarkan kosong.
     *
     * @param  array<string, mixed>  $p  payload SIMKOPDES (profil login/detail)
     * @return array<string, string>    kolom siap-update; kosong bila tak ada datanya
     */
    public static function payrollDari(array $p): array
    {
        // Rekening: baris PERTAMA yang benar-benar punya nomor. `account_banks`
        // sering memuat baris kosong (hanya lampiran tanpa nomor).
        $bank = null;
        foreach ((array) ($p['account_banks'] ?? []) as $b) {
            if (is_array($b) && trim((string) ($b['account_number'] ?? '')) !== '') {
                $bank = $b;
                break;
            }
        }

        $kes = ''; $kerja = ''; $status = ''; $nik = ''; $program = [];
        foreach ((array) ($p['insurances'] ?? []) as $i) {
            if (! is_array($i)) {
                continue;
            }
            $nomor = trim((string) ($i['membership_number'] ?? ''));
            if ($nomor === '') {
                continue;
            }
            // "Tidak Aktif" dikecualikan dari daftar program; baris kosong dianggap
            // belum tentu aktif, jadi hanya yang benar-benar "Aktif" yang dihitung.
            $aktif = trim((string) ($i['membership_status'] ?? '')) !== ''
                && stripos((string) $i['membership_status'], 'tidak') === false;

            if ((string) ($i['insurance_type'] ?? '') === 'Health' && $kes === '') {
                $kes = $nomor;
                $nik = (string) ($i['nik'] ?? '');
                $status = (string) ($i['membership_status'] ?? '');
            } elseif ((string) ($i['insurance_type'] ?? '') === 'Employment') {
                $nama = (string) ($i['insurance_program']['name'] ?? '');
                if ($aktif && $nama !== '') {
                    $program[$nama] = true;
                }
                if ($aktif || $kerja === '') {
                    $kerja = $nomor;
                }
            }
        }

        return array_filter([
            'bank_nama' => $bank ? mb_substr(trim((string) ($bank['bank_name'] ?? '')), 0, 60) : null,
            'bank_nomor' => $bank ? mb_substr(trim((string) ($bank['account_number'] ?? '')), 0, 40) : null,
            'bank_pemilik' => $bank ? mb_substr(trim((string) ($bank['account_name'] ?? '')), 0, 120) : null,
            'bank_cabang' => $bank ? mb_substr(trim((string) ($bank['branch_name'] ?? '')), 0, 120) : null,
            'bank_berkas' => $bank ? (string) ($bank['account_book_attachment_file'] ?? '') : null,
            'bpjs_kesehatan' => $kes !== '' ? mb_substr($kes, 0, 60) : null,
            'bpjs_bpjs_ketenagakerjaan' => $kerja !== '' ? mb_substr($kerja, 0, 60) : null,
            'bpjs_status' => $status !== '' ? mb_substr($status, 0, 40) : null,
            'bpjs_program' => $program !== [] ? implode(', ', array_keys($program)) : null,
            'nik_bpjs' => $nik !== '' ? mb_substr($nik, 0, 32) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Simpan kolom payroll untuk seorang pengguna dari payload SIMKOPDES.
     *
     * Dipanggil tiap kali profil SIMKOPDES disimpan (yaitu saat pendamping login),
     * jadi data bank & BPJS di meja HR selalu seumur dengan login terakhir — tanpa
     * satu panggilan API tambahan dan tanpa menyentuh data pegawai lain.
     */
    public static function simpanPayroll(int $userId, array $p): bool
    {
        $isi = self::payrollDari($p);
        if ($isi === []) {
            return false;
        }

        $isi['data_bank_pada'] = kini();

        $ada = self::where('user_id', $userId)->exists();
        if ($ada) {
            return self::where('user_id', $userId)->update($isi) > 0;
        }

        // Tanpa lembar SDM: buat selembar berisi HANYA data payroll. Status kerja
        // dibiarkan kosong — "belum punya lembar" tetap terbaca AKTIF (default).
        self::create(['user_id' => $userId, 'dibuat' => kini()] + $isi);

        return true;
    }

    /**
     * Data payroll (rekening + BPJS) sudah lengkap? Dipakai HR untuk melihat
     * siapa yang datanya belum siap — orang inilah yang gajinya tidak bisa
     * ditransfer otomatis.
     */
    public function payrollLengkap(): bool
    {
        return trim((string) $this->bank_nomor) !== ''
            && trim((string) $this->bpjs_kesehatan) !== ''
            && trim((string) $this->bpjs_bpjs_ketenagakerjaan) !== '';
    }

    /** Status efektif: akun nonaktif selalu terbaca nonaktif, apa pun isi lembar. */
    public function statusEfektif(bool $akunAktif = true): string
    {
        if (! $akunAktif) {
            return 'NONAKTIF';
        }

        return in_array($this->status, self::STATUS, true) ? $this->status : 'AKTIF';
    }

    /** Rentang masa kerja yang enak dibaca, mis. "1 Okt 2026 – sekarang". */
    public function masaKerja(): string
    {
        if ($this->tanggal_awal === '') {
            return '';
        }

        return tanggal_id($this->tanggal_awal).' – '.($this->tanggal_akhir !== '' ? tanggal_id($this->tanggal_akhir) : 'sekarang');
    }
}

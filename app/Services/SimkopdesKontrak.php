<?php

namespace App\Services;

use App\Models\Kontrak;
use App\Models\Koperasi;
use App\Models\Profil as ProfilModel;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Tarik KONTRAK + PERIODE + PENEMPATAN satu pendamping dari SIMKOPDES ke Hub.
 *
 * Kenapa harus saat pendamping login (dan bukan dari satu akun admin):
 * `regional-facilitator-period-contracts/{id}` milik orang lain → **403**, dan
 * endpoint global tidak menerima saringan per orang (diuji 9 Okt 2026:
 * `by-regional-facilitator-id` 404, `?search=` diabaikan). Hanya token
 * pendamping itu sendiri yang bisa membaca periode & kontraknya. Jadi salinan
 * di Hub diisi saat dia masuk — sama seperti absensi.
 *
 * Berkas kontraknya presigned URL GCS berumur ~1 jam, jadi yang disimpan hanya
 * nomor + link tanda tangannya; PDF diambil ulang saat diminta.
 */
class SimkopdesKontrak
{
    public function __construct(private readonly SimkopdesAbsensi $absen) {}

    /**
     * Tarik & simpan seluruh periode–penempatan–kontrak milik $u.
     *
     * @return array{periode: int, baris: int, kontrak: int}
     */
    public function tarik(User $u): array
    {
        $fac = $this->absen->facilitatorId($u);
        $token = $this->absen->token($u);
        if ($fac <= 0 || $token === '') {
            return ['periode' => 0, 'baris' => 0, 'kontrak' => 0];
        }

        $blob = (array) (ProfilModel::where('user_id', $u->id)->value('profil') ?? []);

        // 1. Kontrak semua periode: satu panggilan per periode. Token yang sama
        //    dipakai untuk menariknya; kalau mati, login ulang sekali di sini.
        $semuaKontrak = [];
        $periode = $this->periode($blob);
        $payloadPeriode = [];
        foreach ($periode as $p) {
            $pid = (int) ($p['regional_facilitator_period_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $payloadPeriode[$pid] = $p;
            $semuaKontrak[$pid] = $this->kontrakPeriode($u, $fac, $pid, $token);
        }

        // 2. Penempatan (koperasi binaan) — memuat masa tugas per periode.
        $penempatan = $this->penempatan($u, $fac, $token);

        $n = 0;
        $nKontrak = 0;
        foreach ($periode as $p) {
            $pid = (int) ($p['regional_facilitator_period_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }

            $baris = $this->koperasiPeriode($blob, $penempatan, $pid);

            // Periode tanpa penempatan tetap disimpan (kunci 0): kontraknya
            // mungkin sudah terbit sebelum koperasi ditetapkan.
            if ($baris === []) {
                $baris = [['koperasi_id' => 0, 'nama' => '', 'provinsi' => '',
                    'wilayah' => '', 'jenis' => '', 'mulai' => '', 'selesai' => '']];
            }

            foreach ($baris as $b) {
                $c = [];
                foreach ($semuaKontrak[$pid] as $x) {
                    // Satu kontrak per periode; kalau kelak ada beberapa, ambil
                    // yang paling baru (id terbesar).
                    if ((int) ($x['regional_facilitator_period_contract_id'] ?? 0)
                        > (int) ($c['regional_facilitator_period_contract_id'] ?? 0)) {
                        $c = (array) $x;
                    }
                }

                $this->simpan($u, $pid, (array) $p, $b, $c);
                $n++;
                if ($c !== []) {
                    $nKontrak++;
                }
            }
        }

        return ['periode' => count($payloadPeriode), 'baris' => $n, 'kontrak' => $nKontrak];
    }

    /** Daftar periode pendampingan dari blob profil (ikut payload login). */
    private function periode(array $blob): array
    {
        $p = (array) ($blob['regional_facilitator_periods'] ?? []);

        return array_values(array_filter($p, 'is_array'));
    }

    /**
     * URL segar berkas kontrak satu periode (presigned GCS, mati ~1 jam).
     * Dipakai rute unduhan: ambil → teruskan → tidak disimpan.
     */
    public function berkasUrl(User $u, int $pid): string
    {
        $token = $this->absen->token($u);
        foreach ($this->kontrakPeriode($u, $this->absen->facilitatorId($u), $pid, $token) as $c) {
            $url = (string) (((array) $c)['contract_file'] ?? '');
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * Kontrak satu periode. 404 = belum diterbitkan (wajar) → array kosong;
     * 401/403 = token mati (SIMKOPDES cuma mengizinkan SATU sesi per akun, jadi
     * login dari ponsel pendamping mematikan token kita) → login ulang SEKALI,
     * lalu ulangi. Tanpa ini tarikan mengembalikan kosong secara acak dan
     * halaman menampilkan "belum diterbitkan" padahal kontraknya ada.
     *
     * @return array<int, array<string, mixed>>
     */
    private function kontrakPeriode(User $u, int $fac, int $pid, string $token): array
    {
        try {
            return $this->daftar($token, "/regional-facilitator-period-contracts/by-period-id/{$pid}");
        } catch (\RuntimeException $e) {
            if (! self::tokenMati($e)) {
                return [];
            }
        }

        try {
            $token = $this->absen->segarkanToken($u);
            if ((int) $this->absen->facilitatorId($u) !== $fac) {
                return [];
            }

            return $this->daftar($token, "/regional-facilitator-period-contracts/by-period-id/{$pid}");
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Penempatan semua periode: `regional-facilitator-cooperatives/by-facilitator-id/{fac}`. */
    private function penempatan(User $u, int $fac, string $token): array
    {
        try {
            $r = $this->daftar($token, "/regional-facilitator-cooperatives/by-facilitator-id/{$fac}");
        } catch (\Throwable $e) {
            // Blob profil sudah memuat penempatan periode aktif; jalur ini hanya
            // melengkapinya untuk periode lain.
            report($e);
            $r = [];
        }

        return $r;
    }

    /**
     * Penempatan untuk satu periode: dari payload by-facilitator-id bila ada,
     * kalau tidak dari blob profil.
     *
     * @return array<int, array<string, mixed>>
     */
    private function koperasiPeriode(array $blob, array $penempatan, int $pid): array
    {
        $sumber = [];
        foreach ($penempatan as $x) {
            $x = (array) $x;
            if ((int) ($x['regional_facilitator_period_id'] ?? 0) === $pid) {
                $sumber[] = $x;
            }
        }

        if ($sumber === []) {
            foreach ((array) ($blob['regional_facilitator_cooperatives'] ?? []) as $x) {
                $x = (array) $x;
                if ((int) ($x['regional_facilitator_period_id'] ?? 0) === $pid) {
                    $sumber[] = $x;
                }
            }
        }

        return array_map(function (array $x) {
            $c = (array) ($x['cooperative'] ?? []);
            $id = (int) ($c['cooperative_id'] ?? $x['cooperative_id'] ?? 0);

            return [
                'koperasi_id' => $id,
                'nama' => (string) ($c['name'] ?? Koperasi::where('koperasi_simkopdes_id', $id)->value('nama') ?? ''),
                'provinsi' => strtoupper((string) ($c['province_name'] ?? '')),
                'wilayah' => (string) ($c['district_name'] ?? ''),
                'jenis' => (string) ($x['placement_type'] ?? ''),
                'mulai' => self::tanggal($x['placement_start_date'] ?? null),
                'selesai' => self::tanggal($x['placement_end_date'] ?? null),
            ];
        }, $sumber);
    }

    /**
     * Simpan satu baris. `updateOrCreate` memakai kunci yang sama dengan UNIQUE
     * di tabel supaya tarikan berulang tidak menumpuk baris.
     */
    private function simpan(User $u, int $pid, array $p, array $b, array $c): void
    {
        $status = (string) ($c['status'] ?? '');
        $label = (string) ($c['status_label'] ?? '');
        $kini = kini();

        $isi = [
            'periode_mulai' => (string) ($p['start_activation'] ?? ''),
            'periode_selesai' => (string) ($p['end_activation'] ?? ''),
            'periode_status' => (string) ($p['status'] ?? ''),
            'periode_program' => (string) (((array) ($p['assistance_period'] ?? []))['position']
                ?? ((array) (((array) ($p['assistance_period'] ?? []))['regional_facilitator_program'] ?? []))['name'] ?? ''),

            'penempatan_nama' => $b['nama'],
            'penempatan_provinsi' => $b['provinsi'],
            'penempatan_wilayah' => $b['wilayah'],
            'penempatan_jenis' => $b['jenis'],
            'penempatan_mulai' => $b['mulai'],
            'penempatan_selesai' => $b['selesai'],

            // Kontrak kosong DIKOSONGKAN kalau sebelumnya juga kosong; tapi kalau
            // SIMKOPDES membalas tanpa kontrak (gangguan sesaat), baris lama yang
            // sudah punya nomor tidak boleh ikut dikosongkan.
            'kontrak_status' => $status !== '' ? $status : null,
            'kontrak_label' => $label !== '' ? $label : null,
            'kontrak_nomor' => (string) ($c['contract_number'] ?? ''),
            'kontrak_komitmen' => (string) ($c['commitment_number'] ?? ''),
            'kontrak_jenis' => (string) ($c['contract_type'] ?? ''),
            'kontrak_mulai' => self::tanggal($c['effective_date'] ?? null),
            'kontrak_selesai' => self::tanggal($c['expired_date'] ?? null),
            'kontrak_ttd' => self::tanggal($c['signed_date'] ?? null),
            'kontrak_sumber' => (string) ($c['source'] ?? ''),
            'kontrak_tautan' => (string) ($c['signing_url'] ?? ''),
            'kontrak_id' => (int) ($c['regional_facilitator_period_contract_id'] ?? 0) ?: null,
            'ditarik_pada' => $kini,
            'diperbarui' => $kini,
        ];

        if ($c === []) {
            foreach (array_keys($isi) as $k) {
                if (str_starts_with($k, 'kontrak_') && $k !== 'kontrak_tautan') {
                    unset($isi[$k]);
                }
            }
        }

        $kunci = ['user_id' => $u->id, 'periode_id' => $pid,
            'penempatan_koperasi_id' => (int) $b['koperasi_id']];

        $ada = Kontrak::where($kunci)->exists();
        Kontrak::updateOrCreate($kunci, $isi + ($ada ? [] : ['sumber' => 'SIMKOPDES', 'dibuat' => $kini]));
    }

    /** Tanggal ISO → 'Y-m-d' ('' bila kosong). */
    public static function tanggal(mixed $iso): string
    {
        $t = trim((string) ($iso ?? ''));

        return $t === '' ? '' : substr($t, 0, 10);
    }

    /** Token ditolak (bukan kredensial salah)? SIMKOPDES membalas 401 atau 403. */
    private static function tokenMati(\Throwable $e): bool
    {
        $m = $e->getMessage();

        return str_contains($m, 'HTTP 401') || str_contains($m, 'HTTP 403');
    }

    /**
     * GET satu jalur dengan token yang DIBERIKAN (bukan token cache), karena
     * pemanggilnya sedang memegang token milik pendamping yang barusan login.
     *
     * SENGAJA TANPA retry(): 401/403 harus terbaca apa adanya supaya pemanggil
     * bisa memutuskan login ulang, bukan menelan galat lalu menyimpan kosong.
     */
    private function daftar(string $token, string $path): array
    {
        $r = Http::acceptJson()->timeout(30)->connectTimeout(20)
            ->withToken($token)
            ->get(config('services.simkopdes.url').$path);

        if ($r->status() === 404) {
            return []; // belum diterbitkan / tidak ada — bukan galat
        }

        if (! $r->successful()) {
            throw new \RuntimeException("SIMKOPDES {$path} menjawab HTTP {$r->status()}.");
        }

        $data = $this->buka((array) $r->json());

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /** Dekripsi `data` AES-256-CBC — protokol SIMKOPDES yang sama dengan Simkopdes.php. */
    /**
     * Dekripsi `data` AES-256-CBC — protokol SIMKOPDES yang sama dengan
     * Simkopdes.php (kunci & IV dari env).
     * Respons yang tidak terenkripsi diteruskan apa adanya.
     */
    private function buka(array $amplop): mixed
    {
        $data = $amplop['data'] ?? null;
        if (! is_string($data) || $data === '') {
            return $amplop;
        }

        $kunci = (string) config('services.simkopdes.proto_key');
        if ($kunci === '') {
            throw new \RuntimeException('SIMKOPDES_PROTO_KEY belum diisi.');
        }

        $teks = openssl_decrypt(base64_decode($data), 'aes-256-cbc',
            hash('sha256', $kunci, true), OPENSSL_RAW_DATA, (string) config('services.simkopdes.proto_iv'));

        if ($teks === false) {
            throw new \RuntimeException('Payload SIMKOPDES gagal didekripsi.');
        }

        return json_decode($teks, true, 512, JSON_THROW_ON_ERROR);
    }
}

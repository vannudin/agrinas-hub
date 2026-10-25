<?php

namespace App\Services;

use App\Models\Koperasi;
use App\Models\Profil as ProfilModel;
use App\Models\SdmPegawai;
use App\Models\User;

/**
 * Tarik profil + akun SIMKOPDES ke Hub, supaya login berikutnya ringan
 * (sandi sudah ada di Hub) dan data tetap sinkron dengan sumbernya.
 *
 * Hasilnya, untuk satu pendamping:
 *   - baris `users` (upsert by `email`): nama, koperasi tempat bertugas
 *   - baris `profil`: identitas + koperasi + blob profil lengkap SIMKOPDES
 *   - `koperasi` binaan: kode wilayah, nama, wilayah, alamat, status
 *   - `users.sandi` + `users.sandi_sumber='SIMKOPDES'`: HANYA bila
 *     `--sandi=<sandi SIMKOPDES>` diberikan. Sandi itu di-hash sekali di sini
 *     dan tidak pernah disimpan mentah.
 *
 * ponytail: tarik manual/CLI, bukan pengintai latar; `simkopdes:tarik` sudah
 * cukup sampai jumlah koperasi binaan bertambah banyak.
 */
class ProfilPenarik
{
    public function __construct(private readonly Simkopdes $simkopdes) {}

    public function terkonfigurasi(): bool
    {
        return $this->simkopdes->terkonfigurasi();
    }

    /**
     * Simpan profil pendamping dari payload login SIMKOPDES.
     *
     * Dipisah dari tarik() karena kredensialnya berbeda: tarik() memakai akun
     * yang tersimpan di config (jalur CLI/admin), sedangkan di sini datanya
     * datang dari payload login orang yang bersangkutan. Ini juga satu-satunya
     * jalan untuk pendamping SELAIN pemilik akun config: token pendamping hanya
     * boleh membaca dirinya sendiri (`/regional-facilitators/detail/{id_lain}`
     * → 403), jadi profil tiap orang harus disimpan saat dia sendiri masuk.
     *
     * Sengaja TIDAK memanggil API lagi: seluruh identitas sudah ada di payload,
     * dan token SIMKOPDES di-cache global (satu untuk semua akun) sehingga
     * panggilan `detail` di sini bisa membaca orang yang salah.
     *
     * @param  array<string, mixed>  $login  payload `user` hasil /auth/login
     */
    public function dariLogin(array $login, ?int $koperasiId = null): bool
    {
        $email = mb_strtolower(trim((string) ($login['email'] ?? '')));
        if ($email === '') {
            return false;
        }

        $u = User::where('email', $email)->first();
        if ($u === null) {
            return false;
        }

        $simId = (int) ($login['regional_facilitator']['regional_facilitator_id'] ?? 0);

        // Teruskan payload apa adanya (memuat riwayat kerja, pendidikan, bank,
        // dokumen bila sudah dilengkapi pemanggil); hanya identitas yang
        // ditegaskan di sini — jangan disusun ulang, nanti detailnya terbuang.
        $profil = $login;
        $profil['email'] = $email;
        $profil['nama'] = (string) ($login['name'] ?? $u->nama);
        $profil['niak'] = (string) ($login['niak'] ?? '');
        $profil['user_code'] = (string) ($login['user_code'] ?? '');
        $profil['telepon'] = (string) ($login['phone'] ?? '');
        $profil['peran_simkopdes'] = $this->peran($login);
        $profil['verified_email'] = (string) ($login['verified_email'] ?? '');
        $profil['simkopdes_user_id'] = $simId ?: null;

        $ringkas = ['profil' => 0];
        $this->profil($u, $profil, (int) ($koperasiId ?? 0), $ringkas);

        // Sinyalkan kalau ini baru profil ringkas (detail gagal diambil) supaya
        // pemanggil bisa mencobanya lagi di login berikutnya, bukan menyimpan
        // baris setengah jadi lalu menganggapnya selesai.
        return $ringkas['profil'] > 0 && ($login['_lengkap'] ?? true) === true;
    }

    /** Peran SIMKOPDES dari payload login (`roles[].role_position.position`). */
    private function peran(array $login): string
    {
        foreach ($login['roles'] ?? [] as $r) {
            $pos = $r['role_position']['position'] ?? null;
            if ($pos) {
                return (string) $pos;
            }
        }

        return '';
    }

    /**
     * Tautkan akun ke koperasi binaannya memakai blob profil yang sudah tersimpan
     * — TANPA menyentuh identitas profil dan TANPA memanggil SIMKOPDES.
     *
     * Dipakai jalur login lokal: akun yang sandinya sudah tersalin tidak lagi
     * memanggil SIMKOPDES, jadi pautan koperasi yang dulu gagal tidak akan pernah
     * diperbaiki sendiri. `dariLogin()` tidak boleh dipakai di sini: ia menulis
     * ulang seluruh kolom profil dari blob, dan blob lama tidak selalu memuat
     * `roles` — peran SIMKOPDES yang benar jadi tertimpa kosong.
     *
     * ponytail: hanya koperasi binaan PERTAMA, sama seperti koperasiDariPayload().
     *
     * @return int id koperasi lokal, 0 bila belum bisa dipastikan
     */
    public function tautkanKoperasi(User $u): int
    {
        if (Koperasi::where('manajer_id', $u->id)->exists()) {
            return (int) Koperasi::where('manajer_id', $u->id)->value('id');
        }

        $blob = (array) (\App\Models\Profil::where('user_id', $u->id)->value('profil') ?? []);
        if ($blob === []) {
            return 0;
        }

        $blob['email'] = (string) $u->email;
        $ringkas = ['koperasi_baru' => 0];
        $id = $this->koperasiDariPayload($u, $blob, $ringkas);

        if ($id > 0) {
            // Kolom ringkas di `profil` ikut diisi supaya halaman lain yang
            // membacanya (absensi, kontrak) melihat pautan yang sama.
            \App\Models\Profil::where('user_id', $u->id)->update([
                'koperasi_id' => $id,
                'koperasi_simkopdes_id' => (int) ($this->idKoperasiPayload($blob) ?? 0) ?: null,
            ]);
            jurnal($u->id, 'TAUT_KOPERASI', (string) $u->email, 'koperasi='.$id);
        }

        return $id;
    }

    /**
     * @param  string|null  $sandi  Sandi SIMKOPDES milik pendamping; bila diisi,
     *                              dipakai untuk menyalin sandi ke Hub sekali hash.
     * @return array<string, mixed> ringkasan
     */
    public function tarik(?string $sandi = null): array
    {
        $profilPendamping = $this->simkopdes->pendamping();
        $koperasiMentah = $this->simkopdes->koperasi();

        $ringkas = ['pendamping' => 0, 'koperasi_baru' => 0, 'koperasi_ubah' => 0,
            'koperasi_tetap' => 0, 'profil' => 0, 'sandi' => 0, 'koperasi' => []];

        // 1. Koperasi binaan dulu supaya id lokalnya sudah ada saat pautan.
        $petaKoperasi = [];
        foreach ($koperasiMentah as $baris) {
            $baris = (array) $baris;
            $c = (array) ($baris['cooperative'] ?? []);
            $idSim = (int) ($c['cooperative_id'] ?? $baris['cooperative_id'] ?? 0);
            if ($idSim <= 0) {
                continue;
            }
            // Kode wilayah tidak terbaca -> teruskan kosong; untukSumber masih
            // menemukan baris lewat id SIMKOPDES (`SIMP-{id}`) alih-alih
            // melewati koperasinya sama sekali.
            $petaKoperasi[$idSim] = $this->koperasi((string) ($this->kode($c, $idSim) ?? ''),
                $idSim, $c, $ringkas);
        }

        // 2. Akun pendamping.
        foreach ($this->pendamping($profilPendamping) as $p) {
            $ringkas['pendamping']++;
            $email = mb_strtolower(trim((string) ($p['email'] ?? '')));
            if ($email === '') {
                continue;
            }

            $koperasiSim = array_key_first((array) ($p['koperasi'] ?? [])) ?? 0;
            $koperasiId = (int) ($petaKoperasi[(int) $koperasiSim] ?? 0);
            $p['koperasi_simkopdes_id'] = (int) $koperasiSim;
            $p['_koperasi_id'] = $koperasiId;
            $u = User::where('email', $email)->first();

            $sandiHash = null;
            if ($sandi !== null && $sandi !== '') {
                $sandiHash = \App\Support\ScryptHasher::buat($sandi);
            }

            $isi = ['nama' => (string) ($p['nama'] ?? $email), 'aktif' => 1,
                'peran' => \App\Models\Domain::PERAN_MANAJER,
                'simkopdes_id' => (int) ($p['simkopdes_user_id'] ?? 0) ?: null,
                // Kunci penyambung absensi: baris absensi SIMKOPDES menyebut
                // facilitator id, sedangkan `simkopdes_id` (jalur CLI) memuat
                // id pendamping — dua nilai yang berbeda untuk akun yang sama.
                'simkopdes_facilitator_id' => (int) ($p['regional_facilitator_id'] ?? 0) ?: null,
                'koperasi_simkopdes_id' => (int) ($p['koperasi_simkopdes_id'] ?? 0) ?: null];

            if ($sandiHash !== null) {
                $isi['sandi'] = $sandiHash;
                $isi['sandi_sumber'] = 'SIMKOPDES';
                $isi['wajib_ganti'] = 0;
                $ringkas['sandi']++;
            }

            if ($u === null) {
                $u = User::create($isi + ['email' => $email, 'sandi' => $sandiHash
                    ?? \App\Support\ScryptHasher::buat(bin2hex(random_bytes(32))),
                    'wajib_ganti' => $sandiHash ? 0 : 1, 'dibuat' => kini()]);
            } else {
                // Jangan turunkan penanda wajib ganti yang sudah benar.
                if ($sandiHash === null) {
                    unset($isi['wajib_ganti']);
                }
                $u->forceFill($isi)->save();
            }

            // Pautan user <-> koperasi kanonik di Hub: koperasi.manajer_id.
            if ($koperasiId > 0) {
                $k = Koperasi::find($koperasiId);
                if ($k && ($k->manajer_id === null || (int) $k->manajer_id === (int) $u->id)) {
                    $k->forceFill(['manajer_id' => $u->id])->save();
                }
                // Wilayah PIC dibiarkan kosong sampai admin pusat menetapkannya;
                // yang diisi otomatis hanya kalau kosong, sebagai usulan awal.
                if ($k && (string) ($u->wilayah ?? '') === '') {
                    $w = \App\Support\Wilayah::dariProvinsi((string) $k->provinsi);
                    if ($w !== '') {
                        $u->forceFill(['wilayah' => $w])->save();
                    }
                }
            }

            $this->profil($u, $p, $koperasiId, $ringkas);
        }

        jurnal(null, 'SIMP_PROFIL', 'profil',
            "pendamping={$ringkas['pendamping']} koperasi_baru={$ringkas['koperasi_baru']} "
            ."sandi={$ringkas['sandi']}");

        return $ringkas;
    }

    /**
     * Akun pendamping + koperasi tempat bertugas.
     *
     * Koperasi binaan ada di `regional_facilitator_cooperatives` (plan B: dari
     * `cooperatives`), sementara `/auth/me` tidak membawanya — jadi tarikan ini
     * melengkapi sendiri, bukan menebak dari kolom `position`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendamping(array $p): array
    {
        $p = (array) $p;
        $id = (int) ($p['regional_facilitator_id'] ?? 0);
        if ($id <= 0) {
            $p['email'] = mb_strtolower(trim((string) config('services.simkopdes.email')));
            $p['nama'] = (string) ($p['name'] ?? $p['email']);

            return [$p];
        }

        $binaan = [];
        foreach ($p['regional_facilitator_cooperatives'] ?? [] as $b) {
            $b = (array) $b;
            $binaan[(int) ($b['cooperative_id'] ?? 0)] = $b;
        }
        foreach ($p['cooperatives'] ?? [] as $b) {
            $b = (array) $b;
            $idK = (int) ($b['cooperative_id'] ?? 0);
            if ($idK > 0 && ! isset($binaan[$idK])) {
                $binaan[$idK] = $b;
            }
        }

        $p['email'] = mb_strtolower(trim((string) ($p['email'] ?? config('services.simkopdes.email'))));
        $p['nama'] = (string) ($p['name'] ?? $p['nickname'] ?? $p['email']);
        $p['niak'] = (string) ($p['niak'] ?? '');
        $p['telepon'] = (string) ($p['phone'] ?? '');
        $p['alamat'] = (string) ($p['address'] ?? '');
        $p['peran_simkopdes'] = (string) ($p['position'] ?? $p['organization'] ?? '');
        // Identitas pendamping di SIMKOPDES. Payload `detail` tidak membawa
        // `user_id` (itu baru muncul di payload login), jadi yang dipakai id
        // pendamping — stabil dan memang itu kuncinya di sana.
        $p['simkopdes_user_id'] = (int) ($p['regional_facilitator_id'] ?? 0) ?: null;
        $p['koperasi'] = $binaan;
        $p['_koperasi_id'] = 0;

        return [$p];
    }

    /** Simpan satu baris `profil` (upsert by user_id) + pautan koperasinya. */
    private function profil(User $u, array $p, int $koperasiId, array &$ringkas): void
    {
        $binaan = (array) ($p['koperasi'] ?? []);
        $koperasiSim = (int) ($p['koperasi_simkopdes_id'] ?? array_key_first($binaan)
            ?? $this->idKoperasiPayload($p) ?? 0);

        // Koperasi binaan yang BELUM ada di Hub dibuat dari payload ini. Tanpa
        // ini, pendamping login hanya lewat web masuk tanpa pautan koperasi →
        // /kerja & /keuangan jatuh ke keadaan "belum ditautkan" dan tampak
        // kosong. (Koperasi hasil penarik CLI tetap dibuat lebih dulu di tarik(),
        // jadi jalur itu tidak berubah.)
        if ($koperasiId <= 0) {
            $koperasiId = $this->koperasiDariPayload($u, $p, $ringkas);
        }

        $prof = ProfilModel::where('user_id', $u->id)->first() ?? new ProfilModel(['user_id' => $u->id]);

        $isi = [
            'user_id' => $u->id,
            'sumber' => 'SIMKOPDES',
            'simkopdes_user_id' => (int) ($p['simkopdes_user_id'] ?? 0) ?: null,
            'email' => (string) ($p['email'] ?? $u->email),
            'nama' => (string) ($p['nama'] ?? $u->nama),
            'niak' => (string) ($p['niak'] ?? ''),
            'user_code' => (string) ($p['user_code'] ?? ''),
            'telepon' => (string) ($p['telepon'] ?? ''),
            'peran_simkopdes' => (string) ($p['peran_simkopdes'] ?? ''),
            'gender' => (string) ($p['gender'] ?? ''),
            'tempat_lahir' => (string) ($p['birth_place'] ?? ''),
            'tanggal_lahir' => (string) ($p['birth_date'] ?? ''),
            'agama' => (string) ($p['religion'] ?? ''),
            'status_kawin' => (string) ($p['marital_status'] ?? ''),
            'golongan_darah' => (string) ($p['blood_type'] ?? ''),
            'alamat' => (string) ($p['alamat'] ?? ''),
            'kode_pos' => (string) ($p['postal_code'] ?? ''),
            'provinsi' => (string) ($p['province']['name'] ?? $p['province_name'] ?? ''),
            'kabupaten' => (string) ($p['district']['name'] ?? $p['district_name'] ?? ''),
            'kecamatan' => (string) ($p['subdistrict']['name'] ?? $p['subdistrict_name'] ?? ''),
            'desa' => (string) ($p['village']['name'] ?? $p['village_name'] ?? ''),
            'koperasi_simkopdes_id' => $koperasiSim ?: null,
            'terverifikasi' => (string) ($p['verified_email'] ?? ''),
            'akhir_tarik' => kini(),
        ];

        // koperasi_id hanya ditulis kalau sudah diketahui. Tanpa penjagaan ini,
        // tarikan yang tidak membawa pautan (mis. dari payload login) akan
        // MENGOSONGKAN pautan yang sudah benar hasil penarik CLI.
        if ($koperasiId > 0) {
            $isi['koperasi_id'] = $koperasiId;
        }

        // Blob profil: gabung dengan yang tersimpan supaya detail (riwayat kerja,
        // dokumen) tidak hilang saat payload berikutnya hanya memuat identitas.
        $lama = (array) ($prof->profil ?? []);
        $isi['profil'] = array_replace($lama, $p);

        $prof->forceFill($isi)->save();

        // Data bank & BPJS ikut disalin ke kolom ringkas sdm_pegawai SETIAP kali
        // profil disimpan — yaitu tiap pendamping login SIMKOPDES. Inilah "sinkron
        // saat login": meja HR selalu memakai data login terakhir, tanpa panggilan
        // API tambahan (payload sudah memuat `account_banks` & `insurances`).
        // Gagal di sini TIDAK boleh menggagalkan login.
        try {
            SdmPegawai::simpanPayroll($u->id, $p);
        } catch (\Throwable $e) {
            report($e);
        }

        $ringkas['profil']++;
    }

    /**
     * Bikin/ambil baris `koperasi` dari koperasi binaan yang dibawa payload.
     *
     * Dipakai saat login web: isi payload sudah memuat `cooperatives[]` lengkap
     * (nama, alamat, wilayah) + id wilayah, jadi tak perlu API lagi. Kalau
     * koperasi itu sudah ada di Hub, hanya dipautkan — datanya tidak ditimpa.
     * Koperasi ditemukan lewat id SIMKOPDES dulu, baru kode wilayah: Hub bisa
     * memuat beberapa koperasi di satu kabupaten.
     *
     * ponytail: hanya koperasi binaan PERTAMA yang dipautkan. Satu pendamping
     * bisa membina beberapa koperasi; Hub sendiri masih satu-koperasi-per-manajer
     * (koperasi.manajer_id), jadi memperbanyaknya berarti mengubah model datanya
     * juga. Tambah tabel pautan manajer↔koperasi kalau itu memang dibutuhkan.
     *
     * @return int id koperasi lokal, 0 bila tidak bisa dipastikan
     */
    private function koperasiDariPayload(User $u, array $p, array &$ringkas): int
    {
        $c = $this->koperasiPayload($p);

        $idSim = (int) ($c['cooperative_id'] ?? 0);
        if ($idSim <= 0) {
            return 0;
        }

        // Sudah dipautkan sebelumnya? Pakai itu — sekaligus mengisi id SIMKOPDES
        // baris lama. Menghindari panggilan API tiap login.
        $milik = Koperasi::where('manajer_id', $u->id)->first();
        if ($milik !== null) {
            if ($milik->koperasi_simkopdes_id === null) {
                $milik->forceFill(['koperasi_simkopdes_id' => $idSim])->save();
            }

            return (int) $milik->id;
        }

        // Kunci = id koperasi SIMKOPDES (lihat Koperasi::untukSumber). Kode
        // wilayah hanya dipakai untuk baris lama yang belum punya id itu; kalau
        // kode tak terbaca, baris tetap dibuat dengan kode `SIMP-{id}` — nanti
        // ditemukan lagi lewat id, jadi tidak ada koperasi kembar.
        $ada = Koperasi::untukSumber($idSim, (string) ($this->kode($c, $idSim) ?? ''));

        if (! $ada->exists) {
            $wilayah = trim(implode(', ', array_filter([
                (string) ($c['village_name'] ?? ''), (string) ($c['subdistrict_name'] ?? ''),
                (string) ($c['district_name'] ?? ''),
            ])));

            $ada->forceFill([
                'nama' => trim((string) ($c['name'] ?? '')) ?: 'Koperasi',
                'wilayah' => $wilayah,
                // Nama provinsi disimpan terpisah: dari sinilah PIC wilayah
                // ditentukan, dan menebaknya dari teks alamat tidak andal.
                'provinsi' => \App\Support\Wilayah::normal((string) ($c['province_name'] ?? '')),
                'alamat' => trim((string) ($c['address'] ?? '')),
                'status' => 'AKTIF',
                'diperbarui' => kini(),
            ])->save();
            $ringkas['koperasi_baru'] = ($ringkas['koperasi_baru'] ?? 0) + 1;
        }

        // Pautan kanonik user↔koperasi = koperasi.manajer_id. Koperasi yang sudah
        // punya manajer lain TIDAK diambil alih.
        if ($ada->manajer_id === null || (int) $ada->manajer_id === (int) $u->id) {
            $ada->forceFill(['manajer_id' => $u->id])->save();
        } elseif ((int) $ada->manajer_id !== (int) $u->id) {
            return 0; // koperasi ini milik orang lain — jangan tautkan
        }

        return (int) $ada->id;
    }

    /** Koperasi binaan pertama dari payload (plan B: `cooperatives[]`). */
    private function koperasiPayload(array $p): array
    {
        foreach ((array) ($p['cooperatives'] ?? []) as $baris) {
            if (is_array($baris) && (int) ($baris['cooperative_id'] ?? 0) > 0) {
                return $baris;
            }
        }

        return [];
    }

    /** Id koperasi SIMKOPDES dari kunci `koperasi` maupun blok `cooperatives[]`. */
    private function idKoperasiPayload(array $p): ?int
    {
        $id = array_key_first((array) ($p['koperasi'] ?? []));
        if ($id) {
            return (int) $id;
        }

        return (int) ($this->koperasiPayload($p)['cooperative_id'] ?? 0) ?: null;
    }

    /** Upsert koperasi binaan; kode wilayah dipakai sebagai kunci (stabil). */
    private function kode(array $c, int $idSim): ?string
    {
        $idKab = (int) ($c['districtId'] ?? 0);
        if ($idKab <= 0) {
            return 'SIMP-'.$idSim;
        }

        try {
            $kode = (string) ($this->simkopdes->kodeWilayah('district', $idKab) ?? '');
        } catch (\Throwable $e) {
            // Kode wilayah tidak bisa dipastikan: JANGAN bikin baris dengan kunci
            // tebakan — nanti muncul koperasi kembar begitu kode aslinya terbaca.
            // Pemanggil melewatinya dan mencoba lagi di tarikan berikutnya.
            report($e);

            return null;
        }

        return $kode !== '' ? $kode : 'SIMP-'.$idSim;
    }

    /**
     * Upsert koperasi binaan. Kuncinya id SIMKOPDES (lihat Koperasi::untukSumber),
     * BUKAN kode kabupaten: satu kabupaten bisa memuat beberapa koperasi dan
     * kolom `kode` bersifat UNIQUE.
     */
    private function koperasi(string $kode, int $idSim, array $c, array &$ringkas): int
    {
        $wilayah = trim(implode(', ', array_filter([
            (string) ($c['village_name'] ?? ''), (string) ($c['subdistrict_name'] ?? ''),
            (string) ($c['district_name'] ?? ''),
        ])));

        $ada = Koperasi::untukSumber($idSim, $kode);
        $isi = [
            'nama' => trim((string) ($c['name'] ?? '')) ?: ($ada->nama ?? 'Koperasi'),
            'wilayah' => $wilayah !== '' ? $wilayah : ($ada->wilayah ?? ''),
            'alamat' => trim((string) ($c['address'] ?? '')) ?: ($ada->alamat ?? ''),
            'status' => 'AKTIF',
        ];

        if (! $ada->exists) {
            $ada->forceFill($isi + ['versi' => 1, 'diperbarui' => kini()])->save();
            $ringkas['koperasi_baru']++;
        } elseif (array_intersect_assoc($isi, $ada->only(array_keys($isi))) === $isi) {
            $ringkas['koperasi_tetap']++;
        } else {
            $ada->forceFill($isi + ['versi' => (int) $ada->versi + 1, 'diperbarui' => kini()])->save();
            $ringkas['koperasi_ubah']++;
        }

        $ringkas['koperasi'][] = ['kode' => $ada->kode, 'nama' => $isi['nama'], 'wilayah' => $isi['wilayah']];

        return (int) $ada->id;
    }
}

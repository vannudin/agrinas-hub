<?php

namespace App\Http\Controllers;

use App\Models\Koperasi;
use App\Services\Simkopdes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tarik data koperasi binaan dari API SIMKOPDES ke hub.
 *
 * Penarikannya MANUAL (tombol "Tarik data"), bukan latar belakang: satu akun
 * pendamping hanya membina 1-3 koperasi, jadi sinkronisasi terjadwal belum
 * dibutuhkan — dan kredensialnya tidak perlu dibaca proses otomatis.
 *
 * ponytail: tarik manual dulu; cron 1×/hari kalau jumlah koperasi binaan
 * bertambah banyak atau data SIMKOPDES mulai dipakai lintas halaman.
 */
class SimkopdesController extends Controller
{
    public function __construct(private readonly Simkopdes $simkopdes) {}

    public function halaman()
    {
        $aktif = 'simkopdes';
        $siap = $this->simkopdes->terkonfigurasi();
        $lok = [];
        $fac = null;

        if ($siap) {
            // Id yang DITAMPILKAN harus milik akun di .env, bukan angka tetap:
            // kalau keduanya tidak sinkron, di sinilah bedanya langsung terlihat
            // sebelum menekan tombol tarik.
            try {
                $fac = $this->simkopdes->facilitatorId();
            } catch (\Throwable) {
                $fac = null;
            }

            // Daftar yang sudah ada di basis data lokal (belum tentu sama dengan
            // SIMKOPDES — itu gunanya tombol tarik).
            $lok = Koperasi::query()
                ->select('kode', 'nama', 'wilayah', 'alamat', 'status', 'diperbarui')
                ->orderBy('nama')->get()->all();
        }

        return view('simkopdes.index', [
            'aktif' => $aktif,
            'siap' => $siap,
            'facilitator' => $fac,
            'email' => config('services.simkopdes.email'),
            'koperasi' => $lok,
        ]);
    }

    /** Tombol "Tarik data": panggil API, jadikan baris siap-tulis. */
    public function tarik(Request $request)
    {
        if (! $this->simkopdes->terkonfigurasi()) {
            return back()->with('galat', 'SIMKOPDES belum diatur. Isi SIMKOPDES_PASSWORD (dan '
                .'SIMKOPDES_FACILITATOR_ID) di .env, lalu jalankan php artisan config:clear.');
        }

        try {
            $baris = $this->baris($this->simkopdes->koperasi());
        } catch (\Throwable $e) {
            Log::warning('Simkopdes tarik gagal: '.$e->getMessage());

            return back()->with('galat', 'Gagal menarik data SIMKOPDES: '.$e->getMessage());
        }

        $terapkan = $request->boolean('terapkan');
        $ringkas = $this->simpan($baris, $terapkan);
        $ringkas['token'] = $this->potongToken($this->simkopdes->token());

        // Aksi admin lain di hub ini selalu meninggalkan jejak audit.
        jurnal($request->user()?->id, 'SIMP_TARIK', 'koperasi',
            "lewat=web baru={$ringkas['baru']} ubah={$ringkas['ubah']} tetap={$ringkas['tetap']}"
            .($terapkan ? '' : ' (uji saja)'), $request->ip() ?? '');

        return back()->with('pesan', $request->boolean('terapkan')
            ? "Selesai: {$ringkas['baru']} koperasi baru, {$ringkas['ubah']} diperbarui, {$ringkas['tetap']} tetap."
            : "Berhasil menarik {$ringkas['total']} koperasi binaan dari SIMKOPDES "
              ."(token OK). Periksa di bawah, lalu tekan Terapkan.");
    }

    /**
     * Padatkan respons SIMKOPDES (bersarang) → baris bersih untuk Koperasi Hub.
     * Endpoint `by-period-id` membawa objek `cooperative` lengkap; endpoint
     * `by-regional-facilitator-id` tidak (hanya id) — jadi fallback dipertahankan.
     */
    private function baris(array $koperasi): array
    {
        $keluar = [];

        foreach ($koperasi as $k) {
            $k = (array) $k;
            $c = (array) ($k['cooperative'] ?? []);
            $id = (int) ($c['cooperative_id'] ?? $k['cooperative_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $kab = (string) ($c['district_name'] ?? '');
            $kec = (string) ($c['subdistrict_name'] ?? '');
            $desa = (string) ($c['village_name'] ?? '');

            // Kode wilayah resmi BPS dari master (district/169 → "32.16"), bukan karangan.
            $kodeKab = (string) ($c['districtId'] ?? '') !== ''
                ? (string) ($this->simkopdes->kodeWilayah('district', (int) $c['districtId']) ?? '')
                : '';
            $kode = $kodeKab !== '' ? $kodeKab : 'SIMP-'.$id;

            $keluar[] = [
                'kode' => $kode,
                'id_simkopdes' => $id,
                'kode_sumber' => $kodeKab,
                'nama' => trim((string) ($c['name'] ?? '')),
                'wilayah' => trim(implode(', ', array_filter([$desa, $kec, $kab]))),
                // Nama provinsi apa adanya dari SIMKOPDES -> penentu lingkup PIC.
                'provinsi' => \App\Support\Wilayah::normal((string) ($c['province_name'] ?? '')),
                'alamat' => trim((string) ($c['address'] ?? '')),
                'no_rekening' => '',
                'status' => 'AKTIF',
                'sumber' => (string) ($c['sk_number'] ?? ''),
                'profil' => $c,
            ];
        }

        return $keluar;
    }

    /**
     * Tulis ke tabel `koperasi`. Upsert memakai KODE WILAYAH sebagai kunci —
     * itu satu-satunya kode stabil yang dimiliki koperasi di sini (SIMKOPDES
     * memakai id internal 37038 yang tidak ada di skema Hub).
     *
     * ponytail: kunci = kode wilayah; ganti ke kolom `simkopdes_id` kalau nanti
     * wilayah dipakai bersama koperasi lain.
     */
    private function simpan(array $baris, bool $terapkan): array
    {
        $ringkas = ['total' => count($baris), 'baru' => 0, 'ubah' => 0, 'tetap' => 0];
        if (! $terapkan) {
            return $ringkas;
        }

        DB::connection('hub')->transaction(function () use ($baris, &$ringkas) {
            foreach ($baris as $b) {
                // Kunci = id koperasi SIMKOPDES, BUKAN kode wilayah: satu kabupaten
                // bisa memuat beberapa koperasi dan kolom `kode` bersifat UNIQUE.
                // Lihat catatan lengkap di Koperasi::untukSumber().
                $ada = Koperasi::untukSumber((int) ($b['id_simkopdes'] ?? 0), (string) $b['kode']);
                $isi = [
                    'nama' => $b['nama'] !== '' ? $b['nama'] : ($ada->nama ?? 'Koperasi'),
                    'wilayah' => $b['wilayah'] !== '' ? $b['wilayah'] : ($ada->wilayah ?? ''),
                    'provinsi' => $b['provinsi'] !== '' ? $b['provinsi'] : ($ada->provinsi ?? ''),
                    'alamat' => $b['alamat'] !== '' ? $b['alamat'] : ($ada->alamat ?? ''),
                    'status' => 'AKTIF',
                ];

                if (! $ada->exists) {
                    $ada->forceFill($isi + ['versi' => 1, 'diperbarui' => kini()])->save();
                    $ringkas['baru']++;
                } elseif (array_intersect_assoc($isi, $ada->only(array_keys($isi))) === $isi) {
                    $ringkas['tetap']++;
                } else {
                    $ada->forceFill($isi + ['versi' => (int) $ada->versi + 1, 'diperbarui' => kini()])->save();
                    $ringkas['ubah']++;
                }
            }
        });

        return $ringkas;
    }

    /** Potongan token hanya untuk konfirmasi visual di layar. */
    private function potongToken(string $tok): string
    {
        return mb_substr($tok, 0, 14).'…';
    }
}

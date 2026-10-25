<?php

namespace App\Http\Controllers;

use App\Models\Koperasi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BerandaController extends Controller
{
    /** Jumlah artikel di beranda & per halaman daftar artikel. */
    private const ARTIKEL_BERANDA = 3;

    private const ARTIKEL_PER_HALAMAN = 6;

    public function beranda(Request $request)
    {
        $u = $request->user();
        if ($u) {
            return redirect(rute_dasbor($u));
        }

        return view('beranda', [
            'artikel' => array_slice($this->muatArtikel(), 0, self::ARTIKEL_BERANDA),
        ]);
    }

    /** Daftar semua artikel, 6 per halaman. */
    public function artikel(Request $request)
    {
        $semua = $this->muatArtikel();
        $total = max(1, (int) ceil(count($semua) / self::ARTIKEL_PER_HALAMAN));
        $halaman = min(max(1, (int) $request->query('halaman', 1)), $total);

        return view('artikel', [
            'artikel' => array_slice($semua, ($halaman - 1) * self::ARTIKEL_PER_HALAMAN, self::ARTIKEL_PER_HALAMAN),
            'halaman' => $halaman,
            'totalHalaman' => $total,
            'jumlah' => count($semua),
        ]);
    }

    /**
     * Baca docs/riset-media.json dan siapkan untuk tampilan: tanggal bahasa
     * Indonesia + path gambar yang benar-benar ada di public/.
     */
    private function muatArtikel(): array
    {
        $berkas = base_path('docs/riset-media.json');
        if (! is_file($berkas)) {
            return [];
        }

        $isi = json_decode((string) file_get_contents($berkas), true);
        $artikel = is_array($isi['artikel'] ?? null) ? $isi['artikel'] : [];

        // "Kabar terbaru" harus benar-benar terbaru di atas: urutan berkas JSON tidak
        // dijamin (entri lama disimpan sesuai urutan riset). Tanggal kosong ke belakang.
        usort($artikel, function (array $a, array $b): int {
            $ta = ! empty($a['tanggal']) ? (strtotime((string) $a['tanggal']) ?: 0) : 0;
            $tb = ! empty($b['tanggal']) ? (strtotime((string) $b['tanggal']) ?: 0) : 0;

            return $tb <=> $ta;
        });

        return array_map(function (array $a): array {
            $gambar = null;
            $kandidat = (string) ($a['gambar'] ?? '');
            if (preg_match('#^https?://#', $kandidat)) {
                $gambar = $kandidat;
            } elseif ($kandidat !== '') {
                $rel = preg_replace('#^public/#', '', ltrim($kandidat, '/'));
                if (is_file(public_path($rel))) {
                    $gambar = '/'.$rel;
                }
            }

            $tanggal = '';
            if (! empty($a['tanggal'])) {
                try {
                    $tanggal = Carbon::parse($a['tanggal'])->locale('id')->translatedFormat('j F Y');
                } catch (\Throwable) {
                    $tanggal = (string) $a['tanggal'];
                }
            }

            return [
                'judul' => $a['judul'] ?? 'Tanpa judul',
                'media' => $a['media'] ?? '',
                'url' => $a['url'] ?? '#',
                'ringkasan' => $a['ringkasan'] ?? '',
                'tanggal' => $tanggal,
                'gambar' => $gambar,
            ];
        }, $artikel);
    }

    /** Halaman koperasi publik: hanya data koperasi + ringkasan tugas, tanpa berkas. */
    public function koperasi(string $kode)
    {
        $k = Koperasi::where('kode', mb_strtoupper($kode))->firstOrFail();
        $manajer = $k->manajer;

        $ringkas = \App\Models\Tugas::where('koperasi_id', $k->id)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all();

        return view('publik', ['k' => $k, 'manajer' => $manajer, 'ringkas' => $ringkas]);
    }

    /**
     * sitemap.xml — daftar halaman PUBLIK yang boleh diindeks.
     *
     * Yang SENGAJA tidak dimasukkan:
     *   - /k/{kode}  : memuat NAMA LENGKAP manajer koperasi. Itu data pribadi;
     *                  mendaftarkannya ke sitemap = meminta Google mempublikasikan
     *                  nama orang. Halaman tetap bisa dibuka pemilik tautan.
     *   - /masuk, /keluar, /workspace/*, /toko/*, /profil : area ber-login.
     *   - /artikel?halaman=N : kanoniknya halaman 1 (lihat artikel.blade.php),
     *                  jadi tidak didaftarkan terpisah agar tidak jadi konten ganda.
     */
    public function sitemap()
    {
        $base = rtrim(config('app.url') ?: url('/'), '/');

        // lastmod halaman statis = waktu berkas view-nya terakhir berubah.
        $ubah = function (string $view): string {
            $f = resource_path('views/'.$view.'.blade.php');
            return date('c', is_file($f) ? filemtime($f) : time());
        };

        $urls = [
            ['loc' => $base.'/', 'lastmod' => $ubah('beranda'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => $base.'/artikel', 'lastmod' => $this->artikelTerbaru(), 'changefreq' => 'weekly', 'priority' => '0.8'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url>'."\n"
                .'    <loc>'.e($u['loc']).'</loc>'."\n"
                .'    <lastmod>'.e($u['lastmod']).'</lastmod>'."\n"
                .'    <changefreq>'.$u['changefreq'].'</changefreq>'."\n"
                .'    <priority>'.$u['priority'].'</priority>'."\n"
                .'  </url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** Tanggal artikel terbaru (ISO 8601) — dipakai sebagai lastmod sitemap. */
    private function artikelTerbaru(): string
    {
        $terbaru = 0;
        foreach ($this->muatArtikel() as $a) {
            $t = strtotime((string) ($a['tanggal'] ?? ''));
            if ($t && $t > $terbaru) {
                $terbaru = $t;
            }
        }
        return date('c', $terbaru ?: time());
    }

    /**
     * llms.txt — ringkasan situs untuk mesin pencari berbasis AI (ChatGPT,
     * Perplexity, Claude). Formatnya markdown sederhana di /llms.txt: menjelaskan
     * siapa pemilik situs, apa isinya, dan halaman mana yang publik.
     *
     * Dibuat dari data yang SUDAH publik (beranda + daftar artikel), bukan dari
     * database, supaya tidak ada risiko membocorkan data internal.
     */
    public function llms()
    {
        $base = rtrim(config('app.url') ?: url('/'), '/');
        $baris = [
            '# Agrinas Pangan Nusantara',
            '',
            '> PT Agrinas Pangan Nusantara (Persero) — perusahaan bidang pangan dan konsultansi',
            '> konstruksi Indonesia. Mengelola Koperasi Desa/Kelurahan Merah Putih (KDKMP) dan',
            '> menyediakan ruang kerja digital "Agrinas Hub".',
            '',
            'Situs ini berbahasa Indonesia. Halaman publik boleh dibaca dan dikutip dengan',
            'menyebut sumbernya. Area /workspace, /profil, /toko, dan /masuk memerlukan akun —',
            'tidak untuk diindeks.',
            '',
            '## Halaman publik',
            '',
            "- [Beranda]($base/): profil perusahaan, mandat, lini bisnis, layanan Agrinas Hub, direksi, kontak.",
            "- [Pusat Informasi]($base/artikel): kumpulan pemberitaan media tentang Agrinas dan KDKMP.",
            '',
            '## Catatan untuk pengutip',
            '',
            '- Nama resmi: PT Agrinas Pangan Nusantara (Persero). Singkatan yang lazim: Agrinas.',
            '- Program yang disebut: Koperasi Desa/Kelurahan Merah Putih (KDKMP / Kopdes).',
            '- Produk digital: Agrinas Hub (agrinashub.com) — ruang kerja koperasi, absensi,',
            '  katalog produk, dan pusat komunikasi tim.',
            '- Halaman detail koperasi (/k/{kode}) sengaja TIDAK didaftarkan di sini karena',
            '  memuat nama pengelola; jangan dikutip tanpa izin.',
            '',
        ];

        return response(implode("\n", $baris), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** Ringkasan mesin untuk pemantauan. */
    public function healthz()
    {
        return response('ok koperasi='.Koperasi::count(), 200)
            ->header('Content-Type', 'text/plain');
    }
}

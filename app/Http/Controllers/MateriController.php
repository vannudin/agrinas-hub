<?php

namespace App\Http\Controllers;

use App\Models\Materi;
use App\Support\Halaman;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MateriController extends Controller
{
    /** Ukuran maksimal berkas materi (KB). Batas PHP upload_max_filesize = 2M. */
    public const MAKS_KB = 2048;

    public const MIME = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'mp4'];

    /**
     * Satu sumber penyaringan untuk daftar penuh & potongan AJAX. Kalau aturan
     * saringnya ditulis dua kali, hasil muat-ulang dan hasil AJAX cepat atau
     * lambat berbeda saat saringan ditambah.
     */
    private function saring(Request $request): Builder
    {
        $cari = trim((string) $request->query('q', ''));
        $kategori = $this->kategoriSah($request);

        return Materi::query()
            ->when($kategori !== '', fn ($w) => $w->where('kategori', $kategori))
            ->when($cari !== '', function ($w) use ($cari) {
                // `%` dan `_` dilolosi: keduanya pola LIKE, dan tanpa ini
                // mencari `_` mencocokkan hampir semua baris.
                $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], $cari).'%';
                $w->where(fn ($x) => $x->where('judul', 'like', $pola)
                    ->orWhere('nama_asli', 'like', $pola)
                    ->orWhere('catatan', 'like', $pola));
            })
            ->orderByDesc('id');
    }

    /** Kategori saring yang sah; nilai asing = "semua". */
    private function kategoriSah(Request $request): string
    {
        $k = mb_strtoupper((string) $request->query('kategori', ''));

        return array_key_exists($k, Materi::KATEGORI) ? $k : '';
    }

    private function saringKosong(Request $request): bool
    {
        $kategori = mb_strtoupper((string) $request->query('kategori', ''));

        return trim((string) $request->query('q', '')) !== ''
            || array_key_exists($kategori, Materi::KATEGORI);
    }

    /** Jumlah materi per kategori — dipakai kartu ringkasan & label opsi saring. */
    private function perKategori(): array
    {
        return Materi::query()->selectRaw('kategori, COUNT(*) as n')
            ->groupBy('kategori')->pluck('n', 'kategori')->all();
    }

    /**
     * Daftar materi untuk manajer (baca saja).
     *
     * Ditampilkan sebagai kartu per modul (`kelompok`), bukan tabel: satu modul
     * berisi beberapa berkas (modul, bahan tayang, kertas kerja) dan manajer
     * memilih mau membaca yang mana. Urutan modul memakai strnatcasecmp supaya
     * "10" tidak jatuh sebelum "2".
     */
    public function daftar(Request $request)
    {
        $q = $this->saring($request);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);
        $daftar = $halaman->batasi($q)->get();

        $kelompok = $daftar->groupBy(fn (Materi $m) => $m->kelompok !== '' ? $m->kelompok : 'Materi lain')
            ->sortKeysUsing(fn (string $a, string $b) => strnatcasecmp($a, $b));

        // Jumlah berkas per modul dihitung dari SELURUH hasil saringan, bukan dari
        // 10 baris halaman ini — modul yang terbelah antarhalaman tetap menyebut
        // jumlah sebenarnya.
        $perKelompok = $this->saring($request)->reorder()
            ->selectRaw('kelompok, COUNT(*) as n')->groupBy('kelompok')->pluck('n', 'kelompok')->all();

        return view('materi', ['aktif' => 'materi',
            'kelompok' => $kelompok, 'perKelompok' => $perKelompok, 'halaman' => $halaman,
            'jumlah' => $jumlah,
            'perKategori' => $this->perKategori(),
            'kategori' => Materi::KATEGORI,
            'saringKategori' => $this->kategoriSah($request),
            'cari' => trim((string) $request->query('q', ''))]);
    }

    /** Baca di peramban (bukan unduh): PDF disajikan inline. */
    public function lihat(Request $request, int $id)
    {
        $m = Materi::findOrFail($id);
        $disk = Storage::disk(Materi::DISK);

        if (! $disk->exists($m->berkas)) {
            abort(404, 'Berkas materi tidak ditemukan di penyimpanan.');
        }

        jurnal($request->user()->id, 'MATERI_BACA', (string) $m->id, $m->judul, $request->ip() ?? '');

        return $disk->response($m->berkas, $m->nama_asli,
            ['Content-Type' => $m->mime ?: 'application/pdf'], 'inline');
    }

    /** Sampul kartu (halaman pertama PDF) — dipakai halaman materi. */
    public function thumb(Request $request, int $id)
    {
        $m = Materi::findOrFail($id);
        $disk = Storage::disk(Materi::DISK);

        if ($m->thumb === '' || ! $disk->exists($m->thumb)) {
            abort(404);
        }

        return $disk->response($m->thumb, 'sampul.jpg',
            ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=604800'], 'inline');
    }

    /** Kelola materi (unggah/hapus) — hanya Agrinas. */
    public function kelola(Request $request)
    {
        $q = $this->saring($request);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        // Kategori asing di URL diperlakukan "semua", bukan "kosong" — kalau
        // tidak, `?kategori=ngawur` menampilkan tabel kosong tanpa sebab.
        $kategori = mb_strtoupper((string) $request->query('kategori', ''));

        return view('admin.materi', ['aktif' => 'materi',
            'daftar' => $halaman->batasi($q)->get(), 'halaman' => $halaman,
            'kategori' => Materi::KATEGORI, 'maksKb' => self::MAKS_KB,
            'jumlah' => $jumlah,
            'perKategori' => $this->perKategori(),
            'cari' => trim((string) $request->query('q', '')),
            'saringKategori' => array_key_exists($kategori, Materi::KATEGORI) ? $kategori : '']);
    }

    /**
     * Potongan baris tabel untuk AJAX (cari & saring tanpa muat ulang).
     * Markup-nya memakai partial yang SAMA dengan daftar penuh, jadi hasil AJAX
     * mustahil berbeda dari hasil muat ulang.
     */
    public function baris(Request $request)
    {
        // Dibuka langsung (bukan lewat fetch): alihkan ke halaman dengan
        // saringan yang sama, supaya pengunjung tidak menerima potongan telanjang.
        if (! ($request->ajax() || $request->wantsJson())) {
            return redirect()->route('admin.materi', $request->query());
        }

        $q = $this->saring($request);
        $jumlah = (clone $q)->count();
        $halaman = Halaman::buat($jumlah, $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return response()->view('admin._materi-baris', [
            'daftar' => $halaman->batasi($q)->get(),
            'kategori' => Materi::KATEGORI,
            'kosong' => $this->saringKosong($request),
        ])->header('X-Jumlah', (string) $jumlah);
    }

    public function unggah(Request $request)
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'kategori' => ['required', 'in:'.implode(',', array_keys(Materi::KATEGORI))],
            'catatan' => ['nullable', 'string', 'max:500'],
            'berkas' => ['required', 'file', 'max:'.self::MAKS_KB, 'mimes:'.implode(',', self::MIME)],
        ], [
            'berkas.max' => 'Ukuran berkas melebihi '.self::MAKS_KB.' KB.',
            'berkas.mimes' => 'Jenis berkas tidak diizinkan.',
        ], ['judul' => 'judul', 'kategori' => 'kategori', 'catatan' => 'catatan', 'berkas' => 'berkas']);

        $unggah = $request->file('berkas');
        // Nama berkas disimpan acak; nama asli hanya untuk ditampilkan.
        $berkas = $unggah->store('materi', Materi::DISK);

        $m = Materi::create([
            'judul' => $data['judul'], 'kategori' => $data['kategori'],
            'nama_asli' => $unggah->getClientOriginalName(), 'berkas' => $berkas,
            'mime' => (string) $unggah->getClientMimeType(), 'ukuran' => (int) $unggah->getSize(),
            'catatan' => (string) ($data['catatan'] ?? ''), 'oleh' => $request->user()->id,
            'dibuat' => kini(),
        ]);

        jurnal($request->user()->id, 'MATERI_UNGGAH', (string) $m->id,
            $m->judul.' ('.ukuran_id($m->ukuran).')', $request->ip() ?? '');

        $pesan = 'Materi "'.$m->judul.'" diunggah.';

        // Pemanggil AJAX (unggah berprogres) TIDAK boleh diberi redirect: ia
        // menelusuri 302, dan GET halaman tujuan itu sudah MENGHABISKAN flash
        // session-nya — sehingga navigasi berikutnya tidak menyisakan pesan
        // apa pun. Jadi jalur JSON mengembalikan pesannya sendiri; skrip
        // halaman menitipkannya ke `hub_toast` sebelum berpindah.
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan, 'url' => route('admin.materi')]);
        }

        return redirect()->route('admin.materi')->with('pesan', $pesan);
    }

    public function hapus(Request $request, int $id)
    {
        $m = Materi::findOrFail($id);
        Storage::disk(Materi::DISK)->delete($m->berkas);
        jurnal($request->user()->id, 'MATERI_HAPUS', (string) $m->id, $m->judul, $request->ip() ?? '');
        $m->delete();

        return redirect()->route('admin.materi')->with('pesan', 'Materi dihapus.');
    }

    /** Unduh materi. Kedua peran boleh; berkas tidak pernah disajikan langsung dari URL. */
    public function unduh(Request $request, int $id)
    {
        $m = Materi::findOrFail($id);
        if (! Storage::disk(Materi::DISK)->exists($m->berkas)) {
            abort(404, 'Berkas materi tidak ditemukan di penyimpanan.');
        }

        jurnal($request->user()->id, 'MATERI_UNDUH', (string) $m->id, $m->judul, $request->ip() ?? '');

        return Storage::disk(Materi::DISK)->download($m->berkas, $m->nama_asli, ['Content-Type' => $m->mime ?: 'application/octet-stream']);
    }
}

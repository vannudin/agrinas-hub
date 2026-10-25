<?php

namespace App\Support;

/**
 * Paginasi daftar: 10 baris per halaman, nomor halaman gaya
 * "Prev 1 2 3 … 10 Next" (nomor di tepi selalu tampil, sisanya dijendelakan).
 *
 * Sengaja bukan Illuminate\Pagination: yang dibutuhkan hanyalah jumlah baris +
 * batas halaman (LIMIT/OFFSET), bukan LengthAwarePaginator.
 *
 * PENTING soal skala: pemanggil TIDAK mengirim seluruh tabel. Pola lama
 * `Model::get()` lalu `array_slice` memuat SELURUH baris ke memori hanya untuk
 * menampilkan 10 — di 30 ribu pengguna itu artinya tiap permintaan halaman
 * menarik seluruh tabel. Karena itu `buat()` hanya butuh JUMLAH baris, dan
 * `batasi()` menyempitkan query sebelum dieksekusi.
 */
final class Halaman
{
    private function __construct(
        public readonly int $jumlah,
        public readonly int $perHalaman,
        public readonly int $halaman,
        public readonly int $total,
    ) {}

    public static function buat(int $jumlah, mixed $minta, int $perHalaman = 10): self
    {
        $perHalaman = max(1, $perHalaman);
        $total = max(1, (int) ceil($jumlah / $perHalaman));
        $halaman = min(max(1, (int) $minta), $total);

        return new self($jumlah, $perHalaman, $halaman, $total);
    }

    /**
     * Terapkan batas halaman langsung di query (LIMIT/OFFSET) supaya DB hanya
     * mengirim 10 baris, bukan seluruh tabel. Ganti pola
     * `Halaman::buat($q->count(), ...)` + `$halaman->batasi($q)->get()`:
     *
     *     $h = Halaman::buat((clone $q)->count(), $minta);
     *     $baris = $h->batasi($q)->get();
     *
     * Mengembalikan QUERY-nya (bukan $this) supaya bisa langsung ->get().
     */
    public function batasi(\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $q): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
    {
        // clone() wajib: Builder mutable, `limit()` mengubah pemanggilnya. Tanpa
        // clone, agregat yang dihitung setelah batasi() (->sum(), ->count())
        // hanya melihat 10 baris halaman aktif.
        return $q->clone()->limit($this->perHalaman)->offset($this->offset());
    }

    public function offset(): int
    {
        return ($this->halaman - 1) * $this->perHalaman;
    }

    public function dari(): int
    {
        return $this->jumlah === 0 ? 0 : ($this->halaman - 1) * $this->perHalaman + 1;
    }

    public function sampai(): int
    {
        return min($this->jumlah, $this->halaman * $this->perHalaman);
    }

    /**
     * Deret nomor yang ditampilkan. Nomor halaman bertipe int, celah bertipe
     * string "…" — Blade memeriksa tipe untuk memilih <a> / <span>.
     *
     * @return array<int, int|string>
     */
    public function jendela(): array
    {
        // Di halaman pinggir jendela dilebarkan supaya bentuknya tetap
        // "1 2 3 … N", bukan "1 … N" yang terasa kikir.
        $sayap = ($this->halaman <= 2 || $this->halaman >= $this->total - 1) ? 2 : 1;

        $pilih = [];
        for ($n = 1; $n <= $this->total; $n++) {
            if ($this->total <= 7 || $n === 1 || $n === $this->total
                || abs($n - $this->halaman) <= $sayap) {
                $pilih[] = $n;
            }
        }

        $keluar = [];
        $sebelum = 0;
        foreach ($pilih as $n) {
            if ($sebelum !== 0 && $n - $sebelum > 1) {
                $keluar[] = '…';
            }
            $keluar[] = $n;
            $sebelum = $n;
        }

        return $keluar;
    }
}

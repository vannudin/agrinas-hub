<?php

namespace App\Http\Controllers;

use App\Models\Distribusi;
use App\Models\Koperasi;
use App\Models\Tugas;
use App\Models\VoteSuara;
use App\Support\Halaman;
use Illuminate\Http\Request;

class KerjaController extends Controller
{
    /**
     * Dasbor manajer — disusun sejajar dengan dasbor Agrinas
     * (DasborController::dasbor): kartu ringkasan, aksi cepat, lalu DUA daftar
     * berhalaman: Tugas dan Barang.
     *
     * Saringan & halaman dipisah per daftar (`q`/`status`/`halaman` untuk tugas,
     * `bq`/`bstatus`/`halaman_barang` untuk barang). Satu himpunan parameter
     * bersama membuat menekan "halaman 2" di tabel barang ikut menggeser tabel
     * tugas — pengguna melihat tabel yang tak ia sentuh berubah sendiri.
     */
    public function dasbor(Request $request)
    {
        $u = $request->user();
        $kop = Koperasi::where('manajer_id', $u->id)->first();

        // Modal "Manager Memilih Sistem" muncul HANYA selama manajer belum
        // bersuara. Dicek dari baris `vote_suara` (kunci unik topik+user_id),
        // bukan penanda di sesi — pindah peramban tidak memunculkannya ulang.
        // `$kop` wajib: yang diajak adalah MANAJER. Tanpa syarat ini admin/PIC/
        // HR (tak mengampu koperasi mana pun) juga dibuka modalnya di halaman
        // kosong — dan <dialog> yang terbuka MENAHAN semua klik di halaman,
        // jadi tiap pengukuran/otomasi di /kerja gagal "intercepts pointer
        // events" (terukur: ukur_kerja.py TimeoutError 30 s).
        $voteBelum = $kop && ! VoteSuara::where('topik', VoteSuara::TOPIK)->where('user_id', $u->id)->exists();

        if (! $kop) {
            return view('kerja', ['aktif' => 'kerja', 'koperasi' => null, 'tugas' => collect(),
                'stat' => [], 'barang' => collect(), 'statKirim' => [], 'halamanKirim' => null,
                'halamanTugas' => null, 'q' => '', 'status' => '', 'bq' => '', 'bstatus' => '',
                'voteBelum' => $voteBelum]);
        }

        // ---------------------------------------------------------------- Tugas
        $q = (string) $request->query('q', '');
        $status = mb_strtoupper((string) $request->query('status', ''));

        $qTugas = Tugas::where('koperasi_id', $kop->id)
            // Selaras dengan urutan kartu dasbor: yang perlu digarap dulu
            // (revisi/ditolak), baru yang masih berjalan.
            ->orderByRaw("CASE status WHEN 'REVISI' THEN 0 WHEN 'DITOLAK' THEN 1
                          WHEN 'BARU' THEN 2 WHEN 'DIKERJAKAN' THEN 3 WHEN 'DIAJUKAN' THEN 4 ELSE 5 END")
            ->orderByRaw('deadline IS NULL')->orderBy('deadline');

        // Angka kartu dihitung dari lingkup koperasi, TANPA saringan: kartu
        // ringkasan adalah gambaran keseluruhan; kalau ikut tersaring, angkanya
        // berubah setiap kali pengguna mengetik di kotak cari dan itu
        // menyesatkan (labelnya tidak menyebut "hasil pencarian").
        // ponytail: agregat dihitung sebelum saringan — pindahkan bila kartu
        // perlu ikut menyaring.
        $dasarTugas = clone $qTugas;
        $stat = (clone $dasarTugas)->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')->pluck('n', 'status')->all();
        $stat['TERLAMBAT'] = (clone $dasarTugas)->where('status', '<>', 'SELESAI')
            ->whereNotNull('deadline')->where('deadline', '<', now()->toDateString())->count();

        // Saring di DB, bukan di PHP: filter di PHP memaksa seluruh tugas koperasi
        // ditarik lebih dulu lalu dipotong — pola yang mematikan halaman saat
        // tugas menumpuk. (LIKE: '%'/'_' di kata kunci diperlakukan sebagai pola.)
        if ($status === 'TERLAMBAT') {
            $qTugas->where('status', '<>', 'SELESAI')->whereNotNull('deadline')
                ->where('deadline', '<', now()->toDateString());
        } elseif ($status !== '') {
            $qTugas->where('status', $status);
        }
        if ($q !== '') {
            $qTugas->where('judul', 'like', '%'.$q.'%');
        }

        $halamanTugas = Halaman::buat((clone $qTugas)->count(), $request->query('halaman', 1),
            DasborController::PER_HALAMAN);
        $tugas = $halamanTugas->batasi($qTugas)->get();

        // --------------------------------------------------------------- Barang
        // Kepemilikan ditentukan `distribusi.koperasi_id` (barang adalah katalog
        // global Agrinas, ia TIDAK punya koperasi_id — join ke sana error).
        // Satu baris tabel = satu barang dalam satu surat jalan; sengaja TIDAK
        // di-group per surat jalan supaya `count(*)` untuk paging sama dengan
        // jumlah baris yang benar-benar tampil (group + jumlah baris berbeda =
        // pager menunjuk halaman yang isinya tak sesuai).
        $bq = (string) $request->query('bq', '');
        $bstatus = mb_strtoupper((string) $request->query('bstatus', ''));

        $selectBarang = 'distribusi.id, distribusi.kode, distribusi.status, distribusi.diperbarui,
                         b.nama as barang, b.kode as kode_barang, b.satuan,
                         i.jumlah, i.jumlah_diterima';

        $urutBarang = "CASE distribusi.status WHEN 'DIKIRIM' THEN 0 WHEN 'DIAJUKAN' THEN 1
                       WHEN 'DIPROSES' THEN 2 WHEN 'DITOLAK' THEN 3 ELSE 4 END";

        $statKirim = [];
        foreach (Distribusi::STATUS as $s) {
            $statKirim[$s] = ['n' => 0, 'jenis' => 0, 'jumlah' => 0];
        }
        foreach (Distribusi::query()
            ->join('distribusi_item as i', 'i.distribusi_id', '=', 'distribusi.id')
            ->where('distribusi.koperasi_id', $kop->id)
            ->selectRaw('distribusi.status, COUNT(DISTINCT distribusi.id) as n,
                         COUNT(*) as jenis, COALESCE(SUM(i.jumlah),0) as jumlah')
            ->groupBy('distribusi.status')->get() as $r) {
            if (isset($statKirim[$r->status])) {
                $statKirim[$r->status] = ['n' => (int) $r->n, 'jenis' => (int) $r->jenis,
                                          'jumlah' => (int) $r->jumlah];
            }
        }

        $qBarang = Distribusi::query()
            ->join('distribusi_item as i', 'i.distribusi_id', '=', 'distribusi.id')
            ->join('barang as b', 'b.id', '=', 'i.barang_id')
            ->where('distribusi.koperasi_id', $kop->id)
            ->selectRaw($selectBarang)
            ->orderByRaw($urutBarang)
            ->orderByDesc('distribusi.id')
            ->orderBy('b.nama');

        if ($bstatus !== '' && isset(Distribusi::LABEL[$bstatus])) {
            $qBarang->where('distribusi.status', $bstatus);
        }
        if ($bq !== '') {
            $qBarang->where(fn ($w) => $w->where('distribusi.kode', 'like', '%'.$bq.'%')
                ->orWhere('b.nama', 'like', '%'.$bq.'%')
                ->orWhere('b.kode', 'like', '%'.$bq.'%'));
        }

        $halamanKirim = Halaman::buat((clone $qBarang)->get()->count(),
            $request->query('halaman_barang', 1), DasborController::PER_HALAMAN);

        return view('kerja', [
            'aktif' => 'kerja', 'koperasi' => $kop,
            'tugas' => $tugas, 'stat' => $stat, 'halamanTugas' => $halamanTugas,
            'barang' => $halamanKirim->batasi($qBarang)->get(),
            'statKirim' => $statKirim, 'halamanKirim' => $halamanKirim,
            'q' => $q, 'status' => $status, 'bq' => $bq, 'bstatus' => $bstatus,
            'voteBelum' => $voteBelum,
        ]);
    }

    /**
     * Rincian tugas. `KerjaController::tugas()` dihapus bersama rute
     * /kerja/tugas/{id}: view `tugas` yang dirujuknya tidak ada di repo ini
     * sehingga selalu 500, dan tak satu pun tautan mengarah ke sana. Detail
     * tugas ada di /workspace/tugas.
     */

    public static function terlambat($t): bool
    {
        return (bool) $t->deadline && $t->deadline < now()->toDateString() && $t->status !== 'SELESAI';
    }
}

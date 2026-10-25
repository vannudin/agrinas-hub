<?php

namespace App\Http\Controllers;

use App\Models\Aspirasi;
use App\Models\Koperasi;
use App\Models\PengumumanBaca;
use App\Models\Siaran;
use App\Support\Halaman;
use App\Support\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Komunikasi dua arah dengan manajer:
 *
 *  - PENGUMUMAN (turun): pengelola (agrinas/HR/PIC) menerbitkan, manajer membaca.
 *    Yang dikelola di sini hanya PENANDA BACA. Pengumumannya sendiri tetap di
 *    tabel `broadcast` supaya tidak ada dua tempat menulis hal yang sama, dan
 *    siaran ke email/WhatsApp tetap bisa dikirim dari /admin/broadcast.
 *
 *  - ASPIRASI (naik): manajer mengirim masukan/keluhan, pengelola menanggapi.
 *
 * Penjaga rutenya: '/pengumuman' & '/aspirasi' → semua peran yang login;
 * '/admin/aspirasi' → 'wilayah' (agrinas|HR|PIC). Pemeriksaan yang lebih halus
 * dilakukan di dalam method: `pengumuman_terbit` dan `aspirasi_tanggapi` menolak
 * 403 untuk selain pengelola, `aspirasi_simpan` menolak pengelola (mereka
 * menanggapi, bukan mengirim). Dua tempat saja, bukan penjaga rute terpisah per
 * peran yang isinya bakal sama.
 */
class KomunikasiDuaarahController extends Controller
{
    public const PER_HALAMAN = 10;

    /** Pengumuman: daftar + formulir terbit (khusus pengelola) atau daftar baca (manajer). */
    public function pengumuman(Request $request)
    {
        $u = $request->user();
        $pengelola = $this->pengelola($u);
        $cari = trim((string) $request->query('q', ''));

        // Daftar: MANAJER hanya melihat yang menyasar dirinya (kalau tidak,
        // pengumuman khusus pusat bocor ke semua manajer). PENGELOLA melihat
        // seluruh pengumuman — kalau ia hanya melihat sasaran dirinya sendiri,
        // pengumuman yang baru ia terbitkan untuk manajer HILANG dari daftarnya.
        $q = ($pengelola ? Siaran::query() : PengumumanBaca::untuk($u))
            ->when($pengelola, fn ($w) => $w->where(fn ($x) => $x
                ->where('sasaran', 'not like', 'usr:%')
                ->orWhere('sasaran', 'usr:'.$u->id)))
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('perihal', 'like', '%'.$cari.'%')
                ->orWhere('pesan', 'like', '%'.$cari.'%')))
            ->orderByDesc('id');
        $h = Halaman::buat((clone $q)->count(), $request->query('halaman', 1), self::PER_HALAMAN);

        $baca = DB::connection('hub')->table('pengumuman_baca')
            ->where('user_id', $u->id)->pluck('siaran_id')->all();

        return view('pengumuman.index', [
            'aktif' => 'pengumuman',
            'daftar' => $h->batasi($q)->get(),
            'halaman' => $h,
            'cari' => $cari,
            'baca' => $baca,
            // Dihitung di controller, BUKAN diandalkan dari layout: layout
            // merender `@section('isi')` sebagai view terpisah, jadi variabel
            // yang dibuat di layout tidak ikut masuk ke view halaman ini
            // (gejalanya: "Undefined variable $belumBaca" → 500).
            'belumBaca' => PengumumanBaca::belumDibaca($u),
            'pengelola' => $pengelola,
            'sasaran' => Siaran::SASARAN,
            'koperasiPilihan' => $u->agrinas() || $u->hrd()
                ? \App\Models\Koperasi::orderBy('kode')->get(['kode', 'nama'])
                : collect(),
        ]);
    }

    /**
     * Detail satu pengumuman — halaman penuh, bukan kartu yang mengembang.
     *
     * Membukanya SEKALIGUS menandai sudah dibaca: itu satu-satunya saat kita tahu
     * pengumuman benar-benar dilihat, dan Bos memintanya begitu. Penandanya idempoten
     * (insertOrIgnore), jadi membuka dua kali tidak menambah baris.
     *
     * Lingkupnya memakai penyaring yang sama dengan daftar: manajer hanya boleh
     * membuka pengumuman yang menyasar dirinya — kalau tidak, pengumuman khusus
     * pusat bocor lewat URL yang ditebak.
     */
    public function pengumuman_detail(Request $request, int $id)
    {
        $u = $request->user();
        $pengelola = $this->pengelola($u);

        $s = ($pengelola ? Siaran::query() : PengumumanBaca::untuk($u))
            // Pengumuman PRIBADI tidak boleh dibuka pengelola lewat URL yang
            // ditebak: isinya kredensial milik satu orang. Hanya pemiliknya.
            ->when($pengelola, fn ($w) => $w->where(fn ($x) => $x
                ->where('sasaran', 'not like', 'usr:%')
                ->orWhere('sasaran', 'usr:'.$u->id)))
            ->where('id', $id)->firstOrFail();

        $sudahBaca = in_array($s->id, DB::connection('hub')->table('pengumuman_baca')
            ->where('user_id', $u->id)->pluck('siaran_id')->all(), true);

        if (! $sudahBaca) {
            DB::connection('hub')->table('pengumuman_baca')->insertOrIgnore([
                'user_id' => $u->id, 'siaran_id' => $s->id, 'dibaca' => kini(),
            ]);
        }

        return view('pengumuman.detail', [
            'aktif' => 'pengumuman',
            's' => $s,
            'sudahBaca' => $sudahBaca,
            'pengelola' => $pengelola,
        ]);
    }

    /**
     * Terbitkan pengumuman (hanya pengelola).
     *
     * Ditulis ke `broadcast` dengan kanal PENGUMUMAN dan kolom `dibuat` apa
     * adanya — persis bentuk yang sudah dibaca halaman lain. Tidak ada tabel
     * pengumuman kedua.
     */
    public function pengumuman_terbit(Request $request)
    {
        $u = $request->user();
        if (! $this->pengelola($u)) {
            abort(403, 'Hanya pengelola (Agrinas, HR, atau PIC) yang boleh menerbitkan pengumuman.');
        }

        $d = $request->validate([
            'perihal' => ['required', 'string', 'max:160'],
            'pesan' => ['required', 'string', 'max:4000'],
            'sasaran' => ['required', 'string', 'max:40'],
        ], [], ['perihal' => 'perihal', 'pesan' => 'isi pengumuman', 'sasaran' => 'sasaran']);

        // Sasaran sah: kode dari Siaran::SASARAN, atau 'kop:KODE' yang benar-benar ada.
        $sah = isset(Siaran::SASARAN[$d['sasaran']]);
        if (! $sah && str_starts_with($d['sasaran'], 'kop:')) {
            $sah = \App\Models\Koperasi::where('kode', mb_strtoupper(substr($d['sasaran'], 4)))->exists();
        }
        if (! $sah) {
            return back()->withInput()->with('galat', 'Sasaran pengumuman tidak dikenal.');
        }

        $s = Siaran::create([
            'kanal' => Siaran::KANAL_PENGUMUMAN, 'perihal' => $d['perihal'], 'pesan' => $d['pesan'],
            'sasaran' => $d['sasaran'],
            // `jumlah` = banyak penerima, dihitung dengan aturan yang sudah ada
            // (Siaran::penerima) supaya tidak ada dua arti sasaran.
            'jumlah' => Siaran::penerima($d['sasaran'])->count(),
            'pengirim' => (string) $u->email, 'dibuat' => kini(),
        ]);

        jurnal($u->id, 'PENGUMUMAN', $s->id.':TERBIT',
            'sasaran='.$d['sasaran'].' perihal='.mb_substr($d['perihal'], 0, 60), $request->ip() ?? '');

        return back()->with('pesan', 'Pengumuman terbit dan muncul sebagai penanda baru bagi '
            .number_format($s->jumlah, 0, ',', '.').' penerima.');
    }

    /** Manajer menandai seluruh pengumuman yang berlaku sebagai sudah dibaca. */
    public function pengumuman_baca(Request $request)
    {
        $n = PengumumanBaca::tandaiSemua($request->user());

        return back()->with('pesan', $n > 0
            ? "{$n} pengumuman ditandai sudah dibaca."
            : 'Tidak ada pengumuman baru.');
    }

    // ------------------------------------------------------------------ aspirasi

    /** Aspirasi manajer: daftar miliknya + formulir kirim. */
    public function aspirasi(Request $request)
    {
        $u = $request->user();
        if ($this->pengelola($u)) {
            return redirect()->route('admin.aspirasi');
        }

        $cari = trim((string) $request->query('q', ''));
        $q = Aspirasi::query()->where('user_id', $u->id)
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('perihal', 'like', '%'.$cari.'%')->orWhere('pesan', 'like', '%'.$cari.'%')))
            ->orderByDesc('id');
        $h = Halaman::buat((clone $q)->count(), $request->query('halaman', 1), self::PER_HALAMAN);

        return view('aspirasi.index', [
            'aktif' => 'aspirasi', 'daftar' => $h->batasi($q)->get(),
            'halaman' => $h, 'cari' => $cari,
            'jenis' => Aspirasi::JENIS,
        ]);
    }

    /** Kirim aspirasi baru (manajer). Status selalu BARU. */
    public function aspirasi_simpan(Request $request)
    {
        $u = $request->user();
        if ($this->pengelola($u)) {
            abort(403, 'Pengelola menanggapi aspirasi, bukan mengirimnya.');
        }

        $d = $request->validate([
            'jenis' => ['required', 'in:'.implode(',', array_keys(Aspirasi::JENIS))],
            'perihal' => ['required', 'string', 'max:160'],
            'pesan' => ['required', 'string', 'max:4000'],
        ], [], ['jenis' => 'jenis', 'perihal' => 'perihal', 'pesan' => 'isi aspirasi']);

        $a = Aspirasi::create($d + [
            'user_id' => $u->id,
            'koperasi' => (string) ($u->koperasiDiampu()?->nama ?? ''),
            'status' => 'BARU',
            'dibuat' => kini(),
        ]);

        jurnal($u->id, 'ASPIRASI', $a->id.':KIRIM',
            'jenis='.$a->jenis.' perihal='.mb_substr($a->perihal, 0, 60), $request->ip() ?? '');

        return back()->with('pesan', 'Aspirasi terkirim ke pengelola. Tanggapan akan tampil di halaman ini.');
    }

    /** Daftar aspirasi untuk pengelola + jumlah yang belum disentuh. */
    public function aspirasi_admin(Request $request)
    {
        $u = $request->user();
        if (! $this->pengelola($u) && ! $u->pantau()) {
            abort(403, 'Halaman ini khusus pengelola.');
        }

        $cari = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        // Pengawasan membaca aspirasi dari koperasi di lingkupnya saja. Kolom
        // `aspirasi.koperasi` berisi NAMA koperasi (bukan id), jadi pagarnya
        // memakai daftar nama dari koperasiQuery().
        $saringLingkup = function ($w) use ($u) {
            $s = Wilayah::saringLingkup((string) $u->wilayah);

            return $s === null ? $w : $w->whereIn('koperasi',
                Koperasi::query()->where($s)->select('nama'));
        };

        $q = Aspirasi::query()->with('pengirim:id,nama,email')
            ->when($u->pantau(), $saringLingkup)
            ->when($cari !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('perihal', 'like', '%'.$cari.'%')
                ->orWhere('pesan', 'like', '%'.$cari.'%')
                ->orWhere('koperasi', 'like', '%'.$cari.'%')))
            ->when(isset(Aspirasi::STATUS[$status]), fn ($w) => $w->where('status', $status))
            ->orderByDesc('id');
        $h = Halaman::buat((clone $q)->count(), $request->query('halaman', 1), self::PER_HALAMAN);

        return view('aspirasi.admin', [
            'aktif' => 'aspirasi', 'daftar' => $h->batasi($q)->get(),
            'halaman' => $h, 'cari' => $cari, 'status' => $status,
            'statusPilihan' => Aspirasi::STATUS,
            'jumlahBaru' => Aspirasi::where('status', 'BARU')->when($u->pantau(), $saringLingkup)->count(),
        ]);
    }

    /**
     * Tanggapi aspirasi (pengelola). Satu kolom tanggapan, bukan percakapan
     * berantai; menyimpan juga siapa yang menanggapi dan kapan.
     */
    public function aspirasi_tanggapi(Request $request, int $id)
    {
        $u = $request->user();
        if (! $this->pengelola($u)) {
            abort(403, 'Hanya pengelola yang boleh menanggapi aspirasi.');
        }

        $a = Aspirasi::findOrFail($id);
        $d = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Aspirasi::STATUS))],
            'tanggapan' => ['nullable', 'string', 'max:4000'],
        ], [], ['status' => 'status', 'tanggapan' => 'tanggapan']);

        $a->update([
            'status' => $d['status'],
            // Tanggapan kosong TIDAK menimpa tanggapan lama: salah ketik lalu
            // simpan ulang tidak boleh menghapus jawaban yang sudah dikirim.
            'tanggapan' => ($d['tanggapan'] ?? '') !== '' ? $d['tanggapan'] : $a->tanggapan,
            'penanggap' => $d['status'] === 'SELESAI' || ($d['tanggapan'] ?? '') !== '' ? (string) $u->email : $a->penanggap,
            'ditanggapi' => now(),
        ]);

        jurnal($u->id, 'ASPIRASI', $a->id.':TANGGAPI', 'status='.$d['status'], $request->ip() ?? '');

        return back()->with('pesan', 'Aspirasi #'.$a->id.' diperbarui ('.$a->labelStatus().').');
    }

    /** Pengelola = agrinas pusat, petugas HR, atau PIC wilayah. */
    private function pengelola($u): bool
    {
        return (bool) $u && ($u->agrinas() || $u->hrd() || $u->pic());
    }
}

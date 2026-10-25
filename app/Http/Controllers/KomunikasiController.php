<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Koperasi;
use App\Models\PemetaanLahan;
use App\Models\Siaran;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Halaman;
use App\Support\ScryptHasher;
use App\Support\Wilayah;
use App\Services\Whatsapp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class KomunikasiController extends Controller
{
    public function __construct(private readonly Whatsapp $whatsapp) {}

    /**
     * Daftar akun: SATU tabel semua pengguna + pencarian dan saringan
     * peran/status. Tambah akun juga satu formulir dengan pilihan peran —
     * dulu ada tiga formulir & tiga tabel terpisah (PIC/HR/Keuangan) padahal
     * isinya potongan dari tabel yang sama, dan filternya sudah bisa
     * memisahkannya.
     *
     */
    public function akun(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $peran = (string) $request->query('peran', '');
        $status = (string) $request->query('status', '');

        $semua = User::query()
            ->leftJoin('koperasi as k', 'k.manajer_id', '=', 'users.id')
            ->select('users.*', 'k.nama as nama_koperasi', 'k.kode as kode_koperasi');

        if ($q !== '') {
            $semua->where(fn ($w) => $w->where('users.nama', 'like', '%'.$q.'%')
                ->orWhere('users.email', 'like', '%'.$q.'%')
                ->orWhere('k.nama', 'like', '%'.$q.'%')
                ->orWhere('k.kode', 'like', '%'.$q.'%'));
        }
        if (isset(Domain::LABEL_PERAN[$peran])) {
            $semua->where('users.peran', $peran);
        }
        if ($status === 'aktif') {
            $semua->where('users.aktif', 1);
        } elseif ($status === 'nonaktif') {
            $semua->where('users.aktif', 0);
        }

        $semua->orderBy('users.peran')->orderBy('users.nama');

        $halaman = Halaman::buat((clone $semua)->count(), $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return view('admin.akun', ['aktif' => 'akun',
            'daftar' => $halaman->batasi($semua)->get(),
            'koperasiPilihan' => Koperasi::orderBy('nama')->get(),
            'halaman' => $halaman,
            'wilayah' => Wilayah::PILIHAN,
            // Lingkup kabupaten/kota untuk peran pengawasan (TNI/Dinas) — diambil
            // dari data pemetaan lahan supaya yang bisa dipilih benar-benar ada.
            'kabupaten' => PemetaanLahan::query()->select('kabupaten')
                ->where('kabupaten', '<>', '')->distinct()->orderBy('kabupaten')->pluck('kabupaten'),
            'q' => $q, 'peran' => $peran, 'status' => $status,
            // Pilihan vendor untuk peran VENDOR: akun mitra WAJIB tertaut ke
            // satu baris vendor (lihat akun_simpan()).
            'vendorList' => Vendor::query()->orderBy('nama')->pluck('nama', 'id')->all(),
            'kanal' => Siaran::KANAL, 'sasaran' => Siaran::SASARAN]);
    }

    /**
     * Buat / angkat akun dengan peran pilihan admin — satu jalur untuk semua
     * peran yang boleh dibuat dari panel (PIC Wilayah, Petugas HR, Keuangan).
     *
     * Dulu tiga metode terpisah (keuangan_baru/hr_baru/pic_baru) dengan isi
     * hampir sama; bedanya hanya awalan sandi bawaan dan satu aturan PIC (satu
     * wilayah satu PIC). Perbedaan itu tinggal di sini.
     *
     * Sandi HANYA ditulis bila admin menyebutnya — jangan reset sandi orang
     * tanpa diminta. MANAJER & AGRINAS tidak ditawarkan: manajer lahir dari
     * login SIMKOPDES pendamping, admin pusat dari seeder.
     */
    public function akun_simpan(Request $request)
    {
        $peranSah = [Domain::PERAN_PIC, Domain::PERAN_HR, Domain::PERAN_KEUANGAN,
                     Domain::PERAN_TNI, Domain::PERAN_DINAS, Domain::PERAN_VENDOR];

        $d = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'peran' => ['required', 'string', 'in:'.implode(',', $peranSah)],
            'wilayah' => ['nullable', 'string', 'max:80'],
            'vendor_id' => ['nullable', 'integer'],
            'sandi' => ['nullable', 'string', 'min:10', 'max:200'],
        ], ['peran.in' => 'Peran yang bisa dibuat di sini: PIC Wilayah, Petugas HR, Keuangan, TNI Pengawasan, Dinas Koperasi Pengawasan, atau Vendor Penyalur.'],
            ['email' => 'email', 'peran' => 'peran', 'wilayah' => 'wilayah']);

        // Akun VENDOR WAJIB tertaut ke satu baris `vendor`: tanpa tautan, halaman
        // /mitra-nya tidak punya penyaring barang dan akan menampilkan pesanan
        // seluruh vendor. Diperiksa di sini (bukan `required_if`) supaya pesannya
        // menyebut peran, bukan nama kolom.
        $vendorId = null;
        if ($d['peran'] === Domain::PERAN_VENDOR) {
            $vendorId = (int) ($d['vendor_id'] ?? 0);
            if ($vendorId <= 0 || ! Vendor::whereKey($vendorId)->exists()) {
                return back()->withErrors(['vendor_id' => 'Akun vendor harus ditautkan ke salah satu vendor di daftar.'])->withInput();
            }
        }

        // Wilayah wajib untuk PIC. Diperiksa di sini, bukan `required_if`, supaya
        // pesannya menyebut perannya alih-alih nama kolom.
        if ($d['peran'] === Domain::PERAN_PIC) {
            $w = trim((string) ($d['wilayah'] ?? ''));
            if ($w === '') {
                return back()->withErrors(['wilayah' => 'PIC harus punya wilayah.'])->withInput();
            }
            if (! Wilayah::sah($w)) {
                return back()->withErrors(['wilayah' => 'Wilayah PIC harus salah satu provinsi/lingkup di daftar.'])->withInput();
            }
        }

        // Pengawasan (TNI/Dinas) memegang satu lingkup: PUSAT, provinsi, atau
        // kabupaten/kota. Disimpan di kolom yang sama (`users.wilayah`) supaya
        // tidak ada kolom baru; TNI menyaring pemetaan lahan dari nilai ini.
        if (in_array($d['peran'], Domain::PERAN_PANTAU, true)) {
            $w = trim((string) ($d['wilayah'] ?? ''));
            $sah = $w === Wilayah::PUSAT || Wilayah::sah($w)
                || ($w !== '' && PemetaanLahan::where('kabupaten', $w)->exists());
            if (! $sah) {
                return back()->withErrors(['wilayah' => 'Pengawasan harus punya lingkup: PUSAT, nama provinsi, atau nama kabupaten/kota yang ada di pemetaan lahan.'])->withInput();
            }
        }

        $email = mb_strtolower(trim($d['email']));
        $awalan = [Domain::PERAN_PIC => 'Pic#', Domain::PERAN_HR => 'Hr#', Domain::PERAN_KEUANGAN => 'Kgn#',
                   Domain::PERAN_TNI => 'Tni#', Domain::PERAN_DINAS => 'Dns#', Domain::PERAN_VENDOR => 'Vnd#'];
        $sandi = (string) ($d['sandi'] ?? ($awalan[$d['peran']].substr(bin2hex(random_bytes(6)), 0, 10)));
        // `users.wilayah` NOT NULL (bukan nullable) — peran non-PIC memakai ''.
        // Pengawasan memakai kolom yang sama untuk lingkupnya (PUSAT/provinsi/kabupaten).
        $wilayah = ($d['peran'] === Domain::PERAN_PIC || in_array($d['peran'], Domain::PERAN_PANTAU, true))
            ? trim((string) ($d['wilayah'] ?? '')) : '';

        // Satu wilayah satu PIC: PIC lama di wilayah itu turun jadi Manajer,
        // kecuali baris yang sedang disimpan ini sendiri.
        if ($wilayah !== '') {
            User::where('peran', Domain::PERAN_PIC)->where('wilayah', $wilayah)
                ->where('email', '!=', $email)->get()
                ->each(fn (User $l) => $l->forceFill(['peran' => Domain::PERAN_MANAJER])->save());
        }

        $ada = User::where('email', $email)->first();
        if ($ada) {
            $isi = ['peran' => $d['peran'], 'nama' => $d['nama'], 'aktif' => 1];
            // Wilayah hanya DISENTUH untuk PIC: mengangkat manajer jadi HR tidak
            // boleh mengosongkan wilayah yang sudah tercatat untuknya.
            if ($wilayah !== '') {
                $isi['wilayah'] = $wilayah;
            }
            // Tautan vendor hanya ditulis saat perannya VENDOR; mengubah peran
            // vendor menjadi HR harus MELEPAS tautannya, bukan menyisakan
            // tautan lama yang membuat akun itu masih lolos pagar /mitra.
            $isi['vendor_id'] = $vendorId;
            if (! empty($d['sandi'])) {
                // Sandi yang disebut admin dipasang ulang; kalau kosong, sandi lama
                // dibiarkan (jangan reset sandi orang tanpa diminta).
                $isi['sandi'] = ScryptHasher::buat($sandi);
                $isi['sandi_sumber'] = 'HUB';
                $isi['wajib_ganti'] = 0;
            }
            $ada->forceFill($isi)->save();
        } else {
            User::create([
                'email' => $email, 'nama' => $d['nama'], 'peran' => $d['peran'],
                'wilayah' => $wilayah, 'sandi' => ScryptHasher::buat($sandi),
                'vendor_id' => $vendorId,
                'wajib_ganti' => 1, 'aktif' => 1, 'dibuat' => kini(),
            ]);
        }

        jurnal($request->user()->id, 'AKUN_BARU', $email,
            Domain::LABEL_PERAN[$d['peran']].($wilayah !== '' ? ' '.Wilayah::label($wilayah) : '').' (via web)',
            $request->ip() ?? '');

        // Sandi hanya ditampilkan sekali, di pesan sesi — tidak disimpan mentah.
        return back()->with('pesan', 'Akun '.$d['nama'].' ('.Domain::LABEL_PERAN[$d['peran']]
            .($wilayah !== '' ? ' — '.Wilayah::label($wilayah) : '').') siap. Sandi awal: '.$sandi
            .' — wajib diganti saat masuk pertama. Kirim lewat kanal yang aman.');
    }

    /** Aktif / nonaktifkan akun (dipakai untuk PIC/HR yang berhenti bertugas). */
    public function akun_aktif(Request $request, int $id)
    {
        $a = User::findOrFail($id);
        if ($a->id === $request->user()->id) {
            return back()->with('galat', 'Tidak bisa menonaktifkan akun sendiri.');
        }

        $a->forceFill(['aktif' => ! $a->aktif])->save();
        jurnal($request->user()->id, 'AKUN_AKTIF_UBAH', $a->email,
            $a->aktif ? 'diaktifkan' : 'dinonaktifkan', $request->ip() ?? '');

        return back()->with('pesan', 'Akun '.$a->email.' '.($a->aktif ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    /** Daftar riwayat + formulir broadcast, plus keadaan gerbang WhatsApp. */
    public function siaran(Request $request)
    {
        $semua = Siaran::query()->orderByDesc('id');
        $halaman = Halaman::buat((clone $semua)->count(), $request->query('halaman', 1), DasborController::PER_HALAMAN);

        return view('admin.broadcast', ['aktif' => 'siaran',
            'riwayat' => $halaman->batasi($semua)->get(),
            'koperasiPilihan' => Koperasi::orderBy('nama')->get(),
            'halaman' => $halaman,
            'wa' => $this->whatsapp->status(),
            'kanal' => Siaran::KANAL, 'sasaran' => Siaran::SASARAN]);
    }

    /** Keadaan gerbang untuk polling dialog QR (JSON, bukan muat ulang halaman). */
    public function wa_status()
    {
        return response()->json($this->whatsapp->status());
    }

    /** Putuskan sesi WhatsApp (perangkat tertaut dicabut dari sisi kontainer). */
    public function wa_putus()
    {
        return response()->json(['siap' => $this->whatsapp->putus()]);
    }

    /** Gambar QR pemindaian WhatsApp — diproksi supaya kontainer tetap loopback. */
    public function wa_qr()
    {
        $png = $this->whatsapp->qrPng();

        if ($png === null) {
            return redirect()->route('admin.broadcast')
                ->with('galat', 'QR belum tersedia. Pastikan kontainer baileys-runtime hidup, lalu muat ulang.');
        }

        return response($png, 200, [
            'Content-Type' => 'image/png', 'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    /**
     * Kirim broadcast.
     *
     * Email: satu pesan per penerima (bukan BCC) supaya tiap orang hanya
     * melihat namanya sendiri; kegagalan dikumpulkan, tidak menghentikan
     * seluruh proses.
     *
     * WhatsApp: dikirim lewat gerbang Baileys di kontainer terpisah
     * (App\Services\Whatsapp). Nomor diambil dari profil SIMKOPDES masing-masing
     * penerima; yang belum punya nomor masuk daftar gagal, bukan dianggap sukses.
     */
    public function siaran_aksi(Request $request)
    {
        $data = $request->validate([
            'kanal' => ['required', 'in:EMAIL,WHATSAPP'],
            'sasaran' => ['required', 'string', 'max:40'],
            'perihal' => ['nullable', 'string', 'max:160'],
            'pesan' => ['required', 'string', 'max:4000'],
        ], [], ['kanal' => 'kanal', 'sasaran' => 'sasaran', 'perihal' => 'perihal', 'pesan' => 'pesan']);

        $penerima = Siaran::penerima($data['sasaran']);
        if ($penerima->isEmpty()) {
            return redirect()->route('admin.broadcast')
                ->with('galat', 'Tidak ada penerima aktif untuk sasaran itu.');
        }

        $kanal = $data['kanal'];
        $perihal = (string) ($data['perihal'] ?? '');
        $terkirim = 0;
        $gagal = [];
        $contoh = [];

        if ($kanal === 'EMAIL') {
            foreach ($penerima as $p) {
                $isi = Siaran::isi_pesan($data['pesan'], $p, $perihal);
                try {
                    Mail::raw($isi, fn ($m) => $m->to($p->email)
                        ->subject($perihal !== '' ? $perihal : 'Informasi Agrinas Hub'));
                    $terkirim++;
                } catch (\Throwable $e) {
                    $gagal[] = $p->email.' — '.mb_substr($e->getMessage(), 0, 160);
                }
                if (count($contoh) < 2) {
                    $contoh[$p->email] = $isi;
                }
            }
        }

        // WhatsApp lewat gerbang Baileys (kontainer terpisah). Nomor diambil dari
        // profil SIMKOPDES; yang tidak punya nomor dicatat, bukan digagalkan diam.
        if ($kanal === 'WHATSAPP') {
            if (! $this->whatsapp->status()['siap']) {
                return redirect()->route('admin.broadcast')
                    ->with('galat', 'Gerbang WhatsApp belum tersambung. Klik "Koneksikan WhatsApp" di samping kolom Kanal, lalu pindai QR-nya.');
            }
            foreach ($penerima as $p) {
                if (trim((string) $p->telepon) === '') {
                    $gagal[] = $p->nama.' — nomor WhatsApp belum ada di profil';
                    continue;
                }
                $isi = Siaran::isi_pesan($data['pesan'], $p, $perihal);
                $hasil = $this->whatsapp->kirim((string) $p->telepon, $isi);
                if ($hasil['ok']) {
                    $terkirim++;
                } else {
                    $gagal[] = $p->telepon.' — '.($hasil['galat'] ?? 'gagal kirim');
                }
                if (count($contoh) < 2) {
                    $contoh[$p->nama.' '.$p->telepon] = $isi;
                }
            }
        }

        $catatan = Siaran::create([
            'kanal' => $kanal, 'perihal' => $perihal, 'pesan' => $data['pesan'],
            'sasaran' => $data['sasaran'], 'jumlah' => $terkirim,
            'pengirim' => (string) $request->user()->email, 'dibuat' => kini(),
        ]);

        jurnal($request->user()->id, 'SIARAN', $catatan->id.':'.$kanal,
            'sasaran='.$data['sasaran'].' penerima='.$penerima->count().' terkirim='.$terkirim,
            $request->ip() ?? '');

        $mailer = (string) config('mail.default');

        return view('admin.broadcast-hasil', [
            'aktif' => 'siaran', 'siaran' => $catatan, 'kanal' => $kanal,
            'penerima' => $penerima, 'terkirim' => $terkirim, 'gagal' => $gagal,
            'contoh' => $contoh, 'perihal' => $perihal, 'mailer' => $mailer,
        ]);
    }

    /** CSV tujuan broadcast — arsip tujuan kalau perlu dikirim ulang manual. */
    public function siaran_tujuan(int $id)
    {
        $s = Siaran::findOrFail($id);
        $penerima = Siaran::penerima($s->sasaran);

        $keluar = fopen('php://temp', 'r+');
        // Titik koma: Excel berlokal Indonesia memakai pemisah itu.
        fputcsv($keluar, ['Nama', 'Koperasi', 'Email', 'Pesan'], ';');
        foreach ($penerima as $p) {
            fputcsv($keluar, [$p->nama, $p->koperasi, $p->email,
                Siaran::isi_pesan($s->pesan, $p, $s->perihal)], ';');
        }
        rewind($keluar);

        return response(stream_get_contents($keluar), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="siaran-'.$id.'.csv"',
        ]);
    }
}

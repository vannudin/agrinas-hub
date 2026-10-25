<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Koperasi;
use App\Models\User;
use App\Services\SimkopdesAbsensi;
use App\Support\BatasLogin;
use App\Support\ScryptHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class MasukController extends Controller
{
    public function __construct(
        private readonly SimkopdesAbsensi $absen,
    ) {}

    public function formulir(Request $request)
    {
        if ($request->user()) {
            return redirect(rute_dasbor($request->user()));
        }

        return view('masuk');
    }

    public function aksi(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'sandi' => ['required', 'string'],
        ], [], ['email' => 'email', 'sandi' => 'sandi']);

        $email = mb_strtolower(trim($data['email']));
        $ip = $request->ip() ?? '';

        // Captcha Cloudflare Turnstile: bot berhenti di sini, tidak sampai
        // menyentuh penghitung BatasLogin maupun query ke tabel users.
        if (! $this->lolosTurnstile($request)) {
            return redirect('/masuk')->withInput()
                ->withErrors(['sandi' => 'Verifikasi keamanan (Cloudflare) gagal. Muat ulang halaman, lalu coba lagi.']);
        }

        if (! BatasLogin::boleh($email)) {
            return redirect('/masuk?galat=Percobaan+terlalu+banyak.+Coba+lagi+15+menit.');
        }

        // Satu query, bukan dua: jawaban "belum aktif" dan "sandi salah" harus
        // diputuskan dari baris yang sama supaya akun non-aktif tidak terbaca
        // sebagai "sandi salah" (dulu: user mengulang terus, layar seperti
        // menggantung karena tidak ada petunjuk apa pun).
        $u = User::where('email', $email)->first();

        // Akun pendamping hasil impor belum punya sandi sama sekali: mengetik
        // sandi apa pun bukan "salah", tapi belum pernah dibuat. Beri tahu DI
        // HALAMAN INI beserta tombol aktivasi — tanpa berpindah halaman, karena
        // lompatan ke /aktivasi itulah yang terbaca sebagai "loading" tak habis.
        // Akun yang sudah tersinkron dari SIMKOPDES sudah punya sandi, jadi
        // tidak pernah masuk cabang ini (permintaan Bos 2026-10-25).
        if ($u && trim((string) $u->sandi) === '') {
            return redirect('/masuk')->withInput()
                ->withErrors(['sandi' => 'Akun ini belum aktif. Aktifkan dulu sekali saja: cocokkan tanggal lahir, lalu buat sandi Anda sendiri.'])
                ->with('aktivasi', $email);
        }

        if ($u && ! $u->aktif) {
            return redirect('/masuk')->withInput()
                ->withErrors(['sandi' => 'Akun ini dinonaktifkan admin. Hubungi admin Agrinas lewat menu Aspirasi.']);
        }

        if ($u && ScryptHasher::cek($data['sandi'], $u->sandi)) {
            // Naikkan diam-diam hash warisan satu lapis ke dua lapis. Hanya di
            // sini sandi mentah tersedia, jadi hanya di sini pula bisa.
            if (ScryptHasher::perluDiperbarui($u->sandi)) {
                $u->forceFill(['sandi' => ScryptHasher::buat($data['sandi'])])->save();
            }

            // Login TIDAK menghubungi SIMKOPDES sama sekali (Bos 2026-10-24:
            // stand alone). Tarikan profil/absensi/kontrak dulu menempel di sini
            // dan tiap kali membakar waktu saat SIMKOPDES memblokir server;
            // sekarang semuanya lewat tombol manual di halaman masing-masing
            // (/profil "Tarik ulang dari SIMKOPDES", Absen KDKMP, Kontrak).
            //
            // Pautan koperasi tetap dijalankan: seluruh bahannya dari blob
            // profil yang sudah tersimpan, tanpa panggilan API.
            $this->sambungkanKoperasi($u);

            return $this->masukkan($request, $u, $email, $ip);
        }

        // SIMKOPDES DIPUTUS (permintaan Bos 2026-10-24): Hub berdiri sendiri.
        // Login otomatis ke SIMKOPDES dihapus — panggilan itu selalu ditolak
        // (reCAPTCHA/403) dan hanya menghasilkan pesan "SIMKOPDES sedang
        // membatasi login" yang membingungkan. Sandi yang sah satu-satunya
        // adalah sandi Hub; akun hasil impor memakai /aktivasi.
        // Pintu token peramban (lewatToken) tetap ada: itu tindakan pemilik akun
        // sendiri, bukan login otomatis dari server.
        BatasLogin::catat($email);
        jurnal(null, 'MASUK_GAGAL', $email, '', $ip);

        return redirect('/masuk?galat=Email+atau+sandi+salah.');
    }

    /**
     * Cloudflare Turnstile — verifikasi captcha di sisi server.
     *
     * Kunci kosong = captcha dimatikan (lingkungan pengembangan tanpa .env).
     * Kalau Cloudflare tidak terjangkau, login TIDAK dikunci: gangguan pihak
     * ketiga tidak boleh membuat semua pengguna tak bisa masuk.
     *
     * ponytail: gagal-terbuka saat jaringan galat; ganti jadi gagal-tertutup
     * kalau ternyata ada bot yang memanfaatkan blokir ke Cloudflare.
     */
    private function lolosTurnstile(Request $request): bool
    {
        $secret = (string) config('services.turnstile.secret');
        if ($secret === '') {
            return true;
        }

        // Widget Turnstile hanya sah untuk domain yang terdaftar di widget
        // (agrinashub.com). Akses lewat localhost/LAN — dipakai Bos & pengembang
        // — tidak bisa memuat widget, jadi jangan dikunci: host publik tetap
        // wajib captcha, host lain lewat. Aman karena Host publik hanya bisa
        // dicapai lewat tunnel Cloudflare.
        $publik = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($publik && $request->getHost() !== $publik) {
            return true;
        }

        $token = (string) $request->input('cf-turnstile-response', '');
        if ($token === '') {
            return false;
        }

        try {
            $jawab = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ])->json();
        } catch (\Throwable $e) {
            report($e);

            return true;
        }

        return (bool) ($jawab['success'] ?? false);
    }

    /**
     * Masuk memakai token SIMKOPDES yang dibawa peramban pengguna sendiri.
     *
     * Login otomatis dari server sudah ditolak SIMKOPDES (butuh reCAPTCHA yang
     * hanya lolos dari peramban pengguna), jadi tokennya diambil dari tab
     * SIMKOPDES miliknya lalu ditempel di sini. Token dibaca SEKALI untuk
     * memastikan pemiliknya, lalu disimpan untuk keperluan absensi.
     */
    public function lewatToken(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:300'],
        ], [], ['token' => 'token']);

        $ip = $request->ip() ?? '';
        $token = trim($data['token']);

        try {
            $profil = $this->absen->profilToken($token);
        } catch (\Throwable $e) {
            report($e);

            return redirect('/masuk?galat=Token+SIMKOPDES+tidak+berlaku+atau+sudah+kedaluwarsa.+Ambil+ulang+dari+tab+SIMKOPDES.');
        }

        $email = mb_strtolower(trim((string) ($profil['email'] ?? '')));
        if ($email === '') {
            return redirect('/masuk?galat=Profil+SIMKOPDES+tidak+memuat+email.');
        }

        if (! BatasLogin::boleh($email)) {
            return redirect('/masuk?galat=Percobaan+terlalu+banyak.+Coba+lagi+15+menit.');
        }

        $u = User::where('email', $email)->first() ?? User::create([
            'email' => $email,
            'nama' => (string) ($profil['name'] ?? $email),
            'peran' => Domain::PERAN_MANAJER,
            // Sandi acak: pintu akun ini adalah token SIMKOPDES, bukan sandi.
            'sandi' => ScryptHasher::buat(bin2hex(random_bytes(16))),
            'sandi_sumber' => 'SIMKOPDES',
            'simkopdes_id' => (int) ($profil['id'] ?? 0) ?: null,
            'wajib_ganti' => 0,
            'aktif' => 1,
            'dibuat' => kini(),
        ]);

        $u->forceFill(['nama' => (string) ($profil['name'] ?? $u->nama), 'aktif' => 1])->save();

        $fac = (int) ($profil['regional_facilitator']['regional_facilitator_id'] ?? 0);
        if ($fac > 0 && (int) ($u->simkopdes_facilitator_id ?? 0) !== $fac) {
            $u->forceFill(['simkopdes_facilitator_id' => $fac])->save();
        }

        try {
            $this->absen->simpanTokenBrowser($u, $token);
        } catch (\Throwable $e) {
            report($e); // absensi boleh gagal dihubungkan; masuk tetap jalan
        }

        $this->simpanProfil($u, $profil, 0);
        $this->sambungkanKoperasi($u);
        jurnal($u->id, 'SIMP_TOKEN', $email, 'masuk lewat token peramban', $ip);

        return $this->masukkan($request, $u, $email, $ip);
    }

    /**
     * Tautkan akun ke koperasi binaannya memakai blob profil yang SUDAH tersimpan.
     *
     * Dipisah dari jalur login SIMKOPDES karena akun yang sandinya sudah tersalin
     * masuk lewat jalur lokal: SIMKOPDES tidak dipanggil lagi, jadi pautan yang
     * dulu gagal (kode kabupaten bentrok) tidak akan pernah diperbaiki. Seluruh
     * isi yang dibutuhkan ada di blob — tidak ada panggilan API di sini.
     *
     * Tidak bisa menggagalkan login: kegagalan apa pun dilaporkan, lalu dilewati.
     */
    private function sambungkanKoperasi(User $u): void
    {
        try {
            if (Koperasi::where('manajer_id', $u->id)->exists()) {
                return; // sudah tertaut
            }

            app(\App\Services\ProfilPenarik::class)->tautkanKoperasi($u);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Simpan profil dari payload login; kembalikan true bila sungguh tersimpan. */
    private function simpanProfil(User $u, array $profil, ?int $koperasiId): bool
    {
        try {
            return app(\App\Services\ProfilPenarik::class)->dariLogin($profil, $koperasiId);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function masukkan(Request $request, User $u, string $email, string $ip): \Illuminate\Http\RedirectResponse
    {
        Auth::login($u);
        $request->session()->regenerate();
        BatasLogin::bersihkan($email);

        $u->forceFill(['masuk_terakhir' => kini()])->save();
        jurnal($u->id, 'MASUK', $email, '', $ip);

        // Pengumuman PRIBADI (mis. kredensial AgriConnect) harus terbaca di layar
        // pertama sesudah masuk. Flash, bukan variabel biasa: hanya berlaku untuk
        // SATU permintaan ini, jadi tidak mengganggu halaman berikutnya.
        try {
            if (\App\Models\PengumumanBaca::pribadiBelumDibaca($u)->isNotEmpty()) {
                $request->session()->flash('pribadi_baru', true);
            }
        } catch (\Throwable $e) {
            report($e); // pengumuman gagal dihitung tidak boleh menggagalkan login
        }

        return redirect(rute_dasbor($u));
    }

    /**
     * Aktivasi sekali pakai untuk akun pendamping hasil impor.
     *
     * Akun-akun itu belum punya sandi, jadi tidak ada yang bisa dipakai masuk.
     * Pemiliknya membuktikan diri dengan TANGGAL LAHIR (data yang hanya dia dan
     * SIMKOPDES tahu), lalu membuat sandi Hub sendiri. Sesudah itu kolom sandi
     * terisi dan jalur ini tertutup untuknya — buka /aktivasi lagi hanya
     * menghasilkan "akun sudah aktif".
     */
    public function aktivasi(Request $request)
    {
        if ($request->user()) {
            return redirect(rute_dasbor($request->user()));
        }

        return view('aktivasi', ['email' => mb_strtolower(trim((string) $request->query('email', '')))]);
    }

    public function aktivasiAksi(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d'],
            'sandi' => ['required', 'string', 'min:10'],
            'ulang' => ['required', 'string', 'same:sandi'],
        ], [
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.date_format' => 'Tanggal lahir harus lengkap: hari, bulan, tahun.',
            'sandi.min' => 'Sandi minimal 10 karakter.',
            'ulang.same' => 'Ulangi sandi tidak sama.',
        ], ['email' => 'email', 'tanggal_lahir' => 'tanggal lahir', 'sandi' => 'sandi baru', 'ulang' => 'ulangi sandi']);

        $email = mb_strtolower(trim($data['email']));
        $ip = $request->ip() ?? '';

        // Captcha Turnstile juga di sini: halaman aktivasi menebak tanggal lahir,
        // jadi sama-sama sasaran percobaan berulang.
        if (! $this->lolosTurnstile($request)) {
            return back()->withInput()
                ->withErrors(['sandi' => 'Verifikasi keamanan (Cloudflare) gagal. Muat ulang halaman, lalu coba lagi.']);
        }

        if (! BatasLogin::boleh($email)) {
            return redirect('/aktivasi?email='.urlencode($email)
                .'&galat=Percobaan+terlalu+banyak.+Coba+lagi+15+menit.');
        }

        $u = User::where('email', $email)->where('aktif', 1)->first();

        if (! $u) {
            return redirect('/aktivasi?email='.urlencode($email)
                .'&galat=Email+tidak+terdaftar.+Pakai+email+SIMKOPDES+Anda+yang+berakhiran+%2Bsppi.');
        }

        if (trim((string) $u->sandi) !== '') {
            return redirect('/masuk?galat=Akun+ini+sudah+aktif.+Masuk+dengan+sandi+Anda.');
        }

        if (trim((string) $u->tanggal_lahir) === '') {
            return redirect('/masuk?galat=Tanggal+lahir+akun+ini+belum+tercatat.+Hubungi+admin+Agrinas.');
        }

        if ($u->tanggal_lahir !== $data['tanggal_lahir']) {
            BatasLogin::catat($email);
            jurnal($u->id, 'AKTIVASI_GAGAL', $email, 'tanggal lahir tidak cocok', $ip);

            return redirect('/aktivasi?email='.urlencode($email)
                .'&galat=Tanggal+lahir+tidak+cocok+dengan+data+SIMKOPDES.');
        }

        $u->forceFill(['sandi' => ScryptHasher::buat($data['sandi']),
            'sandi_sumber' => 'HUB', 'wajib_ganti' => 0])->save();

        jurnal($u->id, 'AKTIVASI', $email, '', $ip);

        return $this->masukkan($request, $u, $email, $ip);
    }

    public function keluar(Request $request)
    {
        $u = $request->user();
        if ($u) {
            jurnal($u->id, 'KELUAR', $u->email, '', $request->ip() ?? '');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

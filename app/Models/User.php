<?php

namespace App\Models;

use App\Support\Wilayah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Crypt;

class User extends Authenticatable
{
    protected $connection = 'hub';
    protected $table = 'users';
    public $timestamps = false;

    protected $fillable = ['email', 'nama', 'peran', 'sandi', 'koperasi_id', 'wilayah',
                           'wajib_ganti', 'aktif', 'dibuat', 'masuk_terakhir',
                           'simkopdes_id', 'koperasi_simkopdes_id', 'sandi_sumber',
                           'simkopdes_facilitator_id', 'simkopdes_sandi', 'simkopdes_token', 'vendor_id',
                           'tanggal_lahir'];
    protected $hidden = ['sandi', 'simkopdes_sandi', 'simkopdes_token'];

    protected function casts(): array
    {
        return ['wajib_ganti' => 'boolean', 'aktif' => 'boolean'];
    }

    /**
     * Tanggal lahir disimpan TERENKRIPSI (AES-256-GCM, kunci APP_KEY).
     *
     * Dipakai accessor, bukan cast `encrypted`, karena kolom ini sudah berisi
     * campuran: baris lama masih teks biasa dan baris kosong tetap '' (kolom
     * NOT NULL). Cast akan melempar "The payload is invalid" untuk keduanya dan
     * mematikan halaman yang kebetulan membacanya — accessor ini membacanya
     * sebagai nilai apa adanya, sedangkan setiap TULISAN baru tetap disandi.
     *
     * Tanggal lahir adalah bukti identitas untuk aktivasi: kalau bocor bersama
     * email, akun bisa diambil alih. Karena itu ia tidak boleh dipakai sebagai
     * syarat WHERE/LIKE — saring lewat model.
     */
    protected function tanggalLahir(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => self::bukaTanggal($v),
            set: fn ($v) => $v === null || trim((string) $v) === ''
                ? ''
                : Crypt::encryptString(trim((string) $v)),
        );
    }

    private static function bukaTanggal(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        try {
            return (string) Crypt::decryptString((string) $v);
        } catch (\Throwable $e) {
            // Warisan teks biasa (atau kunci berganti): pakai apa adanya
            // daripada menggagalkan seluruh halaman.
            return (string) $v;
        }
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class, 'koperasi_id');
    }

    /**
     * Profil SIMKOPDES yang SUDAH lengkap (tanggal lahir terisi).
     *
     * Dipakai sebagai hitungan `whereDoesntHave('profilLengkap')`: satu query
     * agregat untuk seluruh meja, bukan memuat 14 profil lalu memeriksanya satu
     * per satu di PHP.
     */
    public function profilLengkap(): HasOne
    {
        return $this->hasOne(Profil::class, 'user_id')
            ->whereNotNull('tanggal_lahir')->where('tanggal_lahir', '!=', '');
    }

    /** Skema warisan memakai kolom `sandi`, bukan `password`. */
    public function getAuthPassword(): string
    {
        return $this->sandi;
    }

    public function getAuthPasswordName(): string
    {
        return 'sandi';
    }

    public function agrinas(): bool
    {
        return $this->peran === Domain::PERAN_AGRINAS;
    }

    /**
     * Petugas HR / admin pusat — yang membuka meja SDM & Kepegawaian.
     *
     * Dipakai di SATU tempat (penjaga rute & menu), bukan digelar di tiap
     * halaman, supaya menambah peran HR tidak berarti memburu setiap
     * pemeriksaan `agrinas()`.
     */
    public function hrd(): bool
    {
        return in_array($this->peran, Domain::PERAN_HRD, true);
    }

    /** PIC penanggung jawab wilayah: melihat koperasi di wilayahnya, bukan seluruh negeri. */
    public function pic(): bool
    {
        return $this->peran === Domain::PERAN_PIC;
    }

    /** Petugas keuangan: urus pembayaran & kas, tanpa kendali pusat/gudang. */
    public function keuangan(): bool
    {
        return $this->peran === Domain::PERAN_KEUANGAN;
    }

    /** TNI (Babinsa/Kodim): pengawasan, hanya-baca; pemetaan lahan ikut wilayahnya. */
    public function tni(): bool
    {
        return $this->peran === Domain::PERAN_TNI;
    }

    /** Dinas Koperasi: pengawasan, hanya-baca + boleh mengunggah materi. */
    public function dinas(): bool
    {
        return $this->peran === Domain::PERAN_DINAS;
    }

    /**
     * Peran pengawasan (TNI & Dinas Koperasi). Dipakai SATU baris penjaga tulis
     * (`pantau` middleware), satu cabang menu, dan daftar materi.
     */
    public function pantau(): bool
    {
        return in_array($this->peran, Domain::PERAN_PANTAU, true);
    }

    /**
     * Akun vendor: peran VENDOR **dan** sudah tertaut ke satu baris `vendor`.
     *
     * Dua syarat, bukan satu: akun yang perannya VENDOR tetapi belum ditautkan
     * tak punya vendor untuk menyaring barang — halaman `/mitra`-nya akan
     * menampilkan SELURUH barang Agrinas. Karena itu `vendor_id` ikut diperiksa
     * di sini, satu tempat, dipakai menu, penjaga rute, dan controller.
     */
    public function vendorAkun(): bool
    {
        return $this->peran === Domain::PERAN_VENDOR && (int) $this->vendor_id > 0;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * Boleh MENANDAI pembayaran (uang keluar) — pusat & petugas keuangan.
     *
     * PIC sengaja TIDAK termasuk: ia memantau & menindaklanjuti, bukan
     * mengeluarkan uang (gate uji_pesanan_peran menegaskan PIC tak melihat
     * tombolnya). Dipakai bersama rute `bayar` dan tombol "Tandai Dibayar" /
     * "Bayar" supaya yang terlihat dan yang diizinkan server tidak berbeda.
     */
    public function bolehBayar(): bool
    {
        return $this->agrinas() || $this->keuangan();
    }

    /** Manajer koperasi (bukan pusat, bukan PIC, bukan HR). */
    public function manajerKoperasi(): bool
    {
        return $this->peran === Domain::PERAN_MANAJER;
    }

    /**
     * Peran yang MEMANTAU kehadiran manajer: admin pusat, HR, PIC wilayah, dan
     * keuangan. Mereka tidak punya presensi sendiri untuk diurus, yang dicari
     * adalah absensi para manajer — jadi halaman presensi menampilkan daftar
     * orang, bukan formulir Check In.
     *
     * Satu penanda dipakai di controller dan view (bukan tiga `in_array` yang
     * tersebar), supaya menambah peran pemantau cukup di satu tempat.
     */
    public function pemantauKehadiran(): bool
    {
        return $this->hrd() || $this->pic() || $this->keuangan() || $this->pantau();
    }

    /**
     * KUNCI pembeda persona, bukan sekadar label.
     *
     * `hrd()` sengaja TRUE untuk admin juga (keduanya membuka meja SDM), tetapi
     * admin pusat dan petugas HR memegang pekerjaan yang BERBEDA: admin mengurus
     * lini koperasi (distribusi, stok, pasokan, keuangan, penempatan), HR mengurus
     * kepegawaian (payroll, kontrak, penilaian, rekrutmen). Selama ini keduanya
     * diperlakukan sama — saran & persona di /asisten jadi HRS milik admin juga.
     * Dipakai untuk memilih persona/saran asisten; peran lain tetap 'umum'.
     */
    public function kunciAi(): string
    {
        return match (true) {
            $this->agrinas() => 'agrinas',
            $this->peran === Domain::PERAN_HR => 'hr',
            $this->keuangan() => 'keuangan',
            $this->pic() => 'pic',
            $this->manajerKoperasi() => 'manajer',
            default => 'umum',
        };
    }

    /**
     * Melihat seluruh koperasi tanpa pagar wilayah: admin pusat DAN petugas HR.
     *
     * HR ikut penuh karena pekerjaannya lintas wilayah (kontrak & penempatan
     * semua pendamping) — pagar wilayah justru memotong daftar yang harus ia
     * periksa. Halaman SDM sendiri tetap dijaga penjaga rutenya sendiri.
     */
    public function lingkupPenuh(): bool
    {
        return $this->hrd();
    }

    /**
     * Boleh membuka foto kehadiran milik pengguna lain?
     *
     * Pemilik selalu boleh. Pemantau hanya sejauh LINGKUPNYA — aturan yang sama
     * dengan daftar manajer di `/agri/presensi` (`manajerTerpantau`), supaya
     * halaman yang tersaring wilayah tidak bisa ditembus lewat URL foto:
     * PIC Jogja cukup menebak id untuk melihat wajah manajer di Papua.
     */
    public function bolehLihatKehadiran(int $pemilikId): bool
    {
        if ($this->id === $pemilikId) {
            return true;
        }

        if (! $this->pemantauKehadiran()) {
            return false;
        }

        if ($this->lingkupPenuh() || $this->keuangan()) {
            return true;
        }

        return $this->koperasiQuery()->whereNotNull('manajer_id')
            ->where('manajer_id', $pemilikId)->exists();
    }

    /**
     * Koperasi yang terjangkau pengguna ini, sudah berupa query (bukan hasil
     * tarik) supaya bisa disambung paginasi, join, dan hitungan.
     *
     * Kolomnya `koperasi.wilayah`/`koperasi.provinsi` — lihat App\Support\Wilayah.
     */
    public function koperasiQuery(): Builder
    {
        $q = Koperasi::query();

        // Keuangan ikut penuh: ia membaca kas & membayar tagihan koperasi mana
        // pun. Sengaja TIDAK lewat `lingkupPenuh()` — penanda itu dipakai view
        // untuk menampilkan wewenang gudang (lepas/batal barang) yang bukan
        // miliknya.
        if ($this->lingkupPenuh() || $this->keuangan()) {
            return $q;
        }

        // Pengawasan (TNI/Dinas) melihat koperasi DI WILAYAHNYA saja: PUSAT
        // seluruh negeri, provinsi menurut `Wilayah::saring()`, kabupaten/kota
        // lewat nama wilayah. Dulu peran ini mengembalikan semua koperasi —
        // akun TNI kabupaten membaca data seluruh Indonesia (keluhan Bos
        // 2026-10-22), padahal pagar tulisnya sudah ada di middleware `pantau`.
        if ($this->pantau()) {
            $s = Wilayah::saringLingkup((string) $this->wilayah);

            return $s ? $q->where($s) : $q;
        }

        if ($this->pic()) {
            return $q->where(Wilayah::saring((string) $this->wilayah));
        }

        // Manajer: koperasi yang dia pimpin, dicatat lewat koperasi.manajer_id —
        // kolom users.koperasi_id tidak pernah diisi di hub ini.
        return $q->where('manajer_id', $this->id);
    }

    /**
     * Koperasi milik manajer ini (satu orang satu koperasi di hub ini).
     * Dipakai controller yang butuh objeknya, bukan query.
     */
    public function koperasiDiampu(): ?Koperasi
    {
        return Koperasi::where('manajer_id', $this->id)->first();
    }

    /**
     * Boleh menyentuh koperasi ini? Pusat selalu; PIC hanya kalau koperasinya
     * masuk wilayahnya; manajer hanya koperasinya sendiri. Dipakai sebagai satu
     * penjagaan di controller supaya tidak ada jalur yang terlewat.
     */
    public function bolehKoperasi(int $koperasiId): bool
    {
        return $this->koperasiQuery()->whereKey($koperasiId)->exists();
    }
}

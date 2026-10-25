<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Asisten AI Agrinas Hub — penerus langsung 9Router lokal (endpoint
 * OpenAI-compatible di 127.0.0.1:20128, model sama yang dipakai Hermes).
 *
 * Desainnya sengaja datar: satu metode `tanya()` yang mengirim riwayat pesan
 * lalu mengembalikan teks jawaban. Tidak ada agen, tidak ada tool-calling,
 * tidak ada penyimpanan percakapan — percakapan hidup di sesi Laravel.
 *
 * PENTING: konteks yang dikirim ke model adalah RINGKASAN ANGKA yang berbeda
 * per peran (manajer: koperasinya sendiri; admin: seluruh koperasi). Itu
 * menjaga asisten berguna tanpa membocorkan data koperasi lain ke manajer.
 */
class AsistenAi
{
    /** Batas percakapan yang disimpan di sesi (pasangan tanya-jawab). */
    public const MAKS_TUR = 6;

    /**
     * Persona asisten per peran — SATU sumber kebenaran.
     *
     * Dijadikan konstanta supaya bisa diuji tanpa memanggil model: cukup
     * pastikan keempatnya ada dan benar-benar BERBEDA (dulu hanya ada dua
     * bentuk, sehingga admin pusat mendapat persona HR).
     */
    public const PERSONA = [
        'agrinas' => <<<'AGRINAS'
            Anda Asisten Operasi Pusat PT Agrinas Pangan Nusantara: membaca angka
            SELURUH koperasi binaan (KDKMP) dan membantu manajemen pusat mengambil
            keputusan lini.

            CARA KERJA:
            - Mulai dari ANGKA: sebut koperasi/kelompok yang menyimpang beserta
              besarannya sebelum memberi saran. Jangan menyuruh memeriksa kalau
              datanya sudah ada di DATA di bawah.
            - Prioritaskan yang berdampak lintas koperasi: stok di bawah minimum,
              distribusi/pasokan yang mandek pada satu status, keuangan negatif.
            - Untuk pertanyaan kepegawaian, jawab sebagai pengawas: tunjukkan
              ringkasan lalu arahkan ke meja SDM/HRIS. Jangan menghitung pajak
              atau nominal gaji — angka penggajian ada di Agrinas HRIS.
            - Bila diminta naskah (pengumuman, instruksi, laporan pusat), tulis
              langsung siap pakai.
            AGRINAS,
        'hr' => <<<'HR'
            Anda HR Manager senior dengan 25 tahun pengalaman di perusahaan
            distribusi & ritel nasional Indonesia: 1.000+ karyawan PKWT lintas
            provinsi, payroll multi-bank, kepatuhan BPJS & PPh 21, hubungan
            industrial, dan rekrutmen massal.

            CARA KERJA:
            - Preskriptif: beri KEPUTUSAN dan urutan langkah, bukan daftar
              kemungkinan. Sebut apa yang harus dikerjakan lebih dulu dan mengapa.
            - Setiap saran yang menyentuh uang atau legal (PHK, potongan, SP, pajak)
              disertai risiko & dasar aturannya.
            - PPh 21 pegawai tetap = TER bulanan (PP 58/2023): bruto x %TER(status
              PTKP). Tunjangan umum & uang makan KENA PPh 21. Biaya jabatan + PTKP
              tahunan hanya untuk rekonsiliasi Desember.
            - BPJS porsi pekerja: Kesehatan 1% + JHT 2% + JP 1%, dasar = gaji pokok
              + tunjangan TETAP (uang makan proporsional kehadiran TIDAK masuk).
            - JANGAN menyajikan satu angka pajak pasti bila status PTKP tidak
              diketahui; sebut variabelnya.
            HR,
        'keuangan' => <<<'KEUANGAN'
            Anda pengendali keuangan operasional KDKMP: kas koperasi, pembayaran
            pasokan UMKM, dan pelunasan order barang. Bukan akuntan pajak, bukan
            pengurus stok.

            CARA KERJA:
            - Selalu berpijak pada angka di DATA. Bila nominal/tagihan tidak ada di
              sana, katakan datanya belum tersedia — jangan mengarang rupiah.
            - Untuk setiap tagihan, sebut urutannya: apa yang harus disetujui dulu,
              siapa yang menyetujui, dan bukti apa yang wajib dilampirkan sebelum
              status berubah menjadi Dibayar.
            - Bedakan uang MASUK (penjualan koperasi) dan uang KELUAR (pembelian
              barang, pasokan UMKM). Selisihnya yang dilaporkan, bukan totalnya.
            - Angka penggajian/pajak dihitung di Agrinas HRIS, bukan di Hub —
              arahkan ke sana bila ditanya.
            KEUANGAN,
        'pic' => <<<'PIC'
            Anda pendamping operasi untuk PIC Wilayah: membantu mengurus koperasi
            di SATU wilayah, bukan kebijakan nasional.

            CARA KERJA:
            - Selalu berpijak pada wilayah pengguna. Bila angka wilayah tidak ada
              di DATA, katakan belum tersedia — jangan memakai angka nasional
              seolah-olah itu wilayahnya.
            - Fokus pekerjaan lapangan: koperasi mana yang perlu perhatian,
              kesiapan manajer, distribusi/pasokan yang tertahan, keuangan yang
              perlu ditertibkan, dan rekap berkala wilayah.
            - Bila diminta laporan atau surat untuk koperasi binaan, tulis
              langsung siap pakai.
            PIC,
        'manajer' => <<<'MANAJER'
            Anda pendamping kerja manajer KDKMP: membantu mengelola SATU koperasi
            — keuangan harian, stok, pasokan UMKM, tugas, dan laporan.

            CARA KERJA:
            - Selalu berpijak pada koperasi pengguna (lihat DATA). Jangan memakai
              angka koperasi lain; itu memang tidak dikirim ke Anda.
            - Praktis dan bertahap: apa yang dikerjakan hari ini, apa yang ditunggu
              pengelola pusat, apa yang perlu dilaporkan.
            - Bila diminta naskah (laporan mingguan/bulanan, pengajuan barang),
              tulis langsung siap pakai.
            MANAJER,
        'umum' => <<<'UMUM'
            Pengguna Anda adalah pengguna Agrinas Hub. Jawab ringkas, bantu membaca
            angka yang tersedia, dan tulis naskah dokumen langsung siap pakai bila
            diminta.
            UMUM,
    ];

    /** Awalan gaya jawaban untuk tiap persona — dipakai uji sebagai penanda. */
    public const TANDA_PERSONA = [
        'agrinas' => 'Asisten Operasi Pusat',
        'hr' => 'HR Manager senior',
        'keuangan' => 'pengendali keuangan operasional',
        'pic' => 'PIC Wilayah',
        'manajer' => 'manajer KDKMP',
        'umum' => 'pengguna Agrinas Hub',
    ];

    /** Panggil 9Router. Mengembalikan teks jawaban, atau melempar RuntimeException. */
    public function tanya(array $riwayat, string $konteks, string $persona = 'umum', ?string $model = null): string
    {
        $cfg = config('services.ai');
        $pesan = [['role' => 'system', 'content' => $this->sistem($konteks, $persona)], ...$riwayat];

        $res = Http::withToken($cfg['api_key'])
            ->timeout($cfg['timeout'] ?? 90)
            ->post(rtrim($cfg['base_url'], '/').'/chat/completions', [
                'model' => $model ?: $cfg['model'],
                'messages' => $pesan,
                'temperature' => 0.3,
                // WAJIB longgar untuk model reasoning: seluruh anggaran token
                // dipakai berpikir lebih dulu, dan anggaran kecil membuat
                // `content` pulang kosong (terbaca "tidak menjawab").
                'max_tokens' => (int) ($cfg['max_tokens'] ?? 3000),
            ]);

        if (! $res->successful()) {
            throw new \RuntimeException('Asisten menjawab HTTP '.$res->status().': '
                .mb_substr((string) $res->body(), 0, 200));
        }

        $teks = (string) ($res->json('choices.0.message.content') ?? '');
        if (trim($teks) === '') {
            // Kehabisan anggaran token BUKAN "tidak menjawab": model reasoning
            // menghabiskan seluruh jatah untuk berpikir. Sebut sebabnya supaya
            // tidak dicari-cari sebagai galat koneksi.
            $sebab = (string) ($res->json('choices.0.finish_reason') ?? '');
            throw new \RuntimeException($sebab === 'length'
                ? 'Jawaban terpotong sebelum selesai (anggaran token habis). Naikkan AI_MAX_TOKENS.'
                : 'Asisten tidak mengembalikan jawaban.');
        }

        return $teks;
    }

    /** Instruksi sistem + ringkasan data peran pemanggil. */
    private function sistem(string $konteks, string $peran = 'umum'): string
    {
        $tanggal = now()->translatedFormat('d M Y');

        // Persona berbeda per peran. Sebelumnya hanya DUA bentuk ('umum'
        // vs 'hr'), sehingga admin pusat — yang pekerjaannya lini koperasi —
        // mendapat persona HR. Sekarang empat bentuk, dipilih lewat
        // User::kunciAi(); peran tak dikenal tetap jatuh ke 'umum'.
        $diri = self::PERSONA[$peran] ?? self::PERSONA['umum'];

        return <<<TXT
        {$diri}

        Lingkungan Anda: Agrinas Hub, aplikasi koordinasi koperasi desa (KDKMP)
        PT Agrinas Pangan Nusantara.

        ATURAN (berlaku untuk semua peran):
        - Jawab dalam Bahasa Indonesia, ringkas dan konkret.
        - Pakai HANYA angka pada DATA di bawah. Bila datanya tidak ada, katakan
          datanya belum tersedia — jangan mengarang angka.
        - Hub TIDAK menyimpan angka penggajian: nominal gaji dihitung & dibayarkan
          di Agrinas HRIS. Dari Hub hanya ada rekening tujuan, keanggotaan BPJS,
          dan RUJUKAN slip (nomor + nama berkas). Jangan mengarang rupiah.
        - Jangan pernah menampilkan atau meminta kredensial/sandi.
        - Fitur Hub yang boleh Anda rujuk (jangan mengarang fitur lain):
          Dashboard, Toko (pesanan pembelian & penjualan koperasi), Distribusi
          barang Agrinas, Pasokan UMKM + pembayarannya, Keuangan, Persetujuan,
          Absen Agrinashub, Presensi Manajer, HRIS AgriConnect (absensi, cuti,
          profil, BPJS, keluarga, performa, gaji), SDM/HR, Rekrutmen, Kontrak,
          Sarana & Lahan, Pembelajaran (materi, poin, imbalan), Konferensi,
          Message, Aspirasi, Pusat Informasi (/artikel), Wiki, Dokumen.
        - Bila ditanya "di mana/menu apa", sebut nama menunya; jangan menyuruh
          pengguna menghitung ulang angka yang sudah ada di DATA.

        DATA (ringkasan per {$tanggal}, sama untuk seluruh percakapan ini):
        {$konteks}
        TXT;
    }
}

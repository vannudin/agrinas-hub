<?php

namespace App\Support;

use App\Models\Dokumen;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Penyambung ONLYOFFICE Docs (kontainer di localhost:80, publik di
 * office.advi.dev). Tugasnya dua: menandatangani token JWT yang diminta editor
 * dan menyusun config editor untuk satu dokumen.
 *
 * Tanda tangan dibuat sendiri (HS256) karena firebase/php-jwt tidak terpasang —
 * protokolnya cuma base64url(header).base64url(payload) di-HMAC-SHA256, jadi
 * menambah dependensi hanya untuk itu tidak sepadan.
 */
class OnlyOffice
{
    /** Editor dibuka sebagai <script src=".../api.js"> di halaman Blade. */
    public static function dasar(): string
    {
        return rtrim((string) config('services.onlyoffice.url'), '/');
    }

    public static function siap(): bool
    {
        return self::dasar() !== '' && (string) config('services.onlyoffice.secret') !== '';
    }

    /** HS256 JWT. Payload apa pun (array) -> token bertitik-tiga. */
    public static function token(array $klaim): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $kepala = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $isi = $b64(json_encode($klaim));
        $tanda = $b64(hash_hmac('sha256', $kepala.'.'.$isi,
            (string) config('services.onlyoffice.secret'), true));

        return $kepala.'.'.$isi.'.'.$tanda;
    }

    /**
     * Config editor untuk satu dokumen. `$tautan` = URL yang bisa diambil
     * ONLYOFFICE (harus publik); `$callback` = URL simpan kita, juga ditandatangani.
     */
    /**
     * `$sunting` = boleh menyunting. Dokumen publik untuk pengguna yang BELUM
     * diberi izin dibuka sebagai lihat-saja — bukan disembunyikan, bukan juga
     * dibiarkan bisa diubah orang yang tidak diminta.
     */
    public static function config(Dokumen $dok, User $u, string $tautan, string $callback,
                                 bool $sunting = true): array
    {
        $ext = $dok->jenis;
        $jenis = match ($ext) {
            'xlsx' => 'cell',
            'pptx' => 'slide',
            default => 'word',
        };

        $cfg = [
            'documentType' => $jenis,
            'document' => [
                'fileType' => $ext,
                'key' => $dok->kunci,
                'title' => $dok->judul.'.'.$ext,
                'url' => $tautan,
                'permissions' => [
                    'edit' => $sunting, 'download' => true, 'print' => true,
                    'review' => true, 'comment' => true, 'chat' => true,
                ],
            ],
            'editorConfig' => [
                'mode' => $sunting ? 'edit' : 'view',
                'lang' => 'id',
                'callbackUrl' => $callback,
                // Nama tampil peserta; ONLYOFFICE memakai ini untuk kursor bersama.
                'user' => ['id' => (string) $u->id, 'name' => (string) $u->nama],
                'customization' => [
                    'autosave' => true, 'forcesave' => true,
                    'comments' => true,
                    'compactHeader' => true,
                    'help' => false,
                    'uiTheme' => 'theme-classic-light',
                ],
                // Kolaborasi real-time + penyisipan peta pikiran lewat plugin bawaan.
                'coEditing' => ['mode' => 'fast', 'change' => true],
                'plugins' => ['autostart' => [], 'pluginsData' => []],
            ],
        ];

        // Token di dalam body: ONLYOFFICE menerima `token` di akar config.
        $cfg['token'] = self::token($cfg);

        return $cfg;
    }

    /** URL berkas untuk diambil ONLYOFFICE. Ditandatangani supaya tidak bisa ditebak. */
    public static function tautan_berkas(Dokumen $dok): string
    {
        return route('workspace.dokumen.berkas', ['id' => $dok->id, 't' => self::token(['d' => $dok->id, 'exp' => time() + 3600])]);
    }

    /**
     * Terjemahkan teks (dipakai Messenger). Mengembalikan null bila asisten
     * tidak siap — pemanggil menyimpan pesan aslinya saja.
     */
    public static function terjemahkan(string $teks, string $sasaran): ?string
    {
        if ($teks === '' || ! config('services.ai.api_key')) {
            return null;
        }
        try {
            $res = \Illuminate\Support\Facades\Http::withToken((string) config('services.ai.api_key'))
                ->timeout((int) config('services.ai.timeout', 60))
                ->post(rtrim((string) config('services.ai.base_url'), '/').'/chat/completions', [
                    'model' => config('services.ai.model'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'Anda penerjemah. Terjemahkan teks pengguna ke '
                            .$sasaran.'. Keluarkan HANYA hasil terjemahan, tanpa penjelasan, tanpa tanda kutip.'],
                        ['role' => 'user', 'content' => $teks],
                    ],
                    'temperature' => 0.1,
                    'max_tokens' => 900,
                ]);
            $isi = trim((string) $res->json('choices.0.message.content'));

            return $isi === '' ? null : $isi;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Gerbang WhatsApp (Baileys) di kontainer terpisah — 127.0.0.1:8092.
 *
 * Kontainer itu yang memegang sesi WhatsApp; Hub hanya menitip pesan lewat
 * HTTP. Sesi (kredensial) sengaja TIDAK disimpan di Hub supaya pencabutan
 * perangkat cukup dilakukan di ponsel, dan tidak ada rahasia WA di basis data
 * aplikasi.
 */
class Whatsapp
{
    private function http()
    {
        $h = Http::baseUrl(rtrim((string) config('services.whatsapp.url'), '/'))
            ->acceptJson()->timeout(20)->connectTimeout(5);

        $token = (string) config('services.whatsapp.token');

        return $token !== '' ? $h->withHeaders(['x-token' => $token]) : $h;
    }

    public function terkonfigurasi(): bool
    {
        return (string) config('services.whatsapp.url') !== '';
    }

    /** @return array{siap:bool,nomor:?string,qr:bool,putus:?string,hidup:bool} */
    public function status(): array
    {
        $kosong = ['siap' => false, 'nomor' => null, 'qr' => false, 'putus' => null, 'hidup' => false];
        if (! $this->terkonfigurasi()) {
            return $kosong;
        }

        try {
            $r = $this->http()->get('/status');
        } catch (\Throwable) {
            return $kosong;
        }

        if (! $r->ok()) {
            return $kosong;
        }

        return [
            'siap' => (bool) $r->json('siap'),
            'nomor' => $r->json('nomor'),
            'qr' => (bool) $r->json('qr'),
            'putus' => $r->json('putus'),
            'hidup' => true,
        ];
    }

    /** Gambar QR pemindaian (PNG) — null kalau belum ada. */
    public function qrPng(): ?string
    {
        try {
            $r = $this->http()->get('/qr.png');
        } catch (\Throwable) {
            return null;
        }

        return $r->ok() ? $r->body() : null;
    }

    /**
     * Putuskan sesi (logout perangkat tertaut).
     *
     * @return bool siap sesudah tindakan — false kalau berhasil diputus.
     */
    public function putus(): bool
    {
        try {
            $this->http()->post('/putus');
        } catch (\Throwable) {
            // gerbang mati: tidak ada sesi yang bisa diputus
        }

        return $this->status()['siap'];
    }

    /**
     * Kirim satu pesan teks.
     *
     * @return array{ok:bool,galat:?string,id:?string}
     */
    public function kirim(string $nomor, string $pesan): array
    {
        try {
            $r = $this->http()->post('/kirim', ['nomor' => $nomor, 'pesan' => $pesan]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'galat' => mb_substr($e->getMessage(), 0, 160), 'id' => null];
        }

        return [
            'ok' => $r->ok() && (bool) $r->json('terkirim'),
            'galat' => $r->ok() ? null : (string) ($r->json('galat') ?? 'HTTP '.$r->status()),
            'id' => $r->json('id'),
        ];
    }
}

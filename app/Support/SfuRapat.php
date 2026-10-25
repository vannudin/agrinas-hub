<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cloudflare Realtime SFU — perantara media rapat video.
 *
 * Media (audio/video) mengalir LANGSUNG dari browser peserta ke Cloudflare;
 * server ini hanya mengurus "siapa menerbitkan apa" lewat Connection API.
 * Karena itu rapat jalan tanpa port masuk, tanpa UDP, tanpa VPS — dan tidak
 * lagi bergantung pada server pihak ketiga (meet.jit.si).
 *
 * Rapat bersifat DUA ARAH: setiap peserta menerbitkan kamera + mikrofonnya
 * sendiri dan menerima jalur milik peserta lain. Satu PeerConnection per
 * peserta; satu sessionId SFU per PeerConnection.
 */
final class SfuRapat
{
    /** Nama jalur yang dipakai semua peserta. */
    public const JENIS = ['kamera' => 'cam', 'mikrofon' => 'mic'];

    private const BASIS = 'https://rtc.live.cloudflare.com/v1';

    /** Kredensial SFU sudah diisi? Kalau tidak, halaman jatuh ke mode tautan. */
    public static function siap(): bool
    {
        return self::appId() !== '' && self::rahasia() !== '';
    }

    /** Sesi baru (tanpa body). Satu sesi = satu PeerConnection di browser. */
    public static function sesi(): ?string
    {
        $r = self::kirim('POST', self::jalur('/sessions/new'));

        return $r['ok'] ? ($r['data']['sessionId'] ?? null) : null;
    }

    /**
     * Terbitkan jalur lokal (kamera/mikrofon) dari tawaran browser.
     *
     * @param  array<int,array{mid:string,jenis:string}>  $trek
     * @return array{ok:bool,pesan:string,description:?array}
     */
    public static function terbitkan(string $sesi, array $trek, array $offer): array
    {
        $minta = [];
        foreach ($trek as $t) {
            $minta[] = [
                'location' => 'local',
                'mid' => (string) $t['mid'],
                'trackName' => self::JENIS[$t['jenis']] ?? (string) $t['jenis'],
            ];
        }

        $r = self::kirim('POST', self::jalur('/sessions/'.$sesi.'/tracks/new'), [
            'sessionDescription' => self::sdp($offer, 'offer'),
            'tracks' => $minta,
        ]);

        if (! $r['ok']) {
            return self::gagal($r);
        }

        if (($galat = self::galatTrek($r['data'])) !== '') {
            return self::gagal($r, $galat);
        }

        return ['ok' => true, 'pesan' => '', 'description' => $r['data']['sessionDescription'] ?? null];
    }

    /**
     * Minta jalur milik peserta lain. Cloudflare yang menawarkan, browser
     * penerima menjawab lewat {@see jawab()}.
     *
     * @param  array<int,array{sesi:string,trek:string,slot:int,jenis:string}>  $minta
     * @return array{ok:bool,pesan:string,peta:array<int,array<string,string>>,description:?array}
     */
    public static function tonton(string $sesi, array $minta): array
    {
        $trek = [];
        foreach ($minta as $m) {
            $trek[] = ['location' => 'remote', 'sessionId' => $m['sesi'], 'trackName' => $m['trek']];
        }

        $r = self::kirim('POST', self::jalur('/sessions/'.$sesi.'/tracks/new'), ['tracks' => $trek]);

        if (! $r['ok']) {
            return self::gagal($r) + ['peta' => []];
        }

        // Hasil per-trek sejajar urutan permintaan; yang gagal dilewati saja.
        $peta = [];
        foreach (($r['data']['tracks'] ?? []) as $i => $t) {
            if (! empty($t['errorCode']) || ! isset($minta[$i])) {
                continue;
            }
            $peta[$minta[$i]['slot']][$minta[$i]['jenis']] = (string) ($t['mid'] ?? '');
        }

        // Tidak dapat satu pun jalur? Jangan balas "sukses" dengan peta kosong —
        // klien akan menunggu gambar yang tak pernah datang.
        if ($peta === []) {
            return self::gagal($r, self::galatTrek($r['data'])) + ['peta' => []];
        }

        return ['ok' => true, 'pesan' => '', 'peta' => $peta,
            'description' => $r['data']['sessionDescription'] ?? null];
    }

    /** Kirim jawaban (answer) browser ke Cloudflare. */
    public static function jawab(string $sesi, string $sdp): array
    {
        $r = self::kirim('PUT', self::jalur('/sessions/'.$sesi.'/renegotiate'), [
            'sessionDescription' => self::sdp(['type' => 'answer', 'sdp' => $sdp], 'answer'),
        ]);

        return $r['ok'] ? ['ok' => true, 'pesan' => ''] : self::gagal($r);
    }

    /** Tutup jalur tanpa negosiasi (force) — mis. saat kamera dimatikan. */
    public static function tutup(string $sesi, array $mids): void
    {
        $mids = array_values(array_filter($mids, fn ($m) => (string) $m !== ''));
        if ($mids === []) {
            return;
        }

        self::kirim('PUT', self::jalur('/sessions/'.$sesi.'/tracks/close'), [
            'tracks' => array_map(fn ($m) => ['mid' => (string) $m], $mids),
            'force' => true,
        ]);
    }

    /**
     * Kredensial ICE untuk browser.
     *
     * STUN saja cukup di jaringan terbuka, tapi peserta di balik NAT ketat
     * (paket data seluler, kantor) butuh TURN sebagai relay. Kredensial TURN
     * berumur 24 jam; di-cache 10 menit supaya tidak memanggil API tiap gabung.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function ice(): array
    {
        $stun = [['urls' => ['stun:stun.cloudflare.com:3478']]];
        $kunci = (string) config('services.realtime.turn_key');
        $rahasia = (string) config('services.realtime.turn_secret');

        if ($kunci === '' || $rahasia === '') {
            return $stun;
        }

        $ice = Cache::remember('sfu-ice-'.$kunci, 600, function () use ($kunci, $rahasia) {
            $r = Http::withToken($rahasia)->acceptJson()->timeout(10)
                ->post('https://rtc.live.cloudflare.com/v1/turn/keys/'.$kunci.'/credentials/generate-ice-servers',
                    ['ttl' => 86400]);

            return $r->successful() ? $r->json('iceServers') : null;
        });

        return is_array($ice) && $ice !== [] ? $ice : $stun;
    }

    private static function appId(): string
    {
        return (string) config('services.realtime.app_id');
    }

    private static function rahasia(): string
    {
        return (string) config('services.realtime.secret');
    }

    private static function jalur(string $ekor): string
    {
        return '/apps/'.self::appId().$ekor;
    }

    /**
     * Normalisasi SDP sebelum dikirim.
     *
     * Laravel (`TrimStrings`) memotong spasi di ujung tiap input — pada SDP itu
     * berarti baris terakhir kehilangan CRLF, dan Cloudflare menolaknya dengan
     * "SDP contains no ice-ufrag" / "missing termination at the end". Jadi
     * baris dirapikan, lalu WAJIB diakhiri CRLF.
     */
    private static function sdp(array $d, string $tipe): array
    {
        $baris = preg_split('/\r\n|\r|\n/', (string) ($d['sdp'] ?? '')) ?: [];
        $baris = array_map('rtrim', $baris);
        $baris = array_filter($baris, fn ($b) => $b !== '');
        $sdp = implode("\r\n", $baris)."\r\n";

        return ['type' => (string) ($d['type'] ?? $tipe), 'sdp' => $sdp];
    }

    /**
     * Kirim satu permintaan ke Connection API.
     *
     * @return array{ok:bool,kode:int,data:array}
     */
    private static function kirim(string $metode, string $jalur, ?array $body = null): array
    {
        $p = Http::withToken(self::rahasia())->acceptJson()->timeout(20)->connectTimeout(10);

        $res = $body === null
            ? $p->send($metode, self::BASIS.$jalur)
            : $p->asJson()->send($metode, self::BASIS.$jalur, ['json' => $body]);

        $data = $res->json();

        return ['ok' => $res->successful(), 'kode' => $res->status(), 'data' => is_array($data) ? $data : []];
    }

    /** Galat pertama dari daftar hasil per-trek (API menjawab 200 walau satu trek gagal). */
    private static function galatTrek(array $data): string
    {
        foreach (($data['tracks'] ?? []) as $t) {
            if (! empty($t['errorCode'])) {
                return (string) ($t['errorDescription'] ?? $t['errorCode']);
            }
        }

        return '';
    }

    /** @return array{ok:bool,pesan:string} */
    private static function gagal(array $r, string $tambahan = ''): array
    {
        $pesan = $r['data']['errorDescription'] ?? $r['data']['errorCode'] ?? 'HTTP '.$r['kode'];

        return ['ok' => false, 'pesan' => trim($tambahan !== '' ? $tambahan : $pesan)];
    }
}

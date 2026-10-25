<?php

return [




    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
    | Cloudflare Turnstile — captcha halaman /masuk.
    | Widget "agrinashub-login" (managed), kunci disimpan di .env.
    | Sandi rahasia hanya dipakai server (siteverify), tidak pernah ke peramban.
    */
    'turnstile' => [
        'site' => env('TURNSTILE_SITE_KEY', ''),
        'secret' => env('TURNSTILE_SECRET_KEY', ''),
    ],

    /*
    | Asisten AI Agrinas Hub. Endpoint 9Router lokal (OpenAI-compatible).
    | Nilai default sengaja menunjuk 127.0.0.1:20128 karena aplikasi berjalan
    | di kontainer dengan network=host, jadi loopback-nya adalah WSL yang sama
    | tempat 9Router mendengarkan.
    */
    'ai' => [
        'base_url' => env('AI_BASE_URL', 'http://127.0.0.1:20128/v1'),
        'api_key' => env('AI_API_KEY', ''),
        'model' => env('AI_MODEL', 'cmc/deepseek/deepseek-v4.1-flash-fast'),
        'timeout' => (int) env('AI_TIMEOUT', 90),
        // Model reasoning (deepseek-v4.1-flash-fast) memakai token untuk
        // PENALARAN lebih dulu; dengan batas kecil seluruh anggaran habis di
        // sana dan `content` pulang KOSONG (terbaca "tidak menjawab").
        // 3000 menyisakan ruang untuk jawaban utuh.
        'max_tokens' => (int) env('AI_MAX_TOKENS', 3000),
    ],

    /*
    | ONLYOFFICE Docs (document server) — kontainer di port 80 host, dipublikasikan
    | sebagai office.advi.dev. `url` dipakai editor di sisi peramban DAN oleh server
    | ONLYOFFICE untuk mengambil berkas; `secret` harus sama dengan JWT_SECRET
    | kontainer, kalau tidak setiap config editor ditolak dengan error -4.
    */
    'onlyoffice' => [
        'url' => env('ONLYOFFICE_URL', 'https://office.advi.dev'),
        'secret' => env('ONLYOFFICE_JWT_SECRET', ''),
    ],

    'simkopdes' => [
        // Kunci bersama untuk helper di PC admin (lihat HelperTokenController).
        'helper_key' => env('SIMKOPDES_HELPER_KEY', ''),
        // Akun pendamping KDKMP yang menarik data. Kunci enkripsi/IV payload
        // TIDAK disimpan di sini — lihat App\Services\Simkopdes (protokol API).
        'url' => env('SIMKOPDES_API_URL', 'https://api.simkopdes.go.id/api'),
        'email' => env('SIMKOPDES_EMAIL'),
        'password' => env('SIMKOPDES_PASSWORD'),
        'facilitator_id' => env('SIMKOPDES_FACILITATOR_ID'),
        // Relay Cloudflare Worker — jalur UTAMA semua panggilan SIMKOPDES
        // (profil, absensi, unggah foto, login): keluar dengan IP Cloudflare
        // supaya IP server ini tidak tercatat di sana. Kosong = jalur langsung.
        // Lihat /home/vannudin/simkopdes-relay/worker.js.
        'relay' => env('SIMKOPDES_RELAY_URL'),
        'relay_key' => env('SIMKOPDES_RELAY_KEY'),
    ],

    /*
     * Akun LMS APN yang dipakai menarik materi pelatihan (modul + konten).
     * Kredensial disimpan di .env, bukan di kode.
     */
    'lms' => [
        'url' => env('LMS_URL', 'https://lms.agrinaspangan.id'),
        'email' => env('LMS_EMAIL'),
        'sandi' => env('LMS_SANDI'),
    ],

    /*
     * Gerbang WhatsApp (Baileys) — kontainer terpisah, lihat App\Services\Whatsapp.
     * Sesi WA hidup di kontainer itu; Hub hanya menitip pesan lewat HTTP.
     */
    'whatsapp' => [
        'url' => env('WA_URL', 'http://127.0.0.1:8092'),
        'token' => env('WA_TOKEN'),
    ],

    /*
     * AgriConnect HRIS (Starconnect) — layanan mandiri pegawai.
     *
     * `master` = gerbang login; base DATA tiap akun berbeda dan dibaca dari
     * klaim JWT `instance_api`, jadi tidak ada di sini. Kredensial di .env.
     * Alasan base data bukan master: di master, seluruh endpoint data dijawab
     * 403/404 — lihat App\Services\Agriconnect.
     */
    'agriconnect' => [
        'master' => env('AC_MASTER', 'https://agriconnect-master.starconnect.id'),
        'username' => env('AC_USERNAME'),
        'password' => env('AC_PASSWORD'),
        'ua' => env('AC_UA', 'okhttp/4.12.0'),
        'timeout' => (int) env('AC_TIMEOUT', 30),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Cloudflare Realtime SFU — media rapat diteruskan Cloudflare, bukan server ini.
    // `turn_key`/`turn_secret` = kredensial TURN (Calls → TURN Key), dipakai
    // menerbitkan ICE server agar peserta di balik NAT ketat tetap tersambung.
    'realtime' => [
        'app_id' => env('CF_REALTIME_APP_ID'),
        'secret' => env('CF_REALTIME_APP_SECRET'),
        'turn_key' => env('CF_REALTIME_TURN_KEY'),
        'turn_secret' => env('CF_REALTIME_TURN_SECRET'),
    ],

    // PhotoCraft — editor gambar WASM yang berdiri di kontainernya sendiri.
    // `editor` = alamat yang DIBINGKAI halaman Ruang Kerja; bawaan menunjuk
    // kontainer di server yang sama (port 8080). Jangan arahkan ke API-nya
    // (data/control token) — itu jalur agent, bukan manusia.
    'photocraft' => [
        // Alamat LOOPBACK kontainer editor (nginx, port 8080). Editor tidak lagi
        // dibuka dari subdomain publiknya sendiri: Hub yang menyalurkan berkasnya
        // (lihat WorkspaceController::gambar_editor), sehingga hanya pengguna yang
        // sudah masuk yang bisa memuatnya dan halaman + editor berada di satu asal.
        'loopback' => env('PHOTOCRAFT_LOOPBACK_URL', 'http://127.0.0.1:8080'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

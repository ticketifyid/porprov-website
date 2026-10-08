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

    /*
     * Cloudflare Turnstile pada form pendaftaran publik.
     * TURNSTILE_ENABLED=false mematikan verifikasi (dipakai di phpunit.xml dan
     * saat pengembangan lokal tanpa kunci).
     */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'enabled' => env('TURNSTILE_ENABLED', true),
    ],

    /*
     * Implementasi App\Contracts\TicketNotifier yang dipakai job notifikasi.
     * 'log'  → App\Services\Notifications\LogTicketNotifier (tidak mengirim apa pun)
     * 'mail' → App\Services\Notifications\MailTicketNotifier (email asli; WA
     *          sementara masih log). Lihat docs/notifikasi.md.
     * Nilai yang tidak dikenal sengaja melempar exception di AppServiceProvider.
     */
    'ticket_notifier' => env('TICKET_NOTIFIER', 'log'),

    /*
     * Driver WhatsApp dipakai MailTicketNotifier::sendWhatsApp() (docs/notifikasi.md,
     * docs/whatsapp-api.md).
     * 'log'  (default) → diteruskan ke LogTicketNotifier, tidak mengirim apa pun.
     * 'waha' → kirim sungguhan lewat WAHA. Pengiriman dilakukan command
     *          `wa:send-next` (bukan job), lihat App\Console\Commands\WaSendNext.
     */
    'ticket_wa_driver' => env('TICKET_WA_DRIVER', 'log'),

    'waha' => [
        'url' => env('WAHA_URL'),
        'api_key' => env('WAHA_API_KEY'),
        'session' => env('WAHA_SESSION'),
    ],

    /*
     * Kebijakan pengiriman WhatsApp (nomor tidak resmi, berisiko dibanned).
     * Lihat docs/notifikasi.md "Outbox WhatsApp".
     */
    'wa' => [
        'min_gap_seconds' => (int) env('WA_MIN_GAP_SECONDS', 45),
        'max_gap_seconds' => (int) env('WA_MAX_GAP_SECONDS', 90),
        'typing_max_seconds' => (int) env('WA_TYPING_MAX_SECONDS', 8),
        'daily_cap' => (int) env('WA_DAILY_CAP', 100),
        'send_from' => env('WA_SEND_FROM', '08:00'),
        'send_until' => env('WA_SEND_UNTIL', '20:00'),
        'pause_hours' => (int) env('WA_PAUSE_HOURS', 6),
        'max_consecutive_failures' => (int) env('WA_MAX_CONSECUTIVE_FAILURES', 3),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

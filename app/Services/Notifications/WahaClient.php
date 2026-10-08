<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Transport HTTP murni ke WAHA (docs/whatsapp-api.md). Tidak ada logika
 * kebijakan di sini (jeda, jam kirim, batas harian, circuit breaker) — itu
 * urusan App\Console\Commands\WaSendNext. API key TIDAK PERNAH masuk log atau
 * pesan exception.
 */
class WahaClient
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * GET /api/sessions — dipakai untuk memastikan sesi WAHA_SESSION berstatus
     * WORKING sebelum mengirim apa pun.
     */
    public function sessions(): Response
    {
        return $this->http()->get('/api/sessions');
    }

    public function startTyping(string $chatId): Response
    {
        return $this->http()->post('/api/startTyping', [
            'session' => $this->session(),
            'chatId' => $chatId,
        ]);
    }

    public function sendText(string $chatId, string $text): Response
    {
        return $this->http()->post('/api/sendText', [
            'session' => $this->session(),
            'chatId' => $chatId,
            'text' => $text,
        ]);
    }

    public function stopTyping(string $chatId): Response
    {
        return $this->http()->post('/api/stopTyping', [
            'session' => $this->session(),
            'chatId' => $chatId,
        ]);
    }

    public function session(): string
    {
        return (string) config('services.waha.session');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.waha.url'))
            ->withHeaders(['X-Api-Key' => (string) config('services.waha.api_key')])
            ->timeout(self::TIMEOUT_SECONDS);
    }
}

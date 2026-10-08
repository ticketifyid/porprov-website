<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Mengklasifikasi kegagalan WahaClient::sendText() untuk WaSendNext
 * (docs/notifikasi.md "Circuit breaker"):
 *
 * - IMMEDIATE: koneksi/timeout gagal, atau body respons memuat "463"
 *   (indikasi akun WhatsApp dibatasi). Langsung memicu pause.
 * - COUNTS: HTTP 5xx. Memicu pause hanya setelah tiga kali berturut-turut
 *   (WA_MAX_CONSECUTIVE_FAILURES).
 * - NONE: kegagalan lain (mis. nomor tidak valid). Hanya menaikkan attempts
 *   baris notification_logs, tidak memicu breaker, dan mereset hitungan
 *   kegagalan beruntun.
 *
 * Bentuk body respons sendText dan error 463 BELUM DIVERIFIKASI
 * (docs/whatsapp-api.md) — klasifikasi ini akan disesuaikan setelah ada
 * kegagalan nyata.
 */
class WahaFailureClassifier
{
    public const IMMEDIATE = 'immediate';

    public const COUNTS = 'counts';

    public const NONE = 'none';

    public function classify(?Response $response, ?Throwable $exception): string
    {
        if ($exception !== null) {
            return self::IMMEDIATE;
        }

        if ($response === null) {
            return self::NONE;
        }

        if (str_contains((string) $response->body(), '463')) {
            return self::IMMEDIATE;
        }

        if ($response->serverError()) {
            return self::COUNTS;
        }

        return self::NONE;
    }
}

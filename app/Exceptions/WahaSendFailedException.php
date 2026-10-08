<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Dilempar MailTicketNotifier::sendWhatsApp() saat WAHA membalas selain HTTP
 * 201. Membawa $response supaya App\Services\Notifications\WahaFailureClassifier
 * bisa membaca status/body-nya (5xx, atau body memuat "463").
 */
class WahaSendFailedException extends RuntimeException
{
    public function __construct(string $message, public readonly ?Response $response = null)
    {
        parent::__construct($message);
    }
}

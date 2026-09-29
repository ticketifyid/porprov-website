<?php

namespace App\Actions;

use App\Jobs\SendTicketNotification;
use App\Models\NotificationLog;
use App\Models\Registration;

/**
 * Reset notification_logs registrasi ke pending lalu dispatch ulang kedua
 * kanal. Dipakai oleh TicketController::search (/cari-tiket, Fase 6) dan
 * Admin\RegistrationAdminController::resend (Fase 8) — logikanya identik,
 * jadi ditaruh di satu tempat.
 */
class ResendTicketNotifications
{
    /**
     * @var list<string>
     */
    private const CHANNELS = ['email', 'whatsapp'];

    public function handle(Registration $registration): void
    {
        foreach (self::CHANNELS as $channel) {
            NotificationLog::query()->updateOrCreate(
                ['registration_id' => $registration->getKey(), 'channel' => $channel],
                ['status' => 'pending', 'attempts' => 0, 'last_error' => null],
            );

            SendTicketNotification::dispatch($registration->getKey(), $channel);
        }
    }
}

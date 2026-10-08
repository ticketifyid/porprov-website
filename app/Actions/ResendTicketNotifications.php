<?php

namespace App\Actions;

use App\Jobs\SendTicketNotification;
use App\Models\NotificationLog;
use App\Models\Registration;

/**
 * Reset notification_logs registrasi lalu antre ulang. Dipakai oleh
 * TicketController::search (/cari-tiket, Fase 6) dan
 * Admin\RegistrationAdminController::resend (Fase 8) — logikanya identik,
 * jadi ditaruh di satu tempat.
 *
 * Kanal email: reset ke 'pending' + dispatch job seperti sebelumnya. Kanal
 * whatsapp TIDAK dispatch job (docs/notifikasi.md "Outbox WhatsApp"): reset
 * ke 'pending' jika peserta menyetujui WA, atau 'skipped' jika tidak, lalu
 * diambil belakangan oleh command `wa:send-next`.
 */
class ResendTicketNotifications
{
    public function handle(Registration $registration): void
    {
        NotificationLog::query()->updateOrCreate(
            ['registration_id' => $registration->getKey(), 'channel' => 'email'],
            ['status' => 'pending', 'attempts' => 0, 'last_error' => null],
        );
        SendTicketNotification::dispatch($registration->getKey(), 'email');

        NotificationLog::query()->updateOrCreate(
            ['registration_id' => $registration->getKey(), 'channel' => 'whatsapp'],
            ['status' => $registration->wa_consent ? 'pending' : 'skipped', 'attempts' => 0, 'last_error' => null],
        );
    }
}

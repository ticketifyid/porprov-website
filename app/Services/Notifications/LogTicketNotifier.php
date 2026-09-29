<?php

namespace App\Services\Notifications;

use App\Contracts\TicketNotifier;
use App\Models\Registration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Implementasi default (TICKET_NOTIFIER=log): tidak mengirim apa pun, hanya
 * menulis ke log aplikasi. Dipakai sampai pemilik proyek membuat implementasi
 * SMTP dan WhatsApp asli pada Fase 10 (docs/notifikasi.md).
 */
class LogTicketNotifier implements TicketNotifier
{
    public function sendEmail(Registration $registration): ?string
    {
        return $this->write('email', $registration, $registration->email);
    }

    public function sendWhatsApp(Registration $registration): ?string
    {
        return $this->write('whatsapp', $registration, $registration->phone);
    }

    private function write(string $channel, Registration $registration, string $destination): string
    {
        $messageId = 'log-'.Str::uuid();

        Log::info('Notifikasi e-ticket (LogTicketNotifier)', [
            'channel' => $channel,
            'destination' => $destination,
            'code' => $registration->code,
            'name' => $registration->name,
            'ticket_qty' => $registration->ticket_qty,
            'ticket_url' => url('/tiket/'.$registration->token),
            'provider_message_id' => $messageId,
        ]);

        return $messageId;
    }
}

<?php

namespace App\Services\Notifications;

use App\Contracts\TicketNotifier;
use App\Models\Registration;
use App\Support\ContactMasker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Implementasi default (TICKET_NOTIFIER=log): tidak mengirim apa pun, hanya
 * menulis ke log aplikasi. Dipakai sampai pemilik proyek membuat implementasi
 * SMTP dan WhatsApp asli pada Fase 10 (docs/notifikasi.md).
 *
 * Log tidak memuat email/nomor HP utuh, nama, maupun link tiket bertoken:
 * siapa pun yang bisa membaca storage/logs akan bisa membuka tiket peserta.
 * Tujuan ditulis tersamar dengan format yang sama seperti halaman tiket.
 */
class LogTicketNotifier implements TicketNotifier
{
    public function sendEmail(Registration $registration): ?string
    {
        return $this->write('email', $registration, ContactMasker::email($registration->email));
    }

    public function sendWhatsApp(Registration $registration): ?string
    {
        return $this->write('whatsapp', $registration, ContactMasker::phone($registration->phone));
    }

    public function deliversWhatsApp(): bool
    {
        return false;
    }

    private function write(string $channel, Registration $registration, string $maskedDestination): string
    {
        $messageId = 'log-'.Str::uuid();

        Log::info('Notifikasi e-ticket (LogTicketNotifier)', [
            'channel' => $channel,
            'registration_id' => $registration->id,
            'code' => $registration->code,
            'destination' => $maskedDestination,
        ]);

        return $messageId;
    }
}

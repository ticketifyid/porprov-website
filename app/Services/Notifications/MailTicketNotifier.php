<?php

namespace App\Services\Notifications;

use App\Contracts\TicketNotifier;
use App\Mail\TicketMail;
use App\Models\Registration;
use Illuminate\Support\Facades\Mail;

/**
 * Implementasi produksi (TICKET_NOTIFIER=mail): e-ticket dikirim lewat email
 * (mailer default, SMTP di produksi). WhatsApp belum diimplementasikan dan
 * sementara diteruskan ke LogTicketNotifier (docs/notifikasi.md).
 */
class MailTicketNotifier implements TicketNotifier
{
    public function __construct(private LogTicketNotifier $whatsapp) {}

    /**
     * Dikirim langsung (send, bukan queue): method ini sudah berjalan di dalam
     * job SendTicketNotification. Exception transport sengaja tidak ditangkap
     * supaya job menandai percobaan gagal dan queue melakukan retry.
     */
    public function sendEmail(Registration $registration): ?string
    {
        $registration->loadMissing('event');

        // Ke kolom email (persis seperti diketik), BUKAN email_canonical (aturan 13).
        $sent = Mail::to($registration->email, $registration->name)
            ->send(new TicketMail($registration));

        return $sent?->getMessageId();
    }

    public function sendWhatsApp(Registration $registration): ?string
    {
        return $this->whatsapp->sendWhatsApp($registration);
    }
}

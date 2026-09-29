<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email e-ticket. Sengaja tidak ShouldQueue: dikirim dari dalam job
 * SendTicketNotification lewat MailTicketNotifier.
 */
class TicketMail extends Mailable
{
    public function __construct(public Registration $registration) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'E-ticket Opening Ceremony Porprov Jateng XVII 2026 - '.$this->registration->code,
        );
    }

    public function content(): Content
    {
        $event = $this->registration->event;

        return new Content(
            view: 'mail.ticket',
            text: 'mail.ticket-text',
            with: [
                'name' => $this->registration->name,
                'code' => $this->registration->code,
                'ticketQty' => $this->registration->ticket_qty,
                // Di worker tidak ada request, jadi route() memakai APP_URL.
                // Link memakai token, bukan kode registrasi (aturan 7).
                'ticketUrl' => route('tiket.show', $this->registration->token),
                'eventStartsAt' => $event?->event_starts_at?->locale('id')->translatedFormat('j F Y, H.i'),
                'venue' => $event?->venue,
            ],
        );
    }
}

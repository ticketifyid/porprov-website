<?php

namespace App\Services\Notifications;

use App\Contracts\TicketNotifier;
use App\Exceptions\WahaSendFailedException;
use App\Mail\TicketMail;
use App\Models\Registration;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Implementasi produksi (TICKET_NOTIFIER=mail): e-ticket dikirim lewat email
 * (mailer default, SMTP di produksi). WhatsApp lewat WAHA jika
 * TICKET_WA_DRIVER=waha (docs/whatsapp-api.md, docs/notifikasi.md); selain itu
 * (default) tetap diteruskan ke LogTicketNotifier.
 */
class MailTicketNotifier implements TicketNotifier
{
    /** Batas simulasi mengetik, detik (WA_TYPING_MAX_SECONDS). */
    private const MIN_TYPING_SECONDS = 2;

    public function __construct(private LogTicketNotifier $whatsapp, private WahaClient $waha) {}

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

    /**
     * TICKET_WA_DRIVER=waha → kirim lewat WAHA (docs/whatsapp-api.md). Selain
     * itu (default 'log') tetap diteruskan ke LogTicketNotifier.
     *
     * Dipanggil oleh App\Console\Commands\WaSendNext, BUKAN oleh
     * SendTicketNotification — pengiriman WhatsApp tidak lagi lewat job
     * (docs/notifikasi.md "Outbox WhatsApp").
     */
    public function sendWhatsApp(Registration $registration): ?string
    {
        if (config('services.ticket_wa_driver') !== 'waha') {
            return $this->whatsapp->sendWhatsApp($registration);
        }

        $registration->loadMissing('event');

        $chatId = PhoneNormalizer::normalize($registration->phone).'@c.us';
        $text = $this->buildWhatsAppMessage($registration);

        $this->waha->startTyping($chatId);

        Sleep::usleep((int) ($this->typingSeconds($text) * 1_000_000));

        $response = $this->waha->sendText($chatId, $text);

        try {
            $this->waha->stopTyping($chatId);
        } catch (Throwable) {
            // Kegagalan stopTyping sengaja tidak membatalkan pengiriman.
        }

        if ($response->status() !== 201) {
            throw new WahaSendFailedException(
                'WAHA sendText gagal: HTTP '.$response->status().' - '.mb_substr((string) $response->body(), 0, 300),
                $response,
            );
        }

        return $this->extractMessageId($response->json());
    }

    public function deliversWhatsApp(): bool
    {
        return config('services.ticket_wa_driver') === 'waha';
    }

    /**
     * Isi pesan WhatsApp (docs/notifikasi.md). Tanggal/lokasi disembunyikan
     * jika null, sama seperti email.
     */
    private function buildWhatsAppMessage(Registration $registration): string
    {
        $event = $registration->event;
        $qty = $registration->ticket_qty;

        $lines = [
            "Halo {$registration->name},",
            'Pendaftaran '.$event?->name.' berhasil.',
            '',
            "Kode registrasi: {$registration->code}",
            "Jumlah tiket: {$qty} (tukar dengan {$qty} gelang)",
        ];

        $eventStartsAt = $event?->event_starts_at?->locale('id')->translatedFormat('j F Y, H.i');
        $venue = $event?->venue;

        if ($eventStartsAt !== null || $venue !== null) {
            $lines[] = 'Acara: '.trim(($eventStartsAt ?? '').($eventStartsAt !== null && $venue !== null ? ', ' : '').($venue ?? ''));
        }

        $lines[] = '';
        $lines[] = 'E-ticket dan QR: '.route('tiket.show', $registration->token);
        $lines[] = '';
        $lines[] = 'Tunjukkan QR saat registrasi ulang.';
        $lines[] = 'Pesan ini dikirim otomatis karena Anda mendaftar dan menyetujui notifikasi WhatsApp.';

        return implode("\n", $lines);
    }

    /**
     * Simulasi mengetik: minimal 2 detik, maksimal WA_TYPING_MAX_SECONDS,
     * ditambah sedikit acak.
     */
    private function typingSeconds(string $text): float
    {
        $max = (float) config('services.wa.typing_max_seconds', 8);
        $base = mb_strlen($text) / 20;
        $seconds = max(self::MIN_TYPING_SECONDS, min($max, $base));

        return $seconds + (random_int(0, 500) / 1000);
    }

    /**
     * Bentuk body respons sendText BELUM DIVERIFIKASI (docs/whatsapp-api.md):
     * ambil id pesan defensif (string id, id._serialized, atau key.id),
     * null jika tidak ditemukan.
     */
    private function extractMessageId(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $id = $json['id'] ?? null;

        if (is_string($id)) {
            return $id;
        }

        if (is_array($id) && is_string($id['_serialized'] ?? null)) {
            return $id['_serialized'];
        }

        $keyId = $json['key']['id'] ?? null;

        return is_string($keyId) ? $keyId : null;
    }
}

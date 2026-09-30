<?php

namespace App\Contracts;

use App\Models\Registration;

/**
 * Satu-satunya titik sambung pengiriman e-ticket (aturan 8 CLAUDE.md).
 *
 * App\Jobs\SendTicketNotification hanya mengenal interface ini: job tidak tahu
 * apa-apa soal SMTP, Mailable, atau VPS WhatsApp. Implementasi asli dibuat
 * pemilik proyek pada Fase 10 — lihat docs/notifikasi.md.
 *
 * Kontrak kedua method:
 * - Berhasil  → kembalikan id pesan dari provider, atau null jika provider
 *               tidak memberi id. null TETAP berarti berhasil.
 * - Gagal     → LEMPAR exception. Jangan mengembalikan false atau null untuk
 *               menandai kegagalan; job memakai exception untuk menaikkan
 *               attempts, mengisi last_error, dan meminta queue melakukan retry.
 */
interface TicketNotifier
{
    /** @return string|null provider message id; lempar exception jika gagal */
    public function sendEmail(Registration $registration): ?string;

    /** @return string|null provider message id; lempar exception jika gagal */
    public function sendWhatsApp(Registration $registration): ?string;

    /**
     * true hanya jika sendWhatsApp() benar-benar mengirim pesan WhatsApp
     * (bukan diteruskan ke LogTicketNotifier). Menentukan teks kanal di
     * halaman peserta: "email" saja, atau "email dan WhatsApp".
     */
    public function deliversWhatsApp(): bool;
}

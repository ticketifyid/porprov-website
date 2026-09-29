<?php

namespace App\Jobs;

use App\Contracts\TicketNotifier;
use App\Models\NotificationLog;
use App\Models\Registration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;
use Throwable;

/**
 * Mengirim e-ticket untuk satu registrasi lewat satu kanal, di luar request
 * (aturan 8 CLAUDE.md: selalu setelah COMMIT, tidak pernah sinkron).
 *
 * Job ini HANYA mengenal App\Contracts\TicketNotifier — tidak tahu apa-apa soal
 * SMTP maupun VPS WhatsApp. Yang diurus di sini: memilih method sesuai kanal,
 * memperbarui notification_logs, dan menyerahkan retry ke queue.
 */
class SendTicketNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Maksimal 3 percobaan, lalu failed() (docs/arsitektur.md Fase 2).
     */
    public int $tries = 3;

    /**
     * Jeda antar percobaan: ~1 menit, lalu ~5 menit. Worker dijalankan cron
     * tiap menit (docs/hosting.md), jadi jeda ini dieksekusi di run berikutnya.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public int $registrationId,
        public string $channel,
    ) {}

    public function handle(TicketNotifier $notifier): void
    {
        // Kanal divalidasi lebih dulu: nilai di luar enum notification_logs
        // adalah salah kode, bukan kegagalan pengiriman, dan tidak boleh
        // sempat membuat baris log.
        $send = match ($this->channel) {
            'email' => $notifier->sendEmail(...),
            'whatsapp' => $notifier->sendWhatsApp(...),
            default => throw new InvalidArgumentException("Kanal notifikasi '{$this->channel}' tidak dikenal."),
        };

        $registration = Registration::query()->find($this->registrationId);

        // Registrasi bisa sudah dihapus, atau dibatalkan admin sebelum worker
        // jalan. Itu bukan kegagalan pengiriman; jangan bikin job failed, dan
        // jangan sentuh notification_logs (biar tetap pending, bukan sent).
        if ($registration === null || $registration->cancelled_at !== null) {
            return;
        }

        $log = NotificationLog::query()->firstOrCreate(
            ['registration_id' => $registration->getKey(), 'channel' => $this->channel],
            ['status' => 'pending', 'attempts' => 0],
        );

        // Worker bisa terbunuh setelah pengiriman tapi sebelum job dihapus dari
        // antrean, lalu job yang sama jalan lagi di cron berikutnya.
        if ($log->status === 'sent') {
            return;
        }

        try {
            $messageId = $send($registration);
        } catch (Throwable $e) {
            $log->forceFill([
                'attempts' => $log->attempts + 1,
                'last_error' => $this->errorMessage($e),
            ])->save();

            // Status tetap 'pending': queue yang menentukan kapan retry dan
            // kapan menyerah. Penanda 'failed' dipasang di failed() di bawah.
            throw $e;
        }

        $log->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'provider_message_id' => $messageId,
            'last_error' => null,
        ])->save();
    }

    /**
     * Dipanggil queue setelah percobaan terakhir gagal.
     */
    public function failed(?Throwable $e): void
    {
        NotificationLog::query()
            ->where('registration_id', $this->registrationId)
            ->where('channel', $this->channel)
            ->update([
                'status' => 'failed',
                'last_error' => $e === null ? 'Gagal tanpa keterangan.' : $this->errorMessage($e),
                'updated_at' => now(),
            ]);
    }

    /**
     * Pesan untuk notification_logs.last_error, dibaca admin di halaman detail
     * peserta. Exception SMTP/HTTP bisa memuat kredensial, alamat email, nomor
     * HP, atau token tiket, jadi pesannya disaring dulu (docs/notifikasi.md),
     * lalu dipotong 500 karakter.
     */
    private function errorMessage(Throwable $e): string
    {
        $message = preg_replace(
            [
                // smtp://user:pass@host → smtp://[disaring]@host
                '~(\b[a-z][a-z0-9+.\-]*://)[^\s/@]+@~i',
                '~\bAuthorization\s*[:=]\s*(?:(?:Bearer|Basic)\s+)?[^\s"\',;]+~i',
                '~\b(Bearer|Basic)\s+[^\s"\',;]+~i',
                // password=..., token: ...
                '~\b(password|passwd|pwd|pass|secret|token|api[_-]?key|apikey|access[_-]?key|key|username|user)\s*[=:]\s*("[^"]*"|\'[^\']*\'|[^\s&"\',;]+)~i',
                // with username "..." (pesan autentikasi Symfony Mailer)
                '~\b(password|username|user)\s+("[^"]*"|\'[^\']*\')~i',
                '~[a-z0-9._%+\-]+@[a-z0-9\-]+(?:\.[a-z0-9\-]+)+~i',
                // token tiket (48 karakter), API key, dsb.
                '~\b[A-Za-z0-9_\-]{32,}\b~',
                '~(?<![\w\[])\+?\d(?:[\s\-.()]*\d){8,}~',
            ],
            [
                '$1[disaring]@',
                'Authorization: [disaring]',
                '$1 [disaring]',
                '$1=[disaring]',
                '$1 "[disaring]"',
                '[email]',
                '[token]',
                '[nomor]',
            ],
            $e->getMessage(),
        );

        return mb_substr($e::class.': '.($message ?? '[pesan tidak bisa dibaca]'), 0, 500);
    }
}

<?php

namespace App\Jobs;

use App\Contracts\TicketNotifier;
use App\Models\NotificationLog;
use App\Models\Registration;
use App\Services\Notifications\NotificationLogRecorder;
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
        $recorder = app(NotificationLogRecorder::class);

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
            // Status tetap 'pending': queue yang menentukan kapan retry dan
            // kapan menyerah. Penanda 'failed' dipasang di failed() di bawah.
            $recorder->recordAttemptFailure($log, $e);

            throw $e;
        }

        $recorder->markSent($log, $messageId);
    }

    /**
     * Dipanggil queue setelah percobaan terakhir gagal.
     */
    public function failed(?Throwable $e): void
    {
        $log = NotificationLog::query()
            ->where('registration_id', $this->registrationId)
            ->where('channel', $this->channel)
            ->first();

        if ($log === null) {
            return;
        }

        if ($e === null) {
            $log->forceFill(['status' => 'failed', 'last_error' => 'Gagal tanpa keterangan.'])->save();

            return;
        }

        app(NotificationLogRecorder::class)->markPermanentlyFailed($log, $e);
    }
}

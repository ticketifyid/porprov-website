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

        // Registrasi bisa sudah dibatalkan/dihapus admin sebelum worker jalan.
        // Itu bukan kegagalan pengiriman; jangan bikin job failed.
        if ($registration === null) {
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
     * Kolomnya text, tapi jejak exception bisa sangat panjang; simpan secukupnya
     * untuk dibaca admin di halaman detail peserta.
     */
    private function errorMessage(Throwable $e): string
    {
        return mb_substr($e::class.': '.$e->getMessage(), 0, 1000);
    }
}

<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use App\Support\NotificationErrorSanitizer;
use Throwable;

/**
 * Pembaruan notification_logs yang dipakai ulang oleh App\Jobs\SendTicketNotification
 * (kanal email) dan App\Console\Commands\WaSendNext (kanal whatsapp), supaya logika
 * "sent + sent_at + provider_message_id" dan "attempts++ + last_error" tidak
 * diduplikasi (docs/notifikasi.md).
 */
class NotificationLogRecorder
{
    public function markSent(NotificationLog $log, ?string $messageId): void
    {
        $log->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'provider_message_id' => $messageId,
            'last_error' => null,
        ])->save();
    }

    /**
     * Satu percobaan gagal: attempts++ dan last_error diisi (tersaring).
     * Status TIDAK disentuh di sini — pemanggil yang menentukan apakah baris
     * kembali ke 'pending' atau sudah waktunya markPermanentlyFailed().
     */
    public function recordAttemptFailure(NotificationLog $log, Throwable $e): void
    {
        $log->forceFill([
            'attempts' => $log->attempts + 1,
            'last_error' => NotificationErrorSanitizer::sanitize($e),
        ])->save();
    }

    public function markPermanentlyFailed(NotificationLog $log, Throwable $e): void
    {
        $log->forceFill([
            'status' => 'failed',
            'last_error' => NotificationErrorSanitizer::sanitize($e),
        ])->save();
    }
}

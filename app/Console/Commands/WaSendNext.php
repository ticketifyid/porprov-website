<?php

namespace App\Console\Commands;

use App\Contracts\TicketNotifier;
use App\Exceptions\WahaSendFailedException;
use App\Models\NotificationLog;
use App\Models\WaThrottleState;
use App\Services\Notifications\NotificationLogRecorder;
use App\Services\Notifications\WahaClient;
use App\Services\Notifications\WahaFailureClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengambil SATU baris notification_logs (channel whatsapp, status pending)
 * dan mengirimkannya, lalu berhenti. Dijadwalkan everyMinute() di
 * routes/console.php supaya jeda antar pesan terjadi secara alami antar
 * eksekusi cron (docs/notifikasi.md "Outbox WhatsApp").
 *
 * Nomor WhatsApp pengirim tidak resmi dan berisiko dibanned, jadi urutannya
 * ketat: driver aktif → tidak sedang pause → dalam jam kirim → belum
 * mencapai batas harian → sudah lewat next_allowed_at → sesi WAHA WORKING →
 * klaim atomik satu baris → kirim di luar transaksi.
 */
class WaSendNext extends Command
{
    protected $signature = 'wa:send-next';

    protected $description = 'Kirim satu notifikasi WhatsApp outbox berikutnya (docs/notifikasi.md)';

    /**
     * Baris 'sending' yang menggantung lebih dari ini (worker mati di tengah
     * pengiriman) dikembalikan ke 'pending'.
     */
    private const STALE_SENDING_MINUTES = 10;

    /**
     * Sama seperti SendTicketNotification::$tries (docs/notifikasi.md).
     */
    private const MAX_ATTEMPTS = 3;

    public function handle(
        TicketNotifier $notifier,
        NotificationLogRecorder $recorder,
        WahaClient $waha,
        WahaFailureClassifier $classifier,
    ): int {
        if (config('services.ticket_wa_driver') !== 'waha') {
            return self::SUCCESS;
        }

        $state = WaThrottleState::current();

        if ($state->isPaused() || $state->mustWaitForGap() || ! $this->withinSendWindow() || $this->sentToday() >= (int) config('services.wa.daily_cap')) {
            return self::SUCCESS;
        }

        $this->reclaimStaleSending();

        if (! $this->sessionHealthy($waha, $state)) {
            return self::SUCCESS;
        }

        $log = $this->claimNext();

        if ($log === null) {
            return self::SUCCESS;
        }

        $registration = $log->registration;

        try {
            $messageId = $notifier->sendWhatsApp($registration);
        } catch (Throwable $e) {
            $this->handleFailure($log, $e, $classifier, $recorder, $state);

            return self::SUCCESS;
        }

        $recorder->markSent($log, $messageId);

        $state->forceFill([
            'next_allowed_at' => now()->addSeconds(random_int(
                (int) config('services.wa.min_gap_seconds'),
                (int) config('services.wa.max_gap_seconds'),
            )),
            'consecutive_failures' => 0,
        ])->save();

        return self::SUCCESS;
    }

    private function withinSendWindow(): bool
    {
        $now = now()->format('H:i');

        return $now >= (string) config('services.wa.send_from') && $now < (string) config('services.wa.send_until');
    }

    private function sentToday(): int
    {
        return NotificationLog::query()
            ->where('channel', 'whatsapp')
            ->where('status', 'sent')
            ->whereDate('sent_at', now()->toDateString())
            ->count();
    }

    private function reclaimStaleSending(): void
    {
        NotificationLog::query()
            ->where('channel', 'whatsapp')
            ->where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_SENDING_MINUTES))
            ->update(['status' => 'pending', 'updated_at' => now()]);
    }

    private function sessionHealthy(WahaClient $waha, WaThrottleState $state): bool
    {
        try {
            $response = $waha->sessions();
        } catch (Throwable) {
            $this->triggerBreaker($state, 'Sesi WAHA tidak terjangkau (koneksi/timeout).');

            return false;
        }

        if (! $response->successful()) {
            $this->triggerBreaker($state, 'GET /api/sessions gagal: HTTP '.$response->status());

            return false;
        }

        $sessions = $response->json();
        $session = collect(is_array($sessions) ? $sessions : [])->firstWhere('name', $waha->session());

        if (($session['status'] ?? null) !== 'WORKING') {
            $this->triggerBreaker($state, 'Sesi WAHA belum WORKING.');

            return false;
        }

        return true;
    }

    /**
     * Klaim atomik: lockForUpdate di dalam transaksi, status langsung
     * ditandai 'sending' sebelum transaksi commit, supaya dua eksekusi
     * command tidak pernah mengirim baris yang sama.
     */
    private function claimNext(): ?NotificationLog
    {
        return DB::transaction(function (): ?NotificationLog {
            $log = NotificationLog::query()
                ->where('channel', 'whatsapp')
                ->where('status', 'pending')
                ->whereHas('registration', function ($query): void {
                    $query->where('wa_consent', true)->whereNull('cancelled_at');
                })
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if ($log === null) {
                return null;
            }

            $log->forceFill(['status' => 'sending'])->save();

            return $log;
        });
    }

    private function handleFailure(
        NotificationLog $log,
        Throwable $e,
        WahaFailureClassifier $classifier,
        NotificationLogRecorder $recorder,
        WaThrottleState $state,
    ): void {
        $response = $e instanceof WahaSendFailedException ? $e->response : null;
        $severity = $classifier->classify($response, $response === null ? $e : null);

        $recorder->recordAttemptFailure($log, $e);

        if ($log->attempts >= self::MAX_ATTEMPTS) {
            $recorder->markPermanentlyFailed($log, $e);
        } else {
            // Klaim 'sending' dilepas: baris kembali 'pending' untuk dicoba
            // wa:send-next berikutnya.
            $log->forceFill(['status' => 'pending'])->save();
        }

        match ($severity) {
            WahaFailureClassifier::NONE => $state->forceFill(['consecutive_failures' => 0])->save(),
            WahaFailureClassifier::IMMEDIATE => $this->triggerBreaker($state, $e->getMessage()),
            WahaFailureClassifier::COUNTS => $this->bumpConsecutiveFailures($state),
        };
    }

    private function bumpConsecutiveFailures(WaThrottleState $state): void
    {
        $failures = $state->consecutive_failures + 1;

        if ($failures >= (int) config('services.wa.max_consecutive_failures')) {
            $this->triggerBreaker($state, 'HTTP 5xx berturut-turut dari WAHA.');

            return;
        }

        $state->forceFill(['consecutive_failures' => $failures])->save();
    }

    private function triggerBreaker(WaThrottleState $state, string $reason): void
    {
        Log::warning('Outbox WhatsApp dijeda (circuit breaker)', ['reason' => $reason]);

        $state->pause($reason, (int) config('services.wa.pause_hours'));
    }
}

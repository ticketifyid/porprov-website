<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kerangka job notifikasi e-ticket. Fase 4 hanya membutuhkan dispatch-nya
 * setelah pendaftaran commit; isi handle() (panggil App\Contracts\TicketNotifier,
 * perbarui notification_logs, retry maks 3) dikerjakan pada Fase 5
 * sesuai docs/prompts.md dan docs/arsitektur.md Fase 2.
 */
class SendTicketNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $registrationId,
        public string $channel,
    ) {}

    public function handle(): void
    {
        // Diisi pada Fase 5.
    }
}

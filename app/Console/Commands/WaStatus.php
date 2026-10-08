<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Models\WaThrottleState;
use Illuminate\Console\Command;

/**
 * Ringkasan outbox WhatsApp untuk dicek lewat SSH (docs/notifikasi.md).
 */
class WaStatus extends Command
{
    protected $signature = 'wa:status';

    protected $description = 'Tampilkan ringkasan outbox WhatsApp (batas harian, pending, failed, status pause)';

    public function handle(): int
    {
        $state = WaThrottleState::current();

        $sentToday = NotificationLog::query()
            ->where('channel', 'whatsapp')
            ->where('status', 'sent')
            ->whereDate('sent_at', now()->toDateString())
            ->count();

        $pending = NotificationLog::query()->where('channel', 'whatsapp')->where('status', 'pending')->count();
        $sending = NotificationLog::query()->where('channel', 'whatsapp')->where('status', 'sending')->count();
        $failed = NotificationLog::query()->where('channel', 'whatsapp')->where('status', 'failed')->count();
        $skipped = NotificationLog::query()->where('channel', 'whatsapp')->where('status', 'skipped')->count();

        $this->line('Driver: '.config('services.ticket_wa_driver'));
        $this->line("Terkirim hari ini: {$sentToday} / ".config('services.wa.daily_cap'));
        $this->line("Pending: {$pending} | Sedang dikirim: {$sending} | Gagal: {$failed} | Dilewati (tanpa persetujuan): {$skipped}");

        if ($state->isPaused()) {
            $this->line('Status: DIJEDA sampai '.$state->paused_until->toDateTimeString().' — '.$state->pause_reason);
        } else {
            $this->line('Status: aktif');
        }

        $this->line('next_allowed_at: '.($state->next_allowed_at?->toDateTimeString() ?? '-'));
        $this->line('consecutive_failures: '.$state->consecutive_failures);

        return self::SUCCESS;
    }
}

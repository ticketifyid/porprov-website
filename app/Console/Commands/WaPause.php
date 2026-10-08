<?php

namespace App\Console\Commands;

use App\Models\WaThrottleState;
use Illuminate\Console\Command;

class WaPause extends Command
{
    protected $signature = 'wa:pause {hours=6 : Lama jeda, dalam jam} {--reason= : Alasan jeda}';

    protected $description = 'Jeda paksa outbox WhatsApp (wa:send-next tidak akan mengirim apa pun)';

    public function handle(): int
    {
        $state = WaThrottleState::current();
        $hours = (int) $this->argument('hours');
        $reason = $this->option('reason') ?? 'Dijeda manual lewat wa:pause.';

        $state->pause($reason, $hours);

        $this->info("Outbox WhatsApp dijeda sampai {$state->refresh()->paused_until->toDateTimeString()}.");

        return self::SUCCESS;
    }
}

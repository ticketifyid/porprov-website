<?php

namespace App\Console\Commands;

use App\Models\WaThrottleState;
use Illuminate\Console\Command;

class WaResume extends Command
{
    protected $signature = 'wa:resume';

    protected $description = 'Lepas jeda outbox WhatsApp dan reset hitungan kegagalan beruntun';

    public function handle(): int
    {
        WaThrottleState::current()->resume();

        $this->info('Outbox WhatsApp aktif kembali.');

        return self::SUCCESS;
    }
}

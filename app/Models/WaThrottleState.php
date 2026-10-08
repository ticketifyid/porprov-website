<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris tunggal (id = 1) penyimpan pacing/circuit-breaker wa:send-next
 * (docs/notifikasi.md). Dipakai lewat WaThrottleState::current().
 */
#[Fillable(['next_allowed_at', 'paused_until', 'pause_reason', 'consecutive_failures'])]
class WaThrottleState extends Model
{
    private const SINGLETON_ID = 1;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_allowed_at' => 'datetime',
            'paused_until' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => self::SINGLETON_ID]);
    }

    public function isPaused(): bool
    {
        return $this->paused_until !== null && now()->lt($this->paused_until);
    }

    public function mustWaitForGap(): bool
    {
        return $this->next_allowed_at !== null && now()->lt($this->next_allowed_at);
    }

    public function pause(string $reason, int $hours): void
    {
        $this->forceFill([
            'paused_until' => now()->addHours($hours),
            'pause_reason' => $reason,
        ])->save();
    }

    public function resume(): void
    {
        $this->forceFill([
            'paused_until' => null,
            'pause_reason' => null,
            'consecutive_failures' => 0,
        ])->save();
    }
}

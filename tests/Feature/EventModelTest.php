<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $attributes = []): Event
    {
        return Event::create(array_merge([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 0,
            'is_open' => false,
        ], $attributes));
    }

    public function test_is_open_returns_false_when_manual_switch_is_off(): void
    {
        $event = $this->makeEvent(['is_open' => false]);

        $this->assertFalse($event->isOpen());
    }

    public function test_is_open_returns_false_before_registration_window_opens(): void
    {
        $event = $this->makeEvent([
            'is_open' => true,
            'registration_open_at' => now()->addDay(),
        ]);

        $this->assertFalse($event->isOpen());
    }

    public function test_is_open_returns_false_after_registration_window_closes(): void
    {
        $event = $this->makeEvent([
            'is_open' => true,
            'registration_close_at' => now()->subDay(),
        ]);

        $this->assertFalse($event->isOpen());
    }

    public function test_is_open_returns_true_within_window(): void
    {
        $event = $this->makeEvent([
            'is_open' => true,
            'registration_open_at' => now()->subDay(),
            'registration_close_at' => now()->addDay(),
        ]);

        $this->assertTrue($event->isOpen());
    }

    public function test_is_open_returns_true_when_no_window_is_set(): void
    {
        $event = $this->makeEvent(['is_open' => true]);

        $this->assertTrue($event->isOpen());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use Database\Seeders\EventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeder yang dijalankan ulang di produksi tidak boleh menimpa pengaturan
 * event yang sudah diubah admin.
 */
class EventSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_event_bila_belum_ada(): void
    {
        (new EventSeeder)->run();

        $event = Event::where('code_prefix', 'PJT26')->firstOrFail();
        $this->assertSame(3600, $event->quota);
        $this->assertFalse($event->is_open);
    }

    public function test_dijalankan_ulang_tidak_menimpa_pengaturan_admin(): void
    {
        (new EventSeeder)->run();

        Event::where('code_prefix', 'PJT26')->firstOrFail()->update([
            'name' => 'Nama Diubah Admin',
            'quota' => 2500,
            'is_open' => true,
            'tickets_taken' => 120,
        ]);

        (new EventSeeder)->run();

        $this->assertSame(1, Event::count());

        $event = Event::firstOrFail();
        $this->assertSame('Nama Diubah Admin', $event->name);
        $this->assertSame(2500, $event->quota);
        $this->assertTrue($event->is_open);
        $this->assertSame(120, $event->tickets_taken);
    }
}

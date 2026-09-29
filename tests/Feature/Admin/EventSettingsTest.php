<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 10,
            'is_open' => false,
        ]);

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_bisa_melihat_halaman_pengaturan_event(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/events')
            ->assertOk()
            ->assertSee('Kuota tiket');
    }

    public function test_scanner_tidak_bisa_mengakses_pengaturan_event(): void
    {
        $scanner = User::factory()->create(['role' => 'scanner']);

        $this->actingAs($scanner)->get('/admin/events')->assertForbidden();
        $this->actingAs($scanner)->put('/admin/events', $this->payload())->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'venue' => 'Stadion Jatidiri',
            'event_starts_at' => '2026-10-10T19:00',
            'quota' => 3600,
            'is_open' => '1',
            'registration_open_at' => '2026-09-01T00:00',
            'registration_close_at' => '2026-10-09T23:59',
        ], $overrides);
    }

    public function test_admin_bisa_menyimpan_pengaturan_event(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/events', $this->payload());

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->event->refresh();
        $this->assertSame('Stadion Jatidiri', $this->event->venue);
        $this->assertTrue($this->event->is_open);
        $this->assertSame(3600, $this->event->quota);
        $this->assertSame('2026-10-10 19:00:00', $this->event->event_starts_at->format('Y-m-d H:i:s'));
    }

    public function test_kuota_tidak_boleh_diturunkan_di_bawah_tickets_taken(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/events', $this->payload(['quota' => 5]));

        $response->assertSessionHasErrors('quota');
        $this->assertSame(3600, $this->event->fresh()->quota);
    }

    public function test_is_open_default_false_saat_checkbox_tidak_dicentang(): void
    {
        $payload = $this->payload();
        unset($payload['is_open']);

        $this->actingAs($this->admin)->put('/admin/events', $payload);

        $this->assertFalse($this->event->fresh()->is_open);
    }
}

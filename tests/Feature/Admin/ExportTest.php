<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Regency $regency;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 0,
            'is_open' => true,
        ]);

        $this->regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRegistration(array $overrides = []): Registration
    {
        static $n = 0;
        $n++;

        return Registration::forceCreate(array_merge([
            'event_id' => $this->event->id,
            'code' => sprintf('PJT26-%06d', $n),
            'token' => str_pad((string) $n, 48, 'x'),
            'name' => 'Budi Santoso',
            'regency_id' => $this->regency->id,
            'email' => "budi{$n}@gmail.com",
            'email_canonical' => "budi{$n}@gmail.com",
            'phone' => '6281234567890',
            'ticket_qty' => 3,
        ], $overrides));
    }

    public function test_scanner_tidak_bisa_export(): void
    {
        $scanner = User::factory()->create(['role' => 'scanner']);

        $this->actingAs($scanner)->get('/admin/export')->assertForbidden();
    }

    public function test_export_berisi_bom_dan_empat_kolom_urut_nama(): void
    {
        $this->makeRegistration(['name' => 'Zaki Ramadhan']);
        $this->makeRegistration(['name' => 'Andi Wijaya']);
        $dibatalkan = $this->makeRegistration(['name' => 'Cici Dibatalkan', 'cancelled_at' => now(), 'email_canonical' => null]);

        $response = $this->actingAs($this->admin)->get('/admin/export');

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $headerLine = explode("\n", substr($content, 3))[0];
        foreach (['Kode', 'Nama', 'No. HP', 'Jumlah Tiket'] as $column) {
            $this->assertStringContainsString($column, $headerLine);
        }

        $bodyLines = array_slice(explode("\n", trim(substr($content, 3))), 1);
        $bodyLines = array_values(array_filter($bodyLines));

        $this->assertCount(2, $bodyLines);
        $this->assertStringContainsString('Andi Wijaya', $bodyLines[0]);
        $this->assertStringContainsString('Zaki Ramadhan', $bodyLines[1]);
        $this->assertStringNotContainsString($dibatalkan->name, $content);
    }

    public function test_sel_berbahaya_diberi_prefix_kutip_tunggal(): void
    {
        $this->makeRegistration(['name' => '=SUM(A1:A9)']);

        $response = $this->actingAs($this->admin)->get('/admin/export');
        $content = $response->streamedContent();

        $this->assertStringContainsString("'=SUM(A1:A9)", $content);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use App\Services\Notifications\LogTicketNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * LogTicketNotifier tidak boleh menulis email utuh, nomor HP utuh, atau
 * link tiket bertoken ke log (review keamanan Fase 9).
 */
class LogTicketNotifierTest extends TestCase
{
    use RefreshDatabase;

    private Registration $registration;

    /** @var list<array{message: string, context: array<string, mixed>}> */
    private array $entries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $event = Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 2,
            'is_open' => true,
        ]);

        $regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);

        $this->registration = Registration::forceCreate([
            'event_id' => $event->id,
            'code' => 'PJT26-7K3M9Q',
            'token' => str_repeat('T', 48),
            'name' => 'Budi Santoso',
            'regency_id' => $regency->id,
            'email' => 'budi.santoso@gmail.com',
            'email_canonical' => 'budisantoso@gmail.com',
            'phone' => '6281234567890',
            'ticket_qty' => 2,
        ]);

        Log::listen(function ($event): void {
            $this->entries[] = ['message' => $event->message, 'context' => $event->context];
        });
    }

    public function test_log_email_hanya_memuat_data_yang_disamarkan(): void
    {
        (new LogTicketNotifier)->sendEmail($this->registration);

        $this->assertSame([
            'channel' => 'email',
            'registration_id' => $this->registration->id,
            'code' => 'PJT26-7K3M9Q',
            'destination' => 'bu***@gmail.com',
        ], $this->onlyEntry()['context']);
    }

    public function test_log_whatsapp_hanya_memuat_data_yang_disamarkan(): void
    {
        (new LogTicketNotifier)->sendWhatsApp($this->registration);

        $this->assertSame([
            'channel' => 'whatsapp',
            'registration_id' => $this->registration->id,
            'code' => 'PJT26-7K3M9Q',
            'destination' => '0812-****-7890',
        ], $this->onlyEntry()['context']);
    }

    public function test_email_nomor_dan_token_tidak_muncul_di_log(): void
    {
        $notifier = new LogTicketNotifier;
        $notifier->sendEmail($this->registration);
        $notifier->sendWhatsApp($this->registration);

        $this->assertCount(2, $this->entries);

        $written = json_encode($this->entries);

        foreach (['budi.santoso@gmail.com', 'budisantoso@gmail.com', '6281234567890', '081234567890', str_repeat('T', 48), '/tiket/', 'Budi Santoso'] as $secret) {
            $this->assertStringNotContainsString($secret, $written);
        }
    }

    /**
     * @return array{message: string, context: array<string, mixed>}
     */
    private function onlyEntry(): array
    {
        $this->assertCount(1, $this->entries);

        return $this->entries[0];
    }
}

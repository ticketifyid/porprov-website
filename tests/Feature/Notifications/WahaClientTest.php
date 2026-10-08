<?php

namespace Tests\Feature\Notifications;

use App\Exceptions\WahaSendFailedException;
use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use App\Services\Notifications\MailTicketNotifier;
use App\Services\Notifications\WahaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * App\Services\Notifications\WahaClient (transport murni) dan urutan
 * panggilannya lewat MailTicketNotifier::sendWhatsApp() ketika
 * TICKET_WA_DRIVER=waha (docs/whatsapp-api.md, docs/notifikasi.md). Tidak
 * pernah memanggil WAHA sungguhan — selalu Http::fake().
 */
class WahaClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ticket_wa_driver' => 'waha',
            'services.waha.url' => 'https://wa.test',
            'services.waha.api_key' => 'rahasia-api-key',
            'services.waha.session' => 'dwilaptop',
        ]);

        Sleep::fake();
    }

    private function registration(array $overrides = []): Registration
    {
        $event = Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'is_open' => true,
            'event_starts_at' => '2026-11-14 19:00:00',
            'venue' => 'Stadion Jatidiri Semarang',
        ]);

        $regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);

        return Registration::forceCreate(array_merge([
            'event_id' => $event->id,
            'code' => 'PJT26-7K3M9Q',
            'token' => str_repeat('a', 48),
            'name' => 'Budi Santoso',
            'regency_id' => $regency->id,
            'email' => 'budi@gmail.com',
            'email_canonical' => 'budi@gmail.com',
            'phone' => '6281234567890',
            'wa_consent' => true,
            'ticket_qty' => 2,
        ], $overrides));
    }

    // -------------------------------------------------------------- urutan panggilan

    public function test_urutan_panggilan_start_typing_send_text_stop_typing(): void
    {
        Http::fake(['wa.test/api/*' => Http::response(['id' => 'wa-msg-1'], 201)]);

        $registration = $this->registration();

        $this->app->make(MailTicketNotifier::class)->sendWhatsApp($registration);

        Http::assertSentInOrder([
            fn ($request) => $request->url() === 'https://wa.test/api/startTyping',
            fn ($request) => $request->url() === 'https://wa.test/api/sendText',
            fn ($request) => $request->url() === 'https://wa.test/api/stopTyping',
        ]);
    }

    public function test_header_dan_session_dari_config(): void
    {
        Http::fake(['wa.test/api/*' => Http::response(['id' => 'wa-msg-1'], 201)]);

        $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://wa.test/api/sendText'
                && $request->hasHeader('X-Api-Key', 'rahasia-api-key')
                && $request['session'] === 'dwilaptop';
        });
    }

    public function test_chat_id_dari_nomor_ternormalisasi(): void
    {
        Http::fake(['wa.test/api/*' => Http::response(['id' => 'wa-msg-1'], 201)]);

        $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration(['phone' => '6281234567890']));

        Http::assertSent(fn ($request) => ($request['chatId'] ?? null) === '6281234567890@c.us');
    }

    public function test_api_key_tidak_pernah_masuk_body_request(): void
    {
        Http::fake(['wa.test/api/*' => Http::response(['id' => 'wa-msg-1'], 201)]);

        $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration());

        Http::assertSent(fn ($request) => ! str_contains(json_encode($request->data()), 'rahasia-api-key'));
    }

    // -------------------------------------------------------------- message id defensif

    public function test_message_id_diambil_dari_id_string(): void
    {
        Http::fake(['wa.test/api/*' => Http::response(['id' => 'wa-msg-1'], 201)]);

        $id = $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration());

        $this->assertSame('wa-msg-1', $id);
    }

    public function test_message_id_diambil_dari_id_serialized(): void
    {
        Http::fake(['wa.test/api/*' => Http::response(['id' => ['_serialized' => 'wa-msg-2']], 201)]);

        $id = $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration());

        $this->assertSame('wa-msg-2', $id);
    }

    public function test_message_id_diambil_dari_key_id(): void
    {
        Http::fake(['wa.test/api/*' => Http::response(['key' => ['id' => 'wa-msg-3']], 201)]);

        $id = $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration());

        $this->assertSame('wa-msg-3', $id);
    }

    public function test_message_id_null_jika_tidak_ditemukan(): void
    {
        Http::fake(['wa.test/api/*' => Http::response([], 201)]);

        $id = $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration());

        $this->assertNull($id);
    }

    // -------------------------------------------------------------- kegagalan

    public function test_status_bukan_201_melempar_exception(): void
    {
        Http::fake(['wa.test/api/*' => Http::response(['error' => 'gagal'], 500)]);

        $this->expectException(WahaSendFailedException::class);

        $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration());
    }

    public function test_kegagalan_stop_typing_tidak_membatalkan_pengiriman(): void
    {
        Http::fake([
            'wa.test/api/startTyping' => Http::response([], 201),
            'wa.test/api/sendText' => Http::response(['id' => 'wa-msg-ok'], 201),
            'wa.test/api/stopTyping' => Http::response([], 500),
        ]);

        $id = $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration());

        $this->assertSame('wa-msg-ok', $id);
    }

    public function test_driver_log_tidak_memanggil_waha(): void
    {
        config(['services.ticket_wa_driver' => 'log']);
        Http::fake();

        $this->app->make(MailTicketNotifier::class)->sendWhatsApp($this->registration());

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------- transport murni

    public function test_waha_client_sessions_memanggil_get_api_sessions(): void
    {
        Http::fake(['wa.test/api/sessions' => Http::response([], 200)]);

        $this->app->make(WahaClient::class)->sessions();

        Http::assertSent(fn ($request) => $request->url() === 'https://wa.test/api/sessions' && $request->method() === 'GET');
    }
}

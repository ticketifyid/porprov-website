<?php

namespace Tests\Feature;

use App\Contracts\TicketNotifier;
use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teks kanal notifikasi di halaman peserta mengikuti kanal yang benar-benar
 * aktif (TicketNotifier::deliversWhatsApp), bukan teks tetap.
 */
class NotificationChannelTextTest extends TestCase
{
    use RefreshDatabase;

    private Registration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $event = Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 1,
            'is_open' => true,
        ]);

        $regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);

        $this->registration = Registration::forceCreate([
            'event_id' => $event->id,
            'code' => 'PJT26-7K3M9Q',
            'token' => str_repeat('t', 48),
            'name' => 'Budi Santoso',
            'regency_id' => $regency->id,
            'email' => 'budi@gmail.com',
            'email_canonical' => 'budi@gmail.com',
            'phone' => '6281234567890',
            'ticket_qty' => 1,
        ]);
    }

    private function bindNotifier(bool $whatsapp): void
    {
        $this->app->instance(TicketNotifier::class, new class($whatsapp) implements TicketNotifier
        {
            public function __construct(private bool $whatsapp) {}

            public function sendEmail(Registration $registration): ?string
            {
                return null;
            }

            public function sendWhatsApp(Registration $registration): ?string
            {
                return null;
            }

            public function deliversWhatsApp(): bool
            {
                return $this->whatsapp;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    private function pages(): array
    {
        return [
            'beranda' => $this->get('/')->assertOk()->getContent(),
            'form' => $this->get('/daftar')->assertOk()->getContent(),
            'sukses' => $this->get('/daftar/sukses/'.$this->registration->token)->assertOk()->getContent(),
            'cari' => $this->get('/cari-tiket')->assertOk()->getContent(),
            'cari-terkirim' => $this->post('/cari-tiket', ['contact' => 'tidakada@gmail.com'])->assertOk()->getContent(),
        ];
    }

    public function test_binding_bawaan_tidak_mengirim_whatsapp(): void
    {
        foreach (['log', 'mail'] as $driver) {
            config(['services.ticket_notifier' => $driver]);
            $this->app->forgetInstance(TicketNotifier::class);

            $this->assertFalse(app(TicketNotifier::class)->deliversWhatsApp(), $driver);
        }
    }

    public function test_selama_whatsapp_belum_aktif_teks_hanya_menyebut_email(): void
    {
        $this->bindNotifier(false);

        foreach ($this->pages() as $page => $html) {
            $this->assertStringNotContainsString('email dan WhatsApp', $html, $page);
        }

        $this->get('/')->assertSeeText('Link e-ticket berisi QR dikirim ke email Anda.');
        $this->get('/daftar')
            ->assertSeeText('E-ticket berisi QR dikirim ke email Anda.')
            ->assertSeeText('E-ticket dikirim ke email di bawah.')
            ->assertDontSeeText('E-ticket dikirim ke nomor ini.');
        $this->get('/daftar/sukses/'.$this->registration->token)
            ->assertSeeText('E-ticket sudah dikirim ke email Anda.');
        $this->get('/cari-tiket')->assertSeeText('Link e-ticket akan dikirim ulang ke email yang terdaftar.');
        $this->post('/cari-tiket', ['contact' => 'tidakada@gmail.com'])
            ->assertSeeText('Jika data terdaftar, link e-ticket sudah dikirim ulang ke email Anda.');
    }

    public function test_saat_whatsapp_aktif_teks_menyebut_email_dan_whatsapp(): void
    {
        $this->bindNotifier(true);

        $this->get('/')->assertSeeText('Link e-ticket berisi QR dikirim ke email dan WhatsApp Anda.');
        $this->get('/daftar')
            ->assertSeeText('E-ticket berisi QR dikirim ke email dan WhatsApp Anda.')
            ->assertSeeText('E-ticket dikirim ke email dan WhatsApp di bawah.')
            ->assertSeeText('E-ticket dikirim ke nomor ini.');
        $this->get('/daftar/sukses/'.$this->registration->token)
            ->assertSeeText('E-ticket sudah dikirim ke email dan WhatsApp Anda.');
        $this->get('/cari-tiket')->assertSeeText('Link e-ticket akan dikirim ulang ke kontak tersebut.');
        $this->post('/cari-tiket', ['contact' => 'tidakada@gmail.com'])
            ->assertSeeText('Jika data terdaftar, link e-ticket sudah dikirim ulang ke email dan WhatsApp Anda.');
    }
}

<?php

namespace Tests\Feature;

use App\Contracts\TicketNotifier;
use App\Jobs\SendTicketNotification;
use App\Mail\TicketMail;
use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Regency;
use App\Models\Registration;
use App\Services\Notifications\MailTicketNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Implementasi email asli Fase 10 (docs/notifikasi.md). Berdiri sendiri:
 * tes job ada di NotificationJobTest.
 */
class MailTicketNotifierTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Registration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 2,
            'is_open' => true,
            'event_starts_at' => '2026-11-14 19:00:00',
            'venue' => 'Stadion Jatidiri Semarang',
        ]);

        $regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);

        $this->registration = Registration::forceCreate([
            'event_id' => $this->event->id,
            'code' => 'PJT26-7K3M9Q',
            'token' => str_repeat('a', 24).str_repeat('B', 24),
            'name' => 'Budi Santoso',
            'regency_id' => $regency->id,
            'email' => 'budi.santoso+tiket@gmail.com',
            'email_canonical' => 'budisantoso@gmail.com',
            'phone' => '6281234567890',
            'ticket_qty' => 2,
        ]);
    }

    private function notifier(): MailTicketNotifier
    {
        return $this->app->make(MailTicketNotifier::class);
    }

    private function ticketUrl(): string
    {
        return url('/tiket/'.$this->registration->token);
    }

    // -------------------------------------------------------------- binding

    public function test_binding_mail_memakai_mail_ticket_notifier(): void
    {
        $this->app->forgetInstance(TicketNotifier::class);
        config(['services.ticket_notifier' => 'mail']);

        $this->assertInstanceOf(MailTicketNotifier::class, $this->app->make(TicketNotifier::class));
    }

    public function test_smtp_punya_timeout_sepuluh_detik(): void
    {
        $this->assertSame(10, (int) config('mail.mailers.smtp.timeout'));
    }

    // -------------------------------------------------------------- email

    public function test_email_dikirim_langsung_ke_alamat_asli_bukan_email_canonical(): void
    {
        Mail::fake();

        $this->notifier()->sendEmail($this->registration);

        Mail::assertSent(TicketMail::class, function (TicketMail $mail): bool {
            return $mail->hasTo('budi.santoso+tiket@gmail.com')
                && ! $mail->hasTo('budisantoso@gmail.com');
        });
        Mail::assertSentCount(1);
        Mail::assertNothingQueued();
    }

    public function test_subjek_memuat_kode_registrasi(): void
    {
        $mail = new TicketMail($this->registration);

        $mail->assertHasSubject('E-ticket Opening Ceremony Porprov Jateng XVII 2026 - PJT26-7K3M9Q');
    }

    public function test_isi_memuat_nama_kode_jumlah_gelang_dan_link_tiket_berbasis_token(): void
    {
        $mail = new TicketMail($this->registration);

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assert) {
            $mail->{$assert}('Budi Santoso');
            $mail->{$assert}('PJT26-7K3M9Q');
            $mail->{$assert}('tukar dengan 2 gelang');
            $mail->{$assert}($this->ticketUrl(), false);
            $mail->{$assert}('Tunjukkan QR', false);
        }

        $mail->assertDontSeeInHtml('/tiket/PJT26-7K3M9Q', false);
        $mail->assertDontSeeInText('/tiket/PJT26-7K3M9Q', false);
    }

    public function test_html_memakai_navy_dan_tanpa_gambar_eksternal(): void
    {
        $html = (new TicketMail($this->registration))->render();

        $this->assertStringContainsString('#0E2A6B', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_tanggal_dan_lokasi_tampil_jika_terisi(): void
    {
        $mail = new TicketMail($this->registration);

        foreach (['assertSeeInHtml', 'assertSeeInText'] as $assert) {
            $mail->{$assert}('14 November 2026, 19.00');
            $mail->{$assert}('Stadion Jatidiri Semarang');
        }
    }

    public function test_tanggal_dan_lokasi_disembunyikan_jika_null(): void
    {
        $this->event->update(['event_starts_at' => null, 'venue' => null]);
        $this->registration->unsetRelation('event');

        $mail = new TicketMail($this->registration);

        foreach (['assertDontSeeInHtml', 'assertDontSeeInText'] as $assert) {
            $mail->{$assert}('Tanggal');
            $mail->{$assert}('Lokasi');
        }
        $mail->assertSeeInHtml($this->ticketUrl(), false);
    }

    public function test_message_id_dari_mailer_dikembalikan_sebagai_provider_id(): void
    {
        config(['mail.default' => 'array']);

        $messageId = $this->notifier()->sendEmail($this->registration);

        $this->assertIsString($messageId);
        $this->assertNotSame('', $messageId);
    }

    public function test_gagal_kirim_melempar_exception(): void
    {
        Mail::shouldReceive('to')->andThrow(new TransportException('Connection timed out'));

        $this->expectException(TransportException::class);

        $this->notifier()->sendEmail($this->registration);
    }

    // -------------------------------------------------------------- whatsapp

    public function test_whatsapp_tetap_hanya_menulis_log(): void
    {
        Mail::fake();
        Log::shouldReceive('info')->once();

        $messageId = $this->notifier()->sendWhatsApp($this->registration);

        $this->assertStringStartsWith('log-', $messageId);
        Mail::assertNothingSent();
    }

    // -------------------------------------------------------------- last_error tersaring

    public function test_pesan_error_yang_disimpan_sudah_tersaring(): void
    {
        $token = $this->registration->token;

        Mail::shouldReceive('to')->andThrow(new TransportException(
            'Failed to authenticate on SMTP server with username "noreply@ticketify.id" '
            .'at smtp://noreply:Rahasia123@mail.ticketify.id:465 password=Rahasia123 '
            .'Authorization: Bearer sk_live_abcdef '
            .'rcpt budi.santoso+tiket@gmail.com phone +62 812-3456-7890 / 6281234567890 '
            ."url /tiket/{$token}"
        ));

        $this->app->forgetInstance(TicketNotifier::class);
        config(['services.ticket_notifier' => 'mail']);

        try {
            (new SendTicketNotification($this->registration->id, 'email'))
                ->handle($this->app->make(TicketNotifier::class));
            $this->fail('Exception seharusnya dilempar ulang.');
        } catch (TransportException) {
            // diharapkan
        }

        $error = NotificationLog::where('registration_id', $this->registration->id)->firstOrFail()->last_error;

        $this->assertStringStartsWith(TransportException::class.': ', $error);
        $this->assertStringContainsString('Failed to authenticate', $error);
        foreach ([
            'noreply@ticketify.id', 'noreply:', 'Rahasia123', 'sk_live_abcdef',
            'budi.santoso', 'gmail.com', '812-3456-7890', '6281234567890', $token,
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $error);
        }
        $this->assertLessThanOrEqual(500, mb_strlen($error));
    }

    public function test_pesan_error_panjang_dipotong_500_karakter(): void
    {
        $job = new SendTicketNotification($this->registration->id, 'email');
        NotificationLog::create([
            'registration_id' => $this->registration->id,
            'channel' => 'email',
            'status' => 'pending',
            'attempts' => 0,
        ]);

        $job->failed(new RuntimeException(str_repeat('gagal ', 200)));

        $error = NotificationLog::firstOrFail()->last_error;
        $this->assertSame(500, mb_strlen($error));
        $this->assertStringStartsWith(RuntimeException::class.': gagal', $error);
    }
}

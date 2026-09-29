<?php

namespace Tests\Feature;

use App\Contracts\TicketNotifier;
use App\Jobs\SendTicketNotification;
use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Regency;
use App\Models\Registration;
use App\Services\Notifications\LogTicketNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Implementasi palsu dari App\Contracts\TicketNotifier — sengaja BUKAN
 * LogTicketNotifier, supaya yang diuji adalah kontrak interface-nya
 * (docs/prompts.md Fase 5).
 */
class FakeTicketNotifier implements TicketNotifier
{
    /** @var list<string> Urutan method yang dipanggil: "email" / "whatsapp". */
    public array $calls = [];

    /** Jumlah panggilan pertama yang harus gagal sebelum berhasil. */
    public int $failTimes = 0;

    public string $failMessage = 'SMTP menolak koneksi';

    public function __construct(public ?string $messageId = 'fake-123') {}

    public function sendEmail(Registration $registration): ?string
    {
        return $this->record('email');
    }

    public function sendWhatsApp(Registration $registration): ?string
    {
        return $this->record('whatsapp');
    }

    private function record(string $channel): ?string
    {
        $this->calls[] = $channel;

        if (count($this->calls) <= $this->failTimes) {
            throw new RuntimeException($this->failMessage);
        }

        return $this->messageId;
    }
}

class NotificationJobTest extends TestCase
{
    use RefreshDatabase;

    private Registration $registration;

    private FakeTicketNotifier $notifier;

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

        $this->registration = Registration::create([
            'event_id' => $event->id,
            'code' => 'PJT26-7K3M9Q',
            'token' => str_repeat('a', 48),
            'name' => 'Budi Santoso',
            'regency_id' => $regency->id,
            'email' => 'budi@gmail.com',
            'email_canonical' => 'budi@gmail.com',
            'phone' => '6281234567890',
            'ticket_qty' => 2,
        ]);

        $this->notifier = new FakeTicketNotifier;
        $this->app->instance(TicketNotifier::class, $this->notifier);
    }

    private function log(string $channel = 'email'): NotificationLog
    {
        return NotificationLog::create([
            'registration_id' => $this->registration->id,
            'channel' => $channel,
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }

    private function job(string $channel = 'email'): SendTicketNotification
    {
        return new SendTicketNotification($this->registration->id, $channel);
    }

    private function runJob(string $channel = 'email'): void
    {
        $this->job($channel)->handle($this->notifier);
    }

    // -------------------------------------------------------------- binding

    public function test_binding_default_memakai_log_ticket_notifier(): void
    {
        $this->app->forgetInstance(TicketNotifier::class);
        config(['services.ticket_notifier' => 'log']);

        $this->assertInstanceOf(LogTicketNotifier::class, $this->app->make(TicketNotifier::class));
    }

    public function test_binding_menolak_driver_yang_tidak_dikenal(): void
    {
        $this->app->forgetInstance(TicketNotifier::class);
        config(['services.ticket_notifier' => 'wa']);

        $this->expectException(\InvalidArgumentException::class);

        $this->app->make(TicketNotifier::class);
    }

    // -------------------------------------------------------------- sukses

    public function test_email_sukses_menandai_log_sent(): void
    {
        $log = $this->log('email');

        $this->runJob('email');

        $log->refresh();
        $this->assertSame(['email'], $this->notifier->calls);
        $this->assertSame('sent', $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertSame('fake-123', $log->provider_message_id);
        $this->assertSame(0, $log->attempts);
        $this->assertNull($log->last_error);
    }

    public function test_whatsapp_sukses_memanggil_method_whatsapp(): void
    {
        $log = $this->log('whatsapp');

        $this->runJob('whatsapp');

        $log->refresh();
        $this->assertSame(['whatsapp'], $this->notifier->calls);
        $this->assertSame('sent', $log->status);
        $this->assertSame('fake-123', $log->provider_message_id);
    }

    public function test_provider_message_id_null_tetap_dianggap_sukses(): void
    {
        $this->notifier->messageId = null;
        $log = $this->log();

        $this->runJob();

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertNull($log->provider_message_id);
        $this->assertNotNull($log->sent_at);
    }

    public function test_channel_tidak_dikenal_ditolak(): void
    {
        $this->log('email');

        $this->expectException(\InvalidArgumentException::class);

        $this->runJob('sms');
    }

    // -------------------------------------------------------------- retry

    public function test_gagal_menaikkan_attempts_dan_melempar_ulang_supaya_queue_retry(): void
    {
        $this->notifier->failTimes = 1;
        $log = $this->log();

        try {
            $this->runJob();
            $this->fail('Job seharusnya melempar ulang exception supaya queue menjadwalkan retry.');
        } catch (RuntimeException $e) {
            $this->assertSame('SMTP menolak koneksi', $e->getMessage());
        }

        $log->refresh();
        $this->assertSame('pending', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertStringContainsString('SMTP menolak koneksi', $log->last_error);
        $this->assertNull($log->sent_at);
    }

    public function test_percobaan_berikutnya_yang_berhasil_membersihkan_last_error(): void
    {
        $this->notifier->failTimes = 1;
        $log = $this->log();

        try {
            $this->runJob();
        } catch (RuntimeException) {
            // percobaan pertama memang gagal
        }

        $this->runJob();

        $log->refresh();
        $this->assertSame('sent', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertNull($log->last_error);
        $this->assertNotNull($log->sent_at);
    }

    public function test_kontrak_retry_maks_tiga_percobaan(): void
    {
        $job = $this->job();

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff);
    }

    // -------------------------------------------------------------- gagal permanen

    public function test_gagal_permanen_menandai_log_failed_dengan_last_error(): void
    {
        $this->notifier->failTimes = 3;
        $log = $this->log();

        $exception = null;

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->runJob();
            } catch (RuntimeException $e) {
                $exception = $e;
            }
        }

        $this->job()->failed($exception);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame(3, $log->attempts);
        $this->assertStringContainsString('SMTP menolak koneksi', $log->last_error);
        $this->assertNull($log->sent_at);
    }

    // -------------------------------------------------------------- idempoten & data hilang

    public function test_log_yang_sudah_sent_tidak_dikirim_ulang(): void
    {
        $log = $this->log();
        $log->update([
            'status' => 'sent',
            'sent_at' => now()->subHour(),
            'provider_message_id' => 'fake-lama',
        ]);
        $sentAt = $log->fresh()->sent_at;

        $this->runJob();

        $log->refresh();
        $this->assertSame([], $this->notifier->calls);
        $this->assertSame('fake-lama', $log->provider_message_id);
        $this->assertTrue($sentAt->equalTo($log->sent_at));
    }

    public function test_registrasi_yang_sudah_dihapus_tidak_membuat_job_gagal(): void
    {
        $id = $this->registration->id;
        $this->registration->delete();

        (new SendTicketNotification($id, 'email'))->handle($this->notifier);

        $this->assertSame([], $this->notifier->calls);
    }

    public function test_registrasi_yang_sudah_dibatalkan_tidak_dikirim_dan_log_tetap_pending(): void
    {
        $log = $this->log();
        $this->registration->update(['cancelled_at' => now(), 'email_canonical' => null]);

        $this->runJob();

        $log->refresh();
        $this->assertSame([], $this->notifier->calls);
        $this->assertSame('pending', $log->status);
        $this->assertNull($log->sent_at);
    }

    public function test_job_tetap_jalan_meski_baris_log_belum_ada(): void
    {
        $this->runJob();

        $log = NotificationLog::where('registration_id', $this->registration->id)
            ->where('channel', 'email')
            ->firstOrFail();

        $this->assertSame('sent', $log->status);
        $this->assertSame(['email'], $this->notifier->calls);
    }
}

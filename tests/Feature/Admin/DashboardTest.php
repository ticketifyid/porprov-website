<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendTicketNotification;
use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\ScanLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DashboardTest extends TestCase
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
            'quota' => 100,
            'tickets_taken' => 10,
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
            'name' => 'Peserta '.$n,
            'regency_id' => $this->regency->id,
            'email' => "peserta{$n}@gmail.com",
            'email_canonical' => "peserta{$n}@gmail.com",
            'phone' => '6281234567890',
            'ticket_qty' => 2,
        ], $overrides));
    }

    public function test_scanner_tidak_bisa_mengakses_dashboard(): void
    {
        $scanner = User::factory()->create(['role' => 'scanner']);

        $this->actingAs($scanner)->get('/admin')->assertForbidden();
        $this->actingAs($scanner)->get('/admin/dashboard-data')->assertForbidden();
        $this->actingAs($scanner)->post('/admin/dashboard/resend-failed')->assertForbidden();
    }

    public function test_dashboard_menampilkan_ringkasan_yang_benar(): void
    {
        $redeemed = $this->makeRegistration(['ticket_qty' => 3, 'redeemed_at' => now(), 'redeemed_by' => $this->admin->id]);
        $cancelled = $this->makeRegistration(['ticket_qty' => 2, 'cancelled_at' => now(), 'email_canonical' => null]);
        $turnstile = $this->makeRegistration(['ticket_qty' => 1, 'verified_via' => 'turnstile']);
        $captcha = $this->makeRegistration(['ticket_qty' => 1, 'verified_via' => 'captcha']);

        NotificationLog::create(['registration_id' => $redeemed->id, 'channel' => 'email', 'status' => 'sent', 'sent_at' => now()]);
        NotificationLog::create(['registration_id' => $turnstile->id, 'channel' => 'email', 'status' => 'pending']);
        NotificationLog::create(['registration_id' => $captcha->id, 'channel' => 'email', 'status' => 'failed', 'last_error' => 'Koneksi gagal']);

        ScanLog::create([
            'registration_id' => $redeemed->id,
            'user_id' => $this->admin->id,
            'scanned_value' => $redeemed->token,
            'method' => 'hardware',
            'result' => 'success',
            'scanned_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/dashboard-data');

        $response->assertOk();
        $response->assertJson([
            'ticketsTaken' => 10,
            'ticketsRemaining' => 90,
            'quota' => 100,
            // registrantCount tidak menghitung registrasi yang dibatalkan.
            'registrantCount' => 3,
            'checkedInTickets' => 3,
            // totalTickets: 3 (redeemed) + 1 (turnstile) + 1 (captcha), cancelled dikecualikan.
            'totalTickets' => 5,
            'recentScans' => 1,
            'notifSent' => 1,
            'notifPending' => 1,
            'notifFailed' => 1,
            'emailFailedCount' => 1,
            'verifiedTurnstile' => 1,
            'verifiedCaptcha' => 1,
            'verifiedNone' => 1,
        ]);
    }

    public function test_quota_percent_di_atas_90_persen(): void
    {
        $this->event->update(['quota' => 100, 'tickets_taken' => 95]);

        $response = $this->actingAs($this->admin)->get('/admin/dashboard-data');

        $response->assertOk()->assertJsonPath('quotaPercent', 95);
    }

    public function test_kirim_ulang_semua_yang_gagal_hanya_mengenai_notifikasi_email_gagal_dan_belum_dibatalkan(): void
    {
        Queue::fake();

        $emailFailed = $this->makeRegistration(['name' => 'Email Gagal']);
        NotificationLog::create(['registration_id' => $emailFailed->id, 'channel' => 'email', 'status' => 'failed']);
        NotificationLog::create(['registration_id' => $emailFailed->id, 'channel' => 'whatsapp', 'status' => 'sent', 'sent_at' => now()]);

        $whatsappFailedOnly = $this->makeRegistration(['name' => 'WA Gagal Saja']);
        NotificationLog::create(['registration_id' => $whatsappFailedOnly->id, 'channel' => 'email', 'status' => 'sent', 'sent_at' => now()]);
        NotificationLog::create(['registration_id' => $whatsappFailedOnly->id, 'channel' => 'whatsapp', 'status' => 'failed']);

        $cancelledEmailFailed = $this->makeRegistration(['name' => 'Dibatalkan', 'cancelled_at' => now(), 'email_canonical' => null]);
        NotificationLog::create(['registration_id' => $cancelledEmailFailed->id, 'channel' => 'email', 'status' => 'failed']);

        $sentOk = $this->makeRegistration(['name' => 'Terkirim Semua']);
        NotificationLog::create(['registration_id' => $sentOk->id, 'channel' => 'email', 'status' => 'sent', 'sent_at' => now()]);

        $response = $this->actingAs($this->admin)->post('/admin/dashboard/resend-failed');

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Queue::assertPushed(SendTicketNotification::class, 2);

        $this->assertSame(
            'pending',
            NotificationLog::where('registration_id', $emailFailed->id)->where('channel', 'email')->value('status'),
        );
        $this->assertSame(
            'failed',
            NotificationLog::where('registration_id', $whatsappFailedOnly->id)->where('channel', 'whatsapp')->value('status'),
            'Registrasi yang hanya WhatsApp-nya gagal tidak termasuk kriteria "email failed".',
        );
        $this->assertSame(
            'failed',
            NotificationLog::where('registration_id', $cancelledEmailFailed->id)->where('channel', 'email')->value('status'),
            'Registrasi yang sudah dibatalkan tidak ikut dikirim ulang.',
        );
    }

    public function test_kirim_ulang_semua_yang_gagal_tanpa_target_memberi_pesan_netral(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/dashboard/resend-failed');

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Tidak ada notifikasi gagal yang perlu dikirim ulang.');
    }
}

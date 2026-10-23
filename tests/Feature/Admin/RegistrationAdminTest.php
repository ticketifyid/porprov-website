<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendTicketNotification;
use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegistrationAdminTest extends TestCase
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
            'tickets_taken' => 5,
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

    // ---------- akses ----------

    public function test_scanner_tidak_bisa_mengakses_halaman_admin_registrasi(): void
    {
        $scanner = User::factory()->create(['role' => 'scanner']);
        $registration = $this->makeRegistration();

        $this->actingAs($scanner)->get('/admin/registrations')->assertForbidden();
        $this->actingAs($scanner)->get('/admin/registrations/'.$registration->id)->assertForbidden();
        $this->actingAs($scanner)->post('/admin/registrations/'.$registration->id.'/cancel')->assertForbidden();
        $this->actingAs($scanner)->post('/admin/registrations/'.$registration->id.'/resend')->assertForbidden();
    }

    // ---------- daftar & detail ----------

    public function test_admin_bisa_melihat_daftar_dan_detail_peserta(): void
    {
        $registration = $this->makeRegistration();

        $this->actingAs($this->admin)->get('/admin/registrations')
            ->assertOk()->assertSee($registration->code);

        $this->actingAs($this->admin)->get('/admin/registrations/'.$registration->id)
            ->assertOk()->assertSee($registration->name);
    }

    public function test_detail_peserta_dengan_notifikasi_pending_dan_failed_tetap_200(): void
    {
        $registration = $this->makeRegistration();
        NotificationLog::create([
            'registration_id' => $registration->id,
            'channel' => 'email',
            'status' => 'pending',
            'attempts' => 0,
        ]);
        $failed = $this->makeRegistration();
        NotificationLog::create([
            'registration_id' => $failed->id,
            'channel' => 'email',
            'status' => 'failed',
            'attempts' => 3,
            'last_error' => 'Koneksi gagal',
        ]);

        $this->actingAs($this->admin)->get('/admin/registrations/'.$registration->id)
            ->assertOk()
            ->assertSeeText('Menunggu');

        $this->actingAs($this->admin)->get('/admin/registrations/'.$failed->id)
            ->assertOk()
            ->assertSeeText('Gagal');
    }

    public function test_detail_peserta_tidak_menampilkan_baris_kanal_whatsapp(): void
    {
        $registration = $this->makeRegistration();
        NotificationLog::create(['registration_id' => $registration->id, 'channel' => 'email', 'status' => 'sent', 'sent_at' => now()]);
        NotificationLog::create(['registration_id' => $registration->id, 'channel' => 'whatsapp', 'status' => 'sent', 'sent_at' => now()]);

        $this->actingAs($this->admin)->get('/admin/registrations/'.$registration->id)
            ->assertOk()
            ->assertDontSeeText('whatsapp');
    }

    public function test_registrasi_dengan_log_whatsapp_lama_aman_di_admin_dan_kirim_ulang_hanya_email(): void
    {
        Queue::fake();
        $registration = $this->makeRegistration();
        NotificationLog::create(['registration_id' => $registration->id, 'channel' => 'email', 'status' => 'sent', 'attempts' => 1, 'sent_at' => now()]);
        NotificationLog::create(['registration_id' => $registration->id, 'channel' => 'whatsapp', 'status' => 'sent', 'attempts' => 1, 'sent_at' => now()]);

        $this->actingAs($this->admin)->get('/admin/registrations')->assertOk()->assertSee($registration->code);
        $this->actingAs($this->admin)->get('/admin/registrations/'.$registration->id)->assertOk();
        $this->actingAs($this->admin)->get('/admin')->assertOk();
        $this->actingAs($this->admin)->get('/admin/dashboard-data')->assertOk();

        $this->actingAs($this->admin)
            ->post('/admin/registrations/'.$registration->id.'/resend')
            ->assertRedirect();

        Queue::assertPushed(SendTicketNotification::class, 1);
        Queue::assertPushed(SendTicketNotification::class, fn (SendTicketNotification $job) => $job->channel === 'email');
        $this->assertSame('pending', NotificationLog::where('registration_id', $registration->id)->where('channel', 'email')->value('status'));
        $this->assertSame('sent', NotificationLog::where('registration_id', $registration->id)->where('channel', 'whatsapp')->value('status'));
    }

    public function test_daftar_peserta_menampilkan_kontak_dan_menyaring_status(): void
    {
        $pending = $this->makeRegistration(['name' => 'Peserta Belum', 'email' => 'belum@example.com']);
        $redeemed = $this->makeRegistration(['name' => 'Peserta Tukar', 'redeemed_at' => now()]);
        $cancelled = $this->makeRegistration(['name' => 'Peserta Batal', 'cancelled_at' => now()]);

        $this->actingAs($this->admin)->get('/admin/registrations')
            ->assertOk()
            ->assertSeeText('No. HP')
            ->assertSeeText('belum@example.com')
            ->assertDontSeeText('Verifikasi')
            ->assertSee('<option value="redeemed"', false)
            ->assertSee($pending->code)->assertSee($redeemed->code)->assertSee($cancelled->code);

        $this->actingAs($this->admin)->get('/admin/registrations?status=redeemed')
            ->assertOk()
            ->assertSee($redeemed->code)
            ->assertDontSee($pending->code)
            ->assertDontSee($cancelled->code)
            ->assertSee('<option value="redeemed" selected', false);

        $this->actingAs($this->admin)->get('/admin/registrations?status=pending')
            ->assertOk()->assertSee($pending->code)->assertDontSee($redeemed->code)->assertDontSee($cancelled->code);

        $this->actingAs($this->admin)->get('/admin/registrations?status=cancelled&q=Peserta')
            ->assertOk()->assertSee($cancelled->code)->assertDontSee($pending->code);

        $this->actingAs($this->admin)->get('/admin/registrations?q=belum@example')
            ->assertOk()->assertSee($pending->code)->assertDontSee($redeemed->code);

        // Nilai tak dikenal diabaikan, bukan error.
        $this->actingAs($this->admin)->get('/admin/registrations?status=xss')
            ->assertOk()->assertSee($pending->code);
    }

    public function test_daftar_akun_bisa_dicari_dan_disaring_tipe(): void
    {
        User::factory()->create(['name' => 'Sari Scanner', 'username' => 'sari', 'role' => 'scanner']);
        User::factory()->create(['name' => 'Andi Admin', 'username' => 'andi', 'role' => 'admin']);

        $this->actingAs($this->admin)->get('/admin/users?role=scanner')
            ->assertOk()->assertSee('Sari Scanner')->assertDontSee('Andi Admin')
            ->assertSee('<option value="scanner" selected', false);

        $this->actingAs($this->admin)->get('/admin/users?q=andi')
            ->assertOk()->assertSee('Andi Admin')->assertDontSee('Sari Scanner');

        $this->actingAs($this->admin)->get('/admin/users?role=xss')
            ->assertOk()->assertSee('Sari Scanner')->assertSee('Andi Admin');
    }

    public function test_daftar_peserta_dan_akun_mengumumkan_hasil_pencarian_ke_pembaca_layar(): void
    {
        $this->actingAs($this->admin)->get('/admin/registrations')
            ->assertOk()
            ->assertSee('data-live-results', false)
            ->assertSee('aria-live="polite"', false);

        $this->actingAs($this->admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('aria-live="polite"', false);
    }

    public function test_detail_peserta_tanpa_error_notifikasi_tidak_memakai_tanda_pisah_panjang(): void
    {
        $registration = $this->makeRegistration();
        NotificationLog::create(['registration_id' => $registration->id, 'channel' => 'email', 'status' => 'sent', 'sent_at' => now()]);

        $this->actingAs($this->admin)->get('/admin/registrations/'.$registration->id)
            ->assertOk()
            ->assertDontSee('—', false)
            ->assertDontSee('&mdash;', false);
    }

    public function test_daftar_peserta_bisa_disaring_notifikasi_gagal(): void
    {
        $failed = $this->makeRegistration(['name' => 'Peserta Gagal']);
        NotificationLog::create(['registration_id' => $failed->id, 'channel' => 'email', 'status' => 'failed']);

        $ok = $this->makeRegistration(['name' => 'Peserta Lancar']);
        NotificationLog::create(['registration_id' => $ok->id, 'channel' => 'email', 'status' => 'sent', 'sent_at' => now()]);

        $this->actingAs($this->admin)->get('/admin/registrations?notif=failed')
            ->assertOk()
            ->assertSee($failed->code)
            ->assertDontSee($ok->code);

        // Nilai selain "failed" diabaikan, bukan error.
        $this->actingAs($this->admin)->get('/admin/registrations?notif=xss')
            ->assertOk()->assertSee($ok->code);
    }

    // ---------- pembatalan ----------

    public function test_pembatalan_mengembalikan_kuota_dalam_transaksi(): void
    {
        $registration = $this->makeRegistration(['ticket_qty' => 3]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/registrations/'.$registration->id.'/cancel');

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $registration->refresh();
        $this->assertNotNull($registration->cancelled_at);
        $this->assertSame($this->admin->id, $registration->cancelled_by);
        $this->assertNull($registration->email_canonical);
        $this->assertSame(2, $this->event->fresh()->tickets_taken);
    }

    public function test_registrasi_yang_sudah_ditukar_tidak_bisa_dibatalkan(): void
    {
        $registration = $this->makeRegistration(['redeemed_at' => now(), 'redeemed_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/registrations/'.$registration->id.'/cancel');

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $registration->refresh();
        $this->assertNull($registration->cancelled_at);
        $this->assertSame(5, $this->event->fresh()->tickets_taken);
    }

    public function test_registrasi_yang_sudah_dibatalkan_tidak_bisa_dibatalkan_lagi(): void
    {
        $registration = $this->makeRegistration(['cancelled_at' => now(), 'email_canonical' => null]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/registrations/'.$registration->id.'/cancel');

        $response->assertSessionHas('error');
        $this->assertSame(5, $this->event->fresh()->tickets_taken);
    }

    // ---------- kirim ulang ----------

    public function test_kirim_ulang_reset_log_ke_pending_dan_dispatch_job(): void
    {
        Queue::fake();

        $registration = $this->makeRegistration();
        NotificationLog::create([
            'registration_id' => $registration->id,
            'channel' => 'email',
            'status' => 'sent',
            'attempts' => 1,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/registrations/'.$registration->id.'/resend');

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Queue::assertPushed(SendTicketNotification::class, 1);
        $this->assertSame(
            'pending',
            NotificationLog::where('registration_id', $registration->id)->where('channel', 'email')->value('status'),
        );
        $this->assertSame(1, NotificationLog::where('registration_id', $registration->id)->count());
    }
}

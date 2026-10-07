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
        NotificationLog::create([
            'registration_id' => $registration->id,
            'channel' => 'whatsapp',
            'status' => 'failed',
            'attempts' => 3,
            'last_error' => 'Koneksi gagal',
        ]);

        $this->actingAs($this->admin)->get('/admin/registrations/'.$registration->id)
            ->assertOk()
            ->assertSeeText('pending')
            ->assertSeeText('failed');
    }

    public function test_daftar_peserta_menampilkan_dan_menyaring_jalur_verifikasi(): void
    {
        $turnstile = $this->makeRegistration(['name' => 'Peserta Turnstile', 'verified_via' => 'turnstile']);
        $captcha = $this->makeRegistration(['name' => 'Peserta Captcha', 'verified_via' => 'captcha']);
        $none = $this->makeRegistration(['name' => 'Peserta Lama']);

        $this->actingAs($this->admin)->get('/admin/registrations')
            ->assertOk()
            ->assertSeeText('Verifikasi')
            ->assertSee('<option value="captcha"', false)
            ->assertSee($turnstile->code)->assertSee($captcha->code)->assertSee($none->code);

        $this->actingAs($this->admin)->get('/admin/registrations?via=captcha')
            ->assertOk()
            ->assertSee($captcha->code)
            ->assertDontSee($turnstile->code)
            ->assertDontSee($none->code)
            ->assertSee('<option value="captcha" selected', false);

        $this->actingAs($this->admin)->get('/admin/registrations?via=turnstile&q=Peserta')
            ->assertOk()
            ->assertSee($turnstile->code)
            ->assertDontSee($captcha->code);

        // Nilai tak dikenal diabaikan, bukan error.
        $this->actingAs($this->admin)->get('/admin/registrations?via=xss')
            ->assertOk()->assertSee($none->code);

        $this->actingAs($this->admin)->get('/admin/registrations/'.$captcha->id)
            ->assertOk()->assertSeeText('Captcha gambar');
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

        Queue::assertPushed(SendTicketNotification::class, 2);
        $this->assertSame(
            'pending',
            NotificationLog::where('registration_id', $registration->id)->where('channel', 'email')->value('status'),
        );
        $this->assertSame(2, NotificationLog::where('registration_id', $registration->id)->count());
    }
}

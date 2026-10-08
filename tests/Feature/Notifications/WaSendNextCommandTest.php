<?php

namespace Tests\Feature\Notifications;

use App\Contracts\TicketNotifier;
use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\WaThrottleState;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Command wa:send-next (docs/notifikasi.md "Outbox WhatsApp"). Tidak pernah
 * memanggil WAHA sungguhan — selalu Http::fake().
 */
class WaSendNextCommandTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Regency $regency;

    private static int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();

        config([
            'services.ticket_notifier' => 'mail',
            'services.ticket_wa_driver' => 'waha',
            'services.waha.url' => 'https://wa.test',
            'services.waha.api_key' => 'secret',
            'services.waha.session' => 'dwilaptop',
        ]);
        $this->app->forgetInstance(TicketNotifier::class);

        $this->event = Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'is_open' => true,
        ]);
        $this->regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);

        // 10.00 WIB: di dalam jam kirim default (08:00-20:00).
        Carbon::setTestNow(Carbon::parse('2026-06-01 10:00:00', 'Asia/Jakarta'));
    }

    private function fakeHealthySession(): void
    {
        Http::fake([
            'wa.test/api/sessions' => Http::response([['name' => 'dwilaptop', 'status' => 'WORKING']], 200),
            'wa.test/api/startTyping' => Http::response([], 201),
            'wa.test/api/sendText' => Http::response(['id' => 'wa-1'], 201),
            'wa.test/api/stopTyping' => Http::response([], 201),
        ]);
    }

    private function registrationWithPendingWa(array $overrides = []): Registration
    {
        self::$n++;
        $n = self::$n;

        $registration = Registration::forceCreate(array_merge([
            'event_id' => $this->event->id,
            'code' => sprintf('PJT26-%06d', $n),
            'token' => str_pad((string) $n, 48, 'x'),
            'name' => 'Peserta '.$n,
            'regency_id' => $this->regency->id,
            'email' => "peserta{$n}@gmail.com",
            'email_canonical' => "peserta{$n}@gmail.com",
            'phone' => '62812345'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'wa_consent' => true,
            'ticket_qty' => 1,
        ], $overrides));

        NotificationLog::create([
            'registration_id' => $registration->id,
            'channel' => 'whatsapp',
            'status' => 'pending',
            'attempts' => 0,
        ]);

        return $registration;
    }

    private function waLog(Registration $registration): NotificationLog
    {
        return NotificationLog::where('registration_id', $registration->id)->where('channel', 'whatsapp')->firstOrFail();
    }

    // -------------------------------------------------------------- tidak mengirim

    public function test_driver_log_tidak_mengirim(): void
    {
        $this->fakeHealthySession();
        config(['services.ticket_wa_driver' => 'log']);
        $registration = $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('pending', $this->waLog($registration)->status);
    }

    public function test_di_luar_jam_kirim_tidak_mengirim(): void
    {
        $this->fakeHealthySession();
        Carbon::setTestNow(Carbon::parse('2026-06-01 21:30:00', 'Asia/Jakarta'));
        $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_saat_pause_tidak_mengirim(): void
    {
        $this->fakeHealthySession();
        WaThrottleState::current()->pause('uji', 6);
        $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_batas_harian_tercapai_tidak_mengirim(): void
    {
        $this->fakeHealthySession();
        config(['services.wa.daily_cap' => 1]);
        $already = $this->registrationWithPendingWa();
        $this->waLog($already)->update(['status' => 'sent', 'sent_at' => now()]);

        $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_sebelum_next_allowed_at_tidak_mengirim(): void
    {
        $this->fakeHealthySession();
        WaThrottleState::current()->update(['next_allowed_at' => now()->addMinute()]);
        $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_wa_consent_false_tidak_pernah_diambil(): void
    {
        // Kasus tepi: baris whatsapp 'pending' pada registrasi yang sebenarnya
        // tidak menyetujui WA (seharusnya tidak pernah terjadi lewat alur
        // normal, yang mencatatnya 'skipped' — lihat RegistrationController).
        $this->fakeHealthySession();
        $registration = $this->registrationWithPendingWa(['wa_consent' => false]);

        $this->artisan('wa:send-next')->assertSuccessful();

        Http::assertNotSent(fn ($request) => $request->url() === 'https://wa.test/api/sendText');
        $this->assertSame('pending', $this->waLog($registration)->status);
    }

    // -------------------------------------------------------------- sukses

    public function test_sukses_menandai_sent_dan_mengatur_next_allowed_at(): void
    {
        $this->fakeHealthySession();
        $registration = $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        $log = $this->waLog($registration);
        $this->assertSame('sent', $log->status);
        $this->assertSame('wa-1', $log->provider_message_id);
        $this->assertNotNull($log->sent_at);

        $state = WaThrottleState::current();
        $this->assertNotNull($state->next_allowed_at);
        $this->assertTrue($state->next_allowed_at->greaterThanOrEqualTo(now()->addSeconds(45)));
        $this->assertTrue($state->next_allowed_at->lessThanOrEqualTo(now()->addSeconds(90)));
        $this->assertSame(0, $state->consecutive_failures);
    }

    public function test_dua_eksekusi_berturut_hanya_mengirim_satu(): void
    {
        $this->fakeHealthySession();
        $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();
        $this->artisan('wa:send-next')->assertSuccessful();

        Http::assertSentCount(4); // sessions + startTyping + sendText + stopTyping, hanya sekali
    }

    public function test_baris_sending_yang_menggantung_dikembalikan_lalu_dikirim(): void
    {
        $this->fakeHealthySession();
        $registration = $this->registrationWithPendingWa();
        $this->waLog($registration)->forceFill([
            'status' => 'sending',
            'updated_at' => now()->subMinutes(15),
        ])->save();

        $this->artisan('wa:send-next')->assertSuccessful();

        $this->assertSame('sent', $this->waLog($registration)->fresh()->status);
    }

    // -------------------------------------------------------------- circuit breaker

    public function test_sesi_bukan_working_memicu_pause_dan_tidak_mengirim(): void
    {
        Http::fake([
            'wa.test/api/sessions' => Http::response([['name' => 'dwilaptop', 'status' => 'STARTING']], 200),
        ]);
        $registration = $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        Http::assertNotSent(fn ($request) => $request->url() === 'https://wa.test/api/sendText');
        $this->assertTrue(WaThrottleState::current()->isPaused());
        $this->assertSame('pending', $this->waLog($registration)->status);
    }

    public function test_koneksi_gagal_memicu_pause_langsung(): void
    {
        Http::fake([
            'wa.test/api/sessions' => Http::response([['name' => 'dwilaptop', 'status' => 'WORKING']], 200),
            'wa.test/api/startTyping' => Http::response([], 201),
            'wa.test/api/sendText' => fn () => throw new ConnectionException('cURL error 28: timed out'),
        ]);
        $registration = $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        $log = $this->waLog($registration);
        $this->assertSame('pending', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertTrue(WaThrottleState::current()->isPaused());
    }

    public function test_body_memuat_463_memicu_pause_langsung(): void
    {
        Http::fake([
            'wa.test/api/sessions' => Http::response([['name' => 'dwilaptop', 'status' => 'WORKING']], 200),
            'wa.test/api/startTyping' => Http::response([], 201),
            'wa.test/api/sendText' => Http::response(['error' => 'blocked: 463'], 403),
        ]);
        $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        $this->assertTrue(WaThrottleState::current()->isPaused());
    }

    public function test_tiga_kali_5xx_beruntun_memicu_pause_dan_baris_menjadi_failed(): void
    {
        Http::fake([
            'wa.test/api/sessions' => Http::response([['name' => 'dwilaptop', 'status' => 'WORKING']], 200),
            'wa.test/api/startTyping' => Http::response([], 201),
            'wa.test/api/sendText' => Http::response(['error' => 'internal'], 500),
        ]);
        $registration = $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();
        $this->assertFalse(WaThrottleState::current()->isPaused());

        $this->artisan('wa:send-next')->assertSuccessful();
        $this->assertFalse(WaThrottleState::current()->isPaused());

        $this->artisan('wa:send-next')->assertSuccessful();

        $log = $this->waLog($registration);
        $this->assertSame('failed', $log->status);
        $this->assertSame(3, $log->attempts);
        $this->assertTrue(WaThrottleState::current()->isPaused());
    }

    public function test_5xx_menaikkan_consecutive_failures_tanpa_langsung_pause(): void
    {
        Http::fake([
            'wa.test/api/sessions' => Http::response([['name' => 'dwilaptop', 'status' => 'WORKING']], 200),
            'wa.test/api/startTyping' => Http::response([], 201),
            'wa.test/api/sendText' => Http::response(['error' => 'internal'], 500),
        ]);
        $registration = $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        $this->assertSame(1, WaThrottleState::current()->consecutive_failures);
        $this->assertFalse(WaThrottleState::current()->isPaused());
        $log = $this->waLog($registration);
        $this->assertSame('pending', $log->status);
        $this->assertSame(1, $log->attempts);
    }

    public function test_kegagalan_nomor_tidak_valid_tidak_memicu_breaker_dan_mereset_hitungan(): void
    {
        // Seolah-olah sudah ada kegagalan 5xx sebelumnya di eksekusi lain.
        WaThrottleState::current()->forceFill(['consecutive_failures' => 1])->save();

        Http::fake([
            'wa.test/api/sessions' => Http::response([['name' => 'dwilaptop', 'status' => 'WORKING']], 200),
            'wa.test/api/startTyping' => Http::response([], 201),
            'wa.test/api/sendText' => Http::response(['error' => 'nomor tidak terdaftar di WhatsApp'], 422),
        ]);
        $registration = $this->registrationWithPendingWa();

        $this->artisan('wa:send-next')->assertSuccessful();

        $this->assertSame(0, WaThrottleState::current()->consecutive_failures);
        $this->assertFalse(WaThrottleState::current()->isPaused());
        $this->assertSame('pending', $this->waLog($registration)->status);
        $this->assertSame(1, $this->waLog($registration)->attempts);
    }
}

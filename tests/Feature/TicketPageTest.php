<?php

namespace Tests\Feature;

use App\Jobs\SendTicketNotification;
use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Regency;
use App\Models\Registration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketPageTest extends TestCase
{
    use RefreshDatabase;

    private const NEUTRAL = 'Jika data terdaftar, link e-ticket sudah dikirim ulang ke email Anda.';

    private Event $event;

    private Regency $regency;

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
            'email' => 'budisantoso@gmail.com',
            'email_canonical' => 'budisantoso@gmail.com',
            'phone' => '6281234567890',
            'ticket_qty' => 4,
        ], $overrides));
    }

    // ---------- /tiket/{token} ----------

    public function test_token_tidak_valid_menghasilkan_404(): void
    {
        $this->get('/tiket/tidak-ada')->assertNotFound();
    }

    public function test_registrasi_yang_dibatalkan_menghasilkan_404(): void
    {
        $registration = $this->makeRegistration(['cancelled_at' => now()]);

        $this->get('/tiket/'.$registration->token)->assertNotFound();
    }

    public function test_halaman_tiket_memuat_kode_qr_dan_data_tersamar_bukan_data_utuh(): void
    {
        $registration = $this->makeRegistration();

        $response = $this->get('/tiket/'.$registration->token)->assertOk();

        $response->assertSee($registration->code);
        $response->assertSee('<svg', false);
        $response->assertSee('bu***@gmail.com');
        $response->assertSee('0812-****-7890');
        $response->assertDontSee('budisantoso@gmail.com');
        $response->assertDontSee('6281234567890');
        $response->assertDontSee('081234567890');
        // Token adalah isi QR; tidak boleh tercetak sebagai teks di halaman.
        $response->assertDontSee($registration->token);
    }

    public function test_status_belum_dan_sudah_ditukar(): void
    {
        $belum = $this->makeRegistration();
        $this->get('/tiket/'.$belum->token)
            ->assertSee('Belum ditukar')
            ->assertDontSee('Gelang sudah diterima');

        $sudah = $this->makeRegistration([
            'email_canonical' => 'lain@gmail.com',
            'redeemed_at' => Carbon::parse('2026-09-29 14:05:00', 'Asia/Jakarta'),
        ]);
        $this->get('/tiket/'.$sudah->token)
            ->assertSee('Sudah ditukar, 14.05')
            ->assertSee('Gelang sudah diterima');
    }

    public function test_zona_waktu_aplikasi_adalah_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_halaman_tiket_tidak_boleh_diindeks(): void
    {
        $registration = $this->makeRegistration();

        $response = $this->get('/tiket/'.$registration->token)->assertOk();

        $response->assertHeader('X-Robots-Tag', 'noindex');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertSee('<meta name="referrer" content="no-referrer">', false);
    }

    public function test_tanggal_dan_lokasi_disembunyikan_jika_kosong_tanpa_placeholder(): void
    {
        $registration = $this->makeRegistration();

        $response = $this->get('/tiket/'.$registration->token)->assertOk();

        $response->assertDontSee('[');
        $response->assertDontSee('TANGGAL');
        $response->assertDontSee('LOKASI');
        $response->assertDontSee('ticket-hero__when');
    }

    public function test_tanggal_dan_lokasi_tampil_jika_terisi(): void
    {
        $this->event->update([
            'event_starts_at' => Carbon::parse('2026-10-10 19:00:00', 'Asia/Jakarta'),
            'venue' => 'Stadion Jatidiri',
        ]);
        $registration = $this->makeRegistration();

        $this->get('/tiket/'.$registration->token)
            ->assertSee('10 Oktober 2026, 19.00')
            ->assertSee('Stadion Jatidiri');
    }

    // ---------- /cari-tiket ----------

    public function test_form_cari_tiket_tanpa_pesan_netral(): void
    {
        $this->get('/cari-tiket')->assertOk()->assertDontSee(self::NEUTRAL);
    }

    public function test_cari_tiket_terdaftar_dispatch_ulang_dan_reset_log(): void
    {
        Queue::fake();
        $registration = $this->makeRegistration();
        NotificationLog::create([
            'registration_id' => $registration->id, 'channel' => 'email',
            'status' => 'sent', 'attempts' => 1, 'sent_at' => now(),
        ]);

        $this->post('/cari-tiket', ['contact' => 'Budi.Santoso+x@Gmail.com'])
            ->assertOk()->assertSee(self::NEUTRAL);

        Queue::assertPushed(SendTicketNotification::class, 2);
        $this->assertSame('pending', NotificationLog::where('registration_id', $registration->id)->where('channel', 'email')->value('status'));
        $this->assertSame(2, NotificationLog::where('registration_id', $registration->id)->count());
    }

    public function test_respons_identik_untuk_terdaftar_dan_tidak_terdaftar(): void
    {
        Queue::fake();
        $this->makeRegistration();

        $terdaftar = $this->post('/cari-tiket', ['contact' => '081234567890']);
        $tidak = $this->post('/cari-tiket', ['contact' => '089999999999']);

        $strip = fn (string $html) => preg_replace('/name="_token" value="[^"]*"/', '', $html);

        $terdaftar->assertOk()->assertSee(self::NEUTRAL);
        $this->assertSame($strip($terdaftar->getContent()), $strip($tidak->getContent()));
        Queue::assertPushed(SendTicketNotification::class, 2); // hanya milik yang terdaftar
    }

    public function test_hp_dipakai_dua_registrasi_keduanya_dikirim_ulang(): void
    {
        Queue::fake();
        $this->makeRegistration(['email' => 'a@gmail.com', 'email_canonical' => 'a@gmail.com']);
        $this->makeRegistration(['email' => 'b@gmail.com', 'email_canonical' => 'b@gmail.com']);

        $this->post('/cari-tiket', ['contact' => '+62 812-3456-7890'])->assertSee(self::NEUTRAL);

        Queue::assertPushed(SendTicketNotification::class, 4);
    }

    public function test_permintaan_ke_4_untuk_kontak_sama_tidak_dispatch_tetapi_respons_identik(): void
    {
        Queue::fake();
        $this->makeRegistration();

        $responses = [];
        foreach (range(1, 4) as $i) {
            $responses[] = $this->post('/cari-tiket', ['contact' => 'budisantoso@gmail.com']);
        }

        $strip = fn (string $html) => preg_replace('/name="_token" value="[^"]*"/', '', $html);

        foreach ($responses as $r) {
            $r->assertOk()->assertSee(self::NEUTRAL);
        }
        $this->assertSame($strip($responses[0]->getContent()), $strip($responses[3]->getContent()));
        Queue::assertPushed(SendTicketNotification::class, 6); // 3 permintaan x 2 kanal
    }

    public function test_kontak_berbeda_dari_ip_sama_tidak_ikut_terblokir(): void
    {
        Queue::fake();
        $this->makeRegistration();
        $this->makeRegistration(['email' => 'lain@gmail.com', 'email_canonical' => 'lain@gmail.com', 'phone' => '6285555555555']);

        foreach (range(1, 4) as $i) {
            $this->post('/cari-tiket', ['contact' => 'budisantoso@gmail.com'])->assertOk();
        }
        Queue::assertPushed(SendTicketNotification::class, 6);

        $this->post('/cari-tiket', ['contact' => 'lain@gmail.com'])->assertOk()->assertSee(self::NEUTRAL);

        Queue::assertPushed(SendTicketNotification::class, 8);
    }

    public function test_registrasi_yang_dibatalkan_diperlakukan_seolah_tidak_terdaftar(): void
    {
        Queue::fake();
        $this->makeRegistration(['cancelled_at' => now(), 'email_canonical' => null]);

        $this->post('/cari-tiket', ['contact' => 'budisantoso@gmail.com'])
            ->assertOk()->assertSee(self::NEUTRAL);

        Queue::assertNotPushed(SendTicketNotification::class);
    }

    public function test_throttle_per_ip_120_per_menit_baru_menghasilkan_429(): void
    {
        Queue::fake();

        // Kontak berbeda-beda supaya batas per kontak (3/jam) tidak ikut kena.
        foreach (range(1, 120) as $i) {
            $this->post('/cari-tiket', ['contact' => sprintf('0812%08d', $i)])->assertOk()->assertSee(self::NEUTRAL);
        }

        $this->post('/cari-tiket', ['contact' => '081299999999'])->assertStatus(429);

        // IP lain tidak ikut terblokir.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post('/cari-tiket', ['contact' => '081299999999'])
            ->assertOk();

        // Setelah semenit, IP pertama bebas lagi.
        $this->travel(61)->seconds();
        $this->post('/cari-tiket', ['contact' => '081288888888'])->assertOk();
    }

    public function test_batas_per_kontak_tetap_tiga_per_jam_setelah_throttle_ip_dilonggarkan(): void
    {
        Queue::fake();
        $this->makeRegistration();

        foreach (range(1, 10) as $i) {
            $this->post('/cari-tiket', ['contact' => 'budisantoso@gmail.com'])->assertOk()->assertSee(self::NEUTRAL);
        }

        Queue::assertPushed(SendTicketNotification::class, 6); // tetap 3 permintaan x 2 kanal

        $this->travel(61)->minutes();
        $this->post('/cari-tiket', ['contact' => 'budisantoso@gmail.com'])->assertOk();
        Queue::assertPushed(SendTicketNotification::class, 8);
    }
}

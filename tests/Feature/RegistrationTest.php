<?php

namespace Tests\Feature;

use App\Actions\RegisterAttendee;
use App\Jobs\SendTicketNotification;
use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use App\Services\ImageCaptcha;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PDOException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Regency $regency;

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $turnstileLogs = [];

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

        Log::listen(function ($event): void {
            $this->turnstileLogs[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context];
        });

        // Form dianggap sudah dirender semenit lalu, supaya pengecekan waktu
        // isi minimum (3 detik) tidak menolak POST langsung di tes.
        $this->withSession(['daftar_form_rendered_at' => now()->subMinute()->getTimestamp()]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'regency_id' => $this->regency->id,
            'email_local' => 'budi',
            'email_domain' => 'gmail.com',
            'email_domain_other' => '',
            'phone' => '081234567890',
            'ticket_qty' => 2,
        ], $overrides);
    }

    /**
     * Penyebab kegagalan Turnstile hanya dibedakan di log (pesan ke pengguna
     * sama). Token widget dan secret tidak boleh ikut tertulis.
     *
     * @param  list<string>|null  $errorCodes
     */
    private function assertTurnstileLogged(string $reason, ?array $errorCodes = null): void
    {
        $entries = collect($this->turnstileLogs)->where('level', 'warning')
            ->filter(fn (array $entry) => ($entry['context']['reason'] ?? null) === $reason);

        $this->assertCount(1, $entries);

        if ($errorCodes !== null) {
            $this->assertSame($errorCodes, $entries->first()['context']['error_codes']);
        }

        $written = json_encode($this->turnstileLogs);
        $this->assertStringNotContainsString('token-widget', $written);
        $this->assertStringNotContainsString('rahasia', $written);
    }

    private function setRemainingQuota(int $remaining): void
    {
        $this->event->update(['tickets_taken' => $this->event->quota - $remaining]);
    }

    // ---------------------------------------------------------------- kuota

    public function test_qty_melebihi_sisa_ditolak_dengan_pesan_kuota(): void
    {
        $this->setRemainingQuota(2);

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 3]));

        $response->assertRedirect('/daftar');
        $response->assertSessionHasErrors([
            'ticket_qty' => 'Sisa kuota tinggal 2 tiket. Silakan kurangi jumlah tiket.',
        ]);

        // tickets_taken tidak berubah
        $this->assertSame(3598, $this->event->fresh()->tickets_taken);
        $this->assertSame(0, Registration::count());

        // input lama kembali
        $response->assertSessionHasInput('name', 'Budi Santoso');
        $response->assertSessionHasInput('email_local', 'budi');
        $response->assertSessionHasInput('phone', '081234567890');
        $response->assertSessionHasInput('ticket_qty', 3);
    }

    public function test_halaman_form_menampilkan_pesan_kuota_setelah_submit_ditolak(): void
    {
        $this->setRemainingQuota(2);

        $response = $this->followingRedirects()->post('/daftar', $this->payload(['ticket_qty' => 3]));

        $response->assertOk();
        // Banner: pesan aturan 3 apa adanya + satu kalimat tambahan.
        $response->assertSeeText('Sisa kuota tinggal 2 tiket. Silakan kurangi jumlah tiket. Data lain tidak perlu diisi ulang.');

        // Kalimat tambahan itu hanya milik banner, bukan pesan error ticket_qty.
        $this->post('/daftar', $this->payload(['ticket_qty' => 3]))
            ->assertSessionHasErrors([
                'ticket_qty' => 'Sisa kuota tinggal 2 tiket. Silakan kurangi jumlah tiket.',
            ]);
    }

    public function test_sisa_nol_menolak_pendaftaran_dengan_pesan_kuota_penuh(): void
    {
        $this->setRemainingQuota(0);

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 1]));

        $response->assertSessionHasErrors([
            'ticket_qty' => 'Mohon maaf, kuota pendaftaran sudah penuh.',
        ]);
        $this->assertSame(0, Registration::count());
    }

    public function test_sisa_nol_menampilkan_halaman_status_kuota_penuh(): void
    {
        $this->setRemainingQuota(0);

        $this->get('/')->assertOk()->assertSeeText('Kuota pendaftaran sudah penuh');
        $this->get('/daftar')->assertOk()->assertSeeText('Kuota pendaftaran sudah penuh');
    }

    public function test_html_form_tidak_pernah_memuat_angka_sisa_kuota(): void
    {
        $this->setRemainingQuota(3600);

        $response = $this->get('/daftar');

        $response->assertOk();
        $response->assertDontSee('3600');
        $response->assertDontSee('3.600');
    }

    // ------------------------------------------------------- validasi input

    public function test_ticket_qty_desimal_ditolak_bukan_dibulatkan(): void
    {
        foreach (['1.5', '2.0', '1e1', '0x2'] as $qty) {
            $this->post('/daftar', $this->payload(['ticket_qty' => $qty]))
                ->assertSessionHasErrors('ticket_qty');
        }

        $this->assertSame(0, Registration::count());
        $this->assertSame(0, $this->event->fresh()->tickets_taken);
    }

    public function test_ticket_qty_bilangan_bulat_dalam_bentuk_string_tetap_diterima(): void
    {
        Queue::fake();

        $this->post('/daftar', $this->payload(['ticket_qty' => '3']))->assertSessionHasNoErrors();

        $this->assertSame(3, Registration::firstOrFail()->ticket_qty);
    }

    public function test_karakter_kontrol_dibuang_dari_nama(): void
    {
        Queue::fake();

        $this->post('/daftar', $this->payload([
            'name' => "  Budi\u{0000}\u{200B} San\ttoso\r\n\u{202E} ",
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Budi Santoso', Registration::firstOrFail()->name);
    }

    public function test_nama_yang_hanya_berisi_karakter_kontrol_ditolak(): void
    {
        $this->post('/daftar', $this->payload(['name' => "\u{200B}\t\n"]))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Registration::count());
    }

    // ------------------------------------------------------ halaman sukses

    public function test_halaman_sukses_noindex_dan_no_referrer(): void
    {
        Queue::fake();
        $this->post('/daftar', $this->payload());
        $registration = Registration::firstOrFail();

        $response = $this->get('/daftar/sukses/'.$registration->token)->assertOk();

        $response->assertHeader('X-Robots-Tag', 'noindex');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertSee('<meta name="referrer" content="no-referrer">', false);
    }

    public function test_halaman_sukses_registrasi_dibatalkan_menghasilkan_404(): void
    {
        Queue::fake();
        $this->post('/daftar', $this->payload());
        $registration = Registration::firstOrFail();
        $registration->forceFill(['cancelled_at' => now(), 'email_canonical' => null])->save();

        $this->get('/daftar/sukses/'.$registration->token)->assertNotFound();
    }

    // ------------------------------------------------------- mass assignment

    public function test_kolom_sensitif_registrasi_tidak_bisa_diisi_lewat_mass_assignment(): void
    {
        $registration = new Registration([
            'code' => 'PJT26-AAAAAA',
            'token' => 'x',
            'redeemed_at' => now(),
            'redeemed_by' => 1,
            'cancelled_at' => now(),
            'cancelled_by' => 1,
            'verified_via' => 'captcha',
            'name' => 'Budi',
        ]);

        $this->assertSame(['name' => 'Budi'], $registration->getAttributes());
    }

    // -------------------------------------------------------------- beranda

    public function test_beranda_menampilkan_hero_dan_cara_mendaftar(): void
    {
        $this->event->update([
            'event_starts_at' => '2026-07-18 19:00:00',
            'venue' => 'Stadion Jatidiri, Semarang',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeText($this->event->name);
        $response->assertSeeText('Satu langkah menuju semangat Jawa Tengah!');
        $response->assertSeeText('18 Juli 2026, 19.00');
        $response->assertSeeText('Stadion Jatidiri, Semarang');
        $response->assertSeeText('Cara mendaftar');
        $response->assertDontSee('3600');
    }

    // --------------------------------------------------------------- status

    public function test_event_tertutup_menampilkan_halaman_status(): void
    {
        $this->event->update(['is_open' => false]);

        $this->get('/')->assertOk()->assertSeeText('Pendaftaran belum dibuka');
        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran belum dibuka');

        $this->event->update([
            'is_open' => false,
            'registration_close_at' => now()->subDay(),
        ]);

        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran sudah ditutup');
    }

    public function test_penutupan_manual_di_tengah_masa_pendaftaran_menampilkan_status_ditutup(): void
    {
        // Pendaftaran sempat dibuka, lalu admin mematikan saklar is_open
        // sebelum registration_close_at tiba.
        $this->event->update([
            'is_open' => false,
            'registration_open_at' => now()->subDays(3),
            'registration_close_at' => now()->addDays(3),
        ]);

        $this->get('/')->assertOk()->assertSeeText('Pendaftaran sudah ditutup');
        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran sudah ditutup');

        // Tanpa registration_close_at pun hasilnya sama.
        $this->event->update(['registration_close_at' => null]);

        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran sudah ditutup');

        // Sebaliknya, jadwal buka yang belum tiba tetap "belum dibuka".
        $this->event->update(['registration_open_at' => now()->addDay()]);

        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran belum dibuka');
    }

    // ------------------------------------------------------------ turnstile

    public function test_turnstile_tidak_terjangkau_menjadi_error_validasi_bukan_500(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'rahasia']);

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds'));

        $response = $this->post('/daftar', $this->payload(['cf-turnstile-response' => 'token-widget']));

        $response->assertRedirect('/daftar');
        $response->assertSessionHasErrors([
            'cf-turnstile-response' => 'Verifikasi keamanan gagal, silakan coba lagi.',
        ]);
        $response->assertSessionHasInput('name', 'Budi Santoso');

        $this->assertSame(0, Registration::count());
        $this->assertSame(0, $this->event->fresh()->tickets_taken);
        $this->assertTurnstileLogged('connection');
    }

    public function test_token_turnstile_ditolak_memakai_pesan_yang_sama_dengan_koneksi_gagal(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'rahasia']);

        Http::fake(['challenges.cloudflare.com/*' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ])]);

        $response = $this->post('/daftar', $this->payload(['cf-turnstile-response' => 'token-widget']));

        $response->assertRedirect('/daftar');
        $response->assertSessionHasErrors([
            'cf-turnstile-response' => 'Verifikasi keamanan gagal, silakan coba lagi.',
        ]);
        $response->assertSessionHasInput('name', 'Budi Santoso');

        $this->assertSame(0, Registration::count());
        $this->assertTurnstileLogged('rejected', ['invalid-input-response']);
    }

    public function test_turnstile_sukses_meneruskan_pendaftaran(): void
    {
        Queue::fake();
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'rahasia']);

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post('/daftar', $this->payload(['cf-turnstile-response' => 'token-widget']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Registration::count());
        $this->assertSame('turnstile', Registration::firstOrFail()->verified_via);
    }

    public function test_token_dan_captcha_kosong_ditolak_dengan_pesan_belum_selesai(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'rahasia']);
        Http::fake();

        $this->post('/daftar', $this->payload())
            ->assertRedirect('/daftar')
            ->assertSessionHasErrors(['cf-turnstile-response' => 'Verifikasi keamanan belum selesai. Coba lagi.'])
            ->assertSessionHasInput('name', 'Budi Santoso');

        Http::assertNothingSent();
        $this->assertSame(0, Registration::count());
    }

    public function test_turnstile_dimatikan_verified_via_kosong(): void
    {
        Queue::fake();

        $this->post('/daftar', $this->payload())->assertSessionHasNoErrors();

        $this->assertNull(Registration::firstOrFail()->verified_via);
    }

    public function test_verified_via_tidak_bisa_diisi_dari_request(): void
    {
        Queue::fake();

        $this->post('/daftar', $this->payload(['verified_via' => 'captcha']))->assertSessionHasNoErrors();

        $this->assertNull(Registration::firstOrFail()->verified_via);
    }

    public function test_tombol_daftar_nonaktif_sampai_ada_token_saat_turnstile_aktif(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.site_key' => 'site-key-uji', 'services.turnstile.secret_key' => 'rahasia']);

        $response = $this->get('/daftar')->assertOk();

        $response->assertSee('data-callback="porprovTurnstileOk"', false);
        $response->assertSee('data-expired-callback="porprovTurnstileExpired"', false);
        $response->assertSee('data-error-callback="porprovTurnstileError"', false);
        $response->assertSee('<button type="submit" disabled', false);
        $response->assertSeeText('Menunggu verifikasi keamanan…');
        // Panel captcha cadangan ada, tersembunyi sampai Turnstile gagal.
        $response->assertSee('data-captcha-panel hidden', false);
        $response->assertSeeText('Verifikasi keamanan gagal di perangkat ini.');
        $response->assertSee('js/verification.js?v=', false);
    }

    public function test_tombol_daftar_langsung_aktif_saat_turnstile_dimatikan(): void
    {
        $response = $this->get('/daftar')->assertOk();

        $response->assertDontSee('<button type="submit" disabled', false);
        $response->assertDontSeeText('Menunggu verifikasi keamanan…');
        $response->assertDontSee('cf-turnstile', false);
        $response->assertDontSee('data-captcha-panel', false);
    }

    public function test_panel_captcha_langsung_tampil_setelah_captcha_salah(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.site_key' => 'site-key-uji', 'services.turnstile.secret_key' => 'rahasia']);

        $code = app(ImageCaptcha::class)->issue();
        $wrong = $code === 'AAAAA' ? 'BBBBB' : 'AAAAA';

        $response = $this->followingRedirects()->post('/daftar', $this->payload(['captcha' => $wrong]));

        $response->assertOk();
        $response->assertSee('data-captcha-panel>', false);
        $response->assertSeeText('Kode tidak sesuai. Ketik ulang kode pada gambar baru.');
        // Isian tetap ada.
        $response->assertSee('value="Budi Santoso"', false);
    }

    // -------------------------------------------- pengaman produksi turnstile

    public function test_produksi_tanpa_kunci_turnstile_menampilkan_halaman_perbaikan(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        foreach ([['', 'rahasia'], ['site-key', ''], [null, null]] as [$site, $secret]) {
            config(['services.turnstile.enabled' => true, 'services.turnstile.site_key' => $site, 'services.turnstile.secret_key' => $secret]);

            $response = $this->get('/daftar');

            $response->assertStatus(503);
            $response->assertSeeText('Pendaftaran sedang dalam perbaikan');
            $response->assertDontSee('name="email_local"', false);
        }

        $errors = collect($this->turnstileLogs)->where('level', 'error');
        $this->assertCount(1, $errors, 'Log error dibatasi sekali per 10 menit.');
        $this->assertStringContainsString('TURNSTILE_SITE_KEY', $errors->first()['message']);
    }

    public function test_produksi_dengan_kunci_lengkap_menampilkan_form(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['services.turnstile.enabled' => true, 'services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'rahasia']);

        $this->get('/daftar')->assertOk()->assertSee('name="email_local"', false);
    }

    public function test_produksi_dengan_turnstile_dimatikan_menampilkan_form(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['services.turnstile.enabled' => false, 'services.turnstile.site_key' => '', 'services.turnstile.secret_key' => '']);

        $this->get('/daftar')->assertOk()->assertSee('name="email_local"', false);
    }

    // ------------------------------------------- honeypot & waktu isi minimum

    public function test_honeypot_terisi_ditolak_dan_isian_tetap_ada(): void
    {
        $response = $this->post('/daftar', $this->payload(['website' => 'http://spam.example']));

        $response->assertRedirect('/daftar');
        $response->assertSessionHasErrors(['form' => 'Pendaftaran tidak dapat diproses. Silakan coba lagi.']);
        $response->assertSessionHasInput('name', 'Budi Santoso');
        $this->assertSame(0, Registration::count());
    }

    public function test_honeypot_berlaku_juga_saat_turnstile_aktif_dan_token_tidak_diverifikasi(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'rahasia']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post('/daftar', $this->payload(['website' => 'x', 'cf-turnstile-response' => 'token-widget']))
            ->assertSessionHasErrors('form');

        Http::assertNothingSent();
        $this->assertSame(0, Registration::count());
    }

    public function test_submit_kurang_dari_tiga_detik_setelah_form_dirender_ditolak(): void
    {
        Queue::fake();
        $this->withSession(['daftar_form_rendered_at' => now()->getTimestamp()]);

        $this->travel(2)->seconds();

        $this->post('/daftar', $this->payload())
            ->assertRedirect('/daftar')
            ->assertSessionHasErrors(['form' => 'Mohon tunggu sebentar, lalu tekan Daftar lagi.'])
            ->assertSessionHasInput('email_local', 'budi');
        $this->assertSame(0, Registration::count());

        $this->travel(1)->seconds();

        $this->post('/daftar', $this->payload())->assertSessionHasNoErrors();
        $this->assertSame(1, Registration::count());
    }

    public function test_submit_tanpa_pernah_membuka_form_ditolak(): void
    {
        session()->forget('daftar_form_rendered_at');

        $this->post('/daftar', $this->payload())
            ->assertSessionHasErrors(['form' => 'Mohon tunggu sebentar, lalu tekan Daftar lagi.']);
        $this->assertSame(0, Registration::count());
    }

    public function test_waktu_render_form_diukur_server_dan_tidak_direset_saat_dibuka_ulang(): void
    {
        session()->forget('daftar_form_rendered_at');

        $this->get('/daftar')->assertOk();
        $first = session('daftar_form_rendered_at');
        $this->assertSame(now()->getTimestamp(), $first);

        $this->travel(10)->seconds();
        $this->get('/daftar')->assertOk();

        $this->assertSame($first, session('daftar_form_rendered_at'));
    }

    // -------------------------------------------------------------- sukses

    public function test_pendaftaran_sukses_menambah_tickets_taken_dan_mengantre_notifikasi(): void
    {
        Queue::fake();

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 4]));

        $registration = Registration::firstOrFail();

        $response->assertRedirect('/daftar/sukses/'.$registration->token);

        $this->assertSame(4, $this->event->fresh()->tickets_taken);
        $this->assertSame(4, $registration->ticket_qty);
        $this->assertMatchesRegularExpression('/^PJT26-[0-9A-HJKMNP-TV-Z]{6}$/', $registration->code);
        $this->assertSame(48, strlen($registration->token));
        $this->assertSame('budi@gmail.com', $registration->email);
        $this->assertSame('budi@gmail.com', $registration->email_canonical);
        $this->assertSame('6281234567890', $registration->phone);

        $logs = $registration->notificationLogs()->orderBy('channel')->get();
        $this->assertSame(['email', 'whatsapp'], $logs->pluck('channel')->all());
        $this->assertSame(['pending', 'pending'], $logs->pluck('status')->all());

        Queue::assertPushed(SendTicketNotification::class, 2);
        foreach (['email', 'whatsapp'] as $channel) {
            Queue::assertPushed(
                SendTicketNotification::class,
                fn (SendTicketNotification $job) => $job->registrationId === $registration->id
                    && $job->channel === $channel,
            );
        }
    }

    public function test_halaman_sukses_menampilkan_kode_dan_jumlah_tiket(): void
    {
        $this->post('/daftar', $this->payload(['ticket_qty' => 3]));

        $registration = Registration::firstOrFail();

        $response = $this->get('/daftar/sukses/'.$registration->token);

        $response->assertOk();
        $response->assertSeeText('Pendaftaran berhasil');
        $response->assertSeeText($registration->code);
        $response->assertSeeText('3 tiket');
    }

    public function test_halaman_sukses_dengan_token_tidak_dikenal_menghasilkan_404(): void
    {
        $this->get('/daftar/sukses/'.str_repeat('x', 48))->assertNotFound();
    }

    // ------------------------------------------------------------- duplikat

    public function test_email_gmail_setara_kanonik_ditolak_pada_pendaftaran_kedua(): void
    {
        $this->post('/daftar', $this->payload())->assertRedirectContains('/daftar/sukses/');

        $response = $this->post('/daftar', $this->payload([
            'email_local' => 'b.u.di+lain',
            'email_domain' => 'lainnya',
            'email_domain_other' => 'googlemail.com',
            'phone' => '082111111111',
        ]));

        $response->assertSessionHasErrors('email_local');
        $this->assertSame(1, Registration::count());
        $this->assertSame(2, $this->event->fresh()->tickets_taken);
    }

    public function test_daftar_ulang_dengan_email_sama_diterima_setelah_registrasi_pertama_dibatalkan(): void
    {
        $this->post('/daftar', $this->payload())->assertRedirectContains('/daftar/sukses/');

        $pertama = Registration::firstOrFail();
        app(\App\Actions\CancelRegistration::class)->handle($pertama, \App\Models\User::factory()->admin()->create());

        $response = $this->post('/daftar', $this->payload(['name' => 'Budi Santoso Lagi']));

        $response->assertRedirectContains('/daftar/sukses/');
        $this->assertSame(2, Registration::count());
        $this->assertSame(2, $this->event->fresh()->tickets_taken);
    }

    public function test_nomor_hp_sama_dengan_email_berbeda_diterima(): void
    {
        $this->post('/daftar', $this->payload())->assertRedirectContains('/daftar/sukses/');

        $response = $this->post('/daftar', $this->payload([
            'name' => 'Siti Aminah',
            'email_local' => 'siti',
        ]));

        $response->assertRedirectContains('/daftar/sukses/');
        $this->assertSame(2, Registration::count());
        $this->assertSame(
            ['6281234567890', '6281234567890'],
            Registration::orderBy('id')->pluck('phone')->all(),
        );
        $this->assertSame(4, $this->event->fresh()->tickets_taken);
    }

    // ------------------------------------------------- kegagalan level DB

    public function test_bentrok_email_canonical_di_level_database_dibalas_ramah(): void
    {
        $duplicateInserted = false;

        // Balapan dua submit: baris dengan email_canonical sama masuk setelah
        // validasi unique lolos, tepat sebelum registrasi ini di-insert.
        Registration::creating(function () use (&$duplicateInserted): void {
            if ($duplicateInserted) {
                return;
            }

            $duplicateInserted = true;

            DB::table('registrations')->insert([
                'event_id' => $this->event->getKey(),
                'code' => 'PJT26-AAAAAA',
                'token' => str_repeat('a', 48),
                'name' => 'Budi Duluan',
                'regency_id' => $this->regency->getKey(),
                'email' => 'budi@gmail.com',
                'email_canonical' => 'budi@gmail.com',
                'phone' => '6289999999999',
                'ticket_qty' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 2]));

        $this->assertTrue($duplicateInserted, 'Listener penyisip duplikat tidak pernah jalan.');

        $response->assertRedirect('/daftar');
        $response->assertSessionHasErrors([
            'email_local' => 'Email ini sudah terdaftar. Gunakan menu Cari tiket saya untuk menerima ulang e-ticket.',
        ]);
        $response->assertSessionHasInput('name', 'Budi Santoso');

        // Transaksi ikut rollback: kuota dan tabel registrasi tidak berubah.
        $this->assertSame(0, $this->event->fresh()->tickets_taken);
        $this->assertSame(0, Registration::count());
    }

    public function test_lock_wait_timeout_menampilkan_pesan_server_sibuk(): void
    {
        $pdoException = new PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded');
        $pdoException->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'];

        $this->mock(RegisterAttendee::class)
            ->shouldReceive('handle')
            ->andThrow(new QueryException(
                'mysql',
                'update `events` set `tickets_taken` = `tickets_taken` + ? where `id` = ?',
                [2, $this->event->getKey()],
                $pdoException,
            ));

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 2]));

        $response->assertRedirect('/daftar');
        $response->assertSessionHasErrors([
            'form' => 'Server sedang sibuk karena banyak pendaftar. Silakan tekan Daftar sekali lagi.',
        ]);
        $response->assertSessionHasInput('name', 'Budi Santoso');
        $response->assertSessionHasInput('email_local', 'budi');
        $response->assertSessionHasInput('phone', '081234567890');
        $response->assertSessionHasInput('ticket_qty', 2);

        $this->assertSame(0, $this->event->fresh()->tickets_taken);
        $this->assertSame(0, Registration::count());

        // Banner tampil di halaman form.
        $this->followingRedirects()
            ->post('/daftar', $this->payload(['ticket_qty' => 2]))
            ->assertOk()
            ->assertSeeText('Server sedang sibuk karena banyak pendaftar. Silakan tekan Daftar sekali lagi.');
    }

    public function test_query_exception_lain_tidak_ditangkap_dan_menjadi_500(): void
    {
        $pdoException = new PDOException('SQLSTATE[42000]: Syntax error or access violation: 1064');
        $pdoException->errorInfo = ['42000', 1064, 'You have an error in your SQL syntax'];

        $this->mock(RegisterAttendee::class)
            ->shouldReceive('handle')
            ->andThrow(new QueryException('mysql', 'select * from `events`', [], $pdoException));

        $this->post('/daftar', $this->payload(['ticket_qty' => 2]))->assertStatus(500);
    }

    // ------------------------------------------------------------ validasi

    public function test_ticket_qty_nol_dan_lima_ditolak(): void
    {
        foreach ([0, 5] as $qty) {
            $response = $this->post('/daftar', $this->payload(['ticket_qty' => $qty]));

            $response->assertSessionHasErrors('ticket_qty');
        }

        $this->assertSame(0, Registration::count());
        $this->assertSame(0, $this->event->fresh()->tickets_taken);
    }

    public function test_email_local_tidak_boleh_berisi_at_atau_spasi(): void
    {
        $this->post('/daftar', $this->payload(['email_local' => 'budi@lain']))
            ->assertSessionHasErrors('email_local');

        $this->post('/daftar', $this->payload(['email_local' => 'budi santoso']))
            ->assertSessionHasErrors('email_local');

        $this->assertSame(0, Registration::count());
    }

    public function test_domain_lainnya_yang_ternyata_ada_di_daftar_diperlakukan_sama(): void
    {
        $this->post('/daftar', $this->payload([
            'email_local' => 'Budi.Santoso+1',
            'email_domain' => 'lainnya',
            'email_domain_other' => ' Gmail.com ',
        ]))->assertRedirectContains('/daftar/sukses/');

        $registration = Registration::firstOrFail();

        $this->assertSame('budi.santoso+1@gmail.com', $registration->email);
        $this->assertSame('budisantoso@gmail.com', $registration->email_canonical);

        // Pendaftaran kedua lewat dropdown gmail.com ditolak sebagai duplikat.
        $this->post('/daftar', $this->payload([
            'email_local' => 'budisantoso',
            'email_domain' => 'gmail.com',
        ]))->assertSessionHasErrors('email_local');

        $this->assertSame(1, Registration::count());
    }

    public function test_domain_lainnya_dari_daftar_ditampilkan_ulang_sebagai_pilihan_dropdown(): void
    {
        $response = $this->followingRedirects()->post('/daftar', $this->payload([
            'name' => '',
            'email_domain' => 'lainnya',
            'email_domain_other' => 'Yahoo.CO.ID',
        ]));

        $response->assertOk();
        // Dropdown memilih yahoo.co.id, kotak "Lainnya…" kembali kosong.
        $response->assertSee('<option value="yahoo.co.id" selected>', false);
        $response->assertSee('<option value="lainnya" >', false);
        $response->assertDontSee('Yahoo.CO.ID', false);
    }

    public function test_domain_lainnya_wajib_diisi_dan_berbentuk_domain(): void
    {
        $this->post('/daftar', $this->payload(['email_domain' => 'lainnya', 'email_domain_other' => '']))
            ->assertSessionHasErrors('email_domain_other');

        $this->post('/daftar', $this->payload(['email_domain' => 'lainnya', 'email_domain_other' => 'kantor']))
            ->assertSessionHasErrors('email_domain_other');

        $this->assertSame(0, Registration::count());
    }

    public function test_field_wajib_lainnya_divalidasi(): void
    {
        $response = $this->post('/daftar', [
            'ticket_qty' => 1,
        ]);

        $response->assertSessionHasErrors(['name', 'regency_id', 'email_local', 'phone']);
        $this->assertSame(0, Registration::count());
    }
}

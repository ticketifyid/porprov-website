<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use App\Services\ImageCaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Captcha gambar: jalur cadangan saat Turnstile gagal di perangkat peserta.
 */
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    private Regency $regency;

    /** @var list<array{level: string, message: string}> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 0,
            'is_open' => true,
        ]);

        $this->regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);

        config(['services.turnstile.enabled' => true, 'services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'rahasia']);

        Log::listen(function ($event): void {
            $this->logs[] = ['level' => $event->level, 'message' => $event->message];
        });

        Queue::fake();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->useSession(Str::random(40));
    }

    /**
     * Pindah ke sesi lain (cookie sesi berbeda, isi sesi kosong) dari IP yang
     * sama. Form dianggap sudah dirender semenit lalu.
     */
    private function useSession(string $id): void
    {
        $this->app['session.store']->flush();
        $this->withCookie(config('session.cookie'), $id);
        $this->withSession(['daftar_form_rendered_at' => now()->subMinute()->getTimestamp()]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        static $n = 0;
        $n++;

        return array_merge([
            'name' => 'Budi Santoso',
            'regency_id' => $this->regency->id,
            'email_local' => "budi{$n}",
            'email_domain' => 'gmail.com',
            'email_domain_other' => '',
            'phone' => '081234567890',
            'ticket_qty' => 1,
        ], $overrides);
    }

    private function wrongCode(string $code): string
    {
        return $code === 'AAAAA' ? 'BBBBB' : 'AAAAA';
    }

    // ------------------------------------------------------------- gambar

    public function test_gambar_captcha_berupa_png_tanpa_cache_dan_jawaban_di_sesi(): void
    {
        $response = $this->get('/daftar/captcha');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith("\x89PNG", $response->getContent());

        $stored = session('daftar_captcha');
        $this->assertIsArray($stored);
        $this->assertArrayHasKey('hash', $stored);
        $this->assertEqualsWithDelta(now()->addMinutes(5)->getTimestamp(), $stored['expires_at'], 2);
    }

    public function test_kode_captcha_lima_karakter_tanpa_karakter_mirip(): void
    {
        $captcha = app(ImageCaptcha::class);

        for ($i = 0; $i < 300; $i++) {
            $code = $captcha->issue();

            $this->assertMatchesRegularExpression('/^[A-Z2-9]{5}$/', $code);
            $this->assertDoesNotMatchRegularExpression('/[0O1IL]/', $code);
        }
    }

    public function test_gambar_dirender_dengan_font_ttf_tanpa_warning(): void
    {
        $png = app(ImageCaptcha::class)->renderPng('ABCDE');

        $image = imagecreatefromstring($png);
        $this->assertNotFalse($image);
        $this->assertSame(ImageCaptcha::WIDTH, imagesx($image));
        $this->assertSame(ImageCaptcha::HEIGHT, imagesy($image));
        $this->assertEmpty(collect($this->logs)->where('level', 'warning'));
        $this->assertFileExists(resource_path('fonts/DejaVuSans-Bold.ttf'));
        $this->assertFileExists(resource_path('fonts/DejaVu-LICENSE.txt'));
    }

    public function test_tanpa_freetype_jatuh_ke_imagestring_dan_mencatat_warning(): void
    {
        $captcha = new class extends ImageCaptcha
        {
            protected function freeTypeAvailable(): bool
            {
                return false;
            }
        };

        $image = imagecreatefromstring($captcha->renderPng('ABCDE'));

        $this->assertNotFalse($image);
        $this->assertSame(ImageCaptcha::WIDTH, imagesx($image));
        $warnings = collect($this->logs)->where('level', 'warning');
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('FreeType', $warnings->first()['message']);
    }

    // ---------------------------------------------------- jalur pendaftaran

    public function test_captcha_benar_tanpa_token_diterima_dan_dicatat_sebagai_captcha(): void
    {
        $code = app(ImageCaptcha::class)->issue();

        $this->post('/daftar', $this->payload(['captcha' => strtolower($code)]))
            ->assertSessionHasNoErrors();

        $this->assertSame('captcha', Registration::firstOrFail()->verified_via);
        Http::assertNothingSent();
    }

    public function test_token_turnstile_didahulukan_meski_captcha_terisi(): void
    {
        app(ImageCaptcha::class)->issue();

        $this->post('/daftar', $this->payload(['cf-turnstile-response' => 'token', 'captcha' => 'AAAAA']))
            ->assertSessionHasNoErrors();

        $this->assertSame('turnstile', Registration::firstOrFail()->verified_via);
        Http::assertSentCount(1);
    }

    public function test_captcha_salah_ditolak_isian_tetap_dan_kode_hangus(): void
    {
        $code = app(ImageCaptcha::class)->issue();

        $this->post('/daftar', $this->payload(['captcha' => $this->wrongCode($code)]))
            ->assertRedirect('/daftar')
            ->assertSessionHasErrors(['captcha' => 'Kode tidak sesuai. Ketik ulang kode pada gambar baru.'])
            ->assertSessionHasInput('name', 'Budi Santoso')
            ->assertSessionHasInput('phone', '081234567890');

        // Sekali pakai: kode yang benar pun tidak berlaku lagi.
        $this->post('/daftar', $this->payload(['captcha' => $code]))
            ->assertSessionHasErrors(['captcha' => 'Kode sudah kedaluwarsa. Tekan Ganti gambar, lalu ketik kode yang baru.']);

        $this->assertSame(0, Registration::count());
    }

    public function test_captcha_benar_tidak_bisa_dipakai_dua_kali(): void
    {
        $code = app(ImageCaptcha::class)->issue();

        $this->post('/daftar', $this->payload(['captcha' => $code]))->assertSessionHasNoErrors();
        $this->post('/daftar', $this->payload(['captcha' => $code]))->assertSessionHasErrors('captcha');

        $this->assertSame(1, Registration::count());
    }

    public function test_captcha_kedaluwarsa_setelah_lima_menit(): void
    {
        $code = app(ImageCaptcha::class)->issue();

        $this->travel(301)->seconds();

        $this->post('/daftar', $this->payload(['captcha' => $code]))
            ->assertSessionHasErrors(['captcha' => 'Kode sudah kedaluwarsa. Tekan Ganti gambar, lalu ketik kode yang baru.']);

        $this->assertSame(0, Registration::count());
    }

    // ------------------------------------------------ throttle percobaan

    public function test_percobaan_captcha_dibatasi_lima_per_sepuluh_menit_per_sesi(): void
    {
        $captcha = app(ImageCaptcha::class);

        for ($i = 0; $i < 5; $i++) {
            $code = $captcha->issue();
            $this->post('/daftar', $this->payload(['captcha' => $this->wrongCode($code)]))->assertSessionHasErrors('captcha');
        }

        $code = $captcha->issue();
        $this->post('/daftar', $this->payload(['captcha' => $code]))
            ->assertSessionHasErrors(['captcha' => 'Terlalu banyak percobaan kode. Tunggu 10 menit, atau tekan Ulangi verifikasi.'])
            ->assertSessionHasInput('name', 'Budi Santoso');
        $this->assertSame(0, Registration::count());

        // Sesi lain dari IP yang sama tidak ikut terblokir.
        $this->useSession(Str::random(40));
        $code = $captcha->issue();
        $this->post('/daftar', $this->payload(['captcha' => $code]))->assertSessionHasNoErrors();
        $this->assertSame(1, Registration::count());

        // Sesi pertama bebas lagi setelah 10 menit.
        $this->travel(601)->seconds();
        $this->useSession(Str::random(40));
        $code = $captcha->issue();
        $this->post('/daftar', $this->payload(['captcha' => $code]))->assertSessionHasNoErrors();
    }

    public function test_percobaan_captcha_dibatasi_tiga_puluh_per_sepuluh_menit_per_ip(): void
    {
        $captcha = app(ImageCaptcha::class);

        // 6 sesi x 5 percobaan = 30 percobaan dari satu IP. Jeda 61 detik per
        // sesi supaya throttle rute POST /daftar (20/menit/IP) tidak ikut kena.
        for ($s = 0; $s < 6; $s++) {
            $this->useSession(Str::random(40));

            for ($i = 0; $i < 5; $i++) {
                $code = $captcha->issue();
                $this->post('/daftar', $this->payload(['captcha' => $this->wrongCode($code)]))
                    ->assertSessionHasErrors(['captcha' => 'Kode tidak sesuai. Ketik ulang kode pada gambar baru.']);
            }

            $this->travel(61)->seconds();
        }

        $this->useSession(Str::random(40));
        $code = $captcha->issue();
        $this->post('/daftar', $this->payload(['captcha' => $code]))
            ->assertSessionHasErrors(['captcha' => 'Terlalu banyak percobaan kode. Tunggu 10 menit, atau tekan Ulangi verifikasi.']);

        // IP lain tidak terpengaruh.
        $this->useSession(Str::random(40));
        $code = $captcha->issue();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post('/daftar', $this->payload(['captcha' => $code]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Registration::count());
    }

    // --------------------------------------------- throttle rute gambar

    public function test_rute_gambar_dibatasi_dua_puluh_per_menit_per_sesi(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get('/daftar/captcha')->assertOk();
        }

        $this->get('/daftar/captcha')->assertStatus(429);

        // Sesi lain dari IP yang sama tetap dilayani.
        $this->useSession(Str::random(40));
        $this->get('/daftar/captcha')->assertOk();
    }

    public function test_rute_gambar_dibatasi_seratus_dua_puluh_per_menit_per_ip(): void
    {
        for ($s = 0; $s < 6; $s++) {
            $this->useSession(Str::random(40));

            for ($i = 0; $i < 20; $i++) {
                $this->get('/daftar/captcha')->assertOk();
            }
        }

        $this->useSession(Str::random(40));
        $this->get('/daftar/captcha')->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get('/daftar/captcha')->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

/**
 * AppServiceProvider::boot() menolak melayani request web bila
 * APP_ENV=production dan APP_DEBUG=true (review keamanan Fase 9).
 * Environment lain, termasuk local lewat Cloudflare Tunnel, tidak terblokir.
 */
class ProductionDebugGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        // boot() di env production juga menyalakan larangan command destruktif
        // yang statis lintas test class (lihat DestructiveCommandsTest).
        DB::prohibitDestructiveCommands(false);

        parent::tearDown();
    }

    public function test_production_dengan_debug_menolak_berjalan(): void
    {
        $this->simulateWebRequest();
        $this->app['env'] = 'production';
        config(['app.debug' => true]);

        try {
            (new AppServiceProvider($this->app))->boot();
            $this->fail('Seharusnya melempar RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('APP_DEBUG=true tidak boleh dipakai saat APP_ENV=production', $e->getMessage());
        }

        // Debug dimatikan dulu supaya exception di atas dirender sebagai 500
        // biasa, bukan halaman debug yang membocorkan detail.
        $this->assertFalse(config('app.debug'));
    }

    public function test_production_tanpa_debug_berjalan_normal(): void
    {
        $this->simulateWebRequest();
        $this->app['env'] = 'production';
        config(['app.debug' => false]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(config('app.debug'));
    }

    public function test_local_dengan_debug_tidak_terblokir_termasuk_lewat_cloudflare_tunnel(): void
    {
        $this->simulateWebRequest();
        $this->app['env'] = 'local';
        config(['app.debug' => true, 'app.url' => 'https://contoh-acak.trycloudflare.com']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(config('app.debug'));
    }

    public function test_artisan_di_production_tetap_jalan_supaya_config_clear_bisa_dipakai(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(config('app.debug'));
    }

    private function simulateWebRequest(): void
    {
        (new ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
    }
}

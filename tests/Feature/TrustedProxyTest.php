<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * bootstrap/app.php hanya mempercayai proxy saat APP_ENV=local (kebutuhan uji
 * kamera lewat Cloudflare Tunnel). Tes ini menjaga sisi sebaliknya: di
 * environment lain header X-Forwarded-* TIDAK boleh dipercaya, karena
 * X-Forwarded-For yang dipercaya membuat throttle per IP bisa ditembus.
 */
class TrustedProxyTest extends TestCase
{
    public function test_header_x_forwarded_diabaikan_di_environment_selain_local(): void
    {
        $this->assertNotSame('local', $this->app->environment(), 'Tes ini harus berjalan di APP_ENV=testing.');

        Route::get('/_uji-proxy', fn (Request $request) => response()->json([
            'secure' => $request->secure(),
            'ip' => $request->ip(),
            'host' => $request->getHost(),
        ]));

        $response = $this->get('/_uji-proxy', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.9',
            'X-Forwarded-Host' => 'palsu.example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('secure', false);
        $response->assertJsonPath('host', 'localhost');
        $this->assertNotSame('203.0.113.9', $response->json('ip'));
    }
}

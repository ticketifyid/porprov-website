<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => EnsureRole::class,
        ]);

        // Cloudflare Tunnel (uji kamera scanner dari HP) menerima HTTPS di sisi
        // luar lalu meneruskan ke Laravel sebagai http:// + X-Forwarded-Proto.
        // Tanpa mempercayai proxy, URL aset ikut http:// dan browser menolak
        // membuka kamera di halaman campuran.
        //
        // HANYA untuk APP_ENV=local. Mempercayai semua proxy di environment
        // lain berarti X-Forwarded-For apa pun dipercaya, sehingga throttle
        // per IP (POST /daftar, POST /cari-tiket) bisa ditembus dengan
        // memalsukan header.
        if (env('APP_ENV') === 'local') {
            $middleware->trustProxies(at: '*');
        }

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin() ? '/admin' : '/scanner');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

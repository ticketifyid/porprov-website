<?php

namespace App\Providers;

use App\Contracts\TicketNotifier;
use App\Services\Notifications\LogTicketNotifier;
use App\Services\Notifications\MailTicketNotifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Implementasi pengiriman e-ticket dipilih lewat TICKET_NOTIFIER
        // (docs/arsitektur.md Fase 2, docs/notifikasi.md). Driver yang tidak
        // dikenal sengaja melempar exception, bukan diam-diam jatuh ke log:
        // salah ketik di produksi harus langsung terlihat, bukan berujung
        // peserta tidak menerima apa-apa sementara statusnya 'sent'.
        $this->app->singleton(TicketNotifier::class, function (): TicketNotifier {
            $driver = (string) config('services.ticket_notifier');

            return match ($driver) {
                'log' => new LogTicketNotifier,
                'mail' => $this->app->make(MailTicketNotifier::class),
                default => throw new InvalidArgumentException(
                    "Implementasi TicketNotifier '{$driver}' tidak dikenal. Lihat docs/notifikasi.md.",
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->refuseDebugInProduction();

        // @versionedAsset('css/app.css') -> /css/app.css?v={filemtime}
        // Cache busting aset milik proyek tanpa build (lihat App\Support\AssetVersion).
        Blade::directive(
            'versionedAsset',
            fn (string $expression): string => "<?php echo e(\App\Support\AssetVersion::url({$expression})); ?>",
        );

        // Cegah migrate:fresh/db:wipe/migrate:rollback dkk berjalan tanpa
        // sengaja di produksi (docs/struktur.md Fase 8 — insiden migrate:fresh
        // yang salah sasaran terhadap DB lokal). Command-command itu akan
        // menolak jalan dan melempar exception kecuali dipaksa dengan --force.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // POST /daftar: per IP longgar (CGNAT: satu IP operator seluler
        // dipakai banyak peserta), per sesi ketat. Saat batas terlampaui,
        // kembali ke form dengan error dan isian tetap, bukan halaman 429.
        RateLimiter::for('daftar-submit', function (Request $request): array {
            $backToForm = fn (Request $request): RedirectResponse => redirect()
                ->to(url()->previous(route('daftar')))
                ->withInput($request->except(['_token', 'captcha', 'cf-turnstile-response']))
                ->withErrors(['form' => 'Terlalu banyak percobaan pendaftaran dari perangkat atau jaringan Anda. Tunggu 1 menit, lalu tekan Daftar lagi.']);

            return [
                Limit::perMinute(10)->by('sesi:'.$request->session()->getId())->response($backToForm),
                Limit::perMinute(300)->by('ip:'.$request->ip())->response($backToForm),
            ];
        });

        // Gambar captcha: per sesi ketat, per IP longgar karena banyak
        // pengguna seluler berbagi IP (CGNAT).
        RateLimiter::for('daftar-captcha-image', fn (Request $request): array => [
            Limit::perMinute(20)->by('sesi:'.$request->session()->getId()),
            Limit::perMinute(120)->by('ip:'.$request->ip()),
        ]);

        // Kanal e-ticket yang benar-benar aktif, untuk teks halaman peserta.
        // Selama WhatsApp masih diteruskan ke LogTicketNotifier, cukup "email".
        View::composer('public.*', function ($view): void {
            $whatsapp = $this->app->make(TicketNotifier::class)->deliversWhatsApp();

            $view->with([
                'whatsappActive' => $whatsapp,
                'ticketChannels' => $whatsapp ? 'email dan WhatsApp' : 'email',
            ]);
        });
    }

    /**
     * APP_DEBUG=true di produksi menampilkan stack trace, query, dan isi
     * request ke pengunjung. Request web ditolak (500) dengan pesan jelas di
     * storage/logs. APP_ENV lain (termasuk local lewat Cloudflare Tunnel
     * *.trycloudflare.com) tidak disentuh.
     *
     * Artisan/cron sengaja tidak diblokir: kalau config:cache terlanjur berisi
     * debug=true, `php artisan config:clear` harus tetap bisa dipakai.
     */
    private function refuseDebugInProduction(): void
    {
        if (! $this->app->isProduction() || ! config('app.debug') || $this->app->runningInConsole()) {
            return;
        }

        // Matikan debug dulu supaya exception di bawah dirender sebagai
        // halaman 500 biasa, bukan halaman debug yang justru membocorkan detail.
        config(['app.debug' => false]);

        throw new RuntimeException(
            'Konfigurasi tidak aman: APP_DEBUG=true tidak boleh dipakai saat APP_ENV=production. '
            .'Ubah APP_DEBUG=false di .env, lalu jalankan `php artisan config:clear` '
            .'(atau hapus bootstrap/cache/config.php). Lihat docs/deploy.md.',
        );
    }
}

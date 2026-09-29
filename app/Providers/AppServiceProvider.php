<?php

namespace App\Providers;

use App\Contracts\TicketNotifier;
use App\Services\Notifications\LogTicketNotifier;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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
        // @versionedAsset('css/app.css') -> /css/app.css?v={filemtime}
        // Cache busting aset milik proyek tanpa build (lihat App\Support\AssetVersion).
        Blade::directive(
            'versionedAsset',
            fn (string $expression): string => "<?php echo e(\App\Support\AssetVersion::url({$expression})); ?>",
        );
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

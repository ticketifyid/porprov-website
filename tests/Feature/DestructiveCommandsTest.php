<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

/**
 * AppServiceProvider::boot() memanggil DB::prohibitDestructiveCommands()
 * berdasarkan $this->app->isProduction(), supaya migrate:fresh/db:wipe/dst
 * tidak bisa tidak sengaja dijalankan di produksi (docs/struktur.md Fase 8).
 */
class DestructiveCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Flag larangan (Illuminate\Console\Prohibitable) statis lintas test
        // class — selalu dilepas supaya tidak membocorkan larangan ke test lain.
        DB::prohibitDestructiveCommands(false);

        parent::tearDown();
    }

    public function test_command_destruktif_tidak_dilarang_di_environment_testing(): void
    {
        $this->assertFalse($this->isProhibited(FreshCommand::class));
    }

    public function test_boot_melarang_migrate_fresh_saat_environment_production(): void
    {
        $this->app['env'] = 'production';

        (new AppServiceProvider($this->app))->boot();

        $exitCode = Artisan::call('migrate:fresh');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'This command is prohibited from running in this environment.',
            Artisan::output(),
        );
    }

    public function test_boot_tidak_melarang_migrate_fresh_di_environment_local(): void
    {
        $this->app['env'] = 'local';

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse($this->isProhibited(FreshCommand::class));
    }

    private function isProhibited(string $commandClass): bool
    {
        $property = new ReflectionProperty($commandClass, 'prohibitedFromRunning');
        $property->setAccessible(true);

        return $property->getValue();
    }
}

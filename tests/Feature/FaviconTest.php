<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

    protected function setUp(): void
    {
        parent::setUp();

        $event = Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 0,
            'is_open' => true,
        ]);

        $regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);

        Registration::forceCreate([
            'event_id' => $event->id,
            'code' => 'PJT26-7K3M9Q',
            'token' => self::TOKEN,
            'name' => 'Budi Santoso',
            'regency_id' => $regency->id,
            'email' => 'budisantoso@gmail.com',
            'email_canonical' => 'budisantoso@gmail.com',
            'phone' => '6281234567890',
            'ticket_qty' => 2,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: string}> [url, peran: guest|admin|scanner]
     */
    public static function halaman(): array
    {
        return [
            'beranda' => ['/', 'guest'],
            'form' => ['/daftar', 'guest'],
            'cari tiket' => ['/cari-tiket', 'guest'],
            'sukses' => ['/daftar/sukses/'.self::TOKEN, 'guest'],
            'tiket' => ['/tiket/'.self::TOKEN, 'guest'],
            'login' => ['/login', 'guest'],
            'admin dashboard' => ['/admin', 'admin'],
            'admin event' => ['/admin/events', 'admin'],
            'admin peserta' => ['/admin/registrations', 'admin'],
            'admin akun' => ['/admin/users', 'admin'],
            'scanner' => ['/scanner', 'scanner'],
        ];
    }

    private function buka(string $url, string $peran): string
    {
        $user = match ($peran) {
            'admin' => User::factory()->admin()->create(),
            'scanner' => User::factory()->create(['role' => 'scanner']),
            default => null,
        };

        $request = $user ? $this->actingAs($user) : $this;

        return $request->get($url)->assertOk()->getContent();
    }

    #[DataProvider('halaman')]
    public function test_semua_halaman_memuat_ikon_ticketify_lewat_versioned_asset(string $url, string $peran): void
    {
        $html = $this->buka($url, $peran);

        $this->assertMatchesRegularExpression('#<link rel="icon" href="[^"]*/favicon\.ico\?v=\d+" sizes="any">#', $html);
        $this->assertMatchesRegularExpression('#<link rel="icon" type="image/png" sizes="32x32" href="[^"]*/favicon-32\.png\?v=\d+">#', $html);
        $this->assertMatchesRegularExpression('#<link rel="icon" type="image/png" sizes="16x16" href="[^"]*/favicon-16\.png\?v=\d+">#', $html);
        $this->assertMatchesRegularExpression('#<link rel="apple-touch-icon" href="[^"]*/apple-touch-icon\.png\?v=\d+">#', $html);
        $this->assertMatchesRegularExpression('#<link rel="manifest" href="[^"]*/site\.webmanifest\?v=\d+">#', $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#3F368F">', $html);
    }

    #[DataProvider('halaman')]
    public function test_favicon_lama_dihapus(string $url, string $peran): void
    {
        $html = $this->buka($url, $peran);

        $this->assertStringNotContainsString('shortcut icon', $html);
        $this->assertSame(1, substr_count($html, 'href="'.asset('favicon.ico').'?v='), 'favicon.ico hanya satu kali, bukan versi lama tanpa ?v=');
        $this->assertStringNotContainsString('href="'.asset('favicon.ico').'"', $html);
    }

    #[DataProvider('halaman')]
    public function test_title_hanya_ticketify(string $url, string $peran): void
    {
        $html = $this->buka($url, $peran);

        $this->assertStringContainsString('<title>Ticketify</title>', $html);
        $this->assertSame(1, substr_count($html, '<title>'));
    }

    public function test_title_diambil_dari_config_app_name(): void
    {
        config(['app.name' => 'Nama Lain']);

        $this->get('/')->assertSee('<title>Nama Lain</title>', false);
    }

    public function test_berkas_ikon_ada_di_public(): void
    {
        foreach (['favicon.ico', 'favicon-16.png', 'favicon-32.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'site.webmanifest'] as $berkas) {
            $this->assertFileExists(public_path($berkas));
        }
    }
}

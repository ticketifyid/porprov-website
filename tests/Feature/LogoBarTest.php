<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LogoBarTest extends TestCase
{
    use RefreshDatabase;

    private const LOGOS = [
        'logo-porprov' => 'Logo Porprov XVII Jawa Tengah',
        'logo-jateng' => 'Lambang Provinsi Jawa Tengah',
        'logo-koni-jateng' => 'Logo KONI Jawa Tengah',
        'logo-ngopeni-nglakoni' => 'Ngopeni Nglakoni Jateng',
    ];

    private const TAGLINE_BARU = 'Ngopeni Nglakoni Menuju Puncak Prestasi Jawa Tengah';

    private const TAGLINE_LAMA = 'Satu langkah menuju semangat Jawa Tengah!';

    private Event $event;

    private Registration $registration;

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

        $regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);

        $this->registration = Registration::forceCreate([
            'event_id' => $this->event->id,
            'code' => 'PJT26-7K3M9Q',
            'token' => str_pad('1', 48, 'x'),
            'name' => 'Budi Santoso',
            'regency_id' => $regency->id,
            'email' => 'budisantoso@gmail.com',
            'email_canonical' => 'budisantoso@gmail.com',
            'phone' => '6281234567890',
            'ticket_qty' => 2,
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function halamanPeserta(): array
    {
        return [
            'beranda' => ['/'],
            'form' => ['/daftar'],
            'sukses' => ['/daftar/sukses/'.str_pad('1', 48, 'x')],
            'tiket' => ['/tiket/'.str_pad('1', 48, 'x')],
            'cari tiket' => ['/cari-tiket'],
        ];
    }

    /**
     * Dijalankan untuk setiap halaman peserta.
     */
    #[DataProvider('halamanPeserta')]
    public function test_semua_halaman_peserta_memuat_keempat_logo_dengan_alt_yang_benar(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        foreach (self::LOGOS as $file => $alt) {
            $this->assertSame(1, substr_count($html, 'alt="'.$alt.'"'), "{$url}: alt \"{$alt}\" harus tepat satu kali");
            $this->assertMatchesRegularExpression(
                '#<source[^>]+srcset="[^"]*img/'.$file.'\.webp\?v=\d+"[^>]+type="image/webp"#',
                $html,
                "{$url}: {$file}.webp harus jadi sumber <picture>, dimuat lewat @versionedAsset"
            );
            $this->assertMatchesRegularExpression(
                '#<img[^>]+src="[^"]*img/'.$file.'\.png\?v=\d+"[^>]+width="\d+"[^>]+height="\d+"#',
                $html,
                "{$url}: fallback {$file}.png harus punya width dan height eksplisit"
            );
        }
    }

    #[DataProvider('halamanPeserta')]
    public function test_maskot_tepat_di_sebelah_kanan_logo_porprov(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'alt="Maskot Porprov XVII Jawa Tengah"'));
        $this->assertMatchesRegularExpression(
            '#<source[^>]+srcset="[^"]*img/maskot-porprov\.webp\?v=\d+"[^>]+type="image/webp"#',
            $html
        );
        $this->assertMatchesRegularExpression(
            '#<img[^>]+src="[^"]*img/maskot-porprov\.png\?v=\d+"[^>]+width="403"[^>]+height="900"#',
            $html
        );

        $porprov = strpos($html, 'alt="Logo Porprov XVII Jawa Tengah"');
        $maskot = strpos($html, 'alt="Maskot Porprov XVII Jawa Tengah"');
        $jateng = strpos($html, 'alt="Lambang Provinsi Jawa Tengah"');

        $this->assertLessThan($maskot, $porprov, 'maskot harus setelah logo Porprov');
        $this->assertLessThan($jateng, $maskot, 'maskot harus sebelum logo Jawa Tengah (satu kelompok dengan Porprov)');
    }

    public function test_halaman_status_juga_memuat_bar_logo(): void
    {
        $this->event->update(['is_open' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        foreach (self::LOGOS as $alt) {
            $this->assertSame(1, substr_count($html, 'alt="'.$alt.'"'));
        }
    }

    /**
     * Dijalankan untuk setiap halaman peserta.
     */
    #[DataProvider('halamanPeserta')]
    public function test_bar_logo_tepat_di_bawah_strip_dan_logo_lama_tidak_dirujuk(string $url): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertStringNotContainsString('Logo Porprov Jawa Tengah XVII 2026', $html);
        $this->assertSame(1, substr_count($html, 'img/logo-porprov.png'), 'logo Porprov hanya tampil di bar logo');
        $this->assertLessThan(
            strpos($html, 'class="logo-bar'),
            strpos($html, 'class="strip-porprov'),
            'bar logo harus setelah strip 4 warna'
        );
        $this->assertLessThan(
            strpos($html, '<h1'),
            strpos($html, 'class="logo-bar'),
            'bar logo harus sebelum isi halaman'
        );
    }

    public function test_beranda_memuat_tagline_baru_dan_tidak_lagi_tagline_lama(): void
    {
        $response = $this->get('/');

        $response->assertSeeText(self::TAGLINE_BARU);
        $response->assertDontSeeText(self::TAGLINE_LAMA);
    }

    public function test_tagline_lama_tidak_tersisa_di_view_dan_dokumen_desain(): void
    {
        $dirs = [
            resource_path('views'),
            base_path('docs/design'),
        ];

        foreach ($dirs as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    self::TAGLINE_LAMA,
                    file_get_contents($file->getPathname()),
                    $file->getPathname().' masih memuat tagline lama'
                );
            }
        }
    }

    public function test_file_logo_png_dan_webp_ada_di_public_img(): void
    {
        foreach ([...array_keys(self::LOGOS), 'maskot-porprov'] as $file) {
            $this->assertFileExists(public_path("img/{$file}.png"));
            $this->assertFileExists(public_path("img/{$file}.webp"));
        }
    }
}

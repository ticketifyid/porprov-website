<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Regency;
use App\Support\AssetVersion;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class AssetVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AssetVersion::flush();
    }

    public function test_url_menambahkan_filemtime_sebagai_query_string(): void
    {
        $expected = asset('css/app.css').'?v='.filemtime(public_path('css/app.css'));

        $this->assertSame($expected, AssetVersion::url('css/app.css'));
    }

    public function test_url_aset_yang_tidak_ada_dikembalikan_tanpa_query_string(): void
    {
        $this->assertSame(asset('css/tidak-ada.css'), AssetVersion::url('css/tidak-ada.css'));
    }

    public function test_directive_versioned_asset_memanggil_helper(): void
    {
        $compiled = Blade::compileString("@versionedAsset('js/stepper.js')");

        $this->assertStringContainsString('App\Support\AssetVersion::url(\'js/stepper.js\')', $compiled);
    }

    public function test_halaman_peserta_memuat_css_dan_js_bertanda_versi(): void
    {
        Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 0,
            'is_open' => true,
        ]);

        Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);

        $response = $this->get('/daftar');

        $response->assertOk();
        $response->assertSee('css/app.css?v='.filemtime(public_path('css/app.css')), false);
        $response->assertSee('js/stepper.js?v='.filemtime(public_path('js/stepper.js')), false);
        $response->assertSee('js/email-domain.js?v='.filemtime(public_path('js/email-domain.js')), false);
    }

    /**
     * Aturan 14 CLAUDE.md, dijaga untuk SEMUA view (termasuk layout Metronic
     * di halaman admin dan scanner).
     */
    public function test_semua_css_dan_js_proyek_memakai_versioned_asset(): void
    {
        $pelanggaran = [];

        foreach ($this->bladeFiles() as $path => $isi) {
            // asset('css/...') atau asset('js/...') tanpa cache busting.
            if (preg_match('/(?<![A-Za-z])asset\(\s*[\'"](css|js)\//', $isi)) {
                $pelanggaran[] = $path.': aset proyek dimuat dengan asset(), seharusnya @versionedAsset';
            }

            // Sebaliknya: metronic tidak perlu ?v= karena tidak pernah berubah.
            if (preg_match('/@versionedAsset\(\s*[\'"]metronic\//', $isi)) {
                $pelanggaran[] = $path.': aset metronic seharusnya memakai asset() biasa';
            }
        }

        $this->assertSame([], $pelanggaran, implode("\n", $pelanggaran));
    }

    /**
     * @return array<string, string>
     */
    private function bladeFiles(): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }

        $this->assertNotEmpty($files, 'Tidak ada file Blade yang terbaca.');

        return $files;
    }
}

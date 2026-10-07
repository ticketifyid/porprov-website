<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRulesTest extends TestCase
{
    use RefreshDatabase;

    private const LAKUKAN = [
        'Membawa kartu identitas',
        'Mengenakan tiket gelang',
        'Datang tepat waktu',
        'Pastikan berada di tribun yang benar',
        'Jaga kebersihan',
    ];

    private const JANGAN = [
        'Membawa kamera profesional dan tongsis',
        'Merokok atau rokok elektrik',
        'Membawa obat-obatan terlarang',
        'Membawa senjata tajam',
        'Membawa hewan peliharaan',
    ];

    private Event $event;

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
    }

    public function test_beranda_memuat_judul_kedua_kelompok_dan_semua_butir(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSeeText('Ketentuan penonton');
        $response->assertSeeText('Lakukan');
        $response->assertSeeText('Jangan');

        foreach ([...self::LAKUKAN, ...self::JANGAN] as $butir) {
            $response->assertSeeText($butir);
        }
    }

    public function test_ketentuan_ada_setelah_cara_mendaftar_dan_sebelum_footer(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertLessThan(strpos($html, 'Ketentuan penonton'), strpos($html, 'Cara mendaftar</h2>'));
        $this->assertLessThan(strpos($html, 'site-footer'), strpos($html, 'Ketentuan penonton'));
        $this->assertLessThan(strpos($html, 'Jangan</h3>'), strpos($html, 'Lakukan</h3>'));
    }

    public function test_jam_datang_tepat_waktu_mengikuti_event_starts_at(): void
    {
        $this->event->update(['event_starts_at' => '2026-07-18 19:30:00']);

        $this->get('/')->assertSeeText('Datang tepat waktu (pukul 19.30)');

        $this->event->update(['event_starts_at' => '2026-07-18 08:05:00']);

        $this->get('/')->assertSeeText('Datang tepat waktu (pukul 08.05)');
    }

    public function test_jam_disembunyikan_jika_event_starts_at_null(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Datang tepat waktu', $html);
        $this->assertStringNotContainsString('pukul', $html);
        $this->assertStringNotContainsString('Datang tepat waktu (', $html);
    }

    public function test_tautan_poster_dibuka_di_tab_baru_lewat_versioned_asset_dengan_fallback_jpg(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Lihat poster lengkap', $html);
        $this->assertMatchesRegularExpression(
            '#<a[^>]+href="[^"]*img/poster-dodont\.webp\?v=\d+"[^>]+target="_blank"[^>]+rel="noopener#',
            $html
        );
        $this->assertMatchesRegularExpression('#data-fallback="[^"]*img/poster-dodont\.jpg\?v=\d+"#', $html);
        $this->assertFileExists(public_path('img/poster-dodont.webp'));
        $this->assertFileExists(public_path('img/poster-dodont.jpg'));
    }

    public function test_ketentuan_hanya_ada_di_beranda(): void
    {
        $this->get('/daftar')->assertOk()->assertDontSeeText('Ketentuan penonton');
        $this->get('/cari-tiket')->assertOk()->assertDontSeeText('Ketentuan penonton');
    }

    public function test_beranda_tidak_memuat_angka_sisa_kuota(): void
    {
        $this->get('/')->assertDontSee('3600');
    }
}

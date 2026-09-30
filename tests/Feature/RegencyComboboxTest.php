<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Regency;
use Database\Seeders\RegencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Domisili: select asli tetap ada di HTML (sumber data + fallback tanpa JS)
 * dan diubah menjadi dropdown yang bisa dicari oleh regency-combobox.js.
 */
class RegencyComboboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::create([
            'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
            'code_prefix' => 'PJT26',
            'quota' => 3600,
            'tickets_taken' => 0,
            'is_open' => true,
        ]);

        $this->seed(RegencySeeder::class);

        $this->withSession(['daftar_form_rendered_at' => now()->subMinute()->getTimestamp()]);
    }

    /**
     * Isi <select name="regency_id"> dari HTML form.
     */
    private function regencySelect(string $html): string
    {
        $this->assertSame(1, preg_match('/<select id="regency_id" name="regency_id" data-combobox(?:="data-combobox")?>(.*?)<\/select>/s', $html, $m));

        return $m[1];
    }

    public function test_form_tetap_memuat_select_dengan_36_pilihan_dan_skrip_combobox(): void
    {
        $html = $this->get('/daftar')->assertOk()->getContent();
        $select = $this->regencySelect($html);

        preg_match_all('/<option value="(\d+)"/', $select, $options);
        $this->assertCount(36, $options[1]);
        $this->assertSame(Regency::query()->orderBy('sort_order')->pluck('id')->map(fn ($id) => (string) $id)->all(), $options[1]);

        foreach (Regency::pluck('name') as $name) {
            $this->assertStringContainsString('>'.e($name).'</option>', $select);
        }

        // Pilihan kosong untuk fallback tanpa JS, dan tidak ada yang terpilih.
        $this->assertStringContainsString('<option value="">Pilih kabupaten/kota</option>', $select);
        $this->assertStringNotContainsString(' selected', $select);

        // "Luar Jawa Tengah" ditandai agar selalu muncul di combobox.
        $this->assertSame(1, substr_count($select, 'data-combobox-always'));
        $luar = Regency::where('name', 'Luar Jawa Tengah')->value('id');
        $this->assertMatchesRegularExpression('/<option value="'.$luar.'"\s+data-combobox-always\s*>Luar Jawa Tengah<\/option>/', $select);

        $this->assertStringContainsString('js/regency-combobox.js?v=', $html);
    }

    public function test_pilihan_domisili_lama_tetap_terpilih_setelah_error_validasi(): void
    {
        $tegal = Regency::where('name', 'Kab. Tegal')->firstOrFail();

        $response = $this->followingRedirects()->post('/daftar', [
            'name' => '',
            'regency_id' => $tegal->id,
            'email_local' => 'budi',
            'email_domain' => 'gmail.com',
            'phone' => '081234567890',
            'ticket_qty' => 1,
        ]);

        $response->assertOk()->assertSeeText('Nama lengkap wajib diisi.');

        $select = $this->regencySelect($response->getContent());

        $this->assertMatchesRegularExpression('/<option value="'.$tegal->id.'" selected\b/', $select);
        $this->assertSame(1, substr_count($select, ' selected'));
    }
}

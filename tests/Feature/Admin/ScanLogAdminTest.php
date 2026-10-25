<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\ScanLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanLogAdminTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Regency $regency;

    private User $admin;

    private User $scanner;

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

        $this->regency = Regency::create(['name' => 'Kota Semarang', 'sort_order' => 1]);
        $this->admin = User::factory()->admin()->create();
        $this->scanner = User::factory()->create(['role' => 'scanner', 'name' => 'Petugas Satu', 'username' => 'petugas1']);
    }

    private function makeRegistration(string $name, string $code): Registration
    {
        static $n = 0;
        $n++;

        return Registration::forceCreate([
            'event_id' => $this->event->id,
            'code' => $code,
            'token' => str_pad("scanlog{$n}", 48, 'x'),
            'name' => $name,
            'regency_id' => $this->regency->id,
            'email' => "log{$n}@example.com",
            'email_canonical' => "log{$n}@example.com",
            'phone' => '6281234567890',
            'ticket_qty' => 2,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLog(?Registration $registration, array $overrides = []): ScanLog
    {
        return ScanLog::create(array_merge([
            'registration_id' => $registration?->id,
            'user_id' => $this->scanner->id,
            'scanned_value' => 'nilai-mentah-qr-rahasia',
            'method' => 'camera',
            'result' => 'success',
            'ip_address' => '127.0.0.1',
            'scanned_at' => now(),
        ], $overrides));
    }

    public function test_scanner_tidak_bisa_membuka_log_penukaran(): void
    {
        $this->actingAs($this->scanner)->get('/admin/scan-logs')->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/admin/scan-logs')->assertRedirect('/login');
    }

    public function test_admin_melihat_log_dengan_peserta_petugas_dan_hasil(): void
    {
        $registration = $this->makeRegistration('Budi Santoso', 'PJT26-AAAAAA');
        $this->makeLog($registration);

        $this->actingAs($this->admin)->get('/admin/scan-logs')
            ->assertOk()
            ->assertSee('PJT26-AAAAAA')
            ->assertSee('Budi Santoso')
            ->assertSee('Petugas Satu')
            ->assertSee('Berhasil');
    }

    public function test_log_qr_tidak_dikenal_menampilkan_isi_scan_di_bawah_judul(): void
    {
        $this->makeLog(null, ['result' => 'not_found', 'scanned_value' => 'qr-palsu-123']);

        $this->actingAs($this->admin)->get('/admin/scan-logs')
            ->assertOk()
            ->assertSeeInOrder(['QR tidak dikenal', 'qr-palsu-123']);
    }

    public function test_isi_scan_panjang_dipotong_dan_di_escape(): void
    {
        $this->makeLog(null, ['result' => 'not_found', 'scanned_value' => '<b>'.str_repeat('z', 100)]);

        $this->actingAs($this->admin)->get('/admin/scan-logs')
            ->assertDontSee('<b>', false)
            ->assertDontSee(str_repeat('z', 100));
    }

    public function test_isi_qr_tiket_valid_tidak_ditampilkan(): void
    {
        $registration = $this->makeRegistration('Budi Santoso', 'PJT26-AAAAAA');
        $this->makeLog($registration, ['scanned_value' => 'token-tiket-valid-rahasia']);

        $this->actingAs($this->admin)->get('/admin/scan-logs')
            ->assertSee('Budi Santoso')
            ->assertDontSee('token-tiket-valid-rahasia');
    }

    public function test_log_diurutkan_dari_yang_terbaru(): void
    {
        $lama = $this->makeRegistration('Peserta Lama', 'PJT26-LAMA11');
        $baru = $this->makeRegistration('Peserta Baru', 'PJT26-BARU11');
        $this->makeLog($lama, ['scanned_at' => now()->subHour()]);
        $this->makeLog($baru, ['scanned_at' => now()]);

        $this->actingAs($this->admin)->get('/admin/scan-logs')
            ->assertSeeInOrder(['Peserta Baru', 'Peserta Lama']);
    }

    public function test_log_bisa_disaring_hasil_dan_metode(): void
    {
        $a = $this->makeRegistration('Peserta Sukses', 'PJT26-SUKSES');
        $b = $this->makeRegistration('Peserta Ganda', 'PJT26-GANDAAA');
        $this->makeLog($a, ['result' => 'success', 'method' => 'camera']);
        $this->makeLog($b, ['result' => 'already_redeemed', 'method' => 'hardware']);

        $this->actingAs($this->admin)->get('/admin/scan-logs?result=already_redeemed')
            ->assertSee('Peserta Ganda')->assertDontSee('Peserta Sukses');

        $this->actingAs($this->admin)->get('/admin/scan-logs?method=camera')
            ->assertSee('Peserta Sukses')->assertDontSee('Peserta Ganda');
    }

    public function test_log_bisa_dicari_nama_kode_dan_petugas(): void
    {
        $a = $this->makeRegistration('Citra Lestari', 'PJT26-CITRA1');
        $b = $this->makeRegistration('Doni Pratama', 'PJT26-DONI11');
        $lain = User::factory()->create(['role' => 'scanner', 'name' => 'Petugas Dua', 'username' => 'petugas2']);
        $this->makeLog($a);
        $this->makeLog($b, ['user_id' => $lain->id]);

        $this->actingAs($this->admin)->get('/admin/scan-logs?q=Citra')
            ->assertSee('Citra Lestari')->assertDontSee('Doni Pratama');

        $this->actingAs($this->admin)->get('/admin/scan-logs?q=DONI11')
            ->assertSee('Doni Pratama')->assertDontSee('Citra Lestari');

        $this->actingAs($this->admin)->get('/admin/scan-logs?q=petugas2')
            ->assertSee('Doni Pratama')->assertDontSee('Citra Lestari');
    }

    public function test_filter_tidak_valid_diabaikan(): void
    {
        $this->makeLog($this->makeRegistration('Budi Santoso', 'PJT26-AAAAAA'));

        $this->actingAs($this->admin)->get('/admin/scan-logs?result=ngawur&method=ngawur')
            ->assertOk()->assertSee('Budi Santoso');
    }

    public function test_log_kosong_menampilkan_keadaan_kosong(): void
    {
        $this->actingAs($this->admin)->get('/admin/scan-logs')
            ->assertOk()->assertSee('Belum ada log penukaran');
    }

    public function test_menu_log_penukaran_ada_di_sidebar_admin(): void
    {
        $this->actingAs($this->admin)->get('/admin/scan-logs')
            ->assertSee(route('admin.scan-logs.index'), false);
    }

    public function test_log_dipaginasi_25_per_halaman(): void
    {
        $registration = $this->makeRegistration('Budi Santoso', 'PJT26-AAAAAA');
        for ($i = 0; $i < 26; $i++) {
            $this->makeLog($registration, ['scanned_at' => now()->subMinutes($i)]);
        }

        $this->actingAs($this->admin)->get('/admin/scan-logs')
            ->assertSee('class="pagination"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use App\Models\ScanLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Regency $regency;

    private User $officer;

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

        $this->officer = User::factory()->create(['name' => 'Pos 3 - Andi', 'role' => 'scanner']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRegistration(array $overrides = []): Registration
    {
        static $n = 0;
        $n++;

        return Registration::create(array_merge([
            'event_id' => $this->event->id,
            'code' => sprintf('PJT26-%06d', $n),
            'token' => str_pad((string) $n, 48, 'x'),
            'name' => 'Budi Santoso',
            'regency_id' => $this->regency->id,
            'email' => "budi{$n}@gmail.com",
            'email_canonical' => "budi{$n}@gmail.com",
            'phone' => '6281234567890',
            'ticket_qty' => 3,
        ], $overrides));
    }

    // ---------- halaman ----------

    public function test_halaman_scanner_memuat_aset_bertanda_versi_tanpa_sidebar(): void
    {
        $response = $this->actingAs($this->officer)->get('/scanner')->assertOk();

        $response->assertSee('css/scanner.css?v='.filemtime(public_path('css/scanner.css')), false);
        $response->assertSee('js/scanner.js?v='.filemtime(public_path('js/scanner.js')), false);
        $response->assertSee('js/scanner-camera.js?v='.filemtime(public_path('js/scanner-camera.js')), false);
        $response->assertSee('js/scanner-hardware.js?v='.filemtime(public_path('js/scanner-hardware.js')), false);
        $response->assertSee('js/vendor/html5-qrcode.min.js?v='.filemtime(public_path('js/vendor/html5-qrcode.min.js')), false);
        // Tampilan fokus: tanpa sidebar Metronic (catatan Fase 7 docs/struktur.md).
        $response->assertDontSee('kt_app_sidebar', false);
    }

    // ---------- mode kamera (dua langkah) ----------

    public function test_kamera_langkah_pertama_tidak_mengubah_status_dan_tidak_menulis_log_sukses(): void
    {
        $registration = $this->makeRegistration();

        $response = $this->actingAs($this->officer)
            ->postJson('/scan', ['value' => $registration->token, 'method' => 'camera'])
            ->assertOk();

        $response->assertJsonPath('result', 'pending_confirm');
        $response->assertJsonPath('registration.name', 'Budi Santoso');
        $response->assertJsonPath('registration.ticket_qty', 3);
        $response->assertJsonPath('registration.id', $registration->id);

        $this->assertNull($registration->fresh()->redeemed_at);
        $this->assertSame(0, ScanLog::count());
    }

    public function test_konfirmasi_kamera_menukar_dan_mencatat_method_camera(): void
    {
        $registration = $this->makeRegistration();

        $this->actingAs($this->officer)
            ->postJson('/scan', ['value' => $registration->token, 'method' => 'camera'])
            ->assertOk();

        $this->actingAs($this->officer)
            ->postJson("/scan/{$registration->id}/redeem", ['method' => 'camera', 'value' => $registration->token])
            ->assertOk()
            ->assertJsonPath('result', 'success')
            ->assertJsonPath('registration.ticket_qty', 3);

        $registration->refresh();
        $this->assertNotNull($registration->redeemed_at);
        $this->assertSame($this->officer->id, $registration->redeemed_by);

        $log = ScanLog::sole();
        $this->assertSame('camera', $log->method);
        $this->assertSame('success', $log->result);
        $this->assertSame($registration->id, $log->registration_id);
        $this->assertSame($this->officer->id, $log->user_id);
        $this->assertNotNull($log->scanned_at);
    }

    // ---------- mode alat scanner (sekali scan) ----------

    public function test_hardware_langsung_menukar_dan_mencatat_method_hardware(): void
    {
        $registration = $this->makeRegistration(['ticket_qty' => 4]);

        $this->actingAs($this->officer)
            ->postJson('/scan', ['value' => $registration->token, 'method' => 'hardware'])
            ->assertOk()
            ->assertJsonPath('result', 'success')
            ->assertJsonPath('registration.ticket_qty', 4)
            ->assertJsonPath('registration.code', $registration->code);

        $registration->refresh();
        $this->assertNotNull($registration->redeemed_at);
        $this->assertSame($this->officer->id, $registration->redeemed_by);

        $log = ScanLog::sole();
        $this->assertSame('hardware', $log->method);
        $this->assertSame('success', $log->result);
        $this->assertSame($registration->token, $log->scanned_value);
    }

    public function test_redeem_kedua_menghasilkan_already_redeemed_dan_redeemed_at_tidak_berubah(): void
    {
        $penukarPertama = User::factory()->create(['name' => 'Pos 1 - Sari', 'role' => 'scanner']);
        $waktu = Carbon::parse('2026-10-10 18:30:00', 'Asia/Jakarta');

        $registration = $this->makeRegistration([
            'redeemed_at' => $waktu,
            'redeemed_by' => $penukarPertama->id,
        ]);

        $this->actingAs($this->officer)
            ->postJson('/scan', ['value' => $registration->token, 'method' => 'hardware'])
            ->assertOk()
            ->assertJsonPath('result', 'already_redeemed')
            ->assertJsonPath('recent_self', false)
            ->assertJsonPath('registration.redeemed_at_label', '18.30')
            ->assertJsonPath('registration.redeemed_by_name', 'Pos 1 - Sari');

        $registration->refresh();
        $this->assertTrue($waktu->equalTo($registration->redeemed_at));
        $this->assertSame($penukarPertama->id, $registration->redeemed_by);

        $log = ScanLog::sole();
        $this->assertSame('already_redeemed', $log->result);
        $this->assertSame('hardware', $log->method);
    }

    public function test_scan_ulang_oleh_petugas_sama_kurang_dari_dua_menit_ditandai_recent_self(): void
    {
        $registration = $this->makeRegistration([
            'redeemed_at' => now()->subSeconds(30),
            'redeemed_by' => $this->officer->id,
        ]);

        $this->actingAs($this->officer)
            ->postJson('/scan', ['value' => $registration->token, 'method' => 'hardware'])
            ->assertOk()
            ->assertJsonPath('result', 'already_redeemed')
            ->assertJsonPath('recent_self', true);

        // Log tetap already_redeemed, bukan status khusus.
        $this->assertSame('already_redeemed', ScanLog::sole()->result);
    }

    public function test_scan_ulang_petugas_sama_lebih_dari_dua_menit_tidak_recent_self(): void
    {
        $registration = $this->makeRegistration([
            'redeemed_at' => now()->subMinutes(5),
            'redeemed_by' => $this->officer->id,
        ]);

        $this->actingAs($this->officer)
            ->postJson('/scan', ['value' => $registration->token, 'method' => 'hardware'])
            ->assertOk()
            ->assertJsonPath('recent_self', false);
    }

    public function test_scan_ulang_petugas_lain_kurang_dari_dua_menit_tidak_recent_self(): void
    {
        $lain = User::factory()->create(['name' => 'Pos 2 - Rini', 'role' => 'scanner']);

        $registration = $this->makeRegistration([
            'redeemed_at' => now()->subSeconds(10),
            'redeemed_by' => $lain->id,
        ]);

        $this->actingAs($this->officer)
            ->postJson('/scan', ['value' => $registration->token, 'method' => 'hardware'])
            ->assertOk()
            ->assertJsonPath('recent_self', false)
            ->assertJsonPath('registration.redeemed_by_name', 'Pos 2 - Rini');
    }

    // ---------- QR tidak dikenal ----------

    public function test_token_tidak_dikenal_dicatat_not_found_tanpa_registration_id(): void
    {
        $this->actingAs($this->officer)
            ->postJson('/scan', ['value' => 'token-ngawur', 'method' => 'camera'])
            ->assertOk()
            ->assertJsonPath('result', 'not_found');

        $log = ScanLog::sole();
        $this->assertNull($log->registration_id);
        $this->assertSame('not_found', $log->result);
        $this->assertSame('camera', $log->method);
        $this->assertSame('token-ngawur', $log->scanned_value);
    }

    public function test_method_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->officer)
            ->postJson('/scan', ['value' => 'apa-saja', 'method' => 'manual'])
            ->assertStatus(422);

        $this->assertSame(0, ScanLog::count());
    }

    // ---------- pencarian manual (dua langkah) ----------

    public function test_pencarian_kode_mentolerir_huruf_kecil_spasi_dan_o_i_l(): void
    {
        $registration = $this->makeRegistration(['code' => 'PJT26-70K3M9']);

        $this->actingAs($this->officer)
            ->postJson('/scan/cari', ['query' => ' pjt26 7ok3m9 '])
            ->assertOk()
            ->assertJsonPath('result', 'candidates')
            ->assertJsonPath('candidates.0.id', $registration->id)
            ->assertJsonPath('candidates.0.code', 'PJT26-70K3M9');

        $lain = $this->makeRegistration(['code' => 'PJT26-1K3M9Q']);

        $this->actingAs($this->officer)
            ->postJson('/scan/cari', ['query' => 'pjt26-lk3m9q'])
            ->assertOk()
            ->assertJsonPath('candidates.0.id', $lain->id);

        $this->assertSame(0, ScanLog::count());
    }

    public function test_pencarian_manual_dua_langkah_mencatat_method_manual(): void
    {
        $registration = $this->makeRegistration();

        $this->actingAs($this->officer)
            ->postJson('/scan/cari', ['query' => $registration->code])
            ->assertOk()
            ->assertJsonPath('candidates.0.is_redeemed', false);

        $this->actingAs($this->officer)
            ->postJson("/scan/{$registration->id}/redeem", ['method' => 'manual', 'value' => $registration->code])
            ->assertOk()
            ->assertJsonPath('result', 'success');

        $this->assertNotNull($registration->fresh()->redeemed_at);

        $log = ScanLog::sole();
        $this->assertSame('manual', $log->method);
        $this->assertSame('success', $log->result);
        $this->assertSame($registration->code, $log->scanned_value);
    }

    public function test_pencarian_nama_dan_hp_bisa_mengembalikan_lebih_dari_satu_kandidat(): void
    {
        $this->makeRegistration(['name' => 'Budi Santoso']);
        $this->makeRegistration(['name' => 'Budi Hartono']);

        $this->actingAs($this->officer)
            ->postJson('/scan/cari', ['query' => 'budi'])
            ->assertOk()
            ->assertJsonCount(2, 'candidates');

        $this->actingAs($this->officer)
            ->postJson('/scan/cari', ['query' => '081234567890'])
            ->assertOk()
            ->assertJsonCount(2, 'candidates');
    }

    public function test_pencarian_nama_kurang_dari_tiga_karakter_memberi_pesan_bukan_hasil(): void
    {
        $this->makeRegistration(['name' => 'Budi Santoso']);

        $this->actingAs($this->officer)
            ->postJson('/scan/cari', ['query' => 'bu'])
            ->assertOk()
            ->assertJsonPath('result', 'too_short')
            ->assertJsonPath('message', 'Ketik minimal 3 karakter nama.')
            ->assertJsonMissingPath('candidates');

        $this->assertSame(0, ScanLog::count());
    }

    public function test_pencarian_tanpa_hasil_dicatat_not_found_method_manual(): void
    {
        $this->actingAs($this->officer)
            ->postJson('/scan/cari', ['query' => 'tidak ada siapa pun'])
            ->assertOk()
            ->assertJsonPath('result', 'not_found');

        $log = ScanLog::sole();
        $this->assertSame('manual', $log->method);
        $this->assertSame('not_found', $log->result);
        $this->assertNull($log->registration_id);
    }

    // ---------- akses ----------

    public function test_petugas_nonaktif_ditolak_dan_tidak_menulis_log(): void
    {
        $nonaktif = User::factory()->inactive()->create(['role' => 'scanner']);
        $registration = $this->makeRegistration();

        $this->actingAs($nonaktif)
            ->postJson('/scan', ['value' => $registration->token, 'method' => 'hardware'])
            ->assertRedirect('/login');

        $this->assertNull($registration->fresh()->redeemed_at);
        $this->assertSame(0, ScanLog::count());
    }

    public function test_guest_tidak_bisa_scan(): void
    {
        $registration = $this->makeRegistration();

        $this->postJson('/scan', ['value' => $registration->token, 'method' => 'hardware'])
            ->assertUnauthorized();

        $this->assertNull($registration->fresh()->redeemed_at);
        $this->assertSame(0, ScanLog::count());
    }

    public function test_admin_juga_boleh_scan(): void
    {
        $admin = User::factory()->admin()->create();
        $registration = $this->makeRegistration();

        $this->actingAs($admin)
            ->postJson('/scan', ['value' => $registration->token, 'method' => 'hardware'])
            ->assertOk()
            ->assertJsonPath('result', 'success');

        $this->assertSame($admin->id, $registration->fresh()->redeemed_by);
    }
}

<?php

namespace Tests\Feature;

use App\Actions\RegisterAttendee;
use App\Jobs\SendTicketNotification;
use App\Models\Event;
use App\Models\Regency;
use App\Models\Registration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PDOException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Regency $regency;

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
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'regency_id' => $this->regency->id,
            'email_local' => 'budi',
            'email_domain' => 'gmail.com',
            'email_domain_other' => '',
            'phone' => '081234567890',
            'ticket_qty' => 2,
        ], $overrides);
    }

    private function setRemainingQuota(int $remaining): void
    {
        $this->event->update(['tickets_taken' => $this->event->quota - $remaining]);
    }

    // ---------------------------------------------------------------- kuota

    public function test_qty_melebihi_sisa_ditolak_dengan_pesan_kuota(): void
    {
        $this->setRemainingQuota(2);

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 3]));

        $response->assertRedirect('/daftar');
        $response->assertSessionHasErrors([
            'ticket_qty' => 'Sisa kuota tinggal 2 tiket. Silakan kurangi jumlah tiket.',
        ]);

        // tickets_taken tidak berubah
        $this->assertSame(3598, $this->event->fresh()->tickets_taken);
        $this->assertSame(0, Registration::count());

        // input lama kembali
        $response->assertSessionHasInput('name', 'Budi Santoso');
        $response->assertSessionHasInput('email_local', 'budi');
        $response->assertSessionHasInput('phone', '081234567890');
        $response->assertSessionHasInput('ticket_qty', 3);
    }

    public function test_halaman_form_menampilkan_pesan_kuota_setelah_submit_ditolak(): void
    {
        $this->setRemainingQuota(2);

        $response = $this->followingRedirects()->post('/daftar', $this->payload(['ticket_qty' => 3]));

        $response->assertOk();
        // Banner: pesan aturan 3 apa adanya + satu kalimat tambahan.
        $response->assertSeeText('Sisa kuota tinggal 2 tiket. Silakan kurangi jumlah tiket. Data lain tidak perlu diisi ulang.');

        // Kalimat tambahan itu hanya milik banner, bukan pesan error ticket_qty.
        $this->post('/daftar', $this->payload(['ticket_qty' => 3]))
            ->assertSessionHasErrors([
                'ticket_qty' => 'Sisa kuota tinggal 2 tiket. Silakan kurangi jumlah tiket.',
            ]);
    }

    public function test_sisa_nol_menolak_pendaftaran_dengan_pesan_kuota_penuh(): void
    {
        $this->setRemainingQuota(0);

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 1]));

        $response->assertSessionHasErrors([
            'ticket_qty' => 'Mohon maaf, kuota pendaftaran sudah penuh.',
        ]);
        $this->assertSame(0, Registration::count());
    }

    public function test_sisa_nol_menampilkan_halaman_status_kuota_penuh(): void
    {
        $this->setRemainingQuota(0);

        $this->get('/')->assertOk()->assertSeeText('Kuota pendaftaran sudah penuh');
        $this->get('/daftar')->assertOk()->assertSeeText('Kuota pendaftaran sudah penuh');
    }

    public function test_html_form_tidak_pernah_memuat_angka_sisa_kuota(): void
    {
        $this->setRemainingQuota(3600);

        $response = $this->get('/daftar');

        $response->assertOk();
        $response->assertDontSee('3600');
        $response->assertDontSee('3.600');
    }

    // -------------------------------------------------------------- beranda

    public function test_beranda_menampilkan_hero_dan_cara_mendaftar(): void
    {
        $this->event->update([
            'event_starts_at' => '2026-07-18 19:00:00',
            'venue' => 'Stadion Jatidiri, Semarang',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeText($this->event->name);
        $response->assertSeeText('Satu langkah menuju semangat Jawa Tengah!');
        $response->assertSeeText('18 Juli 2026, 19.00');
        $response->assertSeeText('Stadion Jatidiri, Semarang');
        $response->assertSeeText('Cara mendaftar');
        $response->assertDontSee('3600');
    }

    // --------------------------------------------------------------- status

    public function test_event_tertutup_menampilkan_halaman_status(): void
    {
        $this->event->update(['is_open' => false]);

        $this->get('/')->assertOk()->assertSeeText('Pendaftaran belum dibuka');
        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran belum dibuka');

        $this->event->update([
            'is_open' => false,
            'registration_close_at' => now()->subDay(),
        ]);

        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran sudah ditutup');
    }

    public function test_penutupan_manual_di_tengah_masa_pendaftaran_menampilkan_status_ditutup(): void
    {
        // Pendaftaran sempat dibuka, lalu admin mematikan saklar is_open
        // sebelum registration_close_at tiba.
        $this->event->update([
            'is_open' => false,
            'registration_open_at' => now()->subDays(3),
            'registration_close_at' => now()->addDays(3),
        ]);

        $this->get('/')->assertOk()->assertSeeText('Pendaftaran sudah ditutup');
        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran sudah ditutup');

        // Tanpa registration_close_at pun hasilnya sama.
        $this->event->update(['registration_close_at' => null]);

        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran sudah ditutup');

        // Sebaliknya, jadwal buka yang belum tiba tetap "belum dibuka".
        $this->event->update(['registration_open_at' => now()->addDay()]);

        $this->get('/daftar')->assertOk()->assertSeeText('Pendaftaran belum dibuka');
    }

    // -------------------------------------------------------------- sukses

    public function test_pendaftaran_sukses_menambah_tickets_taken_dan_mengantre_notifikasi(): void
    {
        Queue::fake();

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 4]));

        $registration = Registration::firstOrFail();

        $response->assertRedirect('/daftar/sukses/'.$registration->token);

        $this->assertSame(4, $this->event->fresh()->tickets_taken);
        $this->assertSame(4, $registration->ticket_qty);
        $this->assertMatchesRegularExpression('/^PJT26-[0-9A-HJKMNP-TV-Z]{6}$/', $registration->code);
        $this->assertSame(48, strlen($registration->token));
        $this->assertSame('budi@gmail.com', $registration->email);
        $this->assertSame('budi@gmail.com', $registration->email_canonical);
        $this->assertSame('6281234567890', $registration->phone);

        $logs = $registration->notificationLogs()->orderBy('channel')->get();
        $this->assertSame(['email', 'whatsapp'], $logs->pluck('channel')->all());
        $this->assertSame(['pending', 'pending'], $logs->pluck('status')->all());

        Queue::assertPushed(SendTicketNotification::class, 2);
        foreach (['email', 'whatsapp'] as $channel) {
            Queue::assertPushed(
                SendTicketNotification::class,
                fn (SendTicketNotification $job) => $job->registrationId === $registration->id
                    && $job->channel === $channel,
            );
        }
    }

    public function test_halaman_sukses_menampilkan_kode_dan_jumlah_tiket(): void
    {
        $this->post('/daftar', $this->payload(['ticket_qty' => 3]));

        $registration = Registration::firstOrFail();

        $response = $this->get('/daftar/sukses/'.$registration->token);

        $response->assertOk();
        $response->assertSeeText('Pendaftaran berhasil');
        $response->assertSeeText($registration->code);
        $response->assertSeeText('3 tiket');
    }

    public function test_halaman_sukses_dengan_token_tidak_dikenal_menghasilkan_404(): void
    {
        $this->get('/daftar/sukses/'.str_repeat('x', 48))->assertNotFound();
    }

    // ------------------------------------------------------------- duplikat

    public function test_email_gmail_setara_kanonik_ditolak_pada_pendaftaran_kedua(): void
    {
        $this->post('/daftar', $this->payload())->assertRedirectContains('/daftar/sukses/');

        $response = $this->post('/daftar', $this->payload([
            'email_local' => 'b.u.di+lain',
            'email_domain' => 'lainnya',
            'email_domain_other' => 'googlemail.com',
            'phone' => '082111111111',
        ]));

        $response->assertSessionHasErrors('email_local');
        $this->assertSame(1, Registration::count());
        $this->assertSame(2, $this->event->fresh()->tickets_taken);
    }

    public function test_daftar_ulang_dengan_email_sama_diterima_setelah_registrasi_pertama_dibatalkan(): void
    {
        $this->post('/daftar', $this->payload())->assertRedirectContains('/daftar/sukses/');

        $pertama = Registration::firstOrFail();
        app(\App\Actions\CancelRegistration::class)->handle($pertama, \App\Models\User::factory()->admin()->create());

        $response = $this->post('/daftar', $this->payload(['name' => 'Budi Santoso Lagi']));

        $response->assertRedirectContains('/daftar/sukses/');
        $this->assertSame(2, Registration::count());
        $this->assertSame(2, $this->event->fresh()->tickets_taken);
    }

    public function test_nomor_hp_sama_dengan_email_berbeda_diterima(): void
    {
        $this->post('/daftar', $this->payload())->assertRedirectContains('/daftar/sukses/');

        $response = $this->post('/daftar', $this->payload([
            'name' => 'Siti Aminah',
            'email_local' => 'siti',
        ]));

        $response->assertRedirectContains('/daftar/sukses/');
        $this->assertSame(2, Registration::count());
        $this->assertSame(
            ['6281234567890', '6281234567890'],
            Registration::orderBy('id')->pluck('phone')->all(),
        );
        $this->assertSame(4, $this->event->fresh()->tickets_taken);
    }

    // ------------------------------------------------- kegagalan level DB

    public function test_bentrok_email_canonical_di_level_database_dibalas_ramah(): void
    {
        $duplicateInserted = false;

        // Balapan dua submit: baris dengan email_canonical sama masuk setelah
        // validasi unique lolos, tepat sebelum registrasi ini di-insert.
        Registration::creating(function () use (&$duplicateInserted): void {
            if ($duplicateInserted) {
                return;
            }

            $duplicateInserted = true;

            DB::table('registrations')->insert([
                'event_id' => $this->event->getKey(),
                'code' => 'PJT26-AAAAAA',
                'token' => str_repeat('a', 48),
                'name' => 'Budi Duluan',
                'regency_id' => $this->regency->getKey(),
                'email' => 'budi@gmail.com',
                'email_canonical' => 'budi@gmail.com',
                'phone' => '6289999999999',
                'ticket_qty' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 2]));

        $this->assertTrue($duplicateInserted, 'Listener penyisip duplikat tidak pernah jalan.');

        $response->assertRedirect('/daftar');
        $response->assertSessionHasErrors([
            'email_local' => 'Email ini sudah terdaftar. Gunakan menu Cari tiket saya untuk menerima ulang e-ticket.',
        ]);
        $response->assertSessionHasInput('name', 'Budi Santoso');

        // Transaksi ikut rollback: kuota dan tabel registrasi tidak berubah.
        $this->assertSame(0, $this->event->fresh()->tickets_taken);
        $this->assertSame(0, Registration::count());
    }

    public function test_lock_wait_timeout_menampilkan_pesan_server_sibuk(): void
    {
        $pdoException = new PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded');
        $pdoException->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'];

        $this->mock(RegisterAttendee::class)
            ->shouldReceive('handle')
            ->andThrow(new QueryException(
                'mysql',
                'update `events` set `tickets_taken` = `tickets_taken` + ? where `id` = ?',
                [2, $this->event->getKey()],
                $pdoException,
            ));

        $response = $this->post('/daftar', $this->payload(['ticket_qty' => 2]));

        $response->assertRedirect('/daftar');
        $response->assertSessionHasErrors([
            'form' => 'Server sedang sibuk karena banyak pendaftar. Silakan tekan Daftar sekali lagi.',
        ]);
        $response->assertSessionHasInput('name', 'Budi Santoso');
        $response->assertSessionHasInput('email_local', 'budi');
        $response->assertSessionHasInput('phone', '081234567890');
        $response->assertSessionHasInput('ticket_qty', 2);

        $this->assertSame(0, $this->event->fresh()->tickets_taken);
        $this->assertSame(0, Registration::count());

        // Banner tampil di halaman form.
        $this->followingRedirects()
            ->post('/daftar', $this->payload(['ticket_qty' => 2]))
            ->assertOk()
            ->assertSeeText('Server sedang sibuk karena banyak pendaftar. Silakan tekan Daftar sekali lagi.');
    }

    public function test_query_exception_lain_tidak_ditangkap_dan_menjadi_500(): void
    {
        $pdoException = new PDOException('SQLSTATE[42000]: Syntax error or access violation: 1064');
        $pdoException->errorInfo = ['42000', 1064, 'You have an error in your SQL syntax'];

        $this->mock(RegisterAttendee::class)
            ->shouldReceive('handle')
            ->andThrow(new QueryException('mysql', 'select * from `events`', [], $pdoException));

        $this->post('/daftar', $this->payload(['ticket_qty' => 2]))->assertStatus(500);
    }

    // ------------------------------------------------------------ validasi

    public function test_ticket_qty_nol_dan_lima_ditolak(): void
    {
        foreach ([0, 5] as $qty) {
            $response = $this->post('/daftar', $this->payload(['ticket_qty' => $qty]));

            $response->assertSessionHasErrors('ticket_qty');
        }

        $this->assertSame(0, Registration::count());
        $this->assertSame(0, $this->event->fresh()->tickets_taken);
    }

    public function test_email_local_tidak_boleh_berisi_at_atau_spasi(): void
    {
        $this->post('/daftar', $this->payload(['email_local' => 'budi@lain']))
            ->assertSessionHasErrors('email_local');

        $this->post('/daftar', $this->payload(['email_local' => 'budi santoso']))
            ->assertSessionHasErrors('email_local');

        $this->assertSame(0, Registration::count());
    }

    public function test_domain_lainnya_yang_ternyata_ada_di_daftar_diperlakukan_sama(): void
    {
        $this->post('/daftar', $this->payload([
            'email_local' => 'Budi.Santoso+1',
            'email_domain' => 'lainnya',
            'email_domain_other' => ' Gmail.com ',
        ]))->assertRedirectContains('/daftar/sukses/');

        $registration = Registration::firstOrFail();

        $this->assertSame('budi.santoso+1@gmail.com', $registration->email);
        $this->assertSame('budisantoso@gmail.com', $registration->email_canonical);

        // Pendaftaran kedua lewat dropdown gmail.com ditolak sebagai duplikat.
        $this->post('/daftar', $this->payload([
            'email_local' => 'budisantoso',
            'email_domain' => 'gmail.com',
        ]))->assertSessionHasErrors('email_local');

        $this->assertSame(1, Registration::count());
    }

    public function test_domain_lainnya_dari_daftar_ditampilkan_ulang_sebagai_pilihan_dropdown(): void
    {
        $response = $this->followingRedirects()->post('/daftar', $this->payload([
            'name' => '',
            'email_domain' => 'lainnya',
            'email_domain_other' => 'Yahoo.CO.ID',
        ]));

        $response->assertOk();
        // Dropdown memilih yahoo.co.id, kotak "Lainnya…" kembali kosong.
        $response->assertSee('<option value="yahoo.co.id" selected>', false);
        $response->assertSee('<option value="lainnya" >', false);
        $response->assertDontSee('Yahoo.CO.ID', false);
    }

    public function test_domain_lainnya_wajib_diisi_dan_berbentuk_domain(): void
    {
        $this->post('/daftar', $this->payload(['email_domain' => 'lainnya', 'email_domain_other' => '']))
            ->assertSessionHasErrors('email_domain_other');

        $this->post('/daftar', $this->payload(['email_domain' => 'lainnya', 'email_domain_other' => 'kantor']))
            ->assertSessionHasErrors('email_domain_other');

        $this->assertSame(0, Registration::count());
    }

    public function test_field_wajib_lainnya_divalidasi(): void
    {
        $response = $this->post('/daftar', [
            'ticket_qty' => 1,
        ]);

        $response->assertSessionHasErrors(['name', 'regency_id', 'email_local', 'phone']);
        $this->assertSame(0, Registration::count());
    }
}

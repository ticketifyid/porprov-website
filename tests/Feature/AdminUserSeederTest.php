<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Akun admin adalah satu-satunya jalan masuk ke data peserta. Di produksi,
 * seeder menolak kata sandi contoh dan kata sandi pendek (docs/deploy.md).
 */
class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_menolak_kata_sandi_contoh_di_produksi(): void
    {
        $this->app['env'] = 'production';
        config(['porprov.admin' => ['username' => 'admin', 'password' => 'password']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('masih memakai kata sandi contoh');

        (new AdminUserSeeder)->run();
    }

    public function test_menolak_kata_sandi_contoh_apa_pun_huruf_besar_kecilnya(): void
    {
        $this->app['env'] = 'production';
        config(['porprov.admin' => ['username' => 'admin', 'password' => 'PassWord']]);

        $this->expectException(RuntimeException::class);

        (new AdminUserSeeder)->run();
    }

    public function test_menolak_kata_sandi_kurang_dari_12_karakter_di_produksi(): void
    {
        $this->app['env'] = 'production';
        config(['porprov.admin' => ['username' => 'admin', 'password' => 'Rahasia12x']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('terlalu pendek untuk produksi');

        (new AdminUserSeeder)->run();
    }

    public function test_menerima_kata_sandi_kuat_di_produksi(): void
    {
        $this->app['env'] = 'production';
        config(['porprov.admin' => ['username' => 'admin', 'password' => 'K4mis-Sore-Porprov!']]);

        (new AdminUserSeeder)->run();

        $this->assertDatabaseHas('users', ['username' => 'admin', 'role' => 'admin', 'is_active' => true]);
    }

    public function test_kata_sandi_lemah_tetap_boleh_di_luar_produksi(): void
    {
        config(['porprov.admin' => ['username' => 'devadmin', 'password' => 'password']]);

        (new AdminUserSeeder)->run();

        $this->assertNotNull(User::query()->where('username', 'devadmin')->first());
    }

    public function test_menolak_kredensial_kosong(): void
    {
        config(['porprov.admin' => ['username' => '', 'password' => '']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('belum diisi di .env');

        (new AdminUserSeeder)->run();
    }
}

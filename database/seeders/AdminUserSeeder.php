<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Panjang minimal kata sandi admin di produksi (docs/deploy.md).
     */
    private const MIN_PRODUCTION_PASSWORD_LENGTH = 12;

    /**
     * 1 akun admin dari ADMIN_USERNAME / ADMIN_PASSWORD di .env
     * (dibaca lewat config/porprov.php supaya aman terhadap config:cache).
     */
    public function run(): void
    {
        $username = config('porprov.admin.username');
        $password = config('porprov.admin.password');

        if (empty($username) || empty($password)) {
            throw new RuntimeException(
                'ADMIN_USERNAME/ADMIN_PASSWORD belum diisi di .env. Isi dulu sebelum menjalankan seeder ini.'
            );
        }

        $this->assertStrongEnoughForProduction((string) $password);

        // firstOrCreate, bukan updateOrCreate: menjalankan ulang seeder tidak
        // boleh mereset kata sandi (atau role/status) admin yang sudah ada.
        User::firstOrCreate(
            ['username' => $username],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
            ],
        );
    }

    /**
     * Akun admin adalah satu-satunya jalan masuk ke data peserta dan ke
     * pembatalan registrasi. Di produksi, kata sandi contoh dari dokumentasi
     * (dan kata sandi pendek) ditolak keras — lebih baik seeder gagal saat
     * deploy daripada panel admin terbuka dengan kata sandi tebakan.
     *
     * Di local/testing aturan ini sengaja tidak berlaku supaya pengembangan
     * dan `migrate:fresh --seed` tidak terganggu.
     */
    private function assertStrongEnoughForProduction(string $password): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (mb_strtolower($password) === 'password') {
            throw new RuntimeException(
                'ADMIN_PASSWORD masih memakai kata sandi contoh "password". Ganti sebelum menjalankan seeder di produksi.'
            );
        }

        if (mb_strlen($password) < self::MIN_PRODUCTION_PASSWORD_LENGTH) {
            throw new RuntimeException(sprintf(
                'ADMIN_PASSWORD terlalu pendek untuk produksi: minimal %d karakter.',
                self::MIN_PRODUCTION_PASSWORD_LENGTH,
            ));
        }
    }
}

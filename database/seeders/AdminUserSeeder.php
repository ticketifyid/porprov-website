<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
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

        User::updateOrCreate(
            ['username' => $username],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
            ],
        );
    }
}

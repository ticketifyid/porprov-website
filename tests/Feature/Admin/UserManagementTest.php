<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_tidak_bisa_mengakses_manajemen_akun(): void
    {
        $scanner = User::factory()->create(['role' => 'scanner']);

        $this->actingAs($scanner)->get('/admin/users')->assertForbidden();
        $this->actingAs($scanner)->get('/admin/users/create')->assertForbidden();
    }

    public function test_admin_bisa_membuat_akun_petugas_baru(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Pos 4 - Rina',
            'username' => 'pos4',
            'password' => 'password123',
            'role' => 'scanner',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('username', 'pos4')->firstOrFail();
        $this->assertSame('scanner', $user->role->value);
        $this->assertTrue($user->is_active);
    }

    public function test_admin_tidak_bisa_menonaktifkan_akun_sendiri(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->put('/admin/users/'.$admin->id, [
            'name' => $admin->name,
            'username' => $admin->username,
            'role' => 'admin',
            // is_active sengaja tidak dikirim (checkbox tidak dicentang)
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_tidak_bisa_menurunkan_role_sendiri(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create(); // admin lain, supaya bukan kasus "admin terakhir"

        $response = $this->actingAs($admin)->put('/admin/users/'.$admin->id, [
            'name' => $admin->name,
            'username' => $admin->username,
            'role' => 'scanner',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role->value);
    }

    public function test_admin_boleh_menonaktifkan_admin_lain_selama_masih_ada_admin_aktif_tersisa(): void
    {
        $aktor = User::factory()->admin()->create();
        $adminLain = User::factory()->admin()->create();

        // $aktor menonaktifkan admin lain itu — sah, karena $aktor sendiri masih admin aktif.
        $this->actingAs($aktor)->put('/admin/users/'.$adminLain->id, [
            'name' => $adminLain->name,
            'username' => $adminLain->username,
            'role' => 'admin',
            // tidak dicentang -> nonaktif
        ])->assertRedirect(route('admin.users.index'));

        $this->assertFalse($adminLain->fresh()->is_active);
    }
}

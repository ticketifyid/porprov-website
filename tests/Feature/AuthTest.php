<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'username' => 'petugas1',
            'password' => bcrypt('rahasia123'),
        ], $attributes));
    }

    public function test_login_page_is_accessible_and_shows_form(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('username', false);
        $response->assertSee('password', false);
    }

    public function test_admin_can_login_and_is_redirected_to_admin_dashboard(): void
    {
        $user = $this->makeUser(['role' => 'admin']);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'rahasia123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/admin');
    }

    public function test_scanner_can_login_and_is_redirected_to_scanner_page(): void
    {
        $user = $this->makeUser(['role' => 'scanner']);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'rahasia123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/scanner');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'salah-sekali',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeUser(['is_active' => false]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'rahasia123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString(
            'dinonaktifkan',
            collect(session('errors')->get('username'))->implode(' ')
        );
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        Cache::flush();
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => $user->username,
                'password' => 'salah',
            ]);
        }

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'salah',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan',
            collect(session('errors')->get('username'))->implode(' ')
        );
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->makeUser(['role' => 'admin']);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_logged_in_admin_visiting_login_page_is_redirected(): void
    {
        $user = $this->makeUser(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/admin');
    }
}

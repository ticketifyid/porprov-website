<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_cannot_open_admin_pages(): void
    {
        $user = User::factory()->create(['role' => 'scanner']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_admin_can_open_scanner_page(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get('/scanner');

        $response->assertOk();
    }

    public function test_scanner_can_open_scanner_page(): void
    {
        $user = User::factory()->create(['role' => 'scanner']);

        $response = $this->actingAs($user)->get('/scanner');

        $response->assertOk();
    }

    public function test_guest_visiting_admin_is_redirected_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    public function test_guest_visiting_scanner_is_redirected_to_login(): void
    {
        $response = $this->get('/scanner');

        $response->assertRedirect('/login');
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $response = $this->get('/admin');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}

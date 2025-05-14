<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->withPersonalTeam()->create(); // Ensure the user has a personal team

        // If the user has a personal team, Jetstream usually sets it as current by default.
        // However, to be explicit, you could also do:
        // if ($user->personalTeam()) {
        //     $user->switchTeam($user->personalTeam());
        // }
        // Or ensure current_team_id is set if your logic relies on it directly.
        // For most Jetstream setups, withPersonalTeam() is sufficient.

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }
}

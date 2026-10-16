<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_own_location(): void
    {
        $location = Location::factory()->create(['title' => 'Harbour Bakery']);

        $this->asUser($location->user)->getJson("/api/locations/{$location->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Harbour Bakery')
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_user_cannot_view_another_users_location(): void
    {
        $location = Location::factory()->create();
        $intruder = User::factory()->create();

        $this->asUser($intruder)->getJson("/api/locations/{$location->id}")->assertNotFound();
    }

    public function test_admin_routes_are_forbidden_for_owners(): void
    {
        $this->asUser(User::factory()->create())->getJson('/api/admin/health')->assertForbidden();
    }

    public function test_admin_can_reach_admin_routes(): void
    {
        $this->asUser(User::factory()->admin()->create())->getJson('/api/admin/health')
            ->assertOk()->assertJsonStructure(['queued_jobs', 'failed_jobs']);
    }

    public function test_promote_command_grants_admin_role(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $demo = User::factory()->create(['email' => 'demo@demo.invalid', 'is_demo' => true]);

        $this->artisan('user:promote', ['email' => 'Owner@Example.com'])->assertSuccessful();
        $this->artisan('user:promote', ['email' => 'demo@demo.invalid'])->assertFailed();
        $this->artisan('user:promote', ['email' => 'nobody@example.com'])->assertFailed();

        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertFalse($demo->fresh()->isAdmin());
    }
}

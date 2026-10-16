<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_reports_available_sign_in_methods(): void
    {
        config(['services.google.client_id' => null, 'services.demo.login_enabled' => true]);

        $this->getJson('/api/config')->assertOk()->assertExactJson(['googleEnabled' => false, 'demoEnabled' => true]);
    }

    public function test_demo_login_creates_an_isolated_demo_owner(): void
    {
        $first = $this->postJson('/api/auth/demo')
            ->assertCreated()
            ->assertJsonPath('user.is_demo', true)
            ->assertJsonPath('user.role', 'owner')
            ->assertJsonPath('user.timezone', 'UTC')
            ->assertJsonMissingPath('user.id');

        $this->postJson('/api/auth/demo')->assertCreated();

        $this->assertSame(2, User::where('is_demo', true)->count());
        $this->withToken($first->json('token'))->getJson('/api/me')
            ->assertOk()->assertJsonPath('user.email', $first->json('user.email'));
    }

    public function test_demo_login_can_be_disabled(): void
    {
        config(['services.demo.login_enabled' => false]);

        $this->postJson('/api/auth/demo')->assertNotFound();
        $this->assertSame(0, User::count());
    }

    public function test_demo_login_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/demo')->assertCreated();
        }

        $this->postJson('/api/auth/demo')
            ->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertHeader('Retry-After');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->withToken('1|not-a-real-token')->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = User::factory()->create()->createToken('spa')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = User::factory()->create()->createToken('spa', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_account_deletion_removes_user_data_and_tokens(): void
    {
        $user = User::factory()->create();
        $user->locations()->create(['google_location_name' => 'locations/1', 'title' => 'Corner Cafe']);
        $token = $user->createToken('spa')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/me')->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('locations', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_errors_are_generic_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/test-explode', fn () => throw new \RuntimeException('secret internals'));

        $this->getJson('/api/test-explode')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error']);
    }

    public function test_security_headers_present(): void
    {
        $this->getJson('/api/config')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}

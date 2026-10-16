<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_routes_are_blocked_until_terms_are_accepted(): void
    {
        $this->asUser(User::factory()->withoutTerms()->create());

        foreach (['/api/locations', '/api/queue', '/api/settings'] as $uri) {
            $this->getJson($uri)->assertForbidden()->assertJsonPath('code', 'terms_required')->assertJsonStructure(['message']);
        }
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.terms_accepted', false);
    }

    public function test_admins_must_accept_terms_too(): void
    {
        $this->asUser(User::factory()->admin()->withoutTerms()->create())
            ->getJson('/api/admin/usage')->assertForbidden()->assertJsonPath('code', 'terms_required');
    }

    public function test_accepting_terms_requires_the_checkbox_and_unlocks_the_api(): void
    {
        $user = User::factory()->withoutTerms()->create();
        $this->asUser($user);

        $this->postJson('/api/me/accept-terms')->assertUnprocessable()->assertJsonValidationErrors('accept_terms');
        $this->postJson('/api/me/accept-terms', ['accept_terms' => false])->assertUnprocessable();
        $this->assertNull($user->fresh()->terms_accepted_at);

        $this->postJson('/api/me/accept-terms', ['accept_terms' => true])->assertOk()->assertJsonPath('user.terms_accepted', true);
        $this->assertNotNull($user->fresh()->terms_accepted_at);
        $this->getJson('/api/locations')->assertOk();
    }

    public function test_unaccepted_owner_can_still_delete_the_account(): void
    {
        $user = User::factory()->withoutTerms()->create();
        $token = $user->createToken('spa')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/me')->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_demo_sign_in_requires_accepting_the_terms(): void
    {
        $this->postJson('/api/auth/demo')->assertUnprocessable()->assertJsonValidationErrors('accept_terms');
        $this->postJson('/api/auth/demo', ['accept_terms' => false])->assertUnprocessable();
        $this->assertSame(0, User::count());

        $this->postJson('/api/auth/demo', ['accept_terms' => true])->assertCreated()->assertJsonPath('user.terms_accepted', true);
        $this->assertNotNull(User::sole()->terms_accepted_at);
    }

    public function test_terms_and_summary_dates_cannot_be_set_through_settings(): void
    {
        $user = User::factory()->create(['terms_accepted_at' => now()->subYear()]);
        $before = $user->terms_accepted_at->toIso8601String();

        $this->asUser($user)->putJson('/api/settings', [
            'tone' => 'friendly', 'strictness' => 'balanced', 'notify_daily' => true, 'notify_hour' => 7, 'timezone' => 'UTC',
            'terms_accepted_at' => null, 'last_summary_sent_on' => '2030-01-01',
        ])->assertOk();

        $this->assertSame($before, $user->fresh()->terms_accepted_at->toIso8601String());
        $this->assertNull($user->fresh()->last_summary_sent_on);
    }
}

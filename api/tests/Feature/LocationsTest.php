<?php

namespace Tests\Feature;

use App\Enums\DraftStatus;
use App\Models\Draft;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.driver' => 'real', 'services.llm.api_key' => null]);
        Http::preventStrayRequests();
    }

    private function pendingDraftFor(Location $location, DraftStatus $status = DraftStatus::PendingReview): Draft
    {
        $review = $location->reviews()->create([
            'google_review_name' => $location->google_location_name.'/reviews/'.fake()->unique()->numberBetween(),
            'reviewer_display_name' => 'Sam T.',
            'star_rating' => 4,
            'reviewed_at' => now(),
        ]);

        return $review->drafts()->create(['status' => $status, 'body' => 'Thanks!', 'generator' => 'template']);
    }

    public function test_demo_login_seeds_sample_locations_reviews_and_drafts(): void
    {
        $token = $this->postJson('/api/auth/demo', ['accept_terms' => true])->assertCreated()->json('token');

        $user = User::where('is_demo', true)->sole();
        $this->assertSame(3, $user->locations()->where('monitoring', true)->count());
        $this->assertGreaterThan(0, $user->reviews()->count());
        $this->assertSame($user->reviews()->count(), Draft::where('status', 'pending_review')->where('generator', 'template')->count());
        $this->assertSame(0, Review::whereNull('sentiment')->count());

        $res = $this->withToken($token)->getJson('/api/locations')->assertOk()->assertJsonCount(3, 'data');
        $this->assertStringStartsWith('Sample Bakery', $res->json('data.0.title'));
        $this->assertTrue($res->json('data.0.is_sample'));
        $this->assertSame($user->reviews()->count(), array_sum(array_column($res->json('data'), 'pending_drafts')));
    }

    public function test_index_lists_only_the_owners_locations_with_pending_counts(): void
    {
        $mine = Location::factory()->create(['title' => 'Mine']);
        $theirs = Location::factory()->create(['title' => 'Theirs']);
        $this->pendingDraftFor($mine);
        $this->pendingDraftFor($mine);
        $this->pendingDraftFor($mine, DraftStatus::Published);
        $this->pendingDraftFor($theirs);

        $this->asUser($mine->user)->getJson('/api/locations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mine')
            ->assertJsonPath('data.0.pending_drafts', 2)
            ->assertJsonPath('data.0.is_sample', false)
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.google_location_name');
    }

    public function test_owner_can_toggle_monitoring_and_set_timezone(): void
    {
        $location = Location::factory()->create();

        $this->asUser($location->user)->patchJson("/api/locations/{$location->id}", ['monitoring' => true, 'timezone' => 'Asia/Karachi'])
            ->assertOk()
            ->assertJsonPath('data.monitoring', true)
            ->assertJsonPath('data.timezone', 'Asia/Karachi')
            ->assertJsonPath('data.pending_drafts', 0);

        $this->assertTrue($location->fresh()->monitoring);
    }

    public function test_timezone_must_be_a_known_identifier(): void
    {
        $location = Location::factory()->create();

        $this->asUser($location->user)->patchJson("/api/locations/{$location->id}", ['timezone' => 'Mars/Olympus_Mons'])
            ->assertUnprocessable()->assertJsonValidationErrors('timezone');
        $this->asUser($location->user)->patchJson("/api/locations/{$location->id}", ['monitoring' => 'perhaps'])
            ->assertUnprocessable()->assertJsonValidationErrors('monitoring');

        $this->assertSame('UTC', $location->fresh()->timezone);
    }

    public function test_user_cannot_patch_another_users_location(): void
    {
        $location = Location::factory()->create();

        // 404 even with invalid input, so the location's existence isn't revealed.
        $this->asUser(User::factory()->create())->patchJson("/api/locations/{$location->id}", ['monitoring' => true])->assertNotFound();
        $this->asUser(User::factory()->create())->patchJson("/api/locations/{$location->id}", ['timezone' => 'nope'])->assertNotFound();

        $this->assertFalse($location->fresh()->monitoring);
    }

    public function test_refresh_upserts_locations_and_keeps_owner_settings(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/London']);
        $user->googleAccount()->create(['refresh_token' => 'r', 'access_token' => 'a', 'expires_at' => now()->addHour()]);
        $existing = $user->locations()->create([
            'google_location_name' => 'accounts/1/locations/2', 'title' => 'Old name', 'timezone' => 'Asia/Karachi', 'monitoring' => true,
        ]);
        Http::fake([
            'mybusinessaccountmanagement.googleapis.com/*' => Http::response(['accounts' => [['name' => 'accounts/1']]]),
            'mybusinessbusinessinformation.googleapis.com/*' => Http::response(['locations' => [
                ['name' => 'locations/2', 'title' => 'New name'],
                ['name' => 'locations/3', 'title' => 'Another'],
            ]]),
        ]);

        $this->asUser($user)->postJson('/api/locations/refresh')->assertOk()->assertJsonCount(2, 'data');

        $existing->refresh();
        $this->assertSame(['New name', 'Asia/Karachi', true], [$existing->title, $existing->timezone, $existing->monitoring]);
        $added = $user->locations()->where('google_location_name', 'accounts/1/locations/3')->sole();
        $this->assertSame(['Europe/London', false], [$added->timezone, $added->monitoring]);
    }

    public function test_refresh_needs_a_connected_google_account_for_real_data(): void
    {
        $this->asUser(User::factory()->create())->postJson('/api/locations/refresh')->assertStatus(409)->assertJsonStructure(['message']);
    }

    public function test_refresh_reports_google_failures_generically(): void
    {
        $user = User::factory()->create();
        $user->googleAccount()->create(['refresh_token' => 'r', 'access_token' => 'a', 'expires_at' => now()->addHour()]);
        Http::fake(['*' => Http::response('internal detail', 500)]);

        $res = $this->asUser($user)->postJson('/api/locations/refresh')->assertStatus(502);
        $this->assertStringNotContainsString('internal detail', $res->getContent());
    }

    public function test_refresh_is_rate_limited_per_user(): void
    {
        $user = User::factory()->create(['is_demo' => true]);

        for ($i = 0; $i < 3; $i++) {
            $this->asUser($user)->postJson('/api/locations/refresh')->assertOk();
        }
        $this->asUser($user)->postJson('/api/locations/refresh')->assertStatus(429)->assertJsonStructure(['message']);
        $this->assertSame(3, $user->locations()->count());
    }
}

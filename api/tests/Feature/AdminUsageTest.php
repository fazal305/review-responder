<?php

namespace Tests\Feature;

use App\Models\ApiUsageLog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUsageTest extends TestCase
{
    use RefreshDatabase;

    private function log(array $attributes, string $at): void
    {
        ApiUsageLog::forceCreate($attributes + ['created_at' => $at]);
    }

    public function test_owners_get_403(): void
    {
        $this->asUser(User::factory()->create())->getJson('/api/admin/usage')
            ->assertForbidden()->assertJsonPath('message', 'This area is for administrators only.');
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/admin/usage')->assertUnauthorized();
    }

    public function test_admin_sees_seven_day_aggregates_without_personal_data(): void
    {
        Carbon::setTestNow('2026-10-16 12:00:00 UTC');
        $owner = User::factory()->create(['email' => 'owner@example.com', 'name' => 'Owner Name']);
        User::factory()->create(['is_demo' => true]);
        $location = Location::factory()->for($owner)->create(['monitoring' => true]);
        $review = $location->reviews()->create([
            'google_review_name' => 'reviews/1', 'reviewer_display_name' => 'Secret Reviewer', 'star_rating' => 2,
            'comment' => 'Private review text', 'reviewed_at' => now(),
        ]);
        $review->drafts()->create(['status' => 'pending_review', 'body' => 'Draft body', 'generator' => 'template']);
        $review->drafts()->create(['status' => 'draft_failed', 'body' => '', 'generator' => 'template']);

        $this->log(['user_id' => $owner->id, 'service' => 'llm', 'action' => 'chat.draft', 'ok' => true, 'duration_ms' => 100], '2026-10-15 10:00:00');
        $this->log(['user_id' => $owner->id, 'service' => 'llm', 'action' => 'chat.draft', 'ok' => false, 'duration_ms' => 301], '2026-10-16 09:00:00');
        $this->log(['user_id' => $owner->id, 'service' => 'google', 'action' => 'reviews.list', 'ok' => true, 'duration_ms' => 50], '2026-10-14 10:00:00');
        $this->log(['user_id' => $owner->id, 'service' => 'google', 'action' => 'reviews.list', 'ok' => false, 'duration_ms' => 50], '2026-10-01 10:00:00'); // outside the window
        DB::table('failed_jobs')->insert(['uuid' => 'x', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);

        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
        $response = $this->asUser($admin)->getJson('/api/admin/usage')->assertOk();

        $response->assertJsonPath('window_days', 7)
            ->assertJsonPath('services', [
                ['service' => 'google', 'action' => 'reviews.list', 'calls' => 1, 'failures' => 0, 'avg_duration_ms' => 50],
                ['service' => 'llm', 'action' => 'chat.draft', 'calls' => 2, 'failures' => 1, 'avg_duration_ms' => 201],
            ])
            ->assertJsonPath('recent_failures.0', ['service' => 'llm', 'action' => 'chat.draft', 'created_at' => '2026-10-16T09:00:00+00:00'])
            ->assertJsonCount(2, 'recent_failures')
            ->assertJsonPath('failed_jobs', 1)
            ->assertJsonPath('totals.users', 2)
            ->assertJsonPath('totals.demo_users', 1)
            ->assertJsonPath('totals.locations', 1)
            ->assertJsonPath('totals.monitored_locations', 1)
            ->assertJsonPath('totals.drafts', ['pending_review' => 1, 'published' => 0, 'dismissed' => 0, 'draft_failed' => 1]);

        $body = $response->getContent();
        foreach (['owner@example.com', 'admin@example.com', 'Owner Name', 'Secret Reviewer', 'Private review text', 'Draft body', 'user_id', '"email"'] as $private) {
            $this->assertStringNotContainsString($private, $body);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\GenerateDraft;
use App\Jobs\SyncLocationReviews;
use App\Models\Draft;
use App\Models\Location;
use App\Models\User;
use App\Services\Google\DemoGoogleBusiness;
use App\Services\ReviewSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviewSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.driver' => 'demo', 'services.llm.api_key' => null]);
        Http::preventStrayRequests();
    }

    private function location(array $attributes): Location
    {
        return Location::factory()->create($attributes + ['monitoring' => true, 'google_location_name' => 'accounts/sample/locations/1']);
    }

    public function test_command_queues_only_locations_in_their_local_two_am_hour_not_synced_today(): void
    {
        // 21:30 UTC is 02:30 on the 17th in Karachi (UTC+5), 03:00 in Kolkata, 22:30 in London, 17:30 in New York.
        Carbon::setTestNow('2026-10-16 21:30:00 UTC');
        Queue::fake();

        $due = $this->location(['timezone' => 'Asia/Karachi', 'last_synced_on' => '2026-10-16']);
        $neverSynced = $this->location(['timezone' => 'Asia/Karachi']);
        $this->location(['timezone' => 'Asia/Karachi', 'last_synced_on' => '2026-10-17']);
        $this->location(['timezone' => 'Asia/Karachi', 'monitoring' => false]);
        $this->location(['timezone' => 'Asia/Kolkata']);
        $this->location(['timezone' => 'Europe/London']);
        $this->location(['timezone' => 'America/New_York']);

        $this->artisan('reviews:sync')->expectsOutput('Queued 2 location sync(s).')->assertSuccessful();

        Queue::assertPushed(SyncLocationReviews::class, 2);
        $queued = Queue::pushed(SyncLocationReviews::class)->map(fn ($job) => $job->location->id)->sort()->values()->all();
        $this->assertSame([$due->id, $neverSynced->id], $queued);
    }

    public function test_command_follows_the_clock_in_each_timezone(): void
    {
        Queue::fake();
        $london = $this->location(['timezone' => 'Europe/London']);
        $newYork = $this->location(['timezone' => 'America/New_York']);

        Carbon::setTestNow('2026-10-17 01:00:00 UTC'); // 02:00 BST in London, 21:00 in New York
        $this->artisan('reviews:sync')->assertSuccessful();
        Carbon::setTestNow('2026-10-17 06:00:00 UTC'); // 02:00 EDT in New York
        $this->artisan('reviews:sync')->assertSuccessful();
        Carbon::setTestNow('2026-10-17 12:00:00 UTC');
        $this->artisan('reviews:sync')->expectsOutput('No locations are due.')->assertSuccessful();

        $this->assertSame([$london->id, $newYork->id], Queue::pushed(SyncLocationReviews::class)->map(fn ($job) => $job->location->id)->all());
    }

    public function test_force_and_location_options_skip_the_clock(): void
    {
        Carbon::setTestNow('2026-10-16 12:00:00 UTC');
        Queue::fake();
        $this->location(['timezone' => 'UTC']);
        $this->location(['timezone' => 'UTC', 'last_synced_on' => '2026-10-16']);
        $unmonitored = $this->location(['monitoring' => false]);

        $this->artisan('reviews:sync --force')->expectsOutput('Queued 2 location sync(s).');
        $this->artisan('reviews:sync', ['--location' => $unmonitored->id])->expectsOutput('Queued 1 location sync(s).');
    }

    public function test_sync_job_stores_new_reviews_and_drafts_them_once(): void
    {
        Carbon::setTestNow('2026-10-16 23:00:00 UTC');
        $location = $this->location(['timezone' => 'UTC']);

        SyncLocationReviews::dispatch($location);
        $reviews = $location->reviews()->count();
        $this->assertGreaterThan(0, $reviews);
        $this->assertSame($reviews, Draft::where('status', 'pending_review')->count());
        $this->assertSame('2026-10-16', $location->fresh()->last_synced_on->toDateString());

        // Re-syncing the same window doesn't duplicate reviews or drafts.
        SyncLocationReviews::dispatch($location->fresh());
        $this->assertSame($reviews, $location->reviews()->count());
        $this->assertSame($reviews, Draft::count());
    }

    public function test_reviews_already_answered_on_google_are_skipped(): void
    {
        Carbon::setTestNow('2026-10-16 12:00:00 UTC');
        $location = $this->location(['timezone' => 'UTC']);
        $stored = app(ReviewSync::class)->syncLocation($location, now()->subDays(30));
        $offered = app(DemoGoogleBusiness::class)->listReviewsSince($location, now()->subDays(30));

        $answered = array_filter($offered, fn ($r) => $r['reply'] !== null);
        $this->assertNotEmpty($answered, 'The sample data should include an answered review.');
        $this->assertSame(count($offered) - count($answered), $stored->count());
        $this->assertSame(0, $location->reviews()->whereIn('google_review_name', array_column($answered, 'name'))->count());
    }

    public function test_since_covers_missed_nights_without_gaps(): void
    {
        Carbon::setTestNow('2026-10-16 21:30:00 UTC');

        $fresh = $this->location(['timezone' => 'Asia/Karachi']);
        $this->assertTrue(ReviewSync::since($fresh)->equalTo(now()->subDay()));

        $missed = $this->location(['timezone' => 'Asia/Karachi', 'last_synced_on' => '2026-10-13']);
        $this->assertTrue(ReviewSync::since($missed)->equalTo(Carbon::parse('2026-10-13 00:00', 'Asia/Karachi')));

        $ancient = $this->location(['timezone' => 'UTC', 'last_synced_on' => '2025-01-01']);
        $this->assertTrue(ReviewSync::since($ancient)->equalTo(now()->subDays(30)));
    }

    public function test_draft_jobs_are_queued_per_new_review(): void
    {
        Queue::fake([GenerateDraft::class]);
        $location = $this->location(['timezone' => 'UTC']);

        (new SyncLocationReviews($location))->handle(app(ReviewSync::class));

        Queue::assertPushed(GenerateDraft::class, $location->reviews()->count());
    }

    public function test_demo_sync_uses_sample_data_even_when_driver_is_real(): void
    {
        config(['services.google.driver' => 'real']);
        $location = $this->location(['user_id' => User::factory()->create(['is_demo' => true])->id]);

        $this->assertGreaterThan(0, app(ReviewSync::class)->syncLocation($location, now()->subDays(3))->count());
        Http::assertNothingSent();
    }
}

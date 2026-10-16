<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.driver' => 'demo', 'services.llm.api_key' => null, 'services.demo.login_enabled' => true]);
    }

    private function demoAt(string $time): array
    {
        Carbon::setTestNow($time);
        $response = $this->postJson('/api/auth/demo', ['accept_terms' => true])->assertCreated();

        return [User::where('is_demo', true)->latest('id')->firstOrFail(), $response->json('token')];
    }

    public function test_demo_accounts_older_than_a_day_are_deleted_with_their_data(): void
    {
        [$old, $oldToken] = $this->demoAt('2026-10-15 09:00:00 UTC');
        [$recent, $recentToken] = $this->demoAt('2026-10-16 08:00:00 UTC');
        $realOld = User::factory()->create(['created_at' => '2025-01-01 00:00:00']);
        $realOld->locations()->create(['google_location_name' => 'locations/1', 'title' => 'Corner Cafe']);
        $this->assertGreaterThan(0, $old->locations()->count());

        Carbon::setTestNow('2026-10-16 10:00:00 UTC');
        $this->artisan('demo:prune')->expectsOutput('Deleted 1 demo account(s).')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $old->id]);
        $this->assertSame(0, DB::table('locations')->where('user_id', $old->id)->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $old->id)->count());
        $this->assertSame(1, DB::table('locations')->where('user_id', $realOld->id)->count());
        $this->assertDatabaseHas('users', ['id' => $recent->id]);
        $this->assertDatabaseHas('users', ['id' => $realOld->id]);

        // Only data owned by the remaining accounts is left behind.
        $remainingLocations = DB::table('locations')->pluck('id');
        $this->assertSame(0, DB::table('reviews')->whereNotIn('location_id', $remainingLocations)->count());
        $this->assertSame(0, DB::table('drafts')->whereNotIn('review_id', DB::table('reviews')->pluck('id'))->count());

        $this->withToken($oldToken)->getJson('/api/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($recentToken)->getJson('/api/me')->assertOk();
    }

    public function test_pruning_is_scheduled_hourly(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'demo:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
    }

    public function test_real_accounts_are_never_pruned(): void
    {
        $user = User::factory()->create(['created_at' => now()->subYears(2)]);

        $this->artisan('demo:prune')->expectsOutput('Deleted 0 demo account(s).')->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}

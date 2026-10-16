<?php

namespace Tests\Feature;

use App\Models\ApiUsageLog;
use App\Models\Location;
use App\Models\User;
use App\Services\Google\GoogleApiException;
use App\Services\Google\RealGoogleBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GoogleDriverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-16 12:00:00');
        Http::preventStrayRequests();
        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);

        $this->user = User::factory()->create();
        $this->user->googleAccount()->create([
            'refresh_token' => '1//plain-refresh-token',
            'access_token' => 'valid-access-token',
            'expires_at' => now()->addHour(),
        ]);
    }

    private function driver(): RealGoogleBusiness
    {
        return app(RealGoogleBusiness::class);
    }

    private static function review(string $id, string $stars, string $updated, array $extra = []): array
    {
        return array_replace([
            'name' => "accounts/1/locations/2/reviews/{$id}",
            'reviewer' => ['displayName' => "Reviewer {$id}"],
            'starRating' => $stars,
            'comment' => "Comment {$id}",
            'createTime' => $updated,
            'updateTime' => $updated,
        ], $extra);
    }

    public function test_lists_locations_across_accounts_and_pages(): void
    {
        Http::fake([
            'mybusinessaccountmanagement.googleapis.com/*' => Http::response(['accounts' => [['name' => 'accounts/1']]]),
            'mybusinessbusinessinformation.googleapis.com/*' => Http::sequence()
                ->push(['locations' => [[
                    'name' => 'locations/2',
                    'title' => 'Corner Cafe',
                    'storefrontAddress' => ['addressLines' => ['1 High St'], 'locality' => 'Leeds', 'postalCode' => 'LS1 1AA'],
                ]], 'nextPageToken' => 'p2'])
                ->push(['locations' => [['name' => 'locations/3', 'title' => 'Corner Cafe Kiosk']]]),
        ]);

        $locations = $this->driver()->listLocations($this->user);

        $this->assertSame([
            ['name' => 'accounts/1/locations/2', 'title' => 'Corner Cafe', 'address_line' => '1 High St, Leeds, LS1 1AA'],
            ['name' => 'accounts/1/locations/3', 'title' => 'Corner Cafe Kiosk', 'address_line' => null],
        ], $locations);
        $this->assertSame('accounts/1', $this->user->googleAccount->fresh()->google_account_name);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'accounts/1/locations')
            && $r['readMask'] === 'name,title,storefrontAddress' && $r->hasHeader('Authorization', 'Bearer valid-access-token'));
        Http::assertSent(fn (Request $r) => ($r['pageToken'] ?? null) === 'p2');
        $this->assertSame(3, ApiUsageLog::where(['service' => 'google', 'user_id' => $this->user->id, 'ok' => true])->count());
    }

    public function test_reviews_are_mapped_and_paging_stops_once_older_than_since(): void
    {
        $location = Location::factory()->for($this->user)->create(['google_location_name' => 'accounts/1/locations/2']);
        Http::fake([
            'mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews*' => Http::sequence()
                ->push(['reviews' => [
                    self::review('a', 'FIVE', '2026-10-16T09:00:00Z'),
                    self::review('b', 'TWO', '2026-10-16T08:00:00Z', ['reviewReply' => ['comment' => 'Sorry!']]),
                ], 'nextPageToken' => 'next'])
                ->push(['reviews' => [
                    self::review('c', 'THREE', '2026-10-15T13:00:00Z', ['reviewer' => ['isAnonymous' => true]]),
                    self::review('d', 'STAR_RATING_UNSPECIFIED', '2026-10-15T12:30:00Z'),
                    self::review('e', 'ONE', '2026-10-14T10:00:00Z'),
                ], 'nextPageToken' => 'never-requested']),
        ]);

        $reviews = $this->driver()->listReviewsSince($location, now()->subDay());

        $this->assertSame(['a', 'b', 'c'], array_map(fn ($r) => substr($r['name'], -1), $reviews));
        $this->assertSame([5, 2, 3], array_column($reviews, 'star_rating'));
        $this->assertSame([null, 'Sorry!', null], array_column($reviews, 'reply'));
        $this->assertSame('A Google user', $reviews[2]['reviewer']);
        $this->assertTrue($reviews[0]['reviewed_at']->equalTo(Carbon::parse('2026-10-16T09:00:00Z')));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r['orderBy'] === 'updateTime desc');
    }

    public function test_expired_access_token_is_refreshed_and_stored_encrypted(): void
    {
        $this->user->googleAccount->update(['access_token' => 'old-token', 'expires_at' => now()->subMinute()]);
        $location = Location::factory()->for($this->user)->create(['google_location_name' => 'accounts/1/locations/2']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.fresh-access-token', 'expires_in' => 3599]),
            'mybusiness.googleapis.com/*' => Http::response(['reviews' => []]),
        ]);

        $this->driver()->listReviewsSince($location, now()->subDay());

        Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/token'
            && $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === '1//plain-refresh-token');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'mybusiness.googleapis.com')
            && $r->hasHeader('Authorization', 'Bearer ya29.fresh-access-token'));

        $raw = DB::table('google_accounts')->first();
        $this->assertStringNotContainsString('fresh-access-token', $raw->access_token);
        $this->assertSame('ya29.fresh-access-token', Crypt::decryptString($raw->access_token));
        $this->assertTrue(Carbon::parse($raw->expires_at)->equalTo(now()->addSeconds(3599)));
        $this->assertTrue(ApiUsageLog::where(['action' => 'oauth.refresh', 'user_id' => $this->user->id])->exists());
    }

    public function test_post_reply_puts_the_comment(): void
    {
        $location = Location::factory()->for($this->user)->create(['google_location_name' => 'accounts/1/locations/2']);
        Http::fake(['mybusiness.googleapis.com/*' => Http::response(['comment' => 'Thanks!'])]);

        $this->driver()->postReply($location, 'accounts/1/locations/2/reviews/a', 'Thanks!');

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r->url() === 'https://mybusiness.googleapis.com/v4/accounts/1/locations/2/reviews/a/reply'
            && $r['comment'] === 'Thanks!');
    }

    public function test_google_errors_are_logged_and_raised(): void
    {
        Http::fake(['mybusinessaccountmanagement.googleapis.com/*' => Http::response(['error' => 'nope'], 403)]);

        $this->expectException(GoogleApiException::class);
        try {
            $this->driver()->listLocations($this->user);
        } finally {
            $this->assertTrue(ApiUsageLog::where(['action' => 'accounts.list', 'ok' => false])->exists());
        }
    }

    public function test_calls_are_throttled_per_google_account(): void
    {
        Http::fake();
        $key = 'google-api:'.$this->user->googleAccount->id;
        for ($i = 0; $i < RealGoogleBusiness::CALLS_PER_MINUTE; $i++) {
            RateLimiter::hit($key, 60);
        }

        try {
            $this->driver()->listLocations($this->user);
            $this->fail('Expected the throttle to refuse the call.');
        } catch (GoogleApiException $e) {
            $this->assertGreaterThan(0, $e->retryAfter);
        }
        Http::assertNothingSent();
    }
}

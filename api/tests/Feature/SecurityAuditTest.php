<?php

namespace Tests\Feature;

use App\Enums\DraftStatus;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    /** Routes a signed-out visitor may call. */
    private const PUBLIC = ['GET api/config', 'POST api/auth/demo', 'POST api/cron/run', 'GET api/auth/google/redirect', 'GET api/auth/google/callback', 'POST api/auth/exchange'];

    /** Static responses that touch no database or cache, so the host's health checks can call them freely. */
    private const UNTHROTTLED = ['GET api/health'];

    /** Signed-in routes that work before the terms are accepted. */
    private const BEFORE_TERMS = ['GET api/me', 'POST api/me/accept-terms', 'DELETE api/me', 'POST api/auth/logout'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.driver' => 'demo', 'services.llm.api_key' => null, 'app.debug' => false]);
        Http::preventStrayRequests();
    }

    /** @return array<string, RoutingRoute> */
    private function apiRoutes(): array
    {
        $routes = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $routes[$route->methods()[0].' '.$route->uri()] = $route;
            }
        }

        return $routes;
    }

    public function test_every_api_route_is_rate_limited_and_protected_routes_need_auth_and_terms(): void
    {
        $routes = $this->apiRoutes();
        $this->assertNotEmpty($routes);

        foreach ($routes as $name => $route) {
            $middleware = $route->gatherMiddleware();
            if (in_array($name, self::UNTHROTTLED, true)) {
                continue;
            }
            $this->assertNotEmpty(preg_grep('/^throttle:/', $middleware), "{$name} has no rate limit");

            if (in_array($name, self::PUBLIC, true)) {
                continue;
            }
            $this->assertContains('auth:sanctum', $middleware, "{$name} does not require sign-in");
            if (! in_array($name, self::BEFORE_TERMS, true)) {
                $this->assertContains('terms', $middleware, "{$name} does not require accepted terms");
            }
        }
    }

    public function test_no_route_with_an_id_reveals_or_changes_another_owners_records(): void
    {
        $location = Location::factory()->create(['monitoring' => false, 'timezone' => 'UTC']);
        $review = $location->reviews()->create([
            'google_review_name' => $location->google_location_name.'/reviews/1',
            'reviewer_display_name' => 'Sam T.',
            'star_rating' => 2,
            'reviewed_at' => now(),
        ]);
        $draft = $review->drafts()->create(['status' => DraftStatus::PendingReview, 'body' => 'Original reply.', 'generator' => 'template']);
        $ids = ['location' => $location->id, 'draft' => $draft->id];
        $payload = ['body' => 'Hijacked', 'monitoring' => true, 'timezone' => 'Asia/Karachi', 'tone' => 'shorter'];

        $this->asUser(User::factory()->create());
        $checked = 0;
        foreach ($this->apiRoutes() as $name => $route) {
            $params = $route->parameterNames();
            if ($params === []) {
                continue;
            }
            $this->assertEmpty(array_diff($params, array_keys($ids)), "{$name} has a parameter this test does not cover");

            $theirs = $missing = '/'.$route->uri();
            foreach ($params as $param) {
                $theirs = str_replace('{'.$param.'}', (string) $ids[$param], $theirs);
                $missing = str_replace('{'.$param.'}', '999999', $missing);
            }

            // Another owner's record and a record that doesn't exist answer exactly the same.
            $method = $route->methods()[0];
            $this->json($method, $theirs, $payload)->assertNotFound()->assertExactJson(['message' => 'Not Found.']);
            $this->json($method, $missing, $payload)->assertNotFound()->assertExactJson(['message' => 'Not Found.']);
            $checked++;
        }

        $this->assertSame(6, $checked);
        $this->assertFalse($location->fresh()->monitoring);
        $this->assertSame('UTC', $location->fresh()->timezone);
        $this->assertSame('Original reply.', $draft->fresh()->body);
        $this->assertSame(DraftStatus::PendingReview, $draft->fresh()->status);
    }

    public function test_error_responses_never_include_internals(): void
    {
        Route::get('/api/test-explode', fn () => throw new \RuntimeException('secret internals'));
        $this->asUser(User::factory()->create());

        $this->getJson('/api/test-explode')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
        $this->getJson('/api/does-not-exist')->assertNotFound()->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
        $this->deleteJson('/api/queue')->assertStatus(405)->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
        $this->putJson('/api/settings', ['tone' => 'shouty'])->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['tone']])
            ->assertJsonMissingPath('exception');
    }

    public function test_role_and_ownership_cannot_be_set_through_any_endpoint(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $other = User::factory()->create();
        $location = Location::factory()->for($user)->create();
        $this->asUser($user);
        $sneaky = ['role' => 'admin', 'is_demo' => true, 'email' => 'x@example.com', 'google_sub' => 'stolen', 'user_id' => $other->id];

        $this->putJson('/api/settings', $sneaky + [
            'tone' => 'friendly', 'strictness' => 'balanced', 'notify_daily' => true, 'notify_hour' => 7, 'timezone' => 'UTC',
        ])->assertOk();
        $this->patchJson("/api/locations/{$location->id}", $sneaky + ['monitoring' => true])->assertOk();
        $this->postJson('/api/me/accept-terms', $sneaky + ['accept_terms' => true])->assertOk();

        $fresh = $user->fresh();
        $this->assertFalse($fresh->isAdmin());
        $this->assertFalse($fresh->is_demo);
        $this->assertSame('owner@example.com', $fresh->email);
        $this->assertNull($fresh->google_sub);
        $this->assertSame($user->id, $location->fresh()->user_id);
        $this->assertSame($user->id, $fresh->businessGuideline->user_id);
    }

    public function test_signed_in_writes_share_the_per_owner_limit_and_answer_429_json(): void
    {
        $this->asUser(User::factory()->create());
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/me')->assertOk();
        }

        foreach ([['PUT', '/api/settings'], ['POST', '/api/me/accept-terms'], ['DELETE', '/api/me']] as [$method, $uri]) {
            $this->json($method, $uri)->assertTooManyRequests()->assertJsonStructure(['message'])->assertHeader('Retry-After');
        }
        $this->assertSame(1, User::count());
    }
}

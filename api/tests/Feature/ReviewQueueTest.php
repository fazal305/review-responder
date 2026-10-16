<?php

namespace Tests\Feature;

use App\Enums\DraftStatus;
use App\Models\Draft;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Services\Drafting\DraftingFailed;
use App\Services\Drafting\DraftResult;
use App\Services\Drafting\ReplyDrafter;
use App\Services\Google\GoogleApiException;
use App\Services\Google\GoogleBusinessProvider;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private FakeGoogle $google;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.driver' => 'real', 'services.llm.api_key' => null]);
        Http::preventStrayRequests();
        $this->google = new FakeGoogle;
        $this->app->instance(GoogleBusinessProvider::class, $this->google);
    }

    private function draft(?Location $location = null, array $attributes = [], array $review = []): Draft
    {
        $location ??= Location::factory()->create();
        $review = $location->reviews()->create($review + [
            'google_review_name' => $location->google_location_name.'/reviews/'.fake()->unique()->numberBetween(),
            'reviewer_display_name' => 'Nadia R.',
            'star_rating' => 2,
            'comment' => 'Cold coffee and a long wait.',
            'reviewed_at' => now()->subHour(),
            'sentiment' => 'negative',
            'themes' => ['slow service'],
        ]);

        return $review->drafts()->create($attributes + ['status' => DraftStatus::PendingReview, 'body' => 'Sorry about the wait, Nadia.', 'generator' => 'template']);
    }

    private function bindDrafter(?DraftResult $result = null, ?DraftingFailed $failure = null): FakeDrafter
    {
        $drafter = new FakeDrafter($result ?? new DraftResult('A new reply.', 'negative', ['cold coffee']), $failure);
        $this->app->instance(ReplyDrafter::class, $drafter);

        return $drafter;
    }

    public function test_queue_groups_the_owners_open_drafts_by_location(): void
    {
        $owner = User::factory()->create();
        $cafe = Location::factory()->for($owner)->create(['title' => 'B Cafe', 'timezone' => 'Europe/London']);
        $bakery = Location::factory()->for($owner)->create(['title' => 'A Bakery']);
        $quiet = Location::factory()->for($owner)->create(['title' => 'C Quiet']);
        $pending = $this->draft($cafe);
        $failed = $this->draft($cafe, ['status' => DraftStatus::DraftFailed, 'body' => '', 'error_code' => 'llm_unavailable'], ['reviewed_at' => now()]);
        $this->draft($bakery);
        $this->draft($cafe, ['status' => DraftStatus::Published]);
        $this->draft($quiet, ['status' => DraftStatus::Dismissed]);
        $this->draft(); // another owner's

        $res = $this->asUser($owner)->getJson('/api/queue')->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame(['A Bakery', 'B Cafe'], array_column($res->json('data'), 'title'));
        $this->assertSame('Europe/London', $res->json('data.1.timezone'));
        $this->assertFalse($res->json('data.1.is_sample'));
        $this->assertSame([$failed->id, $pending->id], array_column($res->json('data.1.drafts'), 'id'));
        $res->assertJsonPath('data.1.drafts.0.status', 'draft_failed')
            ->assertJsonPath('data.1.drafts.0.error', "The drafting service couldn't be reached. Try again, or write the reply yourself.")
            ->assertJsonPath('data.1.drafts.1.body', 'Sorry about the wait, Nadia.')
            ->assertJsonPath('data.1.drafts.1.review.reviewer_display_name', 'Nadia R.')
            ->assertJsonPath('data.1.drafts.1.review.star_rating', 2)
            ->assertJsonPath('data.1.drafts.1.review.themes', ['slow service'])
            ->assertJsonMissingPath('data.1.drafts.1.review.google_review_name')
            ->assertJsonMissingPath('data.1.drafts.1.error_code')
            ->assertJsonMissingPath('data.1.user_id');
    }

    public function test_queue_query_count_does_not_grow_with_drafts(): void
    {
        $owner = User::factory()->create();
        $location = Location::factory()->for($owner)->create();
        $this->draft($location);
        $this->asUser($owner);

        DB::enableQueryLog();
        $this->getJson('/api/queue')->assertOk();
        $few = count(DB::getQueryLog());

        foreach (range(1, 5) as $i) {
            $this->draft($location);
            $this->draft(Location::factory()->for($owner)->create());
        }
        DB::flushQueryLog();
        $this->getJson('/api/queue')->assertOk()->assertJsonCount(6, 'data');

        $this->assertSame($few, count(DB::getQueryLog()));
    }

    public function test_queue_is_empty_for_a_new_owner(): void
    {
        $this->draft();

        $this->asUser(User::factory()->create())->getJson('/api/queue')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_editing_saves_trimmed_text_as_a_manual_reply(): void
    {
        $draft = $this->draft();

        $this->asUser($draft->review->location->user)->patchJson("/api/drafts/{$draft->id}", ['body' => "  Thanks for telling us.  \n"])
            ->assertOk()
            ->assertJsonPath('data.body', 'Thanks for telling us.')
            ->assertJsonPath('data.generator', 'manual')
            ->assertJsonPath('data.review.reviewer_display_name', 'Nadia R.');

        $this->assertSame('Thanks for telling us.', $draft->fresh()->body);
    }

    public function test_editing_validates_the_body(): void
    {
        $draft = $this->draft();
        $this->asUser($draft->review->location->user);

        $this->patchJson("/api/drafts/{$draft->id}", ['body' => '   '])->assertUnprocessable()->assertJsonValidationErrors(['body' => 'Write a reply before saving.']);
        $this->patchJson("/api/drafts/{$draft->id}", [])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->patchJson("/api/drafts/{$draft->id}", ['body' => str_repeat('a', 4001)])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->patchJson("/api/drafts/{$draft->id}", ['body' => str_repeat('a', 4000)])->assertOk();
    }

    public function test_writing_over_a_failed_draft_makes_it_pending(): void
    {
        $draft = $this->draft(null, ['status' => DraftStatus::DraftFailed, 'body' => '', 'error_code' => 'llm_unavailable']);

        $this->asUser($draft->review->location->user)->patchJson("/api/drafts/{$draft->id}", ['body' => 'I wrote this myself.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.generator', 'manual')
            ->assertJsonPath('data.error', null);
    }

    public function test_published_and_dismissed_drafts_cannot_be_changed(): void
    {
        foreach ([DraftStatus::Published, DraftStatus::Dismissed] as $status) {
            $draft = $this->draft(null, ['status' => $status]);
            $this->asUser($draft->review->location->user);
            $this->bindDrafter();

            $this->patchJson("/api/drafts/{$draft->id}", ['body' => 'Changed'])->assertConflict();
            $this->postJson("/api/drafts/{$draft->id}/approve")->assertConflict();
            $this->postJson("/api/drafts/{$draft->id}/dismiss")->assertConflict();
            $this->postJson("/api/drafts/{$draft->id}/regenerate", ['tone' => 'shorter'])->assertConflict();

            $this->assertSame($status, $draft->fresh()->status);
            $this->assertSame('Sorry about the wait, Nadia.', $draft->fresh()->body);
        }
        $this->assertSame(0, $this->google->posted);
    }

    public function test_approve_posts_through_the_provider_and_publishes(): void
    {
        $this->freezeSecond();
        $draft = $this->draft();

        $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertSame(1, $this->google->posted);
        $this->assertSame([$draft->review->google_review_name, 'Sorry about the wait, Nadia.'], $this->google->last);
        $draft->refresh();
        $this->assertSame(DraftStatus::Published, $draft->status);
        $this->assertEquals(now(), $draft->published_at);
        $this->assertSame('template', $draft->generator);
    }

    public function test_approve_posts_the_text_shown_on_screen(): void
    {
        $draft = $this->draft();

        $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/approve", ['body' => ' Edited before posting. '])
            ->assertOk()
            ->assertJsonPath('data.body', 'Edited before posting.')
            ->assertJsonPath('data.generator', 'manual');

        $this->assertSame('Edited before posting.', $this->google->last[1]);
    }

    public function test_approve_validates_the_text(): void
    {
        $draft = $this->draft();
        $failed = $this->draft(null, ['status' => DraftStatus::DraftFailed, 'body' => '', 'error_code' => 'llm_unavailable']);

        $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/approve", ['body' => str_repeat('a', 4001)])->assertUnprocessable();
        $this->asUser($failed->review->location->user)->postJson("/api/drafts/{$failed->id}/approve")->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->assertSame(0, $this->google->posted);
    }

    public function test_approving_twice_posts_once(): void
    {
        $draft = $this->draft();
        $this->asUser($draft->review->location->user);

        $this->postJson("/api/drafts/{$draft->id}/approve")->assertOk();
        $this->postJson("/api/drafts/{$draft->id}/approve")->assertConflict();

        $this->assertSame(1, $this->google->posted);
    }

    public function test_approve_is_refused_while_another_post_is_in_flight(): void
    {
        $draft = $this->draft();
        $lock = cache()->lock('draft-post:'.$draft->id, 60);
        $lock->get();

        $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/approve")->assertConflict();

        $lock->release();
        $this->assertSame(0, $this->google->posted);
    }

    public function test_google_failure_returns_a_generic_error_and_keeps_the_draft(): void
    {
        Log::spy();
        $this->google->fail = new GoogleApiException('Google reviews.reply failed with HTTP 500.', 500);
        $draft = $this->draft();

        $res = $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/approve")->assertStatus(502);

        $this->assertStringNotContainsString('HTTP 500', $res->json('message'));
        $this->assertSame(DraftStatus::PendingReview, $draft->fresh()->status);
        $this->assertNull($draft->fresh()->published_at);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => str_contains($context['error'], 'HTTP 500'));
    }

    public function test_approve_is_refused_when_the_review_already_has_a_reply(): void
    {
        $draft = $this->draft(null, [], ['existing_reply' => 'Thanks for visiting!']);

        $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/approve")
            ->assertConflict()
            ->assertJsonPath('message', 'This review already has a reply on Google, so this draft was not posted. Dismiss it to clear it from your queue.');

        $this->assertSame(0, $this->google->posted);
        $this->assertSame(DraftStatus::PendingReview, $draft->fresh()->status);
    }

    public function test_demo_accounts_publish_without_calling_the_real_provider(): void
    {
        $owner = User::factory()->create(['is_demo' => true]);
        $draft = $this->draft(Location::factory()->for($owner)->create());

        $this->asUser($owner)->postJson("/api/drafts/{$draft->id}/approve")->assertOk()->assertJsonPath('data.status', 'published');

        $this->assertSame(0, $this->google->posted);
    }

    public function test_dismiss_marks_the_draft_dismissed(): void
    {
        $draft = $this->draft();

        $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed');

        $this->assertSame(DraftStatus::Dismissed, $draft->fresh()->status);
        $this->getJson('/api/queue')->assertJsonCount(0, 'data');
    }

    public function test_regenerate_uses_the_drafter_with_the_tone(): void
    {
        $drafter = $this->bindDrafter(new DraftResult('We are so sorry, Nadia.', 'negative', ['cold coffee', 'wait']));
        $draft = $this->draft();

        $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/regenerate", ['tone' => 'more_apologetic'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.body', 'We are so sorry, Nadia.')
            ->assertJsonPath('data.generator', 'fake-model')
            ->assertJsonPath('data.tone_modifier', 'more_apologetic')
            ->assertJsonPath('data.review.themes', ['cold coffee', 'wait']);

        $this->assertSame(['Make the reply more apologetic'], $drafter->modifiers);
    }

    public function test_regenerate_rejects_unknown_tones(): void
    {
        $drafter = $this->bindDrafter();
        $draft = $this->draft();

        $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/regenerate", ['tone' => 'angrier'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tone' => 'Choose one of the listed tones.']);

        $this->assertSame([], $drafter->modifiers);
    }

    public function test_regenerate_without_a_tone_retries_a_failed_draft(): void
    {
        $drafter = $this->bindDrafter();
        $draft = $this->draft(null, ['status' => DraftStatus::DraftFailed, 'body' => '', 'error_code' => 'llm_unavailable']);

        $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/regenerate")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_review')
            ->assertJsonPath('data.body', 'A new reply.')
            ->assertJsonPath('data.error', null);

        $this->assertSame([null], $drafter->modifiers);
    }

    public function test_regenerate_failure_marks_the_draft_failed_and_keeps_the_text(): void
    {
        Log::spy();
        $this->bindDrafter(failure: new DraftingFailed('llm_unavailable', 'LLM returned HTTP 503.'));
        $draft = $this->draft();

        $res = $this->asUser($draft->review->location->user)->postJson("/api/drafts/{$draft->id}/regenerate", ['tone' => 'shorter'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft_failed')
            ->assertJsonPath('data.body', 'Sorry about the wait, Nadia.');

        $this->assertStringNotContainsString('503', $res->json('data.error'));
        $this->assertSame('llm_unavailable', $draft->fresh()->error_code);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_regenerate_is_rate_limited(): void
    {
        $this->bindDrafter();
        $draft = $this->draft();
        $this->asUser($draft->review->location->user);

        foreach (range(1, 6) as $i) {
            $this->postJson("/api/drafts/{$draft->id}/regenerate", ['tone' => 'shorter'])->assertOk();
        }
        $this->postJson("/api/drafts/{$draft->id}/regenerate", ['tone' => 'shorter'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many requests. Please wait a minute and try again.');
    }

    public function test_other_owners_drafts_are_not_found(): void
    {
        $drafter = $this->bindDrafter();
        $draft = $this->draft();
        $this->asUser(User::factory()->create());

        $this->patchJson("/api/drafts/{$draft->id}", ['body' => 'Hijacked'])->assertNotFound();
        $this->patchJson("/api/drafts/{$draft->id}", ['body' => ''])->assertNotFound();
        $this->postJson("/api/drafts/{$draft->id}/approve")->assertNotFound();
        $this->postJson("/api/drafts/{$draft->id}/dismiss")->assertNotFound();
        $this->postJson("/api/drafts/{$draft->id}/regenerate", ['tone' => 'shorter'])->assertNotFound();

        $this->assertSame(0, $this->google->posted);
        $this->assertSame([], $drafter->modifiers);
        $fresh = $draft->fresh();
        $this->assertSame(DraftStatus::PendingReview, $fresh->status);
        $this->assertSame('Sorry about the wait, Nadia.', $fresh->body);
    }

    public function test_guests_cannot_reach_the_queue(): void
    {
        $draft = $this->draft();

        $this->getJson('/api/queue')->assertUnauthorized();
        $this->postJson("/api/drafts/{$draft->id}/approve")->assertUnauthorized();
    }
}

class FakeGoogle implements GoogleBusinessProvider
{
    public int $posted = 0;

    public ?array $last = null;

    public ?GoogleApiException $fail = null;

    public function listLocations(User $user): array
    {
        return [];
    }

    public function listReviewsSince(Location $location, CarbonInterface $since): array
    {
        return [];
    }

    public function postReply(Location $location, string $reviewName, string $text): void
    {
        if ($this->fail) {
            throw $this->fail;
        }
        $this->posted++;
        $this->last = [$reviewName, $text];
    }
}

class FakeDrafter implements ReplyDrafter
{
    /** @var list<?string> */
    public array $modifiers = [];

    public function __construct(private DraftResult $result, private ?DraftingFailed $failure = null) {}

    public function generator(): string
    {
        return 'fake-model';
    }

    public function draft(Review $review, ?string $toneModifier = null): DraftResult
    {
        $this->modifiers[] = $toneModifier;
        if ($this->failure) {
            throw $this->failure;
        }

        return $this->result;
    }
}

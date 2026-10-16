<?php

namespace Tests\Feature;

use App\Enums\DraftStatus;
use App\Jobs\SendDailySummary;
use App\Mail\DailySummary;
use App\Models\ApiUsageLog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailySummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.frontend_url' => 'https://app.example']);
    }

    private function owner(array $attributes = []): User
    {
        // Asia/Karachi is UTC+5, so 07:00 local is 02:00 UTC.
        return User::factory()->create($attributes + ['timezone' => 'Asia/Karachi', 'notify_hour' => 7, 'email' => 'owner@example.com']);
    }

    private function draft(Location $location, DraftStatus $status = DraftStatus::PendingReview): void
    {
        $location->reviews()->create([
            'google_review_name' => 'reviews/'.Str::uuid(),
            'reviewer_display_name' => 'Sam',
            'star_rating' => 5,
            'reviewed_at' => now(),
        ])->drafts()->create(['status' => $status, 'body' => 'Thanks!', 'generator' => 'template']);
    }

    private function runAt(string $utc): void
    {
        Carbon::setTestNow($utc);
        $this->artisan('summaries:send')->assertSuccessful();
    }

    public function test_sends_in_the_owners_local_hour_once_a_day(): void
    {
        Carbon::setTestNow('2026-10-16 00:30:00 UTC');
        $user = $this->owner();
        $location = Location::factory()->for($user)->create(['title' => 'Harbour Bakery']);
        $this->draft($location);

        $this->runAt('2026-10-16 01:59:00 UTC'); // 06:59 local
        Mail::assertNothingSent();

        $this->runAt('2026-10-16 02:05:00 UTC'); // 07:05 local
        Mail::assertSent(DailySummary::class, fn (DailySummary $mail) => $mail->hasTo('owner@example.com')
            && $mail->locations === [['title' => 'Harbour Bakery', 'count' => 1]] && $mail->failed === 0);
        $this->assertSame('2026-10-16', $user->fresh()->last_summary_sent_on->toDateString());

        $this->runAt('2026-10-16 02:45:00 UTC');
        Mail::assertSentCount(1);

        // The next morning only drafts created since the last summary count.
        Carbon::setTestNow('2026-10-17 01:00:00 UTC');
        $this->draft($location);
        $this->draft($location, DraftStatus::DraftFailed);
        $this->runAt('2026-10-17 02:10:00 UTC');
        Mail::assertSentCount(2);
        Mail::assertSent(DailySummary::class, fn (DailySummary $mail) => $mail->locations === [['title' => 'Harbour Bakery', 'count' => 1]] && $mail->failed === 1);

        $this->assertSame(2, ApiUsageLog::where(['service' => 'mail', 'action' => 'summary.send', 'ok' => true])->count());
    }

    public function test_skips_owners_with_nothing_new_to_report(): void
    {
        Carbon::setTestNow('2026-10-14 01:00:00 UTC');
        $user = $this->owner(['last_summary_sent_on' => '2026-10-15']);
        $location = Location::factory()->for($user)->create();
        $this->draft($location); // reported before the last summary
        $this->draft($location, DraftStatus::Published);

        $this->runAt('2026-10-16 02:05:00 UTC');

        Mail::assertNothingSent();
        $this->assertSame('2026-10-15', $user->fresh()->last_summary_sent_on->toDateString());
    }

    public function test_never_emails_demo_accounts_or_owners_who_switched_it_off(): void
    {
        Carbon::setTestNow('2026-10-16 01:00:00 UTC');
        $demo = $this->owner(['is_demo' => true, 'email' => 'demo-1@demo.invalid']);
        $off = $this->owner(['notify_daily' => false, 'email' => 'off@example.com']);
        foreach ([$demo, $off] as $user) {
            $this->draft(Location::factory()->for($user)->create());
        }

        $this->runAt('2026-10-16 02:05:00 UTC');

        Mail::assertNothingSent();
        $this->assertNull($demo->fresh()->last_summary_sent_on);
    }

    public function test_the_email_is_queued_rather_than_sent_inline(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-10-16 01:00:00 UTC');
        $user = $this->owner();
        $this->draft(Location::factory()->for($user)->create());

        $this->runAt('2026-10-16 02:05:00 UTC');

        Queue::assertPushed(SendDailySummary::class, fn (SendDailySummary $job) => $job->user->is($user));
        Mail::assertNothingSent();
    }

    public function test_email_lists_locations_failures_and_links_in_html_and_plain_text(): void
    {
        $mail = new DailySummary('Maria & Co', [['title' => 'Bakery <Main St>', 'count' => 2], ['title' => 'Kiosk', 'count' => 1]], 1);

        $this->assertSame('Your morning summary: 3 new reply drafts to review', $mail->envelope()->subject);
        $mail->assertSeeInHtml('Bakery &lt;Main St&gt;', false)
            ->assertDontSeeInHtml('<Main St>', false)
            ->assertSeeInHtml('2 drafts')
            ->assertSeeInHtml('https://app.example/app')
            ->assertSeeInHtml('https://app.example/app/settings')
            ->assertSeeInHtml('Change notification settings')
            ->assertSeeInText('Good morning, Maria & Co')
            ->assertSeeInText('- Bakery <Main St>: 2 drafts')
            ->assertSeeInText('- Kiosk: 1 draft')
            ->assertSeeInText('1 draft could not be written automatically')
            ->assertSeeInText('Open your queue: https://app.example/app')
            ->assertSeeInText('Change notification settings: https://app.example/app/settings');
    }
}

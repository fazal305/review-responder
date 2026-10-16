<?php

namespace App\Mail;

use App\Enums\DraftStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/** The owner's morning email: new drafts waiting per location, plus drafts that failed. Transactional, not marketing. */
class DailySummary extends Mailable
{
    /** @param  list<array{title: string, count: int}>  $locations */
    public function __construct(
        public string $ownerName,
        public array $locations,
        public int $failed,
    ) {}

    /** Builds the summary for drafts created since the last one; null when there is nothing to report. */
    public static function for(User $user): ?self
    {
        $since = self::since($user);
        $counts = fn (DraftStatus $status) => fn ($q) => $q->where('drafts.status', $status)->where('drafts.created_at', '>=', $since);

        $rows = $user->locations()
            ->withCount([
                'drafts as new_drafts' => $counts(DraftStatus::PendingReview),
                'drafts as failed_drafts' => $counts(DraftStatus::DraftFailed),
            ])
            ->orderBy('title')
            ->get();

        $locations = $rows->where('new_drafts', '>', 0)
            ->map(fn ($l) => ['title' => $l->title, 'count' => (int) $l->new_drafts])
            ->values()->all();
        $failed = (int) $rows->sum('failed_drafts');

        return $locations || $failed ? new self($user->name, $locations, $failed) : null;
    }

    /**
     * The previous summary went out in the owner's chosen hour on last_summary_sent_on. Days skipped
     * because there was nothing new don't move it, so drafts are never missed between summaries.
     */
    public static function since(User $user): CarbonInterface
    {
        if (! $user->last_summary_sent_on) {
            return now()->subDay();
        }

        return Carbon::parse($user->last_summary_sent_on->toDateString(), $user->timezone)->setHour($user->notify_hour)->utc();
    }

    public function total(): int
    {
        return array_sum(array_column($this->locations, 'count'));
    }

    public function envelope(): Envelope
    {
        $total = $this->total();

        return new Envelope(subject: $total > 0
            ? "Your morning summary: {$total} new reply ".($total === 1 ? 'draft' : 'drafts').' to review'
            : 'Your morning summary: some reply drafts need attention');
    }

    public function content(): Content
    {
        $frontend = config('services.frontend_url');

        return new Content(
            view: 'mail.daily-summary',
            text: 'mail.daily-summary-text',
            with: [
                'total' => $this->total(),
                'queueUrl' => $frontend.'/app',
                'settingsUrl' => $frontend.'/app/settings',
            ],
        );
    }
}

<?php

namespace App\Jobs;

use App\Enums\DraftStatus;
use App\Models\Review;
use App\Services\Drafting\DraftingFailed;
use App\Services\Drafting\ReplyDrafter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateDraft implements ShouldQueue
{
    use Queueable;

    // Drafting failures are recorded as draft_failed rather than retried; tries only cover infrastructure errors.
    public int $tries = 2;

    public int $backoff = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Review $review) {}

    public function handle(ReplyDrafter $drafter): void
    {
        if ($this->review->drafts()->exists()) {
            return;
        }

        try {
            $result = $drafter->draft($this->review);
        } catch (DraftingFailed $e) {
            Log::warning('Reply draft generation failed', [
                'review_id' => $this->review->id,
                'code' => $e->errorCode,
                'error' => $e->getMessage(),
            ]);
            $this->review->drafts()->create([
                'status' => DraftStatus::DraftFailed,
                'body' => '',
                'generator' => $drafter->generator(),
                'error_code' => $e->errorCode,
            ]);

            return;
        }

        DB::transaction(function () use ($result, $drafter) {
            $this->review->update(['sentiment' => $result->sentiment, 'themes' => $result->themes]);
            $this->review->drafts()->create([
                'status' => DraftStatus::PendingReview,
                'body' => $result->reply,
                'generator' => $drafter->generator(),
            ]);
        });
    }
}

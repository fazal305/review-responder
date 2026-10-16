<?php

namespace App\Jobs;

use App\Models\Location;
use App\Models\Review;
use App\Services\Google\GoogleApiException;
use App\Services\ReviewSync;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class SyncLocationReviews implements ShouldQueue
{
    use Queueable;

    // Rate-limit releases count as attempts, so retries are bounded by time and real errors instead of tries.
    public int $maxExceptions = 3;

    public array $backoff = [60, 300];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Location $location) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function middleware(): array
    {
        return [new RateLimited('google-sync')];
    }

    public function handle(ReviewSync $sync): void
    {
        try {
            $new = $sync->syncLocation($this->location);
        } catch (GoogleApiException $e) {
            if ($e->retryAfter) {
                $this->release($e->retryAfter);

                return;
            }
            throw $e;
        }

        $new->each(fn (Review $review) => GenerateDraft::dispatch($review));
    }
}

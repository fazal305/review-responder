<?php

namespace App\Services;

use App\Jobs\GenerateDraft;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Services\Google\GoogleBusiness;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReviewSync
{
    /** Upserts the owner's locations. New ones start unmonitored, in the owner's timezone. */
    public function importLocations(User $user): void
    {
        foreach (GoogleBusiness::for($user)->listLocations($user) as $item) {
            $location = $user->locations()->firstOrNew(['google_location_name' => $item['name']]);
            $location->fill(['title' => $item['title'], 'address_line' => $item['address_line']]);
            if (! $location->exists) {
                $location->fill(['timezone' => $user->timezone, 'monitoring' => false]);
            }
            $location->save();
        }
    }

    /**
     * Stores reviews we haven't seen that still need a reply, and marks the location synced for its local today.
     *
     * @return Collection<int, Review> the newly stored reviews
     */
    public function syncLocation(Location $location, ?CarbonInterface $since = null): Collection
    {
        $items = GoogleBusiness::for($location->user)->listReviewsSince($location, $since ?? self::since($location));

        $new = collect();
        foreach ($items as $item) {
            if ($item['reply'] !== null) {
                continue; // the owner already answered this one on Google
            }
            $review = $location->reviews()->firstOrCreate(['google_review_name' => $item['name']], [
                'reviewer_display_name' => $item['reviewer'],
                'star_rating' => $item['star_rating'],
                'comment' => $item['comment'],
                'reviewed_at' => $item['reviewed_at'],
            ]);
            if ($review->wasRecentlyCreated) {
                $new->push($review);
            }
        }

        $location->update(['last_synced_on' => now($location->timezone)->toDateString()]);

        return $new;
    }

    /**
     * Normally the last 24 hours. After missed nights we go back to the start of the last synced
     * local day instead, so nothing falls in a gap; overlap is harmless because reviews are deduped
     * by name. Capped at 30 days to bound the first fetch after a long pause.
     */
    public static function since(Location $location): CarbonInterface
    {
        $since = now()->subDay();
        if ($location->last_synced_on) {
            $since = $since->min(Carbon::parse($location->last_synced_on->toDateString(), $location->timezone));
        }

        return $since->max(now()->subDays(30));
    }

    /** Gives a new demo account its sample locations, monitored, with a first batch of reviews and drafts. */
    public function seedDemo(User $user): void
    {
        $this->importLocations($user);
        $user->locations()->update(['monitoring' => true]);

        foreach ($user->locations()->get() as $location) {
            foreach ($this->syncLocation($location, now()->subDays(2)) as $review) {
                GenerateDraft::dispatchSync($review);
            }
        }
    }
}

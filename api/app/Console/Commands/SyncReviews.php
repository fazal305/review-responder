<?php

namespace App\Console\Commands;

use App\Jobs\SyncLocationReviews;
use App\Models\Location;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('reviews:sync
    {--force : Queue every monitored location now, whatever its local time}
    {--location= : Queue just this location ID now (monitored or not)}')]
#[Description('Queue review syncs for monitored locations where it is 02:00-02:59 locally and that have not synced today')]
class SyncReviews extends Command
{
    public function handle(): int
    {
        if ($id = $this->option('location')) {
            $query = Location::whereKey($id);
        } elseif ($this->option('force')) {
            $query = Location::where('monitoring', true);
        } else {
            $query = $this->dueLocations();
            if (! $query) {
                $this->info('No locations are due.');

                return self::SUCCESS;
            }
        }

        $queued = 0;
        $query->chunkById(100, function ($locations) use (&$queued) {
            foreach ($locations as $location) {
                SyncLocationReviews::dispatch($location);
                $queued++;
            }
        });

        $this->info("Queued {$queued} location sync(s).");

        return self::SUCCESS;
    }

    /** Monitored locations whose local hour is 2 and whose last sync was before their local today. */
    private function dueLocations(): ?Builder
    {
        $zones = Location::where('monitoring', true)->distinct()->pluck('timezone')
            ->filter(fn (string $tz) => now($tz)->hour === 2);

        if ($zones->isEmpty()) {
            return null;
        }

        return Location::where('monitoring', true)->where(function (Builder $q) use ($zones) {
            foreach ($zones as $tz) {
                $q->orWhere(fn (Builder $q) => $q->where('timezone', $tz)->where(
                    fn (Builder $q) => $q->whereNull('last_synced_on')->orWhere('last_synced_on', '<', now($tz)->toDateString()),
                ));
            }
        });
    }
}

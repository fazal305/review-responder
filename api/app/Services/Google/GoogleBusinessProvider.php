<?php

namespace App\Services\Google;

use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;

interface GoogleBusinessProvider
{
    /** @return list<array{name: string, title: string, address_line: ?string}> */
    public function listLocations(User $user): array;

    /**
     * Reviews created or updated at or after $since, newest first.
     *
     * @return list<array{name: string, reviewer: string, star_rating: int, comment: ?string, reviewed_at: CarbonInterface, reply: ?string}>
     */
    public function listReviewsSince(Location $location, CarbonInterface $since): array;

    public function postReply(Location $location, string $reviewName, string $text): void;
}

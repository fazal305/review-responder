<?php

namespace App\Services\Google;

use App\Models\Location;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Sample data for demo accounts: a fictional bakery whose reviews are generated
 * deterministically from the location and date, so every nightly sync finds new ones.
 */
class DemoGoogleBusiness implements GoogleBusinessProvider
{
    private const LOCATIONS = [
        ['name' => 'accounts/sample/locations/1', 'title' => 'Sample Bakery — Downtown', 'address_line' => '12 Example Street, Sampletown'],
        ['name' => 'accounts/sample/locations/2', 'title' => 'Sample Bakery — Riverside', 'address_line' => '4 Placeholder Quay, Sampletown'],
        ['name' => 'accounts/sample/locations/3', 'title' => 'Sample Bakery — Station Kiosk', 'address_line' => 'Platform 1, Sampletown Station'],
    ];

    /** [stars, reviewer, comment] */
    private const REVIEWS = [
        [5, 'Amira K.', 'The cardamom buns are the best I have had outside Sweden. Staff were lovely even with a long queue.'],
        [4, 'Tom R.', 'Great sourdough and good coffee. A bit cramped at lunchtime but worth it.'],
        [2, 'Priya S.', 'Waited 20 minutes for a sandwich and it came without the cheese I paid for. Disappointing.'],
        [5, 'Lucas M.', 'Ordered a birthday cake at short notice and it was perfect. Thank you!'],
        [3, 'Hannah B.', 'Pastries were good but the tables were sticky and the music was very loud.'],
        [1, 'Daniel O.', 'Found a hair in my croissant and the person at the till just shrugged. Not coming back.'],
        [4, 'Mei L.', 'Lovely almond croissants. Wish you opened a little earlier for commuters.'],
        [5, 'Sofia G.', null],
        [2, 'Kwame A.', 'Prices have gone up a lot and the portions have got smaller.'],
        [5, 'Oliver P.', 'Friendly team, warm bread, and they remembered my usual order. Proper neighbourhood bakery.'],
        [3, 'Elena V.', 'Decent coffee, average cinnamon roll. Nothing wrong, nothing special.'],
        [4, 'Ravi N.', 'Gluten-free options were clearly labelled and actually tasty. Will be back.'],
    ];

    public function listLocations(User $user): array
    {
        return self::LOCATIONS;
    }

    public function listReviewsSince(Location $location, CarbonInterface $since): array
    {
        $reviews = [];
        $now = now();
        for ($day = $since->copy()->utc()->startOfDay(); $day->lte($now); $day = $day->addDay()) {
            // Each choice reads its own slice of the hash so they don't move together.
            $hash = md5($location->google_location_name.$day->format('Ymd'));
            $count = 1 + hexdec($hash[0]) % 2;
            for ($i = 0; $i < $count; $i++) {
                [$stars, $reviewer, $comment] = self::REVIEWS[(hexdec(substr($hash, 1, 2)) + $i * 5) % count(self::REVIEWS)];
                $at = $day->copy()->addHours(8 + hexdec($hash[3]) % 10 + $i)->addMinutes(hexdec(substr($hash, 4, 2)) % 60);
                if ($at->lt($since) || $at->gt($now)) {
                    continue;
                }
                $reviews[] = [
                    'name' => "{$location->google_location_name}/reviews/{$location->id}-{$day->format('Ymd')}-{$i}",
                    'reviewer' => $reviewer,
                    'star_rating' => $stars,
                    'comment' => $comment,
                    'reviewed_at' => $at,
                    // Now and then a review has already been answered on Google, like in real accounts.
                    'reply' => hexdec($hash[6]) % 6 === 0 && $i === 0 ? 'Thank you for visiting us!' : null,
                ];
            }
        }

        return array_reverse($reviews);
    }

    public function postReply(Location $location, string $reviewName, string $text): void
    {
        // Sample reviews live only in this app, so there is nothing to send.
    }
}

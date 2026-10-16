<?php

namespace App\Services\Google;

use App\Models\ApiUsageLog;
use App\Models\GoogleAccount;
use App\Models\Location;
use App\Models\User;
use App\Services\GoogleOAuth;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class RealGoogleBusiness implements GoogleBusinessProvider
{
    private const ACCOUNTS_URL = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts';

    private const INFO_URL = 'https://mybusinessbusinessinformation.googleapis.com/v1/';

    private const REVIEWS_URL = 'https://mybusiness.googleapis.com/v4/';

    private const STARS = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];

    /** Per connected Google account, well under Business Profile's per-project quota. */
    public const CALLS_PER_MINUTE = 60;

    public function __construct(private GoogleOAuth $oauth) {}

    public function listLocations(User $user): array
    {
        $account = $this->account($user);
        $accounts = $this->paginate($account, 'accounts.list', self::ACCOUNTS_URL, [], 'accounts');

        $locations = [];
        foreach ($accounts as $gbpAccount) {
            $items = $this->paginate($account, 'locations.list', self::INFO_URL.$gbpAccount['name'].'/locations', [
                'readMask' => 'name,title,storefrontAddress',
                'pageSize' => 100,
            ], 'locations');

            foreach ($items as $item) {
                // Stored as "accounts/{a}/locations/{l}" because the v4 reviews API needs both parts.
                $locations[] = [
                    'name' => $gbpAccount['name'].'/'.$item['name'],
                    'title' => $item['title'] ?? 'Untitled location',
                    'address_line' => self::address($item['storefrontAddress'] ?? null),
                ];
            }
        }

        if ($accounts) {
            $account->update(['google_account_name' => $accounts[0]['name']]);
        }

        return $locations;
    }

    public function listReviewsSince(Location $location, CarbonInterface $since): array
    {
        $account = $this->account($location->user);
        $reviews = [];
        $pageToken = null;

        do {
            $page = $this->request($account, 'reviews.list', fn (PendingRequest $http) => $http->get(
                self::REVIEWS_URL.$location->google_location_name.'/reviews',
                array_filter(['pageSize' => 50, 'orderBy' => 'updateTime desc', 'pageToken' => $pageToken]),
            ));

            foreach ($page['reviews'] ?? [] as $item) {
                if (Carbon::parse($item['updateTime'] ?? $item['createTime'])->lt($since)) {
                    return $reviews; // sorted newest first, so the rest are older too
                }
                $stars = self::STARS[$item['starRating'] ?? ''] ?? null;
                if (! $stars || empty($item['name'])) {
                    continue;
                }
                $reviews[] = [
                    'name' => $item['name'],
                    'reviewer' => ($item['reviewer']['isAnonymous'] ?? false) ? 'A Google user' : ($item['reviewer']['displayName'] ?? 'A Google user'),
                    'star_rating' => $stars,
                    'comment' => $item['comment'] ?? null,
                    'reviewed_at' => Carbon::parse($item['createTime']),
                    'reply' => $item['reviewReply']['comment'] ?? null,
                ];
            }
            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken);

        return $reviews;
    }

    public function postReply(Location $location, string $reviewName, string $text): void
    {
        $this->request($this->account($location->user), 'reviews.reply', fn (PendingRequest $http) => $http->put(
            self::REVIEWS_URL.$reviewName.'/reply',
            ['comment' => $text],
        ));
    }

    private function account(User $user): GoogleAccount
    {
        return $user->googleAccount ?? throw new GoogleApiException('No Google account is connected.');
    }

    private function paginate(GoogleAccount $account, string $action, string $url, array $query, string $key): array
    {
        $items = [];
        $pageToken = null;
        do {
            $page = $this->request($account, $action, fn (PendingRequest $http) => $http->get($url, $query + array_filter(['pageToken' => $pageToken])));
            array_push($items, ...($page[$key] ?? []));
            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken);

        return $items;
    }

    /** @param  callable(PendingRequest): Response  $send */
    private function request(GoogleAccount $account, string $action, callable $send): array
    {
        $key = 'google-api:'.$account->id;
        if (RateLimiter::tooManyAttempts($key, self::CALLS_PER_MINUTE)) {
            throw GoogleApiException::rateLimited(RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        $http = Http::withToken($this->accessToken($account))->acceptJson()->timeout(20);
        $started = hrtime(true);
        $ok = false;
        try {
            $response = $send($http);
            $ok = $response->successful();
            if (! $ok) {
                throw new GoogleApiException("Google {$action} failed with HTTP {$response->status()}.", $response->status());
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new GoogleApiException("Google {$action} could not connect.", 0, $e);
        } finally {
            ApiUsageLog::create([
                'user_id' => $account->user_id,
                'service' => 'google',
                'action' => $action,
                'ok' => $ok,
                'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            ]);
        }
    }

    private function accessToken(GoogleAccount $account): string
    {
        if ($account->access_token && $account->expires_at?->isAfter(now()->addMinute())) {
            return $account->access_token;
        }

        try {
            $tokens = $this->oauth->refresh($account->refresh_token, $account->user_id);
        } catch (RuntimeException|ConnectionException $e) {
            throw new GoogleApiException('Google access token refresh failed.', 0, $e);
        }

        $account->update([
            'access_token' => $tokens['access_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);

        return $tokens['access_token'];
    }

    private static function address(?array $address): ?string
    {
        if (! $address) {
            return null;
        }
        $parts = [...($address['addressLines'] ?? []), $address['locality'] ?? null, $address['administrativeArea'] ?? null, $address['postalCode'] ?? null];

        return implode(', ', array_filter($parts)) ?: null;
    }
}

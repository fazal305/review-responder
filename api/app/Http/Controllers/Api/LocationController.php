<?php

namespace App\Http\Controllers\Api;

use App\Enums\DraftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Models\User;
use App\Services\Google\GoogleApiException;
use App\Services\Google\GoogleBusiness;
use App\Services\ReviewSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return LocationResource::collection($this->owned($request->user()));
    }

    public function show(Location $location): LocationResource
    {
        Gate::authorize('view', $location);

        return new LocationResource($location);
    }

    public function refresh(Request $request, ReviewSync $sync): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();
        if (! GoogleBusiness::usesSample($user) && ! $user->googleAccount) {
            return response()->json(['message' => 'Sign in with Google to connect your Business Profile first.'], 409);
        }

        try {
            $sync->importLocations($user);
        } catch (GoogleApiException $e) {
            Log::warning('Location refresh failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return $e->retryAfter
                ? response()->json(['message' => 'Google is limiting requests for your account. Please try again in a minute.'], 429, ['Retry-After' => $e->retryAfter])
                : response()->json(['message' => "We couldn't reach Google just now. Please try again in a few minutes."], 502);
        }

        return LocationResource::collection($this->owned($user));
    }

    public function update(UpdateLocationRequest $request, Location $location): LocationResource
    {
        $location->update($request->validated());

        return new LocationResource($location->loadCount(self::pendingDrafts()));
    }

    /** @return Collection<int, Location> */
    private function owned(User $user): Collection
    {
        return $user->locations()->withCount(self::pendingDrafts())->orderBy('title')->get();
    }

    private static function pendingDrafts(): array
    {
        return ['drafts as pending_drafts_count' => fn ($q) => $q->where('drafts.status', DraftStatus::PendingReview)];
    }
}

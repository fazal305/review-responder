<?php

namespace App\Http\Controllers\Api;

use App\Enums\DraftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegenerateDraftRequest;
use App\Http\Requests\UpdateDraftRequest;
use App\Http\Resources\DraftResource;
use App\Models\Draft;
use App\Services\Drafting\DraftingFailed;
use App\Services\Drafting\ReplyDrafter;
use App\Services\Google\GoogleApiException;
use App\Services\Google\GoogleBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DraftController extends Controller
{
    /** Open drafts grouped by location, newest review first. */
    public function queue(Request $request): JsonResponse
    {
        $user = $request->user();
        $open = fn ($q) => $q->whereIn('drafts.status', DraftStatus::OPEN);

        $locations = $user->locations()
            ->whereHas('drafts', $open)
            ->with(['drafts' => fn ($q) => $open($q)->with('review')->orderByDesc('reviews.reviewed_at')->orderByDesc('drafts.id')])
            ->orderBy('title')
            ->get(['locations.id', 'locations.title', 'locations.timezone']);

        $isSample = GoogleBusiness::usesSample($user);

        return response()->json(['data' => $locations->map(fn ($location) => [
            'id' => $location->id,
            'title' => $location->title,
            'timezone' => $location->timezone,
            'is_sample' => $isSample,
            'drafts' => DraftResource::collection($location->drafts)->resolve($request),
        ])]);
    }

    public function update(UpdateDraftRequest $request, Draft $draft): DraftResource|JsonResponse
    {
        if (! $draft->status->isOpen()) {
            return self::closed();
        }

        $body = $request->validated('body');
        if ($body !== $draft->body) {
            // A reply written over a failed draft becomes a normal draft awaiting approval.
            $draft->update(['body' => $body, 'generator' => 'manual', 'status' => DraftStatus::PendingReview, 'error_code' => null]);
        }

        return new DraftResource($draft->load('review'));
    }

    public function approve(UpdateDraftRequest $request, Draft $draft): DraftResource|JsonResponse
    {
        // Held for the whole Google call, so a double click or a second tab can't post twice.
        $lock = Cache::lock('draft-post:'.$draft->id, 60);
        if (! $lock->get()) {
            return response()->json(['message' => 'This reply is already being posted.'], 409);
        }

        try {
            $draft->refresh()->load('review.location.user');
            if (! $draft->status->isOpen()) {
                return self::closed();
            }
            if (filled($draft->review->existing_reply)) {
                return response()->json(['message' => 'This review already has a reply on Google, so this draft was not posted. Dismiss it to clear it from your queue.'], 409);
            }

            $body = $request->validated('body') ?? $draft->body;
            if (blank($body)) {
                return response()->json(['message' => 'Write a reply before posting.', 'errors' => ['body' => ['Write a reply before posting.']]], 422);
            }

            $location = $draft->review->location;
            try {
                GoogleBusiness::for($location->user)->postReply($location, $draft->review->google_review_name, $body);
            } catch (GoogleApiException $e) {
                Log::warning('Posting a reply failed', ['draft_id' => $draft->id, 'error' => $e->getMessage()]);

                return $e->retryAfter
                    ? response()->json(['message' => 'Google is limiting requests for your account. Please try again in a minute.'], 429, ['Retry-After' => $e->retryAfter])
                    : response()->json(['message' => "We couldn't post your reply to Google. Nothing was published; please try again in a few minutes."], 502);
            }

            $changes = ['status' => DraftStatus::Published, 'published_at' => now(), 'body' => $body, 'error_code' => null];
            if ($body !== $draft->body) {
                $changes['generator'] = 'manual';
            }
            $draft->update($changes);
        } finally {
            $lock->release();
        }

        return new DraftResource($draft);
    }

    public function dismiss(Draft $draft): DraftResource|JsonResponse
    {
        Gate::authorize('update', $draft);

        $updated = Draft::whereKey($draft->id)->whereIn('status', DraftStatus::OPEN)->update(['status' => DraftStatus::Dismissed, 'updated_at' => now()]);

        return $updated ? new DraftResource($draft->refresh()) : self::closed();
    }

    /** Drafts again straight away, since the owner is waiting on the card. */
    public function regenerate(RegenerateDraftRequest $request, Draft $draft, ReplyDrafter $drafter): DraftResource|JsonResponse
    {
        if (! $draft->status->isOpen()) {
            return self::closed();
        }

        $tone = $request->validated('tone');
        $result = null;
        try {
            $result = $drafter->draft($draft->review, $tone ? RegenerateDraftRequest::TONES[$tone] : null);
            $changes = ['status' => DraftStatus::PendingReview, 'body' => $result->reply, 'generator' => $drafter->generator(), 'tone_modifier' => $tone, 'error_code' => null];
        } catch (DraftingFailed $e) {
            Log::warning('Reply draft regeneration failed', ['draft_id' => $draft->id, 'code' => $e->errorCode, 'error' => $e->getMessage()]);
            // The previous text is kept so the owner can still finish it by hand.
            $changes = ['status' => DraftStatus::DraftFailed, 'error_code' => $e->errorCode];
        }

        // Only an open draft is overwritten, in case it was posted or dismissed while drafting.
        $updated = DB::transaction(function () use ($draft, $changes, $result) {
            $updated = Draft::whereKey($draft->id)->whereIn('status', DraftStatus::OPEN)->update($changes + ['updated_at' => now()]);
            if ($updated && $result) {
                $draft->review->update(['sentiment' => $result->sentiment, 'themes' => $result->themes]);
            }

            return $updated;
        });

        return $updated ? new DraftResource($draft->refresh()->load('review')) : self::closed();
    }

    private static function closed(): JsonResponse
    {
        return response()->json(['message' => 'This draft has already been posted or dismissed, so it can no longer be changed.'], 409);
    }
}

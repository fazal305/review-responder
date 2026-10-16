<?php

namespace App\Services\Drafting;

use App\Models\Review;
use Illuminate\Support\Str;

/** Fixed star-based replies, used when no LLM key is configured. Always labelled as a template, never as model-written. */
class TemplateDrafter implements ReplyDrafter
{
    public function generator(): string
    {
        return 'template';
    }

    public function draft(Review $review, ?string $toneModifier = null): DraftResult
    {
        $name = Str::before(trim($review->reviewer_display_name), ' ');
        $greeting = $name !== '' && $review->reviewer_display_name !== 'A Google user' ? "Hi {$name}," : 'Hello,';

        $body = $greeting.' '.match (true) {
            $review->star_rating >= 4 => "thank you so much for the kind words! We're really glad you enjoyed your visit and hope to see you again soon.",
            $review->star_rating === 3 => "thank you for your feedback. We're glad parts of your visit went well, and we'll look at what we can do better next time.",
            default => "we're sorry your visit didn't meet expectations, and thank you for telling us. We'd like to put this right, so please get in touch with us directly.",
        };

        $signOff = $review->location->user->businessGuideline?->sign_off;
        if (filled($signOff)) {
            $body .= "\n\n".$signOff;
        }

        return new DraftResult($body, DraftResult::sentimentForStars($review->star_rating), []);
    }
}

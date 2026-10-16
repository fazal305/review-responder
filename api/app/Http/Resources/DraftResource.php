<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DraftResource extends JsonResource
{
    private const ERRORS = [
        'llm_unavailable' => "The drafting service couldn't be reached. Try again, or write the reply yourself.",
        'llm_invalid_response' => "The drafting service didn't return a usable reply. Try again, or write the reply yourself.",
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'body' => $this->body,
            'generator' => $this->generator,
            'tone_modifier' => $this->tone_modifier,
            'error' => $this->error_code
                ? self::ERRORS[$this->error_code] ?? "We couldn't draft a reply for this review. Try again, or write the reply yourself."
                : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'review' => $this->whenLoaded('review', fn () => [
                'reviewer_display_name' => $this->review->reviewer_display_name,
                'star_rating' => $this->review->star_rating,
                'comment' => $this->review->comment,
                'reviewed_at' => $this->review->reviewed_at->toIso8601String(),
                'sentiment' => $this->review->sentiment,
                'themes' => $this->review->themes ?? [],
            ]),
        ];
    }
}

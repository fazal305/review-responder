<?php

namespace App\Services\Drafting;

use App\Models\Review;

interface ReplyDrafter
{
    /** Stored on each draft: the model id, or "template". */
    public function generator(): string;

    /** @throws DraftingFailed */
    public function draft(Review $review, ?string $toneModifier = null): DraftResult;
}

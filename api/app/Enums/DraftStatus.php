<?php

namespace App\Enums;

enum DraftStatus: string
{
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Dismissed = 'dismissed';
    case DraftFailed = 'draft_failed';

    /** Statuses still waiting on the owner; published and dismissed drafts are final. */
    public const OPEN = [self::PendingReview, self::DraftFailed];

    public function isOpen(): bool
    {
        return in_array($this, self::OPEN, true);
    }
}

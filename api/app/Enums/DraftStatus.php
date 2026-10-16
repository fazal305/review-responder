<?php

namespace App\Enums;

enum DraftStatus: string
{
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Dismissed = 'dismissed';
    case DraftFailed = 'draft_failed';
}

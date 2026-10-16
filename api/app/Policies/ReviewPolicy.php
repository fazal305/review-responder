<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReviewPolicy
{
    public function view(User $user, Review $review): Response
    {
        return $review->location->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

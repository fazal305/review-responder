<?php

namespace App\Policies;

use App\Models\Draft;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DraftPolicy
{
    public function view(User $user, Draft $draft): Response
    {
        return $this->owns($user, $draft);
    }

    public function update(User $user, Draft $draft): Response
    {
        return $this->owns($user, $draft);
    }

    private function owns(User $user, Draft $draft): Response
    {
        return $draft->review->location->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

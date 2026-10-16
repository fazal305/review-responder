<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LocationPolicy
{
    public function view(User $user, Location $location): Response
    {
        return $this->owns($user, $location);
    }

    public function update(User $user, Location $location): Response
    {
        return $this->owns($user, $location);
    }

    // Other users' records answer 404, so their existence isn't revealed.
    private function owns(User $user, Location $location): Response
    {
        return $location->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

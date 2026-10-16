<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Support\Facades\Gate;

class LocationController extends Controller
{
    public function show(Location $location): LocationResource
    {
        Gate::authorize('view', $location);

        return new LocationResource($location);
    }
}

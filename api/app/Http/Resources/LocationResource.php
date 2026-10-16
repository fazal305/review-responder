<?php

namespace App\Http\Resources;

use App\Services\Google\GoogleBusiness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'address_line' => $this->address_line,
            'timezone' => $this->timezone,
            'monitoring' => $this->monitoring,
            'last_synced_on' => $this->last_synced_on?->toDateString(),
            'pending_drafts' => $this->whenCounted('pending_drafts'),
            'is_sample' => GoogleBusiness::usesSample($request->user()),
        ];
    }
}

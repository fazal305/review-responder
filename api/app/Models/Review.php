<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'google_review_name', 'reviewer_display_name', 'star_rating', 'comment',
    'reviewed_at', 'sentiment', 'themes', 'existing_reply',
])]
class Review extends Model
{
    protected function casts(): array
    {
        return [
            'star_rating' => 'integer',
            'reviewed_at' => 'datetime',
            'themes' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(Draft::class);
    }
}

<?php

namespace App\Models;

use App\Enums\DraftStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['status', 'body', 'tone_modifier', 'generator', 'error_code', 'published_at'])]
class Draft extends Model
{
    protected function casts(): array
    {
        return [
            'status' => DraftStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}

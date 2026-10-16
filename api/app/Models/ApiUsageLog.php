<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'service', 'action', 'ok', 'duration_ms'])]
class ApiUsageLog extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'duration_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

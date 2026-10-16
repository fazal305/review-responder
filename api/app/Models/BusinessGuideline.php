<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tone', 'strictness', 'sign_off', 'extra_instructions', 'banned_phrases'])]
class BusinessGuideline extends Model
{
    // Mirror the column defaults so an unsaved instance works as "no guidelines set yet".
    protected $attributes = ['tone' => 'friendly', 'strictness' => 'balanced'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

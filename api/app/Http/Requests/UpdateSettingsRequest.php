<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    // Must match the enum columns on business_guidelines.
    public const TONES = ['friendly', 'professional', 'formal', 'casual'];

    public const STRICTNESS = ['relaxed', 'balanced', 'strict'];

    public function rules(): array
    {
        return [
            'tone' => ['required', 'string', Rule::in(self::TONES)],
            'strictness' => ['required', 'string', Rule::in(self::STRICTNESS)],
            'sign_off' => ['nullable', 'string', 'max:120'],
            'extra_instructions' => ['nullable', 'string', 'max:1000'],
            'banned_phrases' => ['nullable', 'string', 'max:1000'],
            'notify_daily' => ['required', 'boolean'],
            'notify_hour' => ['required', 'integer', 'between:0,23'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }

    public function messages(): array
    {
        return [
            'tone.in' => 'Choose one of the listed tones.',
            'strictness.in' => 'Choose one of the listed options.',
            'sign_off.max' => 'Keep the sign-off to 120 characters or fewer.',
            'extra_instructions.max' => 'Keep these notes to 1,000 characters or fewer.',
            'banned_phrases.max' => 'Keep the phrase list to 1,000 characters or fewer.',
            'notify_hour.*' => 'Choose an hour between 0 and 23.',
            'timezone.*' => 'Choose a timezone from the list.',
        ];
    }
}

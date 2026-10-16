<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RegenerateDraftRequest extends FormRequest
{
    /** Tone keys the owner can pick, and the instruction each one gives the drafter. */
    public const TONES = [
        'more_apologetic' => 'Make the reply more apologetic',
        'more_casual' => 'Make the reply more casual and friendly',
        'more_formal' => 'Make the reply more formal',
        'shorter' => 'Make the reply noticeably shorter',
    ];

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('draft'));
    }

    public function rules(): array
    {
        return [
            'tone' => ['nullable', 'string', Rule::in(array_keys(self::TONES))],
        ];
    }

    public function messages(): array
    {
        return ['tone.in' => 'Choose one of the listed tones.'];
    }
}

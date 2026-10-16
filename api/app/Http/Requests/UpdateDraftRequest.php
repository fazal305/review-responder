<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateDraftRequest extends FormRequest
{
    public const MAX_BODY = 4000;

    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('draft'));
    }

    public function rules(): array
    {
        $rules = ['required', 'string', 'max:'.self::MAX_BODY];

        // Approving may send the text exactly as shown on screen; editing must send it.
        return ['body' => $this->routeIs('drafts.approve') ? ['sometimes', ...$rules] : $rules];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Write a reply before saving.',
            'body.max' => 'Replies can be at most '.self::MAX_BODY.' characters.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLocationRequest extends FormRequest
{
    // Authorizes before validating, so another owner's location is a 404 rather than a 422.
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('location'));
    }

    public function rules(): array
    {
        return [
            'monitoring' => ['sometimes', 'boolean'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
        ];
    }
}

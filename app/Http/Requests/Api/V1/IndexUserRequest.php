<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage:users') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** Case-insensitive match on name or email. */
            'search'   => ['sometimes', 'string', 'max:100'],
            /** Page size (default 15). */
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page'     => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
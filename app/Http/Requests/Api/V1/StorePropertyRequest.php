<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage:property') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['currency', 'country'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtoupper(trim($this->input($field)))]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'beds24_id'      => ['nullable', 'string', 'max:255', Rule::unique('properties', 'beds24_id')],
            'currency'       => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'timezone'       => ['required', 'timezone:all'],
            'address'        => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:255'],
            'state'          => ['nullable', 'string', 'max:255'],
            'zip'            => ['nullable', 'string', 'max:255'],
            'country'        => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180', 'decimal:0,7'],
            'check_in_time'  => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }
}

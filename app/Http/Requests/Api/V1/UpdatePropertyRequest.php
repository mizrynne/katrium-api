<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Property;

class UpdatePropertyRequest extends FormRequest
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
        /** @var Property $property */
        $property = $this->route('property');
 
        return [
            'name'           => ['sometimes', 'required', 'string', 'max:255'],
            'beds24_id'      => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('properties', 'beds24_id')->ignore($property->getKey())],
            'currency'       => ['sometimes', 'required', 'string', 'regex:/^[A-Z]{3}$/'],
            'timezone'       => ['sometimes', 'required', 'timezone:all'],
            'address'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'state'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'zip'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'country'        => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'latitude'       => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude'      => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'decimal:0,7'],
            'check_in_time'  => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }
}

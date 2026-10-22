<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Rules\IsBusinessPartner;

class StoreUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage:units') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id'    => ['required', 'integer', Rule::exists('properties', 'id')->where('is_active', true)],
            'name'           => ['required', 'string', 'max:255'],
            'parent_id'      => ['nullable', 'integer', Rule::exists('units', 'id')->where('property_id', $this->input('property_id'))],
            'beds24_room_id' => ['nullable', 'string', 'max:255'],
            'qty'            => ['sometimes', 'integer', 'min:1'],
            'capacity'       => ['sometimes', 'integer', 'min:1'],
            'max_adults'     => ['nullable', 'integer', 'min:1'],
            'max_children'   => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['sometimes', 'boolean'],
            'partner_id'     => ['nullable', 'integer', new IsBusinessPartner()],
        ];
    }
}

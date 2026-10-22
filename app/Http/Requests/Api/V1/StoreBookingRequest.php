<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('ota_source'))) {
            $this->merge(['ota_source' => strtolower(trim($this->input('ota_source')))]);
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
            'unit_id' => [
                'required', 'integer',
                Rule::exists('units', 'id')->where(fn ($q) => $q->where('is_active', true)->whereNotNull('beds24_room_id')),
            ],
 
            'guest_id' => ['required_without:guest', 'nullable', 'integer', 'exists:guests,id'],
            'guest' => ['required_without:guest_id', 'nullable', 'array'],
            'guest.first_name' => ['required_with:guest', 'string', 'max:100'],
            'guest.last_name' => ['nullable', 'string', 'max:100'],
            'guest.email' => ['nullable', 'email:rfc', 'max:190'],
            'guest.phone' => ['nullable', 'string', 'max:40'],
            'guest.mobile' => ['nullable', 'string', 'max:40'],
            'guest.country' => ['nullable', 'string', 'size:2', 'alpha'],
 
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
 
            'ota_source' => ['sometimes', Rule::in(config('beds24.manual_ota_sources'))],
            'status' => ['sometimes', Rule::in(['confirmed', 'pending'])],
 
            'num_adults' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'num_children' => ['sometimes', 'integer', 'min:0', 'max:99'],
 
            'total_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'amount_paid' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'deposit' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'unit_id.exists' => 'The unit does not exist, is inactive, or is not linked to Beds24.',
            'check_out.after' => 'check_out must be after check_in.',
            'ota_source.in' => 'ota_source must be one of: '.implode(', ', config('beds24.manual_ota_sources')).'.',
            'guest_id.required_without' => 'Provide either guest_id or a guest object.',
            'guest.required_without' => 'Provide either guest_id or a guest object.',
        ];
    }
}

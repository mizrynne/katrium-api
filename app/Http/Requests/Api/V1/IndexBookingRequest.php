<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['sometimes', 'integer'],
            'property_id' => ['sometimes', 'integer'],
            'guest_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'array'],
            'status.*' => ['string', Rule::in(['confirmed', 'pending', 'canceled', 'altered', 'noshow'])],
            'ota_source' => ['sometimes', 'string', 'max:50'],
            'sync_status' => ['sometimes', Rule::in(['pending', 'synced', 'failed', 'not_applicable'])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'search' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['check_in', '-check_in', 'created_at', '-created_at', 'total_price', '-total_price'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }
}

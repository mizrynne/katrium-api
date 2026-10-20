<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
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
            'unit_id' => [
                'sometimes', 'integer',
                Rule::exists('units', 'id')->where(fn ($q) => $q->where('is_active', true)->whereNotNull('beds24_room_id')),
            ],
 
            'guest_id' => ['sometimes', 'integer', 'exists:guests,id'],
            'guest' => ['sometimes', 'array'],
            'guest.first_name' => ['required_with:guest', 'string', 'max:100'],
            'guest.last_name' => ['nullable', 'string', 'max:100'],
            'guest.email' => ['nullable', 'email:rfc', 'max:190'],
            'guest.phone' => ['nullable', 'string', 'max:40'],
            'guest.mobile' => ['nullable', 'string', 'max:40'],
            'guest.country' => ['nullable', 'string', 'size:2', 'alpha'],
 
            'check_in' => ['sometimes', 'date'],
            'check_out' => ['sometimes', 'date'],
 
            'status' => ['sometimes', Rule::in(['confirmed', 'pending', 'noshow'])],
 
            'num_adults' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'num_children' => ['sometimes', 'integer', 'min:0', 'max:99'],
 
            'total_price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'amount_paid' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'deposit' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
 
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->hasAny(['check_in', 'check_out']) || $validator->errors()->hasAny(['check_in', 'check_out'])) {
                    return;
                }
 
                /** @var Booking $booking */
                $booking = $this->route('booking');
 
                $checkIn = Carbon::parse($this->input('check_in', $booking->check_in));
                $checkOut = Carbon::parse($this->input('check_out', $booking->check_out));
 
                if ($checkOut->lte($checkIn)) {
                    $validator->errors()->add('check_out', 'check_out must be after check_in.');
                }
            },
        ];
    }
 
    public function messages(): array
    {
        return [
            'unit_id.exists' => 'The unit does not exist, is inactive, or is not linked to Beds24.',
        ];
    }
}

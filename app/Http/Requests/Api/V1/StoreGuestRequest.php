<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\GuestFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGuestRequest extends FormRequest
{
    use GuestFieldRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage:guests') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->guestRules();
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $identifiable = collect(['first_name', 'last_name', 'email', 'phone', 'mobile'])
                    ->contains(fn (string $field) => filled($this->input($field)));

                if (! $identifiable) {
                    $validator->errors()->add('first_name', 'Provide at least a name, an email address, or a phone number.');
                }
            },
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

use App\Enums\GuestTag;
use App\Services\GuestService;
use App\Support\ContactNormalizer;
use Closure;
use Illuminate\Validation\Rule;

trait GuestFieldRules
{
    /**
     * @param  int|null  $ignoreGuestId  the guest being updated (excluded from uniqueness checks)
     * @param  bool  $partial  PATCH/PUT semantics: every field becomes `sometimes`
     */
    protected function guestRules(?int $ignoreGuestId = null, bool $partial = false): array
    {
        $guests = app(GuestService::class);

        // Uniqueness is case-insensitive and format-insensitive, matching the DB indexes
        // and the dedupe service. NULLs skip these closures entirely (walk-ins / OTA guests).
        $uniqueEmail = function (string $attribute, mixed $value, Closure $fail) use ($guests, $ignoreGuestId): void {
            if ($guests->emailTaken((string) $value, $ignoreGuestId)) {
                $fail('A guest profile with this email address already exists.');
            }
        };

        $validUniquePhone = function (string $attribute, mixed $value, Closure $fail) use ($guests, $ignoreGuestId): void {
            if (ContactNormalizer::phone((string) $value) === null) {
                $fail('The :attribute must be a valid phone number (7-15 digits).');

                return;
            }

            if ($guests->phoneTaken((string) $value, $ignoreGuestId)) {
                $fail('A guest profile with this phone number already exists.');
            }
        };

        $rules = [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255', $uniqueEmail],
            'phone' => ['nullable', 'string', 'max:32', $validUniquePhone],
            'mobile' => ['nullable', 'string', 'max:32', $validUniquePhone],
            'country' => ['nullable', 'alpha', 'size:2'],
            'tag' => ['sometimes', Rule::enum(GuestTag::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];

        if ($partial) {
            foreach ($rules as $field => $fieldRules) {
                if (! in_array('sometimes', $fieldRules, true)) {
                    array_unshift($rules[$field], 'sometimes');
                }
            }
        }

        return $rules;
    }
}

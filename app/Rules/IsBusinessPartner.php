<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Enums\RoleName;
use App\Models\User;

class IsBusinessPartner implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isPartner = User::query()
            ->role(RoleName::PARTNER->value)
            ->whereKey($value)
            ->exists();
 
        if (! $isPartner) {
            $fail('The selected :attribute must reference an existing user with the Business Partner role.');
        }
    }
}

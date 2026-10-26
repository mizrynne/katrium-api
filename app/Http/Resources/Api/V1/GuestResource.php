<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Two tiers of exposure:
 *  - everyone who can read guests (manage:guests OR view:bookings): identity basics only
 *  - manage:guests: contact data, tier, notes, stats and incident history
 * Read-only investors (view:bookings) must never receive PII or blacklist data.
 *
 * @mixin \App\Models\Guest
 */
class GuestResource extends JsonResource
{
    /**
     * Keys marked optional (`?:`) are only present for callers with `manage:guests`.
     *
     * @return array{
     *     id: int,
     *     first_name: string|null,
     *     last_name: string|null,
     *     full_name: string|null,
     *     country: string|null,
     *     total_stays: int,
     *     is_returning_guest: bool,
     *     email?: string|null,
     *     phone?: string|null,
     *     mobile?: string|null,
     *     tag?: 'regular'|'vip'|'blacklisted',
     *     notes?: string|null,
     *     bookings_count?: int,
     *     violations_count?: int,
     *     lifetime_value?: float,
     *     violations?: GuestViolationResource[],
     *     created_at?: string|null,
     *     updated_at?: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'country' => $this->country,
            'total_stays' => $this->total_stays,
            'is_returning_guest' => $this->total_stays > 1,
        ];

        if (! $request->user()?->can('manage:guests')) {
            return $data;
        }

        return $data + [
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'tag' => $this->tag->value,
            'notes' => $this->notes,
            'bookings_count' => $this->whenCounted('bookings'),
            'violations_count' => $this->whenCounted('violations'),
            'lifetime_value' => $this->whenHas('lifetime_value', fn ($value) => round((float) $value, 2)),
            'violations' => GuestViolationResource::collection($this->whenLoaded('violations')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

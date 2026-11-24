<?php

namespace App\Models;

use App\Support\PartnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'unit_id', 'guest_id', 'beds24_booking_id', 'api_reference', 'ota_source',
        'sync_status', 'sync_error', 'last_synced_at', 'channel_data', 'channel_confirmation_code',
        'commission_amount', 'check_in', 'check_out', 'num_adults', 'num_children',
        'status', 'beds24_status', 'total_price', 'amount_paid', 'deposit',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'last_synced_at' => 'datetime',
            'channel_data' => 'array',
            'num_adults' => 'integer',
            'num_children' => 'integer',
            'beds24_status' => 'integer',
            'total_price' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'deposit' => 'decimal:2',
            'commission_amount' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * B2: WHICH rows a user may see is decided by role (PartnerScope), exactly like units and properties.
     * WHAT they may do, and which fields they receive, stays a matter of permissions.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! PartnerScope::applies($user)) {
            return $query;
        }

        return $query->whereHas('unit', fn (Builder $q) => $q->where('partner_id', $user->getKey()));
    }
}
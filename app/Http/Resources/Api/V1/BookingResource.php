<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canManage = (bool) $request->user()?->can('manage:bookings');
 
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
                'property_id' => $this->unit->property_id,
            ] : null),
 
            'guest_id' => $this->guest_id,
            'guest' => $this->whenLoaded('guest', fn () => $this->guest ? [
                'id' => $this->guest->id,
                'first_name' => $this->guest->first_name,
                'last_name' => $this->guest->last_name,
                'full_name' => trim(($this->guest->first_name ?? '').' '.($this->guest->last_name ?? '')),
                'tag' => $this->guest->tag,
                'total_stays' => (int) $this->guest->total_stays,
                'email' => $canManage ? $this->guest->email : null,
                'phone' => $canManage ? ($this->guest->mobile ?: $this->guest->phone) : null,
            ] : null),
 
            'ota_source' => $this->ota_source,
            'status' => $this->status,
 
            'check_in' => $this->check_in?->format('Y-m-d\TH:i:s'),
            'check_out' => $this->check_out?->format('Y-m-d\TH:i:s'),
            'nights' => $this->check_in && $this->check_out
                ? (int) round(abs($this->check_in->copy()->startOfDay()->diffInDays($this->check_out->copy()->startOfDay())))
                : null,
 
            'num_adults' => $this->num_adults,
            'num_children' => $this->num_children,
 
            'total_price' => (float) $this->total_price,
            'amount_paid' => (float) $this->amount_paid,
            'deposit' => (float) $this->deposit,
            'balance_due' => round((float) $this->total_price - (float) $this->amount_paid, 2),
            'commission_amount' => (float) $this->commission_amount,
 
            'channel_confirmation_code' => $canManage ? $this->channel_confirmation_code : null,
 
            'sync' => [
                'status' => $this->sync_status,
                'last_synced_at' => $this->last_synced_at?->toIso8601String(),
                'beds24_booking_id' => $canManage ? $this->beds24_booking_id : null,
                'error' => $canManage ? $this->sync_error : null,
            ],
 
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

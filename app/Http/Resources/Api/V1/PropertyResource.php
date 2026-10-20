<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\PartnerScope;

class PropertyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $internal = ! PartnerScope::applies($request->user());
 
        return [
            'id'             => $this->id,
            'beds24_id'      => $this->when($internal, $this->beds24_id),
            'name'           => $this->name,
            'currency'       => $this->currency,
            'timezone'       => $this->timezone,
            'address'        => $this->address,
            'city'           => $this->city,
            'state'          => $this->state,
            'zip'            => $this->zip,
            'country'        => $this->country,
            'latitude'       => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude'      => $this->longitude !== null ? (float) $this->longitude : null,
            'check_in_time'  => $this->check_in_time ? substr($this->check_in_time, 0, 5) : null,
            'check_out_time' => $this->check_out_time ? substr($this->check_out_time, 0, 5) : null,
            'is_active'      => $this->is_active,
            'units_count'    => $this->whenCounted('units'),
            'units'          => UnitResource::collection($this->whenLoaded('units')),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}

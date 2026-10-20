<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Support\PartnerScope;

class UnitResource extends JsonResource
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
            'property_id'    => $this->property_id,
            'parent_id'      => $this->parent_id,
            'name'           => $this->name,
            'beds24_room_id' => $this->when($internal, $this->beds24_room_id),
            'qty'            => $this->qty,
            'capacity'       => $this->capacity,
            'max_adults'     => $this->max_adults,
            'max_children'   => $this->max_children,
            'is_active'      => $this->is_active,
            'partner_id'     => $this->partner_id,
            'partner'        => $this->whenLoaded('partner', fn () => [
                'id'    => $this->partner->id,
                'name'  => $this->partner->name,
                'email' => $this->partner->email,
            ]),
            'property'       => new PropertyResource($this->whenLoaded('property')),
            'parent'         => new UnitResource($this->whenLoaded('parent')),
            'children'       => UnitResource::collection($this->whenLoaded('children')),
            'created_at'     => $this->created_at?->toIso8601String(),
            'updated_at'     => $this->updated_at?->toIso8601String(),
        ];
    }
}

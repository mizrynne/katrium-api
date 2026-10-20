<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessCatalogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'roles' => $this->resource['roles']->map(fn ($role) => [
                'name'        => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            ])->values()->all(),
            'permissions' => $this->resource['permissions']->pluck('name')->values()->all(),
        ];
    }
}

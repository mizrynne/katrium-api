<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'avatar'             => $this->avatar,
            'roles'              => $this->getRoleNames()->values()->all(),
            'role_permissions'   => $this->names($this->getPermissionsViaRoles()),
            'direct_permissions' => $this->names($this->permissions),
            'permissions'        => $this->names($this->getAllPermissions()),
            'has_signed_in'      => $this->google_id !== null,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<string> */
    private function names($permissions): array
    {
        return $permissions->pluck('name')->unique()->sort()->values()->all();
    }
}

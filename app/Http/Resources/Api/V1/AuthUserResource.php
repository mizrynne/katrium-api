<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class AuthUserResource extends JsonResource
{
    /** Flat shape, no `data` envelope, same as today. */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        /** @var list<string> $roles */
        $roles = $this->getRoleNames()->values()->all();
        /** @var list<string> $permissions */
        $permissions = $this->getAllPermissions()->pluck('name')->sort()->values()->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \stdClass */
class AccessCatalogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{roles: list<array{name: string, permissions: list<string>}>, permissions: list<string>}
     */
    public function toArray(Request $request): array
    {
        /** @var list<array{name: string, permissions: list<string>}> $roles */
        $roles = Role::with('permissions')->where('guard_name', 'web')->orderBy('name')->get()
            ->map(fn ($r) => ['name' => $r->name, 'permissions' => $r->permissions->pluck('name')->sort()->values()->all()])
            ->values()->all();

        /** @var list<string> $permissions */
        $permissions = Permission::where('guard_name', 'web')->orderBy('name')->pluck('name')->all();

        return response()->json(['data' => ['roles' => $roles, 'permissions' => $permissions]]);
    }
}

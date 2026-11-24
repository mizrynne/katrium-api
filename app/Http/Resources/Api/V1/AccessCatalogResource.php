<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @property array{roles: Collection<int, Role>, permissions: Collection<int, Permission>} $resource
 */
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
        $roles = $this->resource['roles']
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            ])
            ->values()
            ->all();

        /** @var list<string> $permissions */
        $permissions = $this->resource['permissions']
            ->pluck('name')
            ->values()
            ->all();

        return [
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }
}
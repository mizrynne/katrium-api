<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AccessCatalogResource;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessCatalogController extends Controller
{
    /**
     * Get the access catalog
     *
     * All assignable roles (with their default permissions) and every permission slug.
     * Used by the user-management form to render role/permission pickers.
     */
    public function __invoke(): AccessCatalogResource
    {
        return new AccessCatalogResource([
            'roles'       => Role::with('permissions')->where('guard_name', 'web')->orderBy('name')->get(),
            'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get(),
        ]);
    }
}
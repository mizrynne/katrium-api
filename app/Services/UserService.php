<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    private const ACCESS_RELATIONS = ['roles.permissions', 'permissions'];

    public function paginate(?string $search, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->with(self::ACCESS_RELATIONS)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name'  => $data['name'],
                'email' => $data['email'],
            ]);

            $user->assignRole($data['role']);

            if (! empty($data['permissions'])) {
                $user->givePermissionTo($data['permissions']);
            }

            return $user->load(self::ACCESS_RELATIONS);
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        if ($actor->is($user)) {
            if (isset($data['role']) && ! $user->hasRole($data['role'])) {
                throw ValidationException::withMessages([
                    'role' => 'You cannot change your own role.',
                ]);
            }

            if (array_key_exists('permissions', $data) && $this->permissionsDiffer($user, $data['permissions'])) {
                throw ValidationException::withMessages([
                    'permissions' => 'You cannot change your own permission overrides.',
                ]);
            }
        }

        return DB::transaction(function () use ($user, $data) {
            $role           = $data['role'] ?? null;
            $syncPermission = array_key_exists('permissions', $data);
            $permissions    = $data['permissions'] ?? [];
            unset($data['role'], $data['permissions']);

            if ($data !== []) {
                $user->update($data);
            }

            if ($role !== null) {
                $user->syncRoles([$role]);
            }

            if ($syncPermission) {
                $user->syncPermissions($permissions);
            }
            
            return $user->fresh(self::ACCESS_RELATIONS);
        });
    }

    public function delete(User $user, User $actor): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own active user account.',
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });
    }

    private function permissionsDiffer(User $user, array $requested): bool
    {
        $current = $user->permissions->pluck('name')->sort()->values()->all();
        sort($requested);

        return $current !== array_values($requested);
    }
}
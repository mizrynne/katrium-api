<?php

namespace App\Services;

use App\Models\GuestViolation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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

    /**
     * @throws ValidationException  when deleting yourself (422)
     * @throws ConflictHttpException when other records still reference the user (409)
     */
    public function delete(User $user, User $actor): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own active user account.',
            ]);
        }

        if (GuestViolation::query()->where('recorded_by_user_id', $user->getKey())->exists()) {
            throw new ConflictHttpException(
                'This user has recorded guest violations, so the account must be kept for the audit trail.'
            );
        }

        try {
            DB::transaction(function () use ($user) {
                $user->tokens()->delete();
                $user->delete();
            });
        } catch (QueryException $e) {
            // 23503 = foreign_key_violation: a reference we did not pre-check (or a violation recorded in a race).
            if (($e->errorInfo[0] ?? null) === '23503') {
                throw new ConflictHttpException('This user is still referenced by other records and cannot be deleted.');
            }

            throw $e;
        }
    }

    private function permissionsDiffer(User $user, array $requested): bool
    {
        $current = $user->permissions->pluck('name')->sort()->values()->all();
        sort($requested);

        return $current !== array_values($requested);
    }
}
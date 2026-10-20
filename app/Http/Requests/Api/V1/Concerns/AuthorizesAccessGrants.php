<?php

namespace App\Http\Requests\Api\V1\Concerns;

trait AuthorizesAccessGrants
{
    private const SUPERADMIN_ONLY_PERMISSIONS = ['manage:users'];

    /**
     * authorize() runs before validation rules, so input here is untrusted and may be malformed.
     *
     * @return list<string>
     */
    protected function requestedPermissions(): array
    {
        $raw = $this->input('permissions');

        return is_array($raw) ? array_values(array_filter($raw, 'is_string')) : [];
    }

    /**
     * @param  mixed         $role     Requested role (untrusted).
     * @param  list<string>  $changed  Permissions being added or removed.
     */
    protected function actorMayGrant(mixed $role, array $changed): bool
    {
        $actor = $this->user();

        if ($actor->hasRole('Superadmin')) {
            return true;
        }

        if ($role === 'Superadmin') {
            return false;
        }

        foreach ($changed as $permission) {
            if (in_array($permission, self::SUPERADMIN_ONLY_PERMISSIONS, true)) {
                return false;
            }

            if (! $actor->can($permission)) {
                return false;
            }
        }

        return true;
    }
}
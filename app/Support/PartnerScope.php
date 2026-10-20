<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Tenant-scoping decision point (RLS-readiness).
 *
 * DENY BY DEFAULT: a user is tenant-scoped (limited to units where
 * units.partner_id = user.id) unless they hold a staff role. This also
 * covers Partners who were handed direct PBAC permission overrides.
 */
final class PartnerScope
{
    public static function applies(User $user): bool
    {
        return ! $user->hasAnyRole([
            RoleName::SUPERADMIN->value,
            RoleName::MANAGER->value,
        ]);
    }
}
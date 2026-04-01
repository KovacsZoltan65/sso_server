<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogPage;
use App\Support\Permissions\AuditLogPermissions;

class AuditLogPolicy
{
    /**
     * Determine whether the user may view the append-only audit log page.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(AuditLogPermissions::VIEW_ANY);
    }

    public function view(User $user, AuditLog|AuditLogPage $auditLog): bool
    {
        return $user->can(AuditLogPermissions::VIEW)
            || $user->can(AuditLogPermissions::VIEW_ANY);
    }
}

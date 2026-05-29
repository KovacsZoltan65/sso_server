<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions\AuditLogPermissions;
use Spatie\Activitylog\Models\Activity;

class AuditLogPolicy
{
    /**
     * Determine whether the user may view the append-only audit log page.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(AuditLogPermissions::VIEW_ANY);
    }

    public function view(User $user, Activity $auditLog): bool
    {
        return $user->can(AuditLogPermissions::VIEW);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, Activity $auditLog): bool
    {
        return false;
    }
}

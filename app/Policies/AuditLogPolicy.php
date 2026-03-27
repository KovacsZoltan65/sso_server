<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AuditLogPage;
use App\Support\Permissions\AuditLogPermissions;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AuditLogPermissions::VIEW_ANY);
    }
}

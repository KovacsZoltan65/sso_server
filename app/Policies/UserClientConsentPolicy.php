<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserClientConsent;
use App\Support\Permissions\RememberedConsentPermissions;

class UserClientConsentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(RememberedConsentPermissions::VIEW_ANY);
    }

    public function view(User $user, UserClientConsent $consent): bool
    {
        return $user->can(RememberedConsentPermissions::VIEW)
            || $user->can(RememberedConsentPermissions::VIEW_ANY);
    }

    public function revoke(User $user, UserClientConsent $consent): bool
    {
        return $user->can(RememberedConsentPermissions::REVOKE);
    }
}

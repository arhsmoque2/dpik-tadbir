<?php

namespace App\Policies;

use App\Models\AllowedRegistrationEmail;
use App\Models\User;

class AllowedRegistrationEmailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, AllowedRegistrationEmail $email): bool
    {
        return $user->isSuperAdmin();
    }
}

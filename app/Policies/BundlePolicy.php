<?php

namespace App\Policies;

use App\Models\Bundle;
use App\Models\User;

class BundlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Bundle $bundle): bool
    {
        return $user->id === $bundle->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Bundle $bundle): bool
    {
        return $user->id === $bundle->user_id;
    }

    public function delete(User $user, Bundle $bundle): bool
    {
        return $user->id === $bundle->user_id;
    }
}

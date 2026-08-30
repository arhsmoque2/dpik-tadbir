<?php

namespace App\Policies;

use App\Models\ExecutivePreset;
use App\Models\User;

class ExecutivePresetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExecutivePreset $preset): bool
    {
        return $preset->user_id === null || $preset->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ExecutivePreset $preset): bool
    {
        return $preset->user_id === $user->id || $user->isSuperAdmin();
    }

    public function delete(User $user, ExecutivePreset $preset): bool
    {
        return $preset->user_id === $user->id || ($preset->user_id === null && $user->isSuperAdmin());
    }
}

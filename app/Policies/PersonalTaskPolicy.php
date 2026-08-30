<?php

namespace App\Policies;

use App\Models\PersonalTask;
use App\Models\User;

class PersonalTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PersonalTask $task): bool
    {
        return $user->id === $task->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PersonalTask $task): bool
    {
        return $user->id === $task->user_id;
    }

    public function delete(User $user, PersonalTask $task): bool
    {
        return $user->id === $task->user_id;
    }
}

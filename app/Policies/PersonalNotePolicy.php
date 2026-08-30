<?php

namespace App\Policies;

use App\Models\PersonalNote;
use App\Models\User;

class PersonalNotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PersonalNote $note): bool
    {
        return $user->id === $note->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PersonalNote $note): bool
    {
        return $user->id === $note->user_id;
    }

    public function delete(User $user, PersonalNote $note): bool
    {
        return $user->id === $note->user_id;
    }
}

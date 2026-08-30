<?php

namespace App\Policies;

use App\Models\ChatSession;
use App\Models\User;

class ChatSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChatSession $session): bool
    {
        return $user->id === $session->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ChatSession $session): bool
    {
        return $user->id === $session->user_id;
    }

    public function delete(User $user, ChatSession $session): bool
    {
        return $user->id === $session->user_id;
    }
}

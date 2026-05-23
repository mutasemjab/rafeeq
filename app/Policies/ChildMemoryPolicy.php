<?php

namespace App\Policies;

use App\Models\ChildMemory;
use App\Models\User;

class ChildMemoryPolicy
{
    public function view(User $user, ChildMemory $memory): bool
    {
        return $memory->child->user_id === $user->id;
    }

    public function update(User $user, ChildMemory $memory): bool
    {
        return $memory->child->user_id === $user->id;
    }

    public function delete(User $user, ChildMemory $memory): bool
    {
        return $memory->child->user_id === $user->id;
    }
}

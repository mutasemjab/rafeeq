<?php

namespace App\Policies;

use App\Models\ChatAttachment;
use App\Models\User;

class ChatAttachmentPolicy
{
    public function view(User $user, ChatAttachment $attachment): bool
    {
        return $attachment->user_id === $user->id;
    }

    public function delete(User $user, ChatAttachment $attachment): bool
    {
        return $attachment->user_id === $user->id;
    }
}

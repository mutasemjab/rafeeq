<?php

namespace App\Policies;

use App\Models\SpecialistReview;
use App\Models\User;

class SpecialistReviewPolicy
{
    public function update(User $user, SpecialistReview $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(User $user, SpecialistReview $review): bool
    {
        return $review->user_id === $user->id;
    }
}

<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $appointment->user_id === $user->id;
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $appointment->user_id === $user->id
            && in_array($appointment->status, ['pending_payment', 'confirmed', 'upcoming'], true);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $appointment->user_id === $user->id
            && in_array($appointment->status, ['pending_payment', 'confirmed', 'upcoming'], true);
    }
}

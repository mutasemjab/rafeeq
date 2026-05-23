<?php

namespace Database\Factories;

use App\Models\Specialist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'specialist_id'    => Specialist::factory(),
            'appointment_type' => 'general_consultation',
            'booking_reference'=> strtoupper(Str::random(10)),
            'scheduled_date'   => $this->faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'start_time'       => '10:00',
            'end_time'         => '10:30',
            'timezone'         => 'UTC',
            'status'           => 'confirmed',
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SpecialistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'             => $this->faker->name(),
            'title'            => $this->faker->jobTitle(),
            'bio'              => $this->faker->paragraph(),
            'specializations'  => json_encode(['autism', 'adhd']),
            'languages'        => json_encode(['en', 'ar']),
            'session_fee'      => $this->faker->randomFloat(2, 20, 150),
            'currency'         => 'USD',
            'is_active'        => true,
            'is_available'     => true,
            'rating_avg'       => 0,
            'reviews_count'    => 0,
        ];
    }
}

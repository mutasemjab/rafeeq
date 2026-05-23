<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChildFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'name'          => $this->faker->firstName(),
            'date_of_birth' => $this->faker->dateTimeBetween('-10 years', '-1 year')->format('Y-m-d'),
            'gender'        => $this->faker->randomElement(['male', 'female']),
            'diagnosis'     => $this->faker->optional()->word(),
        ];
    }
}

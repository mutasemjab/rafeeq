<?php

namespace Database\Factories;

use App\Models\Child;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChildMemoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'child_id'   => Child::factory(),
            'key'        => $this->faker->word(),
            'content'    => $this->faker->sentence(),
            'confidence' => $this->faker->randomFloat(2, 0.5, 1.0),
        ];
    }
}

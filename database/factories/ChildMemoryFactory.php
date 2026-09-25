<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChildMemoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'child_id' => Child::factory(),
            'user_id' => function (array $attributes) {
                return Child::query()->find($attributes['child_id'])?->user_id ?? User::factory();
            },
            'memory_key' => $this->faker->word(),
            'type' => 'general',
            'content' => $this->faker->sentence(),
            'confidence' => $this->faker->randomFloat(2, 0.5, 1.0),
            'status' => 'active',
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlanFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        return [
            'name'                    => $name,
            'slug'                    => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'type'                    => $this->faker->randomElement(['free', 'pro']),
            'billing_period'          => 'monthly',
            'price'                   => $this->faker->randomFloat(2, 0, 50),
            'currency'                => 'USD',
            'ai_messages_per_day'     => $this->faker->optional()->numberBetween(5, 100),
            'max_children'            => $this->faker->optional()->numberBetween(1, 10),
            'max_documents_per_child' => $this->faker->optional()->numberBetween(3, 20),
            'has_specialist_access'   => false,
            'has_voice_mode'          => false,
            'has_progress_reports'    => false,
            'is_active'               => true,
        ];
    }
}

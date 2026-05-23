<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RafiqNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type'    => 'general',
            'title'   => $this->faker->sentence(4),
            'body'    => $this->faker->paragraph(),
            'data'    => null,
            'read_at' => null,
        ];
    }
}

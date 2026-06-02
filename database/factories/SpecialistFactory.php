<?php

namespace Database\Factories;

use App\Models\Specialist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SpecialistFactory extends Factory
{
    protected $model = Specialist::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $displayName = trim("{$firstName} {$lastName}");
        $specializations = ['autism', 'adhd'];

        return [
            'first_name'         => $firstName,
            'last_name'          => $lastName,
            'display_name'       => $displayName,
            'slug'               => Str::slug($displayName) . '-' . Str::lower(Str::random(6)),
            'title'              => $this->faker->jobTitle(),
            'specialty'          => $specializations[0],
            'bio'                => $this->faker->paragraph(),
            'consultation_fee'   => $this->faker->randomFloat(2, 20, 150),
            'currency'           => 'USD',
            'is_active'          => true,
            'metadata'           => [
                'specializations' => $specializations,
                'languages'       => ['en', 'ar'],
                'is_available'    => true,
            ],
            'rating_avg'         => 0,
            'reviews_count'      => 0,
        ];
    }
}

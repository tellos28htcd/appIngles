<?php

namespace Database\Factories;

use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('###'),
            'name' => mb_strtoupper(fake()->unique()->city()),
            'brand_primary' => '#4361EE',
            'brand_accent' => '#FFC23D',
            'session_capacity' => 5,
            'timezone' => 'America/Mexico_City',
            'currency' => 'MXN',
            'status' => SchoolStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => SchoolStatus::Suspended]);
    }
}

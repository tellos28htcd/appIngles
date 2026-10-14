<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'enrollment_number' => 'TEST'.fake()->unique()->numerify('########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'second_last_name' => fake()->lastName(),
            'mobile_phone' => fake()->numerify('442#######'),
            'email' => fake()->unique()->userName().'@appingles.com',
            'birth_date' => fake()->dateTimeBetween('-40 years', '-15 years'),
            'status' => StudentStatus::Active,
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => ['email' => null]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\ContractType;
use App\Enums\TeacherStatus;
use App\Models\Role;
use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => fn (array $attributes) => User::factory()->create([
                'school_id' => $attributes['school_id'],
                'role_id' => Role::where('slug', Role::TEACHER)->value('id') ?? Role::factory(),
            ])->id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'second_last_name' => fake()->lastName(),
            'contract_type' => ContractType::FullTime,
            'weekly_hours' => 48,
            'status' => TeacherStatus::Active,
        ];
    }
}

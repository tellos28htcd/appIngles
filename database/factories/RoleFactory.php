<?php

namespace Database\Factories;

use App\Enums\RoleScope;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->jobTitle(),
            'description' => null,
            'scope' => RoleScope::School,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function platform(): static
    {
        return $this->state(fn () => ['scope' => RoleScope::Platform]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
